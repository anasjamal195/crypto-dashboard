@extends('layouts.app')
@section('content')
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card card-chart">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-sm-6 text-left">
                                    <h2 class="card-title text-white">Confirmed Trades Report</h2>
                                    <p class="card-category text-muted">Overview of all confirmed trading activities</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th scope="col" class="text-primary">#</th>
                                            <th scope="col" class="text-primary">Exchange</th>
                                            <th scope="col" class="text-primary">Coin Name</th>
                                            <th scope="col" class="text-primary">Type</th>
                                            <th scope="col" class="text-primary">Intention</th>
                                            <th scope="col" class="text-primary">Entry Time</th>
                                            <th scope="col" class="text-primary">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @forelse($confirmedTrades as $index => $trade)
                                            <tr>
                                                <th scope="row" class="text-muted">{{ $index + 1 }}</th>
                                                <td class="text-white">
                                                    <span class="badge badge-pill badge-info">{{ $trade->exchange }}</span>
                                                </td>
                                                <td class="text-white font-weight-bold">{{ $trade->coin_name }}</td>
                                                <td>
                                                    @if (strtolower($trade->type) == 'buy')
                                                        <span class="badge bg-success">{{ ucfirst($trade->type) }}</span>
                                                    @elseif(strtolower($trade->type) == 'sell')
                                                        <span class="badge bg-danger">{{ ucfirst($trade->type) }}</span>
                                                    @else
                                                        <span class="badge bg-warning">{{ ucfirst($trade->type) }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-white">{{ ucfirst($trade->intention) }}</td>
                                                @php
                                                    $timestampMillis = $trade->confirm_candle_timestamp;

                                                    // Convert to Carbon instance in Asia/Karachi timezone
                                                    $timestamp = Carbon::createFromTimestampMs(
                                                        $timestampMillis,
                                                    )->setTimezone('Asia/Karachi');

                                                    // Format as SQL timestamp (Y-m-d H:i:s)
                                                    $sqlTimestamp = $timestamp->toDateTimeString();
                                                @endphp
                                                <td class="text-white">{{ $sqlTimestamp}}</td>

                                                <td class="text-white ">
                                                    @if ($trade->coin_report_id)
                                                        @php
                                                            $order = DB::table('coin_reports')->find(
                                                                $trade->coin_report_id,
                                                            );

                                                            $market = $order->market;
                                                            $symbol = $order->symbol;
                                                            $position = $order->position;
                                                            $formula = $order->formula;
                                                            $interval = $order->interval;
                                                        @endphp
                                                        <a target="_blank"
                                                            href="{{ route('coinReportDetails', ['market' => $market, 'symbol' => $symbol, 'position' => $position, 'formula' => $formula, 'stopLoss' => request('stopLoss'), 'interval' => $interval]) }}"
                                                            class="btn btn-info btn-sm">
                                                            <i class="fa fa-eye"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="tim-icons icon-chart-bar-32 text-primary"
                                                        style="font-size: 3rem;"></i>
                                                    <p class="mt-3">No confirmed trades found</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($confirmedTrades->count() > 0)
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="card bg-primary">
                                            <div class="card-body p-3">
                                                <div class="row">
                                                    <div class="col-md-3 col-sm-6">
                                                        <div class="text-center">
                                                            <h4 class="text-white mb-0">{{ $confirmedTrades->count() }}</h4>
                                                            <span class="text-white-50">Total Trades</span>
                                                        </div>
                                                    </div>
                                                    {{-- <div class="col-md-3 col-sm-6">
                                                    <div class="text-center">
                                                        <h4 class="text-white mb-0">{{ $confirmedTrades->unique('exchange')->count() }}</h4>
                                                        <span class="text-white-50">Exchanges</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 col-sm-6">
                                                    <div class="text-center">
                                                        <h4 class="text-white mb-0">{{ $confirmedTrades->unique('coin_name')->count() }}</h4>
                                                        <span class="text-white-50">Unique Coins</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 col-sm-6">
                                                    <div class="text-center">
                                                        <h4 class="text-white mb-0">{{ $confirmedTrades->where('type', 'buy')->count() }}/{{ $confirmedTrades->where('type', 'sell')->count() }}</h4>
                                                        <span class="text-white-50">Buy/Sell Ratio</span>
                                                    </div>
                                                </div> --}}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .card-chart {
            background: linear-gradient(135deg, #1f1f2e 0%, #16213e 100%);
            border: 1px solid #2c2c54;
        }

        .table-dark {
            background-color: transparent;
        }

        .table-dark th,
        .table-dark td {
            border-color: #2c2c54;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.02);
        }

        .badge-info {
            background-color: #1d8cf8;
        }

        .bg-gradient-primary {
            background: linear-gradient(87deg, #1171ef 0, #11cdef 100%);
        }

        .text-primary {
            color: #1d8cf8 !important;
        }
    </style>
@endsection
