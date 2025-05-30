@extends('layouts.app')

@section('content')

    <div class="row">
        <div class="col-12">
            <div class="card card-chart">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            <form action="" method="GET" class="d-flex justify-content-start">

                                <div class="form-group mr-3">
                                    <input type="text" class="form-control" id="symbol" name="symbol"
                                        value="{{ request('symbol', 'BTCUSDT') }}">
                                </div>
                                <div class="form-group mr-3">
                                    <select name="interval" id="interval" class="form-control my-4 select2">
                                        @foreach (\App\CommonHelpers::$binanceIntervals as $key => $value)
                                            <option value="{{ $key }}" {{ $interval === $key ? 'selected' : '' }}>
                                                {{ $key }}
                                            </option>
                                        @endforeach
                                    </select>

                                </div>

                                <input type="hidden" class="form-control" id="limit" max="1000" name="limit"
                                    value="1000">

                                <div class="form-group">
                                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <x-candlestick-chart :data="$coinData" symbol="{{ $symbol }}" interval="{{ $interval }}" :indicators="[
        'ma7',
        // 'ma14',
        'ma25',
        'ma99',
        // 'bb',
        // 'volume',
        'rsi6',
        // 'stoch_rsi',
        // 'macd_hist',
        // 'mfi',
        // 'adx',
        // 'sar',
    ]"
        :markers="$markers" />
    <div class="content my-4">
        <div class="row">
            <!-- Header Card with Current Price and Overall Analysis -->
            <div class="col-12 mb-4">
                <div class="card card-chart">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6 text-left">
                                <h2 class="card-title text-white">Support & Resistance Analysis</h2>
                                <p class="card-category text-white-50">
                                    <i class="tim-icons icon-chart-bar-32"></i>
                                    {{ $srAnalysis['timestamp'] }}
                                </p>
                            </div>
                            <div class="col-sm-6 text-right">
                                <h1 class="text-success font-weight-bold">
                                    ${{ number_format($srAnalysis['current_price'], 2) }}
                                </h1>
                                <p class="text-white-50">Current Price</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Market Structure Overview -->
            <div class="col-lg-4 col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-5">
                                <div
                                    class="info-icon text-center icon-{{ $srAnalysis['confidence_analysis']['overall_bias'] == 'bearish' ? 'danger' : 'success' }}">
                                    <i
                                        class="tim-icons {{ $srAnalysis['confidence_analysis']['overall_bias'] == 'bearish' ? 'icon-trend-down' : 'icon-trend-up' }}"></i>
                                </div>
                            </div>
                            <div class="col-7">
                                <div class="numbers">
                                    <p class="card-category">Market Bias</p>
                                    <h3
                                        class="card-title text-{{ $srAnalysis['confidence_analysis']['overall_bias'] == 'bearish' ? 'danger' : 'success' }}">
                                        {{ ucfirst($srAnalysis['confidence_analysis']['overall_bias']) }}
                                    </h3>
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
                                <div class="info-icon text-center icon-warning">
                                    <i class="tim-icons icon-sound-wave"></i>
                                </div>
                            </div>
                            <div class="col-7">
                                <div class="numbers">
                                    <p class="card-category">Signal Strength</p>
                                    <h3 class="card-title">{{ $srAnalysis['confidence_analysis']['signal_strength'] }}%
                                    </h3>
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
                                <div
                                    class="info-icon text-center icon-{{ $srAnalysis['confidence_analysis']['risk_assessment']['level'] == 'high' ? 'danger' : ($srAnalysis['confidence_analysis']['risk_assessment']['level'] == 'medium' ? 'warning' : 'success') }}">
                                    <i class="tim-icons icon-alert-circle-exc"></i>
                                </div>
                            </div>
                            <div class="col-7">
                                <div class="numbers">
                                    <p class="card-category">Risk Level</p>
                                    <h3
                                        class="card-title text-{{ $srAnalysis['confidence_analysis']['risk_assessment']['level'] == 'high' ? 'danger' : ($srAnalysis['confidence_analysis']['risk_assessment']['level'] == 'medium' ? 'warning' : 'success') }}">
                                        {{ ucfirst($srAnalysis['confidence_analysis']['risk_assessment']['level']) }}
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confidence Analysis -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title text-white">Confidence Analysis</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="progress-container">
                                    <span class="progress-badge text-success">Long Confidence</span>
                                    <div class="progress" style="height:15px">
                                        <div class="progress-bar bg-success"
                                            style="width: {{ $srAnalysis['confidence_analysis']['long_confidence'] }}%">
                                            <span
                                                class="progress-value">{{ $srAnalysis['confidence_analysis']['long_confidence'] }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progress-container">
                                    <span class="progress-badge text-danger">Short Confidence</span>
                                    <div class="progress"  style="height:15px">
                                        <div class="progress-bar bg-danger"
                                            style="width: {{ $srAnalysis['confidence_analysis']['short_confidence'] }}%">
                                            <span
                                                class="progress-value">{{ $srAnalysis['confidence_analysis']['short_confidence'] }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support & Resistance Levels -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title text-white">
                            <i class="tim-icons icon-chart-pie-36"></i>
                            Support & Resistance Levels
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Price</th>
                                        <th>Touches</th>
                                        <th>Strength</th>
                                        <th>Classification</th>
                                        <th>Confidence</th>
                                        <th>Volume</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($srAnalysis['support_resistance_levels'] as $level)
                                        <tr class="{{ $level['type'] == 'support' ? 'table-success' : 'table-danger' }}">
                                            <td>
                                                <span
                                                    class="badge badge-{{ $level['type'] == 'support' ? 'success' : 'danger' }}">
                                                    <i
                                                        class="tim-icons icon-{{ $level['type'] == 'support' ? 'triangle-right-17' : 'minimal-down' }}"></i>
                                                    {{ ucfirst($level['type']) }}
                                                </span>
                                            </td>
                                            <td class="font-weight-bold">${{ number_format($level['price'], 2) }}</td>
                                            <td>
                                                <span class="badge badge-info">{{ $level['touch_count'] }}</span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar bg-{{ $level['classification'] == 'major' ? 'success' : ($level['classification'] == 'minor' ? 'warning' : 'secondary') }}"
                                                        style="width: {{ min($level['strength'], 100) }}%"></div>
                                                </div>
                                                <small>{{ number_format($level['strength'], 2) }}</small>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-{{ $level['classification'] == 'major' ? 'success' : ($level['classification'] == 'minor' ? 'warning' : 'secondary') }}">
                                                    {{ ucfirst($level['classification']) }}
                                                </span>
                                            </td>
                                            <td>{{ number_format($level['confidence'], 1) }}%</td>
                                            <td>{{ number_format($level['total_volume'], 0) }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-info" data-toggle="modal"
                                                    data-target="#levelModal{{ $loop->index }}">
                                                    <i class="tim-icons icon-notes"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trading Signals -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="card-title text-white mb-0">
                                <i class="tim-icons icon-bell-55 mr-2"></i>
                                Trading Signals
                            </h4>
                            <span class="badge badge-primary">Live Analysis</span>
                        </div>
                    </div>
                    <div class="card-body">
                        @if (count($srAnalysis['trading_signals']) > 0)
                            <div class="row">
                                @foreach ($srAnalysis['trading_signals'] as $signal)
                                    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                                        <div
                                            class="card card-stats bg-gradient-{{ $signal['type'] == 'buy' ? 'success' : 'danger' }} h-100">

                                            <!-- Signal Header -->
                                            <div
                                                class="card-header card-header-{{ $signal['type'] == 'buy' ? 'success' : 'danger' }} pb-2">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <i class="tim-icons {{ $signal['type'] == 'buy' ? 'icon-triangle-right-17' : 'icon-minimal-down' }} 
                                               text-white mr-2"
                                                            style="font-size: 1.2rem;"></i>
                                                        <h5 class="card-title text-white mb-0 font-weight-bold">
                                                            {{ strtoupper($signal['type']) }} SIGNAL
                                                        </h5>
                                                    </div>
                                                    <div class="signal-strength">
                                                        @if ($signal['confidence'] >= 80)
                                                            <i class="tim-icons icon-sound-wave text-white"
                                                                title="Strong Signal"></i>
                                                        @elseif($signal['confidence'] >= 60)
                                                            <i class="tim-icons icon-volume-98 text-white"
                                                                title="Medium Signal"></i>
                                                        @else
                                                            <i class="tim-icons icon-volume-98 text-white-50"
                                                                title="Weak Signal"></i>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Signal Details -->
                                            <div class="card-body pt-3">
                                                <!-- Entry Price -->
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <i class="tim-icons icon-single-02 text-white-75 mr-2"></i>
                                                        <span class="text-white-75 font-weight-500">Entry Price</span>
                                                    </div>
                                                    <span class="text-white font-weight-bold h6 mb-0">
                                                        ${{ number_format($signal['entry_price'], 2) }}
                                                    </span>
                                                </div>

                                                <!-- Stop Loss -->
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <i class="tim-icons icon-alert-circle-exc text-white-75 mr-2"></i>
                                                        <span class="text-white-75 font-weight-500">Stop Loss</span>
                                                    </div>
                                                    <span class="text-white font-weight-bold h6 mb-0">
                                                        ${{ number_format($signal['stop_loss'], 2) }}
                                                    </span>
                                                </div>

                                                <!-- Take Profit Levels -->
                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <div class="p-2 bg-black-10 rounded">
                                                            <div class="d-flex align-items-center mb-1">
                                                                <i class="tim-icons icon-trophy text-white-75 mr-1"
                                                                    style="font-size: 0.8rem;"></i>
                                                                <small class="text-white-75 font-weight-500">TP1</small>
                                                            </div>
                                                            <div class="text-white font-weight-bold small">
                                                                ${{ number_format($signal['take_profit_1'], 2) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="p-2 bg-black-10 rounded">
                                                            <div class="d-flex align-items-center mb-1">
                                                                <i class="tim-icons icon-trophy text-white-75 mr-1"
                                                                    style="font-size: 0.8rem;"></i>
                                                                <small class="text-white-75 font-weight-500">TP2</small>
                                                            </div>
                                                            <div class="text-white font-weight-bold small">
                                                                ${{ number_format($signal['take_profit_2'], 2) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Risk/Reward Ratio -->
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <i class="tim-icons icon-chart-bar-32 text-white-75 mr-2"></i>
                                                        <span class="text-white-75 font-weight-500">R/R Ratio</span>
                                                    </div>
                                                    @php
                                                        $risk = abs($signal['entry_price'] - $signal['stop_loss']);
                                                        $reward = abs(
                                                            $signal['take_profit_1'] - $signal['entry_price'],
                                                        );
                                                        $ratio = $risk > 0 ? round($reward / $risk, 2) : 0;
                                                    @endphp
                                                    <span
                                                        class="text-white font-weight-bold h6 mb-0">1:{{ $ratio }}</span>
                                                </div>

                                                <!-- Confidence Level -->
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="text-white-75 font-weight-500">
                                                            <i class="tim-icons icon-coins mr-1"></i>
                                                            Confidence Level
                                                        </span>
                                                        <span
                                                            class="text-white font-weight-bold">{{ $signal['confidence'] }}%</span>
                                                    </div>
                                                    <div class="progress bg-black">
                                                        <div class="progress-bar bg-white progress-bar-striped"
                                                            role="progressbar"
                                                            style="width: {{ $signal['confidence'] }}%"
                                                            aria-valuenow="{{ $signal['confidence'] }}" aria-valuemin="0"
                                                            aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Signal Reason -->
                                                <hr class="bg-white-50">
                                                <div class="d-flex align-items-start">
                                                    <i class="tim-icons icon-bulb-63 text-white-75 mr-2 mt-1"
                                                        style="font-size: 0.9rem;"></i>
                                                    <div>
                                                        <small
                                                            class="text-white-75 font-weight-500 d-block mb-1">Analysis</small>
                                                        <small class="text-white-75">{{ $signal['reason'] }}</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Summary Statistics -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card card-stats bg-gradient-primary">
                                        <div class="card-body p-3">
                                            <div class="row">
                                                <div class="col-lg-3 col-md-6 col-sm-6">
                                                    <div class="numbers text-center">
                                                        <div class="d-flex align-items-center justify-content-center">
                                                            <i class="tim-icons icon-sound-wave text-white mr-2"></i>
                                                            <div class="text-left">
                                                                <p class="card-category text-white-75 mb-1">Active Signals
                                                                </p>
                                                                <h3 class="card-title text-white mb-0">
                                                                    {{ count($srAnalysis['trading_signals']) }}</h3>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-6 col-sm-6">
                                                    <div class="numbers text-center">
                                                        <div class="d-flex align-items-center justify-content-center">
                                                            <i
                                                                class="tim-icons icon-triangle-right-17 text-white mr-2"></i>
                                                            <div class="text-left">
                                                                @php $buySignals = collect($srAnalysis['trading_signals'])->where('type', 'buy')->count(); @endphp
                                                                <p class="card-category text-white-75 mb-1">Buy Signals</p>
                                                                <h3 class="card-title text-white mb-0">{{ $buySignals }}
                                                                </h3>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-6 col-sm-6">
                                                    <div class="numbers text-center">
                                                        <div class="d-flex align-items-center justify-content-center">
                                                            <i class="tim-icons icon-minimal-down text-white mr-2"></i>
                                                            <div class="text-left">
                                                                @php $sellSignals = collect($srAnalysis['trading_signals'])->where('type', 'sell')->count(); @endphp
                                                                <p class="card-category text-white-75 mb-1">Sell Signals
                                                                </p>
                                                                <h3 class="card-title text-white mb-0">{{ $sellSignals }}
                                                                </h3>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-6 col-sm-6">
                                                    <div class="numbers text-center">
                                                        <div class="d-flex align-items-center justify-content-center">
                                                            <i class="tim-icons icon-chart-pie-36 text-white mr-2"></i>
                                                            <div class="text-left">
                                                                @php $avgConfidence = collect($srAnalysis['trading_signals'])->avg('confidence'); @endphp
                                                                <p class="card-category text-white-75 mb-1">Avg Confidence
                                                                </p>
                                                                <h3 class="card-title text-white mb-0">
                                                                    {{ round($avgConfidence) }}%</h3>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <i class="tim-icons icon-chart-bar-32 text-white"
                                        style="font-size: 4rem; opacity: 0.3;"></i>
                                </div>
                                <h5 class="text-white mb-3">No Trading Signals Available</h5>
                                <p class="text-white-75 mb-4">Our analysis system is currently processing market data.
                                    Please check back in a few minutes.</p>
                                <div class="d-flex justify-content-center align-items-center text-white-75">
                                    <div class="spinner-border spinner-border-sm mr-2" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <small>Analyzing market conditions...</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>



            <!-- Recent Breakouts -->
            @if (count($srAnalysis['recent_breakouts']) > 0)
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="card-title text-white mb-0">
                                    <i class="tim-icons icon-chart-bar-32 mr-2"></i>
                                    Recent Breakouts
                                </h4>
                                <span class="badge badge-primary">{{ count($srAnalysis['recent_breakouts']) }}
                                    Active</span>
                            </div>
                        </div>
                        <div class="card-body">
                            @foreach ($srAnalysis['recent_breakouts'] as $breakout)
                                <div
                                    class="alert alert-{{ $breakout['type'] == 'bullish_breakout' ? 'success' : 'danger' }} mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center mb-2">
                                                <i
                                                    class="tim-icons icon-{{ $breakout['type'] == 'bullish_breakout' ? 'triangle-right-17' : 'minimal-down' }} mr-2"></i>
                                                <h5 class="alert-heading mb-0 font-weight-bold">
                                                    {{ ucwords(str_replace('_', ' ', $breakout['type'])) }}
                                                </h5>
                                            </div>

                                            <div class="row">
                                                <div class="col-sm-4 mb-2">
                                                    <strong>Level Broken:</strong><br>
                                                    <span
                                                        class="h6 text-{{ $breakout['type'] == 'bullish_breakout' ? 'success' : 'danger' }}">${{ number_format($breakout['level']['price'], 2) }}</span>
                                                </div>
                                                <div class="col-sm-4 mb-2">
                                                    <strong>Breakout Price:</strong><br>
                                                    <span
                                                        class="h6 text-{{ $breakout['type'] == 'bullish_breakout' ? 'success' : 'danger' }}">${{ number_format($breakout['breakout_price'], 2) }}</span>
                                                </div>
                                                <div class="col-sm-4 mb-2">
                                                    <strong>Volume:</strong><br>
                                                    <span
                                                        class="h6 text-{{ $breakout['type'] == 'bullish_breakout' ? 'success' : 'danger' }}">{{ number_format($breakout['volume'], 0) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4 text-center">
                                            <div class="mb-2">
                                                <h3 class="mb-1 font-weight-bold">{{ $breakout['strength'] }}%</h3>
                                                <small class="text-muted">Signal Strength</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-white progress-bar-striped" role="progressbar"
                                                    style="width: {{ $breakout['strength'] }}%"
                                                    aria-valuenow="{{ $breakout['strength'] }}" aria-valuemin="0"
                                                    aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-2">
                                    <small class="text-muted">
                                        <i class="tim-icons icon-watch-time mr-1"></i>
                                        Detected:
                                        {{ isset($breakout['timestamp']) ? \Carbon\Carbon::parse($breakout['timestamp'])->diffForHumans() : 'Recently' }}
                                    </small>
                                </div>
                            @endforeach

                            <!-- Simple Summary -->
                            <div class="row mt-3">
                                <div class="col-md-4 col-6 mb-2">
                                    <div class="card card-stats bg-gradient-primary">
                                        <div class="card-body p-3 text-center">
                                            <h4 class="text-white mb-1">{{ count($srAnalysis['recent_breakouts']) }}</h4>
                                            <p class="text-white-75 mb-0 small">Total Breakouts</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-6 mb-2">
                                    <div class="card card-stats bg-gradient-success">
                                        <div class="card-body p-3 text-center">
                                            @php $bullishBreakouts = collect($srAnalysis['recent_breakouts'])->where('type', 'bullish_breakout')->count(); @endphp
                                            <h4 class="text-white mb-1">{{ $bullishBreakouts }}</h4>
                                            <p class="text-white-75 mb-0 small">Bullish Breakouts</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-12 mb-2">
                                    <div class="card card-stats bg-gradient-danger">
                                        <div class="card-body p-3 text-center">
                                            @php $bearishBreakouts = collect($srAnalysis['recent_breakouts'])->where('type', 'bearish_breakout')->count(); @endphp
                                            <h4 class="text-white mb-1">{{ $bearishBreakouts }}</h4>
                                            <p class="text-white-75 mb-0 small">Bearish Breakouts</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif




            <!-- Market Structure -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title text-white">Market Structure</h4>
                    </div>
                    <div class="card-body">
                        <div class="structure-info">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <span class="text-white-50">Structure Type:</span>
                                </div>
                                <div class="col-6">
                                    <span
                                        class="badge badge-info">{{ ucfirst($srAnalysis['market_structure']['structure_type']) }}</span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <span class="text-white-50">Trend Direction:</span>
                                </div>
                                <div class="col-6">
                                    <span
                                        class="badge badge-{{ $srAnalysis['market_structure']['trend_direction'] == 'up' ? 'success' : ($srAnalysis['market_structure']['trend_direction'] == 'down' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($srAnalysis['market_structure']['trend_direction']) }}
                                    </span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <span class="text-white-50">Support Levels:</span>
                                </div>
                                <div class="col-6">
                                    <span
                                        class="badge badge-success">{{ $srAnalysis['market_structure']['support_count'] }}</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <span class="text-white-50">Resistance Levels:</span>
                                </div>
                                <div class="col-6">
                                    <span
                                        class="badge badge-danger">{{ $srAnalysis['market_structure']['resistance_count'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analysis Summary -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title text-white">Analysis Summary</h4>
                    </div>
                    <div class="card-body">
                        <div class="summary-content">
                            <p class="text-white-75">{{ $srAnalysis['analysis_summary']['overview'] }}</p>

                            <h6 class="text-white mt-3">Key Insights:</h6>
                            <ul class="text-white-75">
                                @foreach ($srAnalysis['analysis_summary']['key_insights'] as $insight)
                                    <li>{{ $insight }}</li>
                                @endforeach
                            </ul>

                            <h6 class="text-white mt-3">Recommendations:</h6>
                            <ul class="text-success">
                                @foreach ($srAnalysis['analysis_summary']['trading_recommendations'] as $recommendation)
                                    <li>{{ $recommendation }}</li>
                                @endforeach
                            </ul>

                            @if (!empty($srAnalysis['analysis_summary']['risk_warnings']))
                                <div class="alert alert-warning mt-3">
                                    <h6 class="alert-heading">Risk Warnings:</h6>
                                    @foreach ($srAnalysis['analysis_summary']['risk_warnings'] as $warning)
                                        <p class="mb-0">{{ $warning }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Level Detail Modals -->
    @foreach ($srAnalysis['support_resistance_levels'] as $level)
        <div class="modal fade" id="levelModal{{ $loop->index }}" tabindex="-1" role="dialog"
            aria-labelledby="levelModalLabel{{ $loop->index }}">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content bg-dark text-light rounded">
                    <div class="modal-header border-0">
                        <h5 class="modal-title font-lg text-center text-light" id="levelModalLabel{{ $loop->index }}">
                            <i class="bi bi-bar-chart-line-fill text-info me-2"></i>
                            {{ ucfirst($level['type']) }} Level Details -
                            <span class="text-success">${{ number_format($level['price'], 2) }}</span>
                        </h5>
                    </div>

                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="text-info"><i class="bi bi-info-circle-fill me-1"></i>Level Information</h6>
                                <p><strong>Average Price:</strong> <span
                                        class="text-primary">${{ number_format($level['avg_price'], 2) }}</span></p>
                                <p><strong>Touch Count:</strong> {{ $level['touch_count'] }}</p>
                                <p><strong>Total Volume:</strong> <span
                                        class="text-warning">{{ number_format($level['total_volume'], 0) }}</span></p>
                                <p><strong>Strength:</strong>
                                    <span
                                        class="{{ $level['strength'] >= 75 ? 'text-success' : ($level['strength'] >= 50 ? 'text-warning' : 'text-danger') }}">
                                        {{ number_format($level['strength'], 2) }}
                                    </span>
                                </p>
                                <p><strong>Classification:</strong>
                                    <span class="badge bg-warning">{{ ucfirst($level['classification']) }}</span>
                                </p>
                                <p><strong>Proximity:</strong>
                                    <span
                                        class="badge bg-dark border border-light">{{ ucfirst($level['proximity']) }}</span>
                                </p>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-info"><i class="bi bi-shield-check me-1"></i>Validation</h6>
                                <p><strong>Valid:</strong>
                                    @if ($level['validation']['is_valid'])
                                        <span class="text-success"><i class="bi bi-check-circle-fill"></i> Yes</span>
                                    @else
                                        <span class="text-danger"><i class="bi bi-x-circle-fill"></i> No</span>
                                    @endif
                                </p>
                                <p><strong>Score:</strong> {{ $level['validation']['score'] }}/100</p>
                                <p><strong>Confidence:</strong>
                                    <span
                                        class="{{ $level['confidence'] >= 75 ? 'text-success' : ($level['confidence'] >= 50 ? 'text-warning' : 'text-danger') }}">
                                        {{ number_format($level['confidence'], 1) }}%
                                    </span>
                                </p>

                                <h6 class="mt-3"><i class="bi bi-list-check me-1 text-info"></i>Validation Reasons:</h6>
                                <ul>
                                    @foreach ($level['validation']['reasons'] as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <hr class="bg-secondary">

                        <h6 class="text-info mt-4"><i class="bi bi-clock-history me-1"></i>Touch History</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-dark table-striped table-bordered">
                                <thead class="text-uppercase small text-secondary">
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Price</th>
                                        <th>Volume</th>
                                        <th>Strength</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($level['touches'] as $touch)
                                        <tr>
                                            <td>{{ $touch['timestamp'] }}</td>
                                            <td>${{ number_format($touch['price'], 2) }}</td>
                                            <td>{{ number_format($touch['volume'], 0) }}</td>
                                            <td>
                                                <span
                                                    class="{{ $touch['strength'] >= 75 ? 'text-success' : ($touch['strength'] >= 50 ? 'text-warning' : 'text-danger') }}">
                                                    {{ number_format($touch['strength'], 2) }}
                                                </span>
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
    @endforeach

@endsection

@push('styles')
    <style>
        .progress {
            height: 12px;
            margin: 10px;
        }

        .modal-content .modal-body p {
            color: rgba(255, 255, 255, 0.8);
        }

        .progress-container {
            margin-bottom: 1rem;
        }

        .progress-badge {
            font-size: 12px;
            font-weight: 600;
        }

        .progress-value {
            font-size: 12px;
            font-weight: 600;
        }

        .signal-details p {
            margin-bottom: 8px;
        }

        .breakout-strength {
            text-align: center;
        }

        .structure-info .row {
            align-items: center;
        }

        .summary-content h6 {
            color: #fff;
            font-weight: 600;
        }

        .modal-content {
            border: 1px solid #444;
        }

        .table-success {
            background-color: rgba(40, 167, 69, 0.1) !important;
        }

        .table-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        .alert-heading {
            font-weight: 600;
        }

        .bg-black-10 {
            background-color: rgba(0, 0, 0, 0.1) !important;
        }

        .text-white-75 {
            color: rgba(255, 255, 255, 0.75) !important;
        }

        .font-weight-500 {
            font-weight: 500 !important;
        }

        .card-stats .numbers {
            text-align: left;
        }

        .card-stats .numbers p.card-category {
            font-size: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 400;
        }

        .card-stats .numbers h3.card-title {
            font-size: 1.625rem;
            font-weight: 600;
            line-height: 1.3;
        }

        .progress {
            height: 6px;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            border-radius: 10px;
        }

        .card-header-success {
            background: linear-gradient(87deg, #2dce89 0, #2dcecc 100%);
        }

        .card-header-danger {
            background: linear-gradient(87deg, #f5365c 0, #f56036 100%);
        }

        .text-white-75 {
            color: rgba(255, 255, 255, 0.75) !important;
        }

        .alert-success {
            background-color: rgba(45, 206, 137, 0.15) !important;
            border-color: #2dce89 !important;
            color: #2dce89 !important;
        }

        .alert-danger {
            background-color: rgba(245, 54, 92, 0.15) !important;
            border-color: #f5365c !important;
            color: #f5365c !important;
        }

     

        .card-stats .card-body h4 {
            font-size: 1.5rem;
            font-weight: 600;
        }
    </style>
@endpush

@push('js')
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // Auto-refresh functionality (optional)
            // setInterval(function() {
            //     location.reload();
            // }, 60000); // Refresh every minute
        });
    </script>
@endpush
