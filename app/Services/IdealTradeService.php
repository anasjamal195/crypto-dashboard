<?php



namespace App\Services;

use DateTime;
use Carbon\Carbon;

use App\Services\BinanceApiService;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IdealTradeService
{
    private static $timestampFormat = 'Y-m-d H:i:s';
    private static $mysqlDateTimeFormat = 'Y-m-d H:i:s';

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        // Instance specific initializations can be done here
    }

    public static function dumpIdealTrades(
        $symbol = 'BTCUSDT',
        $interval = '1m',
        $limit = 1000,
        $market = 'SPOT'
    ) {


        try {
            $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, $market);
            self::processDataAndStore($data, $symbol, $interval, $market);
        } catch (\Exception $th) { // Catch general exceptions
            Log::error("IdealTradeService: Error processing {$symbol} - " . $th->getMessage());
        }

        usleep(100000); // sleep to prevent rate limits or overload

    }

    public static function processDataAndStore($data, $coin, $interval, $market)
    {
        $requiredCandles = [];
        $priceLock = $data[0]['close'];
        $priceLockIndex = 0;
        $skipIndex = 0;

        foreach ($data as $index => &$candle) {
            self::adjustTimestamp($candle);

            if ($index < $skipIndex + 10 || $index < 20) {
                continue;
            } else {
                $skipIndex == 0;
            }



            if ($priceLock > $candle['close']) {
                $candle['should_buy'] = true;
                $priceLock = $candle['close'];
                $priceLockIndex = $index;
            } else if ($index < $priceLockIndex + 30) {
                if ($candle['close'] > $priceLock * 1.006) {
                    $data[$priceLockIndex]['should_sell'] = true;

                    $data[$priceLockIndex]['should_buy'] = false;
                    $previousObvHigh = 0;
                    for ($i = $priceLockIndex - 15; $i <= $priceLockIndex; $i++) {

                        if ($data[$priceLockIndex]['obv'] > $previousObvHigh) {
                            $previousObvHigh = $data[$i]['obv'];
                        }
                    }
                    $candle['previousObvHigh'] = $previousObvHigh;
                    $requiredCandles[] = $data[$priceLockIndex];
                    $skipIndex = $index;
                    if (MarketTrendService::istradeAllowed($interval, 15, $market, $candle['binance_timestamp']))
                        $requiredCandles[] = $data[$priceLockIndex];
                }
            } else if ($index >= $priceLockIndex + 30) {
                $priceLock = $candle['close'];
            }
        }


        self::deleteExistingEntries($coin, $interval, $market);
        self::storeOrUpdateCandlesticIndicatorData($requiredCandles, $coin, $interval);

        Log::info("DataDumper: Dataset updated for {$coin}.");
    }

    public static function adjustTimestamp(&$candle)
    {
        $candle['timestamp'] /= 1000;
        $date = new DateTime("@{$candle['timestamp']}");
        $date->setTimezone(new DateTimeZone('Asia/Karachi'));
        $candle['timestamp'] = $date->format(self::$timestampFormat);
    }

    public static function deleteExistingEntries($symbol, $interval, $market)
    {
        DB::table('ideal_buying_candles')
            ->where('symbol', $symbol)
            ->where('market', $market)
            ->where('interval', $interval)
            ->delete();
    }

    public static function storeOrUpdateCandlesticIndicatorData($data, $symbol, $interval)
    {
        foreach ($data as &$entry) {
            $entry['timestamp'] = Carbon::createFromFormat(self::$timestampFormat, $entry['timestamp'])
                ->format(self::$mysqlDateTimeFormat);

            DB::table('ideal_buying_candles')->updateOrInsert(
                ['symbol' => $symbol, 'market' => $entry['market'], 'interval' => $interval, 'timestamp' => $entry['timestamp']],
                $entry
            );
        }
    }
}
