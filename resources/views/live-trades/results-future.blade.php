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
                        <th>Current Support</th>
                        <th>Current Resistance</th>
                        <th>Stop Loss</th>
                        <th>Current Profit</th>
                        <th>Take Profit</th>
                        <th>Status</th>
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
                            <td>{{ $order->currentSupport ?? '-' }}</td>
                            <td>{{ $order->currentResistance ?? '-' }}</td>
                            <td>{{ $order->stopLoss ?? '-' }}</td>
                            <td
                                style="color:{{ isset($order->currentProfit) ? ($order->currentProfit > 0 ? 'green' : ($order->currentProfit < 0 ? 'red' : '')) : '' }} !important">
                                {{ isset($order->currentProfit) ? $order->currentProfit . '%' : '0' }}
                            </td>
                            <td>{{ $order->targetProfit ? $order->targetProfit . '%' : '-' }}</td>
                            <td>
                                <span
                                    class="badge {{ $order->trade_status == 'open' ? 'bg-info' : 'bg-secondary text-dark' }}">
                                    {{ ucfirst($order->trade_status ?? '-') }}
                                </span>
                            </td>
                            <td>{{ isset($date) ? $date->format('H:i:s , M d, Y') : '-' }}</td>
                            <td>
                                @if (!$orderClose)
                                    <a href="{{ route('live.trades.future.close', $order->orderId ?? 0) }}"
                                        class="btn btn-primary btn-sm" role="button">
                                        <i class="fas fa-times"></i>
                                    </a>
                                @endif
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
    </div>
@endsection
