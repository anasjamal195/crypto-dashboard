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
                                    <p class="card-category text-white-50">Real-time trading overview (5m-candlesticks) from
                                        {{ $tradeDetails['startTime'] }} onwards</p>
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
                                            <th>Our Intention</th>
                                            <th>Candles to Check</th>
                                            <th class="text-center">Opening Probability</th>
                                            <th>Last Updated (UTC+5)</th>
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
                                                    @php
                                                        $probability = ($trade['checkpoints'] / 5) * 100;
                                                    @endphp
                                                    {{ $probability }}%
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
                                                <td colspan="8" class="text-center text-muted py-4">
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
                                            <th>Entry Price</th>
                                            <th>Exit Price</th>
                                            <th>Unrealized PnL</th>
                                            <th>Duration</th>
                                            <th>Opened (UTC+5)</th>
                                            <th>Closed (UTC+5)</th>
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
                                                <td class="font-weight-bold">${{ number_format($trade['price'], 6) }}</td>
                                                <td class="font-weight-bold">
                                                    ${{ number_format($trade['currentPrice'], 6) }}</td>
                                                <td
                                                    class="{{ $trade['realizedPnl'] >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                                    {{ number_format($trade['currentProfit'], 2) }}%
                                                </td>
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
                                                <td colspan="8" class="text-center text-muted py-4">
                                                    <i class="tim-icons icon-zoom-split"></i>
                                                    No closed trades available
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <!-- Trade Statistics -->
                            @if (count($tradeDetails['closedTrades']) > 0)
                                @php
                                    $longTrades = collect($tradeDetails['closedTrades'])->where('position', 'LONG');
                                    $shortTrades = collect($tradeDetails['closedTrades'])->where('position', 'SHORT');

                                    // LONG Statistics
                                    $longCount = $longTrades->count();
                                    $longProfitable = $longTrades->where('currentProfit', '>', 0)->count();
                                    $longLosses = $longTrades->where('currentProfit', '<', 0)->count();
                                    $longTotalProfit = $longTrades->sum('currentProfit');
                                    $longAvgProfit = $longCount > 0 ? $longTotalProfit / $longCount : 0;

                                    // SHORT Statistics
                                    $shortCount = $shortTrades->count();
                                    $shortProfitable = $shortTrades->where('currentProfit', '>', 0)->count();
                                    $shortLosses = $shortTrades->where('currentProfit', '<', 0)->count();
                                    $shortTotalProfit = $shortTrades->sum('currentProfit');
                                    $shortAvgProfit = $shortCount > 0 ? $shortTotalProfit / $shortCount : 0;

                                    // Overall Statistics
                                    $totalTrades = count($tradeDetails['closedTrades']);
                                    $totalProfitable = collect($tradeDetails['closedTrades'])
                                        ->where('currentProfit', '>', 0)
                                        ->count();
                                    $totalLosses = collect($tradeDetails['closedTrades'])
                                        ->where('currentProfit', '<', 0)
                                        ->count();
                                    $totalProfit = collect($tradeDetails['closedTrades'])->sum('currentProfit');
                                    $avgProfit = $totalTrades > 0 ? $totalProfit / $totalTrades : 0;
                                    $winRate = $totalTrades > 0 ? ($totalProfitable / $totalTrades) * 100 : 0;
                                @endphp

                                <div class="mt-4 p-3 border-top" style="border-color: #2b3553 !important;">
                                    <h5 class="text-white mb-3">
                                        <i class="tim-icons icon-chart-bar-32 text-info"></i>
                                        Trade Statistics Summary
                                    </h5>

                                    <div class="row">
                                        <!-- Overall Statistics -->
                                        <div class="col-lg-4 col-md-12 mb-3">
                                            <div class="stats-card p-3 rounded"
                                                style="background-color: rgba(255,255,255,0.05); border: 1px solid #2b3553;">
                                                <h6 class="text-white-50 mb-2">Overall Performance</h6>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Total Trades:</span>
                                                    <span class="badge badge-info">{{ $totalTrades }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Win Rate:</span>
                                                    <span
                                                        class="badge {{ $winRate >= 50 ? 'badge-success' : 'badge-warning' }}">
                                                        {{ number_format($winRate, 1) }}%
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Profitable:</span>
                                                    <span class="text-success">{{ $totalProfitable }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Losses:</span>
                                                    <span class="text-danger">{{ $totalLosses }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Total P&L:</span>
                                                    <span
                                                        class="font-weight-bold {{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($totalProfit, 2) }}%
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-white">Avg P&L:</span>
                                                    <span
                                                        class="font-weight-bold {{ $avgProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($avgProfit, 2) }}%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- LONG Statistics -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="stats-card p-3 rounded"
                                                style="background-color: rgba(40, 167, 69, 0.1); border: 1px solid rgba(40, 167, 69, 0.3);">
                                                <h6 class="text-success mb-2">
                                                    <i class="tim-icons icon-trend-up"></i>
                                                    LONG Positions
                                                </h6>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Total Count:</span>
                                                    <span class="badge badge-success">{{ $longCount }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Win Rate:</span>
                                                    <span
                                                        class="badge {{ $longCount > 0 && $longProfitable / $longCount >= 0.5 ? 'badge-success' : 'badge-warning' }}">
                                                        {{ $longCount > 0 ? number_format(($longProfitable / $longCount) * 100, 1) : 0 }}%
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Profitable:</span>
                                                    <span class="text-success">{{ $longProfitable }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Losses:</span>
                                                    <span class="text-danger">{{ $longLosses }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Total P&L:</span>
                                                    <span
                                                        class="font-weight-bold {{ $longTotalProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($longTotalProfit, 2) }}%
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-white">Avg P&L:</span>
                                                    <span
                                                        class="font-weight-bold {{ $longAvgProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($longAvgProfit, 2) }}%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SHORT Statistics -->
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="stats-card p-3 rounded"
                                                style="background-color: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.3);">
                                                <h6 class="text-danger mb-2">
                                                    <i class="tim-icons icon-trend-down"></i>
                                                    SHORT Positions
                                                </h6>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Total Count:</span>
                                                    <span class="badge badge-danger">{{ $shortCount }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Win Rate:</span>
                                                    <span
                                                        class="badge {{ $shortCount > 0 && $shortProfitable / $shortCount >= 0.5 ? 'badge-success' : 'badge-warning' }}">
                                                        {{ $shortCount > 0 ? number_format(($shortProfitable / $shortCount) * 100, 1) : 0 }}%
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Profitable:</span>
                                                    <span class="text-success">{{ $shortProfitable }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Losses:</span>
                                                    <span class="text-danger">{{ $shortLosses }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-white">Total P&L:</span>
                                                    <span
                                                        class="font-weight-bold {{ $shortTotalProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($shortTotalProfit, 2) }}%
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-white">Avg P&L:</span>
                                                    <span
                                                        class="font-weight-bold {{ $shortAvgProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($shortAvgProfit, 2) }}%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
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

        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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

            .stats-card {
                margin-bottom: 1rem;
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

            .stats-card {
                font-size: 0.85rem;
            }
        }
    </style>
@endsection
