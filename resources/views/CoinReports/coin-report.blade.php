@extends('layouts.app')
@php
    $totalProfit = 0;
    $totalTrades = 0;
@endphp
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <!-- Card Header -->
                    <div class="card-header card-header-primary">
                        <h4 class="card-title">Internal Trades Report (Recent 1000 Candles)</h4>
                        <p class="card-category">Here is a list of the latest trades across all coins</p>
                    </div>
                    
                    <!-- Filters Form -->
                    <div class="card-body">
                        <form method="GET" action="{{ url()->current() }}" class="mb-4">
                            <input type="hidden" name="interval" value="{{ request()->get('interval') }}">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="position">Filter by Position</label>
                                        <select name="position" id="position" class="form-control select2">
                                            <option value="">All Positions</option>
                                            <option value="LONG" {{ request('position') == 'LONG' ? 'selected' : '' }}>LONG</option>
                                            <option value="SHORT" {{ request('position') == 'SHORT' ? 'selected' : '' }}>SHORT</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="formula">Filter by Formula</label>
                                        <select name="formula" id="formula" class="form-control select2">
                                            <option value="">All Formulas</option>
                                            @foreach (DB::table('coin_reports')->select('formula')->distinct()->get() as $formula)
                                                <option value="{{ $formula->formula }}"
                                                    {{ request('formula') == $formula->formula ? 'selected' : '' }}>
                                                    {{ $formula->formula }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="stopLoss">Stop Loss % - Default is 1%</label>
                                        <input name="stopLoss" id="stopLoss" class="form-control"
                                            value="{{ request('stopLoss') }}" placeholder="Enter Stop Loss">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary mt-4">Apply</button>
                                </div>
                                <div class="col-md-2">
                                    <a href="{{ route('coinReport', ['market' => 'FUTURE', 'interval' => '5m']) }}"
                                        class="btn btn-secondary mt-4">Clear</a>
                                </div>
                            </div>
                        </form>

                        <!-- Formula Details -->
                        @if (request()->get('formula'))
                            @php
                                $formulaDetails = DB::table('formula_details')
                                    ->where('formula', request('formula'))
                                    ->first();
                            @endphp
                            <div class="form-group mb-4">
                                <label for="formulaDetails">Formula Description</label>
                                <textarea id="formulaDetails" class="form-control" rows="5">{{ $formulaDetails ? $formulaDetails->details : 'No details available for the selected formula.' }}</textarea>
                            </div>
                        @endif
                    </div>

                    <!-- Trade Data Table -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-primary">
                                    <tr>
                                        <th>No</th>
                                        <th>Position</th>
                                        <th>Symbol</th>
                                        <th>Total Duration (min)</th>
                                        <th>Average Duration (min)</th>
                                        <th>Total Trades</th>
                                        <th>Total Profit (%)</th>
                                        <th>Average Profit (%)</th>
                                        <th>Max Profit (%)</th>
                                        <th>Min Profit (%)</th>
                                        <th>Max Lowest Price (%)</th>
                                        <th>Min Lowest Price (%)</th>
                                        <th>Formula</th>
                                        <th>Updated at</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tradeData as $index => $trade)
                                        @php
                                            $totalProfit += number_format($trade->total_profit, 2);
                                            $totalTrades += $trade->total_entries;
                                        @endphp
                                        <tr @if ($trade->max_lowestPrice > $stopLoss) class="bg-danger" @endif>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $trade->position }}</td>
                                            <td>{{ $trade->symbol }}</td>
                                            <td>{{ $trade->total_duration }}</td>
                                            <td>{{ number_format($trade->average_duration, 2) }}</td>
                                            <td>{{ $trade->total_entries }}</td>
                                            <td>{{ number_format($trade->total_profit, 2) }} %</td>
                                            <td>{{ number_format($trade->average_profit, 2) }} %</td>
                                            <td>{{ number_format($trade->max_profit, 2) }} %</td>
                                            <td>{{ number_format($trade->min_profit, 2) }} %</td>
                                            <td>{{ number_format($trade->max_lowestPrice, 2) }} %</td>
                                            <td>{{ number_format($trade->min_lowestPrice, 2) }} %</td>
                                            <td>{{ $trade->formula }}</td>
                                            <td>{{ \Carbon\Carbon::parse($trade->last_updated)->timezone('Asia/Karachi')->format('h:i A') }}</td>
                                            <td>
                                                <a href="{{ route('coinReportDetails', ['market' => $market, 'symbol' => $trade->symbol, 'position' => $trade->position, 'formula' => $trade->formula, 'stopLoss' => request('stopLoss'), 'interval' => '5m']) }}"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Stats Summary -->
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-7">
                                                <div class="numbers">
                                                    <p class="card-category">Max Concurrent Trades</p>
                                                    <h4 class="card-title">{{ round($maxNearbyTrades?->entry_count) }} at {{ $maxNearbyTrades?->time_interval }}</h4>
                                                </div>
                                            </div>
                                            <div class="col-5">
                                                <div class="icon icon-primary">
                                                    <i class="tim-icons icon-chart-bar-32"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-7">
                                                <div class="numbers">
                                                    <p class="card-category">Average Duration</p>
                                                    <h4 class="card-title">{{ round($averageDuration) }} min</h4>
                                                </div>
                                            </div>
                                            <div class="col-5">
                                                <div class="icon icon-info">
                                                    <i class="tim-icons icon-time-alarm"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-7">
                                                <div class="numbers">
                                                    <p class="card-category">Formula Accuracy</p>
                                                    <h4 class="card-title">{{ $totalTrades ? round(100 - ($stopLossesTrades / $totalTrades) * 100, 2) : 0 }} %</h4>
                                                </div>
                                            </div>
                                            <div class="col-5">
                                                <div class="icon icon-success">
                                                    <i class="tim-icons icon-check-2"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card card-stats bg-primary">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5 class="card-title text-white">Profitable Trades</h5>
                                                <p class="card-text text-white">Count: {{ $totalTrades - $stopLossesTrades }}</p>
                                                <p class="card-text text-white">Total Profit: {{ $totalProfit }} %</p>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="icon float-right">
                                                    <i class="tim-icons icon-money-coins text-white"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card card-stats bg-danger">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5 class="card-title text-white">Stop Loss Trades</h5>
                                                <p class="card-text text-white">Count: {{ $stopLossesTrades }}</p>
                                                <p class="card-text text-white">Total Loss: {{ $stopLossesTotal }} %</p>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="icon float-right">
                                                    <i class="tim-icons icon-simple-remove text-white"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card card-stats bg-success">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h5 class="card-title text-white">Grand Total Performance</h5>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p class="card-text text-white">Total Trades: {{ $totalTrades }}</p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p class="card-text text-white">Net Profit: {{ $totalProfit - $stopLossesTotal }} %</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="icon float-right">
                                                    <i class="tim-icons icon-bank text-white"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection