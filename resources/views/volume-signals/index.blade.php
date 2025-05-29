@extends('layouts.app', ['pageSlug' => $pageSlug])

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-12">
                <div class="card card-chart">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <h2 class="card-title">{{ $symbol }} Volume Analysis</h2>
                                <p class="card-category">Volume-based trading signals</p>
                            </div>
                            <div class="col-sm-6">
                                <form action="" method="GET"
                                    class="d-flex justify-content-end">
                                    <div class="form-group mr-3">
                                        <label for="symbol">Symbol</label>
                                        <input type="text" class="form-control" id="symbol" name="symbol"
                                            value="{{ request('symbol', 'BTCUSDT') }}">
                                    </div>
                                    <div class="form-group mr-3">
                                        <label for="interval">Interval</label>
                                        <input type="text" class="form-control" id="interval" name="interval"
                                            value="{{ request('interval', '5m') }}">

                                    </div>
                                    <div class="form-group mr-3">
                                        <label for="limit">Limit</label>
                                        <input type="number" class="form-control" id="limit" max="1000"
                                            name="limit" value="{{ request('limit', 100) }}">
                                    </div>
                                    <div class="form-group d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height:50vh; width:100%">
                            <canvas id="candlestick-chart"></canvas>
                        </div>
                        <div class="chart-controls mt-3">
                            <button class="btn btn-sm btn-primary" id="resetZoom">Reset Zoom</button>
                            <div class="btn-group ml-2">
                                <button class="btn btn-sm btn-info" id="zoom1h">1H</button>
                                <button class="btn btn-sm btn-info" id="zoom4h">4H</button>
                                <button class="btn btn-sm btn-info" id="zoom1d">1D</button>
                                <button class="btn btn-sm btn-info" id="zoom1w">1W</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Latest Signal Card -->
        @if (count($volumeSignals) > 0)
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Latest Signal</h4>
                        </div>
                        <div class="card-body">
                            @php
                                $latestSignal = end($volumeSignals);
                            @endphp
                            <div class="latest-signal-card">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div
                                            class="signal-highlight {{ $latestSignal['signal'] == 'buy' ? 'bg-success' : 'bg-danger' }}">
                                            <h3 class="mb-0">{{ strtoupper($latestSignal['signal']) }}</h3>
                                            <p class="mb-0">Signal Strength: {{ $latestSignal['strength'] }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="signal-details">
                                            <h4>{{ $latestSignal['symbol'] }} at {{ $latestSignal['price'] }}</h4>
                                            <p>{{ $latestSignal['timestampReadable'] }}</p>
                                            <div class="reasons">
                                                @foreach ($latestSignal['reasons'] as $reason)
                                                    <span
                                                        class="badge {{ strpos($reason, 'Warning') !== false ? 'badge-warning' : ($latestSignal['signal'] == 'buy' ? 'badge-success' : 'badge-danger') }} mr-1">{{ $reason }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="key-indicators">
                                            <div class="indicator-item">
                                                <span class="indicator-label">MFI:</span>
                                                <span
                                                    class="indicator-value">{{ round($latestSignal['indicators']['mfi_current'], 2) }}</span>
                                            </div>
                                            <div class="indicator-item">
                                                <span class="indicator-label">CVD:</span>
                                                <span
                                                    class="indicator-value">{{ round($latestSignal['indicators']['cvd_current'], 2) }}</span>
                                            </div>
                                            <div class="indicator-item">
                                                <span class="indicator-label">VWAP:</span>
                                                <span
                                                    class="indicator-value">{{ round($latestSignal['indicators']['vwap_current'], 2) }}</span>
                                            </div>
                                            <div class="indicator-item">
                                                <span class="indicator-label">OBV:</span>
                                                <span
                                                    class="indicator-value">{{ round($latestSignal['indicators']['obv_current'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Signal History Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Signal History</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table tablesorter" id="signal-history-table">
                                <thead class="text-primary">
                                    <tr>
                                        <th></th>
                                        <th>Time</th>
                                        <th>Signal</th>
                                        <th>Price</th>
                                        <th>Strength</th>
                                        <th>MFI</th>
                                        <th>CVD</th>
                                        <th>OBV</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (array_reverse($volumeSignals) as $index => $signal)
                                        <tr data-signal-index="{{ $index }}">
                                            <td>
                                                <button class="btn btn-link btn-expand" data-toggle="collapse"
                                                    data-target="#signal-details-{{ $index }}">
                                                    <i class="tim-icons icon-minimal-down"></i>
                                                </button>
                                            </td>
                                            <td>{{ $signal['timestampReadable'] }}</td>
                                            <td>
                                                <span
                                                    class="badge {{ $signal['signal'] == 'buy' ? 'bg-success' : 'bg-danger' }}">{{ $signal['signal'] }}
                                                </span>
                                            </td>
                                            <td>{{ $signal['price'] }}</td>
                                            <td>{{ $signal['strength'] }}</td>
                                            <td>{{ round($signal['indicators']['mfi_current'], 2) }}</td>
                                            <td>{{ round($signal['indicators']['cvd_current'], 2) }}</td>
                                            <td>{{ round($signal['indicators']['obv_current'], 2) }}</td>
                                        </tr>
                                        <tr class="expandable-row">
                                            <td colspan="8" class="p-0">
                                                <div class="collapse" id="signal-details-{{ $index }}">
                                                    <div class="card m-3">
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h5>Signal Reasons</h5>
                                                                    <ul class="list-unstyled">
                                                                        @foreach ($signal['reasons'] as $reason)
                                                                            <li
                                                                                class="{{ strpos($reason, 'Warning') !== false ? 'text-warning' : ($signal['signal'] == 'buy' ? 'text-success' : 'text-danger') }}">
                                                                                <i
                                                                                    class="tim-icons {{ strpos($reason, 'Warning') !== false ? 'icon-alert-circle-exc' : ($signal['signal'] == 'buy' ? 'icon-minimal-up' : 'icon-minimal-down') }} mr-1"></i>
                                                                                {{ $reason }}
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h5>Detailed Indicators</h5>
                                                                    <div class="row">
                                                                        @foreach ($signal['indicators'] as $key => $value)
                                                                            @if ($value !== null)
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                                                                                    <span>{{ is_numeric($value) ? round($value, 4) : $value }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
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
    </div>
@endsection

@push('js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@1.2.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Prepare data for chart
            const volumeSignals = @json($volumeSignals);
            const coinData = @json($coinData);

            // Sort data by timestamp in ascending order
            const sortedData = [...volumeSignals].sort((a, b) => a.timestamp - b.timestamp);

            // Extract data for chart
            const labels = sortedData.map(signal => {
                const date = new Date(signal.timestamp);
                return date.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    }) +
                    '\n' + date.toLocaleDateString([], {
                        month: 'short',
                        day: 'numeric'
                    });
            });

            const timestamps = sortedData.map(signal => signal.timestamp);
            const prices = sortedData.map(signal => signal.price);
            const mfiValues = sortedData.map(signal => signal.indicators.mfi_current);
            const obvValues = sortedData.map(signal => signal.indicators.obv_current);
            const cvdValues = sortedData.map(signal => signal.indicators.cvd_current);
            const vwapValues = sortedData.map(signal => signal.indicators.vwap_current);

            // Create buy/sell signals markers
            const buySignals = sortedData.map(signal => signal.signal === 'buy' ? signal.price : null);
            const sellSignals = sortedData.map(signal => signal.signal === 'sell' ? signal.price : null);

            // Create chart
            const ctx = document.getElementById('candlestick-chart').getContext('2d');
            const volumeChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Price',
                            data: prices,
                            borderColor: '#1d8cf8',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.1,
                            yAxisID: 'y',
                            order: 0
                        },
                        {
                            label: 'VWAP',
                            data: vwapValues,
                            borderColor: '#ff8d72',
                            backgroundColor: 'transparent',
                            borderWidth: 1,
                            borderDash: [5, 5],
                            tension: 0.1,
                            yAxisID: 'y',
                            order: 1
                        },
                        {
                            label: 'MFI',
                            data: mfiValues,
                            borderColor: '#00d6b4',
                            backgroundColor: 'transparent',
                            borderWidth: 1,
                            tension: 0.1,
                            yAxisID: 'y1',

                            order: 2
                        },
                        {
                            label: 'OBV',
                            data: obvValues,
                            borderColor: '#e14eca',
                            backgroundColor: 'transparent',
                            borderWidth: 1,
                            tension: 0.1,
                            yAxisID: 'y2',
                            hidden: true,
                            order: 3
                        },
                        {
                            label: 'CVD',
                            data: cvdValues,
                            borderColor: 'yellow',
                            backgroundColor: 'transparent',
                            borderWidth: 1,
                            tension: 0.1,
                            yAxisID: 'y2',
                            hidden: true,
                            order: 4
                        },
                        {
                            label: 'Buy Signals',
                            data: buySignals,
                            pointBackgroundColor: '#00d6b4',
                            pointRadius: function(context) {
                                const value = context.dataset.data[context.dataIndex];
                                return value !== null ? 6 : 0;
                            },
                            type: 'scatter',
                            showLine: false,
                            yAxisID: 'y',
                            order: -1,
                            hidden: true,

                        },
                        {
                            label: 'Sell Signals',
                            data: sellSignals,
                            pointBackgroundColor: '#fd5d93',
                            pointRadius: function(context) {
                                const value = context.dataset.data[context.dataIndex];
                                return value !== null ? 6 : 0;
                            },
                            type: 'scatter',
                            showLine: false,
                            yAxisID: 'y',
                            order: -1,
                            hidden: true,

                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#fff'
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        },
                        zoom: {
                            pan: {
                                enabled: true,
                                mode: 'x',
                                modifierKey: 'ctrl',
                            },
                            zoom: {
                                wheel: {
                                    enabled: true,
                                },
                                pinch: {
                                    enabled: true
                                },
                                mode: 'x',
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: '#9a9a9a',
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 10
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Price',
                                color: '#fff'
                            },
                            ticks: {
                                color: '#9a9a9a'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'MFI (0-100)',
                                color: '#fff'
                            },
                            min: 0,
                            max: 100,
                            ticks: {
                                color: '#9a9a9a'
                            },
                            grid: {
                                drawOnChartArea: false,
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        y2: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Volume Indicators',
                                color: '#fff'
                            },
                            grid: {
                                drawOnChartArea: false,
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#9a9a9a'
                            }
                        }
                    }
                }
            });

            // Reset zoom button
            document.getElementById('resetZoom').addEventListener('click', function() {
                volumeChart.resetZoom();
            });

            // Zoom preset buttons
            document.getElementById('zoom1h').addEventListener('click', function() {
                zoomToTimeframe(1);
            });

            document.getElementById('zoom4h').addEventListener('click', function() {
                zoomToTimeframe(4);
            });

            document.getElementById('zoom1d').addEventListener('click', function() {
                zoomToTimeframe(24);
            });

            document.getElementById('zoom1w').addEventListener('click', function() {
                zoomToTimeframe(168);
            });

            // Function to zoom to a specific timeframe
            function zoomToTimeframe(hours) {
                volumeChart.resetZoom();

                if (timestamps.length === 0) return;

                const lastTimestamp = timestamps[timestamps.length - 1];
                const targetTimestamp = lastTimestamp - (hours * 60 * 60 * 1000);

                let startIndex = 0;
                for (let i = 0; i < timestamps.length; i++) {
                    if (timestamps[i] >= targetTimestamp) {
                        startIndex = i;
                        break;
                    }
                }

                const min = startIndex / timestamps.length;
                const max = 1;

                volumeChart.zoomScale('x', {
                    min,
                    max
                }, 'default');
            }

            // Toggle indicators visibility
            const toggles = document.querySelectorAll('.toggle-indicator');
            toggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const indicator = this.dataset.indicator;
                    const datasetIndex = volumeChart.data.datasets.findIndex(dataset => dataset
                        .label === indicator);

                    if (datasetIndex !== -1) {
                        volumeChart.getDatasetMeta(datasetIndex).hidden = !volumeChart
                            .getDatasetMeta(datasetIndex).hidden;
                        volumeChart.update();
                    }
                });
            });

            // Expandable rows in signal history table
            const expandButtons = document.querySelectorAll('.btn-expand');
            expandButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    if (icon.classList.contains('icon-minimal-down')) {
                        icon.classList.remove('icon-minimal-down');
                        icon.classList.add('icon-minimal-up');
                    } else {
                        icon.classList.remove('icon-minimal-up');
                        icon.classList.add('icon-minimal-down');
                    }
                });
            });

            // Highlight row on hover instead of changing background
            const tableRows = document.querySelectorAll('#signal-history-table tbody tr:not(.expandable-row)');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.classList.add('highlighted-row');
                });

                row.addEventListener('mouseleave', function() {
                    this.classList.remove('highlighted-row');
                });
            });
        });
    </script>

    <style>
        .signal-highlight {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            color: white;
        }

        .latest-signal-card {
            padding: 10px;
        }

        .reasons {
            margin-top: 10px;
        }

        .indicator-item {
            margin-bottom: 8px;
        }

        .indicator-label {
            font-weight: bold;
            margin-right: 5px;
        }

        .text-success {
            color: #00d6b4 !important;
        }

        .text-danger {
            color: #fd5d93 !important;
        }

        .text-warning {
            color: #ff8d72 !important;
        }

        .expandable-row {
            background-color: transparent !important;
        }

        .btn-expand {
            padding: 5px;
            color: #9a9a9a;
        }

        .highlighted-row {
            background-color: rgba(34, 42, 66, 0.3);
        }

        .chart-controls {
            display: flex;
            justify-content: flex-start;
            align-items: center;
        }
    </style>
@endpush
