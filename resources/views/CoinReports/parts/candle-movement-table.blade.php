@php
    use App\CommonHelpers;

    $count = 0;
    $indicatorAverageRatio = [
        'ratioRsi' => 0,
        'ratioWr' => 0,
        'ratioStochD' => 0,
        'ratioStochK' => 0,
        'ratioDif' => 0,
        'ratioDea' => 0,
        'ratioHistogram' => 0,
        'ratioK' => 0,
        'ratioD' => 0,
        'ratioJ' => 0,
        'ratioVolumeMA5' => 0,
        'ratioVolumeMA10' => 0,

        'ratioWrRsi' => 0,
        'ratioStochDRsi' => 0,
        'ratioStochKRsi' => 0,
        'ratioDifRsi' => 0,
        'ratioDeaRsi' => 0,
        'ratioHistogramRsi' => 0,
        'ratioKRsi' => 0,
        'ratioDRsi' => 0,
        'ratioJRsi' => 0,
        'ratioVolumeMA5Rsi' => 0,
        'ratioVolumeMA10Rsi' => 0,
    ];

    $exportButtonId = $tableType === 'p' ? 'exportProfitableCandleMovementCSV' : 'exportLossesCandleMovementCSV';
    $tableId = $tableType === 'p' ? 'profitableTradesCandleMovementTable' : 'lossTradesCandleMovementTable';

    $totalTrades = $tableType === 'p' ? $reportAnalysis['profitable_trades'] : $reportAnalysis['loss_trades'];

