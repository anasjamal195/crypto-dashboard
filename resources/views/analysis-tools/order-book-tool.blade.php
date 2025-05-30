@extends('layouts.app')

@section('content')
        <x-symbol-interval-form :symbol="$symbol" :interval="$interval" :coinData="$coinData" heading="Order Books Analysis Tool" />


    <x-candlestick-chart :data="$coinData" symbol="{{ $symbol }}" interval="{{ $interval }}" :indicators="[
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

    <div class="card shadow my-4">
        @if ($snapshot)
            <div class="card-body">
                <div class="row">
                    <!-- Metadata Section -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Basic Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">ID</th>
                                        <td>{{ $snapshot->id }}</td>
                                    </tr>
                                    <tr>
                                        <th>Symbol</th>
                                        <td><span class="badge bg-info p-2 text-lg">{{ $snapshot->symbol }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Snapshot Time</th>
                                        <td>{{ \Carbon\Carbon::parse($snapshot->snapshot_time)->format('F d, Y h:i A') }}
                                            (Shown on Chart)
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Depth</th>
                                        <td>{{ $snapshot->depth }}</td>
                                    </tr>
{{-- 
                                    <tr>
                                        <th>Profit / SL</th>
                                    </tr>
                                    <tr>
                                        <form method="GET" action="{{ url()->current() }}">
                                            <td>
                                                <input type="number" step="any" name="tp" class="form-control"
                                                    value="{{ request('tp') }}" placeholder="Profit %">
                                            </td>
                                            <td>
                                                <input type="number" step="any" name="sl" class="form-control"
                                                    value="{{ request('sl') }}" placeholder="Stop Loss %">
                                            </td>
                                            <td>

                                                <button type="submit" class="btn btn-primary">Apply</button>
                                            </td>
                                        </form>
                                    </tr> --}}
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Market Stats Section -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Market Statistics</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Bid Volume</th>
                                        <td>{{ number_format($snapshot->bid_volume, 5) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Ask Volume</th>
                                        <td>{{ number_format($snapshot->ask_volume, 5) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Highest Bid</th>
                                        <td class="text-success">{{ number_format($snapshot->highest_bid, 5) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Lowest Ask</th>
                                        <td class="text-danger">{{ number_format($snapshot->lowest_ask, 5) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Spread</th>
                                        <td>{{ number_format($snapshot->spread, 5) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Analysis Section -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Signal Analysis</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Signal</th>
                                        <td>
                                            @if ($snapshot->signal == 'LONG')
                                                <span class="badge bg-success">LONG</span>
                                            @elseif($snapshot->signal == 'SHORT')
                                                <span class="badge bg-danger">SHORT</span>
                                            @elseif($snapshot->signal == 'NEUTRAL')
                                                <span class="badge bg-primary">NEUTRAL</span>
                                            @else
                                                <span class="badge bg-info">{{ $snapshot->signal }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Long Strength</th>
                                        <td>
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-success " role="progressbar"
                                                    style="width: {{ ($snapshot->long_strength / 10) * 100 }}%">
                                                    {{ $snapshot->long_strength }}/10
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Short Strength</th>
                                        <td>
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-danger" role="progressbar"
                                                    style="width: {{ ($snapshot->short_strength / 10) * 100 }}%">
                                                    {{ $snapshot->short_strength }}/10
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Critical Levels Section -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Critical Levels</h5>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-tabs" id="criticalLevelsTabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#support">Support</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#resistance">Resistance</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#liquidity">Thin Liquidity</a>
                                    </li>
                                </ul>
                                <div class="tab-content pt-3">
                                    <div class="tab-pane fade show active" id="support">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Price Level</th>
                                                        <th>Strength</th>
                                                        <th>Volume</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($snapshot->support_levels as $level)
                                                        <tr>
                                                            <td>{{ $level->price ?? $level['price'] }}</td>
                                                            <td>
                                                                @php
                                                                    $strength =
                                                                        $level->strength ?? ($level['strength'] ?? 5);
                                                                    $strengthPercentage =
                                                                        ($strength /
                                                                            $snapshot->support_levels[0]['strength']) *
                                                                        100;
                                                                @endphp
                                                                <div class="progress" style="height:15px">
                                                                    <div class="progress-bar bg-success"
                                                                        role="progressbar"
                                                                        style="width: {{ $strengthPercentage }}%">
                                                                        {{ round($strength) }} x
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>{{ $level->volume ?? ($level['volume'] ?? 'N/A') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="resistance">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Price Level</th>
                                                        <th>Strength</th>
                                                        <th>Volume</th>
                                                    </tr>
                                                </thead>
                                                <tbody>

                                                    @foreach ($snapshot->resistance_levels as $level)
                                                        <tr>
                                                            <td>{{ $level->price ?? $level['price'] }}</td>
                                                            <td>
                                                                @php
                                                                    $strength =
                                                                        $level->strength ?? ($level['strength'] ?? 5);
                                                                    $strengthPercentage =
                                                                        ($strength /
                                                                            $snapshot->resistance_levels[0][
                                                                                'strength'
                                                                            ]) *
                                                                        100;
                                                                @endphp
                                                                <div class="progress" style="height:15px">
                                                                    <div class="progress-bar bg-warning"
                                                                        role="progressbar"
                                                                        style="width: {{ $strengthPercentage }}%">
                                                                        {{ round($strength) }} x
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>{{ $level->volume ?? ($level['volume'] ?? 'N/A') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="liquidity">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>Gap Size</th>
                                                        <th>Start Price</th>
                                                        <th>End Price</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($snapshot->thin_liquidity_areas as $area)
                                                        <tr>
                                                            <td>
                                                                {{ $area['type'] ?? 'N/A' }}
                                                            </td>
                                                            <td>{{ $area['gap_size'] ?? 'N/A' }}</td>
                                                            <td>{{ round($area['start_price'], 5) ?? 'N/A' }}</td>
                                                            <td>{{ round($area['end_price'], 5) ?? 'N/A' }}</td>

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

                    <!-- Entry Points Section -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Entry Points</h5>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-tabs" id="entryPointsTabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#long">
                                            <i class="fas fa-arrow-up text-success"></i> Long Entry Points
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#short">
                                            <i class="fas fa-arrow-down text-danger"></i> Short Entry Points
                                        </a>
                                    </li>
                                </ul>
                                <div class="tab-content pt-3">
                                    <div class="tab-pane fade show active" id="long">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Price</th>
                                                        <th>Confidence</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($snapshot->long_entry_points as $point)
                                                        <tr>
                                                            <td class="text-success">
                                                                {{ $point->price ?? $point['price'] }}</td>

                                                            <td>
                                                                @php
                                                                    $confidence =
                                                                        $point->confidence ??
                                                                        ($point['confidence'] ?? 5);
                                                                    $confidencePercentage = ($confidence / 5) * 100;
                                                                @endphp
                                                                <div class="progress" style="height:15px">
                                                                    <div class="progress-bar bg-success"
                                                                        role="progressbar"
                                                                        style="width: {{ $confidencePercentage }}%">
                                                                        {{ round($confidence, 1) }}/5
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="short">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Price</th>

                                                        <th>Confidence</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($snapshot->short_entry_points as $point)
                                                        <tr>
                                                            <td class="text-danger">
                                                                {{ $point->price ?? $point['price'] }}
                                                            </td>

                                                            <td>
                                                                @php
                                                                    $confidence =
                                                                        $point->confidence ??
                                                                        ($point['confidence'] ?? 5);
                                                                    $confidencePercentage = ($confidence / 5) * 100;
                                                                @endphp
                                                                <div class="progress" style="height:15px">
                                                                    <div class="progress-bar bg-danger" role="progressbar"
                                                                        style="width: {{ $confidencePercentage }}%">
                                                                        {{ round($confidence, 1) }}/5
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
                </div>
            </div>


            <div class="card-footer text-muted">
                <small>Last updated: {{ $snapshot->updated_at ?? $snapshot->snapshot_time }}</small>
            </div>
        @else
            <div class="row justify-content-center my-4">
                <div class="col-md-8">
                    <div class="card border-info">
                        <div class="card-header bg-warning text-white">
                            <h4 class=""><i class="fas fa-info-circle"></i> Input Required</h4>
                        </div>
                        <div class="card-body">
                            <p class="">Please enter the Symbol and Depth to view details. These values are
                                required t  o load the snapshot information and generate the candlestick chart.</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>


@endsection
