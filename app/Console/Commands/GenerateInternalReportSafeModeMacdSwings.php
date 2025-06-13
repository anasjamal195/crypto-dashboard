<?php

namespace App\Console\Commands;

use App\Services\InternalTrader\ReportService;
use App\Services\InternalTrader\ReportServiceSafeMode;
use App\Services\InternalTrader\ReportServiceSafeModeMacdSwing;
use Illuminate\Console\Command;

class GenerateInternalReportSafeModeMacdSwings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-internal-report-safe-mode-macd-swings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command is used to run intnernal trader in safe mode for live.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {
            try {
                $formula = 'Base Report';
                $timestamp = null;
                ReportServiceSafeModeMacdSwing::generateCoinReport($this, $formula, $timestamp, '', true);
            } catch (\Throwable $th) {
                $this->error($th->getMessage());
            }
        }
    }
}
