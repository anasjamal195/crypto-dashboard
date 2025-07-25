@extends('layouts.app')
@php
    use App\CommonHelpers;
    $totalProfit = 0;
    $totalTrades = 0;
    $percentageProgress = DB::table('formula_details')->where('formula', request('formula'))->first();
    $percentageProgress = $percentageProgress ? $percentageProgress->progress : 100;
    $bestPerformingSymbols = [];
    $lowPerformingSymbols = [];
    $bestPerformingSymbolsTradesTotal = 0;
    $lowPerformingSymbolsTradesTotal = 0;
    $tableKeys = CommonHelpers::$candleDataKeysCoinReports;

    $indicatorSumProfit = [];
    $indicatorSumLosses = [];

    $indicatorRatioCandleProfitSum = [];
@endphp

@section('content')
    <style>
        .text-success {
            color: #00f2c3 !important;
        }

        .text-danger {
            color: #fd5d93 !important;
        }

        .text-primary {
            color: #e14eca !important;
        }
    </style>
    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <div class="" style="display:flex;align-items:center;justify-content:space-between;">

                            <div>
                                <h4 class="card-title ">Internal Trades Report (Recent 1000 Candles)</h4>
                                <p class="card-category"> Here is a list of the latest trades across all coins.
                                    ({{ $percentageProgress }} % Completed)</p>
                            </div>

                            <div>
                                @if (request('formula'))
                                    <a href="{{ route('coinReport.delete', ['formula' => request('formula'), 'current_formula_only' => true]) }}"
                                        class="btn btn-primary my-2 mx-1">Delete Current</a>
                                @endif
                                <a href="{{ route('coinReport.delete', ['incomplete_only' => true]) }}"
                                    class="btn btn-warning my-2 mx-1">Delete Incomplete</a>
                                <a href="{{ route('coinReport.delete', ['delete_all' => true]) }}"
                                    class="btn btn-danger my-2 mx-1">Delete All</a>

                                @if (request('formula'))
                                    <a href="{{ route('coinReport.confirmed_trades', request('formula')) }}"
                                        class="btn btn-danger my-2 mx-1">View Confirmed Trades</a>
                                @endif

                            </div>


                        </div>
                        <div class="progress m-2" style="height: 5px; ">
                            <div class="progress-bar" role="progressbar"
                                style="width: {{ $percentageProgress }}%; background-color: #00f2c3;"
                                aria-valuenow="{{ $percentageProgress }}" aria-valuemin="0" aria-valuemax="100">

                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="title">Filters</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ url()->current() }}">
                                <div class="row">

                                    <div class="col-md-4 mb-3">
                                        <label for="formula">Filter by Formula</label>
                                        <select name="formula" id="formula" class="form-control select2">
                                            <option value="">All Formulas</option>
                                            @foreach (DB::table('formula_details')->distinct('formula')->orderBy('created_at', 'DESC')->get() as $formula)
                                                <option value="{{ $formula->formula }}"
                                                    {{ request('formula') == $formula->formula ? 'selected' : '' }}>
                                                    {{ $formula->formula }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="showTimeline">Trade Timeline</label>
                                        <select name="showTimeline" id="showTimeline" class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('showTimeline') == 'show' ? 'selected' : '' }}>Shown</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="showStopLossChart">Stop Loss Chart</label>
                                        <select name="showStopLossChart" id="showStopLossChart"
                                            class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('showStopLossChart') == 'show' ? 'selected' : '' }}>Shown
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="showSkippedTradesChart">Skipped Trades Chart</label>
                                        <select name="showSkippedTradesChart" id="showSkippedTradesChart"
                                            class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('showSkippedTradesChart') == 'show' ? 'selected' : '' }}>Shown
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="reportSummary">Show Report Summary</label>
                                        <select name="reportSummary" id="reportSummary" class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('reportSummary') == 'show' ? 'selected' : '' }}>Shown</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="profitableTradesSummary">Profitable Trades Summary</label>
                                        <select name="profitableTradesSummary" id="profitableTradesSummary"
                                            class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('profitableTradesSummary') == 'show' ? 'selected' : '' }}>Shown
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="lossTradesSummary">Loss Trades Summary</label>
                                        <select name="lossTradesSummary" id="lossTradesSummary"
                                            class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('lossTradesSummary') == 'show' ? 'selected' : '' }}>Shown
                                            </option>
                                        </select>
                                    </div>


                                    <div class="col-md-4 mb-3">
                                        <label for="profitableTradesCandleMovementSummary">Profitable Trades Candle Movement
                                            Summary</label>
                                        <select name="profitableTradesCandleMovementSummary"
                                            id="profitableTradesCandleMovementSummary" class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('profitableTradesCandleMovementSummary') == 'show' ? 'selected' : '' }}>
                                                Shown
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="lossTradesCandleMovementSummary">Loss Trades Candle Movement
                                            Summary</label>
                                        <select name="lossTradesCandleMovementSummary" id="lossTradesCandleMovementSummary"
                                            class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('lossTradesCandleMovementSummary') == 'show' ? 'selected' : '' }}>
                                                Shown
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="trendAnalysisChart">Trend Analysis Chart</label>
                                        <select name="trendAnalysisChart" id="trendAnalysisChart"
                                            class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('trendAnalysisChart') == 'show' ? 'selected' : '' }}>
                                                Shown
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="trendAnalysisActualChart">Trend Analysis Chart (Indicators)</label>
                                        <select name="trendAnalysisActualChart" id="trendAnalysisActualChart"
                                            class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('trendAnalysisActualChart') == 'show' ? 'selected' : '' }}>
                                                Shown
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="showSymbolPerformanceMetrics">Show Symbol Performance Metrics</label>
                                        <select name="showSymbolPerformanceMetrics" id="showSymbolPerformanceMetrics"
                                            class="form-control select2">
                                            <option value="">Hidden</option>
                                            <option value="show"
                                                {{ request('showSymbolPerformanceMetrics') == 'show' ? 'selected' : '' }}>
                                                Shown
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="position">Filter by Position</label>
                                        <select name="position" id="position" class="form-control select2">
                                            <option value="">All Positions</option>
                                            <option value="LONG" {{ request('position') == 'LONG' ? 'selected' : '' }}>
                                                LONG</option>
                                            <option value="SHORT" {{ request('position') == 'SHORT' ? 'selected' : '' }}>
                                                SHORT</option>
                                        </select>
                                    </div>


                                    <div class="col-md-4 mb-3">
                                        <label for="safe_mode_view">Show Safe Mode Reports</label>
                                        <select name="safe_mode_view" id="safe_mode_view" class="form-control select2">
                                            <option value="">Disable</option>
                                            <option value="show" {{ request('safe_mode_view') ? 'selected' : '' }}>
                                                Enable</option>
                                        </select>
                                    </div>



                                    <div class="col-md-8 d-flex align-items-end justify-content-end">
                                        <div class="form-group d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-round mr-2">Apply</button>
                                            <a href="{{ route('coinReport', ['market' => 'FUTURE', 'interval' => $interval]) }}"
                                                class="btn btn-secondary btn-round">Clear</a>
                                        </div>
                                    </div>

                                </div>
                            </form>
                        </div>
                    </div>



                    <div class="card-body">


                        @if (request('formula'))
                            <div class="">
                                <table class="table">
                                    <thead class="text-primary">
                                        <tr>
                                            <th>No</th>
                                            <th>Position</th>
                                            <th>Symbol</th>
                                            <th>Total Duration (min)</th>
                                            <th>Average Duration (min)</th>
                                            <th>Total Trades</th>
                                            <th>Accuracy</th>
                                            <th>Total Profit (%)</th>
                                            <th>Average Profit (%)</th>
                                            <th>Max Profit (%)</th>
                                            <th>Min Profit (%)</th>
                                            {{-- <th>Max Lowest Price (%)</th>
                                            <th>Min Lowest Price (%)</th> --}}
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
                                                $symbolAccuracy =
                                                    (($trade->total_entries - $trade->number_of_sl) /
                                                        $trade->total_entries) *
                                                    100;

                                                if ($symbolAccuracy >= $accuracyThreshold) {
                                                    $bestPerformingSymbols[$trade->symbol] = [
                                                        'accuracy' => $symbolAccuracy,
                                                        'number_of_trades' => $trade->total_entries,
                                                    ];
                                                    $bestPerformingSymbolsTradesTotal += $trade->total_entries;
                                                }

                                                if ($symbolAccuracy <= $accuracyThresholdLow) {
                                                    $lowPerformingSymbols[$trade->symbol] = [
                                                        'accuracy' => $symbolAccuracy,
                                                        'number_of_trades' => $trade->total_entries,
                                                    ];
                                                    $lowPerformingSymbolsTradesTotal += $trade->total_entries;
                                                }
                                            @endphp
                                            <tr @if ($trade->min_profit < 0) class="bg-danger" @endif>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $trade->position }}</td>
                                                <td>{{ $trade->symbol }}</td>
                                                <td>{{ $trade->total_duration }}</td>
                                                <td>{{ number_format($trade->average_duration, 2) }}</td>
                                                <td>{{ $trade->total_entries }} / {{ $trade->number_of_sl }}</td>
                                                <td>{{ number_format($symbolAccuracy, 2) }} % </td>
                                                <td>{{ number_format($trade->total_profit, 2) }} % </td>
                                                <td>{{ number_format($trade->average_profit, 2) }} %</td>
                                                <td>{{ number_format($trade->max_profit, 2) }} %</td>
                                                <td>{{ number_format($trade->min_profit, 2) }} %</td>
                                                {{-- <td>{{ number_format($trade->max_lowestPrice, 2) }} %</td>
                                                <td>{{ number_format($trade->min_lowestPrice, 2) }} %</td> --}}
                                                <td>{{ $trade->formula }}</td>
                                                <td>{{ \Carbon\Carbon::parse($trade->last_updated)->timezone('Asia/Karachi')->format('h:i A') }}
                                                </td>
                                                <td>
                                                    <a target="_blank"
                                                        href="{{ route('coinReportDetails', ['market' => $market, 'symbol' => $trade->symbol, 'position' => $trade->position, 'formula' => $trade->formula, 'stopLoss' => request('stopLoss'), 'interval' => $interval]) }}"
                                                        class="btn btn-info btn-sm">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <!-- Stats Summary Table -->
                                <div class="mt-4">
                                    <h5 class="text-primary">Trading Performance Summary</h5>
                                    <div class="">
                                        <table class="table table-bordered table-stats">
                                            <thead class="bg-dark text-white">
                                                <tr>
                                                    <th>Metric</th>
                                                    <th>Value</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="font-weight-bold">Below TP</td>
                                                    <td>{{ round($tradesBelowTP ?? 0) }}</td>
                                                    <td>Trades that closed early below {{ $tpLimit }}%</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">1h+ Duration</td>
                                                    <td>{{ round($tradesAbove1h ?? 0) }}</td>
                                                    <td>Trades are above one hour</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">1h+ Duration Profit</td>
                                                    <td>{{ round($tradesAbove1hProfit ?? 0) }}</td>
                                                    <td>Trades are above one hour that closed in profit</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">1h+ Duration Loss</td>
                                                    <td>{{ round($tradesAbove1hLoss ?? 0) }}</td>
                                                    <td>Trades are above one hour that closed in Loss</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Max Trades at a time</td>
                                                    <td>{{ round($maxNearbyTrades?->entry_count) }}</td>
                                                    <td>at {{ $maxNearbyTrades?->time_interval }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Average Duration</td>
                                                    <td>{{ round($averageDuration) }} min</td>
                                                    <td>Average time a trade is active</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total Profit</td>
                                                    <td>{{ $profitsTotal }} %</td>
                                                    <td>From {{ $profitableTrades }} profitable trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total Stop Losses</td>
                                                    <td>{{ $stopLossesTotal }} %</td>
                                                    <td>From {{ $stopLossesTrades }} stop loss trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Grand Total</td>
                                                    <td>{{ $profitsTotal - $stopLossesTotal }} %</td>
                                                    <td>From {{ $totalTrades }} total trades</td>
                                                </tr>

                                                <tr>
                                                    <td class="font-weight-bold">Fee Estimate</td>
                                                    <td>{{ $totalTrades ? round($totalTrades * 0.15, 2) : 0 }}
                                                        %</td>
                                                    <td>Average Estimated fee </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Grand Total (after fee deduction)</td>
                                                    <td>{{ $profitsTotal - $stopLossesTotal - $totalTrades * 0.15 }} %
                                                    </td>
                                                    <td>From {{ $totalTrades }} total trades after fee deduction</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Formula Accuracy</td>
                                                    <td>{{ $totalTrades ? round(100 - ($stopLossesTrades / $totalTrades) * 100, 2) : 0 }}
                                                        %</td>
                                                    <td>Success rate of profitable trades </td>
                                                </tr>









                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">SR Stats</td>
                                                </tr>

                                                <tr>
                                                    <td class="font-weight-bold">Total Profit</td>
                                                    <td>{{ $totalProfitsSR }} %</td>
                                                    <td>From {{ $totalProfitsTradesSR }} profitable trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total Stop Losses</td>
                                                    <td>{{ $totalLossesSR }} %</td>
                                                    <td>From {{ $totalLossesTradesSR }} stop loss trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Grand Total</td>
                                                    <td>{{ $totalProfitsSR - $totalLossesSR }} %</td>
                                                    <td>From {{ $totalTradesSR }} total trades</td>
                                                </tr>

                                                <tr>
                                                    <td class="font-weight-bold">Fee Estimate</td>
                                                    <td>{{ $totalTradesSR ? round($totalTradesSR * 0.15, 2) : 0 }}
                                                        %</td>
                                                    <td>Average Estimated fee </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Grand Total (after fee deduction)</td>
                                                    <td>{{ $totalProfitsSR - $totalLossesSR - $totalTradesSR * 0.15 }} %
                                                    </td>
                                                    <td>From {{ $totalTradesSR }} total trades after fee deduction</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Formula Accuracy</td>
                                                    <td>{{ $totalTradesSR ? round(100 - ($totalLossesTradesSR / $totalTradesSR) * 100, 2) : 0 }}
                                                        %</td>
                                                    <td>Success rate of profitable trades </td>
                                                </tr>

                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>



                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">MACD Stats
                                                    </td>
                                                </tr>

                                                <tr>
                                                    <td class="font-weight-bold">Total Profit</td>
                                                    <td>{{ $totalProfitsMACD }} %</td>
                                                    <td>From {{ $totalProfitsTradesMACD }} profitable trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total Stop Losses</td>
                                                    <td>{{ $totalLossesMACD }} %</td>
                                                    <td>From {{ $totalLossesTradesMACD }} stop loss trades</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Grand Total</td>
                                                    <td>{{ $totalProfitsMACD - $totalLossesMACD }} %</td>
                                                    <td>From {{ $totalTradesMACD }} total trades</td>
                                                </tr>

                                                <tr>
                                                    <td class="font-weight-bold">Fee Estimate</td>
                                                    <td>{{ $totalTradesMACD ? round($totalTradesMACD * 0.15, 2) : 0 }}
                                                        %</td>
                                                    <td>Average Estimated fee </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Grand Total (after fee deduction)</td>
                                                    <td>{{ $totalProfitsMACD - $totalLossesMACD - $totalTradesMACD * 0.15 }}
                                                        %
                                                    </td>
                                                    <td>From {{ $totalTradesMACD }} total trades after fee deduction</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Formula Accuracy</td>
                                                    <td>{{ $totalTradesMACD ? round(100 - ($totalLossesTradesMACD / $totalTradesMACD) * 100, 2) : 0 }}
                                                        %</td>
                                                    <td>Success rate of profitable trades </td>
                                                </tr>

                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>
















                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Early Closed</td>
                                                    <td>{{ $earlyClosedTotal }}</td>
                                                    <td>Trades that closed early after 12-candles</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>

                                                <tr>
                                                    <td class="font-weight-bold">Currently Open Symbols</td>
                                                    <td colspan="2">
                                                        @foreach ($openSymbols as $openSymbol)
                                                            <span class="badge bg-primary">{{ $openSymbol }}</span>
                                                        @endforeach
                                                    </td>

                                                </tr>

                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>

                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">RSI Above
                                                        {{ $rsiLimit }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Profitable</td>
                                                    <td>{{ $rsiAbove40Profitable }}</td>
                                                    <td>Trades having rsi above {{ $rsiLimit }} and profitable </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Loss</td>
                                                    <td>{{ $rsiAbove40Loss }}</td>
                                                    <td>Trades having rsi above {{ $rsiLimit }} and Loss </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total</td>
                                                    <td>{{ $rsiAbove40Total }}
                                                        ({{ round(($rsiAbove40Profitable / max(0.000001, $rsiAbove40Total)) * 100) }}%)
                                                    </td>
                                                    <td>Total trades having rsi above {{ $rsiLimit }} </td>
                                                </tr>


                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">RSI Below and
                                                        equal {{ $rsiLimit }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Profitable</td>
                                                    <td>{{ $rsiBelow40Profitable }}</td>
                                                    <td>Trades having rsi Below {{ $rsiLimit }} and profitable </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Loss</td>
                                                    <td>{{ $rsiBelow40Loss }}</td>
                                                    <td>Trades having rsi Below {{ $rsiLimit }} and Loss </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total</td>
                                                    <td>{{ $rsiBelow40Total }}
                                                        ({{ round(($rsiBelow40Profitable / max(0.000001, $rsiBelow40Total)) * 100) }}%)
                                                    </td>
                                                    <td>Total trades having rsi Below {{ $rsiLimit }} </td>
                                                </tr>


                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">Opening on
                                                        Bullish Candle</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Profitable</td>
                                                    <td>{{ $bullishOpeningsProfit }}</td>
                                                    <td>Trades opened on bullish candles that closed in Profit </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Loss</td>
                                                    <td>{{ $bullishOpeningsLoss }}</td>
                                                    <td>Trades opened on bullish candles that closed in Loss </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total</td>
                                                    <td>{{ $bullishOpenings }}
                                                        ({{ round(($bullishOpeningsProfit / max(0.000001, $bullishOpenings)) * 100) }}%)
                                                    </td>
                                                    <td>Total trades openend on bullish candle </td>
                                                </tr>


                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">&nbsp;</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="font-weight-bold text-center">Opening on
                                                        Berish Candle</td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Profitable</td>
                                                    <td>{{ $berishOpeningsProfit }}</td>
                                                    <td>Trades opened on berish candles that closed in Profit </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Loss</td>
                                                    <td>{{ $berishOpeningsLoss }}</td>
                                                    <td>Trades opened on berish candles that closed in Loss </td>
                                                </tr>
                                                <tr>
                                                    <td class="font-weight-bold">Total</td>
                                                    <td>{{ $berishOpenings }}
                                                        ({{ round(($berishOpeningsProfit / max(0.000001, $berishOpenings)) * 100) }}%)
                                                    </td>
                                                    <td>Total trades openend on berish candle </td>
                                                </tr>




                                                @if (request('showSymbolPerformanceMetrics') === 'show')
                                                    <tr>
                                                        <td colspan="3" class="font-weight-bold text-center">Symbols
                                                            above
                                                            {{ $accuracyThreshold }} % accuracy</td>
                                                    </tr>
                                                    @foreach ($bestPerformingSymbols as $symbol => $data)
                                                        <tr>
                                                            <td class="font-weight-bold">{{ $symbol }}</td>
                                                            <td>{{ $data['accuracy'] }} % (
                                                                {{ $data['number_of_trades'] }} )
                                                            </td>
                                                            <td>
                                                                @php
                                                                    $coinDetails = DB::table('coins')
                                                                        ->where('symbol', $symbol)
                                                                        ->first();

                                                                @endphp

                                                                <div class="coin-tags">
                                                                    <span class="badge me-1">Primary Classification:
                                                                    </span>
                                                                    {{-- Primary Classification --}}
                                                                    @if (!empty($coinDetails->classification))
                                                                        <span
                                                                            class="badge bg-primary me-1">{{ $coinDetails->classification }}</span>
                                                                    @endif
                                                                    <span class="badge me-1">Other tags: </span>
                                                                    {{-- Other Classifications --}}
                                                                    @if ($coinDetails->is_web3)
                                                                        <span class="badge bg-info me-1">Web 3</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_metaverse)
                                                                        <span class="badge bg-info me-1">Metaverse</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_defi)
                                                                        <span class="badge bg-info me-1">Defi</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_nft)
                                                                        <span class="badge bg-info me-1">NFT</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_altcoin)
                                                                        <span class="badge bg-info me-1">ALT</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_meme_coin)
                                                                        <span class="badge bg-info me-1">Meme</span>
                                                                    @endif
                                                                </div>

                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                    <tr>
                                                        <td>Total</td>
                                                        <td>{{ $bestPerformingSymbolsTradesTotal }}</td>
                                                        <td>
                                                            @php
                                                                $keysArray = array_keys($bestPerformingSymbols);

                                                                $phpFormatted = ' [' . PHP_EOL;
                                                                foreach ($keysArray as $key) {
                                                                    $phpFormatted .=
                                                                        '    "' . addslashes($key) . '",' . PHP_EOL;
                                                                }
                                                                $phpFormatted .= ']';
                                                            @endphp

                                                            <button
                                                                onclick="navigator.clipboard.writeText(`{{ addslashes($phpFormatted) }}`).then(() => displayToast('success','Array copied to clipboard'))"
                                                                style="padding: 6px 14px; background-color: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                                                Copy PHP Array
                                                            </button>

                                                        </td>
                                                    </tr>
                                                @endif



                                                @if (request('showSymbolPerformanceMetrics') === 'show')
                                                    <tr>
                                                        <td colspan="3" class="font-weight-bold text-center">Symbols
                                                            below
                                                            {{ $accuracyThresholdLow }} % accuracy</td>
                                                    </tr>
                                                    @foreach ($lowPerformingSymbols as $symbol => $data)
                                                        <tr>
                                                            <td class="font-weight-bold">{{ $symbol }}</td>
                                                            <td>{{ $data['accuracy'] }} % (
                                                                {{ $data['number_of_trades'] }} )
                                                            </td>
                                                            <td>
                                                                @php
                                                                    $coinDetails = DB::table('coins')
                                                                        ->where('symbol', $symbol)
                                                                        ->first();

                                                                @endphp

                                                                <div class="coin-tags">
                                                                    <span class="badge me-1">Primary Classification:
                                                                    </span>
                                                                    {{-- Primary Classification --}}
                                                                    @if (!empty($coinDetails->classification))
                                                                        <span
                                                                            class="badge bg-primary me-1">{{ $coinDetails->classification }}</span>
                                                                    @endif
                                                                    <span class="badge me-1">Other tags: </span>
                                                                    {{-- Other Classifications --}}
                                                                    @if ($coinDetails->is_web3)
                                                                        <span class="badge bg-info me-1">Web 3</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_metaverse)
                                                                        <span class="badge bg-info me-1">Metaverse</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_defi)
                                                                        <span class="badge bg-info me-1">Defi</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_nft)
                                                                        <span class="badge bg-info me-1">NFT</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_altcoin)
                                                                        <span class="badge bg-info me-1">ALT</span>
                                                                    @endif

                                                                    @if ($coinDetails->is_meme_coin)
                                                                        <span class="badge bg-info me-1">Meme</span>
                                                                    @endif
                                                                </div>

                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                    <tr>
                                                        <td>Total</td>
                                                        <td>{{ $lowPerformingSymbolsTradesTotal }}</td>
                                                        <td>
                                                            @php
                                                                $keysArray = array_keys($bestPerformingSymbols);

                                                                $phpFormatted = ' [' . PHP_EOL;
                                                                foreach ($keysArray as $key) {
                                                                    $phpFormatted .=
                                                                        '    "' . addslashes($key) . '",' . PHP_EOL;
                                                                }
                                                                $phpFormatted .= ']';
                                                            @endphp

                                                            <button
                                                                onclick="navigator.clipboard.writeText(`{{ addslashes($phpFormatted) }}`).then(() => displayToast('success','Array copied to clipboard'))"
                                                                style="padding: 6px 14px; background-color: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                                                Copy PHP Array
                                                            </button>

                                                        </td>
                                                    </tr>
                                                @endif



                                                {{-- Testing Section --}}
                                                {{-- <tr>
                                                    @php
                                                      
                                                        $profitsRaw = DB::table('coin_reports')
                                                            ->select('symbol', 'interval', 'market', 'profit')
                                                            ->distinct()
                                                            ->whereRaw('profit > 0');


                                                        if (request()->filled('position')) {
                                                            $profitsRaw->where('position', request()->position);
                                                        }

                                                        if (request()->filled('formula')) {
                                                            $profitsRaw->where('formula', request()->formula);
                                                        }
                                                        $profitsRaw = $profitsRaw->get();
                                                       
                                                    @endphp

                                                    <td class="font-weight-bold">Profits Raw </td>
                                                    <td>{{ count($profitsRaw) }} {{$profitsRaw->sum('profit')}}</td>
                                                    <td>
                                                        @foreach ($profitsRaw as $index => $trade)
                                                            {{ $trade->profit }}{{ $index != count($profitsRaw) - 1 ? '+' : '' }}
                                                        @endforeach
                                                    </td>
                                                </tr>


                                                <tr>
                                                    @php
                                                        $lossesRaw = DB::table('coin_reports')
                                                            ->select('symbol', 'interval', 'market', 'profit')
                                                            ->distinct()
                                                            ->whereRaw('profit < 0')

                                                            ->select('profit');

                                                        if (request()->filled('position')) {
                                                            $lossesRaw->where('position', request()->input('position'));
                                                        }

                                                        if (request()->filled('formula')) {
                                                            $lossesRaw->where('formula', request()->input('formula'));
                                                        }
                                                        $lossesRaw = $lossesRaw->get();
                                                    @endphp
                                                    <td class="font-weight-bold">Losses Raw</td>
                                                    <td>{{ count($lossesRaw) }}</td>

                                                    <td>



                                                        @foreach ($lossesRaw as $index => $trade)
                                                            {{ abs($trade->profit) }}{{ $index != count($lossesRaw) - 1 ? '+' : '' }}
                                                        @endforeach
                                                    </td>
                                                </tr> --}}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="card-category text-center">Please Select any formula to show results...</p>
                        @endif
                    </div>
                </div>
                @if (request()->get('formula'))
                    @php
                        $formulaDetails = DB::table('formula_details')->where('formula', request('formula'))->first();
                    @endphp



                    {!! $formulaDetails ? $formulaDetails->details : '<p>No details available for the selected formula.</p>' !!}
                @endif
                @if (request('showTimeline') === 'show')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Trade Timeline</h4>
                                    <p class="card-category">Visual representation of trade timelines</p>
                                    <div class="row">
                                        @foreach ($timelineColors as $tag => $directions)
                                            <div class="col-md-4">
                                                <h6 class="text-white mt-2">{{ strtoupper($tag) }}</h6>
                                                @foreach ($directions as $direction => $color)
                                                    <span class="badge badge-pill mb-1"
                                                        style="background-color: {{ $color }}; color: white;">
                                                        {{ strtoupper($direction) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>


                                <div class="card-body chart-container">

                                    <canvas id="timelineChart"></canvas>
                                </div>

                            </div>
                        </div>
                    </div>
                @endif
                @if (request('showStopLossChart') === 'show')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Stop Losses Chart</h4>
                                    <p class="card-category">Visual representation of Stop Losses timelines</p>

                                </div>


                                <div class="card-body chart-container">

                                    <canvas id="stopLossChart"></canvas>
                                </div>

                            </div>
                        </div>
                    </div>
                @endif
                @if (request('showSkippedTradesChart') === 'show')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Skipped Trades Chart</h4>
                                    <p class="card-category">Visual representation of Skipped trades timelines</p>

                                </div>


                                <div class="card-body chart-container">

                                    <canvas id="skippedTradesChart"></canvas>
                                </div>

                            </div>
                        </div>
                    </div>
                @endif

                @if (request('trendAnalysisChart') === 'show')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Trend Analysis</h4>
                                    <p class="card-category">Visual representation of Trend</p>
                                    <p class="card-category">Reference Symbol: {{ $trendReferenceSymbol }}</p>
                                    <p class="card-category">Reference Interval: {{ $trendReferenceInterval }}</p>

                                </div>


                                <div class="card-body chart-container">

                                    <canvas id="trendChart"></canvas>
                                </div>

                            </div>
                        </div>
                    </div>
                @endif
                @if (request('trendAnalysisActualChart') === 'show')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">Trend Analysis on same interval</h4>
                                    <p class="card-category">Visual representation of Trend</p>
                                    <p class="card-category">Reference Symbol: {{ $trendReferenceSymbolActual }}</p>
                                    <p class="card-category">Reference Interval: {{ $trendReferenceIntervalActual }}</p>

                                </div>


                                <div class="card-body chart-container">

                                    <canvas id="trendChartActual"></canvas>
                                </div>

                            </div>
                        </div>
                    </div>
                @endif

                @if (request('profitableTradesSummary') === 'show')
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title text-success">Profitable Trades Table</h4>
                                    <p class="card-category ">{{ $reportAnalysis['profitable_trades'] }} profitable</p>
                                    <!-- Export Button -->
                                    <button id="exportProfitableCSV" class="btn btn-sm btn-primary float-right">
                                        <i class="fa fa-download"></i> Export CSV
                                    </button>
                                </div>
                                <div class="card-body" style="max-height: 500px;overflow-y: auto;">
                                    <div class="">
                                        <table id="profitableTradesTable" class="table tablesorter">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Sr.</th>
                                                    <th>Symbol</th>
                                                    <th>Duration</th>

                                                    @foreach ($tableKeys as $heading)
                                                        <th>{{ $heading }}</th>
                                                    @endforeach
                                                    <th>P%</th>
                                                    <th>Position</th>

                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $count = 0;
                                                @endphp
                                                @foreach ($tradeArr as $trade)
                                                    @php
                                                        $openingCandle = json_decode($trade['buyingCandle'], true);
                                                        $previousCandle = json_decode($trade['previousCandle'], true);

                                                        if ($trade['profit'] < 0) {
                                                            continue;
                                                        }
                                                        $count++;
                                                        if (isset($indicatorSumProfit['profit'])) {
                                                            $indicatorSumProfit['profit'] += $trade['profit'];
                                                        } else {
                                                            $indicatorSumProfit['profit'] = $trade['profit'];
                                                        }
                                                    @endphp
                                                    <tr class="indicator-row">
                                                        <td>{{ $count }} {{ $previousCandle ? '*' : '' }}</td>
                                                        <td>{{ $trade['symbol'] }}</td>
                                                        <td>{{ $trade['duration'] }}</td>

                                                        @foreach ($tableKeys as $key => $heading)
                                                            @php
                                                                if (is_numeric($openingCandle[$key])) {
                                                                    if ($previousCandle) {
                                                                        $perDiff = CommonHelpers::getPercentDiff(
                                                                            $previousCandle[$key],
                                                                            $openingCandle[$key],
                                                                            true,
                                                                        );
                                                                        if (isset($indicatorSumProfit[$key])) {
                                                                            $indicatorSumProfit[$key] += $perDiff;
                                                                        } else {
                                                                            $indicatorSumProfit[$key] = $perDiff;
                                                                        }
                                                                    } else {
                                                                        if (isset($indicatorSumProfit[$key])) {
                                                                            $indicatorSumProfit[$key] +=
                                                                                $openingCandle[$key];
                                                                        } else {
                                                                            $indicatorSumProfit[$key] =
                                                                                $openingCandle[$key];
                                                                        }
                                                                    }
                                                                }
                                                            @endphp
                                                            <td>
                                                                @if ($previousCandle)
                                                                    {{ is_numeric($openingCandle[$key]) ? number_format($perDiff, 5) : $openingCandle[$key] }}
                                                                @else
                                                                    {{ is_numeric($openingCandle[$key]) ? number_format($openingCandle[$key], 5) : $openingCandle[$key] }}
                                                                @endif

                                                            </td>
                                                        @endforeach
                                                        <td>{{ $trade['profit'] }}</td>
                                                        <td>{{ $trade['position'] }}</td>
                                                    </tr>
                                                @endforeach
                                                <tr>
                                                    <td>&nbsp;</td>
                                                    <td>Averages</td>
                                                    <td>&nbsp;</td>
                                                    @foreach ($tableKeys as $key => $value)
                                                        @if (is_numeric($openingCandle[$key]))
                                                            <td>
                                                                {{ $reportAnalysis['profitable_trades'] ? number_format($indicatorSumProfit[$key] / $reportAnalysis['profitable_trades'], 5) : 0 }}
                                                            </td>
                                                        @else
                                                            <td>&nbsp;</td>
                                                        @endif
                                                    @endforeach
                                                    <td>
                                                        {{ $reportAnalysis['profitable_trades'] ? number_format($indicatorSumProfit['profit'] / $reportAnalysis['profitable_trades'], 5) : 0 }}
                                                    </td>
                                                    <td>&nbsp;</td>

                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif


                @if (request('lossTradesSummary') === 'show')
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title text-danger">Losses Trades Table</h4>
                                    <p class="card-category ">{{ $reportAnalysis['loss_trades'] }} losses</p>
                                    <!-- Export Button -->
                                    <button id="exportLossesCSV" class="btn btn-sm btn-primary float-right">
                                        <i class="fa fa-download"></i> Export CSV
                                    </button>
                                </div>
                                <div class="card-body" style="max-height: 500px;overflow-y: auto;">
                                    <div class="">
                                        <table id="lossTradesTable" class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Sr.</th>
                                                    <th>Symbol</th>
                                                    <th>Duration</th>

                                                    @foreach ($tableKeys as $heading)
                                                        <th>{{ $heading }}</th>
                                                    @endforeach

                                                    <th>L%</th>
                                                    <th>Position</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $count = 0;
                                                @endphp
                                                @foreach ($tradeArr as $trade)
                                                    @php
                                                        $openingCandle = json_decode($trade['buyingCandle'], true);
                                                        $previousCandle = json_decode($trade['previousCandle'], true);

                                                        if ($trade['profit'] > 0) {
                                                            continue;
                                                        }
                                                        $count++;

                                                        if (isset($indicatorSumLosses['profit'])) {
                                                            $indicatorSumLosses['profit'] += $trade['profit'];
                                                        } else {
                                                            $indicatorSumLosses['profit'] = $trade['profit'];
                                                        }

                                                    @endphp
                                                    <tr class="indicator-row">
                                                        <td>{{ $count }} {{ $previousCandle ? '*' : '' }}</td>
                                                        <td>{{ $trade['symbol'] }}</td>
                                                        <td>{{ $trade['duration'] }}</td>


                                                        @foreach ($tableKeys as $key => $heading)
                                                            @php
                                                                if (is_numeric($openingCandle[$key])) {
                                                                    if ($previousCandle) {
                                                                        $perDiff = CommonHelpers::getPercentDiff(
                                                                            $previousCandle[$key],
                                                                            $openingCandle[$key],
                                                                            true,
                                                                        );
                                                                        if (isset($indicatorSumLosses[$key])) {
                                                                            $indicatorSumLosses[$key] += $perDiff;
                                                                        } else {
                                                                            $indicatorSumLosses[$key] = $perDiff;
                                                                        }
                                                                    } else {
                                                                        if (isset($indicatorSumLosses[$key])) {
                                                                            $indicatorSumLosses[$key] +=
                                                                                $openingCandle[$key];
                                                                        } else {
                                                                            $indicatorSumLosses[$key] =
                                                                                $openingCandle[$key];
                                                                        }
                                                                    }
                                                                }
                                                            @endphp
                                                            <td>
                                                                @if ($previousCandle)
                                                                    {{ is_numeric($openingCandle[$key]) ? number_format($perDiff, 5) : $openingCandle[$key] }}
                                                                @else
                                                                    {{ is_numeric($openingCandle[$key]) ? number_format($openingCandle[$key], 5) : $openingCandle[$key] }}
                                                                @endif

                                                            </td>
                                                        @endforeach
                                                        <td>{{ $trade['profit'] }}</td>
                                                        <td>{{ $trade['position'] }}</td>

                                                    </tr>
                                                @endforeach

                                                <tr>
                                                    <td>&nbsp;</td>
                                                    <td>Averages</td>
                                                    <td>&nbsp;</td>
                                                    @foreach ($tableKeys as $key => $value)
                                                        @if (is_numeric($openingCandle[$key]))
                                                            <td>
                                                                {{ $reportAnalysis['loss_trades'] ? number_format($indicatorSumLosses[$key] / $reportAnalysis['loss_trades'], 5) : 0 }}
                                                            </td>
                                                        @else
                                                            <td>&nbsp;</td>
                                                        @endif
                                                    @endforeach
                                                    <td>
                                                        {{ $reportAnalysis['loss_trades'] ? number_format($indicatorSumLosses['profit'] / $reportAnalysis['loss_trades'], 5) : 0 }}
                                                    </td>
                                                    <td>&nbsp;</td>

                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif




                {{-- Candle Movements table --}}

                @if (request('profitableTradesCandleMovementSummary') === 'show')
                    @php
                        $tableType = 'p';
                    @endphp
                    @include(
                        'CoinReports.parts.candle-movement-table',
                        compact('tradeArr', 'reportAnalysis', 'tableType'))
                @endif


                @if (request('lossTradesCandleMovementSummary') === 'show')
                    @php
                        $tableType = 'l';
                    @endphp
                    @include(
                        'CoinReports.parts.candle-movement-table',
                        compact('tradeArr', 'reportAnalysis', 'tableType'))
                @endif





                @if (request('reportSummary') === 'show')
                    <!-- Trading Report Analysis Table Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Trading Performance Analysis</h4>
                                    <p class="card-category">{{ $reportAnalysis['profitable_trades'] }} profitable vs
                                        {{ $reportAnalysis['loss_trades'] }} losing trades</p>
                                </div>
                                <div class="card-body">
                                    <div class="">
                                        <table class="table tablesorter">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Indicator</th>
                                                    <th>Profitable Avg</th>
                                                    <th>Loss Avg</th>
                                                    <th>Difference</th>
                                                    <th>Suggestion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($reportAnalysis['indicator_comparisons'] as $comparison)
                                                    <tr class="indicator-row">
                                                        <td>{{ ucfirst($comparison['indicator']) }}</td>
                                                        <td class="text-success">
                                                            {{ number_format($comparison['profitable_avg'], 2) }}</td>
                                                        <td class="text-danger">
                                                            {{ number_format($comparison['loss_avg'], 2) }}</td>
                                                        <td>{{ number_format($comparison['difference'], 2) }}</td>
                                                        <td>
                                                            <span
                                                                class="badge suggestion-badge">{{ $comparison['suggestion'] }}</span>
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




                    <!-- Technical Indicators Analysis -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Technical Indicators - Profitable Trades</h4>
                                    <p class="card-category">Average values for
                                        {{ $reportAnalysis['profitable_trades'] }}
                                        winning trades</p>
                                </div>
                                <div class="card-body">
                                    <div class="">
                                        <table class="table tablesorter">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Indicator</th>
                                                    <th>Average</th>
                                                    <th>Std Dev</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($reportAnalysis['profitable_stats'] as $indicator => $stats)
                                                    @if (
                                                        !in_array($indicator, [
                                                            'volume',
                                                            'volumeMA5',
                                                            'volumeMA10',
                                                            'obv',
                                                            'cvd',
                                                            'vwap',
                                                            'bb_upper',
                                                            'bb_lower',
                                                            'sar',
                                                            'ema12',
                                                            'ema26',
                                                        ]))
                                                        <tr>
                                                            <td>{{ ucfirst(str_replace('_', ' ', $indicator)) }}</td>
                                                            <td>{{ number_format($stats['avg'], 2) }}</td>
                                                            <td>{{ number_format($stats['std_dev'], 2) }}</td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Technical Indicators - Loss Trades</h4>
                                    <p class="card-category">Average values for {{ $reportAnalysis['loss_trades'] }}
                                        losing trades</p>
                                </div>
                                <div class="card-body">
                                    <div class="">
                                        <table class="table tablesorter">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Indicator</th>
                                                    <th>Average</th>
                                                    <th>Std Dev</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($reportAnalysis['loss_stats'] as $indicator => $stats)
                                                    @if (
                                                        !in_array($indicator, [
                                                            'volume',
                                                            'volumeMA5',
                                                            'volumeMA10',
                                                            'obv',
                                                            'cvd',
                                                            'vwap',
                                                            'bb_upper',
                                                            'bb_lower',
                                                            'sar',
                                                            'ema12',
                                                            'ema26',
                                                        ]))
                                                        <tr>
                                                            <td>{{ ucfirst(str_replace('_', ' ', $indicator)) }}</td>
                                                            <td>{{ number_format($stats['avg'], 2) }}</td>
                                                            <td>{{ number_format($stats['std_dev'], 2) }}</td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Volume Metrics Analysis -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Volume Metrics Analysis</h4>
                                    <p class="card-category">Comparison of volume indicators between profitable and losing
                                        trades</p>
                                </div>
                                <div class="card-body">
                                    <div class="">
                                        <table class="table tablesorter">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Volume Indicator</th>
                                                    <th>Profitable Average</th>
                                                    <th>Loss Average</th>
                                                    <th>Difference</th>
                                                    <th>Ratio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $volumeIndicators = [
                                                        'volume',
                                                        'volumeMA5',
                                                        'volumeMA10',
                                                        'obv',
                                                        'cvd',
                                                    ];
                                                @endphp

                                                @foreach ($volumeIndicators as $indicator)
                                                    <tr>
                                                        <td>{{ ucfirst(str_replace('MA', ' MA ', $indicator)) }}</td>
                                                        <td class="text-success">
                                                            {{ number_format($reportAnalysis['profitable_stats'][$indicator]['avg'], 0) }}
                                                        </td>
                                                        <td class="text-danger">
                                                            {{ number_format($reportAnalysis['loss_stats'][$indicator]['avg'], 0) }}
                                                        </td>
                                                        <td>{{ number_format($reportAnalysis['profitable_stats'][$indicator]['avg'] - $reportAnalysis['loss_stats'][$indicator]['avg'], 0) }}
                                                        </td>
                                                        <td>{{ number_format($reportAnalysis['profitable_stats'][$indicator]['avg'] / max(1, $reportAnalysis['loss_stats'][$indicator]['avg']), 2) }}x
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

                    <!-- Price Metrics Analysis -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Price-Based Indicators</h4>
                                    <p class="card-category">Comparison of price indicators between profitable and losing
                                        trades</p>
                                </div>
                                <div class="card-body">
                                    <div class="">
                                        <table class="table tablesorter">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Price Indicator</th>
                                                    <th>Profitable Average</th>
                                                    <th>Loss Average</th>
                                                    <th>Difference</th>
                                                    <th>Ratio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $priceIndicators = [
                                                        'vwap',
                                                        'bb_upper',
                                                        'bb_lower',
                                                        'sar',
                                                        'ema12',
                                                        'ema26',
                                                    ];
                                                @endphp

                                                @foreach ($priceIndicators as $indicator)
                                                    <tr>
                                                        <td>{{ ucfirst(str_replace('_', ' ', $indicator)) }}</td>
                                                        <td class="text-success">
                                                            {{ number_format($reportAnalysis['profitable_stats'][$indicator]['avg'], 2) }}
                                                        </td>
                                                        <td class="text-danger">
                                                            {{ number_format($reportAnalysis['loss_stats'][$indicator]['avg'], 2) }}
                                                        </td>
                                                        <td>{{ number_format($reportAnalysis['profitable_stats'][$indicator]['avg'] - $reportAnalysis['loss_stats'][$indicator]['avg'], 2) }}
                                                        </td>
                                                        <td>{{ number_format($reportAnalysis['profitable_stats'][$indicator]['avg'] / max(0.01, $reportAnalysis['loss_stats'][$indicator]['avg']), 2) }}x
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

                    <!-- Key Suggestions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Trading Improvement Suggestions</h4>
                                    <p class="card-category">Based on {{ $reportAnalysis['total_trades'] }} analyzed
                                        trades</p>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group bg-transparent">
                                        @foreach ($reportAnalysis['indicator_comparisons'] as $index => $comparison)
                                            <li class="list-group-item bg-transparent border-0 text-white-50">
                                                <i class="tim-icons icon-alert-circle-exc text-warning mr-2"></i>
                                                <strong>{{ $index + 1 }}.</strong> {{ $comparison['suggestion'] }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif


            </div>
        </div>
    </div>




    <!-- Chart for timeline visualization -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-moment/1.0.1/chartjs-adapter-moment.min.js">
    </script>

    {{-- Scripts for table export --}}

    <!-- Buttons extension -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>


    @if (request('showTimeline') === 'show')
        <script>
            // Dummy data - using SQL-friendly datetime format (YYYY-MM-DD HH:MM:SS)

            const data = @json($timelineData);
            // Chart setup
            window.onload = function() {
                createTimelineChart(data);

            };

            function createTimelineChart(data) {
                const ctx = document.getElementById('timelineChart').getContext('2d');

                // Find the earliest start time and latest end time automatically from data
                const startTimes = data.map(item => new Date(item.startTime.replace(' ', 'T')).getTime());
                const endTimes = data.map(item => new Date(item.endTime.replace(' ', 'T')).getTime());

                const earliestTime = Math.min(...startTimes);
                const latestTime = Math.max(...endTimes);

                // Calculate duration for each item
                data.forEach(item => {
                    const start = new Date(item.startTime.replace(' ', 'T'));
                    const end = new Date(item.endTime.replace(' ', 'T'));
                    item.duration = (end - start) / 1000; // Duration in seconds
                });

                // Prepare datasets for horizontal bar chart
                const datasets = data.map((item, index) => {
                    return {
                        label: item.symbol,
                        data: [{
                            x: [new Date(item.startTime.replace(' ', 'T')), new Date(item.endTime.replace(' ',
                                'T'))],
                            y: item.symbol
                        }],
                        backgroundColor: item.color,
                        borderColor: item.color,
                        borderWidth: 2, // More reasonable border width (500 was extremely large)
                        barThickness: 2, // Fixed bar thickness for better visibility
                        barPercentage: 0.8 // Control spacing between bars
                    };
                });

                // Create the chart
                const chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        datasets: datasets
                    },
                    options: {
                        indexAxis: 'y',
                        scales: {
                            x: {
                                type: 'time',
                                position: 'bottom',
                                time: {
                                    unit: 'minute',
                                    displayFormats: {
                                        minute: 'HH:mm:ss'
                                    }
                                },
                                // Automatically set min and max with padding
                                min: new Date(earliestTime - 60000), // 1 minute padding
                                max: new Date(latestTime + 60000)
                            },
                            y: {
                                display: false,
                                beginAtZero: true
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const datapoint = context.raw;
                                        // Format dates in SQL-friendly format for tooltips
                                        const startTime = moment(datapoint.x[0]).format('YYYY-MM-DD HH:mm:ss');
                                        const endTime = moment(datapoint.x[1]).format('YYYY-MM-DD HH:mm:ss');
                                        const duration = (new Date(datapoint.x[1]) - new Date(datapoint.x[0])) /
                                            1000;

                                        return [
                                            `Symbol: ${datapoint.y}`,
                                            `Start: ${startTime}`,
                                            `End: ${endTime}`,
                                            `Duration: ${duration.toFixed(1)}s`
                                        ];
                                    }
                                }
                            },
                            legend: {
                                display: false
                            }
                        },
                        // // Enable cursor interaction
                        // onHover: (event, elements) => {
                        //     updateCursorInfo(chart, event);
                        // }
                    }
                });

                // Create container for cursor elements if it doesn't exist
                let chartContainer = document.querySelector('.chart-container');
                if (!chartContainer) {
                    // If .chart-container doesn't exist, create it or use the canvas parent
                    const canvas = document.getElementById('timelineChart');
                    chartContainer = canvas.parentElement;
                    chartContainer.classList.add('chart-container');
                    chartContainer.style.position = 'relative';
                } else {
                    chartContainer.style.position = 'relative';
                }

                // Create cursor info display elements
                const cursorInfoId = 'cursorInfo-' + Math.random().toString(36).substr(2, 9); // Generate unique ID
                const cursorLineId = 'cursorLine-' + Math.random().toString(36).substr(2, 9); // Generate unique ID

                // Remove any existing cursor elements (for reload cases)
                const existingInfo = document.getElementById(cursorInfoId);
                if (existingInfo) existingInfo.remove();
                const existingLine = document.getElementById(cursorLineId);
                if (existingLine) existingLine.remove();

                const cursorInfoDiv = document.createElement('div');
                cursorInfoDiv.id = cursorInfoId;
                cursorInfoDiv.style.position = 'absolute';
                cursorInfoDiv.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
                cursorInfoDiv.style.color = 'white';
                cursorInfoDiv.style.padding = '8px';
                cursorInfoDiv.style.borderRadius = '4px';
                cursorInfoDiv.style.pointerEvents = 'none';
                cursorInfoDiv.style.display = 'none';
                cursorInfoDiv.style.zIndex = '100';
                cursorInfoDiv.style.fontSize = '14px';
                chartContainer.appendChild(cursorInfoDiv);

                const cursorLine = document.createElement('div');
                cursorLine.id = cursorLineId;
                cursorLine.style.position = 'absolute';
                cursorLine.style.backgroundColor = 'rgba(0, 183, 255, 0.5)';
                cursorLine.style.height = '100%';
                cursorLine.style.width = '1px';
                cursorLine.style.pointerEvents = 'none';
                cursorLine.style.display = 'none';
                cursorLine.style.zIndex = '99';
                chartContainer.appendChild(cursorLine);

                // Add mouse move event listener to chart canvas
                document.getElementById('timelineChart').addEventListener('mousemove', function(e) {
                    updateTimelineCursor(chart, e, cursorInfoId, cursorLineId, data);
                });

                // Add mouse leave event listener
                document.getElementById('timelineChart').addEventListener('mouseleave', function() {
                    document.getElementById(cursorInfoId).style.display = 'none';
                    document.getElementById(cursorLineId).style.display = 'none';
                });

                return chart;
            }

            // Function to update cursor info - TIME-BASED version
            function updateTimelineCursor(chart, event, cursorInfoId, cursorLineId, data) {
                const cursorInfo = document.getElementById(cursorInfoId);
                const cursorLine = document.getElementById(cursorLineId);

                if (!cursorInfo || !cursorLine) return; // Safety check

                const canvas = chart.canvas;
                const rect = canvas.getBoundingClientRect();
                const x = event.clientX - rect.left;

                // Get the mouse position relative to the chart area
                const chartArea = chart.chartArea;

                // Only show cursor if within chart area
                if (x >= chartArea.left && x <= chartArea.right) {
                    // Get current time based on x position
                    const xScale = chart.scales.x;
                    const currentTime = xScale.getValueForPixel(x);

                    // Find entries that intersect with this time point
                    const intersectingEntries = [];

                    // Loop through each dataset to find entries that intersect the cursor time
                    chart.data.datasets.forEach(dataset => {
                        dataset.data.forEach(item => {
                            const startTime = new Date(item.x[0]).getTime();
                            const endTime = new Date(item.x[1]).getTime();

                            if (currentTime >= startTime && currentTime <= endTime) {
                                intersectingEntries.push({
                                    symbol: item.y,
                                    startTime: new Date(item.x[0]),
                                    endTime: new Date(item.x[1])
                                });
                            }
                        });
                    });

                    // Get the count of intersecting entries
                    const intersectionCount = intersectingEntries.length;

                    // Format the cursor time
                    const cursorTimeFormatted = moment(currentTime).format('HH:mm:ss');

                    // Update cursor info position and content
                    cursorInfo.style.display = 'block';
                    cursorInfo.style.left = (x + 15) + 'px';
                    cursorInfo.style.top = (chartArea.top - 30) + 'px'; // Position at top of chart
                    cursorInfo.innerHTML =
                        `<strong>Time:</strong> ${cursorTimeFormatted} | <strong>Active Trades:</strong> ${intersectionCount}`;

                    // Update cursor line
                    cursorLine.style.display = 'block';
                    cursorLine.style.left = (x + 15) + 'px';
                    cursorLine.style.top = chartArea.top + 'px';
                    cursorLine.style.height = (chartArea.bottom - chartArea.top) + 'px';

                    // Add symbols to cursor info if there are any
                    if (intersectionCount > 0) {
                        cursorInfo.innerHTML += '<br><strong>Symbols:</strong> ' +
                            intersectingEntries.map(entry => entry.symbol).join(', ');
                    }
                }
            }
        </script>
    @endif




    @if (request('showStopLossChart') === 'show')
        <script>
            const yellowChart_rawData = @json($timelineData);

            // Filter only yellow entries
            const yellowChart_filteredData = yellowChart_rawData.filter(item => item.color === 'yellow');

            // Calculate durations
            yellowChart_filteredData.forEach(item => {
                const start = new Date(item.startTime.replace(' ', 'T'));
                const end = new Date(item.endTime.replace(' ', 'T'));
                item.duration = (end - start) / 1000;
            });

            window.addEventListener('load', () => {
                yellowChart_createTimeline(yellowChart_filteredData);
            });

            function round(value) {
                return value !== undefined && value !== null ? Number(value).toFixed(5) : '-';
            }

            function yellowChart_createTimeline(data) {
                const ctx = document.getElementById('stopLossChart').getContext('2d');

                const startTimes = data.map(item => new Date(item.startTime.replace(' ', 'T')).getTime());
                const endTimes = data.map(item => new Date(item.endTime.replace(' ', 'T')).getTime());

                const earliest = Math.min(...startTimes);
                const latest = Math.max(...endTimes);

                const datasets = data.map(item => {
                    return {
                        label: item.symbol,
                        data: [{
                            x: [new Date(item.startTime.replace(' ', 'T')), new Date(item.endTime.replace(' ',
                                'T'))],
                            y: item.symbol,
                            buyingCandle: item.buyingCandle // 👈 Embed this
                        }],
                        backgroundColor: item.color,
                        borderColor: item.color,
                        borderWidth: 2,
                        barThickness: 3,
                        barPercentage: 0.8
                    };
                });

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        datasets
                    },
                    options: {
                        indexAxis: 'y',
                        scales: {
                            x: {
                                type: 'time',
                                position: 'bottom',
                                time: {
                                    unit: 'minute',
                                    displayFormats: {
                                        minute: 'HH:mm:ss'
                                    }
                                },
                                min: new Date(earliest - 60000),
                                max: new Date(latest + 60000)
                            },
                            y: {
                                display: false,
                                beginAtZero: true
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const point = context.raw;
                                        const start = new Date(point.x[0]);
                                        const end = new Date(point.x[1]);
                                        const durationSec = (end - start) / 1000;

                                        const h = Math.floor(durationSec / 3600).toString().padStart(2, '0');
                                        const m = Math.floor((durationSec % 3600) / 60).toString().padStart(2, '0');
                                        const s = Math.floor(durationSec % 60).toString().padStart(2, '0');

                                        const candle = point.buyingCandle;

                                        return [
                                            `Symbol: ${point.y}`,
                                            `Start: ${moment(start).format('YYYY-MM-DD HH:mm:ss')}`,
                                            `End: ${moment(end).format('YYYY-MM-DD HH:mm:ss')}`,
                                            `Duration: ${h}:${m}:${s}`,
                                            '',

                                            `--- Price ---`,
                                            `Open: ${round(candle.open)}`,
                                            `High: ${round(candle.high)}`,
                                            `Low: ${round(candle.low)}`,
                                            `Close: ${round(candle.close)}`,
                                            '',
                                            `--- Volume ---`,
                                            `Volume: ${round(candle.volume)}`,
                                            `Volume MA5: ${round(candle.volumeMA5)}`,
                                            `Volume MA10: ${round(candle.volumeMA10)}`,
                                            `OBV: ${round(candle.obv)}`,
                                            `CVD: ${round(candle.cvd)}`,
                                            '',

                                            `--- Trend Indicators ---`,
                                            `EMA 12: ${round(candle.ema12)}`,
                                            `EMA 26: ${round(candle.ema26)}`,
                                            `MA7: ${round(candle.ma7)}`,
                                            `MA14: ${round(candle.ma14)}`,
                                            `MA25: ${round(candle.ma25)}`,
                                            `VWAP: ${round(candle.vwap)}`,
                                            `SAR: ${round(candle.sar)}`,
                                            '',

                                            `--- Momentum Indicators ---`,
                                            `RSI (6): ${round(candle.rsi6)}`,
                                            `MFI: ${round(candle.mfi)}`,
                                            `ADX: ${round(candle.adx)}`,
                                            `DI+: ${round(candle.di_plus)}`,
                                            `DI-: ${round(candle.di_minus)}`,
                                            `MACD DIF: ${round(candle.dif)}`,
                                            `MACD DEA: ${round(candle.dea)}`,
                                            `MACD Histogram: ${round(candle.histogram)}`,
                                            `STOCH K: ${round(candle.K)}`,
                                            `STOCH D: ${round(candle.D)}`,
                                            `STOCH RSI: ${round(candle.stoch_rsi)}`,
                                            `WR: ${round(candle.wr)}`,
                                            '',

                                            `--- Volatility Indicators ---`,
                                            `BB Upper: ${round(candle.bb_upper)}`,
                                            `BB Middle: ${round(candle.bb_middle)}`,
                                            `BB Lower: ${round(candle.bb_lower)}`,
                                            '',

                                            `--- Support & Resistance ---`,
                                            `Support: ${round(candle.currentSupport)}`,
                                            `Resistance: ${round(candle.currentResistance)}`
                                        ];

                                    }
                                }

                            },
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        </script>
    @endif


    @if (request('showSkippedTradesChart') === 'show')
        <script>
            const orangeChart_rawData = @json($timelineDataSkipped);

            // Filter only orange entries
            const orangeChart_filteredData = orangeChart_rawData.filter(item => item.color === 'orange');

            // Calculate durations
            orangeChart_filteredData.forEach(item => {
                const start = new Date(item.startTime.replace(' ', 'T'));
                const end = new Date(item.endTime.replace(' ', 'T'));
                item.duration = (end - start) / 1000;
            });

            window.addEventListener('load', () => {
                orangeChart_createTimeline(orangeChart_filteredData);
            });

            function round(value) {
                return value !== undefined && value !== null ? Number(value).toFixed(5) : '-';
            }

            function orangeChart_createTimeline(data) {
                const ctx = document.getElementById('skippedTradesChart').getContext('2d');

                const startTimes = data.map(item => new Date(item.startTime.replace(' ', 'T')).getTime());
                const endTimes = data.map(item => new Date(item.endTime.replace(' ', 'T')).getTime());

                const earliest = Math.min(...startTimes);
                const latest = Math.max(...endTimes);

                const datasets = data.map(item => {
                    return {
                        label: item.symbol,
                        data: [{
                            x: [new Date(item.startTime.replace(' ', 'T')), new Date(item.endTime.replace(' ',
                                'T'))],
                            y: item.symbol,
                            buyingCandle: item.buyingCandle, // 👈 Embed this
                            skipping_reasons: item.skipping_reasons // 👈 Embed this
                        }],
                        backgroundColor: item.color,
                        borderColor: item.color,
                        borderWidth: 4,
                        barThickness: 8,
                        barPercentage: 0.8,
                        borderRadius: 6 // 👈 Add this line
                    };
                });

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        datasets
                    },
                    options: {
                        indexAxis: 'y',
                        scales: {
                            x: {
                                type: 'time',
                                position: 'bottom',
                                time: {
                                    unit: 'minute',
                                    displayFormats: {
                                        minute: 'HH:mm:ss'
                                    }
                                },
                                min: new Date(earliest - 60000),
                                max: new Date(latest + 60000)
                            },
                            y: {
                                display: false,
                                beginAtZero: true
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const point = context.raw;
                                        const start = new Date(point.x[0]);
                                        const end = new Date(point.x[1]);
                                        const durationSec = (end - start) / 1000;

                                        const h = Math.floor(durationSec / 3600).toString().padStart(2, '0');
                                        const m = Math.floor((durationSec % 3600) / 60).toString().padStart(2, '0');
                                        const s = Math.floor(durationSec % 60).toString().padStart(2, '0');

                                        const candle = point.buyingCandle;

                                        const lines = [
                                            `Symbol: ${point.y}`,
                                            `Start: ${moment(start).format('YYYY-MM-DD HH:mm:ss')}`,
                                            `End: ${moment(end).format('YYYY-MM-DD HH:mm:ss')}`,
                                            `Duration: ${h}:${m}:${s}`,
                                            '',
                                            `--- Momentum Indicators ---`,
                                            `RSI (6): ${round(candle.rsi6)}`,
                                            `MFI: ${round(candle.mfi)}`,
                                            `ADX: ${round(candle.adx)}`,
                                            `DI+: ${round(candle.di_plus)}`,
                                            `DI-: ${round(candle.di_minus)}`,
                                            `STOCH K: ${round(candle.K)}`,
                                            `STOCH D: ${round(candle.D)}`,
                                            `STOCH RSI: ${round(candle.stoch_rsi)}`,
                                            `WR: ${round(candle.wr)}`,
                                            '',

                                        ];


                                        lines.push('--- Skipping Reasons ---');

                                        for (const key in point.skipping_reasons) {
                                            lines.push(`- ${point.skipping_reasons[key]}`);
                                        }




                                        return lines;
                                    }

                                }

                            },
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        </script>
    @endif

    @if (request('trendAnalysisChart') === 'show')
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const dataTrendReference = @json($dataTrendReference);

                // Extract timestamps and close prices
                const timestamps = dataTrendReference.map(data => data.timestampReadable);
                const closePrices = dataTrendReference.map(data => data.close);



                // Initialize Chart.js
                const ctx = document.getElementById('trendChart').getContext('2d');
                window.candlestickChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: timestamps,
                        datasets: [

                            {
                                label: 'Close Prices',
                                data: closePrices,
                                borderColor: 'rgba(0,123,255,1)',
                                backgroundColor: 'rgba(0,123,255,0.2)',
                                fill: true,
                                tension: 0.1,
                                yAxisID: 'y',
                                pointBackgroundColor: 'rgba(255,255,255,0.6)',
                                pointBorderColor: 'cyan',
                                pointRadius: 3,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Timestamp',
                                },
                                ticks: {
                                    color: '#ccc', // Light grey color for ticks
                                    display: false,
                                }
                            },
                            y: {
                                title: {
                                    display: false,
                                    text: 'Close Value',
                                },
                                ticks: {
                                    color: '#ccc', // Light grey color for ticks
                                }
                            }
                        },
                        plugins: {
                            zoom: {
                                pan: {
                                    enabled: true,
                                    mode: 'xy'
                                },
                                zoom: {
                                    pinch: {
                                        enabled: true
                                    },
                                    wheel: {
                                        enabled: true,
                                        speed: 0.1,
                                        threshold: 10,
                                        modifierKey: 'ctrl'
                                    },
                                    mode: 'x'
                                }
                            }
                        }
                    }
                });

            });
        </script>
    @endif


    @if (request('trendAnalysisActualChart') === 'show')
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const dataTrendReferenceActual = @json($dataTrendReferenceActual);

                const timestamps = dataTrendReferenceActual.map(data => data.timestampReadable);
                const closePrices = dataTrendReferenceActual.map(data => data.close);
                const bb_upper = dataTrendReferenceActual.map(data => data.bb_upper);
                const bb_middle = dataTrendReferenceActual.map(data => data.bb_middle);
                const bb_lower = dataTrendReferenceActual.map(data => data.bb_lower);

                const total_trades = dataTrendReferenceActual.map(data => data.total_trades);
                const total_trades_profitable = dataTrendReferenceActual.map(data => data.total_trades_profitable);
                const total_trades_loss = dataTrendReferenceActual.map(data => data.total_trades_loss);
                const total_trades_skipped = dataTrendReferenceActual.map(data => data.total_trades_skipped);
                const accuracy_long = dataTrendReferenceActual.map(data => data.accuracy_long);
                const profits_long = dataTrendReferenceActual.map(data => data.profits_long);
                const accuracy_short = dataTrendReferenceActual.map(data => data.accuracy_short);
                const profits_short = dataTrendReferenceActual.map(data => data.profits_short);

                const profits_total = dataTrendReferenceActual.map(data => data.profits_total);

                const ctx = document.getElementById('trendChartActual').getContext('2d');
                window.candlestickChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: timestamps,
                        datasets: [{
                                label: 'Close Prices',
                                data: closePrices,
                                borderColor: 'rgba(0,123,255,1)',
                                borderWidth: 1,
                                fill: false,
                                tension: 0.1,
                                pointRadius: 2
                            },
                            {
                                label: 'BB Upper',
                                data: bb_upper,
                                borderColor: '#f0b90b',
                                borderWidth: 1,
                                fill: false,
                                tension: 0.1,
                                pointRadius: 0,
                            },
                            {
                                label: 'BB Middle',
                                data: bb_middle,
                                borderColor: '#999999',
                                borderWidth: 1,
                                borderDash: [5, 5],
                                fill: false,
                                tension: 0.1,
                                pointRadius: 0,
                            },
                            {
                                label: 'BB Lower',
                                data: bb_lower,
                                borderColor: '#f0b90b',
                                borderWidth: 1,
                                fill: false,
                                tension: 0.1,
                                pointRadius: 0,
                            },

                            // 🔽 Trade Stats Datasets (Hidden by Default)
                            {
                                label: 'Total Trades',
                                data: total_trades,
                                borderColor: '#f1c40f', // Yellow
                                backgroundColor: 'rgba(241,196,15,0.2)',
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y1',
                                hidden: true
                            },
                            {
                                label: 'Profitable Trades',
                                data: total_trades_profitable,
                                borderColor: '#2ecc71', // Green
                                backgroundColor: 'rgba(46,204,113,0.2)',
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y1',
                                hidden: true
                            },
                            {
                                label: 'Loss Trades',
                                data: total_trades_loss,
                                borderColor: '#e74c3c', // Red
                                backgroundColor: 'rgba(231,76,60,0.2)',
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y1',
                                hidden: true
                            },
                            {
                                label: 'Skipped Trades',
                                data: total_trades_skipped,
                                borderColor: '#9b59b6', // Purple
                                backgroundColor: 'rgba(155,89,182,0.2)',
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y1',
                                hidden: true
                            },

                            {
                                label: 'Accuracy Long',
                                data: accuracy_long,
                                borderColor: '#8e44ad', // Deep purple
                                backgroundColor: 'rgba(142, 68, 173, 0.2)', // Light transparent purple
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y2',
                                hidden: true
                            },
                            {
                                label: 'Accuracy Short',
                                data: accuracy_short,
                                borderColor: '#16a085', // Teal
                                backgroundColor: 'rgba(22, 160, 133, 0.2)', // Light transparent teal
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y2',
                                hidden: true
                            },



                             {
                                label: 'Profits Long',
                                data: profits_long,
                                borderColor: '#8e44ad', // Deep purple
                                backgroundColor: 'rgba(142, 68, 173, 0.2)', 
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y2',
                                hidden: true
                            },
                            {
                                label: 'Profits Short',
                                data: profits_short,
                                borderColor: '#16a085', // Teal
                                backgroundColor: 'rgba(22, 160, 133, 0.2)', // Light transparent teal
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y2',
                                hidden: true
                            },

                            {
                                label: 'Profits Total',
                                data: profits_total,
                                borderColor: '#16a085', // Teal
                                backgroundColor: 'rgba(22, 160, 133, 0.2)', // Light transparent teal
                                borderWidth: 1,
                                fill: true,
                                tension: 0.1,
                                pointRadius: function(context) {
                                    const value = context.raw;
                                    return value !== 0 ? 3 : 0;
                                },
                                yAxisID: 'y2',
                                hidden: true
                            }



                        ],
                    },
                    options: {
                        responsive: true,
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Timestamp',
                                },
                                ticks: {
                                    color: '#ccc',
                                    display: false,
                                }
                            },
                            y: {
                                display: false // ❌ Hide price axis
                            },
                            y1: {
                                display: false, // ❌ Hide trade stats axis
                                position: 'right',
                                beginAtZero: true,
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        },
                        plugins: {
                            zoom: {
                                pan: {
                                    enabled: true,
                                    mode: 'xy'
                                },
                                zoom: {
                                    pinch: {
                                        enabled: true
                                    },
                                    wheel: {
                                        enabled: true,
                                        speed: 0.1,
                                        threshold: 10,
                                        modifierKey: 'ctrl'
                                    },
                                    mode: 'x'
                                }
                            }
                        }
                    }
                });

            });
        </script>
    @endif












    <!-- JavaScript for DataTables and Export -->
    <script>
        $(document).ready(function() {
            // Check if DataTables Buttons plugin is available
            if ($.fn.dataTable.Buttons) {
                // Initialize DataTable with export functionality
                var profitableTradesTable = $('#profitableTradesTable').DataTable({
                    "paging": true,
                    "ordering": false,
                    "info": true,
                    "searching": false
                });

                // Create a new DataTable Buttons instance
                new $.fn.dataTable.Buttons(profitableTradesTable, {
                    buttons: [{
                        extend: 'csv',
                        text: 'CSV',
                        filename: 'Profitable_Trades_Report',
                        exportOptions: {
                            // Exclude the averages row from the export
                            rows: function(idx, data, node) {
                                return $(node).hasClass('indicator-row');
                            }
                        }
                    }]
                });

                // Add a hidden container for the export button
                $('<div id="exportProfitableButtonContainer" style="display:none;"></div>').insertAfter(
                    '#profitableTradesTable');
                profitableTradesTable.buttons().container().appendTo('#exportProfitableButtonContainer');

                // Custom button event handler
                $('#exportProfitableCSV').on('click', function() {
                    profitableTradesTable.button(0).trigger();
                });
            } else {
                // Fallback: Direct CSV generation if DataTables Buttons is not available
                $('#exportProfitableCSV').on('click', function() {
                    // Prepare CSV content
                    var csvContent = [];
                    var headers = [];

                    // Get headers
                    $('#profitableTradesTable thead th').each(function() {
                        headers.push('"' + $(this).text().trim() + '"');
                    });
                    csvContent.push(headers.join(','));

                    // Get data rows (only indicator rows)
                    $('#profitableTradesTable tbody tr.indicator-row').each(function() {
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
                    link.setAttribute("download", "Profitable_Trades_Report.csv");
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
        });
    </script>
    <script>
        $(document).ready(function() {
            // Check if DataTables Buttons plugin is available
            if ($.fn.dataTable.Buttons) {
                // Initialize DataTable with export functionality
                var lossTradesTable = $('#lossTradesTable').DataTable({
                    "paging": true,
                    "ordering": false,
                    "info": false,
                    "searching": false
                });

                // Create a new DataTable Buttons instance
                new $.fn.dataTable.Buttons(lossTradesTable, {
                    buttons: [{
                        extend: 'csv',
                        text: 'CSV',
                        filename: 'Loss_Trades_Report',
                        exportOptions: {
                            // Exclude the averages row from the export
                            rows: function(idx, data, node) {
                                return $(node).hasClass('indicator-row');
                            }
                        }
                    }]
                });

                // Add a hidden container for the export button
                $('<div id="exportButtonContainer" style="display:none;"></div>').insertAfter('#lossTradesTable');
                lossTradesTable.buttons().container().appendTo('#exportButtonContainer');

                // Custom button event handler
                $('#exportLossesCSV').on('click', function() {
                    lossTradesTable.button(0).trigger();
                });
            } else {
                // Fallback: Direct CSV generation if DataTables Buttons is not available
                $('#exportLossesCSV').on('click', function() {
                    // Prepare CSV content
                    var csvContent = [];
                    var headers = [];

                    // Get headers
                    $('#lossTradesTable thead th').each(function() {
                        headers.push('"' + $(this).text().trim() + '"');
                    });
                    csvContent.push(headers.join(','));

                    // Get data rows (only indicator rows)
                    $('#lossTradesTable tbody tr.indicator-row').each(function() {
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
                    link.setAttribute("download", "Loss_Trades_Report.csv");
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
        })
    </script>






@endsection
