<?php

namespace App;

use Illuminate\Support\Facades\DB;

class CommonHelpers
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        
    }
    public static function getSettingsValue($setting_key,$default){
        return DB::table('trade_settings')->where('settings_key', $setting_key)->first()->settings_value ?? $default;
        
    }
    public static function getIndicatorAverages($symbol,$interval,$market){
        $columns = [
            'volume',
            'ma7', 'ma14', 'ma25', 'ma99',
            'rsi6', 'per', 'dif', 'dea', 'histogram',
            'sar', 'obv',
            'stoch_rsi', 'stoch_k', 'stoch_d',
            'previousObvHigh', 'wr', 'K', 'D', 'J'
        ];
        
        $averages = [];
        
        foreach ($columns as $column) {
            $averages[$column] = DB::table('ideal_buying_candles')->where('symbol',$symbol)->where('interval',$interval)->where('market',$market)->avg($column);
        }
        return $averages;
    }
}
