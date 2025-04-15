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

class ReportService
{


    // Essential Properties
    public static $delayMs = 10;
    public static $supportResistanceCandleSpan = 5;
    public static $backTestTimeUnix = null;

    public static $interval = '5m';
    public static $targetProfit = 0.5;
    public static $stopLoss = 0.8;
    public static $stopLossWaitingDuration = 20;
    public static $longEnabled = true;
    public static $shortEnabled = false;
    public static $formula = 'Internal Report';
    public static $earlyClosingEnabled = true;

    // Coin Selection Filters
    public static $coinLimit = 0; // Use 0 for all coins
    public static $shuffleCoins = true;


    public static $filterOnCoinType = true;
    public static $coinTypeMetaverse = true;
    public static $coinTypeAlt = false;
    public static $coinTypeMeme = false;
    public static $coinTypeDefi = false;
    public static $coinTypeNft = false;
    public static $coinTypeWeb3 = false;





    public static function addFormulaDetails()
    {
        self::$formula = self::$formula . ' - ' . Carbon::now()->format('l, F j, Y h:i A');
        $date = date('Y-m-d H:i:s');

        $html = '
        <div class="card card-chart">
            <div class="card-header">
                <h5 class="card-category text-warning">Formula Report</h5>
                <h4 class="card-title text-white">' . self::$formula . '</h4>
                <p class="card-category text-muted">Generated on ' . $date . '</p>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush text-white">
                    <li class="list-group-item bg-transparent">⏱️ <strong>Interval:</strong> ' . self::$interval . '</li>
                    <li class="list-group-item bg-transparent">💰 <strong>Target Profit:</strong> ' . self::$targetProfit . '%</li>
                    <li class="list-group-item bg-transparent">🔻 <strong>Stop Loss:</strong> ' . self::$stopLoss . '%</li>
                    <li class="list-group-item bg-transparent">⏳ <strong>Stop Loss Wait Duration:</strong> ' . self::$stopLossWaitingDuration . ' minutes</li>
                    <li class="list-group-item bg-transparent">📊 <strong>Support/Resistance Candle Span:</strong> ' . self::$supportResistanceCandleSpan . '</li>
                    <li class="list-group-item bg-transparent">🕒 <strong>Delay (ms):</strong> ' . self::$delayMs . ' ms</li>
                    <li class="list-group-item bg-transparent">📉 <strong>Backtest Time (Unix):</strong> ' . (self::$backTestTimeUnix ?? 'Not set') . '</li>
                    <li class="list-group-item bg-transparent">📈 <strong>Long Position Enabled:</strong> ' . (self::$longEnabled ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">📉 <strong>Short Position Enabled:</strong> ' . (self::$shortEnabled ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">⏩ <strong>Early Closing Enabled:</strong> ' . (self::$earlyClosingEnabled ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">💰 <strong>Coin Limit:</strong> ' . self::$coinLimit . '</li>
                    <li class="list-group-item bg-transparent">🧮 <strong>Filter on Coin Type:</strong> ' . (self::$filterOnCoinType ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">🌐 <strong>Metaverse:</strong> ' . (self::$coinTypeMetaverse ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">📉 <strong>Alt:</strong> ' . (self::$coinTypeAlt ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">😂 <strong>Meme:</strong> ' . (self::$coinTypeMeme ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">📈 <strong>DeFi:</strong> ' . (self::$coinTypeDefi ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">🎨 <strong>NFT:</strong> ' . (self::$coinTypeNft ? 'Yes' : 'No') . '</li>
                    <li class="list-group-item bg-transparent">🌍 <strong>Web3:</strong> ' . (self::$coinTypeWeb3 ? 'Yes' : 'No') . '</li>
                </ul>
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
        $volumeSignals = CommonHelpers::getVolumeSignals($symbol, self::$interval, true, $data[0]['binance_timestamp'], 1000);

        foreach ($data as $index => $candle) {
            $volumeIndex = $index - 1000;

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

                $tradeType = self::handleOpeningConditions($symbol, $data, $index, $volumeSignals, $volumeIndex, $supportResistance, $orderBookSnapshot);

                if (
                    $tradeType
                ) {


                    $candle['should_buy'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot->id;
                    $candle['openingVolumes'] = json_encode($volumeSignals[$volumeIndex]);

                    $open_price = $candle['close'];
                    $currentTrade['buyingCandle'] = json_encode($candle);
                    $extremePrice = $open_price;
                    // Placeholder object for testing

                    $openingIndex = $index;
                }
            } else {
                $closingPrice =  self::handleClosingConditions($symbol, $data, $index, $volumeSignals, $volumeIndex, $tradeType, $openingIndex, $open_price);

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
                    $candle['closingVolumes'] = json_encode($volumeSignals[$volumeIndex]);

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

    public static function handleOpeningConditions($symbol, $data, $index, $volumeSignals, $volumeIndex, $supportResistance, $orderBookSnapshot)
    {

        if ($volumeIndex < 4)
            return null;
        if (!$orderBookSnapshot)
            return null;


        $volumeSignal = $volumeSignals[$volumeIndex];






        $imbalance = ($orderBookSnapshot->bid_volume - $orderBookSnapshot->ask_volume) / ($orderBookSnapshot->bid_volume + $orderBookSnapshot->ask_volume) * 100;
        $spread_pct = ($orderBookSnapshot->lowest_ask - $orderBookSnapshot->highest_bid) / (($orderBookSnapshot->lowest_ask + $orderBookSnapshot->highest_bid) / 2) * 100;


        // Volume Indicators
        $mfi = $volumeSignal['indicators']['mfi_current'];
        $cvd = $volumeSignal['indicators']['cvd_current'];
        $obv = $volumeSignal['indicators']['obv_current'];
        $obv_previous = $volumeSignals[$volumeIndex - 1]['indicators']['obv_current'];
        $vwap = $volumeSignal['indicators']['vwap_current'];
        // dd($volumeSignal['indicators']);
        $priceLong = $orderBookSnapshot->lowest_ask; // For LONG
        $priceShort = $orderBookSnapshot->highest_bid; // For LONG






        // Short condition
        if (
            $imbalance < -5 && $spread_pct < 0.01
            && $obv < $obv_previous
            && $mfi > 80
            && $data[$index]['K'] > 70
            && $data[$index]['J'] < $data[$index]['K'] && $data[$index]['J'] < $data[$index]['D']
            // && $data[$index]['close'] < $supportResistance['resistance']
            // && $data[$index]['open'] > $supportResistance['resistance']
        ) {
            return  self::$shortEnabled ? 'SHORT' : null;
        }



        // Long condition
        if (
            // $imbalance > 20 && $spread_pct < 0.01
            $obv > $volumeSignals[$volumeIndex - 1]['indicators']['obv_current']
            && $volumeSignals[$volumeIndex - 1]['indicators']['obv_current'] > $volumeSignals[$volumeIndex - 2]['indicators']['obv_current']
            // && $mfi < 20

            
            && $data[$index]['K'] < 30
            && $data[$index]['J'] > $data[$index]['K'] && $data[$index]['J'] > $data[$index]['D']
            && $data[$index]['close'] > $supportResistance['support']
            && $data[$index]['open'] < $supportResistance['support']
        ) {
            return self::$longEnabled ? 'LONG' : null;
        }


        // No conditions met so return null
        return null;
    }




    public static function handleClosingConditions($symbol, $data, $index, $volumeSignals, $volumeIndex, $tradeType, $openingIndex, $open_price)
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

        $start = max(0, $end - $length); // make sure we don’t go negative
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
}
