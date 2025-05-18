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
    public static $supportResistanceCandleSpan = 10;
    public static $backTestTimeUnix = 1746126000000;
    // public static $backTestTimeUnix = 1745175600000;

    public static $interval = '5m';
    public static $targetProfit = 0.4;
    public static $stopLoss = 1;
    public static $stopLossWaitingDuration = 0;
    public static $longEnabled = true;
    public static $shortEnabled = false;
    public static $formula = 'Long Internal Report';
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

    public static $candlesToCheck = 1000;
    public static $volumeMA5ValidFor = 1000;
    public static $upperWickValidFor = 1000;
    public static $bollSqueezValidFor = 1000;



    public static function generateCoinReport(
        $cmd = null
    ) {

        $tradesTotal = [];
        $coinsQuery = DB::table('coins')->where('market', 'FUTURE')->where('status', 'T')


            // ->whereIn(
            //     'symbol',
            //     [

            //     ]
            // )
        ;


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
                // dd($e);
                $cmd->error('Error Occured: ', $e->getMessage());
                Log::error("Failed to update coin reports: " . $e->getMessage());
            }
            CommonHelpers::delayMS(self::$delayMs);
        }

        $cmd->info('Completed Report for : ' . self::$formula);
        $cmd->info('Total Coins Processed : ' . count($coins));
    }

    public static function addFormulaDetails()
    {
        self::$formula = self::$formula . ' - ' . Carbon::now()->format('l, F j, Y h:i A');
        $date = date('Y-m-d H:i:s');

        $dateRange = null;
        if (self::$backTestTimeUnix) {
            $diffInMins = CommonHelpers::$binanceIntervals[self::$interval];

            $startUnix = self::$backTestTimeUnix;
            $endUnix = self::$backTestTimeUnix + ($diffInMins * 60 * 1000 * 1000);

            // Get current time in milliseconds
            $currentUnixMillis = round(microtime(true) * 1000);

            // If end time is in the future, use current time instead
            if ($endUnix > $currentUnixMillis) {
                $endUnix = $currentUnixMillis;
            }

            // Convert milliseconds to seconds for formatting
            $startDateStr = date('F j, Y', $startUnix / 1000);
            $endDateStr = date('F j, Y', $endUnix / 1000);

            $dateRange = $startDateStr . ' to ' . $endDateStr;
        }




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
                                <td><i class="tim-icons icon-calendar-60"></i> <strong>Date Range:</strong></td>
                                <td>' . ($dateRange ?? 'Not set') . '</td>
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
            if ($index < 1200) {
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

                $tradeType = self::handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $trades);






                if (
                    $tradeType
                ) {


                    $candle['should_buy'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                    $candle['openingVolumes'] = json_encode($volumeSignal);

                    $open_price = $candle['close'];


                    $candle['trendDetails'] = json_encode(CommonHelpers::detectTrend($data, $index, 50, 50));
                    $currentTrade['buyingCandle'] = json_encode($candle);
                    $currentTrade['previousCandle'] = json_encode($data[$index - 1]);
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



                    // ############## Indicator Logs ###################


                    // $max = $data[$openingIndex]['histogram'];
                    // $min =  $data[$openingIndex]['histogram'];
                    // $sensitivity = 5;
                    // for ($i = $openingIndex; $i >= $openingIndex - $sensitivity; $i--) {

                    //     if ($max <   $data[$i]['histogram']) {
                    //         $max =  $data[$i]['histogram'];
                    //     }

                    //     if ($min >  $data[$i]['histogram']) {
                    //         $min =  $data[$i]['histogram'];
                    //     }
                    // }

                    // $macdVolatility = CommonHelpers::getPercentDiff($min, $max);



                    // CommonHelpers::insertIndicatorLogs($symbol, 'macd_volatility_10_candles', $macdVolatility, $currentTrade['profit'], $currentTrade['duration']);
                    // #################################################



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

        self::confirmOpening($symbol, $data, $index);
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

    public static function handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $trades)
    {



        $buyLongCondition = false;

        $obvLookBack = 5;

        //################################################# STAGE 1 #################################################


        if (self::masterAllowTrades($data, $index, $symbol, $trades) || true) {

            // $volumeAbnormality = $data[$index]['volume'] > $data[$index]['volumeMA5'] * 3;

            if ($data[$index]['rsi6'] < 30 && !self::checkConfirmTradeValidity($symbol, $data, $index)) {
                self::insertConfirmBasicTradeEntry($symbol, $data, $index);
            }

            if (self::checkConfirmTradeValidity($symbol, $data, $index)) {

                $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
                $buyCondition = $data[$index]['close'] > $data[$index]['bb_lower']
                    && $data[$index]['open'] < $data[$index]['bb_lower']
                    && $data[$index]['stoch_d'] > $data[$index - 1]['stoch_d']
                    && $data[$index]['stoch_k'] > $data[$index - 1]['stoch_k']
                    && $bbAnalysis['price_action']['is_near_lower_band']
                    && !$bbAnalysis['bb_squeeze']
                    && $data[$index]['histogram'] > $data[$index - 1]['histogram'];

                if ($buyCondition) {
                    self::confirmOpening($symbol, $data, $index);
                    // $data30m = BinanceApiService::getCandleStickDataPast($symbol, '1h', 500, $data[$index]['binance_timestamp'], 'FUTURE');
                    // $index30m = count($data30m) - 2;
                    // if (
                    //     !(
                    //         max($data30m[$index30m]['open'], $data30m[$index30m]['close']) > $data30m[$index30m]['bb_middle']

                    //     )
                    // ) {
                    //     return null;
                    // }
                    return 'LONG';
                }
            }
        }

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
            } else if ($index - $openingIndex  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss && $open_price > $data[$index]['close']) {
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
        if ($data[$index]['close'] > $data[$index]['bb_middle']  && $data[$index]['open'] < $data[$index]['bb_middle'] && $index > $candleSpan) {

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

    public static function masterAllowTrades($data, $index, $symbol, $trades)
    {

        $allowTrades = false;
        if (empty($trades)) {
            $allowTrades = true;
        } else {

            $lastTrade = $trades[count($trades) - 1];

            $closingTimestamp = json_decode($lastTrade['sellingCandle'], true)['binance_timestamp'];

            $closingIndex = self::getIndexDiffFromTimestamps($closingTimestamp, $data[$index]['binance_timestamp'], self::$interval, true);

            $closingIndex = $index - $closingIndex;


            $bollingerDecreaseCount = 0;
            $bollingerIncreaseCount = 0;
            $bullishCount = 0;
            $berishCount = 0;

            for ($i = $closingIndex; $i <= $index; $i++) {

                if ($data[$index]['bb_middle'] < $data[$index]['bb_middle']) {
                    $bollingerDecreaseCount++;
                } else {
                    $bollingerIncreaseCount++;
                }


                if ($data[$index]['per'] > 0) {
                    $bullishCount++;
                } else {
                    $berishCount++;
                }
            }


            if (
                $bollingerDecreaseCount > $bollingerIncreaseCount
                && ($bollingerDecreaseCount + $bollingerIncreaseCount) > 5
                && $bullishCount > $berishCount
                && ($bullishCount + $berishCount) > 5
            ) {
                $allowTrades = true;
            }
        }


        return $allowTrades;
    }
    public static function updateBollSqueezCondition($symbol, $data, $index)
    {
        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }
        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();

        $highestPointIndex = self::getTightestSqueezIndex($data, $index);
        $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);
        $bbDiffConfirmed = CommonHelpers::getPercentDiff($data[$index]['bb_lower'], $data[$index]['bb_upper']);
        $bbDiff = ($bbDiffConfirmed - $bbDiffHighest) / max(0.0001, $bbDiffHighest) * 100;

        $currentSqueez = CommonHelpers::getPercentDiff($data[$index]['bb_lower'], $data[$index]['bb_middle']);
        $prevSqueez = CommonHelpers::getPercentDiff($data[$index - 1]['bb_lower'], $data[$index - 1]['bb_middle']);

        $squeezDiff = CommonHelpers::getPercentDiff($prevSqueez, $currentSqueez, true);




        $maxExpandSqueezDiff = CommonHelpers::getPercentDiff($lastEntry->bolling_max_expanded_value, $currentSqueez, true);

        $bbLowerLineCandles = self::checkBBLowerLineCount($data, $index, 3);
        $additionalConditions = $data[$index]['per'] > 0 && $bbLowerLineCandles == 0 && $maxExpandSqueezDiff < 0 && $data[$index]['dif'] >= $data[$index - 1]['dif'];

        // $middleBandCrossover = $data[$index]['close'] > $data[$index]['bb_middle'] && $data[$index]['open'] < $data[$index]['open'];
        // Update only if Expansion is happened befor squeez check and is also valid

        if ($squeezDiff < 0 && !$lastEntry->bolling_squeezed_confirmed && $additionalConditions) {


            DB::table('confirmed_trades')->where('ict_id', $ictId)->update(
                [
                    'bolling_squeezed_confirmed' => 1,
                    'bolling_squeezed_timestamp' => $data[$index]['binance_timestamp'],
                    'bolling_squeezed_valid_for' => self::$bollSqueezValidFor,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]
            );
            return true;
        } else {
            return false;
        }
    }
    public static function checkBBLowerLineCount($data, $index, $count = 3)
    {
        $lowerLineCandlesCount = 0;

        for ($i = $index; $i > $index - $count; $i--) {
            if (max($data[$i]['open'], $data[$i]['close']) >= $data[$i]['bb_lower'] &&  min($data[$i]['open'], $data[$i]['close']) <= $data[$i]['bb_lower']) {
                $lowerLineCandlesCount++;
            }
        }

        return $lowerLineCandlesCount;
    }
    public static function checkBollSqueezValidity($symbol, $data, $index)
    {
        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }
        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->bolling_squeezed_timestamp, self::$interval);
        if (!$indexDiff) {
            return false;
        }
        if ($indexDiff > $lastEntry->bolling_squeezed_valid_for) {
            DB::table('confirmed_trades')->where('ict_id', $ictId)->update(
                [
                    'bolling_squeezed_confirmed' => 0,
                    'bolling_squeezed_timestamp' => null,
                    'bolling_squeezed_valid_for' => null,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]
            );
            return false;
        } else {
            return true;
        }
    }

    public static function checkShortOpening($symbol, $data, $index)
    {

        $currentExpand = CommonHelpers::getPercentDiff($data[$index]['bb_lower'], $data[$index]['bb_upper']);
        $prevExpand = CommonHelpers::getPercentDiff($data[$index - 1]['bb_lower'], $data[$index - 1]['bb_upper']);
        $expandDiff = $currentExpand - $prevExpand;

        /* 
            1. If while expanding after squeez BB up is decreasing
            2. RSI is below 20 then skip short
        */
        $skipConditionShort = $data[$index]['bb_upper'] < $data[$index - 1]['bb_upper'] && $data[$index]['rsi6'] < 20;



        // Lower Line crossing after bb_expand
        if (
            self::checkBollSqueezValidity($symbol, $data, $index)
            && $data[$index]['close'] <= $data[$index]['bb_lower']
            && $expandDiff > 0
            && !$skipConditionShort
            && self::checkDifDeaCrossoverFromAbove($data, $index, 6)
            && $data[$index]['wr'] > -92


        ) {
            self::confirmOpening($symbol, $data, $index);
            return true;
        }




        return false;
    }
    public static function checkVolatility($data, $index, $sensitivity)
    {
        $max = max($data[$index]['close'], $data[$index]['open']);
        $min = min($data[$index]['close'], $data[$index]['open']);

        for ($i = $index; $i >= $index - $sensitivity; $i--) {

            if ($max <  max($data[$i]['close'], $data[$i]['open'])) {
                $max = max($data[$i]['close'], $data[$i]['open']);
            }

            if ($min >  min($data[$i]['close'], $data[$i]['open'])) {
                $min = min($data[$i]['close'], $data[$i]['open']);
            }
        }

        return CommonHelpers::getPercentDiff($min, $max);
    }

    public static function checkDifDeaCrossoverFromAbove($data, $index, $distance)
    {


        for ($i = $index; $i >= $index - $distance; $i--) {
            if ($data[$index]['dif'] < $data[$index]['dea'] && $data[$index - 1]['dif'] > $data[$index - 1]['dea']) {
                return true;
            }
        }
        return false;
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

    public static function getIndexDiffFromTimestamps($timestamp1, $timestamp2, $interval, $rounded = true)
    {
        if (!($timestamp1 && $timestamp2)) {
            return false;
        }
        $intervalToMins = CommonHelpers::$binanceIntervals[$interval];
        $diff = abs($timestamp1 - $timestamp2) / (60 * 1000 * $intervalToMins);
        return $rounded ? intval($diff) : $diff;
    }


    public static function insertConfirmBasicTradeEntry($symbol, $data, $index)
    {

        $reportId = self::getFormulaId(self::$formula);

        // BB Calculations for highest point squeez
        $highestPointIndex = self::getTightestSqueezIndex($data, $index);
        $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);



        $id =  DB::table('confirmed_trades')->insertGetId([
            'report_id' => $reportId,
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
    public static function checkConfirmTradeValidity($symbol, $data, $index)
    {
        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }

        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();
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


    public static function checkMA5Validity($symbol, $data, $index)
    {

        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }

        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->volume_ma5_confirm_timestamp, self::$interval);
        if (!$indexDiff) {
            return false;
        }
        if ($indexDiff > $lastEntry->volume_ma5_valid_for) {
            DB::table('confirmed_trades')->where('ict_id', $ictId)->update(
                [
                    'volume_ma5_confirmed' => 0,
                    'volume_ma5_confirm_timestamp' => null,
                    'volume_ma5_valid_for' => null,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]
            );
            return false;
        } else {
            return true;
        }
    }

    public static function checkUpperWickValidity($symbol, $data, $index)
    {

        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }

        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->bolling_up_wick_timestamp, self::$interval);
        if (!$indexDiff) {
            return false;
        }
        if ($indexDiff > $lastEntry->bolling_up_wick_valid_for) {
            DB::table('confirmed_trades')->where('ict_id', $ictId)->update(
                [
                    'bolling_up_wick' => 0,
                    'bolling_up_wick_timestamp' => null,
                    'bolling_up_wick_valid_for' => null,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]
            );
            return false;
        } else {
            return true;
        }
    }
    public static function updateUpperWickCondition($symbol, $data, $index)
    {
        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }
        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();


        $isUpperWick = false;
        $upperWickHight = $data[$index]['high'] - max($data[$index]['open'], $data[$index]['close']);
        $solidRegion = max($data[$index]['open'], $data[$index]['close']) - min($data[$index]['open'], $data[$index]['close']);


        if ($data[$index]['close'] > $data[$index]['bb_upper'] && $data[$index]['open'] < $data[$index]['bb_upper'] && $upperWickHight > $solidRegion) {
            $isUpperWick = true;
        }
        if ($data[$index - 1]['high'] > $data[$index - 1]['bb_upper'] && $data[$index - 1]['close'] < $data[$index - 1]['bb_upper']) {
            $isUpperWick = true;
        }

        if ($isUpperWick && !$lastEntry->bolling_up_wick) {

            DB::table('confirmed_trades')->where('ict_id', $ictId)->update(
                [
                    'bolling_up_wick' => 1,
                    'bolling_up_wick_timestamp' => $data[$index]['binance_timestamp'],
                    'bolling_up_wick_valid_for' => self::$upperWickValidFor,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]
            );
            return true;
        } else {
            return false;
        }
    }


    public static function updateVolumeMA5Condition($symbol, $data, $index)
    {
        $ictId = self::getIctId($symbol);
        if (
            !$ictId
        ) {
            return null;
        }
        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();
        if ($data[$index]['volumeMA5'] > $data[$index - 1]['volumeMA5'] && !$lastEntry->volume_ma5_confirmed) {
            DB::table('confirmed_trades')->where('ict_id', $ictId)->update(
                [
                    'volume_ma5_confirmed' => 1,
                    'volume_ma5_confirm_timestamp' => $data[$index]['binance_timestamp'],
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
