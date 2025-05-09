<?php

namespace App\Console\Commands;

use App\Services\InternalTrader\ReportService;
use App\Services\InternalTrader\ReportServiceTimeBased;
use Illuminate\Console\Command;

class GenerateInternalReportTimeBased extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-internal-report-time-based';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command is used to run intnernal trader.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ReportServiceTimeBased::generateCoinReport($this);
    }
}
