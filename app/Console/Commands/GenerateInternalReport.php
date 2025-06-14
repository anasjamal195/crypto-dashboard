<?php

namespace App\Console\Commands;

use App\Services\InternalTrader\ReportService;
use Illuminate\Console\Command;

class GenerateInternalReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-internal-report';

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

        $reportDetails = [
            [
                'formula' => 'Current',
                'timestamp' => null,
                'includeFiltered' => true,
            ],

            [
                'formula' => 'Testing - Bullish',
                'timestamp' => 1746126000000,
                'includeFiltered' => true,
            ],
            // [
            //     'formula' => 'All Coins Base Hyperliquid  Bearish',
            //     'timestamp' => 1745607600000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Macd Swings Hyperliquid - Slight Bearish',
            //     'timestamp' => 1745607600000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Macd Swings Hyperliquid - Slight Bullish',
            //     'timestamp' => 1744830000000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Macd Swings Hyperliquid - Flat',
            //     'timestamp' => 1732561200000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Macd Swings Hyperliquid - Mixed',
            //     'timestamp' => 1744225200000,
            //     'includeFiltered' => true,
            // ],

        ];


        foreach ($reportDetails as $details) {
            $formula = $details['formula'] . ' - Base';
            $timestamp = $details['timestamp'];
            $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, null, true);


            if ($details['includeFiltered']) {
                $formula = $details['formula'] . ' - Filtered';
                $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, $backtestFormula, false);
            }
        }


        dd("Done on all trends with all coins");
    }
}
