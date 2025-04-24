@extends('layouts.app')

@php
    $buyTriggers = [];
    $sellTriggers = [];
    $lowestTriggers = [];
    $liquidationTriggers = [];
    $oneHourMarks = [];
    $position = request()->get('position');
    $supportTriggers = [];
    $resistanceTriggers = [];

@endphp

@section('content')
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

                                        if($lowestIndex == -1){
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
                                        <td>{{ $indexTrades + 1 }}</td>
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
                                                        @endphp

                                                        <!-- Buy Candle Panel -->
                                                        <div class="col-md-6 p-3 border-end border-dark">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-3">
                                                                <h5 class="text-success mb-0">
                                                                    <i class="fas fa-arrow-up me-2 mx-2"></i>Buy Signal
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
                                                        </div>

                                                        <!-- Sell Candle Panel -->
                                                        <div class="col-md-6 p-3">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-3">
                                                                <h5 class="text-danger mb-0">
                                                                    <i class="fas fa-arrow-down me-2 mx-2"></i>Sell Signal
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

                                                                <!-- Volume Signal -->
                                                                @if (!empty($sell['closingVolumes']))
                                                                    @php $closing = is_string($sell['closingVolumes']) ? json_decode($sell['closingVolumes'], true) : $sell['closingVolumes']; @endphp
                                                                    <div class="col-12">
                                                                        <div class="card bg-dark mb-3 shadow">
                                                                            <div
                                                                                class="card-header py-2 d-flex align-items-center bg-gradient-dark border-bottom border-gray-800">
                                                                                <span class="text-info"><i
                                                                                        class="fas fa-signal me-2"></i>Volume
                                                                                    Signal</span>
                                                                                <span
                                                                                    class="ms-auto badge {{ $closing['signal'] == 'buy' ? 'bg-success' : 'bg-danger' }}">
                                                                                    {{ ucfirst($closing['signal']) }}
                                                                                    ({{ $closing['strength'] }}/10)
                                                                                </span>
                                                                            </div>
                                                                            <div class="card-body py-2">
                                                                                <div class="row g-2 mb-2">
                                                                                    <div class="col-3">
                                                                                        <small
                                                                                            class="text-muted d-block">Price</small>
                                                                                        <span
                                                                                            class="text-white">{{ number_format($closing['price'], 4) }}</span>
                                                                                    </div>
                                                                                    <div class="col-3">
                                                                                        <small
                                                                                            class="text-muted d-block">VWAP</small>
                                                                                        <span
                                                                                            class="text-white">{{ number_format($closing['indicators']['vwap_current'], 4) }}</span>
                                                                                    </div>
                                                                                    <div class="col-3">
                                                                                        <small
                                                                                            class="text-muted d-block">OBV</small>
                                                                                        <span
                                                                                            class="text-white">{{ number_format($closing['indicators']['obv_current'], 2) }}</span>
                                                                                    </div>
                                                                                    <div class="col-3">
                                                                                        <small
                                                                                            class="text-muted d-block">MFI</small>
                                                                                        <span
                                                                                            class="text-white">{{ number_format($closing['indicators']['mfi_current'], 2) }}</span>
                                                                                    </div>
                                                                                </div>

                                                                                <small
                                                                                    class="text-muted d-block mb-1">Signal
                                                                                    Reasons:</small>
                                                                                <div class="bg-gray-800 rounded p-2"
                                                                                    style="max-height: 100px; overflow-y: auto;">
                                                                                    <ul class="mb-0 ps-3 small text-white">
                                                                                        @foreach ($closing['reasons'] as $reason)
                                                                                            <li>{{ $reason }}</li>
                                                                                        @endforeach
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endif

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
                                                        </div>
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
                                                            <div class="card-body p-0 bg-dark">
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
            const lowestTriggers = @json($lowestTriggers);
            const oneHourMarks = @json($oneHourMarks);
            const supportTriggers = @json($supportTriggers);
            const resistanceTriggers = @json($resistanceTriggers);

            const timestamps = candlestickData.map(data => data.timestamp);
            const closePrices = candlestickData.map(data => data.close);
            const openPrices = candlestickData.map(data => data.open);
            const highPrices = candlestickData.map(data => data.high);
            const lowPrices = candlestickData.map(data => data.low);

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
                    datasets: [{
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
                    zoomChartToTrade(trade.buyingCandle.timestamp, trade.sellingCandle.timestamp);
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
                    const index = timestamps.findIndex(ts => ts == trigger.timestamp);
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
