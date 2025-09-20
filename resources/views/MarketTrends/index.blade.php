@extends('layouts.app')

@section('content')
    @php
        $colors = \App\CommonHelpers::$tpSlcolors;
    @endphp
    <div class="container-fluid">

        {{-- Strategy Legends --}}
        <div class="mb-4">
            <div class="card bg-dark text-light shadow-sm border-0" data-bs-theme="dark">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Strategy Legends</h5>
                </div>
                <div class="card-body d-flex flex-wrap gap-4">
                    @foreach ($colors as $strategy => $c)
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:18px;height:18px;background:{{ $c['tp'] }};border:1px solid #444;"></div>
                            <small class="me-2">TP</small>
                            <div style="width:18px;height:18px;background:{{ $c['sl'] }};border:1px solid #444;"></div>
                            <small class="me-2">SL</small>
                            <span class="fw-semibold">{{ $strategy }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Candlestick Chart --}}
        <x-candlestick-chart :data="$data" symbol="{{ $symbol }}" interval="{{ $interval }}" :indicators="[]"
            :markers="$openingMarkers" :trades="$trades" :lines="$lines" />

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
