<?php

namespace App\Services\InternalTrader;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MarketTrendService;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\OrderBookSnapshot;

use Illuminate\Support\Facades\Log;
use stdClass;
use Illuminate\Support\Facades\File;

class ReportServiceTimeBased
{


    // Essential Properties
    public static $delayMs = 10;
    public static $supportResistanceCandleSpan = 4;
    public static $backTestTimeUnix = 1745193600000;
    // public static $backTestTimeUnix = 1744243200000;

    public static $interval = '5m';
    public static $targetProfit = 0.5;
    public static $stopLoss = 1;
    public static $stopLossWaitingDuration = 0;
    public static $longEnabled = true;
    public static $shortEnabled = true;
    public static $formula = 'Parellel Reports';
    public static $earlyClosingEnabled = true;

    // Coin Selection Filters
    public static $coinLimit = 0; // Use 0 for all coins
    public static $shuffleCoins = false;

    public static $filterOnCoinType = true;
    public static $coinTypeMetaverse = true;
    public static $coinTypeAlt = true;
    public static $coinTypeMeme = false;
    public static $coinTypeDefi = true;
    public static $coinTypeNft = false;
    public static $coinTypeWeb3 = false;



    // Confirmed Trades table settings

    public static $candlesToCheck = 500;

    public static $volumeMA5ValidFor = 30;
    public static $difValidFor = 30;
    public static $obvValidFor = 30;
    public static $kdjValidFor = 30;
    public static $bollValidFor = 30;
    public static $bollSqueezValidFor = 30;



