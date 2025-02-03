<?php

namespace App;

use Illuminate\Support\Facades\DB;

class CommonHelpers
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}
    public static function getSettingsValue($setting_key, $default)
    {
        return DB::table('trade_settings')->where('settings_key', $setting_key)->first()->settings_value ?? $default;
    }
    public static function getMetaValue($id, $meta_key, $default)
    {
        return DB::table('user_meta')->where('user_id', $id)->where('meta_key', $meta_key)->first()->meta_value ?? $default;
    }
    public static function getIndicatorAverages($symbol, $interval, $market)
    {
        $columns = [
            'volume',
            'ma7',
            'ma14',
            'ma25',
            'ma99',
            'rsi6',
            'per',
            'dif',
            'dea',
            'histogram',
            'sar',
            'obv',
            'stoch_rsi',
            'stoch_k',
            'stoch_d',
            'previousObvHigh',
            'wr',
            'K',
            'D',
            'J'
        ];

        $averages = [];

        foreach ($columns as $column) {
            $averages[$column] = DB::table('ideal_buying_candles')->where('symbol', $symbol)->where('interval', $interval)->where('market', $market)->avg($column);
        }
        return $averages;
    }
    public static function getPriorityQueue($interval, $market, $limit)
    {

        $tradeData = DB::table('coin_reports as main')
            ->select(
                'main.symbol',
                DB::raw('COUNT(main.id) as total_entries'),              // Total number of entries per symbol
                DB::raw('SUM(main.profit) as total_profit'),             // Sum of profit per symbol
                DB::raw('AVG(main.profit) as average_profit'),           // Average profit per symbol
                DB::raw('AVG(main.duration) as average_duration'),       // Average duration per symbol
                DB::raw('SUM(main.duration) as total_duration'),         // Total duration per symbol
                DB::raw('MAX(main.profit) as max_profit'),               // Maximum profit per symbol
                DB::raw('MIN(main.profit) as min_profit'),               // Minimum profit per symbol
                DB::raw('MAX(main.lowestPricePercentage) as max_lowestPrice'),  // Maximum of lowestPrice per symbol
                DB::raw('MIN(main.lowestPricePercentage) as min_lowestPrice'),   // Minimum of lowestPrice per symbol
                DB::raw('MAX(main.created_at) as last_updated')          // Last updated time for each symbol
            )
            ->where('main.market', $market)
            ->where('main.interval', $interval)
            ->groupBy('main.symbol')
            ->orderBy('total_duration', 'ASC')
            ->orderBy('average_profit', 'ASC')
            ->orderBy('max_lowestPrice', 'ASC')
            ->limit($limit)
            ->get();

        return $tradeData;
    }
    public static function getOpenOrderCount($interval, $market, $trade_acc)
    {
        return  DB::table('orders')
            ->where('interval', $interval)
            ->where('trade_acc', $trade_acc)
            ->where('market', $market)
            ->where('trade_status', 'open')
            ->where('side', 'BUY')
            ->count();
    }
    public static function checkOpenOrder($symbol, $interval, $market, $trade_acc)
    {
        if ($market === 'SPOT') {
            $open_orders =  DB::table('orders')
                ->where('symbol', $symbol)
                ->where('trade_acc', $trade_acc)
                ->where('market', $market)
                ->where('trade_status', 'open')
                ->where('side', 'BUY')
                ->get();

            $open_orders = json_decode(json_encode($open_orders), true);
            if (empty($open_orders)) {
                return ['is_open' => false];
            } else {
                return ['is_open' => true, 'order' => $open_orders[0]];
            }
        } else if ($market === 'FUTURE') {
            $open_orders =  DB::table('live_trades_future_results')
                ->where('symbol', $symbol)
                ->where('position', $interval)
                ->where('trade_acc', $trade_acc)
                ->where('market', $market)
                ->where('trade_status', 'open')
                ->get();

            $open_orders = json_decode(json_encode($open_orders), true);
            if (empty($open_orders)) {
                return ['is_open' => false];
            } else {
                return ['is_open' => true, 'order' => $open_orders[0]];
            }
        }
    }
}
