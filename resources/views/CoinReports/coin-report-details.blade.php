@extends('layouts.app')

@php
    use App\CommonHelpers;
    $buyTriggers = [];
    $sellTriggers = [];
    $confirmTriggers = [];
    $highestTriggers = [];
    $lowestTriggers = [];
    $liquidationTriggers = [];
    $oneHourMarks = [];
    $position = request()->get('position');
    $supportTriggers = [];
    $resistanceTriggers = [];

@endphp

@section('content')
    <style>
        .table-responsive {
            overflow-x: auto;
        }

        .tablesorter th:hover {
            cursor: pointer;
            background: rgba(255, 255, 255, 0.1);
        }

        .bg-dark {
            background-color: #1e1e2f !important;
        }

        .text-success {
            color: #00f2c3 !important;
        }

        .text-danger {
            color: #fd5d93 !important;
        }

        #candle-comparison td,
        #candle-comparison th {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        #candle-comparison thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
    </style>
    <h2 class="mb-4 text-white">
        @if (isset($position))
            @if (strtoupper($position) == 'LONG')
                <i class="fa fa-arrow-up text-success" title="Long Position"></i>
            @elseif(strtoupper($position) == 'SHORT')
                <i class="fa fa-arrow-down text-danger" title="Short Position"></i>
            @endif
        @endif
        Trade Details for {{ $symbol }} - {{ $interval }} ({{ $market }})
    </h2>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-0">Candlestick Chart</h4>
                        <small class="text-muted">Visual representation of trade data</small>
                    </div>
                    <div>
                        <button class="btn btn-warning btn-sm me-2" onclick="resetZoom()">Reset Zoom</button>
                        <button id="gridToggleBtn" class="btn btn-outline-info btn-sm" onclick="toggleGrid()">Hide
                            Grid</button>
                        <a target="_blank" href="https://www.binance.com/en/futures/{{ request('symbol') }}"
                            class="btn btn-outline-primary btn-sm">Show on Binance</a>



                    </div>



                </div>


                <div class="card-body">
                    <canvas id="candlestickChart" style="height: 800px;"></canvas>
                </div>
            </div>
        </div>
    </div>


    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-primary">
                    <h4 class="card-title ">Trade Details</h4>
                    <p class="card-category"> Here is a subtitle for this table</p>
                </div>

                <div class="card-body">


                    <div class="table-responsive">
                        <table class="table">
                            <thead class="text-primary">
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Buying Price (USDT)</th>

                                    <th>Extreme Price (USDT)</th>
                                    <th>Selling Price (USDT)</th>
                                    <th>Buying Time</th>

                                    <th>Selling Time</th>
                                    <th>Profit (%)</th>

                                    <th>Nearby Trades</th>

                                    <th>Duration (mins)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trades as $indexTrades => $trade)
                                    @php

                                        $buyCandle = json_decode(json_encode($trade->buyingCandle), true);
                                        $sellCandle = json_decode(json_encode($trade->sellingCandle), true);

                                        $confirmCandle = json_decode(json_encode($trade->confirmCandle), true);
                                        $highestCandle = json_decode(json_encode($trade->highestCandle), true);

                                        if ($confirmCandle && $highestCandle) {
                                            $confirmTriggers[] = $confirmCandle['binance_timestamp'];
                                            $highestTriggers[] = $highestCandle['binance_timestamp'];
                                        }

                                        $buyTriggers[] = $buyCandle['binance_timestamp'];
                                        $sellTriggers[] = $sellCandle['binance_timestamp'];

                                        $supportTriggers[] = [
                                            'timestamp' => $buyCandle['timestamp'],
                                            'value' => $buyCandle['currentSupport'],
                                        ];

                                        $supportTriggers[] = [
                                            'timestamp' => $sellCandle['timestamp'],
                                            'value' => $sellCandle['currentSupport'],
                                        ];

                                        $resistanceTriggers[] = [
                                            'timestamp' => $buyCandle['timestamp'],
                                            'value' => $buyCandle['currentResistance'],
                                        ];

                                        $resistanceTriggers[] = [
                                            'timestamp' => $sellCandle['timestamp'],
                                            'value' => $sellCandle['currentResistance'],
                                        ];
                                        $lowestIndex = -1;
                                        $lowestPrice = $data[0]['close'];
                                        $oneHourCounter = 0;
                                        foreach ($data as $index => $candle) {
                                            if ($candle['binance_timestamp'] == $buyCandle['binance_timestamp']) {
                                                $lowestIndex = $index;
                                                $lowestPrice = $candle['close'];
                                            }

                                            if (
                                                $candle['binance_timestamp'] > $buyCandle['binance_timestamp'] &&
                                                $candle['binance_timestamp'] <= $sellCandle['binance_timestamp']
                                            ) {
                                                if ($trade->position == 'LONG') {
                                                    if ($lowestPrice > $candle['low']) {
                                                        $lowestPrice = $candle['low'];
                                                        $lowestIndex = $index;
                                                    }
                                                } elseif ($trade->position == 'SHORT') {
                                                    if ($lowestPrice < $candle['high']) {
                                                        $lowestPrice = $candle['high'];
                                                        $lowestIndex = $index;
                                                    }
                                                }

                                                if ($oneHourCounter == 12) {
                                                    $oneHourMarks[] = $candle['binance_timestamp'];
                                                    $oneHourCounter = 0;
                                                } else {
                                                    $oneHourCounter++;
                                                }
                                            }
                                        }

                                        if ($lowestIndex == -1) {
                                            $lowestIndex = 0;
                                        }
                                        $lowestTriggers[] = $data[$lowestIndex]['binance_timestamp'];
                                        $lowestCandle = $data[$lowestIndex];

                                        $timestamp = $buyCandle['timestamp'];

                                        // Parse the timestamp using Carbon
                                        $carbonTimestamp = Carbon\Carbon::parse($timestamp);

                                        // Calculate 5 minutes before and after the timestamp
                                        $fiveMinutesBefore = $carbonTimestamp->copy()->subMinutes(5);

                                        $fiveMinutesAfter = $carbonTimestamp->copy()->addMinutes(5);
                                        // dd($fiveMinutesBefore->format('Y-m-d H:i:s'),$timestamp,$fiveMinutesAfter->format('Y-m-d H:i:s'));

                                        $nearbyTrades = DB::table('coin_reports')
                                            ->where('symbol', '!=', $trade->symbol)
                                            ->where('market', $market)
                                            ->where('market', $trade->formula)
                                            ->where('interval', $interval)

                                            ->whereBetween('buyingCandle->timestamp', [
                                                $fiveMinutesBefore->format('Y-m-d H:i:s'),
                                                $fiveMinutesAfter->format('Y-m-d H:i:s'),
                                            ])
                                            ->get();
                                    @endphp


                                    <tr @if ($trade->profit < 0) class="bg-danger" @endif>
                                        <td>
                                            {{ $indexTrades + 1 }}

                                            {{-- @if ($confirmCandle['binance_timestamp'] == $buyCandle['binance_timestamp'])
                                                <i class="fa fa-exclamation-circle text-warning" aria-hidden="true"></i>
                                            @endif --}}
                                        </td>
                                        <td>{{ number_format($trade->buyingPrice, 4) }}</td>

                                        <td>{{ number_format($trade->lowestPrice, 4) }}
                                            ({{ number_format($trade->lowestPricePercentage, 2) }}%)
                                        </td>
                                        <td>{{ number_format($trade->sellingPrice, 4) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($buyCandle['timestamp'])->format('h:i A') }}
                                        </td>

                                        <td>{{ \Carbon\Carbon::parse($sellCandle['timestamp'])->format('h:i A') }}
                                        </td>
                                        <td>{{ number_format($trade->profit, 2) }}%</td>
                                        <td>{{ $nearbyTrades->count() }}</td>
                                        <td>{{ $trade->duration }}</td>
                                        <td>
                                            <button class="btn btn-info btn-sm"
                                                onclick="window.showDetails({{ $trade->id }},this)">Show
                                                Details</button>
                                            {{-- @if ($trade->snapshot_id)
                                                <a target="_blank" class="btn btn-info btn-sm"
                                                    href="{{ route('order-book.show', $trade->snapshot_id) }}">Show
                                                    Trigger</a>
                                            @endif --}}
                                        </td>
                                    </tr>


                                    <tr id="details-{{ $trade->id }}" class="trade-details d-none">
                                        <td colspan="10">
                                            <!-- Main Trade Details Container -->
                                            <div class="card bg-dark shadow mb-4">
                                                <div class="card-header bg-gradient-primary p-3">
                                                    <h5 class="mb-0 text-white d-flex align-items-center">
                                                        <i class="fas fa-chart-line me-2"></i> Trade Analysis Dashboard
                                                    </h5>
                                                </div>

                                                <div class="card-body p-0 bg-dark text-white">
                                                    <!-- Buy & Sell Candle Indicators Section -->
                                                    <div class="row g-0">
                                                        @php
                                                        
                                                                $buy = $buyCandle;
                                                                $sell = $sellCandle;

                                                                // if(!$buy || !$sell){
                                                                //     dd('Test');
                                                                // }
                                                                $searchCandle = CommonHelpers::getCandleFromData(
                                                                    $data,
                                                                    $buy['binance_timestamp'],
                                                                );

                                                                $index = $searchCandle['index'];

                                                                $currentCandle = $data[$index];
                                                                $prevCandle = $data[$index - 1];

                                                                $bollAnalysis = CommonHelpers::analyzeBollingerBandSwing(
                                                                    $data,
                                                                    $index,
                                                                    10,
                                                                );
                                                            
                                                        @endphp
                                                        <div class="card">
                                                            <div class="card-header ">
                                                                <h5 class="card-title">Bollinger Band Analysis</h5>
                                                            </div>
                                                            <div class="card-body">
                                                                <table class="w-100 tablesorter table-hover">
                                                                    <tbody>
                                                                        <tr>
                                                                            <th scope="row">Signal</th>
                                                                            <td>{{ $bollAnalysis['signal'] }}</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">Long Probability</th>
                                                                            <td>{{ $bollAnalysis['long_probability'] }}%
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">Short Probability</th>
                                                                            <td>{{ $bollAnalysis['short_probability'] }}%
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">BB Width</th>
                                                                            <td>{{ $bollAnalysis['bb_width'] }}</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">BB Width Change</th>
                                                                            <td>{{ $bollAnalysis['bb_width_change'] }}%
                                                                                @if ($bollAnalysis['is_contracting'])
                                                                                    <span
                                                                                        class="badge badge-warning">Contracting</span>
                                                                                @elseif ($bollAnalysis['is_expanding'])
                                                                                    <span
                                                                                        class="badge badge-success">Expanding</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">%B</th>
                                                                            <td>{{ $bollAnalysis['percent_b'] }}%</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">Upper % Change</th>
                                                                            <td>{{ $bollAnalysis['bb_upper_percent_change'] }}%
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">Middle % Change</th>
                                                                            <td>{{ $bollAnalysis['bb_middle_percent_change'] }}%
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">Lower % Change</th>
                                                                            <td>{{ $bollAnalysis['bb_lower_percent_change'] }}%
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">Squeeze</th>
                                                                            <td>
                                                                                @if ($bollAnalysis['bb_squeeze'])
                                                                                    <span
                                                                                        class="badge badge-info">Yes</span>
                                                                                @else
                                                                                    <span
                                                                                        class="badge badge-secondary">No</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">Price Action</th>
                                                                            <td>
                                                                                Up:
                                                                                {{ $bollAnalysis['price_action']['upward_momentum'] }},
                                                                                Down:
                                                                                {{ $bollAnalysis['price_action']['downward_momentum'] }}<br>
                                                                                @if ($bollAnalysis['price_action']['is_near_upper_band'])
                                                                                    <span class="badge badge-danger">Near
                                                                                        Upper Band</span>
                                                                                @endif
                                                                                @if ($bollAnalysis['price_action']['is_near_lower_band'])
                                                                                    <span class="badge badge-primary">Near
                                                                                        Lower Band</span>
                                                                                @endif
                                                                                @if ($bollAnalysis['price_action']['crossed_upper_band'])
                                                                                    <span class="badge badge-danger">Crossed
                                                                                        Upper</span>
                                                                                @endif
                                                                                @if ($bollAnalysis['price_action']['crossed_lower_band'])
                                                                                    <span
                                                                                        class="badge badge-primary">Crossed
                                                                                        Lower</span>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <th scope="row">Message</th>
                                                                            <td>{{ $bollAnalysis['message'] }}</td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>

                                                        <div class="card">
                                                            <div class="card-header">
                                                                <h4 class="card-title">Candle Data Comparison</h4>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="table-responsive">
                                                                    <table class="w-100 tablesorter table-hover"
                                                                        id="candle-comparison">
                                                                        <thead class="text-primary">
                                                                            <tr>
                                                                                <th>Indicator</th>
                                                                                <th>Previous Value</th>
                                                                                <th>Current Value</th>
                                                                                <th>% Change</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <!-- Basic price data -->
                                                                            <tr>
                                                                                <td>Open</td>
                                                                                <td>{{ $prevCandle['open'] }}</td>
                                                                                <td>{{ $currentCandle['open'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['open'], $currentCandle['open'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['open'], $currentCandle['open'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>High</td>
                                                                                <td>{{ $prevCandle['high'] }}</td>
                                                                                <td>{{ $currentCandle['high'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['high'], $currentCandle['high'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['high'], $currentCandle['high'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>Low</td>
                                                                                <td>{{ $prevCandle['low'] }}</td>
                                                                                <td>{{ $currentCandle['low'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['low'], $currentCandle['low'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['low'], $currentCandle['low'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>Close</td>
                                                                                <td>{{ $prevCandle['close'] }}</td>
                                                                                <td>{{ $currentCandle['close'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['close'], $currentCandle['close'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['close'], $currentCandle['close'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>Volume</td>
                                                                                <td>{{ $prevCandle['volume'] }}</td>
                                                                                <td>{{ $currentCandle['volume'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['volume'], $currentCandle['volume'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['volume'], $currentCandle['volume'], true), 2) }}%
                                                                                </td>
                                                                            </tr>

                                                                            <!-- Moving Averages -->
                                                                            <tr class="bg-dark">
                                                                                <td colspan="4" class="font-weight-bold">
                                                                                    Moving Averages</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>MA7</td>
                                                                                <td>{{ $prevCandle['ma7'] }}</td>
                                                                                <td>{{ $currentCandle['ma7'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['ma7'], $currentCandle['ma7'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['ma7'], $currentCandle['ma7'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>MA14</td>
                                                                                <td>{{ $prevCandle['ma14'] }}</td>
                                                                                <td>{{ $currentCandle['ma14'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['ma14'], $currentCandle['ma14'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['ma14'], $currentCandle['ma14'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>MA25</td>
                                                                                <td>{{ $prevCandle['ma25'] }}</td>
                                                                                <td>{{ $currentCandle['ma25'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['ma25'], $currentCandle['ma25'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['ma25'], $currentCandle['ma25'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>MA99</td>
                                                                                <td>{{ $prevCandle['ma99'] }}</td>
                                                                                <td>{{ $currentCandle['ma99'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['ma99'], $currentCandle['ma99'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['ma99'], $currentCandle['ma99'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>EMA12</td>
                                                                                <td>{{ $prevCandle['ema12'] }}</td>
                                                                                <td>{{ $currentCandle['ema12'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['ema12'], $currentCandle['ema12'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['ema12'], $currentCandle['ema12'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>EMA26</td>
                                                                                <td>{{ $prevCandle['ema26'] }}</td>
                                                                                <td>{{ $currentCandle['ema26'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['ema26'], $currentCandle['ema26'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['ema26'], $currentCandle['ema26'], true), 2) }}%
                                                                                </td>
                                                                            </tr>

                                                                            <!-- Bollinger Bands -->
                                                                            <tr class="bg-dark">
                                                                                <td colspan="4" class="font-weight-bold">
                                                                                    Bollinger Bands</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>BB Middle</td>
                                                                                <td>{{ $prevCandle['bb_middle'] }}</td>
                                                                                <td>{{ $currentCandle['bb_middle'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['bb_middle'], $currentCandle['bb_middle'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['bb_middle'], $currentCandle['bb_middle'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>BB Upper</td>
                                                                                <td>{{ $prevCandle['bb_upper'] }}</td>
                                                                                <td>{{ $currentCandle['bb_upper'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['bb_upper'], $currentCandle['bb_upper'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['bb_upper'], $currentCandle['bb_upper'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>BB Lower</td>
                                                                                <td>{{ $prevCandle['bb_lower'] }}</td>
                                                                                <td>{{ $currentCandle['bb_lower'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['bb_lower'], $currentCandle['bb_lower'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['bb_lower'], $currentCandle['bb_lower'], true), 2) }}%
                                                                                </td>
                                                                            </tr>

                                                                            <!-- Oscillators -->
                                                                            <tr class="bg-dark">
                                                                                <td colspan="4" class="font-weight-bold">
                                                                                    Oscillators</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>RSI6</td>
                                                                                <td>{{ $prevCandle['rsi6'] }}</td>
                                                                                <td>{{ $currentCandle['rsi6'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['rsi6'], $currentCandle['rsi6'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['rsi6'], $currentCandle['rsi6'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>Stoch RSI</td>
                                                                                <td>{{ number_format($prevCandle['stoch_rsi'], 4) }}
                                                                                </td>
                                                                                <td>{{ number_format($currentCandle['stoch_rsi'], 4) }}
                                                                                </td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['stoch_rsi'], $currentCandle['stoch_rsi'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['stoch_rsi'], $currentCandle['stoch_rsi'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>Stoch K</td>
                                                                                <td>{{ $prevCandle['stoch_k'] }}</td>
                                                                                <td>{{ $currentCandle['stoch_k'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['stoch_k'], $currentCandle['stoch_k'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['stoch_k'], $currentCandle['stoch_k'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>Stoch D</td>
                                                                                <td>{{ $prevCandle['stoch_d'] }}</td>
                                                                                <td>{{ $currentCandle['stoch_d'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['stoch_d'], $currentCandle['stoch_d'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['stoch_d'], $currentCandle['stoch_d'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>Williams %R</td>
                                                                                <td>{{ $prevCandle['wr'] }}</td>
                                                                                <td>{{ $currentCandle['wr'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['wr'], $currentCandle['wr'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['wr'], $currentCandle['wr'], true), 2) }}%
                                                                                </td>
                                                                            </tr>

                                                                            <!-- MACD -->
                                                                            <tr class="bg-dark">
                                                                                <td colspan="4"
                                                                                    class="font-weight-bold">
                                                                                    MACD</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>DIF</td>
                                                                                <td>{{ $prevCandle['dif'] }}</td>
                                                                                <td>{{ $currentCandle['dif'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['dif'], $currentCandle['dif'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['dif'], $currentCandle['dif'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>DEA</td>
                                                                                <td>{{ $prevCandle['dea'] }}</td>
                                                                                <td>{{ $currentCandle['dea'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['dea'], $currentCandle['dea'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['dea'], $currentCandle['dea'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>MACD</td>
                                                                                <td>{{ $prevCandle['histogram'] }}</td>
                                                                                <td>{{ $currentCandle['histogram'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['histogram'], $currentCandle['histogram'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['histogram'], $currentCandle['histogram'], true), 2) }}%
                                                                                </td>
                                                                            </tr>

                                                                            <!-- Volume Indicators -->
                                                                            <tr class="bg-dark">
                                                                                <td colspan="4"
                                                                                    class="font-weight-bold">
                                                                                    Volume Indicators</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>OBV</td>
                                                                                <td>{{ $prevCandle['obv'] }}</td>
                                                                                <td>{{ $currentCandle['obv'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['obv'], $currentCandle['obv'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['obv'], $currentCandle['obv'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>CVD</td>
                                                                                <td>{{ $prevCandle['cvd'] }}</td>
                                                                                <td>{{ $currentCandle['cvd'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['cvd'], $currentCandle['cvd'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['cvd'], $currentCandle['cvd'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>MFI</td>
                                                                                <td>{{ $prevCandle['mfi'] }}</td>
                                                                                <td>{{ $currentCandle['mfi'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['mfi'], $currentCandle['mfi'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['mfi'], $currentCandle['mfi'], true), 2) }}%
                                                                                </td>
                                                                            </tr>

                                                                            <!-- Trend Indicators -->
                                                                            <tr class="bg-dark">
                                                                                <td colspan="4"
                                                                                    class="font-weight-bold">
                                                                                    Trend Indicators</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>ADX</td>
                                                                                <td>{{ $prevCandle['adx'] }}</td>
                                                                                <td>{{ $currentCandle['adx'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['adx'], $currentCandle['adx'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['adx'], $currentCandle['adx'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>DI+</td>
                                                                                <td>{{ $prevCandle['di_plus'] }}</td>
                                                                                <td>{{ $currentCandle['di_plus'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['di_plus'], $currentCandle['di_plus'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['di_plus'], $currentCandle['di_plus'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>DI-</td>
                                                                                <td>{{ $prevCandle['di_minus'] }}</td>
                                                                                <td>{{ $currentCandle['di_minus'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['di_minus'], $currentCandle['di_minus'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['di_minus'], $currentCandle['di_minus'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>SAR</td>
                                                                                <td>{{ $prevCandle['sar'] }}</td>
                                                                                <td>{{ $currentCandle['sar'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['sar'], $currentCandle['sar'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['sar'], $currentCandle['sar'], true), 2) }}%
                                                                                </td>
                                                                            </tr>

                                                                            <!-- KDJ -->
                                                                            <tr class="bg-dark">
                                                                                <td colspan="4"
                                                                                    class="font-weight-bold">KDJ</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>K</td>
                                                                                <td>{{ $prevCandle['K'] }}</td>
                                                                                <td>{{ $currentCandle['K'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['K'], $currentCandle['K'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['K'], $currentCandle['K'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>D</td>
                                                                                <td>{{ $prevCandle['D'] }}</td>
                                                                                <td>{{ $currentCandle['D'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['D'], $currentCandle['D'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['D'], $currentCandle['D'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>J</td>
                                                                                <td>{{ $prevCandle['J'] }}</td>
                                                                                <td>{{ $currentCandle['J'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['J'], $currentCandle['J'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['J'], $currentCandle['J'], true), 2) }}%
                                                                                </td>
                                                                            </tr>

                                                                            <!-- Other -->
                                                                            <tr class="bg-dark">
                                                                                <td colspan="4"
                                                                                    class="font-weight-bold">Other
                                                                                    Indicators</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>VWAP</td>
                                                                                <td>{{ $prevCandle['vwap'] }}</td>
                                                                                <td>{{ $currentCandle['vwap'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['vwap'], $currentCandle['vwap'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['vwap'], $currentCandle['vwap'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>PER</td>
                                                                                <td>{{ $prevCandle['per'] }}</td>
                                                                                <td>{{ $currentCandle['per'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['per'], $currentCandle['per'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['per'], $currentCandle['per'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td>AVL</td>
                                                                                <td>{{ $prevCandle['avl'] }}</td>
                                                                                <td>{{ $currentCandle['avl'] }}</td>
                                                                                <td
                                                                                    class="{{ CommonHelpers::getPercentDiff($prevCandle['avl'], $currentCandle['avl'], true) > 0 ? 'text-success' : 'text-danger' }}">
                                                                                    {{ number_format(CommonHelpers::getPercentDiff($prevCandle['avl'], $currentCandle['avl'], true), 2) }}%
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        {{-- <!-- Buy Candle Panel -->
                                                        <div class="col-md-6 p-3 border-end border-dark">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-3">
                                                                <h5 class="text-success mb-0">
                                                                    <i class="fas fa-arrow-up me-2 mx-2"></i>Open Signal
                                                                    <span
                                                                        class="badge bg-dark text-success">{{ $buy['timestampReadable'] }}</span>
                                                                </h5>
                                                            </div>

                                                            <div class="row">
                                                                <!-- Price Info -->
                                                                <div class="col-lg-6">
                                                                    <div class="card bg-dark mb-3 shadow">
                                                                        <div
                                                                            class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                            <span class="text-info"><i
                                                                                    class="fas fa-tag me-2"></i>Price
                                                                                Data</span>
                                                                        </div>
                                                                        <div class="card-body py-2">
                                                                            <div class="row g-2">
                                                                                <div class="col-6">
                                                                                    <div
                                                                                        class="bg-gray-800 rounded p-2 text-center">
                                                                                        <small
                                                                                            class="d-block text-muted">Open</small>
                                                                                        <strong
                                                                                            class="text-white">{{ number_format($buy['open'], 4) }}</strong>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <div
                                                                                        class="bg-gray-800 rounded p-2 text-center">
                                                                                        <small
                                                                                            class="d-block text-muted">Close</small>
                                                                                        <strong
                                                                                            class="text-white">{{ number_format($buy['close'], 4) }}</strong>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <div
                                                                                        class="bg-gray-800 rounded p-2 text-center">
                                                                                        <small
                                                                                            class="d-block text-muted">High</small>
                                                                                        <strong
                                                                                            class="text-white">{{ number_format($buy['high'], 4) }}</strong>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <div
                                                                                        class="bg-gray-800 rounded p-2 text-center">
                                                                                        <small
                                                                                            class="d-block text-muted">Low</small>
                                                                                        <strong
                                                                                            class="text-white">{{ number_format($buy['low'], 4) }}</strong>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="bg-gray-800 rounded p-2 text-center mt-2">
                                                                                <small
                                                                                    class="d-block text-muted">Volume</small>
                                                                                <strong
                                                                                    class="text-white">{{ number_format($buy['volume'], 2) }}</strong>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Technical Indicators -->
                                                                <div class="col-lg-6">
                                                                    <div class="card bg-dark mb-3 shadow">
                                                                        <div
                                                                            class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                            <span class="text-info"><i
                                                                                    class="fas fa-chart-bar me-2"></i>Technical
                                                                                Indicators</span>
                                                                        </div>
                                                                        <div class="card-body py-2">
                                                                            <div class="row g-1">
                                                                                <div class="col-6">
                                                                                    <small class="text-muted">RSI(6)</small>
                                                                                    <div class="progress bg-gray-800"
                                                                                        style="height: 8px;">
                                                                                        <div class="progress-bar {{ $buy['rsi6'] > 70 ? 'bg-danger' : ($buy['rsi6'] < 30 ? 'bg-success' : 'bg-info') }}"
                                                                                            style="width: {{ min(100, max(0, $buy['rsi6'])) }}%">
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="text-end">
                                                                                        <small
                                                                                            class="text-white">{{ number_format($buy['rsi6'], 2) }}</small>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <small class="text-muted">MACD</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small">
                                                                                        <span
                                                                                            class="{{ $buy['histogram'] > 0 ? 'text-success' : 'text-danger' }}">
                                                                                            {{ number_format($buy['histogram'], 4) }}
                                                                                        </span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <small class="text-muted">Stoch
                                                                                        K/D</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small text-white">
                                                                                        <span>{{ number_format($buy['stoch_k'], 2) }}</span>
                                                                                        <span>/</span>
                                                                                        <span>{{ number_format($buy['stoch_d'], 2) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <small class="text-muted">ADX</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small">
                                                                                        <span
                                                                                            class="{{ $buy['adx'] > 25 ? 'text-success' : 'text-muted' }}">
                                                                                            {{ number_format($buy['adx'], 2) }}
                                                                                        </span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Volume & Trend -->
                                                                <div class="col-12">
                                                                    <div class="card bg-dark mb-3 shadow">
                                                                        <div
                                                                            class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                            <span class="text-info"><i
                                                                                    class="fas fa-tachometer-alt me-2"></i>Volume
                                                                                & Trend</span>
                                                                        </div>
                                                                        <div class="card-body py-2">
                                                                            <div class="row g-2">
                                                                                <div class="col-md-4">
                                                                                    <small
                                                                                        class="text-muted d-block">Bollinger
                                                                                        Bands</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small text-white">
                                                                                        <span>U:
                                                                                            {{ number_format($buy['bb_upper'], 4) }}</span>
                                                                                        <span>L:
                                                                                            {{ number_format($buy['bb_lower'], 4) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <small
                                                                                        class="text-muted d-block">DMI</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small">
                                                                                        <span class="text-success">+D:
                                                                                            {{ number_format($buy['di_plus'], 2) }}</span>
                                                                                        <span class="text-danger">-D:
                                                                                            {{ number_format($buy['di_minus'], 2) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <small class="text-muted d-block">S/R
                                                                                        Levels</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small text-white">
                                                                                        <span>S:
                                                                                            {{ $buy['currentSupport'] }}</span>
                                                                                        <span>R:
                                                                                            {{ $buy['currentResistance'] }}</span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Volume Signal -->
                                                                @if (!empty($buy['openingVolumes']))
                                                                    @php $opening = is_string($buy['openingVolumes']) ? json_decode($buy['openingVolumes'], true) : $buy['openingVolumes']; @endphp
                                                                    <div class="col-12">
                                                                        <div class="card bg-dark mb-3 shadow">
                                                                            <div
                                                                                class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                                <span class="text-info"><i
                                                                                        class="fas fa-signal me-2"></i>Volume
                                                                                    Signal</span>
                                                                                <span
                                                                                    class="ms-auto badge {{ $opening['signal'] == 'buy' ? 'bg-success' : 'bg-danger' }}">
                                                                                    {{ ucfirst($opening['signal']) }}
                                                                                    ({{ $opening['strength'] }}/10)
                                                                                </span>
                                                                            </div>
                                                                            <div class="card-body py-2">
                                                                                <div class="row g-2 mb-2">
                                                                                    <div class="col-3">
                                                                                        <small
                                                                                            class="text-muted d-block">Price</small>
                                                                                        <span
                                                                                            class="text-white">{{ number_format($opening['price'], 4) }}</span>
                                                                                    </div>
                                                                                    <div class="col-3">
                                                                                        <small
                                                                                            class="text-muted d-block">VWAP</small>
                                                                                        <span
                                                                                            class="text-white">{{ number_format($opening['indicators']['vwap_current'], 4) }}</span>
                                                                                    </div>
                                                                                    <div class="col-3">
                                                                                        <small
                                                                                            class="text-muted d-block">OBV</small>
                                                                                        <span
                                                                                            class="text-white">{{ number_format($opening['indicators']['obv_current'], 2) }}</span>
                                                                                    </div>
                                                                                    <div class="col-3">
                                                                                        <small
                                                                                            class="text-muted d-block">MFI</small>
                                                                                        <span
                                                                                            class="text-white">{{ number_format($opening['indicators']['mfi_current'], 2) }}</span>
                                                                                    </div>
                                                                                </div>

                                                                                <small
                                                                                    class="text-muted d-block mb-1">Signal
                                                                                    Reasons:</small>
                                                                                <div class="bg-gray-800 rounded p-2"
                                                                                    style="max-height: 100px; overflow-y: auto;">
                                                                                    <ul class="mb-0 ps-3 small text-white">
                                                                                        @foreach ($opening['reasons'] as $reason)
                                                                                            <li>{{ $reason }}</li>
                                                                                        @endforeach
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endif


                                                                <!-- Order Book -->
                                                                @if (!empty($buy['orderBookSnapshot']))
                                                                    <div class="col-12 text-center">
                                                                        <a target="_blank"
                                                                            class="btn btn-outline-success btn-sm mt-2"
                                                                            href="{{ route('order-book.show', $buy['orderBookSnapshot']) }}">
                                                                            <i class="fas fa-book me-1"></i> View Buy Order
                                                                            Book Snapshot
                                                                        </a>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <!-- Boll Section -->
                                                            @if ($confirmCandle)
                                                                <div class="col-12">
                                                                    <div class="card bg-dark mb-3 shadow">

                                                                        <div class="card-body py-2">


                                                                            <small
                                                                                class="text-muted d-block mb-1">Bollinger
                                                                                Bands Information</small>
                                                                            <div class="bg-gray-800 rounded p-2"
                                                                                style="max-height: 100px; overflow-y: auto;">

                                                                                <ul class="mb-0 ps-3 small text-white">

                                                                                    <li>Difference at Highest Point:
                                                                                        {{ round($confirmCandle['bb_diff_highest'], 2) }}
                                                                                        %</li>
                                                                                    <li>Difference at Confirmation Point:
                                                                                        {{ round($confirmCandle['bb_diff_confirmed'], 2) }}
                                                                                        %</li>
                                                                                    <li>Difference between highest and
                                                                                        confirmation point:
                                                                                        {{ round($confirmCandle['bb_diff'], 2) }}
                                                                                        % </li>

                                                                                </ul>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <!-- Sell Candle Panel -->
                                                        <div class="col-md-6 p-3">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-3">
                                                                <h5 class="text-danger mb-0">
                                                                    <i class="fas fa-arrow-down me-2 mx-2"></i>Close Signal
                                                                    <span
                                                                        class="badge bg-dark text-danger">{{ $sell['timestampReadable'] }}</span>
                                                                </h5>
                                                            </div>

                                                            <div class="row">
                                                                <!-- Price Info -->
                                                                <div class="col-lg-6">
                                                                    <div class="card bg-dark mb-3 shadow">
                                                                        <div
                                                                            class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                            <span class="text-info"><i
                                                                                    class="fas fa-tag me-2"></i>Price
                                                                                Data</span>
                                                                        </div>
                                                                        <div class="card-body py-2">
                                                                            <div class="row g-2">
                                                                                <div class="col-6">
                                                                                    <div
                                                                                        class="bg-gray-800 rounded p-2 text-center">
                                                                                        <small
                                                                                            class="d-block text-muted">Open</small>
                                                                                        <strong
                                                                                            class="text-white">{{ number_format($sell['open'], 4) }}</strong>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <div
                                                                                        class="bg-gray-800 rounded p-2 text-center">
                                                                                        <small
                                                                                            class="d-block text-muted">Close</small>
                                                                                        <strong
                                                                                            class="text-white">{{ number_format($sell['close'], 4) }}</strong>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <div
                                                                                        class="bg-gray-800 rounded p-2 text-center">
                                                                                        <small
                                                                                            class="d-block text-muted">High</small>
                                                                                        <strong
                                                                                            class="text-white">{{ number_format($sell['high'], 4) }}</strong>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <div
                                                                                        class="bg-gray-800 rounded p-2 text-center">
                                                                                        <small
                                                                                            class="d-block text-muted">Low</small>
                                                                                        <strong
                                                                                            class="text-white">{{ number_format($sell['low'], 4) }}</strong>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="bg-gray-800 rounded p-2 text-center mt-2">
                                                                                <small
                                                                                    class="d-block text-muted">Volume</small>
                                                                                <strong
                                                                                    class="text-white">{{ number_format($sell['volume'], 2) }}</strong>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Technical Indicators -->
                                                                <div class="col-lg-6">
                                                                    <div class="card bg-dark mb-3 shadow">
                                                                        <div
                                                                            class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                            <span class="text-info"><i
                                                                                    class="fas fa-chart-bar me-2"></i>Technical
                                                                                Indicators</span>
                                                                        </div>
                                                                        <div class="card-body py-2">
                                                                            <div class="row g-1">
                                                                                <div class="col-6">
                                                                                    <small
                                                                                        class="text-muted">RSI(6)</small>
                                                                                    <div class="progress bg-gray-800"
                                                                                        style="height: 8px;">
                                                                                        <div class="progress-bar {{ $sell['rsi6'] > 70 ? 'bg-danger' : ($sell['rsi6'] < 30 ? 'bg-success' : 'bg-info') }}"
                                                                                            style="width: {{ min(100, max(0, $sell['rsi6'])) }}%">
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="text-end">
                                                                                        <small
                                                                                            class="text-white">{{ number_format($sell['rsi6'], 2) }}</small>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <small class="text-muted">MACD</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small">
                                                                                        <span
                                                                                            class="{{ $sell['histogram'] > 0 ? 'text-success' : 'text-danger' }}">
                                                                                            {{ number_format($sell['histogram'], 4) }}
                                                                                        </span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <small class="text-muted">Stoch
                                                                                        K/D</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small text-white">
                                                                                        <span>{{ number_format($sell['stoch_k'], 2) }}</span>
                                                                                        <span>/</span>
                                                                                        <span>{{ number_format($sell['stoch_d'], 2) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <small class="text-muted">ADX</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small">
                                                                                        <span
                                                                                            class="{{ $sell['adx'] > 25 ? 'text-success' : 'text-muted' }}">
                                                                                            {{ number_format($sell['adx'], 2) }}
                                                                                        </span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Volume & Trend -->
                                                                <div class="col-12">
                                                                    <div class="card bg-dark mb-3 shadow">
                                                                        <div
                                                                            class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                            <span class="text-info"><i
                                                                                    class="fas fa-tachometer-alt me-2 "></i>Volume
                                                                                & Trend</span>
                                                                        </div>
                                                                        <div class="card-body py-2">
                                                                            <div class="row g-2">
                                                                                <div class="col-md-4">
                                                                                    <small
                                                                                        class="text-muted d-block">Bollinger
                                                                                        Bands</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small text-white">
                                                                                        <span>U:
                                                                                            {{ number_format($sell['bb_upper'], 4) }}</span>
                                                                                        <span>L:
                                                                                            {{ number_format($sell['bb_lower'], 4) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <small
                                                                                        class="text-muted d-block">DMI</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small">
                                                                                        <span class="text-success">+D:
                                                                                            {{ number_format($sell['di_plus'], 2) }}</span>
                                                                                        <span class="text-danger">-D:
                                                                                            {{ number_format($sell['di_minus'], 2) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <small class="text-muted d-block">S/R
                                                                                        Levels</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small text-white">
                                                                                        <span>S:
                                                                                            {{ $sell['currentSupport'] }}</span>
                                                                                        <span>R:
                                                                                            {{ $sell['currentResistance'] }}</span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>


                                                                <div class="col-12">
                                                                    <div class="card bg-dark mb-3 shadow">
                                                                        <div
                                                                            class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                            <span class="text-info"><i
                                                                                    class="fas fa-tachometer-alt me-2 "></i>Volume
                                                                                & Trend</span>
                                                                        </div>
                                                                        <div class="card-body py-2">
                                                                            <div class="row g-2">
                                                                                <div class="col-md-4">
                                                                                    <small
                                                                                        class="text-muted d-block">Bollinger
                                                                                        Bands</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small text-white">
                                                                                        <span>U:
                                                                                            {{ number_format($sell['bb_upper'], 4) }}</span>
                                                                                        <span>L:
                                                                                            {{ number_format($sell['bb_lower'], 4) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <small
                                                                                        class="text-muted d-block">DMI</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small">
                                                                                        <span class="text-success">+D:
                                                                                            {{ number_format($sell['di_plus'], 2) }}</span>
                                                                                        <span class="text-danger">-D:
                                                                                            {{ number_format($sell['di_minus'], 2) }}</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                    <small class="text-muted d-block">S/R
                                                                                        Levels</small>
                                                                                    <div
                                                                                        class="d-flex justify-content-between small text-white">
                                                                                        <span>S:
                                                                                            {{ $sell['currentSupport'] }}</span>
                                                                                        <span>R:
                                                                                            {{ $sell['currentResistance'] }}</span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>


                                                                <!-- Order Book -->
                                                           @if (!empty($sell['orderBookSnapshot']))
                                                                    <div class="col-12 text-center">
                                                                        <a target="_blank"
                                                                            class="btn btn-outline-danger btn-sm mt-2"
                                                                href="{{ route('order-book.show', $sell['orderBookSnapshot']) }}">
                                                                            <i class="fas fa-book me-1"></i> View Sell
                                                                            Order Book Snapshot
                                                                        </a>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div> --}}
                                                    </div>

                                                    <!-- Nearby Trades Section -->
                                                    <div class="px-3 pb-3">
                                                        <div class="card bg-dark shadow mt-3">
                                                            <div class="card-header bg-gradient-danger p-3">
                                                                <h5 class="mb-0 text-white">
                                                                    <i class="fas fa-history me-2"></i> Nearby Trades (± 5
                                                                    mins)
                                                                </h5>
                                                            </div>
                                                            <div class="table-responsive">
                                                                <table class="table table-dark table-hover mb-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th class="text-primary">ID</th>
                                                                            <th class="text-primary">Symbol</th>
                                                                            <th class="text-primary">Buy Price</th>
                                                                            <th class="text-primary">Extreme Price</th>
                                                                            <th class="text-primary">Sell Price</th>
                                                                            <th class="text-primary">Times</th>
                                                                            <th class="text-primary">Profit</th>
                                                                            <th class="text-primary">Duration</th>
                                                                            <th class="text-primary">Action</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach ($nearbyTrades as $nearbyTradeIndex => $trade)
                                                                            <tr>
                                                                                <td>{{ $nearbyTradeIndex + 1 }}</td>
                                                                                <td><span
                                                                                        class="badge bg-primary">{{ $trade->symbol }}</span>
                                                                                </td>
                                                                                <td>{{ number_format($trade->buyingPrice, 4) }}
                                                                                </td>
                                                                                <td>
                                                                                    <span class="text-danger">
                                                                                        {{ number_format($trade->lowestPrice, 4) }}
                                                                                        <small>({{ number_format($trade->lowestPricePercentage, 2) }}%)</small>
                                                                                    </span>
                                                                                </td>
                                                                                <td>{{ number_format($trade->sellingPrice, 4) }}
                                                                                </td>
                                                                                <td>
                                                                                    <small>
                                                                                        <i
                                                                                            class="fas fa-arrow-right text-success"></i>
                                                                                        {{ \Carbon\Carbon::parse(json_decode($trade->buyingCandle, true)['timestamp'])->format('h:i A') }}<br>
                                                                                        <i
                                                                                            class="fas fa-arrow-left text-danger"></i>
                                                                                        {{ \Carbon\Carbon::parse(json_decode($trade->sellingCandle, true)['timestamp'])->format('h:i A') }}
                                                                                    </small>
                                                                                </td>
                                                                                <td>
                                                                                    <span
                                                                                        class="badge {{ $trade->profit > 0 ? 'bg-success' : 'bg-danger' }}">
                                                                                        {{ number_format($trade->profit, 2) }}%
                                                                                    </span>
                                                                                </td>
                                                                                <td>{{ $trade->duration }} min</td>
                                                                                <td>
                                                                                    <a class="btn btn-sm btn-outline-info"
                                                                                        href="{{ route('coinReportDetails', $market) . '?symbol=' . $trade->symbol . '&interval=' . $trade->interval }}">
                                                                                        <i
                                                                                            class="fas fa-external-link-alt"></i>
                                                                                    </a>
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                    </div>
                    </td>
                    </tr>
                    @endforeach
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        function toggleDetails(tradeId) {
            const detailRow = document.getElementById(`details-${tradeId}`);
            const isHidden = detailRow.classList.contains('d-none');
            document.querySelectorAll('.trade-details').forEach(row => row.classList.add('d-none'));
            if (isHidden) {
                detailRow.classList.remove('d-none');
            }
        }
    </script>




    <script>
        let gridVisible = true;
        document.addEventListener("DOMContentLoaded", function() {

            const candlestickData = @json($data);
            const volumeSignals = @json($volumeSignals);
            const trades = @json($trades);
            const buyTriggers = @json($buyTriggers);
            const sellTriggers = @json($sellTriggers);
            const confirmTriggers = @json($confirmTriggers);
            const highestTriggers = @json($highestTriggers);
            const lowestTriggers = @json($lowestTriggers);
            const oneHourMarks = @json($oneHourMarks);
            const supportTriggers = @json($supportTriggers);
            const resistanceTriggers = @json($resistanceTriggers);

            const timestamps = candlestickData.map(data => data.timestampReadable);
            const closePrices = candlestickData.map(data => data.close);
            const openPrices = candlestickData.map(data => data.open);
            const highPrices = candlestickData.map(data => data.high);
            const lowPrices = candlestickData.map(data => data.low);

            const bb_upper = candlestickData.map(data => data.bb_upper);
            const bb_middle = candlestickData.map(data => data.bb_middle);
            const bb_lower = candlestickData.map(data => data.bb_lower);

            // Volume Indicators
            const mfiValues = volumeSignals.map(signal => signal.indicators.mfi_current);
            const obvValues = volumeSignals.map(signal => signal.indicators.obv_current);
            const cvdValues = volumeSignals.map(signal => signal.indicators.cvd_current);
            const vwapValues = volumeSignals.map(signal => signal.indicators.vwap_current);







            const pointStyles = timestamps.map((timestamp, index) => {
                const binanceTimestamp = candlestickData[index].binance_timestamp;
                if (buyTriggers.includes(binanceTimestamp)) {
                    return {
                        backgroundColor: 'lime',
                        borderColor: 'green',
                        radius: 6
                    };
                } else if (sellTriggers.includes(binanceTimestamp)) {
                    return {
                        backgroundColor: 'salmon',
                        borderColor: 'darkred',
                        radius: 6
                    };
                } else if (confirmTriggers.includes(binanceTimestamp)) {
                    return {
                        backgroundColor: '#ffffff', // white
                        borderColor: '#d3d3d3', // light gray for slight border visibility (optional)
                        radius: 6
                    };
                } else if (highestTriggers.includes(binanceTimestamp)) {
                    return {
                        backgroundColor: '#ffff00', // yellow
                        borderColor: '#ffd700', // gold (slightly deeper yellow for border)
                        radius: 6
                    };
                } else {
                    return {
                        backgroundColor: 'rgba(255,255,255,0.6)',
                        borderColor: 'cyan',
                        radius: 3
                    };
                }
            });



            const supportLines = generateSupportResistanceDataset(supportTriggers, timestamps, 'rgba(0,255,0,0.5)',
                'Support Levels');
            const resistanceLines = generateSupportResistanceDataset(resistanceTriggers, timestamps,
                'rgba(255,0,0,0.5)', 'Resistance Levels');

            const ctx = document.getElementById('candlestickChart').getContext('2d');
            window.candlestickChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: timestamps,
                    datasets: [

                        {
                            label: 'Close Prices',
                            data: closePrices,
                            borderColor: 'rgba(0,123,255,1)',
                            backgroundColor: 'rgba(0,123,255,0.2)',
                            fill: true,
                            tension: 0.1,
                            yAxisID: 'y',
                            pointBackgroundColor: pointStyles.map(s => s.backgroundColor),
                            pointBorderColor: pointStyles.map(s => s.borderColor),
                            pointRadius: pointStyles.map(s => s.radius)
                        },

                        // Bollinger Bands

                        {
                            label: 'BB UP',
                            data: bb_upper,
                            borderColor: 'rgba(128, 0, 128, 1)', // Purple
                            backgroundColor: 'rgba(128, 0, 128, 0.2)',
                            hidden: false,

                            tension: 0.1,
                            yAxisID: 'y',
                            pointBackgroundColor: 'rgba(128, 0, 128, 1)',
                            pointBorderColor: 'rgba(128, 0, 128, 1)',
                        },
                        {
                            label: 'BB MD',
                            data: bb_middle,
                            borderColor: 'rgba(255, 105, 180, 1)', // Pink (hot pink)
                            backgroundColor: 'rgba(255, 105, 180, 0.2)',
                            hidden: false,

                            tension: 0.1,
                            yAxisID: 'y',
                            pointBackgroundColor: 'rgba(255, 105, 180, 1)',
                            pointBorderColor: 'rgba(255, 105, 180, 1)',

                        },
                        {
                            label: 'BB DN',
                            data: bb_lower,
                            borderColor: 'rgba(128, 0, 128, 1)', // Purple
                            backgroundColor: 'rgba(128, 0, 128, 0.2)',
                            hidden: false,

                            tension: 0.1,
                            yAxisID: 'y',
                            pointBackgroundColor: 'rgba(128, 0, 128, 1)',
                            pointBorderColor: 'rgba(128, 0, 128, 1)',
                        },



                        {
                            label: 'MFI',
                            data: mfiValues,
                            borderColor: 'rgba(0, 255, 0, 0.7)',
                            backgroundColor: 'rgba(0, 255, 0, 0.1)',
                            tension: 0.1,
                            hidden: true,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'OBV',
                            data: obvValues,
                            borderColor: 'rgba(255, 215, 0, 0.7)',
                            backgroundColor: 'rgba(255, 215, 0, 0.1)',
                            tension: 0.1,
                            hidden: true,
                            yAxisID: 'y2'
                        },
                        {
                            label: 'CVD',
                            data: cvdValues,
                            borderColor: 'rgba(255, 69, 0, 0.7)',
                            backgroundColor: 'rgba(255, 69, 0, 0.1)',
                            tension: 0.1,
                            hidden: true,
                            yAxisID: 'y3'
                        },
                        {
                            label: 'VWAP',
                            data: vwapValues,
                            borderColor: 'rgba(173, 216, 230, 0.7)',
                            backgroundColor: 'rgba(173, 216, 230, 0.1)',
                            tension: 0.1,
                            hidden: true,
                            yAxisID: 'y4'
                        },
                        supportLines,
                        resistanceLines,
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Timestamp',
                                color: '#fff'
                            },
                            ticks: {
                                color: '#ccc',
                                display: false
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Price (USDT)',
                                color: '#fff'
                            },
                            ticks: {
                                color: '#ccc'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        y1: {
                            position: 'right',
                            title: {
                                display: true,
                                text: 'MFI',
                                color: '#fff'
                            },
                            ticks: {
                                color: '#aaa'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        y2: {
                            position: 'right',
                            title: {
                                display: true,
                                text: 'OBV',
                                color: '#fff'
                            },
                            ticks: {
                                color: '#aaa'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        y3: {
                            position: 'right',
                            title: {
                                display: true,
                                text: 'CVD',
                                color: '#fff'
                            },
                            ticks: {
                                color: '#aaa'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        y4: {
                            position: 'right',
                            title: {
                                display: true,
                                text: 'VWAP',
                                color: '#fff'
                            },
                            ticks: {
                                color: '#aaa'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#fff'
                            }
                        },
                        zoom: {
                            pan: {
                                enabled: true,
                                mode: 'x'
                            },
                            zoom: {
                                wheel: {
                                    enabled: true,
                                    modifierKey: 'ctrl'
                                },
                                pinch: {
                                    enabled: true
                                },
                                mode: 'x'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            titleColor: '#fff',
                            bodyColor: '#eee'
                        }
                    }
                }
            });

            window.resetZoom = function() {
                const minIndex = 0;
                const maxIndex = timestamps.length;
                if (minIndex !== -1 && maxIndex !== -1) {
                    window.candlestickChart.options.scales.x.min = timestamps[minIndex];
                    window.candlestickChart.options.scales.x.max = timestamps[maxIndex];
                    window.candlestickChart.update();
                }
            }

            window.showDetails = function(tradeId, button) {
                const detailRow = document.getElementById('details-' + tradeId);
                const isVisible = !detailRow.classList.contains('d-none');
                document.querySelectorAll('.trade-details').forEach(el => el.classList.add('d-none'));
                if (!isVisible) {
                    detailRow.classList.remove('d-none');
                    const trade = trades.find(t => t.id === tradeId);
                    zoomChartToTrade(trade.buyingCandle.timestampReadable, trade.sellingCandle
                        .timestampReadable);
                }
            };

            function zoomChartToTrade(buyTimestamp, sellTimestamp) {
                const minIndex = timestamps.findIndex(t => t === buyTimestamp);
                const maxIndex = timestamps.findIndex(t => t === sellTimestamp);
                if (minIndex !== -1 && maxIndex !== -1) {
                    window.candlestickChart.options.scales.x.min = timestamps[minIndex];
                    window.candlestickChart.options.scales.x.max = timestamps[maxIndex];
                    window.candlestickChart.update();
                }
            }



            function generateSupportResistanceDataset(triggers, timestamps, color, label) {
                const result = {
                    label: label,
                    data: new Array(timestamps.length).fill(null),
                    borderColor: color,
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointRadius: 0,
                    fill: false,
                    tension: 0,
                    hidden: true, // hidden by default
                    yAxisID: 'y'
                };

                triggers.forEach(trigger => {
                    const index = timestamps.findIndex(ts => ts == trigger.timestampReadable);
                    if (index !== -1) {
                        const start = Math.max(index - 3, 0);
                        const end = Math.min(index + 3, timestamps.length - 1);
                        for (let i = start; i <= end; i++) {
                            result.data[i] = trigger.value;
                        }
                    }
                });

                return result;
            }
        });

        function toggleGrid() {
            const chart = window.candlestickChart;

            for (const scaleKey in chart.options.scales) {
                const scale = chart.options.scales[scaleKey];
                if (scale.grid) {
                    scale.grid.display = !gridVisible;
                }
            }

            gridVisible = !gridVisible;
            document.getElementById('gridToggleBtn').textContent = gridVisible ? 'Hide Grid' : 'Show Grid';
            chart.update();
        }
    </script>
    </div>


@endsection
