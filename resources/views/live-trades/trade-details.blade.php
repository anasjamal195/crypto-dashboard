@extends('layouts.app')

@php
    $buy_orders = json_decode(json_encode($order_sell), true);
    $sell_orders = json_decode(json_encode($order_sell), true);
    // dd($buy_orders,$sell_orders);
    $buyTriggers = array_map(function ($order) {
        $sqlTimestamp = $order['created_at'];
        $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $sqlTimestamp, 'Asia/Karachi');
        $date->setTimezone('GMT');
        $unixTimestamp = $date->timestamp * 1000;
        $unixTimestamp = floor($unixTimestamp / 60000) * 60000;
        return intval($unixTimestamp);
    }, $buy_orders);

    $sellTriggers = array_map(function ($order) {
        $sqlTimestamp = $order['created_at'];
        $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $sqlTimestamp, 'Asia/Karachi');
        $date->setTimezone('GMT');
        $unixTimestamp = $date->timestamp * 1000;
        $unixTimestamp = floor($unixTimestamp / 60000) * 60000;
        return intval($unixTimestamp);
    }, $sell_orders);
    $lowestTriggers = [];
    $liquidationTriggers = [];
    // dd($buyTriggers, $sellTriggers,$candlestickData);
@endphp

@section('content')
    <div class="container-fluid mt-5">
        <h2 class="mb-4 text-white">Trade Details for {{ $symbol }} - {{ $interval }}
            ({{ $market }})</h2>
        {{-- <div class="row mb-4">
            <div class="col-md-12">
                <h4 class="text-white mb-3 ">Current Coin Averages:</h4>
                <div class="row ">
                    @php
                        $averages = App\CommonHelpers::getIndicatorAverages($symbol, $interval, $market);
                    @endphp
                    <!-- RSI Average Card -->
                    <div class="card text-white bg-primary mb-3 col-md-2 mx-3">
                        <div class="card-header">RSI Average</div>
                        <div class="card-body">
                            <h5 class="card-title">{{ round($averages['rsi6'], 4) }}</h5>
                        </div>
                    </div>
                    <!-- Stoch Average Card -->
                    <div class="card text-white bg-success mb-3 col-md-2 mx-3">
                        <div class="card-header">Stoch Average</div>
                        <div class="card-body">
                            <h5 class="card-title">{{ round($averages['stoch_d'], 4) }}</h5>
                        </div>
                    </div>
                    <!-- Highest OBV Card -->
                    <div class="card text-white bg-danger mb-3 col-md-2 mx-3">
                        <div class="card-header">Highest Obv</div>
                        <div class="card-body">
                            <h5 class="card-title">{{ round($averages['previousObvHigh'], 4) }}</h5>
                        </div>
                    </div>
                    <!-- OBV Card -->
                    <div class="card text-white bg-warning mb-3 col-md-2 mx-3">
                        <div class="card-header">Obv</div>
                        <div class="card-body">
                            <h5 class="card-title">{{ round($averages['obv'], 4) }}</h5>
                        </div>
                    </div>
                    <!-- OBV Limit Card -->
                    <div class="card text-white bg-info mb-3 col-md-2 mx-3">
                        <div class="card-header">OBV Limit</div>
                        <div class="card-body">
                            <h5 class="card-title">
                                {{ $averages['previousObvHigh'] != 0 ? abs(round((($averages['previousObvHigh'] - $averages['obv']) / $averages['previousObvHigh']) * 100, 2)) : '100%' }}
                            </h5>
                        </div>
                    </div>
                    <!-- K Card -->
                    <div class="card text-white bg-info mb-3 col-md-2 mx-3">
                        <div class="card-header">K</div>
                        <div class="card-body">
                            <h5 class="card-title">{{ round($averages['K'], 4) }}</h5>
                        </div>
                    </div>

                    <!-- D Card -->
                    <div class="card text-white bg-warning mb-3 col-md-2 mx-3">
                        <div class="card-header">D</div>
                        <div class="card-body">
                            <h5 class="card-title">{{ round($averages['D'], 4) }}</h5>
                        </div>
                    </div>

                    <!-- J Card -->
                    <div class="card text-white bg-success mb-3 col-md-2 mx-3">
                        <div class="card-header">J</div>
                        <div class="card-body">
                            <h5 class="card-title">{{ round($averages['J'], 4) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div> --}}


        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <h4 class="card-title">Candlestick Chart</h4>
                        <p class="card-category">Visual representation of trade data</p>
                        <div>
                            <span class="badge badge-rounded " style="background-color:green;color:white">Buying</span>
                            <span class="badge badge-rounded " style="background-color:orange;color:white">Ideal
                                Buying</span>
                            <span class="badge badge-rounded " style="background-color:red;color:white">Buying</span>
                        </div>
                    </div>

                    <div class="card-body">

                        <canvas id="candlestickChart"></canvas>
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
                const candlestickData = @json($candlestickData);
                const trades = [];
                const buyTriggers = @json($buyTriggers);
                const sellTriggers = @json($sellTriggers);
                const lowestTriggers = @json($lowestTriggers);

                // Extract timestamps and close prices
                const timestamps = candlestickData.map(data => data.timestamp);
                const closePrices = candlestickData.map(data => data.close);

                // Determine point colors and styles based on buy/sell triggers
                const pointStyles = timestamps.map((timestamp, index) => {
                    const binanceTimestamp = candlestickData[index].binance_timestamp;
                    if (buyTriggers.includes(binanceTimestamp)) {
                        return {
                            backgroundColor: 'green', // Green for buy triggers
                            borderColor: 'darkgreen', // Darker green border
                            radius: 6 // Larger point radius
                        };
                    } else if (sellTriggers.includes(binanceTimestamp)) {
                        return {
                            backgroundColor: 'red', // Red for sell triggers
                            borderColor: 'darkred', // Darker red border
                            radius: 6 // Larger point radius
                        };
                    } else if (lowestTriggers.includes(binanceTimestamp)) {
                        return {
                            backgroundColor: 'orange', // Red for sell triggers
                            borderColor: 'darkred', // Darker red border
                            radius: 6 // Larger point radius
                        };
                    } else {
                        return {
                            backgroundColor: 'lightBlue', // Light blue for other points
                            borderColor: 'blue', // Blue border
                            radius: 3 // Normal point radius
                        };
                    }
                });

                // Initialize Chart.js
                const ctx = document.getElementById('candlestickChart').getContext('2d');
                window.candlestickChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: timestamps,
                        datasets: [{
                            label: 'Close Prices',
                            data: closePrices,
                            borderColor: 'blue',
                            backgroundColor: 'rgba(0, 94, 255, 0.22)',
                            fill: true,
                            tension: 0.1,
                            yAxisID: 'y',
                            pointBackgroundColor: pointStyles.map(style => style.backgroundColor),
                            pointBorderColor: pointStyles.map(style => style.borderColor),
                            pointRadius: pointStyles.map(style => style.radius)
                        }],
                    },
                    options: {
                        responsive: true,
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Timestamp',
                                },
                                ticks: {
                                    color: '#ccc', // Light grey color for ticks
                                    display: false,
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Close Value',
                                },
                                ticks: {
                                    color: '#ccc', // Light grey color for ticks
                                }
                            }
                        },
                        plugins: {
                            zoom: {
                                pan: {
                                    enabled: true,
                                    mode: 'xy'
                                },
                                zoom: {
                                    pinch: {
                                        enabled: true
                                    },
                                    wheel: {
                                        enabled: true,
                                        speed: 0.1,
                                        threshold: 10,
                                        modifierKey: 'ctrl'



                                    },
                                    mode: 'x'
                                }
                            }
                        }

                    }
                });

                window.showDetails = function(tradeId, button) {
                    const detailRow = document.getElementById('details-' + tradeId);
                    const isVisible = !detailRow.classList.contains('d-none');
                    document.querySelectorAll('.trade-details').forEach(el => el.classList.add(
                        'd-none')); // Hide all
                    if (!isVisible) {
                        detailRow.classList.remove('d-none');
                        const trade = trades.find(t => t.id === tradeId);
                        zoomChartToTrade(trade.buyingCandle.timestamp, trade.sellingCandle.timestamp,
                            window.candlestickChart, timestamps);
                    }
                };

                function zoomChartToTrade(buyTimestamp, sellTimestamp, chart, timestamps) {
                    console.log(timestamps.findIndex(timestamp => timestamp === buyTimestamp))
                    const minIndex = timestamps.findIndex(timestamp => timestamp === buyTimestamp);
                    const maxIndex = timestamps.findIndex(timestamp => timestamp === sellTimestamp);

                    if (minIndex !== -1 && maxIndex !== -1) {
                        chart.options.scales.x.min = timestamps[minIndex];
                        chart.options.scales.x.max = timestamps[maxIndex];
                        chart.update();
                    }
                }
            });
        </script>
    </div>
@endsection
