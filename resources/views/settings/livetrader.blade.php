@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <h2>Live Trading Settings {{ auth()->user()->name }}</h2>
        <form action="{{ route('live.trader.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-6">
                    <h4>Spot Trading Settings</h4>
                    @foreach (['live_trade_coin_count_spot', 'is_auto_update_enable_spot', 'buy_price_spot', 'target_profit_spot', 'live_trade_worker_interval_spot'] as $setting)
                        <div class="form-group">
                            <label for="{{ $setting }}">{{ str_replace('_', ' ', ucfirst($setting)) }}</label>
                            <input type="text" class="form-control" id="{{ $setting }}" name="{{ $setting }}"
                                value="{{ App\CommonHelpers::getMetaValue(auth()->user()->id, $setting, null) }}">
                        </div>
                    @endforeach
                </div>
                <div class="col-md-6">
                    <h4>Future Trading Settings</h4>
                    @foreach (['live_trade_coin_count_future', 'is_auto_update_enable_future', 'buy_price_future', 'target_profit_future', 'live_trade_worker_interval_future'] as $setting)
                        <div class="form-group">
                            <label for="{{ $setting }}">{{ str_replace('_', ' ', ucfirst($setting)) }}</label>
                            <input type="text" class="form-control" id="{{ $setting }}" name="{{ $setting }}"
                                value="{{ App\CommonHelpers::getMetaValue(auth()->user()->id, $setting, null) }}">
                        </div>
                    @endforeach
                </div>
            </div>


            <button type="submit" class="btn btn-primary">Update Settings</button>
        </form>
    </div>
@endsection
