@extends('layouts.app')

@section('content')


    <div class="content">
            <x-symbol-interval-form :symbol="$symbol" :interval="$interval" :coinData="$coinData" heading="Volume Analysis Tool"/>


        <x-candlestick-chart :data="$coinData" symbol="{{ $symbol }}" interval="{{ $interval }}" :markers="[]"
            :indicators="[
                // 'ma7',
                // 'ma14',
                // 'ma25',
                // 'ma99',
                // 'bb',
                'volume',
                // 'rsi6',
                // 'stoch_rsi',
                // 'macd_hist',
                // 'mfi',
                // 'adx',
                // 'sar',
            ]" />


        <!-- Latest Signal Card -->
        @if (count($volumeSignals) > 0)
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Latest Signal</h4>
                        </div>
                        <div class="card-body">
                            @php
                                $latestSignal = end($volumeSignals);
                            @endphp
                            <div class="latest-signal-card">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div
                                            class="signal-highlight {{ $latestSignal['signal'] == 'buy' ? 'bg-success' : 'bg-danger' }}">
                                            <h3 class="mb-0">{{ strtoupper($latestSignal['signal']) }}</h3>
                                            <p class="mb-0">Signal Strength: {{ $latestSignal['strength'] }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="signal-details">
                                            <h4>{{ $latestSignal['symbol'] }} at {{ $latestSignal['price'] }}</h4>
                                            <p>{{ $latestSignal['timestampReadable'] }}</p>
                                            <div class="reasons">
                                                @foreach ($latestSignal['reasons'] as $reason)
                                                    <span
                                                        class="badge {{ strpos($reason, 'Warning') !== false ? 'badge-warning' : ($latestSignal['signal'] == 'buy' ? 'badge-success' : 'badge-danger') }} mr-1">{{ $reason }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="key-indicators">
                                            <div class="indicator-item">
                                                <span class="indicator-label">MFI:</span>
                                                <span
                                                    class="indicator-value">{{ round($latestSignal['indicators']['mfi_current'], 2) }}</span>
                                            </div>
                                            <div class="indicator-item">
                                                <span class="indicator-label">CVD:</span>
                                                <span
                                                    class="indicator-value">{{ round($latestSignal['indicators']['cvd_current'], 2) }}</span>
                                            </div>
                                            <div class="indicator-item">
                                                <span class="indicator-label">VWAP:</span>
                                                <span
                                                    class="indicator-value">{{ round($latestSignal['indicators']['vwap_current'], 2) }}</span>
                                            </div>
                                            <div class="indicator-item">
                                                <span class="indicator-label">OBV:</span>
                                                <span
                                                    class="indicator-value">{{ round($latestSignal['indicators']['obv_current'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Signal History Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Signal History</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table tablesorter" id="signal-history-table">
                                <thead class="text-primary">
                                    <tr>
                                        <th></th>
                                        <th>Time</th>
                                        <th>Signal</th>
                                        <th>Price</th>
                                        <th>Strength</th>
                                        <th>MFI</th>
                                        <th>CVD</th>
                                        <th>OBV</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (array_reverse($volumeSignals) as $index => $signal)
                                        <tr data-signal-index="{{ $index }}">
                                            <td>
                                                <button class="btn btn-link btn-expand" data-toggle="collapse"
                                                    data-target="#signal-details-{{ $index }}">
                                                    <i class="tim-icons icon-minimal-down"></i>
                                                </button>
                                            </td>
                                            <td>{{ $signal['timestampReadable'] }}</td>
                                            <td>
                                                <span
                                                    class="badge {{ $signal['signal'] == 'buy' ? 'bg-success' : 'bg-danger' }}">{{ $signal['signal'] }}
                                                </span>
                                            </td>
                                            <td>{{ $signal['price'] }}</td>
                                            <td>{{ $signal['strength'] }}</td>
                                            <td>{{ round($signal['indicators']['mfi_current'], 2) }}</td>
                                            <td>{{ round($signal['indicators']['cvd_current'], 2) }}</td>
                                            <td>{{ round($signal['indicators']['obv_current'], 2) }}</td>
                                        </tr>
                                        <tr class="expandable-row">
                                            <td colspan="8" class="p-0">
                                                <div class="collapse" id="signal-details-{{ $index }}">
                                                    <div class="card m-3">
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h5>Signal Reasons</h5>
                                                                    <ul class="list-unstyled">
                                                                        @foreach ($signal['reasons'] as $reason)
                                                                            <li
                                                                                class="{{ strpos($reason, 'Warning') !== false ? 'text-warning' : ($signal['signal'] == 'buy' ? 'text-success' : 'text-danger') }}">
                                                                                <i
                                                                                    class="tim-icons {{ strpos($reason, 'Warning') !== false ? 'icon-alert-circle-exc' : ($signal['signal'] == 'buy' ? 'icon-minimal-up' : 'icon-minimal-down') }} mr-1"></i>
                                                                                {{ $reason }}
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h5>Detailed Indicators</h5>
                                                                    <div class="row">
                                                                        @foreach ($signal['indicators'] as $key => $value)
                                                                            @if ($value !== null)
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                                                                                    <span>{{ is_numeric($value) ? round($value, 4) : $value }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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
    </div>


    @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function() {


                // Expandable rows in signal history table
                const expandButtons = document.querySelectorAll('.btn-expand');
                expandButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const icon = this.querySelector('i');
                        if (icon.classList.contains('icon-minimal-down')) {
                            icon.classList.remove('icon-minimal-down');
                            icon.classList.add('icon-minimal-up');
                        } else {
                            icon.classList.remove('icon-minimal-up');
                            icon.classList.add('icon-minimal-down');
                        }
                    });
                });

                // Highlight row on hover instead of changing background
                const tableRows = document.querySelectorAll('#signal-history-table tbody tr:not(.expandable-row)');
                tableRows.forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.classList.add('highlighted-row');
                    });

                    row.addEventListener('mouseleave', function() {
                        this.classList.remove('highlighted-row');
                    });
                });
            });
        </script>

        <style>
            .signal-highlight {
                padding: 15px;
                border-radius: 8px;
                text-align: center;
                color: white;
            }

            .latest-signal-card {
                padding: 10px;
            }

            .reasons {
                margin-top: 10px;
            }

            .indicator-item {
                margin-bottom: 8px;
            }

            .indicator-label {
                font-weight: bold;
                margin-right: 5px;
            }

            .text-success {
                color: #00d6b4 !important;
            }

            .text-danger {
                color: #fd5d93 !important;
            }

            .text-warning {
                color: #ff8d72 !important;
            }

            .expandable-row {
                background-color: transparent !important;
            }

            .btn-expand {
                padding: 5px;
                color: #9a9a9a;
            }

            .highlighted-row {
                background-color: rgba(34, 42, 66, 0.3);
            }

            .chart-controls {
                display: flex;
                justify-content: flex-start;
                align-items: center;
            }
        </style>
    @endpush

@endsection
