@extends('layouts.app')

@php
    $buyTriggers = [];
    $sellTriggers = [];
@endphp

@section('content')
    <div class="container-fluid mt-5">
        <h2 class="mb-4 text-white">Trade Details for {{ $symbol }} - {{ $interval }}</h2>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <h4 class="card-title">Candlestick Chart</h4>
                        <p class="card-category">Visual representation of trade data</p>
                    </div>
                    <div class="card-body">
                        <canvas id="candlestickChart"></canvas>
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
                                        <th>Trade ID</th>
                                        <th>Buying Price (USDT)</th>
                                        <th>Lowest Price (USDT)</th>
                                        <th>Selling Price (USDT)</th>
                                        <th>Buying Time</th>
                                        <th>Selling Time</th>
                                        <th>Profit (%)</th>
                                        <th>Duration (mins)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trades as $trade)
                                        @php
                                            $buyCandle = json_decode(json_encode($trade->buyingCandle), true);
                                            $sellCandle = json_decode(json_encode($trade->sellingCandle), true);
                                            $buyTriggers[] = $buyCandle['binance_timestamp'];
                                            $sellTriggers[] = $sellCandle['binance_timestamp'];
                                        @endphp
                                        <tr>
                                            <td>{{ $trade->id }}</td>
                                            <td>{{ number_format($trade->buyingPrice, 4) }}</td>
                                            <td>{{ number_format($trade->lowestPrice, 2) }}
                                                ({{ number_format($trade->lowestPricePercentage, 2) }}%)
                                            </td>
                                            <td>{{ number_format($trade->sellingPrice, 4) }}</td>
                                            <td>{{ \Carbon\Carbon::parse($buyCandle['timestamp'])->format('h:i A') }}
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($sellCandle['timestamp'])->format('h:i A') }}
                                            </td>
                                            <td>{{ number_format($trade->profit, 2) }}%</td>
                                            <td>{{ $trade->duration }}</td>
                                            <td>
                                                <button class="btn btn-info btn-sm"
                                                    onclick="window.showDetails({{ $trade->id }},this)">Show
                                                    Details</button>
                                            </td>
                                        </tr>
                                        <tr id="details-{{ $trade->id }}" class="trade-details d-none">
                                            <td colspan="9">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <h5 class="text-success">Buying Details:</h5>
                                                        <div>
                                                            <strong>RSI:</strong> {{ round($buyCandle['rsi6']) }}
                                                            (Threshold: ≤ 18)<br>
                                                            <strong>StochRSI:</strong> {{ round($buyCandle['stoch_rsi']) }}
                                                            (Limit: ≤ 3)<br>
                                                            {{-- <strong>OBV:</strong> Highest in last 15 candles, Limit: 50%<br> --}}
                                                            <strong>Moving Averages:</strong>
                                                            <ul>
                                                                <li>MA7: {{ round($buyCandle['ma7'], 4) }} ( Less than MA25
                                                                    )</li>
                                                                <li>MA14: {{ round($buyCandle['ma14'], 4) }} </li>
                                                                <li>MA25: {{ round($buyCandle['ma25'], 4) }} ( Less than
                                                                    MA99 )</li>
                                                                <li>MA99: {{ round($buyCandle['ma99'], 4) }}</li>
                                                            </ul>

                                                            <hr>
                                                            <strong>Other Indicators:</strong>
                                                            <ul>
                                                                <li>SAR: {{ round($buyCandle['sar'],3) }}</li>
                                                                <li>DIF: {{ round($buyCandle['dif'],3) }}</li>
                                                                <li>DEA: {{ round($buyCandle['dea'],3) }}</li>
                                                                <li>OBV: {{ round($buyCandle['obv'],3) }}</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <h5 class="text-info">Coin Selection Conditions:</h5>
                                                        <div>
                                                            <strong>Min (24h) Change:</strong> -5%<br>
                                                            <strong>Max (24h) Change:</strong> 5%<br>
                                                            <strong>Quantity:</strong> 100<br>
                                                        </div>
                                                        <h5 class="text-info">Profit Details:</h5>
                                                        <div>
                                                            <strong>Minimum Target Profit:</strong> 0.4%<br>
                                                        </div>

                                                    </div>
                                                    <div class="col-md-4">
                                                        <h5 class="text-danger">Selling Details:</h5>
                                                        <div>
                                                            <strong>RSI:</strong> {{ round($sellCandle['rsi6']) }}<br>
                                                            <strong>StochRSI:</strong> {{ round($sellCandle['stoch_rsi']) }}<br>
                                                            <strong>Moving Averages:</strong>
                                                            <ul>
                                                                <li>MA7: {{round( $sellCandle['ma7'],4) }}</li>
                                                                <li>MA14: {{ round($sellCandle['ma14'],4) }}</li>
                                                                <li>MA25: {{ round($sellCandle['ma25'],4) }}</li>
                                                                <li>MA99: {{ round($sellCandle['ma99'],4) }}</li>
                                                            </ul>
                                                            <hr>
                                                            <strong>Other Indicators:</strong>
                                                            <ul>
                                                                <li>SAR: {{ round($sellCandle['sar'],3) }}</li>
                                                                <li>DIF: {{ round($sellCandle['dif'],3) }}</li>
                                                                <li>DEA: {{ round($sellCandle['dea'],3) }}</li>
                                                                <li>OBV: {{ round($sellCandle['obv'],3) }}</li>
                                                            </ul>
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
            document.addEventListener("DOMContentLoaded", function() {
                const candlestickData = @json($data);
                const trades = @json($trades);
                const buyTriggers = @json($buyTriggers);
                const sellTriggers = @json($sellTriggers);

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
                const chartElement = document.getElementById('candlestickChart');


                // Add event listener for mouse enter
                chartElement.addEventListener('mouseenter', function() {
                    if (!isScrolling) {
                        ps.settings.wheelSpeed = 0
                        console.log("scrolling Stopped")
                    }

                });
                // Add event listener for mouse leave
                chartElement.addEventListener('mouseleave', function() {
                    if (!isScrolling) {
                        ps.settings.wheelSpeed = 2

                    }

                });
                if (!isScrolling) {
                    ps.settings.wheelSpeed = 2

                }
            });
        </script>
    @endsection
