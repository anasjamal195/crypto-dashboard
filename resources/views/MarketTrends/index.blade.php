@extends('layouts.app')

@section('content')
    @php
        $colors = \App\CommonHelpers::$tpSlcolors;

        // Months map
        $months = [
            'january' => 1,
            'february' => 2,
            'march' => 3,
            'april' => 4,
            'may' => 5,
            'june' => 6,
            'july' => 7,
            'august' => 8,
            'september' => 9,
            'october' => 10,
            'november' => 11,
            'december' => 12,
        ];

        $currentYear = date('Y');
        $selectedMonth = strtolower(request('month', date('F')));
        $selectedYear = request('year', $currentYear);
        $symbolInput = request('symbol', $symbol ?? '');
        $intervalInput = request('interval', '1h');
        $downloadInput = request('download', 'no');
        $rerunInput = request('rerun', 'no');
    @endphp

    <div class="container-fluid">

        {{-- === FILTER FORM === --}}
        <div class="card p-4 bg-dark text-light">
            <h5 class="mb-3">Report Filters</h5>
            <form method="GET" action="">
                <div class="row g-3 align-items-center">
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-control select2">
                            @foreach ($months as $key => $value)
                                <option value="{{ $key }}" {{ request('month') == $key ? 'selected' : '' }}>
                                    {{ ucfirst($key) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-control select2">
                            @for ($y = date('Y'); $y >= 2020; $y--)
                                <option value="{{ $y }}"
                                    {{ request('year', date('Y')) == $y ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Symbol</label>
                        <select name="symbol" class="form-control select2">
                            <option value="BTCUSDT" {{ request('symbol') == 'BTCUSDT' ? 'selected' : '' }}>BTCUSDT</option>
                            <option value="ETHUSDT" {{ request('symbol') == 'ETHUSDT' ? 'selected' : '' }}>ETHUSDT</option>
                            <option value="ETHUSDT" {{ request('symbol') == 'SOLUSDT' ? 'selected' : '' }}>SOLUSDT</option>
                            <option value="ETHUSDT" {{ request('symbol') == 'BNBUSDT' ? 'selected' : '' }}>BNBUSDT</option>
                            <option value="ETHUSDT" {{ request('symbol') == 'HBARUSDT' ? 'selected' : '' }}>HBARUSDT
                            </option>
                            <!-- Add more symbols -->
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Interval</label>
                        <select name="interval" class="form-control select2">
                            <option value="1h" {{ request('interval') == '15m' ? 'selected' : '' }}>15m</option>
                            <option value="1h" {{ request('interval') == '1h' ? 'selected' : '' }}>1h</option>
                            <option value="4h" {{ request('interval') == '4h' ? 'selected' : '' }}>4h</option>
                            <option value="1d" {{ request('interval') == '1d' ? 'selected' : '' }}>1d</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Download Report</label>
                        <select name="download" class="form-control select2">
                            <option value="no" {{ request('download') == 'no' ? 'selected' : '' }}>No</option>
                            <option value="yes" {{ request('download') == 'yes' ? 'selected' : '' }}>Yes</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Rerun Report</label>
                        <select name="rerun" class="form-control select2">
                            <option value="no" {{ request('rerun') == 'no' ? 'selected' : '' }}>No</option>
                            <option value="yes" {{ request('rerun') == 'yes' ? 'selected' : '' }}>Yes</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-gradient btn-lg w-100">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>


        {{-- === SELECTED PERIOD HEADING === --}}
        <div class="text-center mb-5">
            <h2 class="fw-bold text-light text-uppercase mb-0">
                {{ strtoupper($selectedMonth) }} {{ $selectedYear }}
            </h2>
            <hr class="border-secondary w-25 mx-auto opacity-50">
        </div>

        {{-- Strategy Legends --}}
        <div class="mb-4">
            <div class="card bg-dark text-light shadow-sm border-0" data-bs-theme="dark">
                <div class="card-header bg-transparent border-0">
                    <h5 class="card-title mb-0">Strategy Legends</h5>
                </div>
                <div class="card-body d-flex flex-wrap gap-3">
                    @foreach ($colors as $strategy => $c)
                        <div class="legend-card p-3 rounded shadow-sm d-flex flex-column align-items-center 
                            {{ $strategy === 'DEFAULT' ? 'border border-info' : 'border border-secondary' }}"
                            style="min-width: 120px;">
                            <div class="d-flex justify-content-center gap-3 mb-2">
                                <div class="text-center">
                                    <div class="rounded"
                                        style="width:22px;height:22px;background-color:{{ $c['tp'] }};border:1px solid #666;">
                                    </div>
                                    <small class="text-muted">TP</small>
                                </div>
                                <div class="text-center">
                                    <div class="rounded"
                                        style="width:22px;height:22px;background-color:{{ $c['sl'] }};border:1px solid #666;">
                                    </div>
                                    <small class="text-muted">SL</small>
                                </div>
                            </div>
                            <span class="fw-semibold text-uppercase small">{{ $strategy }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Candlestick Chart --}}
        <x-candlestick-chart :data="$data" symbol="{{ $symbol }}" interval="{{ $interval }}"
            :indicators="[]" :markers="$openingMarkers" :trades="$trades" :lines="$lines" />

        @php
            $fmt = fn($v, $dec = 2) => is_numeric($v) ? number_format($v, $dec) : $v;
        @endphp

        {{-- Strategy Stats --}}
        <div class="row my-5">
            @foreach ($stats as $strategy => $s)
                @php
                    $grossPnl = (float) ($s['total_pnl'] ?? 0);
                    $netPnl = (float) ($s['net_pnl'] ?? 0);
                @endphp

                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card bg-dark text-light shadow-sm border-0 h-100" data-bs-theme="dark">
                        <div class="card-header bg-transparent">
                            <h5 class="card-title mb-0">
                                {{ $strategy === 'TOTAL' ? 'All Trades (TOTAL)' : $strategy }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Total Trades:</strong> {{ $fmt($s['total_trades'] ?? 0, 0) }}</p>
                            <p><strong>Long Trades:</strong> {{ $fmt($s['total_long'] ?? 0, 0) }}</p>
                            <p><strong>Short Trades:</strong> {{ $fmt($s['total_short'] ?? 0, 0) }}</p>
                            <p><strong>Win Rate:</strong> {{ $fmt($s['win_rate'] ?? 0, 1) }}%</p>
                            <p><strong>Gross Profit:</strong> +{{ $fmt($s['total_profit'] ?? 0) }}</p>
                            <p><strong>Gross Loss:</strong> -{{ $fmt($s['total_loss'] ?? 0) }}</p>
                            <p><strong>Fees:</strong> {{ $fmt($s['total_fee'] ?? 0) }}</p>

                            <p><strong>Gross PnL:</strong>
                                <span class="{{ $grossPnl >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $grossPnl >= 0 ? '+' : '' }}{{ $fmt($grossPnl) }}
                                </span>
                            </p>
                            <hr class="border-secondary">
                            <p><strong>Net PnL (after fees):</strong>
                                <span class="fw-semibold {{ $netPnl >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $netPnl >= 0 ? '+' : '' }}{{ $fmt($netPnl) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
