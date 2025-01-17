@extends('layouts.app')
@php
    use Carbon\Carbon;
    use Illuminate\Support\Facades\DB;
    $grand_total = 0;
    $profit_total = 0;
    $loss_total = 0;
    $trades_total = 0;
    $profit_order_count = 0;
    $loss_order_count = 0;
    $symbols = [];
    $symbols = DB::table('orders')->pluck('symbol')->unique();

@endphp
@section('content')
    <div class="container">
        <h1 class="text-center mb-4">Trade Statistics</h1>
        {{-- <form method="GET" action="" class="mb-5">
            <div class="form-row">
                <div class="col-md-3">
                    <label for="start_date">Start Date:</label>
                    <input type="datetime-local" class="form-control" name="start_date" id="start_date"
                        value="{{ request('start_date') }}">
                </div>
                <div class="col-md-3">
                    <label for="end_date">End Date:</label>
                    <input type="datetime-local" class="form-control" name="end_date" id="end_date"
                        value="{{ request('end_date') }}">
                </div>
                <div class="col-md-3">
                    <label for="symbol">Symbol:</label>
                    <select class="form-control" name="symbol" id="symbol">
                        <option value="">Select a symbol</option>
                        @foreach ($symbols as $symbol)
                            <option value="{{ $symbol }}" {{ request('symbol') === $symbol ? 'selected' : '' }}>
                                {{ $symbol }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary mt-3">Filter</button>
                </div>
            </div>
        </form> --}}

        <table class="table table-bordered">
            <thead class="thead-light">
                <tr>
                    <th>Coin</th>
                    <th>Interval</th>
                    <th>Amount</th>
                    <th>Buying Price</th>
                    <th>Selling Price</th>
                    <th>Profit (USDT and %)</th>
                    <th>Fee (total Fee in USDT and %)</th>
                    <th>Duration</th>
                    <th>Buying Time</th>
                    <th>Selling Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    @php
                        $order_sell = DB::table('orders')
                            ->where('orderId', $order->pair_id)
                            ->first();
                        $order_sell = DB::table('orders')
                            ->where('orderId', $order->pair_id)
                            ->first();
                        $total_fee_usdt = '-';
                        $fee_percentage = '-';
                        $profit = '-';
                        $profit_percentage = '-';
                        $duration = '-';
                        $selling_time = '-';
                        $selling_price = '-';
                        $color = '';
                        if ($order_sell) {
                            $total_fee_usdt =
                                $order->commission * $order->commissionUSDT +
                                $order_sell->commission * $order_sell->commissionUSDT;
                            $fee_percentage = ($total_fee_usdt / ($order->price * $order->qty)) * 100;
                            $duration = round(
                                Carbon::parse($order->created_at)->diffInSeconds($order_sell->created_at) / 60,
                                2,
                            );

                            $profit =
                                $order_sell->price * $order_sell->qty - $order->price * $order->qty - $total_fee_usdt;
                            $profit_percentage = ($profit / ($order->price * $order->qty)) * 100;
                            if ($order_sell->qty != $order->qty) {
                                $profit =
                                    $order_sell->price * $order_sell->qty -
                                    $order->price * $order_sell->qty -
                                    $total_fee_usdt;
                                $profit_percentage = ($profit / ($order->price * $order->qty)) * 100;
                            }
                            $color = $profit >= 0 ? 'green' : 'red';
                        }
                    @endphp

                    <tr>
                        <td>{{ $order->symbol }}</td>
                        <td>{{ $order->interval }}</td>
                        <td>{{ $order->amount }}

                        </td>
                        <td>{{ number_format($order->price, 4) }}</td>
                        <td>{{ $order_sell ? number_format($order_sell->price, 4) : '-' }}</td>

                        <td
                            @if ($color) style="color:{{ $color }} !important;text-align:center;" @endif>
                            {{ $profit != '-' ? '$ ' . number_format($profit, 4) . ' (' . round($profit_percentage, 2) . '%)' : '-' }}
                            @if ($order_sell && $order_sell->qty != $order->qty)
                                <br>
                                <span class="badge badge-warning">Lot Size</span>
                            @endif
                        </td>
                        <td>{{ $total_fee_usdt != '-' ? '$ ' . number_format($total_fee_usdt, 4) . ' (' . round($fee_percentage, 2) . '%)' : '-' }}
                        </td>
                        <td>{{ $duration }}</td>
                        <td>{{ $order->created_at }}</td>
                        <td>{{ $order_sell ? $order_sell->created_at : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
