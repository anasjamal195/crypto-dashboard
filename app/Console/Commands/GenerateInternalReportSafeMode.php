<?php

namespace App\Console\Commands;

use App\Services\InternalTrader\ReportService;
use App\Services\InternalTrader\ReportServiceSafeMode;
use Illuminate\Console\Command;

class GenerateInternalReportSafeMode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-internal-report-safe-mode';

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
                ReportServiceSafeMode::generateCoinReport($this);
            } catch (\Throwable $th) {
                $this->error($th->getMessage());
            }
        }
    }
}
