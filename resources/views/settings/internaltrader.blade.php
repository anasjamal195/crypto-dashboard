@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <h2>Trading Settings</h2>
        <form action="{{ route('internal.trader.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-6">
                    <h4>Spot Trading Settings</h4>
                    @foreach (['spot_coin_worker_min_percentage', 'spot_coin_worker_max_percentage', 'spot_coin_worker_quantity', 'trend_worker_interval_spot', 'trend_worker_limit_spot', 'ideal_trade_worker_interval_spot', 'ideal_trade_worker_limit_spot', 'report_worker_interval_spot', 'report_worker_limit_spot'] as $setting)
                        <div class="form-group">
                            <label for="{{ $setting }}">{{ str_replace('_', ' ', ucfirst($setting)) }}</label>
                            <input type="text" class="form-control" id="{{ $setting }}" name="{{ $setting }}"
                                value="{{ App\CommonHelpers::getSettingsValue($setting, null) }}">
                        </div>
                    @endforeach
                </div>
                <div class="col-md-6">
                    <h4>Future Trading Settings</h4>
                    @foreach (['future_coin_worker_min_percentage', 'future_coin_worker_max_percentage', 'future_coin_worker_quantity', 'trend_worker_interval_future', 'trend_worker_limit_future', 'ideal_trade_worker_interval_future', 'ideal_trade_worker_limit_future', 'report_worker_interval_future', 'report_worker_limit_future', 'future_coin_report_leverage'] as $setting)
                        <div class="form-group">
                            <label for="{{ $setting }}">{{ str_replace('_', ' ', ucfirst($setting)) }}</label>
                            <input type="text" class="form-control" id="{{ $setting }}" name="{{ $setting }}"
                                value="{{ App\CommonHelpers::getSettingsValue($setting, null) }}">
                        </div>
                    @endforeach
                </div>
            </div>




            <button type="submit" class="btn btn-primary">Update Settings</button>
        </form>
    </div>
@endsection
