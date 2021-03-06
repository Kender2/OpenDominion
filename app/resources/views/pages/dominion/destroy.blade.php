@extends('layouts.master')

@section('page-header', 'Destroy Buildings')

@section('content')
    <form action="{{ route('dominion.destroy') }}" method="post" role="form">
        {!! csrf_field() !!}

        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="ra ra-robot-arm"></i> Destroy Buildings</h3>
            </div>
            <div class="box-body no-padding">
                <table class="table">
                    <colgroup>
                        <col>
                        <col width="100">
                        <col width="100">
                    </colgroup>

                    @foreach ($buildingHelper->getBuildingTypesByLandType($selectedDominion->race) as $landType => $buildingTypes)

                        @if (empty($buildingTypes))
                            @continue
                        @endif

                        <thead>
                            <tr>
                                <th colspan="3">{{ ucfirst($landType) }} <span class="small">(Barren: {{ number_format($landCalculator->getTotalBarrenLandByLandType($landType)) }})</span></th>
                            </tr>
                            <tr>
                                <th>Building</th>
                                <th class="text-center">Owned</th>
                                <th class="text-center">Destroy</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($buildingTypes as $buildingType)
                                <tr>
                                    <td>
                                        {{ ucwords(str_replace('_', ' ', $buildingType)) }}
                                        {!! $buildingHelper->getBuildingImplementedString($buildingType) !!}
                                    </td>
                                    <td class="text-center">
                                        {{ $selectedDominion->{'building_' . $buildingType} }}
                                        <small>
                                            ({{ number_format((($selectedDominion->{'building_' . $buildingType} / $landCalculator->getTotalLand()) * 100), 1) }}%)
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <input type="number" name="destroy[{{ $buildingType }}]" class="form-control text-center" placeholder="0" min="0" max="{{ $selectedDominion->{'building_' . $buildingType} }}" value="{{ old('destroy.' . $buildingType) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                    @endforeach

                </table>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-danger" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Destroy</button>
            </div>
        </div>
    </form>
@endsection