    public static function addFormulaDetails()
    {
        self::$formula = self::$formula . ' - ' . Carbon::now()->format('l, F j, Y h:i A');
        $date = date('Y-m-d H:i:s');

        $classPath = app_path('Services/InternalTrader/ReportService.php');

        // Output path
        $outputPath = storage_path('app/public/formula_bkp_service_' . self::$formula . '.txt');

        $contents = File::get($classPath);
        File::put($outputPath, $contents);
        $html = '
        <div class="card card-chart">
            <div class="card-header">
                <h5 class="card-category text-warning">Formula Report</h5>
                <h4 class="card-title text-white">' . self::$formula . '</h4>
                <p class="card-category text-muted"><i class="tim-icons icon-calendar-60"></i> Generated on ' . $date . '</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table tablesorter">
                        <tbody class="text-white">
                            <tr>
                                <td><i class="tim-icons icon-time-alarm"></i> <strong>Interval:</strong></td>
                                <td>' . self::$interval . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-money-coins"></i> <strong>Target Profit:</strong></td>
                                <td>' . self::$targetProfit . '%</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-triangle-right-17"></i> <strong>Stop Loss:</strong></td>
                                <td>' . self::$stopLoss . '%</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-watch-time"></i> <strong>Stop Loss Wait Duration:</strong></td>
                                <td>' . self::$stopLossWaitingDuration . ' minutes</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-chart-bar-32"></i> <strong>Support/Resistance Candle Span:</strong></td>
                                <td>' . self::$supportResistanceCandleSpan . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-refresh-02"></i> <strong>Delay:</strong></td>
                                <td>' . self::$delayMs . ' ms</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-calendar-60"></i> <strong>Backtest Time (Unix):</strong></td>
                                <td>' . (self::$backTestTimeUnix ?? 'Not set') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-minimal-up"></i> <strong>Long Position Enabled:</strong></td>
                                <td>' . (self::$longEnabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-minimal-down"></i> <strong>Short Position Enabled:</strong></td>
                                <td>' . (self::$shortEnabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-bulb-63"></i> <strong>Early Closing Enabled:</strong></td>
                                <td>' . (self::$earlyClosingEnabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-coins"></i> <strong>Coin Limit:</strong></td>
                                <td>' . self::$coinLimit . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-settings"></i> <strong>Filter on Coin Type:</strong></td>
                                <td>' . (self::$filterOnCoinType ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>';

        // Only show coin type details if filtering is enabled
        if (self::$filterOnCoinType) {
            $html .= '
                            <tr>
                                <td colspan="2" class="text-center"><strong class="text-info">Coin Type Filters</strong></td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-planet"></i> <strong>Metaverse:</strong></td>
                                <td>' . (self::$coinTypeMetaverse ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-bitcoin"></i> <strong>Alt:</strong></td>
                                <td>' . (self::$coinTypeAlt ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-satisfied"></i> <strong>Meme:</strong></td>
                                <td>' . (self::$coinTypeMeme ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-bank"></i> <strong>DeFi:</strong></td>
                                <td>' . (self::$coinTypeDefi ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-palette"></i> <strong>NFT:</strong></td>
                                <td>' . (self::$coinTypeNft ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-world"></i> <strong>Web3:</strong></td>
                                <td>' . (self::$coinTypeWeb3 ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>';
        }

        $html .= '
                            <tr>
                                <td><i class="tim-icons icon-cloud-download-93"></i> <strong>BKP-File path:</strong></td>
                                <td class="" style="max-width: 250px;" title="' . $outputPath . '">' . $outputPath . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="stats">
                    <i class="tim-icons icon-refresh-01"></i> Last updated ' . date('H:i:s') . '
                </div>
            </div>
        </div>
        ';

        DB::table('formula_details')->insert([
            'formula' => self::$formula,
            'details' => $html,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }



    public static function generateCoinReport(
        $cmd = null
    ) {

        $tradesTotal = [];
        $coinsQuery = DB::table('coins')->where('market', 'FUTURE')->where('status', 'T');


        // Coin Type Filters
        if (self::$filterOnCoinType) {
            if (self::$coinTypeMetaverse)
                $coinsQuery->where('is_metaverse', true);
            if (self::$coinTypeAlt)
                $coinsQuery->where('is_altcoin', true);
            if (self::$coinTypeMeme)
                $coinsQuery->where('is_meme_coin', true);
            if (self::$coinTypeNft)
                $coinsQuery->where('is_nft', true);
            if (self::$coinTypeDefi)
                $coinsQuery->where('is_defi', true);
            if (self::$coinTypeWeb3)
                $coinsQuery->where('is_web3', true);
        }
        if (self::$shuffleCoins) {
            $coinsQuery->inRandomOrder();
        }

        if (self::$coinLimit) {
            $coinsQuery->limit(self::$coinLimit);
        } else {
            self::$coinLimit = (clone $coinsQuery)->count();
        }
        $coins = $coinsQuery->get();

        // Clear Console
        system('clear');
        $cmd->info('Processing: 0 %');

        self::addFormulaDetails();
        DB::table('confirmed_trades')->truncate();
        foreach ($coins as $index => $coin) {

            try {


                $symbol = $coin->symbol;

                $data = BinanceApiService::getCandleStickData($symbol, self::$interval, 1000, self::$backTestTimeUnix, 'FUTURE');

                $trades = self::processCandles($symbol, $data);

                // Insert trades into the database
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', self::$interval)->where('formula', self::$formula)->where('market', 'FUTURE')->delete();
                DB::table('coin_reports')->insert($trades);


                $tradesTotal[$symbol] = $trades;


                $perProgress = (($index + 1) / count($coins)) * 100;
                system('clear');
                $cmd->info('Processing: ' . round($perProgress) . ' %');
                DB::table('formula_details')->where('formula', self::$formula)->update([
                    'progress' => $perProgress,
                ]);
            } catch (\Exception $e) {
                dd($e);
                $cmd->error('Error Occured: ', $e->getMessage());
                Log::error("Failed to update coin reports: " . $e->getMessage());
            }
            CommonHelpers::delayMS(self::$delayMs);
        }

        $cmd->info('Completed Report for : ' . self::$formula);
        $cmd->info('Total Coins Processed : ' . count($coins));
    }

    protected static function processCandles($symbol, $data)
    {
        $open_price_long = 0;
        $open_price_short = 0;

        $tradeTypeLong = null;
        $tradeTypeShort = null;


        $currentTradeLong = [];
        $currentTradeShort = [];
        $trades = [];

        $extremePriceLong = 0;
        $extremePriceShort = 0;


        $data = array_map(function ($candle) {
            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
            return $candle;
        },  $data);

        $waitingCandles = 0;


        $openingIndexLong = 0;
        $openingIndexShort = 0;



        $confirmIndexLong = 0;
        $confirmIndexShort = 0;


        foreach ($data as $index => $candle) {



            $volumeSignal  = [
                "symbol" => $symbol,
                "interval" => self::$interval,
                "price" => $data[$index]['close'],
                "signal" => 'NEUTRAL',
                "strength" => 5.0,
                "reasons" => [],
                "potential" => false,
                "indicators" => [
                    "mfi_current" => $data[$index]['mfi'],
                    "cvd_current" => $data[$index]['cvd'],
                    "price_to_poc" => null,
                    "poc_value" => null,
                    "volume_profile" => null,
                    "vwap_current" => $data[$index]['vwap'],
                    "obv_current" => $data[$index]['obv'],
                ],
                "timestampReadable" => $data[$index]['timestampReadable'],
                "timestamp" => $data[$index]['binance_timestamp'],
            ];

            // Skip Adjustment Candles and Volume Adjustment
            if ($index < 250) {
                continue;
            }


            // 20 mins weight after each trade

            if ($waitingCandles) {
                $waitingCandles--;
                continue;
            }





            $supportResistance = self::getSupportResistance($data, $index);
            $orderBookSnapshot = self::getOrderBookSnapshot($symbol, $data, $index);


            // ###########################  Handling LONG Sequence Independently ###########################
            if ($open_price_long == 0 && self::$longEnabled) {

                $tradeTypeLong = self::handleOpeningConditionsLong($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $confirmIndexLong);

                if (
                    $tradeTypeLong
                ) {

                    $candle['should_buy'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot->id;
                    $candle['openingVolumes'] = json_encode($volumeSignal);

                    $open_price_long = $candle['close'];
                    $currentTradeLong['buyingCandle'] = json_encode($candle);
                    $extremePriceLong = $open_price_long;
                    // Placeholder object for testing

                    $openingIndexLong = $index;
                }
            } else {
                $closingPrice =  self::handleClosingConditionsLong($symbol, $data, $index,  $tradeTypeLong, $openingIndexLong, $open_price_long);

                // Closing Sequence

                if ($tradeTypeLong === 'SHORT' && $data[$index]['high'] > $extremePriceLong) {
                    $extremePriceLong = $data[$index]['high'];
                }
                if ($tradeTypeLong === 'LONG' && $data[$index]['low'] < $extremePriceLong) {
                    $extremePriceLong = $data[$index]['low'];
                }
                if ($closingPrice) {
                    $profit = $tradeTypeLong === 'LONG' ? round(($closingPrice - $open_price_long) / $open_price_long * 100, 2) : round(($open_price_long - $closingPrice) / $open_price_long * 100, 2);



                    // Handle boll indicator calculations


                    $highestPointIndex = self::getTightestSqueezIndex($data, $confirmIndexLong ?? $openingIndexLong);


                    $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);
                    $bbDiffConfirmed = CommonHelpers::getPercentDiff($data[$confirmIndexLong]['bb_lower'], $data[$confirmIndexLong]['bb_upper']);
                    $bbDiff = ($bbDiffConfirmed - $bbDiffHighest) / max(0.00001, $bbDiffHighest) * 100;

                    $data[$confirmIndexLong]['bb_diff_highest'] = $bbDiffHighest;
                    $data[$confirmIndexLong]['bb_diff_confirmed'] = $bbDiffConfirmed;
                    $data[$confirmIndexLong]['bb_diff'] = $bbDiff;
                    $candle['should_sell'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;

                    $candle['closingVolumes'] = json_encode($volumeSignal);

                    $currentTradeLong['confirmCandle'] = json_encode($data[$confirmIndexLong]);
                    $currentTradeLong['highestCandle'] = json_encode($data[$highestPointIndex]);




                    $currentTradeLong['sellingCandle'] = json_encode($candle);
                    $currentTradeLong['buyingPrice'] = $open_price_long;
                    $currentTradeLong['market'] = 'FUTURE';
                    $currentTradeLong['sellingPrice'] = $closingPrice;
                    $currentTradeLong['symbol'] = $symbol;
                    $currentTradeLong['interval'] = self::$interval;
                    $currentTradeLong['profit'] = $profit;
                    $currentTradeLong['lowestPrice'] = $extremePriceLong;
                    $currentTradeLong['liquidationPrice'] = 0;
                    $currentTradeLong['lowestPricePercentage'] = abs((($open_price_long - $extremePriceLong) / $open_price_long)) * 100;
                    $currentTradeLong['position'] = $tradeTypeLong;
                    $currentTradeLong['formula'] = self::$formula;

                    $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTradeLong['buyingCandle'], true)['timestamp']);
                    $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTradeLong['sellingCandle'], true)['timestamp']);
                    $currentTradeLong['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;

                    // Resetting params
                    $extremePriceLong = 0;
                    $trades[] = $currentTradeLong;
                    $currentTradeLong = [];
                    $open_price_long = 0;
                    $tradeTypeLong = null;
                    $waitingCandles = 4;
                    $openingIndexLong = 0;
                    $confirmIndexLong = 0;
                }
            }



            // ############################################################################################################






            // ###########################  Handling SHORT Sequence Independently ###########################
            if ($open_price_short == 0 && self::$shortEnabled) {

                $tradeTypeShort = self::handleOpeningConditionsShort($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $confirmIndexShort);

                if (
                    $tradeTypeShort
                ) {

                    $candle['should_buy'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                    $candle['openingVolumes'] = json_encode($volumeSignal);

                    $open_price_short = $candle['close'];
                    $currentTradeShort['buyingCandle'] = json_encode($candle);
                    $extremePriceShort = $open_price_short;
                    // Placeholder object for testing

                    $openingIndexShort = $index;
                }
            } else {
                $closingPrice =  self::handleClosingConditionsShort($symbol, $data, $index,  $tradeTypeShort, $openingIndexShort, $open_price_short);

                // Closing Sequence

                if ($tradeTypeShort === 'SHORT' && $data[$index]['high'] > $extremePriceShort) {
                    $extremePriceShort = $data[$index]['high'];
                }
                if ($tradeTypeShort === 'LONG' && $data[$index]['low'] < $extremePriceShort) {
                    $extremePriceShort = $data[$index]['low'];
                }
                if ($closingPrice) {
                    $profit = $tradeTypeShort === 'LONG' ? round(($closingPrice - $open_price_short) / $open_price_short * 100, 2) : round(($open_price_short - $closingPrice) / $open_price_short * 100, 2);



                    // Handle boll indicator calculations


                    $highestPointIndex = self::getTightestSqueezIndex($data, $confirmIndexShort ?? $openingIndexShort);


                    $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);
                    $bbDiffConfirmed = CommonHelpers::getPercentDiff($data[$confirmIndexShort]['bb_lower'], $data[$confirmIndexShort]['bb_upper']);
                    $bbDiff = ($bbDiffConfirmed - $bbDiffHighest) / max(0.00001, $bbDiffHighest) * 100;

                    $data[$confirmIndexShort]['bb_diff_highest'] = $bbDiffHighest;
                    $data[$confirmIndexShort]['bb_diff_confirmed'] = $bbDiffConfirmed;
                    $data[$confirmIndexShort]['bb_diff'] = $bbDiff;
                    $candle['should_sell'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;

                    $candle['closingVolumes'] = json_encode($volumeSignal);

                    $currentTradeShort['confirmCandle'] = json_encode($data[$confirmIndexShort]);
                    $currentTradeShort['highestCandle'] = json_encode($data[$highestPointIndex]);




                    $currentTradeShort['sellingCandle'] = json_encode($candle);
                    $currentTradeShort['buyingPrice'] = $open_price_short;
                    $currentTradeShort['market'] = 'FUTURE';
                    $currentTradeShort['sellingPrice'] = $closingPrice;
                    $currentTradeShort['symbol'] = $symbol;
                    $currentTradeShort['interval'] = self::$interval;
                    $currentTradeShort['profit'] = $profit;
                    $currentTradeShort['lowestPrice'] = $extremePriceShort;
                    $currentTradeShort['liquidationPrice'] = 0;
                    $currentTradeShort['lowestPricePercentage'] = abs((($open_price_short - $extremePriceShort) / $open_price_short)) * 100;
                    $currentTradeShort['position'] = $tradeTypeShort;
                    $currentTradeShort['formula'] = self::$formula;

                    $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTradeShort['buyingCandle'], true)['timestamp']);
                    $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTradeShort['sellingCandle'], true)['timestamp']);
                    $currentTradeShort['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;

                    // Resetting params
                    $extremePriceShort = 0;
                    $trades[] = $currentTradeShort;
                    $currentTradeShort = [];
                    $open_price_short = 0;
                    $tradeTypeShort = null;
                    $waitingCandles = 4;
                    $openingIndexShort = 0;
                    $confirmIndexShort = 0;
                }
            }



