@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Candle Averages Report</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Symbol</th>
                                    <th>Interval</th>
                                    <th>Avg Volume</th>
                                    <th>Avg MA7</th>
                                    <th>Avg MA14</th>
                                    <th>Avg MA25</th>
                                    <th>Avg MA99</th>
                                    <th>Avg RSI6</th>
                                    <th>Avg PER</th>
                                    <th>Avg DIF</th>
                                    <th>Avg DEA</th>
                                    <th>Avg Histogram</th>
                                    <th>Avg SAR</th>
                                    <th>Avg OBV</th>
                                    <th>Avg Stoch RSI</th>
                                    <th>Avg Stoch K</th>
                                    <th>Avg Stoch D</th>
                                    <th>Avg Previous OBV High</th>
                                    <th>Avg WR</th>
                                    <th>Avg K</th>
                                    <th>Avg D</th>
                                    <th>Avg J</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($averages as $avg)
                                <tr>
                                    <td>{{ $avg->symbol }}</td>
                                    <td>{{ $avg->interval }}</td>
                                    <td>{{ number_format($avg->avg_volume, 2) }}</td>
                                    <td>{{ number_format($avg->avg_ma7, 2) }}</td>
                                    <td>{{ number_format($avg->avg_ma14, 2) }}</td>
                                    <td>{{ number_format($avg->avg_ma25, 2) }}</td>
                                    <td>{{ number_format($avg->avg_ma99, 2) }}</td>
                                    <td>{{ number_format($avg->avg_rsi6, 2) }}</td>
                                    <td>{{ number_format($avg->avg_per, 2) }}</td>
                                    <td>{{ number_format($avg->avg_dif, 2) }}</td>
                                    <td>{{ number_format($avg->avg_dea, 2) }}</td>
                                    <td>{{ number_format($avg->avg_histogram, 2) }}</td>
                                    <td>{{ number_format($avg->avg_sar, 2) }}</td>
                                    <td>{{ number_format($avg->avg_obv, 2) }}</td>
                                    <td>{{ number_format($avg->avg_stoch_rsi, 2) }}</td>
                                    <td>{{ number_format($avg->avg_stoch_k, 2) }}</td>
                                    <td>{{ number_format($avg->avg_stoch_d, 2) }}</td>
                                    <td>{{ number_format($avg->avg_previousObvHigh, 2) }}</td>
                                    <td>{{ number_format($avg->avg_wr, 2) }}</td>
                                    <td>{{ number_format($avg->avg_K, 2) }}</td>
                                    <td>{{ number_format($avg->avg_D, 2) }}</td>
                                    <td>{{ number_format($avg->avg_J, 2) }}</td>
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
@endsection
