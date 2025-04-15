@extends('layouts.app')
@php
    $totalProfit = 0;
    $totalTrades = 0;
    $percentageProgress = DB::table('formula_details')->where('formula', request('formula'))->first();
    $percentageProgress = $percentageProgress ? $percentageProgress->progress : 100;
@endphp
@section('content')
    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <h4 class="card-title ">Internal Trades Report (Recent 1000 Candles)</h4>
                        <p class="card-category"> Here is a list of the latest trades across all coins.
                            ({{ $percentageProgress }} % Completed)</p>

                        <div class="progress m-2" style="height: 5px; ">
                            <div class="progress-bar" role="progressbar"
                                style="width: {{ $percentageProgress }}%; background-color: #00f2c3;"
                                aria-valuenow="{{ $percentageProgress }}" aria-valuemin="0" aria-valuemax="100">

                            </div>
                        </div>
                    </div>
                    <form method="GET" action="{{ url()->current() }}" class="p-3">
                        <input type="hidden" name="interval" value="{{ request()->get('interval') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="position">Filter by Position</label>
                                    <select name="position" id="position" class="form-control select2">
                                        <option value="">All Positions</option>
                                        <option value="LONG" {{ request('position') == 'LONG' ? 'selected' : '' }}>LONG
                                        </option>
                                        <option value="SHORT" {{ request('position') == 'SHORT' ? 'selected' : '' }}>SHORT
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="formula">Filter by Formula</label>
                                    <select name="formula" id="formula" class="form-control select2">
                                        <option value="">All Formulas</option>
                                        @foreach (DB::table('formula_details')->distinct('formula')->orderBy('created_at', 'DESC')->get() as $formula)
                                            <option value="{{ $formula->formula }}"
                                                {{ request('formula') == $formula->formula ? 'selected' : '' }}>
                                                {{ $formula->formula }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="showTimeline">Trade Timeline</label>
                                    <select name="showTimeline" id="showTimeline" class="form-control select2">
                                        <option value="">Hidden</option>
                                        <option value="show" {{ request('showTimeline') == 'show' ? 'selected' : '' }}>
                                            Shown
                                        </option>

                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 ">
                                <button type="submit" class="btn btn-primary my-2">Apply</button>
                            </div>
                            <div class="col-md-2 ">
                                <a href="{{ route('coinReport', ['market' => 'FUTURE', 'interval' => '5m']) }}"
                                    class="btn btn-secondary my-2">Clear</a>
                            </div>



                        </div>
                    </form>


                    <div class="card-body">


                        @if (request('formula'))
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-primary">
                                        <tr>
                                            <th>No</th>
                                            <th>Position</th>
                                            <th>Symbol</th>
                                            <th>Total Duration (min)</th>
                                            <th>Average Duration (min)</th>
                                            <th>Total Trades</th>
                                            <th>Total Profit (%)</th>
                                            <th>Average Profit (%)</th>
                                            <th>Max Profit (%)</th>
                                            <th>Min Profit (%)</th>
                                            <th>Max Lowest Price (%)</th>
                                            <th>Min Lowest Price (%)</th>
                                            <th>Formula</th>
                                            <th>Updated at</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($tradeData as $index => $trade)
                                            @php
                                                $totalProfit += number_format($trade->total_profit, 2);
                                                $totalTrades += $trade->total_entries;
                                            @endphp
                                            <tr @if ($trade->min_profit < 0) class="bg-danger" @endif>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $trade->position }}</td>
                                                <td>{{ $trade->symbol }}</td>
                                                <td>{{ $trade->total_duration }}</td>
                                                <td>{{ number_format($trade->average_duration, 2) }}</td>
                                                <td>{{ $trade->total_entries }}</td>
                                                <td>{{ number_format($trade->total_profit, 2) }} %</td>
                                                <td>{{ number_format($trade->average_profit, 2) }} %</td>
                                                <td>{{ number_format($trade->max_profit, 2) }} %</td>
                                                <td>{{ number_format($trade->min_profit, 2) }} %</td>
                                                <td>{{ number_format($trade->max_lowestPrice, 2) }} %</td>
                                                <td>{{ number_format($trade->min_lowestPrice, 2) }} %</td>
                                                <td>{{ $trade->formula }}</td>
                                                <td>{{ \Carbon\Carbon::parse($trade->last_updated)->timezone('Asia/Karachi')->format('h:i A') }}
                                                </td>
                                                <td>
                                                    <a href="{{ route('coinReportDetails', ['market' => $market, 'symbol' => $trade->symbol, 'position' => $trade->position, 'formula' => $trade->formula, 'stopLoss' => request('stopLoss'), 'interval' => '5m']) }}"
                                                        class="btn btn-info btn-sm">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                                <!-- Stats Summary Table -->
                                <div class="mt-4">
                                    <h5 class="text-primary">Trading Performance Summary</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-stats">
                                            <thead class="bg-dark text-white">
                                                <tr>
                                                    <th>Metric</th>
                                                    <th>Value</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="font-weight-bold">1h+ Duration</td>
                                                    <td>{{ round($tradesAbove1h ?? 0) }}</td>
                                                    <td>Trades are above one hour</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Max Trades at a time</td>
                                                    <td>{{ round($maxNearbyTrades?->entry_count) }}</td>
                                                    <td>at {{ $maxNearbyTrades?->time_interval }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Average Duration</td>
                                                    <td>{{ round($averageDuration) }} min</td>
                                                    <td>Average time a trade is active</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total Profit</td>
                                                    <td>{{ $profitsTotal }} %</td>
                                                    <td>From {{ $profitableTrades }} profitable trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total Stop Losses</td>
                                                    <td>{{ $stopLossesTotal }} %</td>
                                                    <td>From {{ $stopLossesTrades }} stop loss trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Grand Total</td>
                                                    <td>{{ $profitsTotal - $stopLossesTotal }} %</td>
                                                    <td>From {{ $totalTrades }} total trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Formula Accuracy</td>
                                                    <td>{{ $totalTrades ? round(100 - ($stopLossesTrades / $totalTrades) * 100, 2) : 0 }}
                                                        %</td>
                                                    <td>Success rate of profitable trades </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="card-category text-center">Please Select any formula to show results...</p>
                        @endif
                    </div>
                </div>
                @if (request()->get('formula'))
                    @php
                        $formulaDetails = DB::table('formula_details')->where('formula', request('formula'))->first();
                    @endphp



                    {!! $formulaDetails ? $formulaDetails->details : '<p>No details available for the selected formula.</p>' !!}
                @endif
                @if (request('showTimeline') === 'show')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Trade Timeline</h4>
                                    <p class="card-category">Visual representation of trade timelines</p>
                                    <div>
                                        <span class="badge badge-rounded "
                                            style="background-color:green;color:white">LONG</span>

                                        <span class="badge badge-rounded "
                                            style="background-color:red;color:white">SHORT</span>
                                    </div>
                                </div>


                                <div class="card-body chart-container">

                                    <canvas id="timelineChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>




    <!-- Chart for timeline visualization -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-moment/1.0.1/chartjs-adapter-moment.min.js">
    </script>


    @if (request('showTimeline') === 'show')
        <script>
            // Dummy data - using SQL-friendly datetime format (YYYY-MM-DD HH:MM:SS)

            const data = @json($timelineData);
            // console.log(data)
            // Chart setup
            window.onload = function() {
                createTimelineChart(data);
            };

            function createTimelineChart(data) {
                const ctx = document.getElementById('timelineChart').getContext('2d');

                // Find the earliest start time and latest end time automatically from data
                const startTimes = data.map(item => new Date(item.startTime.replace(' ', 'T')).getTime());
                const endTimes = data.map(item => new Date(item.endTime.replace(' ', 'T')).getTime());

                const earliestTime = Math.min(...startTimes);
                const latestTime = Math.max(...endTimes);

                // Calculate duration for each item
                data.forEach(item => {
                    const start = new Date(item.startTime.replace(' ', 'T'));
                    const end = new Date(item.endTime.replace(' ', 'T'));
                    item.duration = (end - start) / 1000; // Duration in seconds
                });

                // Prepare datasets for horizontal bar chart
                const datasets = data.map((item, index) => {
                    return {
                        label: item.symbol,
                        data: [{
                            x: [new Date(item.startTime.replace(' ', 'T')), new Date(item.endTime.replace(' ',
                                'T'))],
                            y: item.symbol
                        }],
                        backgroundColor: item.color,
                        borderColor: item.color,
                        borderWidth: 2, // More reasonable border width (500 was extremely large)
                        barThickness: 2, // Fixed bar thickness for better visibility
                        barPercentage: 0.8 // Control spacing between bars
                    };
                });

                // Create the chart
                const chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        datasets: datasets
                    },
                    options: {
                        indexAxis: 'y',
                        scales: {
                            x: {
                                type: 'time',
                                position: 'bottom',
                                time: {
                                    unit: 'minute',
                                    displayFormats: {
                                        minute: 'HH:mm:ss'
                                    }
                                },
                                // Automatically set min and max with padding
                                min: new Date(earliestTime - 60000), // 1 minute padding
                                max: new Date(latestTime + 60000)
                            },
                            y: {
                                beginAtZero: true
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const datapoint = context.raw;
                                        // Format dates in SQL-friendly format for tooltips
                                        const startTime = moment(datapoint.x[0]).format('YYYY-MM-DD HH:mm:ss');
                                        const endTime = moment(datapoint.x[1]).format('YYYY-MM-DD HH:mm:ss');
                                        const duration = (new Date(datapoint.x[1]) - new Date(datapoint.x[0])) /
                                            1000;

                                        return [
                                            `Symbol: ${datapoint.y}`,
                                            `Start: ${startTime}`,
                                            `End: ${endTime}`,
                                            `Duration: ${duration.toFixed(1)}s`
                                        ];
                                    }
                                }
                            },
                            legend: {
                                display: false
                            }
                        },
                        // // Enable cursor interaction
                        // onHover: (event, elements) => {
                        //     updateCursorInfo(chart, event);
                        // }
                    }
                });

                // Create container for cursor elements if it doesn't exist
                let chartContainer = document.querySelector('.chart-container');
                if (!chartContainer) {
                    // If .chart-container doesn't exist, create it or use the canvas parent
                    const canvas = document.getElementById('timelineChart');
                    chartContainer = canvas.parentElement;
                    chartContainer.classList.add('chart-container');
                    chartContainer.style.position = 'relative';
                } else {
                    chartContainer.style.position = 'relative';
                }

                // Create cursor info display elements
                const cursorInfoId = 'cursorInfo-' + Math.random().toString(36).substr(2, 9); // Generate unique ID
                const cursorLineId = 'cursorLine-' + Math.random().toString(36).substr(2, 9); // Generate unique ID

                // Remove any existing cursor elements (for reload cases)
                const existingInfo = document.getElementById(cursorInfoId);
                if (existingInfo) existingInfo.remove();
                const existingLine = document.getElementById(cursorLineId);
                if (existingLine) existingLine.remove();

                const cursorInfoDiv = document.createElement('div');
                cursorInfoDiv.id = cursorInfoId;
                cursorInfoDiv.style.position = 'absolute';
                cursorInfoDiv.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
                cursorInfoDiv.style.color = 'white';
                cursorInfoDiv.style.padding = '8px';
                cursorInfoDiv.style.borderRadius = '4px';
                cursorInfoDiv.style.pointerEvents = 'none';
                cursorInfoDiv.style.display = 'none';
                cursorInfoDiv.style.zIndex = '100';
                cursorInfoDiv.style.fontSize = '14px';
                chartContainer.appendChild(cursorInfoDiv);

                const cursorLine = document.createElement('div');
                cursorLine.id = cursorLineId;
                cursorLine.style.position = 'absolute';
                cursorLine.style.backgroundColor = 'rgba(0, 183, 255, 0.5)';
                cursorLine.style.height = '100%';
                cursorLine.style.width = '1px';
                cursorLine.style.pointerEvents = 'none';
                cursorLine.style.display = 'none';
                cursorLine.style.zIndex = '99';
                chartContainer.appendChild(cursorLine);

                // Add mouse move event listener to chart canvas
                document.getElementById('timelineChart').addEventListener('mousemove', function(e) {
                    updateTimelineCursor(chart, e, cursorInfoId, cursorLineId, data);
                });

                // Add mouse leave event listener
                document.getElementById('timelineChart').addEventListener('mouseleave', function() {
                    document.getElementById(cursorInfoId).style.display = 'none';
                    document.getElementById(cursorLineId).style.display = 'none';
                });

                return chart;
            }

            // Function to update cursor info - TIME-BASED version
            function updateTimelineCursor(chart, event, cursorInfoId, cursorLineId, data) {
                const cursorInfo = document.getElementById(cursorInfoId);
                const cursorLine = document.getElementById(cursorLineId);

                if (!cursorInfo || !cursorLine) return; // Safety check

                const canvas = chart.canvas;
                const rect = canvas.getBoundingClientRect();
                const x = event.clientX - rect.left;

                // Get the mouse position relative to the chart area
                const chartArea = chart.chartArea;

                // Only show cursor if within chart area
                if (x >= chartArea.left && x <= chartArea.right) {
                    // Get current time based on x position
                    const xScale = chart.scales.x;
                    const currentTime = xScale.getValueForPixel(x);

                    // Find entries that intersect with this time point
                    const intersectingEntries = [];

                    // Loop through each dataset to find entries that intersect the cursor time
                    chart.data.datasets.forEach(dataset => {
                        dataset.data.forEach(item => {
                            const startTime = new Date(item.x[0]).getTime();
                            const endTime = new Date(item.x[1]).getTime();

                            if (currentTime >= startTime && currentTime <= endTime) {
                                intersectingEntries.push({
                                    symbol: item.y,
                                    startTime: new Date(item.x[0]),
                                    endTime: new Date(item.x[1])
                                });
                            }
                        });
                    });

                    // Get the count of intersecting entries
                    const intersectionCount = intersectingEntries.length;

                    // Format the cursor time
                    const cursorTimeFormatted = moment(currentTime).format('HH:mm:ss');

                    // Update cursor info position and content
                    cursorInfo.style.display = 'block';
                    cursorInfo.style.left = (x + 15) + 'px';
                    cursorInfo.style.top = (chartArea.top - 30) + 'px'; // Position at top of chart
                    cursorInfo.innerHTML =
                        `<strong>Time:</strong> ${cursorTimeFormatted} | <strong>Active Trades:</strong> ${intersectionCount}`;

                    // Update cursor line
                    cursorLine.style.display = 'block';
                    cursorLine.style.left = (x + 15) + 'px';
                    cursorLine.style.top = chartArea.top + 'px';
                    cursorLine.style.height = (chartArea.bottom - chartArea.top) + 'px';

                    // Add symbols to cursor info if there are any
                    if (intersectionCount > 0) {
                        cursorInfo.innerHTML += '<br><strong>Symbols:</strong> ' +
                            intersectingEntries.map(entry => entry.symbol).join(', ');
                    }
                }
            }

            // Function to load your own data
            function loadData(newData) {
                // Clear existing chart
                const canvas = document.getElementById('timelineChart');
                canvas.remove();

                // Create new canvas
                const container = document.querySelector('.chart-container') || canvas.parentElement;
                const newCanvas = document.createElement('canvas');
                newCanvas.id = 'timelineChart';
                container.appendChild(newCanvas);

                // Create new chart with new data
                createTimelineChart(newData);
            }
        </script>
    @endif
@endsection
