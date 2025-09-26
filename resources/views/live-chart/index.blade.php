@extends('layouts.app')

@section('content')
    <div>
        <x-candlestick-chart 
            :data="$data" 
            symbol="{{ $symbol }}" 
            interval="{{ $interval }}" 
            :indicators="[]"
            :markers="$labelPlots" 
            :trades="$zonePlots" 
            :lines="$linePlots" 
        />
    </div>

    <div class="mt-5">
        <h3>Failed Setups</h3>
        <table class="table table-bordered table-dark table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Symbol</th>
                    <th>Interval</th>
                    <th>Direction</th>
                    <th>TP</th>
                    <th>SL</th>
                    <th>Trigger Price</th>
                    <th>Status</th>
                    <th>Candle Time</th>
                    <th>Timestamp</th>
                    <th>Strategy</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($failedSetups as $setup)
                    <tr>
                        <td>{{ $setup->id }}</td>
                        <td>{{ $setup->symbol }}</td>
                        <td>{{ $setup->interval }}</td>
                        <td>{{ $setup->direction }}</td>
                        <td>{{ $setup->tp }}</td>
                        <td>{{ $setup->sl }}</td>
                        <td>{{ $setup->trigger_price }}</td>
                        <td>{{ $setup->status }}</td>
                        <td>{{ \Carbon\Carbon::createFromTimestampMs($setup->candle_timestamp)->toDateTimeString() }}</td>
                        <td>{{ \Carbon\Carbon::createFromTimestampMs($setup->timestamp)->toDateTimeString() }}</td>
                        <td>{{ $setup->strategy_name }}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#details-{{ $setup->id }}">
                                View
                            </button>
                        </td>
                    </tr>
                    <tr class="collapse bg-dark" id="details-{{ $setup->id }}">
                        <td colspan="12">
                            <div class="p-3 text-light">
                                <h6>Failure Reason</h6>
                                <div class="text-danger mb-3">
                                    {{ json_decode($setup->faliure_reason, true)['opened_order'] ?? $setup->faliure_reason }}
                                </div>

                                <h6>Zones</h6>
                                <pre class="bg-dark p-2 rounded">{{ json_encode(json_decode($setup->zones), JSON_PRETTY_PRINT) }}</pre>

                                <h6>Current Zone</h6>
                                <pre class="bg-dark p-2 rounded">{{ json_encode(json_decode($setup->current_zone), JSON_PRETTY_PRINT) }}</pre>

                                <h6>Trendline</h6>
                                <pre class="bg-dark p-2 rounded">{{ json_encode(json_decode($setup->trendline), JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center">No failed setups found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