@endphp
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title {{ $tableType === 'p' ? 'text-success' : 'text-danger' }}   ">
                    {{ $tableType === 'p' ? 'Profitable' : 'Losses' }} Trades Candle
                    Movement
                    Table</h4>
                <p class="card-category ">
                    {{ $tableType === 'p' ? $reportAnalysis['profitable_trades'] : $reportAnalysis['loss_trades'] }}
                    {{ $tableType === 'p' ? 'profits' : 'losses' }}</p>
                <!-- Export Button -->
                <button id="{{ $exportButtonId }}" class="btn btn-sm btn-primary float-right">
                    <i class="fa fa-download"></i> Export CSV
                </button>
            </div>
            <div class="card-body" style="max-height: 500px;overflow-y: auto;">
                <div class="">
                    <table id="{{ $tableId }}" class="table tablesorter ">
                        <thead class="text-primary"
                            style="position: sticky;top: -22px;backdrop-filter: blur(10px);background: rgb(39 41 61 / 33%);z-index: 999;">
                            <tr>
                                <th>Sr.</th>
                                <th>Symbol</th>
                                <th>Duration</th>

                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>Candle Last</th>
                                <th>RSI Last</th>
                                <th>Candle Current</th>
                                <th>RSI Current</th>
                                <th>RSI Change %</th>
                                <th>RSI Ratio</th>

                                {{-- WR Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>WR Current</th>
                                <th>WR Change %</th>
                                <th>WR Ratio Candle</th>
                                <th>WR Ratio RSI</th>
                                <th>WR Last</th>

                                {{-- Stoch D Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>Stoch D Current</th>
                                <th>Stoch D Change %</th>
                                <th>Stoch D Ratio Candle</th>
                                <th>Stoch D Ratio RSI</th>
                                <th>Stoch D Last</th>

                                {{-- Stoch K Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>Stoch K Current</th>
                                <th>Stoch K Change %</th>
                                <th>Stoch K Ratio Candle</th>
                                <th>Stoch K Ratio RSI</th>
                                <th>Stoch K Last</th>


                                {{-- DIF Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>Dif Change %</th>
                                <th>Dif Ratio Candle</th>
                                <th>Dif Ratio RSI</th>
                                {{-- DEA Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>Dea Change %</th>
                                <th>Dea Ratio Candle</th>
                                <th>Dea Ratio RSI</th>

                                {{-- MACD Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>MACD Change %</th>
                                <th>MACD Ratio Candle</th>
                                <th>MACD Ratio RSI</th>

                                {{-- Stoch K Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>K Current</th>
                                <th>K Change %</th>
                                <th>K Ratio Candle</th>
                                <th>K Ratio RSI</th>
                                <th>K Last</th>

                                {{-- Stoch D Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>D Current</th>
                                <th>D Change %</th>
                                <th>D Ratio Candle</th>
                                <th>D Ratio RSI</th>
                                <th>D Last</th>

                                {{-- Stoch J Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>J Current</th>
                                <th>J Change %</th>
                                <th>J Ratio Candle</th>
                                <th>J Ratio RSI</th>
                                <th>J Last</th>

                                {{-- Volume MA5 Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>MA5 Change %</th>
                                <th>MA5 Ratio Candle</th>
                                <th>MA5 Ratio RSI</th>


                                {{-- Volume MA10 Indicator --}}
                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>MA10 Change %</th>
                                <th>MA10 Ratio Candle</th>
                                <th>MA10 Ratio RSI</th>

                                <th><span style="width:50px;margin:5px 20px;"></span></th>
                                <th>P%</th>
                                <th>Position</th>

                            </tr>
                        </thead>
                        <tbody>

                            @foreach ($tradeArr as $trade)
                                @php
                                    $openingCandle = json_decode($trade['buyingCandle'], true);
                                    $previousCandle = json_decode($trade['previousCandle'], true);

                                    if ($tableType === 'p') {
                                        if ($trade['profit'] < 0) {
                                            continue;
                                        }
                                    } else {
                                        if ($trade['profit'] > 0) {
                                            continue;
                                        }
                                    }

                                    $count++;

                                    // Indicator Change percentages
                                    $changePrev = $previousCandle ? round($previousCandle['per'], 2) : '-';
                                    $changeCurrent = $openingCandle ? round($openingCandle['per'], 2) : '-';
                                    $changeRsi =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['rsi6'],
                                                    $openingCandle['rsi6'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';
                                    $changeStochD =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['stoch_d'],
                                                    $openingCandle['stoch_d'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';

                                    $changeStochK =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['stoch_k'],
                                                    $openingCandle['stoch_k'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';
                                    $changeWr =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['wr'],
                                                    $openingCandle['wr'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';

                                    $changeK =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['K'],
                                                    $openingCandle['K'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';
                                    $changeD =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['D'],
                                                    $openingCandle['D'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';
                                    $changeJ =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['J'],
                                                    $openingCandle['J'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';

                                    $changeVolumeMA5 =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['volumeMA5'],
                                                    $openingCandle['volumeMA5'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';

                                    $changeVolumeMA10 =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['volumeMA10'],
                                                    $openingCandle['volumeMA10'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';
                                    $changeDif =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['dif'],
                                                    $openingCandle['dif'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';
                                    $changeDea =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['dea'],
                                                    $openingCandle['dea'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';

                                    $changeHistogram =
                                        $openingCandle && $previousCandle
                                            ? round(
                                                CommonHelpers::getPercentDiff(
                                                    $previousCandle['histogram'],
                                                    $openingCandle['histogram'],
                                                    true,
                                                ),
                                                2,
                                            )
                                            : '-';
                                    // Indicator Ratios

                                    $ratioRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['per'],
                                        $changeRsi,
                                    );

                                    $indicatorAverageRatio['ratioRsi'] += $ratioRsi;

                                    $ratioWr = CommonHelpers::calculateIndicatorRatio($openingCandle['per'], $changeWr);
                                    $indicatorAverageRatio['ratioWr'] += $ratioWr;

                                    $ratioStochD = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['per'],
                                        $changeStochD,
                                    );
                                    $indicatorAverageRatio['ratioStochD'] += $ratioStochD;

                                    $ratioStochK = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['per'],
                                        $changeStochK,
                                    );
                                    $indicatorAverageRatio['ratioStochK'] += $ratioStochK;

                                    $ratioK = CommonHelpers::calculateIndicatorRatio($openingCandle['per'], $changeK);
                                    $indicatorAverageRatio['ratioK'] += $ratioK;

                                    $ratioD = CommonHelpers::calculateIndicatorRatio($openingCandle['per'], $changeD);
                                    $indicatorAverageRatio['ratioD'] += $ratioD;

                                    $ratioJ = CommonHelpers::calculateIndicatorRatio($openingCandle['per'], $changeJ);
                                    $indicatorAverageRatio['ratioJ'] += $ratioJ;

                                    $ratioVolumeMA5 = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['per'],
                                        $changeVolumeMA5,
                                    );
                                    $indicatorAverageRatio['ratioVolumeMA5'] += $ratioVolumeMA5;

                                    $ratioVolumeMA10 = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['per'],
                                        $changeVolumeMA10,
                                    );
                                    $indicatorAverageRatio['ratioVolumeMA10'] += $ratioVolumeMA10;

                                    $ratioDif = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['per'],
                                        $changeDif,
                                    );
                                    $indicatorAverageRatio['ratioDif'] += $ratioDif;

                                    $ratioDea = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['per'],
                                        $changeDea,
                                    );
                                    $indicatorAverageRatio['ratioDea'] += $ratioDea;

                                    $ratioHistogram = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['per'],
                                        $changeHistogram,
                                    );
                                    $indicatorAverageRatio['ratioHistogram'] += $ratioHistogram;

                                    // RSI Based Ratios
                                    $ratioWrRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeWr,
                                    );
                                    $indicatorAverageRatio['ratioWrRsi'] += $ratioWrRsi;

                                    $ratioStochDRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeStochD,
                                    );
                                    $indicatorAverageRatio['ratioStochDRsi'] += $ratioStochDRsi;

                                    $ratioStochKRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeStochK,
                                    );
                                    $indicatorAverageRatio['ratioStochKRsi'] += $ratioStochKRsi;

                                    $ratioKRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeK,
                                    );
                                    $indicatorAverageRatio['ratioKRsi'] += $ratioKRsi;

                                    $ratioDRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeD,
                                    );
                                    $indicatorAverageRatio['ratioDRsi'] += $ratioDRsi;

                                    $ratioJRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeJ,
                                    );
                                    $indicatorAverageRatio['ratioJRsi'] += $ratioJRsi;

                                    $ratioVolumeMA5Rsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeVolumeMA5,
                                    );
                                    $indicatorAverageRatio['ratioVolumeMA5Rsi'] += $ratioVolumeMA5Rsi;

                                    $ratioVolumeMA10Rsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeVolumeMA10,
                                    );
                                    $indicatorAverageRatio['ratioVolumeMA10Rsi'] += $ratioVolumeMA10Rsi;

                                    $ratioDifRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeDif,
                                    );
                                    $indicatorAverageRatio['ratioDifRsi'] += $ratioDifRsi;

                                    $ratioDeaRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeDea,
                                    );
                                    $indicatorAverageRatio['ratioDeaRsi'] += $ratioDeaRsi;

                                    $ratioHistogramRsi = CommonHelpers::calculateIndicatorRatio(
                                        $openingCandle['rsi6'],
                                        $changeHistogram,
                                    );
                                    $indicatorAverageRatio['ratioHistogramRsi'] += $ratioHistogramRsi;

                                @endphp
                                <tr class="indicator-row">
                                    <td>{{ $count }} {{ $previousCandle ? '*' : '' }}</td>
                                    <td>{{ $trade['symbol'] }}</td>
                                    <td>{{ $trade['duration'] }}</td>


                                    {{-- RSI Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td
                                        @if ($changePrev > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changePrev < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changePrev }}
                                    </td>
                                    <td>{{ $previousCandle ? round($previousCandle['rsi6'], 2) : '-' }}
                                    </td>
                                    <td
                                        @if ($changeCurrent > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeCurrent < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeCurrent }}
                                    </td>
                                    <td>{{ $openingCandle ? round($openingCandle['rsi6'], 2) : '-' }}
                                    </td>

                                    <td
                                        @if ($changeRsi > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeRsi < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeRsi }}</td>
                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioRsi, 4) : '-' }}
                                    </td>




                                    {{-- WR Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td>{{ $openingCandle ? round($openingCandle['wr'], 2) : '-' }}
                                    </td>
                                    <td
                                        @if ($changeWr > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeWr < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeWr }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioWr, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioWrRsi, 4) : '-' }}
                                    </td>

                                    </td>
                                    <td>{{ $previousCandle ? round($previousCandle['wr'], 2) : '-' }}
                                    </td>



                                    {{-- Stoch D Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td>{{ $openingCandle ? round($openingCandle['stoch_d'], 2) : '-' }}
                                    </td>
                                    <td
                                        @if ($changeStochD > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeStochD < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeStochD }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioStochD, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioStochDRsi, 4) : '-' }}
                                    </td>

                                    <td>{{ $previousCandle ? round($previousCandle['stoch_d'], 2) : '-' }}
                                    </td>


                                    {{-- Stoch K Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td>{{ $openingCandle ? round($openingCandle['stoch_k'], 2) : '-' }}
                                    </td>
                                    <td
                                        @if ($changeStochK > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeStochK < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeStochK }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioStochK, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioStochKRsi, 4) : '-' }}
                                    </td>

                                    <td>{{ $previousCandle ? round($previousCandle['stoch_k'], 2) : '-' }}
                                    </td>


                                    {{--  Dif Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td
                                        @if ($changeDif > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeDif < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeDif }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioDif, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioDifRsi, 4) : '-' }}
                                    </td>

                                    {{--  Dea Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td
                                        @if ($changeDea > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeDea < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeDea }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioDea, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioDeaRsi, 4) : '-' }}
                                    </td>

                                    {{--  Histogram Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td
                                        @if ($changeHistogram > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeHistogram < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeHistogram }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioHistogram, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioHistogramRsi, 4) : '-' }}
                                    </td>
                                    {{--  K Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td>{{ $openingCandle ? round($openingCandle['K'], 2) : '-' }}
                                    </td>
                                    <td
                                        @if ($changeK > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeK < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeK }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioK, 4) : '-' }}
                                    </td>

                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioKRsi, 4) : '-' }}
                                    </td>
                                    <td>{{ $previousCandle ? round($previousCandle['K'], 2) : '-' }}
                                    </td>

                                    {{--  D Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td>{{ $openingCandle ? round($openingCandle['D'], 2) : '-' }}
                                    </td>
                                    <td
                                        @if ($changeD > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeD < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeD }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioD, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioDRsi, 4) : '-' }}
                                    </td>
                                    <td>{{ $previousCandle ? round($previousCandle['D'], 2) : '-' }}
                                    </td>

                                    {{--  J Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td>{{ $openingCandle ? round($openingCandle['J'], 2) : '-' }}
                                    </td>
                                    <td
                                        @if ($changeJ > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeJ < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeJ }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioJ, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioJRsi, 4) : '-' }}
                                    </td>
                                    <td>{{ $previousCandle ? round($previousCandle['J'], 2) : '-' }}
                                    </td>

                                    {{--  Volume MA5 Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td
                                        @if ($changeVolumeMA5 > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeVolumeMA5 < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeVolumeMA5 }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioVolumeMA5, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioVolumeMA5Rsi, 4) : '-' }}
                                    </td>

                                    {{--  Volume MA10 Indicator --}}
                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td
                                        @if ($changeVolumeMA10 > 0) style="color: #00f2c3 !important;"
                                                        @elseif($changeVolumeMA10 < 0)
                                                        style="color: #fd5d93 !important;" @endif>
                                        {{ $changeVolumeMA10 }}</td>

                                    <td style="color: #fd9d5d !important;">
                                        {{ $previousCandle ? round($ratioVolumeMA10, 4) : '-' }}
                                    </td>
                                    <td style="color: #5d82fd !important;">
                                        {{ $previousCandle ? round($ratioVolumeMA10Rsi, 4) : '-' }}
                                    </td>

                                    <td><span style="width:50px;margin:5px 20px;"></span></td>
                                    <td>{{ $trade['profit'] }}</td>
                                    <td>{{ $trade['position'] }}</td>
                                </tr>
                            @endforeach


                            @php
                                if ($totalTrades) {
                                    foreach ($indicatorAverageRatio as &$avg) {
                                        $avg = round($avg / $totalTrades, 4);
                                    }
                                }
                            @endphp
                            <tr class="indicator-row"
                                style="position: sticky;bottom: -15px;backdrop-filter: blur(10px);background: rgb(39 41 61 / 33%);z-index: 999;">
                                <td>Averages:</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>



                                {{-- RSI Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioRsi'] }}
                                </td>

                                {{-- WR Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioWr'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioWrRsi'] }}
                                </td>
                                <td>&nbsp;</td>





                                {{-- Stoch D Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioStochD'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioStochDRsi'] }}
                                </td>
                                <td>&nbsp;</td>




                                {{-- Stoch K Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioStochK'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioStochKRsi'] }}
                                </td>
                                <td>&nbsp;</td>



                                {{--  Dif Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioDif'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioDifRsi'] }}
                                </td>

                                {{--  Dea Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>

                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioDea'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioDeaRsi'] }}
                                </td>


                                {{--  Histogram Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>

                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioHistogram'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioHistogramRsi'] }}
                                </td>


                                {{--  K Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>

                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioK'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioKRsi'] }}
                                </td>
                                <td>&nbsp;</td>


                                {{--  D Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>

                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioD'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioDRsi'] }}
                                </td>
                                <td>&nbsp;</td>

                                {{--  J Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>

                                <td>&nbsp;</td>
                                <td>&nbsp;</td>

                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioJ'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioJRsi'] }}
                                </td>
                                <td>&nbsp;</td>


                                {{--  Volume MA5 Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>

                                <td>&nbsp;</td>
                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioVolumeMA5'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioVolumeMA5Rsi'] }}
                                </td>

                                {{--  Volume MA10 Indicator --}}
                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td style="color: #fd9d5d !important;">
                                    {{ $indicatorAverageRatio['ratioVolumeMA10'] }}
                                </td>
                                <td style="color: #5d82fd !important;">
                                    {{ $indicatorAverageRatio['ratioVolumeMA10Rsi'] }}
                                </td>

                                <td><span style="width:50px;margin:5px 20px;"></span></td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
    <script>
        $(document).ready(function() {
            // Check if DataTables Buttons plugin is available
            if ($.fn.dataTable.Buttons) {
                // Initialize DataTable with export functionality
                var {{ $tableId }} = $('#{{ $tableId }}').DataTable({
                    "paging": true,
                    "ordering": false,
                    "info": false,
                    "searching": false
                });

                // Create a new DataTable Buttons instance
                new $.fn.dataTable.Buttons({{ $tableId }}, {
                    buttons: [{
                        extend: 'csv',
                        text: 'CSV',
                        filename: '{{ $tableType === 'p' ? 'Profitable' : 'Loss' }}_Trades_Report',
                        exportOptions: {
                            // Exclude the averages row from the export
                            rows: function(idx, data, node) {
                                return $(node).hasClass('indicator-row');
                            }
                        }
                    }]
                });

                // Add a hidden container for the export button
                $('<div id="exportButtonContainer" style="display:none;"></div>').insertAfter(
                    '#{{ $tableId }}');
                {{ $tableId }}.buttons().container().appendTo('#exportButtonContainer');

                // Custom button event handler
                $('#{{ $exportButtonId }}').on('click', function() {
                    {{ $tableId }}.button(0).trigger();
                });
            } else {
                // Fallback: Direct CSV generation if DataTables Buttons is not available
                $('#{{ $exportButtonId }}').on('click', function() {
                    // Prepare CSV content
                    var csvContent = [];
                    var headers = [];

                    // Get headers
                    $('#{{ $tableId }} thead th').each(function() {
                        headers.push('"' + $(this).text().trim() + '"');
                    });
                    csvContent.push(headers.join(','));

                    // Get data rows (only indicator rows)
                    $('#{{ $tableId }} tbody tr.indicator-row').each(function() {
                        var row = [];
                        $(this).find('td').each(function() {
                            row.push('"' + $(this).text().trim() + '"');
                        });
                        csvContent.push(row.join(','));
                    });

                    // Create and trigger download
                    var csvString = csvContent.join('\n');
                    var blob = new Blob([csvString], {
                        type: 'text/csv;charset=utf-8;'
                    });

                    // Create download link and click it
                    var link = document.createElement("a");
                    var url = URL.createObjectURL(blob);
                    link.setAttribute("href", url);
                    link.setAttribute("download",
                        "{{ $tableType === 'p' ? 'Profitable' : 'Loss' }}_Trades_Report.csv");
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
        })
    </script>
@endpush
