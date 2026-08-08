<?php

namespace App\Services\InternalTrader;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportServiceV2
{
    // Essential Properties
    public static $delayMs = 10;
    public static $interval = '1h';
    public static $longEnabled = true;
    public static $shortEnabled = true;

    // Backtest & Formula
    public static $backTestTimeUnix;
    public static $formula;
    public static $baseReportFormula;
    public static $isBaseReport = true;
    public static $leftovers = [];
    public static $waitingCandles = 0;
    public static $formulaType = null;
    public static $openingIndex = 0;
    public static $extremePrice = null;
    public static $trades = null;
    public static $currentTrade = null;
    public static $open_price = null;
    public static $tradeType = null;

    // Dynamic TP/SL
    public static $dynamicTP = 0;
    public static $dynamicSL = 0;

    // Entry tag
    public static $currentTagName = null;

    // Limits
    public static $limit = 1000;
    public static $initialWaitingCandles = 20;

    // Cooldown & Safety
    public static $consecutiveLosses = [];
    public static $consecutiveLossTimestamp = [];

    // Coins
    public static $formulaACoins = [
        'SOLUSDT',
        'SUIUSDT',
    ];

    public static $formulaBCoins = [
        'SOLUSDT',
        'SUIUSDT',
    ];

    // ###############################################################################
    //                         Entry Point
    // ###############################################################################

    public static function generateCoinReport(
        $cmd = null,
        $formula = 'Default',
        $timestamp = null,
        $baseReportFormula = '',
        $baseReport = true
    ) {
        $coins = array_values(array_unique(array_merge(self::$formulaACoins, self::$formulaBCoins)));

        self::$formula = $formula;
        self::$backTestTimeUnix = $timestamp;
        self::$baseReportFormula = $baseReportFormula;
        self::$isBaseReport = $baseReport;
        self::$consecutiveLosses = [];
        self::$consecutiveLossTimestamp = [];

        system('clear');
        $cmd->info('Processing: 0 %');
        self::addFormulaDetails();

        foreach ($coins as $index => $coin) {
            try {
                $symbol = $coin;
                $data = null;
                $maxRetries = 3;

                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    try {
                        $data = BinanceApiService::getCandleStickDataExtended($symbol, self::$interval, self::$limit, self::$backTestTimeUnix, 'FUTURE');
                        if (!empty($data) && is_array($data) && !is_null($data[0]['binance_timestamp'] ?? null)) {
                            break;
                        }
                        $cmd->warn("Attempt {$attempt}/{$maxRetries}: Empty data for {$symbol}, retrying...");
                        CommonHelpers::delayMS(2000);
                    } catch (\Exception $retryEx) {
                        if ($attempt === $maxRetries) throw $retryEx;
                        $cmd->warn("Attempt {$attempt}/{$maxRetries}: API failed for {$symbol} ({$retryEx->getMessage()}), retrying...");
                        CommonHelpers::delayMS(2000);
                    }
                }

                self::$trades = self::processCandles($symbol, $data);
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', self::$interval)->where('formula', self::$formula)->where('market', 'FUTURE')->delete();

                foreach (self::$trades as $trade) {
                    DB::table('coin_reports')->insert($trade);
                    CommonHelpers::delayMS(5);
                }

                $perProgress = (($index + 1) / count($coins)) * 100;
                $cmd->info('Processing: ' . round($perProgress) . ' % (' . count(self::$trades) . ' trades for ' . $symbol . ')');
                DB::table('formula_details')->where('formula', self::$formula)->update([
                    'progress' => $perProgress,
                ]);
            } catch (\Exception $e) {
                $cmd->error('Error Occured for ' . $coin . ': ' . $e->getMessage());
                Log::error("V2 Failed to update coin reports for {$coin}: " . $e->getMessage());
            }
            CommonHelpers::delayMS(self::$delayMs);
        }

