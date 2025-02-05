@extends('layouts.app')

@php
    use Carbon\Carbon;
    use App\Services\BinanceApiService;
    use App\CommonHelpers;
    use App\Services\MarketTrendService;

@endphp

@section('content')
    <div class="container">
        <h1 class="text-center mb-4">Shortlisted Coins for Future Trade</h1>
        <table class="table">
            <thead>
                <tr>
                    <th>Coin</th>
                    <th>Current Price</th>
                    <th>Current Resistance</th>
                    <th>Current Support</th>
                    <th>Trigger Long</th>
                    <th>Trigger Short</th>
                    <th>State</th>
                    <th>Update Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coins as $coin)
                    @php
                        $updateTime = Carbon::parse($coin->created_at)->setTimezone('GMT');
                        $current_price = BinanceApiService::getCurrentPrice($coin->symbol, 'FUTURE');
                        $supportResistance = MarketTrendService::getCurrentSupportResistanceValue(
                            $coin->symbol,
                            '5m',
                            'FUTURE',
                            [5],
                        );
                        $current_resistance = $supportResistance[5]['resistance'];
                        $current_support = $supportResistance[5]['support'];
                        $trigger_long = $current_resistance * (1 + 1.5 / 100);
                        $trigger_short = $current_support * (1 - 1.5 / 100);
                        $state = 'Idle';
                        if ($coin->priceLock != 0) {
                            $state = 'Price Locked';
                        } elseif (
                            DB::table('live_trades_future_results')
                                ->where('symbol', $coin->symbol)
                                ->where('trade_acc', auth()->user()->id)
                                ->where('trade_status', 'open')
                                ->first()
                        ) {
                            $state = 'Trade Open';
                        }

                        CommonHelpers::delayMs(100);

                    @endphp
                    <tr>
                        <td>{{ $coin->symbol ?? '-' }}</td>
                        <td>${{ number_format($current_price, 8) ?? '-' }}</td>
                        <td>${{ number_format($current_resistance, 8) ?? '-' }}</td>
                        <td>${{ number_format($current_support, 8) ?? '-' }}</td>
                        <td>${{ number_format($trigger_long, 8) ?? '-' }}</td>
                        <td>${{ number_format($trigger_short, 8) ?? '-' }}</td>
                        <td>{{ $state }}</td>

                        <td>{{ $updateTime->format('H:i:s , M d, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
