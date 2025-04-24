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

class ReportService
{


    // Essential Properties
    public static $delayMs = 10;
    public static $supportResistanceCandleSpan = 3;
    public static $backTestTimeUnix = null;
    // public static $backTestTimeUnix = 1743163200000;

    public static $interval = '1m';
    public static $targetProfit = 0.5;
    public static $stopLoss = 0.8;
    public static $stopLossWaitingDuration = 0;
    public static $longEnabled = true;
    public static $shortEnabled = true;
    public static $formula = 'Test Internal Report (Long)';
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

    public static $candlesToCheck = 30;
    public static $volumeMA5ValidFor = 3;


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
        $open_price = 0;

        $tradeType = null;


        $currentTrade = [];
        $trades = [];

        $extremePrice = 0;


        $intervalToMins = CommonHelpers::$binanceIntervals[self::$interval];
        $timestamp = $data[0]['binance_timestamp'] - (60 * $intervalToMins * 1000 * 1000);
        $averageAdjustmetCandles =  BinanceApiService::getCandleStickData($symbol, self::$interval, 1000, $timestamp, 'FUTURE');

        $data = array_map(function ($candle) {
            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
            return $candle;
        }, array_merge($averageAdjustmetCandles, $data));

        $waitingCandles = 0;
        $openingIndex = 0;


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
            if ($index < 1000) {
                continue;
            }

            // 20 mins weight after each trade

            if ($waitingCandles) {
                $waitingCandles--;
                continue;
            }
            $supportResistance = self::getSupportResistance($data, $index);
            $orderBookSnapshot = self::getOrderBookSnapshot($symbol, $data, $index);

            if ($open_price == 0) {

                $tradeType = self::handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot);

