<?php

namespace App\Console\Commands;

use App\Services\InternalTrader\ReportServiceV2;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateInternalReport extends Command
{
    protected $signature = 'app:generate-internal-report';

    protected $description = 'Run continuous 1-year backtest (Jul 2025 - Jul 2026).';

    public function handle()
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 0);

        $startTs = 1751328000000;
        $candlesInYear = 365 * 24 * 2; // 8760 (1h candles in 1 year)
        $feePerTrade = 0.10;

        ReportServiceV2::$limit = $candlesInYear;

        $formulaLabel = 'V2 - 2 Year (Jul 2024-Jul 2026)';
        $finalFormula = ReportServiceV2::generateCoinReport($this, $formulaLabel, $startTs, null, true);

        $trades = DB::table('coin_reports')
            ->where('formula', $finalFormula)
            ->orderBy('openingTimestamp')
            ->get();

        $this->info(str_repeat('=', 60));
        $this->info('RESULT: ' . $formulaLabel);
        $this->info(str_repeat('-', 60));

        if ($trades->isEmpty()) {
            $this->warn('No trades generated.');
            return;
        }

        $wins = $trades->where('profit', '>', 0);
        $losses = $trades->where('profit', '<=', 0);
        $total = count($trades);
        $wr = $total ? round(count($wins) / $total * 100, 1) : 0;
        $netSum = round($trades->sum('profit'), 2);

        // Grand Total — equal capital per coin, compounded per-coin
        $coins = $trades->groupBy('symbol');
        $coinCount = count($coins);
        $capitalPerCoin = 100.0 / $coinCount;
        $grandTotalBalance = 0.0;

        $perCoinBalance = [];
        $this->line("  Coins: $coinCount | Capital per coin: \${$capitalPerCoin}");
        $this->info('');

        foreach ($coins as $sym => $st) {
            $balance = $capitalPerCoin;
            $sw = $st->where('profit', '>', 0);
            $sl = $st->where('profit', '<=', 0);
            $net = round($st->sum('profit'), 2);
            $avgW = $sw->count() ? round($sw->avg('profit'), 2) : 0;
            $avgL = $sl->count() ? round($sl->avg('profit'), 2) : 0;

            foreach ($st as $t) {
                $balance *= (1 + $t->profit / 100) * (1 - $feePerTrade / 100);
            }

            $coinReturn = round($balance - $capitalPerCoin, 2);
            $grandTotalBalance += $balance;
            $perCoinBalance[$sym] = $balance;

            $this->line("  {$sym}: " . count($st) . " tr, " . count($sw) . "W/" . count($sl) . "L, net: " . $net . "% → \${$coinReturn}");
        }

        $grandTotalReturn = round($grandTotalBalance - 100, 2);

        // Monthly breakdown — sum per-coin balances at end of each month
        $monthlyTotals = [];
        $monthlyCoinBalances = [];
        foreach ($coins as $sym => $st) {
            $bal = $capitalPerCoin;
            $monthlyCoinBalances[$sym] = [];
            foreach ($st as $t) {
                $bal *= (1 + $t->profit / 100) * (1 - $feePerTrade / 100);
                $month = date('Y-m', $t->openingTimestamp / 1000);
                $monthlyCoinBalances[$sym][$month] = $bal;
            }
        }
        $allMonths = [];
        foreach ($monthlyCoinBalances as $sym => $months) {
            foreach ($months as $m => $b) $allMonths[$m] = true;
        }
        ksort($allMonths);
        $prevTotal = 100.0;
        $this->info('');
        $this->info('--- Monthly Portfolio Return ---');
        foreach (array_keys($allMonths) as $m) {
            $totalNow = 0;
            foreach ($coins as $sym => $st) {
                $totalNow += $monthlyCoinBalances[$sym][$m] ?? $capitalPerCoin;
            }
            $monthRet = round(($totalNow - $prevTotal) / $prevTotal * 100, 2);
            $this->line("  {$m}: {$monthRet}% (portfolio: \$" . round($totalNow, 2) . ")");
            $prevTotal = $totalNow;
        }

        $this->info('');
        $avgW = count($wins) ? round($wins->avg('profit'), 2) : 0;
        $avgL = count($losses) ? round($losses->avg('profit'), 2) : 0;
        $pf = ($losses->count() && $losses->sum('profit') != 0) ? round(abs($wins->sum('profit') / $losses->sum('profit')), 2) : '∞';

        $this->info("Trades: {$total} | WR: {$wr}% | avgW: {$avgW}% | avgL: {$avgL}% | PF: {$pf}");
        $this->info("Net Sum (arithmetic): {$netSum}%");
        $this->info("Grand Total (equal allocation, {$feePerTrade}% fee/trade): \${$grandTotalBalance} = {$grandTotalReturn}%");

        // Monthly avg
        $monthCount = count(array_keys($allMonths));
        $cagr = $monthCount > 0 ? round((pow($grandTotalBalance / 100, 12 / $monthCount) - 1) * 100, 2) : 0;
        $this->info("Annualized (CAGR): {$cagr}%");

        $this->info(str_repeat('=', 60));
    }
}
