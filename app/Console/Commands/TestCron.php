<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Jobs\TestJob;
use App\Services\BinanceApiService;
use App\Services\BlockchainTradingSignalService;
use App\Services\InternalTrader\ReportService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use App\Services\OrderBookStrategy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TestCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $reportDetails = [
            [
                'formula' => 'Analysis - Current',
                'timestamp' => null,
                'includeFiltered' => false,
            ],
            // [
            //     'formula' => 'Analysis - Bullish',
            //     'timestamp' => 1746126000000,
            //     'includeFiltered' => false,
            // ],
            // [
            //     'formula' => 'Analysis Bearish',
            //     'timestamp' => 1746126000000,
            //     'includeFiltered' => false,
            // ],
            // [
            //     'formula' => 'Analysis - Slight Bearish',
            //     'timestamp' => 1745607600000,
            //     'includeFiltered' => false,
            // ],
            // [
            //     'formula' => 'Analysis - Slight Bullish',
            //     'timestamp' => 1744830000000,
            //     'includeFiltered' => false,
            // ],
            // [
            //     'formula' => 'Analysis - Flat',
            //     'timestamp' => 1732561200000,
            //     'includeFiltered' => false,
            // ],
            // [
            //     'formula' => 'Analysis - Mixed',
            //     'timestamp' => 1744225200000,
            //     'includeFiltered' => false,
            // ],

        ];


        foreach ($reportDetails as $details) {

            $formula = $details['formula'] . ' - Base';
            $timestamp = $details['timestamp'];
            $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, null, true);
            // $backtestFormula = 'Analysis - Current - Base - Wednesday, June 25, 2025 07:16 PM';


            if ($details['includeFiltered']) {
                $formula = $details['formula'] . ' - Filtered';
                $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, $backtestFormula, false);
            }
        }


        dd("Done on all trends with all coins");
        dd('test');
        $analystUsers = [
            [
                'email' => 'analyst1@egeniuscare.shop',
                'password' => 'Test@kt21234$',
            ],
            [
                'email' => 'analyst2@egeniuscare.shop',
                'password' => 'Test*8pk1234$',
            ],
            [
                'email' => 'analyst3@egeniuscare.shop',
                'password' => 'TestGk21234$',
            ],
            [
                'email' => 'analyst4@egeniuscare.shop',
                'password' => 'TestXc@llj1234$',
            ],
            [
                'email' => 'analyst5@egeniuscare.shop',
                'password' => 'TestD125*o1234$',
            ],
        ];

        $users = [];

        foreach ($analystUsers as $index => $user) {
            $users[] = [
                'name' => "Analysis Tool User " . ($index + 1) . " (Beta)",
                'email' => $user['email'],
                'email_verified_at' => now(),
                'password' => Hash::make($user['password']),
                'role' => 'analyst',
                'api_key' => Str::random(32),
                'api_secret' => Str::random(64),
                'domain_name' => 'egeniuscare.shop',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('users')->insert($users);
    }
}
