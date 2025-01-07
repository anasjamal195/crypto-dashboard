<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('trade_settings')->insert([

            // Spot Trading
            [
                'settings_key' => 'spot_coin_worker_min_percentage',
                'settings_value' => -5
            ],
            [
                'settings_key' => 'spot_coin_worker_max_percentage',
                'settings_value' => 5
            ],
            [
                'settings_key' => 'spot_coin_worker_quantity',
                'settings_value' => 10
            ],
            [
                'settings_key' => 'trend_worker_interval_spot',
                'settings_value' => '1m'
            ],
            [
                'settings_key' => 'trend_worker_limit_spot',
                'settings_value' => 15
            ],
            [
                'settings_key' => 'ideal_trade_worker_interval_spot',
                'settings_value' => '1m'
            ],
            [
                'settings_key' => 'ideal_trade_worker_limit_spot',
                'settings_value' => 1000
            ],
            [
                'settings_key' => 'report_worker_interval_spot',
                'settings_value' => '1m'
            ],
            [
                'settings_key' => 'report_worker_limit_spot',
                'settings_value' => 1000
            ],


            // Future Trading
            [
                'settings_key' => 'future_coin_worker_min_percentage',
                'settings_value' => -5
            ],
            [
                'settings_key' => 'future_coin_worker_max_percentage',
                'settings_value' => 5
            ],
            [
                'settings_key' => 'future_coin_worker_quantity',
                'settings_value' => 10
            ],
            [
                'settings_key' => 'trend_worker_interval_future',
                'settings_value' => '1m'
            ],
            [
                'settings_key' => 'trend_worker_limit_future',
                'settings_value' => 15
            ],
            [
                'settings_key' => 'ideal_trade_worker_interval_future',
                'settings_value' => '1m'
            ],
            [
                'settings_key' => 'ideal_trade_worker_limit_future',
                'settings_value' => 1000
            ],
            [
                'settings_key' => 'report_worker_interval_future',
                'settings_value' => '1m'
            ],
            [
                'settings_key' => 'report_worker_limit_future',
                'settings_value' => 1000
            ],

        ]);
    }
}
