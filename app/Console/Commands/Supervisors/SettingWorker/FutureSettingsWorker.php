<?php

namespace App\Console\Commands\Supervisors\SettingWorker;

use App\CommonHelpers;
use App\Models\User;
use App\Services\LiveTradeService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FutureSettingsWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:future-settings-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump Live Trade Handler table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {

            try {
                foreach (User::all() as $user) {
                    $interval = CommonHelpers::getMetaValue($user->id, 'live_trade_worker_interval_future', '1m');
                    LiveTradeService::updateTradeHandler($interval, 'FUTURE', $user->id);
                }
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
        }
    }
}
