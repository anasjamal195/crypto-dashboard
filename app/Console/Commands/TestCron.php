<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Jobs\TestJob;
use App\Services\BinanceApiService;
use App\Services\BlockchainTradingSignalService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use App\Services\OrderBookStrategy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

        $users = [];

        for ($i = 1; $i <= 5; $i++) {
            $users[] = [
                'name' => "Analysis Tool User {$i} (Beta)",
                'email' => "analyst{$i}@egeniuscare.shop",
                'email_verified_at' => now(),
                'password' => Hash::make('Test1234$'),
                'role' => 'analyst',
                'api_key' => '#########################',
                'api_secret' => '#########################',
                'domain_name' => 'egeniuscare.shop',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('users')->insert($users);
    }
}
