@extends('layouts.app')

@section('content')
    <div>
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
            :markers="$labelPlots" :trades="$zonePlots" :lines="$linePlots" />
    </div>
@endsection
