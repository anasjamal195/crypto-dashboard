@extends('layouts.app')

@php
    $buyTriggers = [];
    $sellTriggers = [];
    $lowestTriggers = [];
    $liquidationTriggers = [];
    $oneHourMarks = [];
    $position = request()->get('position');

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
                    <button class="btn btn-warning btn-sm" onclick="resetZoom()">Reset Zoom</button>
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
                                            @if ($trade->snapshot_id)
                                                <a target="_blank" class="btn btn-info btn-sm"
                                                    href="{{ route('order-book.show', $trade->snapshot_id) }}">Show
                                                    Trigger</a>
                                            @endif
                                        </td>
                                    </tr>


                                    <tr id="details-{{ $trade->id }}" class="trade-details d-none">
                                        <td colspan="10">


                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="mb-4">
                                                        <div
                                                            class="card-header bg-gradient-primary text-white py-2 px-3 d-flex justify-content-between align-items-center">
                                                            <span><i class="fas fa-chart-line me-2"></i>Buying & Selling
                                                                Candle Indicators</span>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="row">
                                                                @php
                                                                    $buy = $buyCandle;
                                                                    $sell = $sellCandle;
                                                                   
                                                                @endphp
                                                                <div class="col-md-6">
                                                                    <h6 class="text-info">🟢 Buying Candle
                                                                        ({{ $buy['timestampReadable'] }})</h6>
                                                                    <ul class="list-unstyled small text-white">
                                                                        <li><strong>Open:</strong>
                                                                            {{ number_format($buy['open'], 4) }}</li>
                                                                        <li><strong>Close:</strong>
                                                                            {{ number_format($buy['close'], 4) }}</li>
                                                                        <li><strong>High:</strong>
                                                                            {{ number_format($buy['high'], 4) }}</li>
                                                                        <li><strong>Low:</strong>
                                                                            {{ number_format($buy['low'], 4) }}</li>
                                                                        <li><strong>Volume:</strong>
                                                                            {{ number_format($buy['volume'], 2) }}</li>
                                                                        <li><strong>RSI (6):</strong>
                                                                            {{ number_format($buy['rsi6'], 2) }}</li>
                                                                       
                                                                        <li><strong>Stoch K/D:</strong>
                                                                            {{ number_format($buy['stoch_k'], 2) }} /
                                                                            {{ number_format($buy['stoch_d'], 2) }}</li>
                                                                        <li><strong>WR:</strong>
                                                                            {{ number_format($buy['wr'], 2) }}</li>
                                                                        <li><strong>SAR:</strong>
                                                                            {{ number_format($buy['sar'], 4) }}</li>
                                                                        <li><strong>Should Buy:</strong>
                                                                            {{ $buy['should_buy'] ? '✅ Yes' : '❌ No' }}
                                                                        </li>
                                                                    </ul>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <h6 class="text-danger">🔴 Selling Candle
                                                                        ({{ $sell['timestampReadable'] }})</h6>
                                                                    <ul class="list-unstyled small text-white">
                                                                        <li><strong>Open:</strong>
                                                                            {{ number_format($sell['open'], 4) }}</li>
                                                                        <li><strong>Close:</strong>
                                                                            {{ number_format($sell['close'], 4) }}</li>
                                                                        <li><strong>High:</strong>
                                                                            {{ number_format($sell['high'], 4) }}</li>
                                                                        <li><strong>Low:</strong>
                                                                            {{ number_format($sell['low'], 4) }}</li>
                                                                        <li><strong>Volume:</strong>
                                                                            {{ number_format($sell['volume'], 2) }}</li>
                                                                        <li><strong>RSI (6):</strong>
                                                                            {{ number_format($sell['rsi6'], 2) }}</li>
                                                                       
                                                                        <li><strong>Stoch K/D:</strong>
                                                                            {{ number_format($sell['stoch_k'], 2) }} /
                                                                            {{ number_format($sell['stoch_d'], 2) }}</li>
                                                                        <li><strong>WR:</strong>
                                                                            {{ number_format($sell['wr'], 2) }}</li>
                                                                        <li><strong>SAR:</strong>
                                                                            {{ number_format($sell['sar'], 4) }}</li>
                                                                        <li><strong>Should Sell:</strong>
                                                                            {{ $sell['should_sell'] ? '✅ Yes' : '❌ No' }}
                                                                        </li>
                                                                    </ul>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>


                                            <div class="row mx-auto">

                                                <h5 class="text-danger">Nearby Trades (± 5 mins):</h5>

                                                <table class="table">
                                                    <thead class="text-primary">
                                                        <tr>
                                                            <th>Trade ID</th>
                                                            <th>Symbol</th>
                                                            <th>Buying Price (USDT)</th>
                                                            <th>Extreme Price (USDT)</th>
                                                            <th>Selling Price (USDT)</th>
                                                            <th>Buying Time</th>
                                                            <th>Selling Time</th>
                                                            <th>Profit (%)</th>
                                                            <th>Duration (mins)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($nearbyTrades as $nearbyTradeIndex => $trade)
                                                            <tr>
                                                                <td>{{ $nearbyTradeIndex + 1 }}</td>
                                                                <td>{{ $trade->symbol }}</td>
                                                                <td>{{ number_format($trade->buyingPrice, 4) }}</td>
                                                                <td>{{ number_format($trade->lowestPrice, 4) }}
                                                                    ({{ number_format($trade->lowestPricePercentage, 2) }}%)
                                                                </td>
                                                                <td>{{ number_format($trade->sellingPrice, 4) }}</td>
                                                                <td>{{ \Carbon\Carbon::parse(json_decode($trade->buyingCandle, true)['timestamp'])->format('h:i A') }}
                                                                </td>

                                                                <td>{{ \Carbon\Carbon::parse(json_decode($trade->sellingCandle, true)['timestamp'])->format('h:i A') }}
                                                                </td>
                                                                <td>{{ number_format($trade->profit, 2) }}%</td>
                                                                <td>{{ $trade->duration }}</td>
                                                                <td>
                                                                    <a class="btn btn-primary btn-sm"
                                                                        href="{{ route('coinReportDetails', $market) . '?symbol=' . $trade->symbol . '&interval=' . $trade->interval }}">Show
                                                                        Details</a>


                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
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
        document.addEventListener("DOMContentLoaded", function() {
            const candlestickData = @json($data);
            const volumeSignals = @json($volumeSignals);
            const trades = @json($trades);
            const buyTriggers = @json($buyTriggers);
            const sellTriggers = @json($sellTriggers);
            const lowestTriggers = @json($lowestTriggers);
            const oneHourMarks = @json($oneHourMarks);

            const timestamps = candlestickData.map(data => data.timestamp);
            const closePrices = candlestickData.map(data => data.close);

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
                        }
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
        });
    </script>
    </div>


@endsection
