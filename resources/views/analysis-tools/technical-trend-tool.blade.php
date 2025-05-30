@extends('layouts.app')

@section('content')
{{-- resources/views/components/trend-analysis.blade.php --}}
<div class="trend-analysis-container">
    <style>
        .trend-analysis-container {
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

        .trend-analysis-card {
            background: var(--bg-card);
            border-radius: 15px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.15);
        }

        .trend-signal-badge {
            padding: 0.6rem 1.8rem;
            border-radius: 30px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 1.1rem;
            letter-spacing: 1.2px;
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .trend-bullish {
            background: linear-gradient(135deg, var(--accent-success), #4ce3b5);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 212, 170, 0.3);
        }

        .trend-bearish {
            background: linear-gradient(135deg, var(--accent-danger), #ff7aa8);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(253, 93, 147, 0.3);
        }

        .trend-neutral {
            background: linear-gradient(135deg, #434a60, #5a6583);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(67, 74, 96, 0.3);
        }

        .trend-strength-container {
            background: linear-gradient(135deg, rgba(225, 78, 202, 0.1), rgba(29, 140, 248, 0.1));
            border: 1px solid rgba(225, 78, 202, 0.2);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .trend-strength-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
            background: conic-gradient(from 0deg, var(--accent-success) 0%, var(--accent-success) var(--strength-percentage, 0%), rgba(255, 255, 255, 0.1) var(--strength-percentage, 0%), rgba(255, 255, 255, 0.1) 100%);
        }

        .trend-strength-inner {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .trend-strength-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
        }

        .trend-strength-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .trend-signal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .trend-signal-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.2rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .trend-signal-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .trend-signal-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .trend-signal-description {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }

        .trend-signal-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .signal-bullish {
            background: rgba(0, 212, 170, 0.2);
            color: var(--accent-success);
            border: 1px solid rgba(0, 212, 170, 0.3);
        }

        .signal-bearish {
            background: rgba(253, 93, 147, 0.2);
            color: var(--accent-danger);
            border: 1px solid rgba(253, 93, 147, 0.3);
        }

        .signal-neutral {
            background: rgba(165, 165, 165, 0.2);
            color: var(--text-secondary);
            border: 1px solid rgba(165, 165, 165, 0.3);
        }

        .trend-summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .trend-stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .trend-stat-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }

        .trend-stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .trend-stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .trend-section-title {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .trend-message-alert {
            background: linear-gradient(135deg, rgba(29, 140, 248, 0.1), rgba(225, 78, 202, 0.1));
            border: 1px solid rgba(29, 140, 248, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .trend-progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .trend-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease;
        }

        @media (max-width: 768px) {
            .trend-summary-stats {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .trend-signal-grid {
                grid-template-columns: 1fr;
            }

            .trend-strength-circle {
                width: 100px;
                height: 100px;
            }

            .trend-strength-inner {
                width: 75px;
                height: 75px;
            }

            .trend-strength-value {
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
                                    <div class="form-group mr-3 d-flex flex-column">
                                        <label for="symbol">Symbol</label>

                                        <input type="text" class="form-control" id="symbol" name="symbol"
                                            value="{{ request('symbol', 'BTCUSDT') }}">
                                    </div>
                                    <div class="form-group mr-3 d-flex flex-column">
                                        <label for="interval">Interval</label>

                                        <select name="interval" id="interval" class="form-control my-4 select2">
                                            @foreach (\App\CommonHelpers::$binanceIntervals as $key => $value)
                                                <option value="{{ $key }}"
                                                    {{ $interval === $key ? 'selected' : '' }}>
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
            'adx',
            // 'sar',
        ]" />
    <div class="trend-analysis-card p-4">
        {{-- Header Section --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div class="mb-3 mb-md-0">
                <h4 class="text-white mb-3">
                    <i class="fas fa-trending-up me-2"></i>
                    Technical Trend Analysis
                </h4>
                <div class="trend-signal-badge trend-{{ strtolower($trendDetails['trend']) }}">
                    <i
                        class="fas fa-{{ $trendDetails['trend'] === 'BULLISH' ? 'arrow-up' : ($trendDetails['trend'] === 'BEARISH' ? 'arrow-down' : 'minus') }}"></i>
                    {{ $trendDetails['trend'] }}
                </div>
            </div>

            {{-- Strength Indicator --}}
            <div class="trend-strength-container">
                <div class="trend-strength-circle" style="--strength-percentage: {{ $trendDetails['strength'] }}%;">
                    <div class="trend-strength-inner">
                        <div class="trend-strength-value">{{ number_format($trendDetails['strength'], 1) }}%</div>
                        <div class="trend-strength-label">Strength</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Message Alert --}}
        @if (!empty($trendDetails['message']))
            <div class="trend-message-alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle me-3" style="color: var(--accent-info); font-size: 1.2rem;"></i>
                    <div style="color: var(--text-primary); font-weight: 500;">
                        {{ $trendDetails['message'] }}
                    </div>
                </div>
            </div>
        @endif

        {{-- Summary Statistics --}}
        <div class="trend-summary-stats">
            <div class="trend-stat-card">
                <div class="trend-stat-value text-success">
                    {{ $trendDetails['bullish_count'] }}
                </div>
                <div class="trend-stat-label">Bullish Signals</div>
                <div class="trend-progress-bar">
                    <div class="trend-progress-fill"
                        style="width: {{ ($trendDetails['bullish_count'] / $trendDetails['total_signals']) * 100 }}%; background: linear-gradient(90deg, var(--accent-success), #4ce3b5);">
                    </div>
                </div>
            </div>

            <div class="trend-stat-card">
                <div class="trend-stat-value text-danger">
                    {{ $trendDetails['bearish_count'] }}
                </div>
                <div class="trend-stat-label">Bearish Signals</div>
                <div class="trend-progress-bar">
                    <div class="trend-progress-fill"
                        style="width: {{ ($trendDetails['bearish_count'] / $trendDetails['total_signals']) * 100 }}%; background: linear-gradient(90deg, var(--accent-danger), #ff7aa8);">
                    </div>
                </div>
            </div>

            <div class="trend-stat-card">
                <div class="trend-stat-value" style="color: var(--accent-info);">
                    {{ $trendDetails['total_signals'] - $trendDetails['bullish_count'] - $trendDetails['bearish_count'] }}
                </div>
                <div class="trend-stat-label">Neutral Signals</div>
                <div class="trend-progress-bar">
                    <div class="trend-progress-fill"
                        style="width: {{ (($trendDetails['total_signals'] - $trendDetails['bullish_count'] - $trendDetails['bearish_count']) / $trendDetails['total_signals']) * 100 }}%; background: linear-gradient(90deg, #434a60, #5a6583);">
                    </div>
                </div>
            </div>
        </div>

        {{-- Technical Indicators Section --}}
        <div class="trend-section-title">
            <i class="fas fa-chart-line"></i>
            Technical Indicators
        </div>

        <div class="trend-signal-grid">
            @php
                $indicatorLabels = [
                    'MA7' => ['name' => 'MA7', 'description' => '7-period Moving Average'],
                    'MA25' => ['name' => 'MA25', 'description' => '25-period Moving Average'],
                    'EMA_CROSS' => ['name' => 'EMA Cross', 'description' => 'Exponential Moving Average Crossover'],
                    'RSI' => ['name' => 'RSI', 'description' => 'Relative Strength Index'],
                    'MACD' => ['name' => 'MACD', 'description' => 'Moving Average Convergence Divergence'],
                    'BB' => ['name' => 'Bollinger Bands', 'description' => 'Price vs Bollinger Bands'],
                    'SAR' => ['name' => 'Parabolic SAR', 'description' => 'Stop and Reverse'],
                    'ADX' => ['name' => 'ADX', 'description' => 'Average Directional Index'],
                    'STOCH' => ['name' => 'Stochastic', 'description' => 'Stochastic Oscillator'],
                    'WR' => ['name' => 'Williams %R', 'description' => 'Williams Percent Range'],
                    'PRICE_ACTION' => ['name' => 'Price Action', 'description' => 'Price Movement Analysis'],
                    'VOLUME' => ['name' => 'Volume', 'description' => 'Volume Analysis'],
                    'OBV' => ['name' => 'OBV', 'description' => 'On-Balance Volume'],
                ];
            @endphp

            @foreach ($trendDetails['signals'] as $signal => $status)
                <div class="trend-signal-item">
                    <div>
                        <div class="trend-signal-name">
                            {{ $indicatorLabels[$signal]['name'] ?? $signal }}
                        </div>
                        <div class="trend-signal-description">
                            {{ $indicatorLabels[$signal]['description'] ?? '' }}
                        </div>
                    </div>
                    <div class="trend-signal-status signal-{{ strtolower($status) }}">
                        <i
                            class="fas fa-{{ $status === 'BULLISH' ? 'arrow-up' : ($status === 'BEARISH' ? 'arrow-down' : 'minus') }} me-1"></i>
                        {{ $status }}
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Overall Trend Confidence --}}
        <div class="mt-4 p-3" style="background: rgba(255, 255, 255, 0.05); border-radius: 12px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="h6 text-white mb-1">Overall Trend Confidence</div>
                    <div class="small text-muted">
                        Based on {{ $trendDetails['total_signals'] }} technical indicators
                    </div>
                </div>
                <div class="text-end">
                    <div class="h5 mb-1"
                        style="color: {{ $trendDetails['trend'] === 'BULLISH' ? 'var(--accent-success)' : ($trendDetails['trend'] === 'BEARISH' ? 'var(--accent-danger)' : 'var(--text-secondary)') }};">
                        {{ number_format($trendDetails['strength'], 1) }}%
                    </div>
                    <div class="small text-muted">
                        {{ $trendDetails['strength'] >= 70 ? 'Strong' : ($trendDetails['strength'] >= 50 ? 'Moderate' : 'Weak') }}
                    </div>
                </div>
            </div>
            <div class="trend-progress-bar mt-2">
                <div class="trend-progress-fill"
                    style="width: {{ $trendDetails['strength'] }}%; background: linear-gradient(90deg, {{ $trendDetails['trend'] === 'BULLISH' ? 'var(--accent-success), #4ce3b5' : ($trendDetails['trend'] === 'BEARISH' ? 'var(--accent-danger), #ff7aa8' : '#434a60, #5a6583') }});">
                </div>
            </div>
        </div>
    </div>
</div>
@endsection