<?php

namespace App\Console\Commands\Supervisors\SettingWorker;

use App\CommonHelpers;
use App\Models\User;
use App\Services\LiveTradeService;
use App\Services\MailerService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SpotSettingsWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:spot-settings-worker';

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
            MailerService::sendWorkerEmail('spot_settings_worker');
            try {
                foreach (User::all() as $user) {
                    $interval = CommonHelpers::getMetaValue($user->id, 'live_trade_worker_interval_spot', '1m');
                    LiveTradeService::updateTradeHandler($interval, 'SPOT', $user->id);
                }
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
        }
    }
}