            // ############################################################################################################

        }




        // For shifting indexes
        $data_new = [];
        foreach ($data as $d) {
            $data_new[] = $d;
        }
        // dd($data_new);
        $data = $data_new;

        return $trades;
    }







    // Function to check opening Conditions

    public static function handleOpeningConditionsLong($symbol, $data, $index, $supportResistance, $orderBookSnapshot, &$confirmIndexLong)
    {

        if (!$orderBookSnapshot)
            return null;


        $buyLongCondition = false;


        if ($data[$index]['per'] > 0.08 && !self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {

            $loopIndex = $index;
            while ($data[$loopIndex]['histogram'] < 0 || $loopIndex == $index) {



                $orderBookSnapshotLoop = self::getOrderBookSnapshot($symbol, $data, $loopIndex);

                if (!$orderBookSnapshotLoop) {
                    break;
                }
                $imbalance = ($orderBookSnapshotLoop->bid_volume - $orderBookSnapshotLoop->ask_volume) / ($orderBookSnapshotLoop->bid_volume + $orderBookSnapshotLoop->ask_volume) * 100;
                $spread_pct = ($orderBookSnapshotLoop->lowest_ask - $orderBookSnapshotLoop->highest_bid) / (($orderBookSnapshotLoop->lowest_ask + $orderBookSnapshotLoop->highest_bid) / 2) * 100;


                $macdLongConditionLoop =
                    $data[$loopIndex]['histogram'] > $data[$loopIndex - 1]['histogram'] && $data[$loopIndex]['histogram'] < 0
                    && $data[$loopIndex - 1]['histogram'] < $data[$loopIndex - 2]['histogram']
                    && $data[$loopIndex - 2]['histogram'] < $data[$loopIndex - 3]['histogram']
                    && $data[$loopIndex - 3]['histogram'] < $data[$loopIndex - 4]['histogram']
                    && $data[$loopIndex - 4]['histogram'] < $data[$loopIndex - 5]['histogram'];

                $buyLongConditionInitial =  $imbalance > 5 && $spread_pct < 0.1
                    && $data[$index]['obv'] > $data[$index - 1]['obv']
                    && $data[$index]['rsi6'] > 18 && $data[$index - 1]['rsi6'] <= 18
                    && $macdLongConditionLoop && $data[$loopIndex]['mfi'] < 30 && $orderBookSnapshot->volume_imbalance > 1
                    && $data[$loopIndex]['K'] < 30
                    && $data[$loopIndex]['J'] > $data[$loopIndex]['K'] && $data[$loopIndex]['J'] > $data[$loopIndex]['D'];

                $loopIndex--;
                if ($buyLongConditionInitial) {

                    $confirmIndexLong = $index;
                    $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

                    if (
                        $bbAnalysis['long_probability'] == 0
                        && CommonHelpers::getPercentDiff($data[$index - 1]['rsi6'], $data[$index - 1]['rsi6'], true) > 100


                    ) {

                        return 'LONG';
                    }
                    if (

                        $bbAnalysis['long_probability'] >= 45 && $bbAnalysis['short_probability'] == 0

                    ) {

                        return 'LONG';
                    }





                    self::insertConfirmBasicTradeEntry($symbol, 'LONG', $data, $index);
                    break;
                }
            }
        }


        if (self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {


            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

            if (


                $data[$index]['per'] > 0

                && $bbAnalysis['is_expanding']
                && $bbAnalysis['percent_b'] >= 50

            ) {
                $buyLongCondition = self::confirmOpening($symbol, $data, $index);
            }
        }



        // Long condition
        if (
            $buyLongCondition

        ) {
            return self::$longEnabled ? 'LONG' : null;
        }


        // No conditions met so return null
        return null;
    }




    public static function handleClosingConditionsLong($symbol, $data, $index, $tradeTypeLong, $openingIndexLong, $open_price_long)
    {

        $candle = $data[$index];
        $closingPrice = 0;
        $waitingCandlesBeforeStopLoss = intval(self::$stopLossWaitingDuration / CommonHelpers::$binanceIntervals[self::$interval]);
        if ($tradeTypeLong == 'SHORT') {
            // Calculate Closing in profit 
            if ($candle['low'] <= $open_price_long * (1 - self::$targetProfit / 100)) {
                $closingPrice = $candle['low'];
            } else if ($index - $openingIndexLong  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price_long, $data[$index]['close']) >= self::$stopLoss && $open_price_long < $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        } else if ($tradeTypeLong == 'LONG') {

            // Calculate Closing in profit 
            if ($candle['high'] >= $open_price_long * (1 + self::$targetProfit / 100)) {
                $closingPrice = $candle['high'];
            } else if ($index - $openingIndexLong  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price_long, $data[$index]['close']) >= self::$stopLoss && $open_price_long > $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        }


        return $closingPrice;
    }





















    // ############################################# SHORT Sequences ###########################################


    public static function handleOpeningConditionsShort($symbol, $data, $index, $supportResistance, $orderBookSnapshot, &$confirmIndexShort)
    {

        // if (!$orderBookSnapshot)
        //     return null;


        $sellShortCondition = false;


        if ($data[$index]['per'] < -0.08 && !self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {

            $loopIndex = $index;
            while ($data[$loopIndex]['histogram'] > 0 || $loopIndex == $index) {




                $macdShortConditionLoop =
                    $data[$loopIndex]['histogram'] < $data[$loopIndex - 1]['histogram'] && $data[$loopIndex]['histogram'] > 0
                    && $data[$loopIndex - 1]['histogram'] > $data[$loopIndex - 2]['histogram']
                    && $data[$loopIndex - 2]['histogram'] > $data[$loopIndex - 3]['histogram']
                    && $data[$loopIndex - 3]['histogram'] > $data[$loopIndex - 4]['histogram']
                    && $data[$loopIndex - 4]['histogram'] > $data[$loopIndex - 5]['histogram'];


                $sellShortConditionInitial =

                    $data[$index]['obv'] < $data[$index - 1]['obv']
                    && $macdShortConditionLoop
                    && $data[$index]['mfi'] > 70;

                $loopIndex--;
                if ($sellShortConditionInitial) {
                    $confirmIndexShort = $index;


                    $tightestPointIndex = self::getTightestSqueezIndex($data, $index);
                    if (max($data[$tightestPointIndex]['close'], $data[$tightestPointIndex]['open']) >= max($data[$index]['close'], $data[$index]['open'])) {
                        return null;
                    } else {

                        $candlesAboveMiddleLine = 0;
                        $loopIndex = $index;
                        while (min($data[$loopIndex]['open'], $data[$loopIndex]['close']) > $data[$loopIndex]['bb_middle']) {
                            $candlesAboveMiddleLine++;
                            $loopIndex--;
                        }

                        if ($data[$index]['wr'] < -30) {

                            return null;
                        }

                        if ($data[$index]['stoch_d'] > 80 && $candlesAboveMiddleLine < 15) {
                            return 'SHORT';
                        }


                        // return 'SHORT';
                        self::insertConfirmBasicTradeEntry($symbol, 'SHORT', $data, $index);
                        // return null;
                        break;
                    }
                }
                break;
            }
        }


        if (self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {



            $bb_squeezed = ($data[$index]['bb_upper'] - $data[$index]['bb_lower']) - ($data[$index - 1]['bb_upper'] - $data[$index - 1]['bb_lower']);


            $candlesAboveMiddleLine = 0;
            $loopIndex = $index;
            while (min($data[$loopIndex]['open'], $data[$loopIndex]['close']) > $data[$loopIndex]['bb_middle']) {
                $candlesAboveMiddleLine++;
                $loopIndex--;
            }
            // Validate point where every condition is true and valid in it timeperiod
            if (
                !($data[$index]['dif'] > $data[$index - 1]['dif'])

                &&
                !($data[$index]['wr'] > -30)

                &&
                $bb_squeezed <= 0

                && $data[$index]['dif'] < max($data[$index - 1]['dif'], $data[$index - 2]['dif'])

                && $candlesAboveMiddleLine < 15

            ) {

                if ($data[$index]['wr'] < -30) {
                    self::confirmOpening($symbol, $data, $index);
                    return null;
                }

                $sellShortCondition = self::confirmOpening($symbol, $data, $index);
            }
        }



        // Long condition
        if (
            $sellShortCondition

        ) {
            return 'SHORT';
        }


        // No conditions met so return null
        return null;
    }




    public static function handleClosingConditionsShort($symbol, $data, $index, $tradeTypeShort, $openingIndexShort, $open_price_short)
    {

        $candle = $data[$index];
        $closingPrice = 0;
        $waitingCandlesBeforeStopLoss = intval(self::$stopLossWaitingDuration / CommonHelpers::$binanceIntervals[self::$interval]);
        if ($tradeTypeShort == 'SHORT') {
            // Calculate Closing in profit 
            if ($candle['low'] <= $open_price_short * (1 - self::$targetProfit / 100)) {
                $closingPrice = $candle['low'];
            } else if ($index - $openingIndexShort  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price_short, $data[$index]['close']) >= self::$stopLoss && $open_price_short < $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        } else if ($tradeTypeShort == 'LONG') {
            // Calculate Closing in profit 
            if ($candle['high'] >= $open_price_short * (1 + self::$targetProfit / 100)) {
                $closingPrice = $candle['high'];
            } else if ($index - $openingIndexShort  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price_short, $data[$index]['close']) >= self::$stopLoss && $open_price_short > $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        }


        return $closingPrice;
    }

    // ##############################################################################################################




























    // ######################################### Other Functions ##################################################


    public static function getSupportResistance($data, $index)
    {
        $end = $index + 1; // +1 to include the $index item
        $length = 300;

        $start = max(0, $end - $length); // make sure we donâ€™t go negative
        $slicedData = array_slice($data, $start, $length);

        return MarketTrendService::getCurrentSupportResistanceValueFromData($slicedData, [self::$supportResistanceCandleSpan])[self::$supportResistanceCandleSpan];
    }
    public static function getOrderBookSnapshot($symbol, $data, $index)
    {

        // Fetch OrderBook snapshot
        $timestamp = $data[$index]['timestampReadable'];
        $snapshot = OrderBookSnapshot::where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
            ->where('snapshot_time', '>=', Carbon::parse($timestamp)->subMinutes(60))
            ->where('symbol', $symbol)
            ->where('depth', 1000)
            ->latest('snapshot_time')
            ->first();
        return $snapshot;
    }


    public static function getFormulaId($formulaName)
    {
        $formula = DB::table('formula_details')->where('formula', $formulaName)->first();
        return $formula ? $formula->id : null;
    }
    public static function getCoinReportsOnFormula($formula_id)
    {
        return  DB::table('coin_reports')
            ->join('formula_details', 'coin_reports.formula', '=', 'formula_details.formula')
            ->where('formula_details.id', $formula_id)
            ->select('coin_reports.*')
            ->get();
    }




    // #########################Functions for confirmed Trades table###############################

    public static function getIndexDiffFromTimestamps($timestamp1, $timestamp2, $interval, $rounded = true)
    {
        if (!($timestamp1 && $timestamp2)) {
            return false;
        }
        $intervalToMins = CommonHelpers::$binanceIntervals[$interval];
        $diff = abs($timestamp1 - $timestamp2) / (60 * 1000 * $intervalToMins);
        return $rounded ? intval($diff) : $diff;
    }


    public static function insertConfirmBasicTradeEntry($symbol, $position, $data, $index)
    {

        $reportId = self::getFormulaId(self::$formula);

        // BB Calculations for highest point squeez
        $highestPointIndex = self::getTightestSqueezIndex($data, $index);
        $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);



        $id =  DB::table('confirmed_trades')->insertGetId([
            'report_id' => $reportId,
            'position' => $position,
            'coin_name' => $symbol,
            'confirm_candle_timestamp' => $data[$index]['binance_timestamp'],
            'candles_to_check' => self::$candlesToCheck,
            'trade_confirmed' => 0,
            'bolling_last_squeez_value' => $bbDiffHighest,
            'bolling_last_squeezed_timestamp' => $data[$highestPointIndex]['binance_timestamp'],
            'update_time' => Carbon::now()->toDateTimeString(),

        ]);
        return DB::table('confirmed_trades')->where('ict_id', $id)->first();
    }

    public static function getIctId($symbol)
    {
        $lastEntry =  DB::table('confirmed_trades')->where('coin_name', $symbol)->where('trade_confirmed', 0)->orderBy('update_time', 'DESC')->first();
        return $lastEntry ? $lastEntry->ict_id : null;
    }


    public static function checkConfirmTradeValidity($symbol, $position, $data, $index)
    {
        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }

        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->where('position', $position)->first();

        if (!$lastEntry) {
            return null;
        }
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->confirm_candle_timestamp, self::$interval);
        if ($indexDiff > $lastEntry->candles_to_check) {
            DB::table('confirmed_trades')->where('ict_id', $ictId)->update([
                'trade_confirmed' => 1,
                'update_time' => Carbon::now()->toDateTimeString(),
            ]);
            return null;
        }
        return $lastEntry;
    }


    public static function confirmOpening($symbol, $data, $index)
    {
        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }
        DB::table('confirmed_trades')->where('ict_id', $ictId)->update([
            'trade_confirmed' => 1,
            'update_time' => Carbon::now()->toDateTimeString(),
        ]);
        return true;
    }

    public static function getTightestSqueezIndex($data, $startIndex)
    {
        $minSqueeze = CommonHelpers::getPercentDiff(
            $data[$startIndex]['bb_lower'],
            $data[$startIndex]['bb_upper']
        );

        $tightestIndex = $startIndex;
        $currentIndex = $startIndex;

        // Step 1: Loop backward until histogram crosses from red to green
        while ($currentIndex > 0) {
            $currentSqueeze = CommonHelpers::getPercentDiff(
                $data[$currentIndex]['bb_lower'],
                $data[$currentIndex]['bb_upper']
            );

            if ($currentSqueeze < $minSqueeze) {
                $minSqueeze = $currentSqueeze;
                $tightestIndex = $currentIndex;
            }

            // Histogram crossover from red to green
            if (
                $data[$currentIndex]['histogram'] > 0 &&
                $data[$currentIndex - 1]['histogram'] < 0
            ) {
                break;
            }

            $currentIndex--;
        }

        // Step 2: After crossover, check previous 3-entry blocks for tighter squeeze
        while ($currentIndex > 2) {
            $foundSmaller = false;

            for ($i = 1; $i <= 3; $i++) {
                $checkIndex = $currentIndex - $i;
                if ($checkIndex < 0) break;

                $squeeze = CommonHelpers::getPercentDiff(
                    $data[$checkIndex]['bb_lower'],
                    $data[$checkIndex]['bb_upper']
                );

                if ($squeeze < $minSqueeze) {
                    $minSqueeze = $squeeze;
                    $tightestIndex = $checkIndex;
                    $currentIndex = $checkIndex; // Move back to this point
                    $foundSmaller = true;
                }
            }

            // If no tighter squeeze found in last 3, break
            if (!$foundSmaller) {
                break;
            }
        }

        return $tightestIndex;
    }





    
}
