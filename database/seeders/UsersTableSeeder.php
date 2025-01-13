<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // User Anas
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Anas Jamal',
            'email' => 'anas@cryptoapis.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Test1234$'),
            'api_key' => 'aJtCqGSfXG0VOIQ9FAiPnMr5XfHWUCNfnM23X0zlkm4lInumC04XB2vtzpGQNUXp',
            'api_secret' => 'hpO4mcliMbGmOqxO1wV4WyymeiazmgdxmURo16PW2bAAxBhGp9eyZ3rvgodghtIm',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        // User Tanveer
        DB::table('users')->insert([
            'id' => 2,
            'name' => 'Tanveer Javaid',
            'email' => 'tanveer@cryptoapis.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Test1234$'),
            'api_key' => 'ThPyxcSof7ynKAwr8gfAAEYEMmPuG6YjAS5bxQtW7lz4BHtpk1fVmHN85yeA35UT',
            'api_secret' => 'Gqrld87JLylmjFdOHpHQrjMcXKIIQHEP5K4n2fv5dBnV3ZL5tCDw1LjZ4LvxkydQ',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('user_meta')->insert([

            // User Anas
            [
                'user_id' => 1,
                'meta_key' => 'live_trade_coin_count_spot',
                'meta_value' => 20,
            ],
            [
                'user_id' => 1,
                'meta_key' => 'is_auto_update_enable_spot',
                'meta_value' => true,
            ],
            [
                'user_id' => 1,
                'meta_key' => 'buy_price_spot',
                'meta_value' => 6,
            ],
            [
                'user_id' => 1,
                'meta_key' => 'target_profit_spot',
                'meta_value' => 0.4,
            ],
            [
                'user_id' => 1,
                'meta_key' => 'live_trade_worker_interval_spot',
                'meta_value' => '1m',
            ],

            // User Tanveer
            [
                'user_id' => 2,
                'meta_key' => 'live_trade_coin_count_spot',
                'meta_value' => 20,
            ],
            [
                'user_id' => 2,
                'meta_key' => 'is_auto_update_enable_spot',
                'meta_value' => true,
            ],
            [
                'user_id' => 2,
                'meta_key' => 'buy_price_spot',
                'meta_value' => 6,
            ],
            [
                'user_id' => 2,
                'meta_key' => 'target_profit_spot',
                'meta_value' => 0.4,
            ],
            [
                'user_id' => 2,
                'meta_key' => 'live_trade_worker_interval_spot',
                'meta_value' => '1m',
            ],

            // User Anas
            [
                'user_id' => 1,
                'meta_key' => 'live_trade_coin_count_future',
                'meta_value' => 20,
            ],
            [
                'user_id' => 1,
                'meta_key' => 'is_auto_update_enable_future',
                'meta_value' => true,
            ],
            [
                'user_id' => 1,
                'meta_key' => 'buy_price_future',
                'meta_value' => 6,
            ],
            [
                'user_id' => 1,
                'meta_key' => 'target_profit_future',
                'meta_value' => 0.4,
            ],
            [
                'user_id' => 1,
                'meta_key' => 'live_trade_worker_interval_future',
                'meta_value' => '1m',
            ],

            // User Tanveer
            [
                'user_id' => 2,
                'meta_key' => 'live_trade_coin_count_future',
                'meta_value' => 20,
            ],
            [
                'user_id' => 2,
                'meta_key' => 'is_auto_update_enable_future',
                'meta_value' => true,
            ],
            [
                'user_id' => 2,
                'meta_key' => 'buy_price_future',
                'meta_value' => 6,
            ],
            [
                'user_id' => 2,
                'meta_key' => 'target_profit_future',
                'meta_value' => 0.4,
            ],
            [
                'user_id' => 2,
                'meta_key' => 'live_trade_worker_interval_future',
                'meta_value' => '1m',
            ],


        ]);
    }
}
