<?php

namespace App\Console\Commands;

use App\Services\InternalTrader\ReportService;
use App\Services\InternalTrader\ReportServiceHyperLiquid;
use Illuminate\Console\Command;

class GenerateInternalReportHyperLiquid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-internal-report-hyperliquid';

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

        // $formula = 'All Coins Base  (Bullish)';
        // $timestamp = 1746644400000;
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, '', true);

        // $formula = 'All Coins Filtered (Bullish)';
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, $backtestFormula, false);


        $formula = 'All Coins Base Hyperliquid  (Bearish)';
        $timestamp = null;
        $backtestFormula = ReportServiceHyperLiquid::generateCoinReport($this, $formula, $timestamp, '', true);

        $formula = 'All Coins Filtered Hyperliquid (Bearish)';
        $backtestFormula = ReportServiceHyperLiquid::generateCoinReport($this, $formula, $timestamp, $backtestFormula, false);


        // $formula = 'All Coins Base  (Slight Bearish)';
        // $timestamp = 1745607600000;
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, '', true);

        // $formula = 'All Coins Filtered (Slight Bearish)';
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, $backtestFormula, false);



        // $formula = 'All Coins Base  (Slight Bullish)';
        // $timestamp = 1744830000000;
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, '', true);

        // $formula = 'All Coins Filtered (Slight Bullish)';
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, $backtestFormula, false);


        // $formula = 'All Coins Base  (Flat)';
        // $timestamp = 1732561200000;
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, '', true);

        // $formula = 'All Coins Filtered (Flat)';
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, $backtestFormula, false);

        // $formula = 'All Coins Base  (Mixed)';
        // $timestamp = 1744225200000;
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, '', true);

        // $formula = 'All Coins Filtered (Mixed)';
        // $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, $backtestFormula, false);

        dd("Done on all trends with all coins");



        // ReportService::generateCoinReport($this);
    }
}