                if (
                    $tradeType
                ) {


                    $candle['should_buy'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot->id;
                    $candle['openingVolumes'] = json_encode($volumeSignal);

                    $open_price = $candle['close'];
                    $currentTrade['buyingCandle'] = json_encode($candle);
                    $extremePrice = $open_price;
                    // Placeholder object for testing

                    $openingIndex = $index;
                }
            } else {
                $closingPrice =  self::handleClosingConditions($symbol, $data, $index,  $tradeType, $openingIndex, $open_price);

                // Closing Sequence

                if ($tradeType === 'SHORT' && $data[$index]['high'] > $extremePrice) {
                    $extremePrice = $data[$index]['high'];
                }
                if ($tradeType === 'LONG' && $data[$index]['low'] < $extremePrice) {
                    $extremePrice = $data[$index]['low'];
                }
                if ($closingPrice) {
                    $profit = $tradeType === 'LONG' ? round(($closingPrice - $open_price) / $open_price * 100, 2) : round(($open_price - $closingPrice) / $open_price * 100, 2);

                    $candle['should_sell'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;

                    $candle['closingVolumes'] = json_encode($volumeSignal);

                    $currentTrade['sellingCandle'] = json_encode($candle);
                    $currentTrade['buyingPrice'] = $open_price;
                    $currentTrade['market'] = 'FUTURE';
                    $currentTrade['sellingPrice'] = $closingPrice;
                    $currentTrade['symbol'] = $symbol;
                    $currentTrade['interval'] = self::$interval;
                    $currentTrade['profit'] = $profit;
                    $currentTrade['lowestPrice'] = $extremePrice;
                    $currentTrade['liquidationPrice'] = 0;
                    $currentTrade['lowestPricePercentage'] = abs((($open_price - $extremePrice) / $open_price)) * 100;
                    $currentTrade['position'] = $tradeType;
                    $currentTrade['formula'] = self::$formula;

                    $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['buyingCandle'], true)['timestamp']);
                    $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['sellingCandle'], true)['timestamp']);
                    $currentTrade['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;

                    // Resetting params
                    $extremePrice = 0;
                    $trades[] = $currentTrade;
                    $currentTrade = [];
                    $open_price = 0;
                    $tradeType = null;
                    $waitingCandles = 4;
                    $openingIndex = 0;
                }
            }
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

    public static function handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot)
    {

        if (!$orderBookSnapshot)
            return null;



        // ############################### BUY LONG CONDITIONS ##################################
        $buyLongCondition = false;
        if (!self::checkConfirmTradeValidity($symbol, $data, $index)) {


            $orderBookSnapshotLoop = self::getOrderBookSnapshot($symbol, $data, $index);

            if (!$orderBookSnapshotLoop) {
                return null;
            }
            $imbalance = ($orderBookSnapshotLoop->bid_volume - $orderBookSnapshotLoop->ask_volume) / ($orderBookSnapshotLoop->bid_volume + $orderBookSnapshotLoop->ask_volume) * 100;
            $spread_pct = ($orderBookSnapshotLoop->lowest_ask - $orderBookSnapshotLoop->highest_bid) / (($orderBookSnapshotLoop->lowest_ask + $orderBookSnapshotLoop->highest_bid) / 2) * 100;



            $macdLongConditionLoop =
                $data[$index]['histogram'] > $data[$index - 1]['histogram'] && $data[$index]['histogram'] < 0 // Current Candle should be light red
                && $data[$index - 1]['histogram'] < $data[$index - 2]['histogram'] && $data[$index - 1]['histogram'] < 0 // // Second Last Candle should be dark red
                && $data[$index - 2]['histogram'] < $data[$index - 3]['histogram'] && $data[$index - 2]['histogram'] < 0 // // Third Last Candle should be dark red
                && $data[$index - 3]['histogram'] < $data[$index - 4]['histogram'] && $data[$index - 3]['histogram'] < 0 // // Fourth Last Candle should be dark red
                && $data[$index - 4]['histogram'] < $data[$index - 5]['histogram'] && $data[$index - 4]['histogram'] < 0 // // Fifth Last Candle should be dark red
                && $data[$index - 5]['histogram'] < $data[$index - 6]['histogram'] && $data[$index - 5]['histogram'] < 0 // // Sixth Last Candle should be dark red
            ;



            $supportResistanceFirst = self::getSupportResistance($data, $index);

            $supportResistanceSecond = self::getSupportResistance($data, $index - max($supportResistanceFirst['resistanceDistance'], $supportResistanceFirst['supportDistance']));


            $buyLongConditionInitial =
                $imbalance > 5 && $spread_pct < 0.1
                // && $data[$index]['obv'] > $data[$index - 1]['obv']

                // $macdLongConditionLoop

                // && $data[$index]['histogram'] < 0
                // && $data[$index - 1]['histogram'] < 0
                // && $data[$index - 2]['histogram'] < 0
                // && $data[$index - 3]['histogram'] < 0

                // && $data[$index - 1]['volume'] < $data[$index - 2]['volume']
                // && $data[$index - 2]['volume'] > $data[$index - 3]['volume']
                // && $data[$index]['per'] > 0
                // && $data[$index - 1]['per'] < 0
                // && $data[$index - 2]['per'] < 0
                // && $data[$index - 3]['per'] < 0
                && $data[$index]['per'] > 0
                && $data[$index - 1]['close'] > $supportResistanceFirst['resistance']
                && $data[$index - 1]['open'] < $supportResistanceFirst['resistance']
                && $data[$index]['J'] > $data[$index]['K']
                && $data[$index]['J'] > $data[$index]['D']

                // && $data[$index]['mfi'] < 30
                // && $orderBookSnapshot->volume_imbalance > 1
            ;



            if ($buyLongConditionInitial) {
                // return 'LONG';
                self::insertConfirmBasicTradeEntry($symbol, $data, $index);
                // return null;
            }
        }














        // -------------------------------------------------------------
        if (self::checkConfirmTradeValidity($symbol, $data, $index)) {



            // Handles MA 5 condition and checks for validity , will update only if index is valid and already no value is assigned
            self::updateVolumeMA5ConditionLong($symbol, $data, $index);

            $bollConditions = self::checkBollingerBandCrossing($data, $index);


            if (
                self::checkMA5Validity($symbol, $data, $index)
                && $data[$index]['per'] > 0


                && $data[$index]['dif'] > max($data[$index - 1]['dif'], $data[$index - 2]['dif'])
                // && $data[$index]['obv'] > min($data[$index - 1]['obv'], $data[$index - 2]['obv'])
                && $bollConditions
                && $bollConditions['position'] === 'LONG'

                // && $data[$index]['close'] > $data[$index]['bb_middle']
                // && $data[$index]['open'] < $data[$index]['bb_middle']

            ) {
                $buyLongCondition = self::confirmOpening($symbol, $data, $index);
            }
        }
        // ###############################################################################



        // Long condition
        if (
            $buyLongCondition

        ) {
            return self::$longEnabled ? 'LONG' : null;
        }

        // No conditions met so return null
        return null;
    }




    public static function handleClosingConditions($symbol, $data, $index, $tradeType, $openingIndex, $open_price)
    {

        $candle = $data[$index];
        $closingPrice = 0;
        $waitingCandlesBeforeStopLoss = intval(self::$stopLossWaitingDuration / CommonHelpers::$binanceIntervals[self::$interval]);
        if ($tradeType == 'SHORT') {
            // Calculate Closing in profit 
            if ($candle['low'] <= $open_price * (1 - self::$targetProfit / 100)) {
                $closingPrice = $candle['low'];
            } else if ($index - $openingIndex  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss && $open_price < $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        } else if ($tradeType == 'LONG') {

            // Calculate Closing in profit 
            if ($candle['high'] >= $open_price * (1 + self::$targetProfit / 100)) {
                $closingPrice = $candle['high'];
            } else if (
                ($index - $openingIndex  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss && $open_price > $data[$index]['close'])
                ||
                ($data[$index]['close'] > $open_price && ($data[$index]['high'] - max($data[$index]['close'], $data[$index]['open']) > min($data[$index]['close'], $data[$index]['open']) - $data[$index]['low']) && abs($data[$index]['per']) < 0.05)

            ) {
                $closingPrice = $data[$index]['close'];
            }
        }


        return $closingPrice;
    }


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

    public static function checkBollingerBandCrossing($data, $index, $candleSpan = 10)
    {

        // Detecting LONG
        if ($data[$index]['close'] > $data[$index]['bb_upper']  && $data[$index]['open'] < $data[$index]['bb_upper'] && $index > $candleSpan) {

            $currentDiff = CommonHelpers::getPercentDiff($data[$index]['bb_lower'], $data[$index]['bb_upper']);

            $minDiff = $currentDiff;
            $minIndex = $index;
            for ($i = $index; $i >= $index - $candleSpan; $i--) {
                if ($minDiff > CommonHelpers::getPercentDiff($data[$i]['bb_lower'], $data[$i]['bb_upper'])) {
                    $minDiff = CommonHelpers::getPercentDiff($data[$i]['bb_lower'], $data[$i]['bb_upper']);
                    $minIndex = $i;
                }
            }

            return [
                'current_difference' => $currentDiff,
                'current_index' => $index,
                'current_close_price' => $data[$index]['close'],
                'min_difference' => $minDiff,
                'min_index' => $minIndex,
                'current_close_price' => $data[$index]['close'],
                'position' => 'LONG',


            ];
        }
        // Detecting SHORT

        else if ($data[$index]['close'] < $data[$index]['bb_lower']  && $data[$index]['open'] > $data[$index]['bb_lower'] && $index > $candleSpan) {

            $currentDiff = CommonHelpers::getPercentDiff($data[$index]['bb_lower'], $data[$index]['bb_upper']);

            $minDiff = $currentDiff;
            $minIndex = $index;
            for ($i = $index; $i >= $index - $candleSpan; $i--) {
                if ($minDiff > CommonHelpers::getPercentDiff($data[$i]['bb_lower'], $data[$i]['bb_upper'])) {
                    $minDiff = CommonHelpers::getPercentDiff($data[$i]['bb_lower'], $data[$i]['bb_upper']);
                    $minIndex = $i;
                }
            }

            return [
                'current_difference' => $currentDiff,
                'current_index' => $index,
                'current_close_price' => $data[$index]['close'],
                'min_difference' => $minDiff,
                'min_index' => $minIndex,
                'current_close_price' => $data[$index]['close'],
                'min_close_price' => $data[$minIndex]['close'],
                'position' => 'SHORT',
            ];
        } else {
            return null;
        }
    }



    // #########################Functions for confirmed Trades table###############################

    public static function insertConfirmBasicTradeEntry($symbol, $data, $index)
    {

        $reportId = self::getFormulaId(self::$formula);
        DB::table('confirmed_trades')->insert([
            'report_id' => $reportId,
            'coin_name' => $symbol,
            'confirm_candle_index' => $index,
            'candles_to_check' => self::$candlesToCheck,
            'trade_confirmed' => 1,
            'update_time' => Carbon::now()->toDateTimeString(),

        ]);
    }


    public static function checkConfirmTradeValidity($symbol, $data, $index)
    {

        $reportId = self::getFormulaId(self::$formula);
        $lastEntry = DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->first();
        if (
            !$lastEntry
        ) {
            return null;
        }
        if ($index > ($lastEntry->confirm_candle_index + $lastEntry->candles_to_check)) {
            DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->delete();
            return null;
        }
        return $lastEntry;
    }


    public static function checkMA5Validity($symbol, $data, $index)
    {

        $reportId = self::getFormulaId(self::$formula);
        $lastEntry = DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->first();
        if (
            !$lastEntry
            ||
            $index > ($lastEntry->confirm_candle_index + $lastEntry->candles_to_check)

        ) {
            return false;
        }

        // Return true if index is within last confirm trade bounds for MA5

        if (
            $lastEntry->volume_ma5_confirmed
            && $index <= ($lastEntry->volume_ma5_confirm_index + $lastEntry->volume_ma5_valid_for)
            && $index >= ($lastEntry->volume_ma5_confirm_index)
        ) {
            return true;
        } else {
            DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->update(
                [
                    'volume_ma5_confirmed' => 0,
                    'volume_ma5_confirm_index' => null,
                    'volume_ma5_valid_for' => null,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]
            );
            return false;
        }
    }


    public static function updateVolumeMA5ConditionLong($symbol, $data, $index)
    {
        $reportId = self::getFormulaId(self::$formula);

        $lastEntry = DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->first();
        if ($data[$index]['volumeMA5'] > min($data[$index - 1]['volumeMA5'], $data[$index - 2]['volumeMA5']) && !$lastEntry->volume_ma5_confirmed) {
            DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->update(
                [
                    'volume_ma5_confirmed' => 1,
                    'volume_ma5_confirm_index' => $index,
                    'volume_ma5_valid_for' => self::$volumeMA5ValidFor,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]
            );
            return true;
        } else {
            return false;
        }
    }
    public static function updateVolumeMA5ConditionShort($symbol, $data, $index)
    {
        $reportId = self::getFormulaId(self::$formula);

        $lastEntry = DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->first();
        if ($data[$index]['volumeMA5'] > min($data[$index - 1]['volumeMA5'], $data[$index - 2]['volumeMA5']) && !$lastEntry->volume_ma5_confirmed) {
            DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->update(
                [
                    'volume_ma5_confirmed' => 1,
                    'volume_ma5_confirm_index' => $index,
                    'volume_ma5_valid_for' => self::$volumeMA5ValidFor,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]
            );
            return true;
        } else {
            return false;
        }
    }


    public static function confirmOpening($symbol, $data, $index)
    {
        $reportId = self::getFormulaId(self::$formula);

        DB::table('confirmed_trades')->where('report_id', $reportId)->where('coin_name', $symbol)->orderBy('update_time', 'DESC')->delete();
        return true;
    }
}
