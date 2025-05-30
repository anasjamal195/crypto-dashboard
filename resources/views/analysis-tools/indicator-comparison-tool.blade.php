@extends('layouts.app')

@section('content')
    {{-- Enhanced Candle Data Comparison Template for Black Dashboard --}}
    <x-symbol-interval-form :symbol="$symbol" :interval="$interval" :coinData="$coinData" :isIndicatorForm="true" :currentCandle="$currentCandle"
        :prevCandle="$prevCandle" heading="Indicator Comparison Tool" />

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
    ]"
        :markers="$markers" />
    <div class="row my-4">

        <div class="col-12">
            <div class="card card-chart">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6 text-left">
                            <h5 class="card-category">Technical Analysis</h5>
                            <h2 class="card-title">Candle Data Comparison</h2>
                        </div>
                        <div class="col-sm-6">
                            <div class="btn-group btn-group-toggle float-right" data-toggle="buttons">
                                <label class="btn btn-sm btn-primary btn-simple active" id="0">
                                    <input type="radio" name="options" checked>
                                    <span class="d-none d-sm-block d-md-block d-lg-block d-xl-block">All</span>
                                    <span class="d-block d-sm-none">
                                        <i class="tim-icons icon-chart-bar-32"></i>
                                    </span>
                                </label>
                                <label class="btn btn-sm btn-primary btn-simple" id="1">
                                    <input type="radio" class="d-none" name="options">
                                    <span class="d-none d-sm-block d-md-block d-lg-block d-xl-block">Changed</span>
                                    <span class="d-block d-sm-none">
                                        <i class="tim-icons icon-triangle-right-17"></i>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    <div class="table-responsive">
                        <table class="table tablesorter table-hover" id="candleComparisonTable">
                            <thead class="text-primary">
                                <tr>
                                    <th class="text-center">
                                        <i class="tim-icons icon-chart-pie-36"></i>
                                        Indicator
                                    </th>
                                    <th class="text-center">
                                        <i class="tim-icons icon-time-alarm"></i>
                                        Previous Value
                                        <br>
                                        {{ isset($prevCandle['timestampReadable']) ? $prevCandle['timestampReadable'] : '' }}
                                    </th>
                                    <th class="text-center">
                                        <i class="tim-icons icon-refresh-01"></i>
                                        Current Value
                                        <br>
                                        {{ isset($currentCandle['timestampReadable']) ? $currentCandle['timestampReadable'] : '' }}

                                    </th>
                                    <th class="text-center">
                                        <i class="tim-icons icon-chart-bar-32"></i>
                                        Change
                                    </th>
                                    <th class="text-center">
                                        <i class="tim-icons icon-sound-wave"></i>
                                        Signal
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Basic Price Data Section --}}
                                <tr class="section-header">
                                    <td colspan="5" class="text-center">
                                        <h5 class="text-primary mb-0">
                                            <i class="tim-icons icon-coins"></i>
                                            Price Data
                                        </h5>
                                    </td>
                                </tr>

                                @php
                                    $priceIndicators = [
                                        'open' => 'Open',
                                        'high' => 'High',
                                        'low' => 'Low',
                                        'close' => 'Close',
                                        'volume' => 'Volume',
                                    ];
                                @endphp

                                @foreach ($priceIndicators as $key => $label)
                                    @php
                                        $prevValue = $prevCandle[$key];
                                        $currentValue = $currentCandle[$key];
                                        $percentChange = \App\CommonHelpers::getPercentDiff(
                                            $prevValue,
                                            $currentValue,
                                            true,
                                        );
                                        $isPositive = $percentChange > 0;
                                        $isSignificant = abs($percentChange) > 1;
                                    @endphp
                                    <tr class="indicator-row" data-change="{{ abs($percentChange) }}">
                                        <td class="font-weight-bold">
                                            <span class="indicator-badge">{{ $label }}</span>
                                        </td>
                                        <td class="text-center text-muted">
                                            {{ $key === 'volume' ? number_format($prevValue) : number_format($prevValue, 4) }}
                                        </td>
                                        <td class="text-center font-weight-bold">
                                            {{ $key === 'volume' ? number_format($currentValue) : number_format($currentValue, 4) }}
                                        </td>
                                        <td class="text-center">
                                            <span class="change-badge {{ $isPositive ? 'badge-success' : 'badge-danger' }}">
                                                <i
                                                    class="tim-icons {{ $isPositive ? 'icon-minimal-up' : 'icon-minimal-down' }}"></i>
                                                {{ number_format($percentChange, 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($isSignificant)
                                                <span
                                                    class="signal-indicator {{ $isPositive ? 'signal-bullish' : 'signal-bearish' }}">
                                                    {{ $isPositive ? 'BULLISH' : 'BEARISH' }}
                                                </span>
                                            @else
                                                <span class="signal-indicator signal-neutral">NEUTRAL</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Moving Averages Section --}}
                                <tr class="section-header">
                                    <td colspan="5" class="text-center">
                                        <h5 class="text-info mb-0">
                                            <i class="tim-icons icon-chart-bar-32"></i>
                                            Moving Averages
                                        </h5>
                                    </td>
                                </tr>

                                @php
                                    $maIndicators = [
                                        'ma7' => 'MA7',
                                        'ma14' => 'MA14',
                                        'ma25' => 'MA25',
                                        'ma99' => 'MA99',
                                        'ema12' => 'EMA12',
                                        'ema26' => 'EMA26',
                                    ];
                                @endphp

                                @foreach ($maIndicators as $key => $label)
                                    @php
                                        $prevValue = $prevCandle[$key];
                                        $currentValue = $currentCandle[$key];
                                        $percentChange = \App\CommonHelpers::getPercentDiff(
                                            $prevValue,
                                            $currentValue,
                                            true,
                                        );
                                        $isPositive = $percentChange > 0;
                                        $isSignificant = abs($percentChange) > 0.5;
                                    @endphp
                                    <tr class="indicator-row" data-change="{{ abs($percentChange) }}">
                                        <td class="font-weight-bold">
                                            <span class="indicator-badge">{{ $label }}</span>
                                        </td>
                                        <td class="text-center text-muted">{{ number_format($prevValue, 4) }}</td>
                                        <td class="text-center font-weight-bold">{{ number_format($currentValue, 4) }}
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="change-badge {{ $isPositive ? 'badge-success' : 'badge-danger' }}">
                                                <i
                                                    class="tim-icons {{ $isPositive ? 'icon-minimal-up' : 'icon-minimal-down' }}"></i>
                                                {{ number_format($percentChange, 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($isSignificant)
                                                <span
                                                    class="signal-indicator {{ $isPositive ? 'signal-bullish' : 'signal-bearish' }}">
                                                    {{ $isPositive ? 'BULLISH' : 'BEARISH' }}
                                                </span>
                                            @else
                                                <span class="signal-indicator signal-neutral">NEUTRAL</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Bollinger Bands Section --}}
                                <tr class="section-header">
                                    <td colspan="5" class="text-center">
                                        <h5 class="text-warning mb-0">
                                            <i class="tim-icons icon-chart-pie-36"></i>
                                            Bollinger Bands
                                        </h5>
                                    </td>
                                </tr>

                                @php
                                    $bbIndicators = [
                                        'bb_middle' => 'BB Middle',
                                        'bb_upper' => 'BB Upper',
                                        'bb_lower' => 'BB Lower',
                                    ];
                                @endphp

                                @foreach ($bbIndicators as $key => $label)
                                    @php
                                        $prevValue = $prevCandle[$key];
                                        $currentValue = $currentCandle[$key];
                                        $percentChange = \App\CommonHelpers::getPercentDiff(
                                            $prevValue,
                                            $currentValue,
                                            true,
                                        );
                                        $isPositive = $percentChange > 0;
                                        $isSignificant = abs($percentChange) > 0.5;
                                    @endphp
                                    <tr class="indicator-row" data-change="{{ abs($percentChange) }}">
                                        <td class="font-weight-bold">
                                            <span class="indicator-badge">{{ $label }}</span>
                                        </td>
                                        <td class="text-center text-muted">{{ number_format($prevValue, 4) }}</td>
                                        <td class="text-center font-weight-bold">{{ number_format($currentValue, 4) }}
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="change-badge {{ $isPositive ? 'badge-success' : 'badge-danger' }}">
                                                <i
                                                    class="tim-icons {{ $isPositive ? 'icon-minimal-up' : 'icon-minimal-down' }}"></i>
                                                {{ number_format($percentChange, 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($isSignificant)
                                                <span
                                                    class="signal-indicator {{ $isPositive ? 'signal-bullish' : 'signal-bearish' }}">
                                                    {{ $isPositive ? 'BULLISH' : 'BEARISH' }}
                                                </span>
                                            @else
                                                <span class="signal-indicator signal-neutral">NEUTRAL</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Oscillators Section --}}
                                <tr class="section-header">
                                    <td colspan="5" class="text-center">
                                        <h5 class="text-success mb-0">
                                            <i class="tim-icons icon-sound-wave"></i>
                                            Oscillators
                                        </h5>
                                    </td>
                                </tr>

                                @php
                                    $oscillatorIndicators = [
                                        'rsi6' => 'RSI6',
                                        'stoch_rsi' => 'Stoch RSI',
                                        'stoch_k' => 'Stoch K',
                                        'stoch_d' => 'Stoch D',
                                        'wr' => 'Williams %R',
                                    ];
                                @endphp

                                @foreach ($oscillatorIndicators as $key => $label)
                                    @php
                                        $prevValue = $prevCandle[$key];
                                        $currentValue = $currentCandle[$key];
                                        $percentChange = \App\CommonHelpers::getPercentDiff(
                                            $prevValue,
                                            $currentValue,
                                            true,
                                        );
                                        $isPositive = $percentChange > 0;
                                        $isSignificant = abs($percentChange) > 2;
                                        $formatDecimals = $key === 'stoch_rsi' ? 4 : 2;
                                    @endphp
                                    <tr class="indicator-row" data-change="{{ abs($percentChange) }}">
                                        <td class="font-weight-bold">
                                            <span class="indicator-badge">{{ $label }}</span>
                                        </td>
                                        <td class="text-center text-muted">
                                            {{ number_format($prevValue, $formatDecimals) }}</td>
                                        <td class="text-center font-weight-bold">
                                            {{ number_format($currentValue, $formatDecimals) }}</td>
                                        <td class="text-center">
                                            <span
                                                class="change-badge {{ $isPositive ? 'badge-success' : 'badge-danger' }}">
                                                <i
                                                    class="tim-icons {{ $isPositive ? 'icon-minimal-up' : 'icon-minimal-down' }}"></i>
                                                {{ number_format($percentChange, 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($isSignificant)
                                                <span
                                                    class="signal-indicator {{ $isPositive ? 'signal-bullish' : 'signal-bearish' }}">
                                                    {{ $isPositive ? 'BULLISH' : 'BEARISH' }}
                                                </span>
                                            @else
                                                <span class="signal-indicator signal-neutral">NEUTRAL</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Continue with other sections following the same pattern... --}}
                                {{-- MACD Section --}}
                                <tr class="section-header">
                                    <td colspan="5" class="text-center">
                                        <h5 class="text-danger mb-0">
                                            <i class="tim-icons icon-triangle-right-17"></i>
                                            MACD
                                        </h5>
                                    </td>
                                </tr>

                                @php
                                    $macdIndicators = [
                                        'dif' => 'DIF',
                                        'dea' => 'DEA',
                                        'histogram' => 'MACD',
                                    ];
                                @endphp

                                @foreach ($macdIndicators as $key => $label)
                                    @php
                                        $prevValue = $prevCandle[$key];
                                        $currentValue = $currentCandle[$key];
                                        $percentChange = \App\CommonHelpers::getPercentDiff(
                                            $prevValue,
                                            $currentValue,
                                            true,
                                        );
                                        $isPositive = $percentChange > 0;
                                        $isSignificant = abs($percentChange) > 5;
                                    @endphp
                                    <tr class="indicator-row" data-change="{{ abs($percentChange) }}">
                                        <td class="font-weight-bold">
                                            <span class="indicator-badge">{{ $label }}</span>
                                        </td>
                                        <td class="text-center text-muted">{{ number_format($prevValue, 6) }}</td>
                                        <td class="text-center font-weight-bold">{{ number_format($currentValue, 6) }}
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="change-badge {{ $isPositive ? 'badge-success' : 'badge-danger' }}">
                                                <i
                                                    class="tim-icons {{ $isPositive ? 'icon-minimal-up' : 'icon-minimal-down' }}"></i>
                                                {{ number_format($percentChange, 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($isSignificant)
                                                <span
                                                    class="signal-indicator {{ $isPositive ? 'signal-bullish' : 'signal-bearish' }}">
                                                    {{ $isPositive ? 'BULLISH' : 'BEARISH' }}
                                                </span>
                                            @else
                                                <span class="signal-indicator signal-neutral">NEUTRAL</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Volume Indicators Section --}}
                                <tr class="section-header">
                                    <td colspan="5" class="text-center">
                                        <h5 class="text-info mb-0">
                                            <i class="tim-icons icon-chart-bar-32"></i>
                                            Volume Indicators
                                        </h5>
                                    </td>
                                </tr>

                                @php
                                    $volumeIndicators = [
                                        'obv' => 'OBV',
                                        'cvd' => 'CVD',
                                        'mfi' => 'MFI',
                                    ];
                                @endphp

                                @foreach ($volumeIndicators as $key => $label)
                                    @php
                                        $prevValue = $prevCandle[$key];
                                        $currentValue = $currentCandle[$key];
                                        $percentChange = \App\CommonHelpers::getPercentDiff(
                                            $prevValue,
                                            $currentValue,
                                            true,
                                        );
                                        $isPositive = $percentChange > 0;
                                        $isSignificant = abs($percentChange) > 1;
                                    @endphp
                                    <tr class="indicator-row" data-change="{{ abs($percentChange) }}">
                                        <td class="font-weight-bold">
                                            <span class="indicator-badge">{{ $label }}</span>
                                        </td>
                                        <td class="text-center text-muted">{{ number_format($prevValue, 2) }}</td>
                                        <td class="text-center font-weight-bold">{{ number_format($currentValue, 2) }}
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="change-badge {{ $isPositive ? 'badge-success' : 'badge-danger' }}">
                                                <i
                                                    class="tim-icons {{ $isPositive ? 'icon-minimal-up' : 'icon-minimal-down' }}"></i>
                                                {{ number_format($percentChange, 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($isSignificant)
                                                <span
                                                    class="signal-indicator {{ $isPositive ? 'signal-bullish' : 'signal-bearish' }}">
                                                    {{ $isPositive ? 'BULLISH' : 'BEARISH' }}
                                                </span>
                                            @else
                                                <span class="signal-indicator signal-neutral">NEUTRAL</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Additional sections can be added following the same pattern --}}
                                {{-- I'll add the remaining sections in a condensed format --}}

                                @php
                                    $remainingSections = [
                                        'Trend Indicators' => [
                                            'adx' => 'ADX',
                                            'di_plus' => 'DI+',
                                            'di_minus' => 'DI-',
                                            'sar' => 'SAR',
                                        ],
                                        'KDJ' => [
                                            'K' => 'K',
                                            'D' => 'D',
                                            'J' => 'J',
                                        ],
                                        'Other Indicators' => [
                                            'vwap' => 'VWAP',
                                            'per' => 'PER',
                                            'avl' => 'AVL',
                                        ],
                                    ];

                                    $sectionColors = [
                                        'Trend Indicators' => 'text-warning',
                                        'KDJ' => 'text-success',
                                        'Other Indicators' => 'text-primary',
                                    ];
                                @endphp

                                @foreach ($remainingSections as $sectionName => $indicators)
                                    <tr class="section-header">
                                        <td colspan="5" class="text-center">
                                            <h5 class="{{ $sectionColors[$sectionName] }} mb-0">
                                                <i class="tim-icons icon-settings-gear-63"></i>
                                                {{ $sectionName }}
                                            </h5>
                                        </td>
                                    </tr>

                                    @foreach ($indicators as $key => $label)
                                        @php
                                            $prevValue = $prevCandle[$key];
                                            $currentValue = $currentCandle[$key];
                                            $percentChange = \App\CommonHelpers::getPercentDiff(
                                                $prevValue,
                                                $currentValue,
                                                true,
                                            );
                                            $isPositive = $percentChange > 0;
                                            $isSignificant = abs($percentChange) > 1;
                                        @endphp
                                        <tr class="indicator-row" data-change="{{ abs($percentChange) }}">
                                            <td class="font-weight-bold">
                                                <span class="indicator-badge">{{ $label }}</span>
                                            </td>
                                            <td class="text-center text-muted">{{ number_format($prevValue, 4) }}</td>
                                            <td class="text-center font-weight-bold">{{ number_format($currentValue, 4) }}
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="change-badge {{ $isPositive ? 'badge-success' : 'badge-danger' }}">
                                                    <i
                                                        class="tim-icons {{ $isPositive ? 'icon-minimal-up' : 'icon-minimal-down' }}"></i>
                                                    {{ number_format($percentChange, 2) }}%
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if ($isSignificant)
                                                    <span
                                                        class="signal-indicator {{ $isPositive ? 'signal-bullish' : 'signal-bearish' }}">
                                                        {{ $isPositive ? 'BULLISH' : 'BEARISH' }}
                                                    </span>
                                                @else
                                                    <span class="signal-indicator signal-neutral">NEUTRAL</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Custom CSS Styles --}}
    <style>
        .select2-dropdown {
            max-height: 300px;
            overflow-y: auto;
        }

        .section-header {
            background: linear-gradient(45deg, #1e1e2e, #2a2d3a);
            border-left: 4px solid var(--primary-color);
        }

        .section-header h5 {
            font-weight: 600;
            margin: 10px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .indicator-badge {
            background: linear-gradient(45deg, #344675, #263148);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .change-badge {
            padding: 6px 12px !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .badge-success {
            background: linear-gradient(45deg, #00d4aa, #00b894) !important;
            color: #fff !important;
        }

        .badge-danger {
            background: linear-gradient(45deg, #fd5d93, #ec250d) !important;
            color: #fff !important;
        }

        .signal-indicator {
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .signal-bullish {
            background: rgba(0, 212, 170, 0.2);
            color: #00d4aa;
            border: 1px solid #00d4aa;
        }

        .signal-bearish {
            background: rgba(253, 93, 147, 0.2);
            color: #fd5d93;
            border: 1px solid #fd5d93;
        }

        .signal-neutral {
            background: rgba(158, 158, 158, 0.2);
            color: #9e9e9e;
            border: 1px solid #9e9e9e;
        }

        .indicator-row {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .indicator-row:hover {
            background: rgba(255, 255, 255, 0.05);
            border-left: 3px solid #1d8cf8;
            transform: translateX(5px);
        }

        .table td {
            vertical-align: middle;
            padding: 12px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table th {
            border-bottom: 2px solid rgba(29, 140, 248, 0.3);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 12px;
        }

        .card-chart {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2d3a 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(29, 140, 248, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(29, 140, 248, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(29, 140, 248, 0);
            }
        }

        .change-badge:hover {
            animation: pulse 1.5s infinite;
        }
    </style>

    {{-- JavaScript for enhanced functionality --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const filterButtons = document.querySelectorAll('.btn-group label');
            const tableRows = document.querySelectorAll('.indicator-row');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const filterId = this.getAttribute('id');

                    if (filterId === '0') {
                        // Show all rows
                        tableRows.forEach(row => row.style.display = '');
                    } else if (filterId === '1') {
                        // Show only rows with significant changes
                        tableRows.forEach(row => {
                            const change = Math.abs(parseFloat(row.getAttribute(
                                'data-change'))).toFixed(2);
                            row.style.display = change > 0 ? '' : 'none';
                        });
                    }
                });
            });

            // Add sorting functionality
            const table = document.getElementById('candleComparisonTable');
            if (table) {
                // Initialize table sorter if available
                if (typeof $.fn.tablesorter !== 'undefined') {
                    $(table).tablesorter({
                        theme: 'bootstrap',
                        headerTemplate: '{content} {icon}',
                        widgets: ['uitheme', 'zebra'],
                        widgetOptions: {
                            zebra: ["even", "odd"]
                        }
                    });
                }
            }

            // Add hover effects and tooltips
            const indicators = document.querySelectorAll('.indicator-badge');
            indicators.forEach(indicator => {
                indicator.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                    this.style.transition = 'transform 0.2s ease';
                });

                indicator.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Highlight significant changes
            const changeBadges = document.querySelectorAll('.change-badge');
            changeBadges.forEach(badge => {
                const changeText = badge.textContent.replace('%', '');
                const changeValue = Math.abs(parseFloat(changeText));

                if (changeValue > 5) {
                    badge.style.boxShadow = '0 0 15px rgba(255, 255, 255, 0.3)';
                    badge.style.animation = 'pulse 2s infinite';
                }
            });
        });
    </script>
@endsection
