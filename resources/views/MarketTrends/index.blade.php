@extends('layouts.app')

@section('content')
    <div class="container-fluid">

        <x-candlestick-chart :data="$data" symbol="{{ $symbol }}" interval="{{ $interval }}" :indicators="[
                // 'ma7',
                // 'ma14',
                // 'ma25',
                // 'ma99',
                // 'bb',
                // 'volume',
                // 'rsi6',
                // 'stoch_rsi',
                // 'macd_hist',
                // 'mfi',
                // 'adx',
                // 'sar',
            ]"
            :markers="$openingMarkers" :trades="$trades" :lines="$lines" />

        @php
            $s = collect($stats);
            $fmt = fn($v, $dec = 2) => is_numeric($v) ? number_format($v, $dec) : $v;
            $title = 'Trade Stats';
        @endphp

        <div class="card bg-dark text-light shadow-sm border-0 my-5" data-bs-theme="dark">
            <div class="card-header bg-transparent">
                <h5 class="card-title mb-0">{{ $title }}</h5>
            </div>

            <div class="card-body">
                <p><strong>Total Trades:</strong> {{ $fmt($s->get('total_trades', 0), 0) }}</p>
                <p><strong>Long Trades:</strong> {{ $fmt($s->get('total_long', 0), 0) }}</p>
                <p><strong>Short Trades:</strong> {{ $fmt($s->get('total_short', 0), 0) }}</p>
                <p><strong>Win Rate:</strong> {{ $fmt($s->get('win_rate', 0), 1) }}%</p>
                <p><strong>Gross Profit:</strong> +{{ $fmt($s->get('total_profit', 0)) }}</p>
                <p><strong>Gross Loss:</strong> -{{ $fmt($s->get('total_loss', 0)) }}</p>
                <p><strong>Fees:</strong> {{ $fmt($s->get('total_fee', 0)) }}</p>
                @php $grossPnl = (float) $s->get('total_pnl', 0); @endphp
                <p><strong>Gross PnL:</strong>
                    <span class="{{ $grossPnl >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $grossPnl >= 0 ? '+' : '' }}{{ $fmt($grossPnl) }}
                    </span>
                </p>
                <hr class="border-secondary">
                @php $net = (float) $s->get('net_pnl', 0); @endphp
                <p><strong>Net PnL (after fees):</strong>
                    <span class="fw-semibold {{ $net >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $net >= 0 ? '+' : '' }}{{ $fmt($net) }}
                    </span>
                </p>
            </div>
        </div>
    @endsection
