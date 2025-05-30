@extends('layouts.app')

@section('content')
    {{-- resources/views/components/bollinger-bands-analysis.blade.php --}}
    <div class="bollinger-analysis-container">
        <style>
            .bollinger-analysis-container {
                --bg-card: #27293d;
                --text-primary: #ffffff;
                --text-secondary: #a5a5a5;
                --accent-primary: #e14eca;
                --accent-success: #00d4aa;
                --accent-warning: #ff8d72;
                --accent-danger: #fd5d93;
                --accent-info: #1d8cf8;
                --border-color: #2b3553;
            }

            .bb-analysis-card {
                background: var(--bg-card);
                border-radius: 15px;
                border: 1px solid var(--border-color);
                box-shadow: 0 4px 25px rgba(0, 0, 0, 0.15);
            }

            .bb-signal-badge {
                padding: 0.5rem 1.5rem;
                border-radius: 25px;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.9rem;
                letter-spacing: 1px;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }

            .bb-signal-neutral {
                background: linear-gradient(45deg, #434a60, #5a6583);
                color: #ffffff;
            }

            .bb-signal-bullish {
                background: linear-gradient(45deg, var(--accent-success), #4ce3b5);
                color: #ffffff;
            }

            .bb-signal-bearish {
                background: linear-gradient(45deg, var(--accent-danger), #ff7aa8);
                color: #ffffff;
            }

            .bb-metric-card {
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 12px;
                padding: 1.5rem;
                transition: all 0.3s ease;
                height: 100%;
            }

            .bb-metric-card:hover {
                background: rgba(255, 255, 255, 0.08);
                transform: translateY(-2px);
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            }

            .bb-metric-value {
                font-size: 1.8rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
            }

            .bb-metric-label {
                color: var(--text-secondary);
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .bb-metric-change {
                font-size: 0.85rem;
                font-weight: 500;
                padding: 0.25rem 0.5rem;
                border-radius: 15px;
                margin-left: 0.5rem;
            }

            .bb-change-positive {
                background: rgba(0, 212, 170, 0.2);
                color: var(--accent-success);
            }

            .bb-change-negative {
                background: rgba(253, 93, 147, 0.2);
                color: var(--accent-danger);
            }

            .bb-progress-bar {
                height: 8px;
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.1);
                overflow: hidden;
                margin-top: 0.5rem;
            }

            .bb-progress-fill {
                height: 100%;
                border-radius: 10px;
                transition: width 0.8s ease;
            }

            .bb-indicator-badge {
                padding: 0.4rem 0.8rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .bb-badge-true {
                background: rgba(0, 212, 170, 0.2);
                color: var(--accent-success);
                border: 1px solid rgba(0, 212, 170, 0.3);
            }

            .bb-badge-false {
                background: rgba(165, 165, 165, 0.2);
                color: var(--text-secondary);
                border: 1px solid rgba(165, 165, 165, 0.3);
            }

            .bb-section-title {
                color: var(--text-primary);
                font-size: 1.1rem;
                font-weight: 600;
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .bb-momentum-indicator {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 1.1rem;
            }

            .bb-momentum-up {
                background: linear-gradient(45deg, var(--accent-success), #4ce3b5);
                color: white;
            }

            .bb-momentum-down {
                background: linear-gradient(45deg, var(--accent-danger), #ff7aa8);
                color: white;
            }

            .bb-header-stats {
                background: rgba(225, 78, 202, 0.1);
                border: 1px solid rgba(225, 78, 202, 0.2);
                border-radius: 12px;
                padding: 1rem;
            }

            @media (max-width: 768px) {
                .bb-metric-value {
                    font-size: 1.4rem;
                }
            }
        </style>


        <div class="row">
            <div class="col-12">
                <div class="card card-chart">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                               <form action="" method="GET" class="d-flex justify-content-start">
                                <div class="form-group mr-3">
                                    <label for="symbol">Symbol</label>

                                    <input type="text" class="form-control" id="symbol" name="symbol"
                                        value="{{ request('symbol', 'BTCUSDT') }}">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="interval">Interval</label>

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


                                <button type="submit" class="btn  my-4 btn-primary">Update</button>


                            </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <x-candlestick-chart :data="$coinData" symbol="{{ $symbol }}" interval="{{ $interval }}"
            :indicators="[
                // 'ma7',
                // 'ma14',
                // 'ma25',
                // 'ma99',
                'bb',
                'volume',
                'rsi6',
                // 'stoch_rsi',
                // 'macd_hist',
                // 'mfi',
                // 'adx',
                // 'sar',
            ]" 
             :markers="$markers"/>

        <div class="bb-analysis-card p-4">


            {{-- Header Section --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="text-white mb-2">
                        <i class="fas fa-chart-line me-2"></i>
                        Bollinger Bands Analysis
                    </h4>
                    <div class="bb-signal-badge bb-signal-{{ $bbAnalysis['signal'] }}">
                        <i
                            class="fas fa-{{ $bbAnalysis['signal'] === 'neutral' ? 'minus' : ($bbAnalysis['signal'] === 'bullish' ? 'arrow-up' : 'arrow-down') }}"></i>
                        {{ ucfirst($bbAnalysis['signal']) }}
                    </div>
                </div>
                <div class="bb-header-stats text-center">
                    <div class="small text-muted">Lookback Period</div>
                    <div class="h5 text-white mb-0">20 Candles</div>
                </div>
            </div>

            {{-- Probability Section --}}
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="bb-metric-card">
                        <div class="bb-metric-value text-success">
                            {{ $bbAnalysis['long_probability'] }}%
                        </div>
                        <div class="bb-metric-label">Long Probability</div>
                        <div class="bb-progress-bar">
                            <div class="bb-progress-fill"
                                style="width: {{ $bbAnalysis['long_probability'] }}%; background: linear-gradient(90deg, var(--accent-success), #4ce3b5);">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="bb-metric-card">
                        <div class="bb-metric-value text-danger">
                            {{ $bbAnalysis['short_probability'] }}%
                        </div>
                        <div class="bb-metric-label">Short Probability</div>
                        <div class="bb-progress-bar">
                            <div class="bb-progress-fill"
                                style="width: {{ $bbAnalysis['short_probability'] }}%; background: linear-gradient(90deg, var(--accent-danger), #ff7aa8);">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Band Metrics --}}
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="bb-metric-card">
                        <div class="bb-metric-value" style="color: var(--accent-info);">
                            {{ number_format($bbAnalysis['bb_width'], 2) }}
                            <span
                                class="bb-metric-change bb-change-{{ $bbAnalysis['bb_width_change'] >= 0 ? 'positive' : 'negative' }}">
                                <i
                                    class="fas fa-{{ $bbAnalysis['bb_width_change'] >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                                {{ number_format(abs($bbAnalysis['bb_width_change']), 2) }}%
                            </span>
                        </div>
                        <div class="bb-metric-label">BB Width</div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="bb-metric-card">
                        <div class="bb-metric-value" style="color: var(--accent-warning);">
                            {{ number_format($bbAnalysis['percent_b'], 2) }}%
                        </div>
                        <div class="bb-metric-label">%B Position</div>
                        <div class="bb-progress-bar">
                            <div class="bb-progress-fill"
                                style="width: {{ min(100, max(0, $bbAnalysis['percent_b'])) }}%; background: linear-gradient(90deg, var(--accent-warning), #ffb347);">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div class="bb-indicator-badge bb-badge-{{ $bbAnalysis['bb_squeeze'] ? 'true' : 'false' }} mb-2">
                            <i class="fas fa-compress-alt me-1"></i>
                            {{ $bbAnalysis['bb_squeeze'] ? 'Squeeze Active' : 'No Squeeze' }}
                        </div>
                        <div class="bb-metric-label">BB Squeeze</div>
                    </div>
                </div>
            </div>

            {{-- Band Expansion/Contraction --}}
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div class="bb-indicator-badge bb-badge-{{ $bbAnalysis['is_expanding'] ? 'true' : 'false' }} mb-2">
                            <i class="fas fa-expand-alt me-1"></i>
                            {{ $bbAnalysis['is_expanding'] ? 'Expanding' : 'Not Expanding' }}
                        </div>
                        <div class="bb-metric-label">Band Expansion</div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div
                            class="bb-indicator-badge bb-badge-{{ $bbAnalysis['is_contracting'] ? 'true' : 'false' }} mb-2">
                            <i class="fas fa-compress-alt me-1"></i>
                            {{ $bbAnalysis['is_contracting'] ? 'Contracting' : 'Not Contracting' }}
                        </div>
                        <div class="bb-metric-label">Band Contraction</div>
                    </div>
                </div>
            </div>

            {{-- Band Changes --}}
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="bb-metric-card">
                        <div class="bb-metric-value" style="color: var(--accent-success);">
                            {{ number_format($bbAnalysis['bb_upper_percent_change'], 3) }}%
                        </div>
                        <div class="bb-metric-label">Upper Band Change</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="bb-metric-card">
                        <div class="bb-metric-value" style="color: var(--accent-primary);">
                            {{ number_format($bbAnalysis['bb_middle_percent_change'], 3) }}%
                        </div>
                        <div class="bb-metric-label">Middle Band Change</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="bb-metric-card">
                        <div class="bb-metric-value" style="color: var(--accent-danger);">
                            {{ number_format($bbAnalysis['bb_lower_percent_change'], 3) }}%
                        </div>
                        <div class="bb-metric-label">Lower Band Change</div>
                    </div>
                </div>
            </div>

            {{-- Price Action Section --}}
            <div class="bb-section-title">
                <i class="fas fa-chart-bar"></i>
                Price Action Analysis
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div class="bb-momentum-indicator bb-momentum-up mx-auto mb-2">
                            {{ $bbAnalysis['price_action']['upward_momentum'] }}
                        </div>
                        <div class="bb-metric-label">Upward Momentum</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div class="bb-momentum-indicator bb-momentum-down mx-auto mb-2">
                            {{ $bbAnalysis['price_action']['downward_momentum'] }}
                        </div>
                        <div class="bb-metric-label">Downward Momentum</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div
                            class="bb-indicator-badge bb-badge-{{ $bbAnalysis['price_action']['is_near_upper_band'] ? 'true' : 'false' }} mb-2">
                            <i class="fas fa-arrow-up me-1"></i>
                            {{ $bbAnalysis['price_action']['is_near_upper_band'] ? 'Near Upper' : 'Not Near Upper' }}
                        </div>
                        <div class="bb-metric-label">Upper Band Proximity</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div
                            class="bb-indicator-badge bb-badge-{{ $bbAnalysis['price_action']['is_near_lower_band'] ? 'true' : 'false' }} mb-2">
                            <i class="fas fa-arrow-down me-1"></i>
                            {{ $bbAnalysis['price_action']['is_near_lower_band'] ? 'Near Lower' : 'Not Near Lower' }}
                        </div>
                        <div class="bb-metric-label">Lower Band Proximity</div>
                    </div>
                </div>
            </div>

            {{-- Band Crossover Section --}}
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div
                            class="bb-indicator-badge bb-badge-{{ $bbAnalysis['price_action']['crossed_upper_band'] ? 'true' : 'false' }} mb-2">
                            <i class="fas fa-level-up-alt me-1"></i>
                            {{ $bbAnalysis['price_action']['crossed_upper_band'] ? 'Crossed Upper' : 'No Upper Cross' }}
                        </div>
                        <div class="bb-metric-label">Upper Band Crossover</div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="bb-metric-card text-center">
                        <div
                            class="bb-indicator-badge bb-badge-{{ $bbAnalysis['price_action']['crossed_lower_band'] ? 'true' : 'false' }} mb-2">
                            <i class="fas fa-level-down-alt me-1"></i>
                            {{ $bbAnalysis['price_action']['crossed_lower_band'] ? 'Crossed Lower' : 'No Lower Cross' }}
                        </div>
                        <div class="bb-metric-label">Lower Band Crossover</div>
                    </div>
                </div>
            </div>

            {{-- Message Section --}}
            @if (!empty($bbAnalysis['message']))
                <div class="mt-4">
                    <div class="alert alert-info"
                        style="background: rgba(29, 140, 248, 0.1); border: 1px solid rgba(29, 140, 248, 0.2); color: var(--text-primary);">
                        <i class="fas fa-info-circle me-2"></i>
                        {{ $bbAnalysis['message'] }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