        $cmd->info('Completed Report for : ' . self::$formula);
        $cmd->info('Total Coins Processed : ' . count($coins));
        return self::$formula;
    }

    // ###############################################################################
    //                         Candle Processing Loop
    // ###############################################################################

    protected static function processCandles($symbol, $data)
    {
        self::$waitingCandles = 0;
        self::$openingIndex = 0;
        self::$open_price = 0;
        self::$tradeType = null;
        self::$currentTrade = [];
        self::$trades = [];
        self::$dynamicTP = 0;
        self::$dynamicSL = 0;
        self::$currentTagName = null;

        foreach ($data as $index => $candle) {
            if ($index < self::$initialWaitingCandles) {
                continue;
            }

            if (self::$waitingCandles) {
                self::$waitingCandles--;
                continue;
            }

            if (self::$open_price == 0) {
                self::$tradeType = self::handleOpeningConditions($symbol, $data, $index);

                if (self::$tradeType) {
                    $key = $symbol . '_' . self::$tradeType;
                    if ((self::$consecutiveLosses[$key] ?? 0) >= 3) {
                        $intervalMs = (CommonHelpers::$binanceIntervals[self::$interval] ?? 15) * 60 * 1000;
                        $candlesSince = ($data[$index]['binance_timestamp'] - (self::$consecutiveLossTimestamp[$key] ?? 0)) / $intervalMs;
                        if ($candlesSince < 24) {
                            self::resetTradeParams();
                            continue;
                        }
                    }

                    $atrPercent = self::getATRPercent($data, $index);
                    $slPct = max(0.8, min(3.0, $atrPercent * 1.0));
                    $tpPct = $slPct * 2.0;

                    if (self::$tradeType === 'LONG') {
                        self::$dynamicTP = $candle['close'] * (1 + $tpPct / 100);
                        self::$dynamicSL = $candle['close'] * (1 - $slPct / 100);
                    } else {
                        self::$dynamicTP = $candle['close'] * (1 - $tpPct / 100);
                        self::$dynamicSL = $candle['close'] * (1 + $slPct / 100);
                    }

                    self::$currentTrade = [
                        'buyingCandle'     => json_encode($candle),
                        'previousCandle'   => json_encode($data[$index - 1]),
                        'openingTimestamp' => $data[$index]['binance_timestamp'],
                        'symbol'           => $symbol,
                        'interval'         => self::$interval,
                        'market'           => 'FUTURE',
                        'position'         => self::$tradeType,
                        'formula'          => self::$formula,
                        'tagName'          => self::$currentTagName,
                        'buyingPrice'      => $candle['close'],
                        'liquidationPrice' => 0,
                    ];

                    self::$open_price = $candle['close'];
                    self::$extremePrice = self::$open_price;
                    self::$openingIndex = $index;
                }
            } else {
                $closingPrice = self::handleClosingConditions($symbol, $data, $index);

                if (self::$tradeType === 'SHORT') {
                    self::$extremePrice = max(array_column(array_slice($data, self::$openingIndex, $index - self::$openingIndex + 1), 'high'));
                } else {
                    self::$extremePrice = min(array_column(array_slice($data, self::$openingIndex, $index - self::$openingIndex + 1), 'low'));
                }

                if ($closingPrice) {
                    $profit = self::$tradeType === 'LONG'
                        ? round(($closingPrice - self::$open_price) / self::$open_price * 100, 2)
                        : round((self::$open_price - $closingPrice) / self::$open_price * 100, 2);

                    self::$currentTrade['sellingCandle'] = json_encode($candle);
                    self::$currentTrade['sellingPrice'] = $closingPrice;
                    self::$currentTrade['profit'] = $profit;
                    self::$currentTrade['lowestPrice'] = self::$extremePrice;
                    self::$currentTrade['lowestPricePercentage'] = abs(((self::$open_price - self::$extremePrice) / self::$open_price)) * 100;
                    self::$currentTrade['duration'] = ($data[$index]['binance_timestamp'] - $data[self::$openingIndex]['binance_timestamp']) / (1000 * 60);
                    self::$currentTrade['tagName'] = self::$currentTagName;

                    self::$trades[] = self::$currentTrade;

                    $safetyKey = $symbol . '_' . self::$tradeType;
                    if ($profit <= 0) {
                        self::$consecutiveLosses[$safetyKey] = (self::$consecutiveLosses[$safetyKey] ?? 0) + 1;
                        self::$consecutiveLossTimestamp[$safetyKey] = $data[$index]['binance_timestamp'];
                    } else {
                        self::$consecutiveLosses[$safetyKey] = 0;
                    }

                    self::resetTradeParams();
                }
            }
        }

        if (isset($index) && $index == (count($data) - 1) && !empty(self::$currentTrade)) {
            self::$leftovers[] = $symbol;
        }

        return self::$trades;
    }

    // ###############################################################################
    //                         Opening Logic
    // ###############################################################################

    public static function handleOpeningConditions($symbol, $data, $index)
    {
        $candle = $data[$index];
        $prev = $data[$index - 1] ?? null;

        if (!$prev) return null;

        // LONG
        if (self::$longEnabled) {
            $entry = self::detectLongEntry($data, $index);
            if ($entry) {
                self::$currentTagName = $entry;
                return 'LONG';
            }
        }

        // SHORT
        if (self::$shortEnabled) {
            $entry = self::detectShortEntry($data, $index);
            if ($entry) {
                self::$currentTagName = $entry;
                return 'SHORT';
            }
        }

        return null;
    }

    // ###############################################################################
    //                     Entry Detection
    // ###############################################################################

    protected static function detectLongEntry($data, $index)
    {
        $c = $data[$index];
        $p = $data[$index - 1];

        if ($c['ma25'] === null) return null;

        // Trend: price above ma25
        if ($c['close'] <= $c['ma25']) return null;

        // RSI in neutral zone and turning up
        if ($c['rsi6'] === null || $p['rsi6'] === null) return null;
        if ($c['rsi6'] > 60 || $c['rsi6'] < 35) return null;
        if ($c['rsi6'] <= $p['rsi6']) return null;

        // MACD histogram rising
        if ($c['histogram'] === null || $p['histogram'] === null) return null;
        if ($c['histogram'] <= $p['histogram']) return null;

        // Volume
        if ($c['volumeMA5'] !== null && $c['volume'] < $c['volumeMA5']) return null;

        // Bullish close
        if ($c['close'] <= $c['open']) return null;

        return 'LONG';
    }

    protected static function detectShortEntry($data, $index)
    {
        $c = $data[$index];
        $p = $data[$index - 1];

        if ($c['ma25'] === null) return null;

        // Trend: price below ma25
        if ($c['close'] >= $c['ma25']) return null;

        // RSI in neutral zone and turning down
        if ($c['rsi6'] === null || $p['rsi6'] === null) return null;
        if ($c['rsi6'] > 65 || $c['rsi6'] < 40) return null;
        if ($c['rsi6'] >= $p['rsi6']) return null;

        // MACD histogram falling
        if ($c['histogram'] === null || $p['histogram'] === null) return null;
        if ($c['histogram'] >= $p['histogram']) return null;

        // Volume
        if ($c['volumeMA5'] !== null && $c['volume'] < $c['volumeMA5']) return null;

        // Bearish close
        if ($c['close'] >= $c['open']) return null;

        return 'SHORT';
    }

    // ###############################################################################
    //                         Closing Logic
    // ###############################################################################

    public static function handleClosingConditions($symbol, $data, $index)
    {
        $closingPrice = 0;
        $candle = $data[$index];

        if (self::$tradeType === 'LONG') {
            if ($candle['close'] < self::$dynamicSL) {
                $closingPrice = $candle['close'];
            } else if ($candle['close'] >= self::$dynamicTP) {
                $closingPrice = $candle['close'];
            } else {
                $candlesHeld = self::getIndexDiffFromTimestamps($data[self::$openingIndex]['binance_timestamp'], $candle['binance_timestamp'], self::$interval);
                if ($candlesHeld > 8) {
                    $closingPrice = $candle['close'];
                }
            }
        } else {
            if ($candle['close'] > self::$dynamicSL) {
                $closingPrice = $candle['close'];
            } else if ($candle['close'] <= self::$dynamicTP) {
                $closingPrice = $candle['close'];
            } else {
                $candlesHeld = self::getIndexDiffFromTimestamps($data[self::$openingIndex]['binance_timestamp'], $candle['binance_timestamp'], self::$interval);
                if ($candlesHeld > 8) {
                    $closingPrice = $candle['close'];
                }
            }
        }

        return $closingPrice;
    }

    // ###############################################################################
    //                         Helpers
    // ###############################################################################

    protected static function getATRPercent($data, $index)
    {
        $atr14 = $data[$index]['atr14'] ?? null;
        if ($atr14 && $data[$index]['close'] > 0) {
            return $atr14 / $data[$index]['close'] * 100;
        }
        return 0.6;
    }

    public static function getIndexDiffFromTimestamps($timestamp1, $timestamp2, $interval)
    {
        $intervalMins = CommonHelpers::$binanceIntervals[$interval] ?? 15;
        $diffMs = abs($timestamp1 - $timestamp2);
        return (int)round($diffMs / ($intervalMins * 60 * 1000));
    }

    public static function resetTradeParams()
    {
        self::$extremePrice = 0;
        self::$currentTrade = [];
        self::$open_price = 0;
        self::$tradeType = null;
        self::$openingIndex = 0;
        self::$dynamicTP = 0;
        self::$dynamicSL = 0;
        self::$formulaType = null;
        self::$currentTagName = null;
    }

    protected static function addFormulaDetails()
    {
        DB::table('formula_details')->updateOrInsert(
            ['formula' => self::$formula],
            [
                'formula'    => self::$formula,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
