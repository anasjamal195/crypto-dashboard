@extends('layouts.app')

@section('content')
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-chart">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-sm-6 text-left">
                                    <h2 class="card-title text-white">eGeniusCare Live Crypto Trading</h2>
                                    <p class="card-category text-white-50">Real-time trading overview</p>
                                </div>
                                <div class="col-sm-6">
                                    <div class="btn-group btn-group-toggle float-right" data-toggle="buttons">
                                        <label class="btn btn-sm btn-primary btn-simple active" id="0">
                                            <input type="radio" name="options" checked>
                                            <span class="d-none d-sm-block d-md-block d-lg-block d-xl-block">Live</span>
                                            <span class="d-block d-sm-none">
                                                <i class="tim-icons icon-single-02"></i>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="card card-stats">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <div class="info-icon text-center icon-warning">
                                        <i class="tim-icons icon-time-alarm text-warning"></i>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <div class="numbers">
                                        <p class="card-category">Pending</p>
                                        <h3 class="card-title">{{ count($tradeDetails['pendingOpening']) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-stats">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <div class="info-icon text-center icon-success">
                                        <i class="tim-icons icon-chart-pie-36 text-success"></i>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <div class="numbers">
                                        <p class="card-category">Open Trades</p>
                                        <h3 class="card-title">{{ count($tradeDetails['openedTrades']) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-stats">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <div class="info-icon text-center icon-info">
                                        <i class="tim-icons icon-check-2 text-info"></i>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <div class="numbers">
                                        <p class="card-category">Trade History</p>
                                        <h3 class="card-title">{{ count($tradeDetails['closedTrades']) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Opening Trades -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title text-white">
                                <i class="tim-icons icon-time-alarm text-warning"></i>
                                Pending Opening Trades
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive fixed-height">
                                <table class="table table-dark table-hover">
                                    <thead class="text-primary sticky-header">
                                        <tr>
                                            <th>Exchange</th>
                                            <th>Coin</th>
                                            <th>Long/Short</th>
                                            <th>Intention</th>
                                            <th>Candles to Check</th>
                                            <th class="text-center">Probability</th>
                                            <th>Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($tradeDetails['pendingOpening'] as $trade)
                                            <tr>
                                                <td>
                                                    <span
                                                        class="badge badge-info">{{ strtoupper($trade['exchange']) }}</span>
                                                </td>
                                                <td class="font-weight-bold">{{ $trade['coin_name'] }}</td>
                                                <td>
                                                    <span class="badge badge-secondary">{{ $trade['type'] }}</span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge {{ $trade['intention'] == 'LONG' ? 'badge-success' : 'badge-danger' }}">
                                                        {{ $trade['intention'] }}
                                                    </span>
                                                </td>
                                                <td class="text-center">{{ $trade['candles_to_check'] }}</td>
                                                <td class="text-center">
                                                    {{-- <div class="progress" style="height: 8px; width: 80px; margin: 0 auto;"> --}}
                                                    @php
                                                        $probability = ($trade['checkpoints'] / 5) * 100;
                                                    @endphp
                                                    {{-- <div class="progress-bar bg-success" role="progressbar"
                                                            style="width: {{ $probability }}%;"
                                                            aria-valuenow="{{ $probability }}" aria-valuemin="0"
                                                            aria-valuemax="100">
                                                        </div> --}}


                                                    {{-- </div> --}}
                                                    {{ $probability }}%

                                                    {{-- <small class="text-muted">{{ $trade['checkpoints'] }}/5
                                                        ({{ number_format($probability, 0) }}%)
                                                    </small> --}}
                                                </td>
                                                <td class="small">
                                                    {{ \Carbon\Carbon::createFromTimestamp($trade['checkpoint_timestamp'] / 1000)->setTimezone('Asia/Karachi')->format('M d, H:i') }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <i class="tim-icons icon-zoom-split"></i>
                                                    No pending trades available
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Opened Trades -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title text-white">
                                <i class="tim-icons icon-chart-pie-36 text-success"></i>
                                Active Trades
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive fixed-height">
                                <table class="table table-dark table-hover">
                                    <thead class="text-primary sticky-header">
                                        <tr>
                                            <th>Symbol</th>
                                            <th>Position</th>
                                            {{-- <th>Amount</th> --}}
                                            <th>Entry Price</th>
                                            <th>Current Price</th>
                                            <th>Current Profit</th>
                                            <th>Initial TP</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($tradeDetails['openedTrades'] as $trade)
                                            <tr>
                                                <td class="font-weight-bold">{{ $trade['symbol'] }}</td>
                                                <td>
                                                    <span
                                                        class="badge {{ $trade['position'] == 'LONG' ? 'badge-success' : 'badge-danger' }}">
                                                        {{ $trade['position'] }}
                                                    </span>
                                                </td>
                                                {{-- <td>${{ number_format($trade['amount'], 2) }}</td> --}}
                                                <td class="font-weight-bold">${{ number_format($trade['price'], 6) }}</td>
                                                <td class="font-weight-bold">
                                                    ${{ number_format($trade['currentPrice'], 6) }}</td>
                                                <td
                                                    class="{{ $trade['currentProfit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($trade['currentProfit'], 2) }}%
                                                </td>
                                                <td class="text-info">{{ number_format($trade['targetProfit'], 2) }}%</td>
                                                <td class="small">
                                                    @php
                                                        $duration = \Carbon\Carbon::parse(
                                                            $trade['created_at'],
                                                        )->diffInMinutes(now());
                                                    @endphp
                                                    {{ round($duration) }}m
                                                </td>
                                                <td>
                                                    <span class="badge badge-primary">
                                                        {{ strtoupper($trade['trade_status']) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">
                                                    <i class="tim-icons icon-zoom-split"></i>
                                                    No active trades available
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Closed Trades -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title text-white">
                                <i class="tim-icons icon-check-2 text-info"></i>
                                Trade History
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive fixed-height">
                                <table class="table table-dark table-hover">
                                    <thead class="text-primary sticky-header">
                                        <tr>
                                            <th>Symbol</th>
                                            <th>Position</th>
                                            {{-- <th>Amount</th> --}}
                                            <th>Entry Price</th>
                                            <th>Exit Price</th>
                                            <th>Unrealized PnL</th>
                                            {{-- <th>Realized PnL</th> --}}
                                            <th>Duration</th>
                                            <th>Opened</th>
                                            <th>Closed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($tradeDetails['closedTrades'] as $trade)
                                            <tr>
                                                <td class="font-weight-bold">{{ $trade['symbol'] }}</td>
                                                <td>
                                                    <span
                                                        class="badge {{ $trade['position'] == 'LONG' ? 'badge-success' : 'badge-danger' }}">
                                                        {{ $trade['position'] }}
                                                    </span>
                                                </td>
                                                {{-- <td>${{ number_format($trade['amount'], 2) }}</td> --}}
                                                <td class="font-weight-bold">${{ number_format($trade['price'], 6) }}</td>
                                                <td class="font-weight-bold">
                                                    ${{ number_format($trade['currentPrice'], 6) }}</td>
                                                <td
                                                    class="{{ $trade['realizedPnl'] >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                                    {{ number_format($trade['currentProfit'], 2) }}%
                                                </td>
                                                {{-- <td
                                                    class="{{ $trade['realizedPnl'] >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                                    ${{ number_format($trade['realizedPnl'], 4) }}
                                                </td> --}}
                                                <td class="small">
                                                    @php
                                                        $duration = \Carbon\Carbon::parse(
                                                            $trade['created_at'],
                                                        )->diffInMinutes(\Carbon\Carbon::parse($trade['updated_at']));
                                                    @endphp
                                                    {{ round($duration) }}m
                                                </td>
                                                <td class="small">
                                                    {{ \Carbon\Carbon::parse($trade['created_at'])->format('M d, H:i') }}
                                                </td>
                                                <td class="small">
                                                    {{ \Carbon\Carbon::parse($trade['updated_at'])->format('M d, H:i') }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">
                                                    <i class="tim-icons icon-zoom-split"></i>
                                                    No closed trades available
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .card {
            background-color: #27293d !important;
            border: 1px solid #2b3553;
        }

        .fixed-height {
            max-height: 500px;
            overflow-y: auto;
        }

        .sticky-header {
            position: sticky;
            top: 0px;
            backdrop-filter: blur(15px);
            z-index: 10;
            /* Optional color tint */

            color: #fff;
        }

        .table-dark {
            background-color: #1e1e2f;
        }

        .table-dark th {
            border-color: #2b3553;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .table-dark td {
            border-color: #2b3553;
            font-size: 0.875rem;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.075);
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }

        .card-stats .info-icon {
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
        }

        .card-stats .numbers {
            text-align: right;
        }

        .card-stats .numbers p {
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            color: #9A9A9A;
        }

        .card-stats .numbers h3 {
            margin-bottom: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #FFFFFF;
        }

        .progress {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border-radius: 4px;
        }

        .progress-bar {
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.8rem;
            }

            .badge {
                font-size: 0.65rem;
                padding: 0.25rem 0.5rem;
            }

            .card-stats .numbers {
                text-align: left;
                margin-top: 0.5rem;
            }

            .btn-group-toggle {
                margin-top: 1rem;
            }
        }

        @media (max-width: 576px) {

            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.75rem;
            }

            .card-header h4 {
                font-size: 1.1rem;
            }
        }
    </style>
@endsection
