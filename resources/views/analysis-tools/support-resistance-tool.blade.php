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
                                    <div class="progress">
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
                                    <div class="progress">
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
                                                    <i class="tim-icons icon-zoom-split"></i>
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
                        <h4 class="card-title text-white">
                            <i class="tim-icons icon-bell-55"></i>
                            Trading Signals
                        </h4>
                    </div>
                    <div class="card-body">
                        @if (count($srAnalysis['trading_signals']) > 0)
                            <div class="row">
                                @foreach ($srAnalysis['trading_signals'] as $signal)
                                    <div class="col-md-4 mb-3">
                                        <div
                                            class="card bg-gradient-{{ $signal['type'] == 'buy' ? 'success' : 'danger' }}">
                                            <div class="card-body">
                                                <h5 class="card-title text-white">
                                                    <i
                                                        class="tim-icons icon-{{ $signal['type'] == 'buy' ? 'triangle-right-17' : 'minimal-down' }}"></i>
                                                    {{ strtoupper($signal['type']) }} SIGNAL
                                                </h5>
                                                <div class="signal-details">
                                                    <p class="text-white-75 mb-2">
                                                        <strong>Entry:</strong>
                                                        ${{ number_format($signal['entry_price'], 2) }}
                                                    </p>
                                                    <p class="text-white-75 mb-2">
                                                        <strong>Stop Loss:</strong>
                                                        ${{ number_format($signal['stop_loss'], 2) }}
                                                    </p>
                                                    <p class="text-white-75 mb-2">
                                                        <strong>TP1:</strong>
                                                        ${{ number_format($signal['take_profit_1'], 2) }}
                                                    </p>
                                                    <p class="text-white-75 mb-2">
                                                        <strong>TP2:</strong>
                                                        ${{ number_format($signal['take_profit_2'], 2) }}
                                                    </p>
                                                    <div class="mt-3">
                                                        <div class="progress bg-dark">
                                                            <div class="progress-bar bg-white"
                                                                style="width: {{ $signal['confidence'] }}%">
                                                                <span
                                                                    class="text-dark font-weight-bold">{{ $signal['confidence'] }}%</span>
                                                            </div>
                                                        </div>
                                                        <small class="text-white-75">Confidence Level</small>
                                                    </div>
                                                </div>
                                                <hr class="bg-white-50">
                                                <p class="text-white-75 mb-0">
                                                    <small><strong>Reason:</strong> {{ $signal['reason'] }}</small>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <i class="tim-icons icon-alert-circle-exc"></i>
                                No trading signals available at the moment.
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
                            <h4 class="card-title text-white">
                                <i class="tim-icons icon-chart-bar-32"></i>
                                Recent Breakouts
                            </h4>
                        </div>
                        <div class="card-body">
                            @foreach ($srAnalysis['recent_breakouts'] as $breakout)
                                <div
                                    class="alert alert-{{ $breakout['type'] == 'bullish_breakout' ? 'success' : 'danger' }}">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5 class="alert-heading">
                                                <i
                                                    class="tim-icons icon-{{ $breakout['type'] == 'bullish_breakout' ? 'triangle-right-17' : 'minimal-down' }}"></i>
                                                {{ ucwords(str_replace('_', ' ', $breakout['type'])) }}
                                            </h5>
                                            <p class="mb-1">
                                                <strong>Level Broken:</strong>
                                                ${{ number_format($breakout['level']['price'], 2) }}
                                            </p>
                                            <p class="mb-1">
                                                <strong>Breakout Price:</strong>
                                                ${{ number_format($breakout['breakout_price'], 2) }}
                                            </p>
                                            <p class="mb-0">
                                                <strong>Volume:</strong> {{ number_format($breakout['volume'], 0) }}
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-right">
                                            <div class="breakout-strength">
                                                <h3 class="mb-0">{{ $breakout['strength'] }}%</h3>
                                                <small>Strength</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
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
                <div class="modal-content bg-dark">
                    <div class="modal-header">
                        <h5 class="modal-title text-white" id="levelModalLabel{{ $loop->index }}">
                            {{ ucfirst($level['type']) }} Level Details - ${{ number_format($level['price'], 2) }}
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-white">Level Information</h6>
                                <p class="text-white-75"><strong>Average Price:</strong>
                                    ${{ number_format($level['avg_price'], 2) }}</p>
                                <p class="text-white-75"><strong>Touch Count:</strong> {{ $level['touch_count'] }}</p>
                                <p class="text-white-75"><strong>Total Volume:</strong>
                                    {{ number_format($level['total_volume'], 0) }}</p>
                                <p class="text-white-75"><strong>Strength:</strong>
                                    {{ number_format($level['strength'], 2) }}</p>
                                <p class="text-white-75"><strong>Classification:</strong>
                                    {{ ucfirst($level['classification']) }}</p>
                                <p class="text-white-75"><strong>Proximity:</strong> {{ ucfirst($level['proximity']) }}
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-white">Validation</h6>
                                <p class="text-white-75"><strong>Valid:</strong>
                                    {{ $level['validation']['is_valid'] ? 'Yes' : 'No' }}</p>
                                <p class="text-white-75"><strong>Score:</strong> {{ $level['validation']['score'] }}/100
                                </p>
                                <p class="text-white-75"><strong>Confidence:</strong>
                                    {{ number_format($level['confidence'], 1) }}%</p>

                                <h6 class="text-white mt-3">Validation Reasons:</h6>
                                <ul class="text-white-75">
                                    @foreach ($level['validation']['reasons'] as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <hr class="bg-white-50">

                        <h6 class="text-white">Touch History</h6>
                        <div class="table-responsive">
                            <table class="table table-dark table-sm">
                                <thead>
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
                                            <td>{{ number_format($touch['strength'], 2) }}</td>
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
