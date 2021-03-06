<?php

namespace OpenDominion\Services\Actions;

use Carbon\Carbon;
use DB;
use Exception;
use OpenDominion\Calculators\Dominion\LandCalculator;
use OpenDominion\Contracts\Calculators\Dominion\Actions\ConstructionCalculator;
use OpenDominion\Exceptions\BadInputException;
use OpenDominion\Exceptions\DominionLockedException;
use OpenDominion\Exceptions\NotEnoughResourcesException;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Models\Dominion;
use OpenDominion\Traits\DominionGuardsTrait;

class ConstructionActionService
{
    use DominionGuardsTrait;

    /** @var ConstructionCalculator */
    protected $constructionCalculator;

    /** @var LandCalculator */
    protected $landCalculator;

    /** @var LandHelper */
    protected $landHelper;

    /**
     * ConstructionActionService constructor.
     *
     * @param ConstructionCalculator $constructionCalculator
     * @param LandCalculator $landCalculator
     * @param LandHelper $landHelper
     */
    public function __construct(ConstructionCalculator $constructionCalculator, LandCalculator $landCalculator, LandHelper $landHelper)
    {
        $this->constructionCalculator = $constructionCalculator;
        $this->landCalculator = $landCalculator;
        $this->landHelper = $landHelper;
    }

    /**
     * Does a construction action for a Dominion.
     *
     * @param Dominion $dominion
     * @param array $data
     * @return array
     * @throws DominionLockedException
     * @throws BadInputException
     * @throws Exception
     * @throws NotEnoughResourcesException
     */
    public function construct(Dominion $dominion, array $data)
    {
        $this->guardLockedDominion($dominion);

        // todo: refactor this
        $this->landCalculator->init($dominion);

        $data = array_map('intval', $data);

        $totalBuildingsToConstruct = array_sum($data);

        if ($totalBuildingsToConstruct === 0) {
            throw new BadInputException;
        }

        $maxAfford = $this->constructionCalculator->getMaxAfford($dominion);

        if ($totalBuildingsToConstruct > $maxAfford) {
            throw new NotEnoughResourcesException;
        }

        foreach ($data as $buildingType => $amount) {
            if ($amount === 0) {
                continue;
            }

            $landType = $this->landHelper->getLandTypeForBuildingByRace($buildingType, $dominion->race);

            if ($amount > $this->landCalculator->getTotalBarrenLandByLandType($landType)) {
                throw new NotEnoughResourcesException;
            }
        }

        $platinumCost = ($this->constructionCalculator->getPlatinumCost($dominion) * $totalBuildingsToConstruct);
        $newPlatinum = ($dominion->resource_platinum - $platinumCost);

        $lumberCost = ($this->constructionCalculator->getLumberCost($dominion) * $totalBuildingsToConstruct);
        $newLumber = ($dominion->resource_lumber - $lumberCost);

        $dateTime = new Carbon;

        DB::beginTransaction();

        try {
            DB::table('dominions')
                ->where('id', $dominion->id)
                ->update([
                    'resource_platinum' => $newPlatinum,
                    'resource_lumber' => $newLumber,
                ]);

            // Check for existing queue
            $existingQueueRows = DB::table('queue_construction')
                ->where([
                    'dominion_id' => $dominion->id,
                    'hours' => 12,
                ])->get(['building', 'amount']);

            foreach ($existingQueueRows as $row) {
                $data[$row->building] += $row->amount;
            }

            foreach ($data as $buildingType => $amount) {
                if ($amount === 0) {
                    continue;
                }

                $where = [
                    'dominion_id' => $dominion->id,
                    'building' => $buildingType,
                    'hours' => 12,
                ];

                $values = [
                    'amount' => $amount,
                    'updated_at' => $dateTime,
                ];

                if ($existingQueueRows->isEmpty()) {
                    $values['created_at'] = $dateTime;
                }

                DB::table('queue_construction')
                    ->updateOrInsert($where, $values);
            }

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return compact('platinumCost', 'lumberCost');
    }
}
