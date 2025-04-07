@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card shadow">
        <div class="card-header  d-flex justify-content-between align-items-center">
            <h2 class="mb-0 text-white">Order Book Snapshot Details</h2>
            <a href="{{ route('order-book.index') }}" class="btn btn-primary">
                <i class="fas fa-arrow-left "></i> Back to List
            </a>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <h4 class="card-title">Candlestick Chart</h4>
                        <div>
                            <span class="badge badge-rounded " style="background-color:green;color:white">Long</span>
                            <span class="badge badge-rounded " style="background-color:red;color:white">Short</span>
                        </div>
                    </div>

                    <div class="card-body">

                        <canvas id="candlestickChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Metadata Section -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">ID</th>
                                    <td>{{ $snapshot->id }}</td>
                                </tr>
                                <tr>
                                    <th>Symbol</th>
                                    <td><span class="badge bg-info p-2 text-lg">{{ $snapshot->symbol }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Snapshot Time</th>
                                    <td>{{ \Carbon\Carbon::parse($snapshot->snapshot_time)->format('F d, Y h:i A') }}
                                        (Shown on Chart)
                                    </td>
                                </tr>
                                <tr>
                                    <th>Depth</th>
                                    <td>{{ $snapshot->depth }}</td>
                                </tr>

                                <tr>
                                    <th>Profit / SL</th>
                                </tr>
                                <tr>
                                    <form method="GET" action="{{ url()->current() }}">
                                        <td>
                                            <input type="number" step="any" name="tp" class="form-control"
                                                value="{{ request('tp') }}" placeholder="Profit %">
                                        </td>
                                        <td>
                                            <input type="number" step="any" name="sl" class="form-control"
                                                value="{{ request('sl') }}" placeholder="Stop Loss %">
                                        </td>
                                        <td>

                                            <button type="submit" class="btn btn-primary">Apply</button>
                                        </td>
                                    </form>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Market Stats Section -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Market Statistics</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Bid Volume</th>
                                    <td>{{ number_format($snapshot->bid_volume, 5) }}</td>
                                </tr>
                                <tr>
                                    <th>Ask Volume</th>
                                    <td>{{ number_format($snapshot->ask_volume, 5) }}</td>
                                </tr>
                                <tr>
                                    <th>Volume Imbalance (Bids/Asks)</th>
                                    <td class="text-success">{{ number_format($snapshot->volume_imbalance, 5) }} <i class="fas {{$snapshot->volume_imbalance > 1? 'fa-arrow-up text-success':'fa-arrow-down text-danger'}}"></i></td>
                                   


                                </tr>
                                <tr>
                                    <th>Highest Bid</th>
                                    <td class="text-success">{{ number_format($snapshot->highest_bid, 5) }}</td>
                                </tr>
                                <tr>
                                    <th>Lowest Ask</th>
                                    <td class="text-danger">{{ number_format($snapshot->lowest_ask, 5) }}</td>
                                </tr>
                                <tr>
                                    <th>Spread</th>
                                    <td>{{ number_format($snapshot->spread, 5) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Analysis Section -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Signal Analysis</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Signal</th>
                                    <td>
                                        @if ($snapshot->signal == 'LONG')
                                        <span class="badge bg-success">LONG</span>
                                        @elseif($snapshot->signal == 'SHORT')
                                        <span class="badge bg-danger">SHORT</span>
                                        @elseif($snapshot->signal == 'NEUTRAL')
                                        <span class="badge bg-primary">NEUTRAL</span>
                                        @else
                                        <span class="badge bg-info">{{ $snapshot->signal }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Long Strength</th>
                                    <td>
                                        <div class="progress" style="height: 15px;">
                                            <div class="progress-bar bg-success " role="progressbar"
                                                style="width: {{ ($snapshot->long_strength / 10) * 100 }}%">
                                                {{ $snapshot->long_strength }}/10
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Short Strength</th>
                                    <td>
                                        <div class="progress" style="height: 15px;">
                                            <div class="progress-bar bg-danger" role="progressbar"
                                                style="width: {{ ($snapshot->short_strength / 10) * 100 }}%">
                                                {{ $snapshot->short_strength }}/10
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Critical Levels Section -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Critical Levels</h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="criticalLevelsTabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#support">Support</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#resistance">Resistance</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#liquidity">Thin Liquidity</a>
                                </li>
                            </ul>
                            <div class="tab-content pt-3">
                                <div class="tab-pane fade show active" id="support">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Price Level</th>
                                                    <th>Strength</th>
                                                    <th>Volume</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($snapshot->support_levels as $level)
                                                <tr>
                                                    <td>{{ $level->price ?? $level['price'] }}</td>
                                                    <td>
                                                        @php
                                                        $strength =
                                                        $level->strength ?? ($level['strength'] ?? 5);
                                                        $strengthPercentage =
                                                        ($strength /
                                                        $snapshot->support_levels[0]['strength']) *
                                                        100;
                                                        @endphp
                                                        <div class="progress" style="height:15px">
                                                            <div class="progress-bar bg-success"
                                                                role="progressbar"
                                                                style="width: {{ $strengthPercentage }}%">
                                                                {{ round($strength) }} x
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>{{ $level->volume ?? ($level['volume'] ?? 'N/A') }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="resistance">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Price Level</th>
                                                    <th>Strength</th>
                                                    <th>Volume</th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                                @foreach ($snapshot->resistance_levels as $level)
                                                <tr>
                                                    <td>{{ $level->price ?? $level['price'] }}</td>
                                                    <td>
                                                        @php
                                                        $strength =
                                                        $level->strength ?? ($level['strength'] ?? 5);
                                                        $strengthPercentage =
                                                        ($strength /
                                                        $snapshot->resistance_levels[0][
                                                        'strength'
                                                        ]) *
                                                        100;
                                                        @endphp
                                                        <div class="progress" style="height:15px">
                                                            <div class="progress-bar bg-warning"
                                                                role="progressbar"
                                                                style="width: {{ $strengthPercentage }}%">
                                                                {{ round($strength) }} x
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>{{ $level->volume ?? ($level['volume'] ?? 'N/A') }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="liquidity">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Gap Size</th>
                                                    <th>Start Price</th>
                                                    <th>End Price</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($snapshot->thin_liquidity_areas as $area)
                                                <tr>
                                                    <td>
                                                        {{ $area['type'] ?? 'N/A' }}
                                                    </td>
                                                    <td>{{ $area['gap_size'] ?? 'N/A' }}</td>
                                                    <td>{{ round($area['start_price'], 5) ?? 'N/A' }}</td>
                                                    <td>{{ round($area['end_price'], 5) ?? 'N/A' }}</td>

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

                <!-- Entry Points Section -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Entry Points</h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="entryPointsTabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#long">
                                        <i class="fas fa-arrow-up text-success"></i> Long Entry Points
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#short">
                                        <i class="fas fa-arrow-down text-danger"></i> Short Entry Points
                                    </a>
                                </li>
                            </ul>
                            <div class="tab-content pt-3">
                                <div class="tab-pane fade show active" id="long">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Price</th>
                                                    <th>Confidence</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($snapshot->long_entry_points as $point)
                                                <tr>
                                                    <td class="text-success">
                                                        {{ $point->price ?? $point['price'] }}
                                                    </td>

                                                    <td>
                                                        @php
                                                        $confidence =
                                                        $point->confidence ??
                                                        ($point['confidence'] ?? 5);
                                                        $confidencePercentage = ($confidence / 5) * 100;
                                                        @endphp
                                                        <div class="progress" style="height:15px">
                                                            <div class="progress-bar bg-success"
                                                                role="progressbar"
                                                                style="width: {{ $confidencePercentage }}%">
                                                                {{ round($confidence, 1) }}/5
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="short">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Price</th>

                                                    <th>Confidence</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($snapshot->short_entry_points as $point)
                                                <tr>
                                                    <td class="text-danger">{{ $point->price ?? $point['price'] }}
                                                    </td>

                                                    <td>
                                                        @php
                                                        $confidence =
                                                        $point->confidence ??
                                                        ($point['confidence'] ?? 5);
                                                        $confidencePercentage = ($confidence / 5) * 100;
                                                        @endphp
                                                        <div class="progress" style="height:15px">
                                                            <div class="progress-bar bg-danger" role="progressbar"
                                                                style="width: {{ $confidencePercentage }}%">
                                                                {{ round($confidence, 1) }}/5
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
            </div>
        </div>

        <div class="card-footer text-muted">
            <small>Last updated: {{ $snapshot->updated_at ?? $snapshot->snapshot_time }}</small>
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const coinData = @json($coinData);
        console.log(coinData)
        // Extract timestamps and close prices
        const timestamps = coinData.map(data => data.timestamp);
        const closePrices = coinData.map(data => data.close);




        // Determine point colors and styles based on buy/sell triggers
        const pointStyles = coinData.map((candle, index) => {
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
                        label: 'Close Prices {{ request()->get('
                        symbol ') }}',
                        data: closePrices,
                        borderColor: 'rgba(52, 152, 219, 1.0)', // Blue border color
                        backgroundColor: 'rgba(52, 152, 219, 0.22)', // Transparent blue background

                        tension: 0.1,
                        yAxisID: 'y',
                        pointBackgroundColor: pointStyles.map(style => style.backgroundColor),
                        pointBorderColor: pointStyles.map(style => style.borderColor),
                        pointRadius: pointStyles.map(style => style.radius)
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

@section('scripts')
@endsection