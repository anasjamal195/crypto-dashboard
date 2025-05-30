@extends('layouts.app')

@section('content')
    {{-- Trading Analysis Component --}}
    <style>
        :root {
            --bg-primary: #1a1a1a;
            /* --bg-secondary: #2d2d2d; */
            --bg-tertiary: rgba(255, 255, 255, 0.05);
            --text-primary: #ffffff;
            --text-secondary: #b8b8b8;
            --accent-green: #28a745;
            --accent-red: #dc3545;
            --accent-blue: #007bff;
            --accent-yellow: #ffc107;
            --border-color: #444;
        }

        .analysis-container {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .signal-card {
            background: var(--bg-tertiary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .signal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.4);
        }

        .signal-strength {
            font-size: 2.5rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }

        .confidence-bar {
            height: 8px;
            background: var(--bg-primary);
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }

        .confidence-fill {
            height: 100%;
            transition: width 0.8s ease;
        }

        .long-signal {
            color: var(--accent-green);
        }

        .short-signal {
            color: var(--accent-red);
        }

        .hold-signal {
            color: var(--accent-yellow);
        }

        .long-bg {
            background-color: var(--accent-green);
        }

        .short-bg {
            background-color: var(--accent-red);
        }

        .hold-bg {
            background-color: var(--accent-yellow);
        }

        .reason-badge {
            display: inline-block;
            padding: 6px 12px;
            margin: 3px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reason-positive {
            background: rgba(40, 167, 69, 0.2);
            color: var(--accent-green);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .reason-negative {
            background: rgba(220, 53, 69, 0.2);
            color: var(--accent-red);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .reason-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .pattern-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .pattern-item {
            background: var(--bg-tertiary);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .pattern-item:hover {
            background: var(--bg-primary);
            transform: translateY(-2px);
        }

        .pattern-detected {
            border-color: var(--accent-green);
            background: rgba(40, 167, 69, 0.1);
        }

        .pattern-not-detected {
            opacity: 0.5;
            border-color: var(--border-color);
        }

        .recommendation-card {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            border: 2px solid var(--border-color);
            margin: 20px 0;
        }

        .risk-reward-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-primary);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }

        .price-level {
            background: var(--bg-primary);
            padding: 10px 15px;
            border-radius: 8px;
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header {
            color: var(--text-primary);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .timestamp {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-align: right;
            margin-top: 20px;
        }

        .modal-content {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        .pattern-explanation {
            background: var(--bg-primary);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid var(--accent-blue);
        }

        @media (max-width: 768px) {
            .analysis-container {
                padding: 15px;
                margin: 10px 0;
            }

            .signal-strength {
                font-size: 2rem;
            }

            .pattern-grid {
                grid-template-columns: 1fr;
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

    <x-candlestick-chart :data="$coinData" symbol="{{ $symbol }}" interval="{{ $interval }}" :indicators="[
        'ma7',
        // 'ma14',
        'ma25',
        'ma99',
        // 'bb',
        // 'volume',
        //   'rsi6',
        // 'stoch_rsi',
        // 'macd_hist',
        // 'mfi',
        //   'adx',
        // 'sar',
    ]" />
    <div class="analysis-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-chart-line me-2"></i>Trading Analysis Results</h2>
            <span class="badge bg-primary">Live Analysis</span>
        </div>

        <!-- Recommendation Card -->
        @if (isset($patternDetails['recommendation']))
            <div class="recommendation-card">
                <h3 class="{{ strtolower($patternDetails['recommendation']['action']) }}-signal mb-3">
                    <i
                        class="fas fa-{{ $patternDetails['recommendation']['action'] == 'LONG' ? 'arrow-up' : ($patternDetails['recommendation']['action'] == 'SHORT' ? 'arrow-down' : 'hand-paper') }} me-2"></i>
                    {{ $patternDetails['recommendation']['action'] }} RECOMMENDATION
                </h3>
                <p class="lead mb-2">{{ $patternDetails['recommendation']['reason'] }}</p>
                <small class="text-muted">Score Difference:
                    {{ number_format($patternDetails['recommendation']['score_difference'], 2) }}</small>
            </div>
        @endif

        <!-- Signal Cards Row -->
        <div class="row">
            <!-- LONG Signal -->
            @if (isset($patternDetails['LONG']))
                <div class="col-lg-6 mb-4">
                    <div class="signal-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="long-signal mb-0">
                                <i class="fas fa-arrow-up me-2"></i>LONG SIGNAL
                            </h4>
                            <span class="badge bg-success">Bull</span>
                        </div>

                        <div class="signal-strength long-signal">
                            {{ number_format($patternDetails['LONG']['signal_strength'], 2) }}</div>
                        <p class="text-center mb-3">Signal Strength</p>

                        <div class="confidence-bar">
                            <div class="confidence-fill long-bg"
                                style="width: {{ $patternDetails['LONG']['confidence'] }}%"></div>
                        </div>
                        <small class="text-muted">Confidence:
                            {{ number_format($patternDetails['LONG']['confidence'], 2) }}%</small>

                        <div class="risk-reward-display">
                            <div>
                                <small class="text-muted d-block">Risk/Reward</small>
                                <strong>{{ number_format($patternDetails['LONG']['risk_reward_ratio'], 2) }}:1</strong>
                            </div>
                            <div class="text-end">
                                <div class="price-level">
                                    <span class="text-success">TP:</span>
                                    <strong>${{ number_format($patternDetails['LONG']['take_profit_suggestion'], 2) }}</strong>
                                </div>
                                <div class="price-level">
                                    <span class="text-danger">SL:</span>
                                    <strong>${{ number_format($patternDetails['LONG']['stop_loss_suggestion'], 2) }}</strong>
                                </div>
                            </div>
                        </div>

                        <h6 class="mt-3 mb-2">Entry Reasons:</h6>
                        <div>
                            @foreach ($patternDetails['LONG']['entry_reason'] as $reason)
                                @php
                                    $isPositive = strpos($reason, '+') !== false;
                                @endphp
                                <span
                                    class="reason-badge {{ $isPositive ? 'reason-positive' : 'reason-negative' }} tooltip-trigger"
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="Click for pattern explanation">{{ $reason }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- SHORT Signal -->
            @if (isset($patternDetails['SHORT']))
                <div class="col-lg-6 mb-4">
                    <div class="signal-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="short-signal mb-0">
                                <i class="fas fa-arrow-down me-2"></i>SHORT SIGNAL
                            </h4>
                            <span class="badge bg-danger">Bear</span>
                        </div>

                        <div class="signal-strength short-signal">
                            {{ number_format($patternDetails['SHORT']['signal_strength'], 2) }}</div>
                        <p class="text-center mb-3">Signal Strength</p>

                        <div class="confidence-bar">
                            <div class="confidence-fill short-bg"
                                style="width: {{ $patternDetails['SHORT']['confidence'] }}%"></div>
                        </div>
                        <small class="text-muted">Confidence:
                            {{ number_format($patternDetails['SHORT']['confidence'], 2) }}%</small>

                        <div class="risk-reward-display">
                            <div>
                                <small class="text-muted d-block">Risk/Reward</small>
                                <strong>{{ number_format($patternDetails['SHORT']['risk_reward_ratio'], 2) }}:1</strong>
                            </div>
                            <div class="text-end">
                                <div class="price-level">
                                    <span class="text-success">TP:</span>
                                    <strong>${{ number_format($patternDetails['SHORT']['take_profit_suggestion'], 2) }}</strong>
                                </div>
                                <div class="price-level">
                                    <span class="text-danger">SL:</span>
                                    <strong>${{ number_format($patternDetails['SHORT']['stop_loss_suggestion'], 2) }}</strong>
                                </div>
                            </div>
                        </div>

                        <h6 class="mt-3 mb-2">Entry Reasons:</h6>
                        <div>
                            @foreach ($patternDetails['SHORT']['entry_reason'] as $reason)
                                @php
                                    $isPositive = strpos($reason, '+') !== false;
                                @endphp
                                <span
                                    class="reason-badge {{ $isPositive ? 'reason-positive' : 'reason-negative' }} tooltip-trigger"
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="Click for pattern explanation">{{ $reason }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Patterns Detected -->
        @if (isset($patternDetails['patterns_detected']))
            <div class="mt-4">
                <h4 class="section-header">
                    <i class="fas fa-search me-2"></i>Detected Patterns
                </h4>

                <!-- Chart Patterns -->
                @if (isset($patternDetails['patterns_detected']['chart_patterns']))
                    <div class="mb-4">
                        <h5 class="mb-3 text-info">
                            <i class="fas fa-chart-area me-2"></i>Chart Patterns
                        </h5>
                        <div class="pattern-grid">
                            @foreach ($patternDetails['patterns_detected']['chart_patterns'] as $pattern => $detected)
                                <div class="pattern-item {{ $detected ? 'pattern-detected' : 'pattern-not-detected' }}"
                                    data-bs-toggle="modal" data-bs-target="#patternModal"
                                    data-pattern="{{ str_replace('_', ' ', ucwords($pattern, '_')) }}">
                                    <i
                                        class="fas fa-{{ $detected ? 'check-circle text-success' : 'times-circle text-muted' }} mb-2"></i>
                                    <h6 class="mb-1">{{ str_replace('_', ' ', ucwords($pattern, '_')) }}</h6>
                                    <small class="text-muted">{{ $detected ? 'Detected' : 'Not Found' }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Support/Resistance -->
                @if (isset($patternDetails['patterns_detected']['support_resistance']))
                    <div class="mb-4">
                        <h5 class="mb-3 text-warning">
                            <i class="fas fa-layer-group me-2"></i>Support & Resistance
                        </h5>
                        <div class="pattern-grid">
                            @foreach ($patternDetails['patterns_detected']['support_resistance'] as $sr => $detected)
                                <div class="pattern-item {{ $detected ? 'pattern-detected' : 'pattern-not-detected' }}">
                                    <i
                                        class="fas fa-{{ $detected ? 'check-circle text-success' : 'times-circle text-muted' }} mb-2"></i>
                                    <h6 class="mb-1">{{ str_replace('_', ' ', ucwords($sr, '_')) }}</h6>
                                    <small class="text-muted">{{ $detected ? 'Active' : 'Inactive' }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Momentum Indicators -->
                @if (isset($patternDetails['patterns_detected']['momentum']))
                    <div class="mb-4">
                        <h5 class="mb-3 text-primary">
                            <i class="fas fa-tachometer-alt me-2"></i>Momentum Indicators
                        </h5>
                        <div class="pattern-grid">
                            @foreach ($patternDetails['patterns_detected']['momentum'] as $momentum => $detected)
                                <div class="pattern-item {{ $detected ? 'pattern-detected' : 'pattern-not-detected' }}">
                                    <i
                                        class="fas fa-{{ $detected ? 'check-circle text-success' : 'times-circle text-muted' }} mb-2"></i>
                                    <h6 class="mb-1">{{ str_replace('_', ' ', ucwords($momentum, '_')) }}</h6>
                                    <small class="text-muted">{{ $detected ? 'Confirmed' : 'Not Active' }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Volume Analysis -->
                @if (isset($patternDetails['patterns_detected']['volume_analysis']))
                    <div class="mb-4">
                        <h5 class="mb-3 text-secondary">
                            <i class="fas fa-chart-bar me-2"></i>Volume Analysis
                        </h5>
                        <div class="pattern-grid">
                            @foreach ($patternDetails['patterns_detected']['volume_analysis'] as $volume => $detected)
                                <div class="pattern-item {{ $detected ? 'pattern-detected' : 'pattern-not-detected' }}">
                                    <i
                                        class="fas fa-{{ $detected ? 'check-circle text-success' : 'times-circle text-muted' }} mb-2"></i>
                                    <h6 class="mb-1">{{ str_replace('_', ' ', ucwords($volume, '_')) }}</h6>
                                    <small class="text-muted">{{ $detected ? 'Detected' : 'Not Found' }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Timestamp -->
        @if (isset($patternDetails['timestamp']))
            <div class="timestamp">
                <i class="fas fa-clock me-1"></i>
                Analysis generated: {{ $patternDetails['timestamp'] }}
            </div>
        @endif
    </div>

    <!-- Pattern Explanation Modal -->
    <div class="modal fade" id="patternModal" tabindex="-1" aria-labelledby="patternModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="patternModalLabel">Pattern Explanation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="patternContent">
                        <!-- Content will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Pattern explanations
            const patternExplanations = {
                'Cup And Handle': {
                    description: 'A bullish continuation pattern that resembles a cup with a handle. The "cup" is a rounded bottom, and the "handle" is a small consolidation period.',
                    signal: 'Bullish continuation - Price likely to break higher',
                    formation: 'Forms after an uptrend, shows accumulation phase, then breakout above handle resistance'
                },
                'Double Bottom': {
                    description: 'A bullish reversal pattern that forms after a downtrend, characterized by two distinct lows at approximately the same level.',
                    signal: 'Bullish reversal - Trend change from down to up',
                    formation: 'Two low points separated by a peak, breaking above the peak confirms the pattern'
                },
                'Double Top': {
                    description: 'A bearish reversal pattern that forms after an uptrend, characterized by two distinct highs at approximately the same level.',
                    signal: 'Bearish reversal - Trend change from up to down',
                    formation: 'Two high points separated by a valley, breaking below the valley confirms the pattern'
                },
                'Head And Shoulders': {
                    description: 'A bearish reversal pattern consisting of three peaks: a higher peak (head) between two lower peaks (shoulders).',
                    signal: 'Bearish reversal - Strong sell signal',
                    formation: 'Left shoulder, head (highest peak), right shoulder, then break below neckline'
                },
                'Descending Triangle': {
                    description: 'A bearish continuation pattern with a horizontal support line and a descending resistance line.',
                    signal: 'Bearish continuation - Breakdown expected',
                    formation: 'Lower highs connecting to form descending trendline, horizontal support eventually breaks'
                },
                'Rising Wedge': {
                    description: 'A bearish pattern where both support and resistance lines slope upward, but resistance rises faster.',
                    signal: 'Bearish reversal - Uptrend losing momentum',
                    formation: 'Converging trendlines sloping upward, volume typically decreases'
                },
                'Inverted Cup And Handle': {
                    description: 'A bearish pattern that is the inverse of the cup and handle, resembling an upside-down cup.',
                    signal: 'Bearish continuation - Price likely to break lower',
                    formation: 'Rounded top formation followed by a small rally (handle) before breakdown'
                }
            };

            // Handle pattern modal
            document.querySelectorAll('[data-bs-target="#patternModal"]').forEach(function(element) {
                element.addEventListener('click', function() {
                    const patternName = this.getAttribute('data-pattern');
                    const explanation = patternExplanations[patternName];

                    if (explanation) {
                        document.getElementById('patternModalLabel').textContent = patternName;
                        document.getElementById('patternContent').innerHTML = `
                    <div class="pattern-explanation">
                        <h6 class="text-info">Description:</h6>
                        <p>${explanation.description}</p>
                        
                        <h6 class="text-warning">Trading Signal:</h6>
                        <p>${explanation.signal}</p>
                        
                        <h6 class="text-success">Formation:</h6>
                        <p>${explanation.formation}</p>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Always combine pattern analysis with other technical indicators and risk management strategies.
                        </div>
                    </div>
                `;
                    } else {
                        document.getElementById('patternContent').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Pattern explanation not available for: <strong>${patternName}</strong>
                    </div>
                `;
                    }
                });
            });
        });
    </script>
@endsection
