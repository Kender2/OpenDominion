<?php

namespace OpenDominion\Console\Commands;

use Carbon\Carbon;
use Config;
use DB;
use Exception;
use Illuminate\Console\Command;
use Log;
use OpenDominion\Calculators\Dominion\PopulationCalculator;
use OpenDominion\Calculators\Dominion\ProductionCalculator;
use OpenDominion\Calculators\NetworthCalculator;
use OpenDominion\Interfaces\DependencyInitializableInterface;
use OpenDominion\Interfaces\DominionInitializableInterface;
use OpenDominion\Models\Dominion;
use OpenDominion\Repositories\DominionRepository;

// todo: refactor this class

class GameTickCommand extends Command
{
    /** @var string The name and signature of the console command */
    protected $signature = 'game:tick';

    /** @var string The console command description */
    protected $description = 'Ticks the game';

    /** @var string */
    protected $databaseDriver;

    /** @var int[] */
    protected $dominionsIdsToUpdate = [];

    /** @var DominionRepository */
    protected $dominions;

    /** @var PopulationCalculator */
    protected $populationCalculator;

    /** @var ProductionCalculator */
    protected $productionCalculator;

    /** @var NetworthCalculator */
    protected $networthCalculator;

    /**
     * GameTickCommand constructor.
     *
     * @param DominionRepository $dominions
     */
    public function __construct(DominionRepository $dominions)
    {
        parent::__construct();

        $this->dominions = $dominions;
        $this->populationCalculator = app(PopulationCalculator::class);
        $this->productionCalculator = app(ProductionCalculator::class);
        $this->networthCalculator = app(NetworthCalculator::class);
    }

    /**
     * Execute the game:tick console command.
     *
     * @throws Exception
     */
    public function handle()
    {
        $this->setDatabaseDriver();

        $this->initCalculatorDependencies();

        Log::debug('Tick started');

        DB::beginTransaction();

        $this->fetchDominionsToUpdate();

        $this->tickDominionResources();
        // todo: Population (peasants & draftees)
        $this->tickDominionMorale();
        $this->tickDominionSpyStrength();
        $this->tickDominionWizardStrength();

        // note: queues get processed even if a dominion is locked. this is to keep the queue tables somewhat clean
        // might produce some unwanted results in the future
        $this->tickExplorationQueue();
        $this->tickConstructionQueue();
        // todo: Military training queue
        // todo: Military returning queue
        // todo: Magic queue
        $this->tickDominionNetworth();

        DB::commit();

        Log::info('Tick');
    }

    /**
     * Saves the database driver name so we can easily reference it later when we need driver-specific SQL.
     *
     * Only MySQL and Sqlite are supported at this time.
     *
     * @throws Exception
     */
    public function setDatabaseDriver()
    {
        $connector = Config::get('database.default');
        $driver = Config::get("database.connections.{$connector}.driver");

        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new Exception("Database driver {$driver} not supported for game:tick command :(");
        }

