@extends('layouts.master')

@section('page-header', 'Military')

@section('content')
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-sword"></i> Military</h3>
                </div>
                <form action="{{ route('dominion.military.train') }}" method="post" role="form">
                    {!! csrf_field() !!}
                    <div class="box-body no-padding">
                        <table class="table">
                            <colgroup>
                                <col>
                                <col width="100">
                                <col width="100">
                                <col width="150">
                                <col width="150">
                                <col width="100">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Unit</th>
                                    <th class="text-center">Trained</th>
                                    <th class="text-center">Training</th>
                                    <th class="text-center">Cost Per Unit</th>
                                    <th class="text-center">Max Trainable</th>
                                    <th class="text-center">Train</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($unitHelper->getUnitTypes() as $unitType)
                                    <tr>
                                        <td>{{ $unitHelper->getUnitName($unitType, $selectedDominion->race) }}</td>
                                        <td class="text-center">{{ number_format($selectedDominion->{'military_' . $unitType}) }}</td>
                                        <td class="text-center">NYI</td>
                                        <td class="text-center">
                                            @php
                                            // todo: move this shit to view presenter or something
                                            $labelParts = [];

                                            foreach ($militaryTrainingCostPerUnit[$unitType] as $costType => $value) {
                                                switch ($costType) {
                                                    case 'platinum':
                                                        $labelParts[] = "{$value}p";
                                                        break;

                                                    case 'ore':
                                                        $labelParts[] = "{$value}r";
                                                        break;

                                                    case 'wizards':
                                                        $labelParts[] = 'Wizard';
                                                        break;

                                                    default:
                                                        break;
                                                }
                                            }

                                            echo implode(', ', $labelParts);
                                            @endphp
                                        </td>
                                        <td class="text-center">{{ number_format($militaryMaxTrainable[$unitType]) }}</td>
                                        <td class="text-center">
                                            <input type="number" name="train[0]" class="form-control text-center" placeholder="0" min="0" max="0" value="" disabled>
                                        </td>
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary" disabled>Train</button>
                        <div class="pull-right">
                            You have {{ number_format($selectedDominion->military_draftees) }} draftees available to train.
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Statistics</h3>
                </div>
                <div class="box-body no-padding">
                    <table class="table">
                        <colgroup>
                            <col width="50%">
                            <col width="50%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="text-center">Population</th>
                                <th class="text-center">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center">Peasants</td>
                                <td class="text-center">
                                    {{ number_format($selectedDominion->peasants) }} ({{ number_format($populationCalculator->getPopulationPeasantPercentage(), 2) }}%)
                                </td>
                            </tr>
                            <tr>
                                <td class="text-center">Military</td>
                                <td class="text-center">
                                    {{ number_format($populationCalculator->getPopulationMilitary()) }} ({{ number_format($populationCalculator->getPopulationMilitaryPercentage(), 2) }}%)
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Draftees</h3>
                </div>
                <form action="{{ route('dominion.military.change-draft-rate') }}" method="post" role="form">
                    {!! csrf_field() !!}
                    <div class="box-body no-padding">
                        <table class="table">
                            <colgroup>
                                <col width="50%">
                                <col width="50%">
                            </colgroup>
                            <tbody>
                                <tr>
                                    <td class="text-center">Draft Rate:</td>
                                    <td class="text-center">
                                        <input type="number" name="draft_rate" class="form-control text-center" style="display: inline-block; width: 80px;" placeholder="0" min="0" max="100" value="{{ $selectedDominion->draft_rate }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}> %
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Change</button>
                    </div>
                </form>
            </div>

        </div>

    </div>
@endsection
