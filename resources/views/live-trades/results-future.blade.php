@extends('layouts.app')
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
@section('content')
    <div class="container">
        <h1 class="text-center mb-4">Trade Statistics Future</h1>
        @if (request()->get('start_date') || request()->get('end_date'))
            <div class="alert alert-info">
                @if (request()->get('start_date') && request()->get('end_date'))
                    Showing results from {{ \Carbon\Carbon::parse(request()->get('start_date'))->format('M d, Y') }} to
                    {{ \Carbon\Carbon::parse(request()->get('end_date'))->format('M d, Y') }}
                @elseif(request()->get('start_date'))
                    Showing results from {{ \Carbon\Carbon::parse(request()->get('start_date'))->format('M d, Y') }} onward
                @elseif(request()->get('end_date'))
                    Showing results up to {{ \Carbon\Carbon::parse(request()->get('end_date'))->format('M d, Y') }}
                @endif
            </div>
        @endif
        <div class="row ">
            <!-- RSI Average Card -->
            <div class="card text-white bg-primary mb-3 col-md-2 mx-3">
                <div class="card-header">Total Orders</div>
                <div class="card-body">
                    <h5 class="card-title">{{ $tradeStatistics['total_orders'] }}</h5>
                </div>
            </div>
            <!-- Stoch Average Card -->
            <div class="card text-white bg-success mb-3 col-md-2 mx-3">
                <div class="card-header">Total Profit</div>
                <div class="card-body">
                    <h5 class="card-title">{{ round($tradeStatistics['total_profit'], 2) }} %</h5>

                </div>
            </div>
            <!-- Highest OBV Card -->
            <div class="card text-white bg-danger mb-3 col-md-2 mx-3">
                <div class="card-header">Total Loss</div>
                <div class="card-body">
                    <h5 class="card-title">{{ round($tradeStatistics['total_loss'], 2) }} %</h5>

                </div>
            </div>
            <!-- OBV Card -->
            <div class="card text-white bg-warning mb-3 col-md-2 mx-3">
                <div class="card-header">Net Total</div>
                <div class="card-body">
                    <h5 class="card-title">{{ round($tradeStatistics['net_total'], 2) }} %</h5>

                </div>
            </div>
            <!-- OBV Limit Card -->
            <div class="card text-white bg-success mb-3 col-md-2 mx-3">
                <div class="card-header">Total Short</div>
                <div class="card-body">
                    <h5 class="card-title">{{ $tradeStatistics['total_short'] }} </h5>

                </div>
            </div>
            <!-- K Card -->
            <div class="card text-white bg-danger mb-3 col-md-2 mx-3">
                <div class="card-header">Total Long</div>
                <div class="card-body">
                    <h5 class="card-title">{{ $tradeStatistics['total_long'] ?? 0 }}</h5>

                </div>
            </div>

        </div>
        <form method="GET" action="{{ route('live.trades.result', 'FUTURE') }}">
            <input type="hidden" name="interval" value="{{ request()->get('interval') }}">
            <div class="row mb-4">
                <div class="col-md-6">
                    <select name="formula" id="formula" class="form-select select2 w-100">
                        <option value="">Select Formula</option>
                        @foreach ($formulas as $item)
                            @if (!is_null($item->formula))
                                <option value="{{ $item->formula }}"
                                    {{ request()->get('formula') == $item->formula ? 'selected' : '' }}>
                                    {{ $item->formula }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <select name="symbol" id="symbol" class="form-select select2 w-100">
                        <option value="">Select Symbol</option>
                        @foreach ($symbols as $symbol)
                            <option value="{{ $symbol }}"
                                {{ request()->get('symbol') == $symbol ? 'selected' : '' }}>
                                {{ $symbol }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control"
                        value="{{ request()->get('start_date') }}">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control"
                        value="{{ request()->get('end_date') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="{{ route('live.trades.result', 'FUTURE') }}?interval={{ request()->get('interval') }}"
                        class="btn btn-secondary">Clear Filter</a>
                </div>
            </div>

            <!-- Hard coded time range buttons -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <a href="{{ route('live.trades.result', 'FUTURE') }}?interval=1m&start_date={{ \Carbon\Carbon::now('Asia/Karachi')->subHour()->format('Y-m-d H:i:s') }}&end_date={{ \Carbon\Carbon::now('Asia/Karachi')->format('Y-m-d H:i:s') }}"
                        class="btn btn-primary btn-block">
                        Last 1 Hr
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('live.trades.result', 'FUTURE') }}?interval=1m&start_date={{ \Carbon\Carbon::now('Asia/Karachi')->subDay()->format('Y-m-d H:i:s') }}&end_date={{ \Carbon\Carbon::now('Asia/Karachi')->format('Y-m-d H:i:s') }}"
                        class="btn btn-success btn-block">
                        Last 24 Hr
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('live.trades.result', 'FUTURE') }}?interval=1m&start_date={{ \Carbon\Carbon::now('Asia/Karachi')->subWeek()->format('Y-m-d H:i:s') }}&end_date={{ \Carbon\Carbon::now('Asia/Karachi')->format('Y-m-d H:i:s') }}"
                        class="btn btn-warning btn-block">
                        Last Week
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('live.trades.result', 'FUTURE') }}?interval=1m&start_date={{ \Carbon\Carbon::now('Asia/Karachi')->subMonth()->format('Y-m-d H:i:s') }}&end_date={{ \Carbon\Carbon::now('Asia/Karachi')->format('Y-m-d H:i:s') }}"
                        class="btn btn-danger btn-block">
                        Last Month
                    </a>
                </div>
            </div>

    </div>
    </form>

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
                    {{-- <th>Current Support</th>
                        <th>Current Resistance</th>
                        <th>Stop Loss</th> --}}
                    <th>Current Profit</th>
                    <th>Status</th>
                    <th>Take Profit</th>
                    <th>Time</th>
                    <th>Action</th>

                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
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
                        {{-- <td>{{ $order->currentSupport ?? '-' }}</td>
                            <td>{{ $order->currentResistance ?? '-' }}</td>
                            <td>{{ $order->stopLoss ?? '-' }}</td> --}}
                        <td
                            style="color:{{ isset($order->currentProfit) ? ($order->currentProfit > 0 ? 'green' : ($order->currentProfit < 0 ? 'red' : '')) : '' }} !important">
                            {{ isset($order->currentProfit) ? round($order->currentProfit, 2) . '%' : '0' }}
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
                        <td>
                            @if (!$orderClose)
                                <a href="{{ route('live.trades.future.close', $order->orderId ?? 0) }}"
                                    class="btn btn-primary btn-sm" role="button">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                        </td>

                    </tr>
                    <tr>
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
