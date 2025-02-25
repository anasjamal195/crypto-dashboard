@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">

            <div class="col-md-12">
                <div class="row">
                    <div class="card text-white bg-primary mb-3 col-md-2 mx-3">
                        <div class="card-header">Total Profit</div>
                        <div class="card-body">
                            <h5 class="card-title">{{ $totalProfit }} %</h5>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header card-header-primary">
                        <h4 class="card-title ">Market Trends Report</h4>
                        <p class="card-category">Here is a list of market trends for various symbols</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-primary">
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Market</th>
                                        <th>Interval</th>
                                        <th>Signal</th>
                                        <th>Trade Type</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trends as $trend)
                                        <tr>
                                            <td>{{ $trend->symbol }}</td>
                                            <td>{{ $trend->market }}</td>
                                            <td>{{ $trend->interval }}</td>
                                            <td>
                                                <span
                                                    class="badge {{ $trend->signal === 'positive' ? 'badge-success' : 'badge-danger' }}">
                                                    {{ $trend->signal === 'positive' ? 'Green' : 'Red' }}
                                                </span>
                                            </td>
                                            <td>{{ $trend->tradeType }}</td>
                                            <td>{{ \Carbon\Carbon::parse($trend->updated_at)->timezone('Asia/Karachi')->format('d M Y, h:i A') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ url()->current() }}">
                            <div class="form-group row">
                                <label for="datetime" class="col-md-2 col-form-label text-md-right">Select Date and
                                    Time:</label>
                                <div class="col-md-4">
                                    <input type="datetime-local" class="form-control" id="datetime" name="timestamp"
                                        value="{{ request()->get('timestamp') }}" required>
                                    <input type="hidden" class="form-control" id="interval" name="interval"
                                        value="{{ request()->get('interval') }}" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary">Submit</button>
                                </div>
                            </div>
                        </form>

                        <canvas id="candlestickChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const historicalTrends = @json($historicalTrends);

            // Extract timestamps and close prices
            const timestamps = historicalTrends.map(data => data.timestamp);
            const closePrices = historicalTrends.map(data => data.close);
            const support = historicalTrends.map(data => data.support);
            const resistance = historicalTrends.map(data => data.resistance);



            // Determine point colors and styles based on buy/sell triggers
            const pointStyles = historicalTrends.map((candle, index) => {
                return {
                    backgroundColor: candle.marketTrend, // Soft green for buy triggers
                    borderColor: 'dark' + candle.marketTrend, // Darker green border
                    radius: candle.marketTrend !== 'blue' ? 6 : 4 // Larger point radius for emphasis
                };
            });



            // Initialize Chart.js
            const ctx = document.getElementById('candlestickChart').getContext('2d');
            window.candlestickChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: timestamps,
                    datasets: [{
                            label: 'Close Prices {{ request()->get('symbol') }}',
                            data: closePrices,
                            borderColor: 'rgba(52, 152, 219, 1.0)', // Blue border color
                            backgroundColor: 'rgba(52, 152, 219, 0.22)', // Transparent blue background

                            tension: 0.1,
                            yAxisID: 'y',
                            pointBackgroundColor: pointStyles.map(style => style.backgroundColor),
                            pointBorderColor: pointStyles.map(style => style.borderColor),
                            pointRadius: pointStyles.map(style => style.radius)
                        },
                        {
                            label: 'Resistance',
                            data: resistance,
                            borderColor: 'red', // Blue border color
                            backgroundColor: 'red', // Transparent blue background

                            tension: 0.1,
                            yAxisID: 'y',
                            pointBackgroundColor: 'red',
                            pointBorderColor: 'darkred',
                            pointRadius: 3
                        },

                        {
                            label: 'Support',
                            data: support,
                            borderColor: 'green', // Blue border color
                            backgroundColor: 'green', // Transparent blue background

                            tension: 0.1,
                            yAxisID: 'y',
                            pointBackgroundColor: 'green',
                            pointBorderColor: 'darkgreen',
                            pointRadius: 3
                        },
                    ],
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
@endsection