        $this->databaseDriver = $driver;
    }

    public function fetchDominionsToUpdate()
    {
        $this->dominionsIdsToUpdate = DB::table('dominions')
            ->select('dominions.id')
            ->join('rounds', function ($join) {
                $join->on('rounds.id', '=', 'dominions.round_id');
            })
            ->where('rounds.start_date', '<=', Carbon::now())
            ->where('rounds.end_date', '>', Carbon::now())
            ->get()
            ->map(function ($dominion) {
                return $dominion->id;
            })
            ->toArray();
    }

    /**
     * Ticks dominion resources.
     */
    public function tickDominionResources()
    {
        Log::debug('Tick resources started');

        // todo: fetch only non-locked dominions
        $dominions = $this->dominions->findWhereIn('id', $this->dominionsIdsToUpdate);

        foreach ($dominions as $dominion) {
            /** @var $dominion Dominion */
            $this->initCalculatorsForDominion($dominion);

            // Resources
            $dominion->resource_platinum += $this->productionCalculator->getPlatinumProduction();
            $dominion->resource_food += $this->productionCalculator->getFoodNetChange();
            // todo: if food < 0 then food = 0?
            $dominion->resource_lumber += $this->productionCalculator->getLumberNetChange();
            // todo: if lumber < 0 then lumber = 0?
            $dominion->resource_mana += $this->productionCalculator->getManaNetChange();
            // todo: if mana < 0 then mana = 0?
            $dominion->resource_ore += $this->productionCalculator->getOreProduction();
            $dominion->resource_gems += $this->productionCalculator->getGemProduction();
            $dominion->resource_boats += $this->productionCalculator->getBoatProduction();

            // Population
            $populationPeasantGrowth = $this->populationCalculator->getPopulationPeasantGrowth();

            $dominion->peasants += $populationPeasantGrowth;
            $dominion->peasants_last_hour = $populationPeasantGrowth;
            $dominion->military_draftees += $this->populationCalculator->getPopulationDrafteeGrowth();

            $dominion->save();
        }

        $affected = $dominions->count();

        Log::debug("Ticked resources, {$affected} dominion(s) affected");
    }

    /**
     * Ticks dominion morale.
     */
    public function tickDominionMorale()
    {
        Log::debug('Tick morale started');

        $sql = null;
        $bindings = [
            'moraleThreshold' => 70,
            'moraleAddedBelowThreshold' => 6,
            'moraleAddedAboveThreshold' => 3,
        ];

        switch ($this->databaseDriver) {
            case 'sqlite':
                $sql = ('UPDATE `dominions` SET `morale` = MIN(100, (`morale` + (CASE WHEN (`morale` < :moraleThreshold) THEN :moraleAddedBelowThreshold ELSE :moraleAddedAboveThreshold END))) WHERE `morale` < 100 AND `id` IN (' . implode(', ', $this->dominionsIdsToUpdate) . ');');
                break;

            case 'mysql':
                $sql = ('UPDATE `dominions` SET `morale` = LEAST(100, (`morale` + IF(`morale` < :moraleThreshold, :moraleAddedBelowThreshold, :moraleAddedAboveThreshold))) WHERE `morale` < 100 AND `id` IN (' . implode(', ', $this->dominionsIdsToUpdate) . ');');
                break;
        }

        $affected = DB::update($sql, $bindings);

        Log::debug("Ticked morale, {$affected} dominion(s) affected");
    }

    /**
     * Ticks dominion spy strength.
     */
    public function tickDominionSpyStrength()
    {
        Log::debug('Tick spy strength started');

        $sql = null;
        $bindings = [
            'spyStrengthAdded' => 4, // todo: get values from EspionageCalculator for Spy Master and Dark Artistry techs
        ];

        switch ($this->databaseDriver) {
            case 'sqlite':
                $sql = ('UPDATE `dominions` SET `spy_strength` = MIN(100, `spy_strength` + :spyStrengthAdded) WHERE `spy_strength` < 100 AND `id` IN (' . implode(', ', $this->dominionsIdsToUpdate) . ');');
                break;

            case 'mysql':
                $sql = ('UPDATE `dominions` SET `spy_strength` = LEAST(100, `spy_strength` + :spyStrengthAdded) WHERE `spy_strength` < 100 AND `id` IN (' . implode(', ', $this->dominionsIdsToUpdate) . ');');
                break;
        }

        $affected = DB::update($sql, $bindings);

        Log::debug("Ticked spy strength, {$affected} dominion(s) affected");
    }

    /**
     * Ticks dominion wizard strength.
     */
    public function tickDominionWizardStrength()
    {
        Log::debug('Tick wizard strength started');

        $sql = null;
        $bindings = [
            'wizardStrengthAdded' => 5, // todo: get values from SpellCalculator for Master of Magi and Dark Artistry techs
        ];

        switch ($this->databaseDriver) {
            case 'sqlite':
                $sql = ('UPDATE `dominions` SET `wizard_strength` = MIN(100, `wizard_strength` + :wizardStrengthAdded) WHERE `wizard_strength` < 100 AND `id` IN (' . implode(', ', $this->dominionsIdsToUpdate) . ');');
                break;

            case 'mysql':
                $sql = ('UPDATE `dominions` SET `wizard_strength` = LEAST(100, `wizard_strength` + :wizardStrengthAdded) WHERE `wizard_strength` < 100 AND `id` IN (' . implode(', ', $this->dominionsIdsToUpdate) . ');');
                break;
        }

        $affected = DB::update($sql, $bindings);

        Log::debug("Ticked wizard strength, {$affected} dominion(s) affected");
    }

    /**
     * Ticks dominion exploration queue.
     */
    public function tickExplorationQueue()
    {
        Log::debug('Tick exploration queue');

        // Two-step process to avoid getting UNIQUE constraint integrity errors since we can't reliably use deferred
        // transactions, deferred update queries or update+orderby cross-database
        DB::table('queue_exploration')->where('hours', '>', 0)->update([
            'hours' => DB::raw('-(`hours` - 1)'),
        ]);
        $affectedUpdated = DB::table('queue_exploration')->where('hours', '<', 0)->update([
            'hours' => DB::raw('-`hours`'),
            'updated_at' => new Carbon(),
        ]);

        $rows = DB::table('queue_exploration')->where('hours', 0)->get();

        foreach ($rows as $row) {
            DB::table('dominions')->where('id', $row->dominion_id)->update([
                "land_{$row->land_type}" => DB::raw("`land_{$row->land_type}` + {$row->amount}"),
            ]);
        }

        $affectedFinished = DB::table('queue_exploration')->where('hours', 0)->delete();

        $affectedUpdated -= $affectedFinished;

        Log::debug("Ticked exploration queue, {$affectedUpdated} updated, {$affectedFinished} finished");
    }

    /**
     * Ticks dominion construction queue.
     */
    public function tickConstructionQueue()
    {
        Log::debug('Tick construction queue');

        // Two-step process to avoid getting UNIQUE constraint integrity errors since we can't reliably use deferred
        // transactions, deferred update queries or update+orderby cross-database
        DB::table('queue_construction')->where('hours', '>', 0)->update([
            'hours' => DB::raw('-(`hours` - 1)'),
        ]);
        $affectedUpdated = DB::table('queue_construction')->where('hours', '<', 0)->update([
            'hours' => DB::raw('-`hours`'),
            'updated_at' => new Carbon(),
        ]);

        $rows = DB::table('queue_construction')->where('hours', 0)->get();

        foreach ($rows as $row) {
            DB::table('dominions')->where('id', $row->dominion_id)->update([
                "building_{$row->building}" => DB::raw("`building_{$row->building}` + {$row->amount}"),
            ]);
        }

        $affectedFinished = DB::table('queue_construction')->where('hours', 0)->delete();

        $affectedUpdated -= $affectedFinished;

        Log::debug("Ticked construction queue, {$affectedUpdated} updated, {$affectedFinished} finished");
    }

    /**
     * Ticks dominion networth.
     */
    public function tickDominionNetworth()
    {
        Log::debug('Tick dominion networth');

        $dominions = $this->dominions->findWhereIn('id', $this->dominionsIdsToUpdate);

        foreach ($dominions as $dominion) {
            /** @var $dominion Dominion */
            $this->initCalculatorsForDominion($dominion);

            $dominion->networth = $this->networthCalculator->getDominionNetworth($dominion);
            $dominion->save();
        }
    }

    /**
     * Initialize calculator dependencies.
     */
    protected function initCalculatorDependencies()
    {
        foreach (app()->tagged('initializableCalculators') as $calculator) {
            /** @var DependencyInitializableInterface $calculator */
            $calculator->initDependencies();
        }
    }

    /**
     * Initialize calculators with dominion.
     *
     * @param Dominion $dominion
     */
    protected function initCalculatorsForDominion(Dominion $dominion)
    {
        foreach (app()->tagged('dominionCalculators') as $calculator) {
            /** @var DominionInitializableInterface $calculator */
            $calculator->init($dominion);
        }
    }
}
