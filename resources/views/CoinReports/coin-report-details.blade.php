@extends('layouts.app')

@php
    $buyTriggers = [];
    $sellTriggers = [];
    $lowestTriggers = [];
    $liquidationTriggers = [];

@endphp

@section('content')
    <div class="container-fluid mt-5">
        <h2 class="mb-4 text-white">Trade Details for {{ $symbol }} - {{ $interval }} ({{ $market }})</h2>
        <div class="row mb-4">
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
        </div>


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
                                        <th>Liquidation Price (USDT)</th>
                                        <th>Lowest Price (USDT)</th>
                                        <th>Selling Price (USDT)</th>
                                        <th>Buying Time</th>
                                        <th>Ideal Buying Time</th>
                                        <th>Selling Time</th>
                                        <th>Profit (%)</th>
                                        <th>Nearby Trades</th>
                                        <th>Duration (mins)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trades as $trade)
                                        @php
                                            $buyCandle = json_decode(json_encode($trade->buyingCandle), true);
                                            $sellCandle = json_decode(json_encode($trade->sellingCandle), true);
                                            $buyingAverages = json_decode($trade->buyingAverages, true);
                                            $buyTriggers[] = $buyCandle['binance_timestamp'];
                                            $sellTriggers[] = $sellCandle['binance_timestamp'];
                                            $lowestIndex = -1;
                                            $lowestPrice = $data[0]['close'];
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
                                        <tr @if ($trade->lowestPricePercentage >= $stopLoss) class="bg-danger" @endif>
                                            <td>{{ $trade->id }}</td>
                                            <td>{{ number_format($trade->buyingPrice, 4) }}</td>
                                            <td>{{ number_format($trade->liquidationPrice, 4) }}</td>
                                            <td>{{ number_format($trade->lowestPrice, 4) }}
                                                ({{ number_format($trade->lowestPricePercentage, 2) }}%)
                                            </td>
                                            <td>{{ number_format($trade->sellingPrice, 4) }}</td>
                                            <td>{{ \Carbon\Carbon::parse($buyCandle['timestamp'])->format('h:i A') }}
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($lowestCandle['timestamp'])->format('h:i A') }}
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
                                                <div class="row mx-auto">
                                                    <div class="col-md-3">
                                                        <h5 class="text-success">Buying Details:</h5>
                                                        <div>
                                                            <strong>RSI:</strong>
                                                            {{ round($buyCandle['rsi6']) }}
                                                            <br>
                                                            <strong>StochRSI:</strong>
                                                            {{ round($buyCandle['stoch_rsi']) }}
                                                            <br>
                                                            {{-- <strong>OBV:</strong> Highest in last 15 candles, Limit: 50%<br> --}}
                                                            <strong>Moving Averages:</strong>
                                                            <ul>
                                                                <li>MA7: {{ round($buyCandle['ma7'], 4) }}
                                                                </li>
                                                                <li>MA14: {{ round($buyCandle['ma14'], 4) }}
                                                                </li>
                                                                <li>MA25: {{ round($buyCandle['ma25'], 4) }}
                                                                </li>
                                                                <li>MA99: {{ round($buyCandle['ma99'], 4) }}
                                                                </li>
                                                            </ul>
                                                            <strong>OBV:</strong>
                                                            <ul>
                                                                <li>OBV: {{ round($buyCandle['obv'], 4) }}</li>
                                                                <li>Highest OBV:
                                                                    {{ round($buyCandle['previousObvHigh'], 4) }}
                                                                </li>
                                                                <li>Obv Candlesticks: 15</li>
                                                                @php
                                                                    if ($buyCandle['previousObvHigh'] == 0) {
                                                                        if ($buyCandle['obv'] < 0) {
                                                                            // Define the percentage decrease as 100% or some other logic based on application needs
                                                                            $percentageDecrease = 100; // Example: Define a full decrease if previous is zero and current is negative
                                                                        } else {
                                                                            // If the previous is zero and the current is zero or positive, you might decide differently
                                                                            $percentageDecrease =
                                                                                $buyCandle['obv'] == 0
                                                                                    ? 0
                                                                                    : 'Not Calculable';
                                                                        }
                                                                    } else {
                                                                        $percentageDecrease =
                                                                            (($buyCandle['previousObvHigh'] -
                                                                                $buyCandle['obv']) /
                                                                                $buyCandle['previousObvHigh']) *
                                                                            100;
                                                                        if (
                                                                            $buyCandle['obv'] >
                                                                            $buyCandle['previousObvHigh']
                                                                        ) {
                                                                            $percentageDecrease = -abs(
                                                                                $percentageDecrease,
                                                                            ); // Indicates an increase
                                                                        } else {
                                                                            $percentageDecrease = abs(
                                                                                $percentageDecrease,
                                                                            ); // Confirms a decrease
                                                                        }
                                                                    }
                                                                @endphp
                                                                <li>Percentage Diff:
                                                                    {{-- {{ round($percentageDecrease, 2) }} --}}
                                                                </li>


                                                            </ul>

                                                            <hr>
                                                            <strong>KDJ:</strong>
                                                            <ul>
                                                                <li>K: {{ round($buyCandle['K'], 4) }}
                                                                </li>
                                                                <li>D: {{ round($buyCandle['D'], 4) }}
                                                                </li>
                                                                <li>J: {{ round($buyCandle['J'], 4) }}
                                                                </li>
                                                            </ul>
                                                            <hr>
                                                            <strong>Other Indicators:</strong>
                                                            <ul>
                                                                <li>SAR: {{ round($buyCandle['sar'], 3) }}</li>
                                                                <li>DIF: {{ round($buyCandle['dif'], 3) }}</li>
                                                                <li>DEA: {{ round($buyCandle['dea'], 3) }}</li>
                                                                <li>OBV: {{ round($buyCandle['obv'], 3) }}</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <h5 class="text-warning">Ideal Buying Candle Details:</h5>
                                                        <div>
                                                            <strong>RSI:</strong>
                                                            {{ round($lowestCandle['rsi6']) }}
                                                            <br>
                                                            <strong>StochRSI:</strong>
                                                            {{ round($lowestCandle['stoch_rsi']) }}
                                                            <br>
                                                            {{-- <strong>OBV:</strong> Highest in last 15 candles, Limit: 50%<br> --}}
                                                            <strong>Moving Averages:</strong>
                                                            <ul>
                                                                <li>MA7: {{ round($lowestCandle['ma7'], 4) }}
                                                                </li>
                                                                <li>MA14: {{ round($lowestCandle['ma14'], 4) }}
                                                                </li>
                                                                <li>MA25: {{ round($lowestCandle['ma25'], 4) }}
                                                                </li>
                                                                <li>MA99: {{ round($lowestCandle['ma99'], 4) }}
                                                                </li>
                                                            </ul>
                                                            <strong>OBV:</strong>
                                                            <ul>
                                                                <li>OBV: {{ round($lowestCandle['obv'], 4) }}</li>
                                                                <li>Highest OBV:
                                                                    {{ round($lowestCandle['previousObvHigh'], 4) }}
                                                                </li>
                                                                <li>Obv Candlesticks: 15</li>
                                                                @php
                                                                    if ($lowestCandle['previousObvHigh'] == 0) {
                                                                        if ($lowestCandle['obv'] < 0) {
                                                                            // Define the percentage decrease as 100% or some other logic based on application needs
                                                                            $percentageDecrease = 100; // Example: Define a full decrease if previous is zero and current is negative
                                                                        } else {
                                                                            // If the previous is zero and the current is zero or positive, you might decide differently
                                                                            $percentageDecrease =
                                                                                $lowestCandle['obv'] == 0
                                                                                    ? 0
                                                                                    : 'Not Calculable';
                                                                        }
                                                                    } else {
                                                                        $percentageDecrease =
                                                                            (($lowestCandle['previousObvHigh'] -
                                                                                $lowestCandle['obv']) /
                                                                                $lowestCandle['previousObvHigh']) *
                                                                            100;
                                                                        if (
                                                                            $lowestCandle['obv'] >
                                                                            $lowestCandle['previousObvHigh']
                                                                        ) {
                                                                            $percentageDecrease = -abs(
                                                                                $percentageDecrease,
                                                                            ); // Indicates an increase
                                                                        } else {
                                                                            $percentageDecrease = abs(
                                                                                $percentageDecrease,
                                                                            ); // Confirms a decrease
                                                                        }
                                                                    }
                                                                @endphp
                                                                {{-- <li>Percentage Diff:
                                                                    {{ round($percentageDecrease, 2) }}
                                                                </li> --}}


                                                            </ul>

                                                            <hr>
                                                            <strong>KDJ:</strong>
                                                            <ul>
                                                                <li>K: {{ round($lowestCandle['K'], 4) }}
                                                                </li>
                                                                <li>D: {{ round($lowestCandle['D'], 4) }}
                                                                </li>
                                                                <li>J: {{ round($lowestCandle['J'], 4) }}
                                                                </li>
                                                            </ul>
                                                            <hr>
                                                            <strong>Other Indicators:</strong>
                                                            <ul>
                                                                <li>SAR: {{ round($lowestCandle['sar'], 3) }}</li>
                                                                <li>DIF: {{ round($lowestCandle['dif'], 3) }}</li>
                                                                <li>DEA: {{ round($lowestCandle['dea'], 3) }}</li>
                                                                <li>OBV: {{ round($lowestCandle['obv'], 3) }}</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">



                                                        <h5 class="text-warning">Buying Conditions:</h5>
                                                        <div>
                                                            <strong>RSI:</strong><br>
                                                            Buying RSI
                                                            (<strong>{{ round($buyCandle['rsi6'], 4) }}</strong>) < Limit
                                                                RSI (<strong>
                                                                {{ round($buyingAverages['rsi6'], 4) }}</strong>) <br><br>
                                                                <strong>Stoch Condition:</strong><br>
                                                                Buying Stoch
                                                                (<strong>{{ round($buyCandle['stoch_d'], 4) }}</strong>) <
                                                                    Limit Stoch (<strong>
                                                                    {{ round($buyingAverages['stoch_rsi'] * 2, 4) }}</strong>)<br><br>
                                                                    <strong>Obv Limit:</strong><br>
                                                                    Buying OBV Limit
                                                                    (<strong>{{ $buyCandle['previousObvHigh'] != 0 ? abs(round((($buyCandle['previousObvHigh'] - $buyCandle['obv']) / $buyCandle['previousObvHigh']) * 100, 2)) : 100 }}</strong>)
                                                                    > Average OBV Limit
                                                                    (<strong>{{ $buyingAverages['previousObvHigh'] != 0 ? abs(round((($buyingAverages['previousObvHigh'] - $buyingAverages['obv']) / $buyingAverages['previousObvHigh']) * 100, 2)) : 100 }}</strong>)
                                                                    <br>
                                                        </div>


                                                    </div>
                                                    <div class="col-md-3">
                                                        <h5 class="text-danger">Selling Details:</h5>
                                                        <div>
                                                            <strong>RSI:</strong>
                                                            {{ round($sellCandle['rsi6']) }}<br>
                                                            <strong>StochRSI:</strong>
                                                            {{ round($sellCandle['stoch_rsi']) }}<br>
                                                            <strong>Moving Averages:</strong>
                                                            <ul>
                                                                <li>MA7: {{ round($sellCandle['ma7'], 4) }}
                                                                </li>
                                                                <li>MA14: {{ round($sellCandle['ma14'], 4) }}
                                                                </li>
                                                                <li>MA25: {{ round($sellCandle['ma25'], 4) }}
                                                                </li>
                                                                <li>MA99: {{ round($sellCandle['ma99'], 4) }}
                                                                </li>
                                                            </ul>
                                                            <hr>
                                                            <strong>Other Indicators:</strong>
                                                            <ul>
                                                                <li>SAR: {{ round($sellCandle['sar'], 3) }}
                                                                </li>
                                                                <li>DIF: {{ round($sellCandle['dif'], 3) }}
                                                                </li>
                                                                <li>DEA: {{ round($sellCandle['dea'], 3) }}
                                                                </li>
                                                                <li>OBV: {{ round($sellCandle['obv'], 3) }}
                                                                </li>
                                                            </ul>
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
                                                                <th>Lowest Price (USDT)</th>
                                                                <th>Selling Price (USDT)</th>
                                                                <th>Buying Time</th>
                                                                <th>Selling Time</th>
                                                                <th>Profit (%)</th>
                                                                <th>Duration (mins)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($nearbyTrades as $trade)
                                                                <tr>
                                                                    <td>{{ $trade->id }}</td>
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
                const trades = @json($trades);
                const buyTriggers = @json($buyTriggers);
                const liveBuy = @json($liveBuy);
                const liveSell = @json($liveSell);
                const sellTriggers = @json($sellTriggers);
                const lowestTriggers = @json($lowestTriggers);

                // Extract timestamps and close prices
                const timestamps = candlestickData.map(data => data.timestamp);
                const closePrices = candlestickData.map(data => data.close);

                // Determine point colors and styles based on buy/sell triggers
                const pointStyles = timestamps.map((timestamp, index) => {
                    const binanceTimestamp = candlestickData[index].binance_timestamp;




                    if (liveBuy.includes(binanceTimestamp)) {
                        return {
                            backgroundColor: 'white', // Green for buy triggers
                            borderColor: 'green', // Darker green border
                            radius: 6 // Larger point radius
                        };
                    }

                    if (liveSell.includes(binanceTimestamp)) {
                        return {
                            backgroundColor: 'white', // Green for buy triggers
                            borderColor: 'red', // Darker green border
                            radius: 6 // Larger point radius
                        };
                    }









                    // Old Conditions
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



    {{-- Live Trades Table --}}
    @php
        use Carbon\Carbon;
        use Illuminate\Support\Facades\DB;
        $grand_total = 0;
        $profit_total = 0;
        $loss_total = 0;
        $trades_total = 0;
        $profit_order_count = 0;
        $profit_percentage_total = 0;
        $loss_order_count = 0;
        $symbols = [];
        $symbols = DB::table('orders')->pluck('symbol')->unique();

    @endphp
    <div class="table-container">
        <table class="table dataTable">

            <thead class="">
                <tr>
                    <th>Coin</th>
                    <th>Amount</th>
                    <th>Leverage</th>
                    <th>Position</th>
                    <th>Type</th>
                    <th>Entry Price</th>
                    <th>Close Price</th>
                    <th>Current Price</th>
                    <th>Turnover Point</th>
                    {{-- <th>Current Support</th>
                        <th>Current Resistance</th>
                        <th>Stop Loss</th> --}}
                    <th>Current Profit</th>
                    <th>Realized Pnl</th>
                    <th>Status</th>
                    <th>Take Profit</th>
                    <th>Time</th>

                    <th>Formula</th>

                </tr>
            </thead>
            <tbody>
                @foreach ($liveTradesData as $order)
                    @php
                        $sqlTimestamp = $order->created_at;
                        $date = Carbon::createFromFormat('Y-m-d H:i:s', $sqlTimestamp, 'Asia/Karachi');
                        $date->setTimezone('GMT');
                        $unixTimestamp = $date->timestamp * 1000;
                        $unixTimestamp = floor($unixTimestamp / 60000) * 60000;
                        $unixTimestamp = intval($unixTimestamp);
                        $orderClose = DB::table('live_trades_future_results')
                            ->where('orderId', $order->pairId)
                            ->first();

                    @endphp
                    <tr>
                        <td>{{ $order->symbol ?? '-' }}</td>
                        <td>{{ $order->amount ?? '-' }}</td>
                        <td>{{ $order->leverage ?? '-' }}</td>
                        <td>{{ $order->position ?? '-' }}</td>
                        <td>{{ $order->type ?? '-' }}</td>
                        <td>{{ $order->price ?? '-' }}</td>
                        <td>{{ $orderClose->price ?? '-' }}</td>
                        <td>{{ $order->previousPrice ?? '-' }}</td>
                        <td>{{ $order->turnoverPoint ?? '-' }}</td>
                        {{-- <td>{{ $order->currentSupport ?? '-' }}</td>
                            <td>{{ $order->currentResistance ?? '-' }}</td>
                            <td>{{ $order->stopLoss ?? '-' }}</td> --}}
                        <td
                            style="color:{{ isset($order->currentProfit) ? ($order->currentProfit > 0 ? 'green' : ($order->currentProfit < 0 ? 'red' : '')) : '' }} !important">
                            {{ isset($order->currentProfit) ? round($order->currentProfit, 2) . '%' : '0' }}
                        </td>
                        <td
                            style="color:{{ isset($order->realizedPnl) ? ($order->realizedPnl > 0 ? 'green' : ($order->realizedPnl < 0 ? 'red' : '')) : '' }} !important">
                            $ {{ isset($order->realizedPnl) ? round($order->realizedPnl, 4) . '' : '0' }}
                        </td>
                        <td>
                            <span
                                class="badge {{ $order->trade_status == 'open' ? 'bg-info' : 'bg-secondary text-dark' }}">
                                {{ ucfirst($order->trade_status ?? '-') }}
                            </span>
                        </td>
                        <td>{{ $order->targetProfit ? $order->targetProfit . '%' : '-' }}</td>
                        <td>{{ $date->setTimezone('Asia/Karachi')->format('H:i:s') }}<br>{{ $date->format('M d, Y') }}
                        </td>
                        </td>



                        <td colspan="13" class="text-center  py-2">
                            <span class="fw-bold"> </span>{{ $order->formula ?? '-' }}
                        </td>
                    </tr>
                @endforeach
                {{-- <tr>
                    <td colspan="10" style="text-align: right;">
                        <strong>Total Trades:</strong> {{ count($orders) }}<br>
                        <strong>Total Profit:</strong> ${{ number_format($profit_total, 4) }}
                        ({{ round($profit_percentage_total, 2) }} %)
                    </td>
                </tr> --}}
            </tbody>
        </table>
    </div>
@endsection
