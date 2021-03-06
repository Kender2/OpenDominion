@extends('layouts.master')

@section('page-header', 'The Realm')

@section('content')
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-circle-of-circles"></i> {{ $realm->name }} (#{{ $realm->number }})</h3>
                </div>
                <div class="box-body no-padding">

                    <table class="table">
                        <colgroup>
                            <col>
                            <col width="100">
                            <col width="100">
                            <col width="100">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Dominion</th>
                                <th class="text-center">Race</th>
                                <th class="text-center">Land</th>
                                <th class="text-center">Networth</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < 12; $i++)
                                @php
                                    $dominion = $dominions->get($i);

                                    if ($dominion !== null) {
                                        $landCalculator->setDominion($dominion);
                                    }
                                @endphp

                                @if ($dominion === null)
                                    <tr>
                                        <td colspan="4"><i>Vacant</i></td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>
                                            @if ($dominion->id === $selectedDominion->id)
                                                <b>{{ $dominion->name }}</b> (you)
                                            @else
                                                {{ $dominion->name }}
                                            @endif

                                            {{--@if ($dominion->id !== $selectedDominion->id)
                                                <a href="{{ route('dominion.other.status', $dominion->id) }}">{{ $dominion->name }}</a>
                                            @else
                                                <b><a href="{{ route('dominion.status') }}">{{ $dominion->name }}</a></b> (you)
                                            @endif--}}
                                        </td>
                                        <td class="text-center">
                                            {{ $dominion->race->name }}
                                            {{--
                                            todo: fix above statement which generates this query:
                                            select * from "units" where "units"."race_id" = '1' and "units"."race_id" is not null order by "slot" asc limit 4
                                            --}}
                                        </td>
                                        <td class="text-center">{{ number_format($landCalculator->getTotalLand()) }}</td>
                                        <td class="text-center">{{ number_format($dominion->networth) }}</td>
                                    </tr>
                                @endif
                            @endfor
                        </tbody>
                    </table>

                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                </div>
                <div class="box-body">
                    <p>This is the realm <strong>{{ $realm->name }} (#{{ $realm->number }})</strong>.</p>
                    <p>Its alignment is <strong>{{ $realm->alignment }}</strong>, it contains <strong>{{ $dominions->count() }}</strong> dominion(s) and its networth is <strong>{{ number_format($networthCalculator->getRealmNetworth($realm)) }}</strong>.</p>
                    {{-- todo: change this to a table? --}}
                </div>
                @if (($prevRealm !== null) || ($nextRealm !== null))
                    <div class="box-footer">
                        <div class="row">
                            <div class="col-xs-4">
                                @if ($prevRealm !== null)
                                    <a href="{{ route('dominion.realm', $prevRealm->id) }}">&lt; {{ $prevRealm->name }} (# {{  $prevRealm->number }})</a>
                                @endif
                            </div>
                            <div class="col-xs-4">
                                <form action="{{ route('dominion.realm.change-realm') }}" method="post" role="form">
                                    {!! csrf_field() !!}
                                    <input type="number" name="realm" class="form-control text-center" placeholder="{{ $realm->number }}">
                                </form>
                            </div>
                            <div class="col-xs-4 text-right">
                                @if ($nextRealm !== null)
                                    <a href="{{ route('dominion.realm', $nextRealm->id) }}">{{ $nextRealm->name }} (# {{  $nextRealm->number }}) &gt;</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection
