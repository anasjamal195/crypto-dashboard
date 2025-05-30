@extends('layouts.app')

@section('content')


    @if (!empty($analysis))
        <div class="row">
            <div class="col-12">
                <div class="card card-chart">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6">
                                <form action="" method="GET" class="d-flex justify-content-start">

                                    <div class="form-group mr-3">
                                        <input type="text" class="form-control" id="symbol" name="symbol"
                                            value="{{ request('symbol', 'BTCUSDT') }}">
                                    </div>
                                    <div class="form-group mr-3">
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

                                    <div class="form-group">
                                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                    </div>

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
                'volume',
                'rsi6',
                // 'stoch_rsi',
                // 'macd_hist',
                // 'mfi',
                // 'adx',
                // 'sar',
            ]" />
        <div class="row">
            <div class="col-12">
                <div class="card card-chart">


                    <div class="card-header">
                        <div class="row">
                            <div class="col-sm-6 text-left">
                                <h5 class="card-category">Trading Analysis</h5>
                                <h2 class="card-title">{{ $analysis['symbol'] }}</h2>
                            </div>



                            <div class="col-sm-6">
                                <div class="btn-group btn-group-toggle float-right" data-toggle="buttons">
                                    <label
                                        class="btn btn-sm btn-primary btn-simple @if ($analysis['recommendation'] == 'LONG') active @endif">
                                        <input type="radio" name="options" autocomplete="off"
                                            @if ($analysis['recommendation'] == 'LONG') checked @endif> LONG
                                    </label>
                                    <label
                                        class="btn btn-sm btn-primary btn-simple @if ($analysis['recommendation'] == 'HOLD') active @endif">
                                        <input type="radio" name="options" autocomplete="off"
                                            @if ($analysis['recommendation'] == 'HOLD') checked @endif> HOLD
                                    </label>
                                    <label
                                        class="btn btn-sm btn-primary btn-simple @if ($analysis['recommendation'] == 'SHORT') active @endif">
                                        <input type="radio" name="options" autocomplete="off"
                                            @if ($analysis['recommendation'] == 'SHORT') checked @endif> SHORT
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Main Recommendation Panel -->
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-5 col-md-4">
                                                <div class="icon-big text-center icon-warning">
                                                    <i
                                                        class="tim-icons icon-coins 
                                            @if ($analysis['recommendation'] == 'LONG') text-success
                                            @elseif($analysis['recommendation'] == 'SHORT') text-danger
                                            @else text-warning @endif"></i>
                                                </div>
                                            </div>
                                            <div class="col-7 col-md-8">
                                                <div class="numbers">
                                                    <p class="card-category">Recommendation</p>
                                                    <p class="card-title">{{ $analysis['recommendation'] }}
                                                        <span
                                                            class="text-{{ $analysis['risk_level'] == 'HIGH' ? 'danger' : ($analysis['risk_level'] == 'LOW' ? 'success' : 'warning') }}">
                                                            ({{ $analysis['risk_level'] }})
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-5 col-md-4">
                                                <div class="icon-big text-center icon-warning">
                                                    <i class="tim-icons icon-chart-bar-32 text-info"></i>
                                                </div>
                                            </div>
                                            <div class="col-7 col-md-8">
                                                <div class="numbers">
                                                    <p class="card-category">Confidence</p>
                                                    <p class="card-title">{{ $analysis['confidence'] }}%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-5 col-md-4">
                                                <div class="icon-big text-center icon-warning">
                                                    <i class="tim-icons icon-money-coins text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="col-7 col-md-8">
                                                <div class="numbers">
                                                    <p class="card-category">Current Price</p>
                                                    <p class="card-title">
                                                        ${{ number_format($analysis['current_price'], 2) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Probability Chart -->
                        <div class="row">
                            <div class="col-12">
                                <h4 class="card-title">Probability Distribution</h4>
                                <div class="progress-container">
                                    <span class="progress-badge">Long Probability</span>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar"
                                            style="width: {{ $analysis['long_probability'] }}%"
                                            aria-valuenow="{{ $analysis['long_probability'] }}" aria-valuemin="0"
                                            aria-valuemax="100">
                                            {{ $analysis['long_probability'] }}%
                                        </div>
                                    </div>
                                </div>
                                <div class="progress-container">
                                    <span class="progress-badge">Short Probability</span>
                                    <div class="progress">
                                        <div class="progress-bar bg-danger" role="progressbar"
                                            style="width: {{ $analysis['short_probability'] }}%"
                                            aria-valuenow="{{ $analysis['short_probability'] }}" aria-valuemin="0"
                                            aria-valuemax="100">
                                            {{ $analysis['short_probability'] }}%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Individual Analysis Results -->
        <div class="row">
            <!-- Order Book Analysis -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Order Book Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table tablesorter">
                                <tbody>
                                    <tr>
                                        <td><strong>Signal</strong></td>
                                        <td>
                                            <span
                                                class="badge badge-{{ $analysis['individual_analysis']['order_book']['signal'] == 'LONG' ? 'success' : ($analysis['individual_analysis']['order_book']['signal'] == 'SHORT' ? 'danger' : 'warning') }}">
                                                {{ $analysis['individual_analysis']['order_book']['signal'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Long Strength</strong></td>
                                        <td>{{ $analysis['individual_analysis']['order_book']['long_strength'] }}/10</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Short Strength</strong></td>
                                        <td>{{ $analysis['individual_analysis']['order_book']['short_strength'] }}/10</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Volume Imbalance</strong></td>
                                        <td>{{ $analysis['individual_analysis']['order_book']['volume_imbalance'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Spread</strong></td>
                                        <td>${{ number_format($analysis['individual_analysis']['order_book']['spread'], 4) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bollinger Bands Analysis -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Bollinger Bands Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table tablesorter">
                                <tbody>
                                    <tr>
                                        <td><strong>Signal</strong></td>
                                        <td>
                                            <span
                                                class="badge badge-{{ $analysis['individual_analysis']['bollinger_bands']['signal'] == 'neutral' ? 'warning' : 'info' }}">
                                                {{ ucfirst($analysis['individual_analysis']['bollinger_bands']['signal']) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>%B Position</strong></td>
                                        <td>{{ $analysis['individual_analysis']['bollinger_bands']['percent_b'] }}%</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Band Width</strong></td>
                                        <td>{{ number_format($analysis['individual_analysis']['bollinger_bands']['width'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Contracting</strong></td>
                                        <td>
                                            <i
                                                class="tim-icons icon-{{ $analysis['individual_analysis']['bollinger_bands']['is_contracting'] ? 'minimal-down text-danger' : 'minimal-up text-success' }}"></i>
                                            {{ $analysis['individual_analysis']['bollinger_bands']['is_contracting'] ? 'Yes' : 'No' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <small
                                                class="text-muted">{{ $analysis['individual_analysis']['bollinger_bands']['message'] }}</small>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Trend Analysis -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Trend Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table tablesorter">
                                <tbody>
                                    <tr>
                                        <td><strong>Trend Direction</strong></td>
                                        <td>
                                            <span
                                                class="badge badge-{{ $analysis['individual_analysis']['trend_analysis']['trend'] == 'BULLISH' ? 'success' : 'danger' }}">
                                                {{ $analysis['individual_analysis']['trend_analysis']['trend'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Trend Strength</strong></td>
                                        <td>{{ $analysis['individual_analysis']['trend_analysis']['strength'] }}%</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Bullish Signals</strong></td>
                                        <td>{{ $analysis['individual_analysis']['trend_analysis']['bullish_signals'] }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Bearish Signals</strong></td>
                                        <td>{{ $analysis['individual_analysis']['trend_analysis']['bearish_signals'] }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <small
                                                class="text-muted">{{ $analysis['individual_analysis']['trend_analysis']['message'] }}</small>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support/Resistance Analysis -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Support/Resistance Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table tablesorter">
                                <tbody>
                                    <tr>
                                        <td><strong>Buy Signal Confidence</strong></td>
                                        <td>{{ $analysis['individual_analysis']['support_resistance']['buy_confidence'] }}%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sell Signal Confidence</strong></td>
                                        <td>{{ $analysis['individual_analysis']['support_resistance']['sell_confidence'] }}%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Support Levels</strong></td>
                                        <td>{{ $analysis['individual_analysis']['support_resistance']['support_levels'] }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Resistance Levels</strong></td>
                                        <td>{{ $analysis['individual_analysis']['support_resistance']['resistance_levels'] }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Entry and Exit Points -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Entry Points</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table tablesorter">
                                <thead class="text-primary">
                                    <tr>
                                        <th>Price</th>
                                        <th>Type</th>
                                        <th>Confidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($analysis['entry_points'] as $entry)
                                        <tr>
                                            <td>${{ number_format($entry['price'], 2) }}</td>
                                            <td>
                                                <span class="badge badge-info">{{ ucfirst($entry['type']) }}</span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 4px;">
                                                    <div class="progress-bar bg-success"
                                                        style="width: {{ (round($entry['confidence'], 1) / 5) * 100 }}%">
                                                    </div>
                                                </div>
                                                {{ round($entry['confidence'], 1) }}/5
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No entry points available
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Exit Points</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table tablesorter">
                                <thead class="text-primary">
                                    <tr>
                                        <th>Price</th>
                                        <th>Type</th>
                                        <th>Confidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($analysis['exit_points'] as $exit)
                                        <tr>
                                            <td>${{ number_format($exit['price'], 2) }}</td>
                                            <td>
                                                <span class="badge badge-warning">{{ ucfirst($exit['type']) }}</span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 4px;">
                                                    <div class="progress-bar bg-warning"
                                                        style="width: {{ (round($exit['confidence'], 1) / 5) * 100 }}%">
                                                    </div>
                                                </div>
                                                {{ round($exit['confidence'], 1) }}/5
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No exit points available
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Market Conditions -->
        {{-- <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Market Conditions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info">
                                    <div class="icon icon-primary">
                                        <i class="tim-icons icon-chart-bar-32"></i>
                                    </div>
                                    <div class="description">
                                        <h4 class="info-title">Liquidity</h4>
                                        <p>{{ number_format($analysis['market_conditions']['liquidity'], 2) }} BTC</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info">
                                    <div class="icon icon-warning">
                                        <i class="tim-icons icon-alert-circle-exc"></i>
                                    </div>
                                    <div class="description">
                                        <h4 class="info-title">Thin Areas</h4>
                                        <p>{{ $analysis['market_conditions']['thin_areas'] }} detected</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info">
                                    <div
                                        class="icon icon-{{ $analysis['market_conditions']['volatility'] == 'HIGH' ? 'danger' : ($analysis['market_conditions']['volatility'] == 'LOW' ? 'success' : 'warning') }}">
                                        <i class="tim-icons icon-sound-wave"></i>
                                    </div>
                                    <div class="description">
                                        <h4 class="info-title">Volatility</h4>
                                        <p>{{ $analysis['market_conditions']['volatility'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="stats">
                            <i class="tim-icons icon-refresh-01"></i> Last updated:
                            {{ \Carbon\Carbon::parse($analysis['timestamp'])->diffForHumans() }}
                        </div>
                    </div>
                </div>
            </div>
        </div> --}}
    @else
        <div class="row justify-content-center my-4">
            <div class="col-md-8">
                <div class="card border-info">
                    <div class="card-header bg-warning text-white">
                        <h4 class=""><i class="fas fa-info-circle"></i> Error occured</h4>
                    </div>
                    <div class="card-body">
                        <p class="">Please try again in a moment...</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection

@push('styles')
    <style>
        .progress {
            height: 15px;
            margin: 10px;
        }
    </style>
@endpush
