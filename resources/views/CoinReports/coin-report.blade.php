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
                    <div class="card-header card-header-primary">
                        <h4 class="card-title ">Internal Trades Report (Recent 1000 Candles)</h4>
                        <p class="card-category"> Here is a list of the latest trades across all coins</p>
                    </div>
                    <form method="GET" action="{{ url()->current() }}" class="p-3">
                        <input type="hidden" name="interval" value="{{ request()->get('interval') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="position">Filter by Position</label>
                                    <select name="position" id="position" class="form-control select2">
                                        <option value="">All Positions</option>
                                        <option value="LONG" {{ request('position') == 'LONG' ? 'selected' : '' }}>LONG
                                        </option>
                                        <option value="SHORT" {{ request('position') == 'SHORT' ? 'selected' : '' }}>SHORT
                                        </option>
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
                            <div class="col-md-2 ">
                                <button type="submit" class="btn btn-primary my-2">Apply</button>
                            </div>
                        </div>
                    </form>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-primary">
                                    <tr>
                                        <th>No</th>
                                        <th>Position</th>
                                        <th>Symbol</th>
                                        <th>Total Duration (min)</th>
                                        <th>Total Trades</th>
                                        <th>Total Profit (%)</th>
                                        <th>Average Profit (%)</th>
                                        <th>Average Duration (min)</th>
                                        <th>Max Profit (%)</th>
                                        <th>Min Profit (%)</th>
                                        <th>Max Lowest Price (%)</th>
                                        <th>Min Lowest Price (%)</th>
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
                                            <td>{{ $trade->total_entries }}</td>
                                            <td>{{ number_format($trade->total_profit, 2) }} %</td>
                                            <td>{{ number_format($trade->average_profit, 2) }} %</td>
                                            <td>{{ number_format($trade->average_duration, 2) }}</td>
                                            <td>{{ number_format($trade->max_profit, 2) }} %</td>
                                            <td>{{ number_format($trade->min_profit, 2) }} %</td>
                                            <td>{{ number_format($trade->max_lowestPrice, 2) }} %</td>
                                            <td>{{ number_format($trade->min_lowestPrice, 2) }} %</td>
                                            <td>{{ \Carbon\Carbon::parse($trade->last_updated)->timezone('Asia/Karachi')->format('h:i A') }}
                                            </td>
                                            <td>
                                                <a href="{{ route('coinReportDetails', ['market' => $market, 'symbol' => $trade->symbol, 'position' => $trade->position, 'stopLoss' => request('stopLoss'), 'interval' => '5m']) }}"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach

                                </tbody>
                            </table>
                            <div class="d-flex flex-row text-center text-white">
                                <div class="flex-fill "><strong>Total Profit:</strong></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill ">{{ $totalTrades - $stopLossesTrades }}</div>
                                <div class="flex-fill ">{{ $totalProfit }} %</div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                            </div>
                            <div class="d-flex flex-row text-center text-white">
                                <div class="flex-fill "><strong>Total Stop Losses:</strong></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill ">{{ $stopLossesTrades }}</div>
                                <div class="flex-fill ">{{ $stopLossesTotal }} %</div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                            </div>
                            <div class="d-flex flex-row text-center text-white">
                                <div class="flex-fill "><strong>Grand Total:</strong></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill ">{{ $totalTrades }}</div>
                                <div class="flex-fill ">{{ $totalProfit - $stopLossesTotal }} %</div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                            </div>

                            <div class="d-flex flex-row text-center text-white">
                                <div class="flex-fill "><strong>Formula Accuracy:</strong></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill ">
                                    {{ $totalTrades ? round(100 - ($stopLossesTrades / $totalTrades) * 100, 2) : 0 }} %
                                </div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                                <div class="flex-fill "></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
