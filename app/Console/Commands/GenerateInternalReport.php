<?php

namespace App\Console\Commands;

use App\Services\InternalTrader\ReportServiceImproved;
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

        $startTs = 1751328000000; // July 1, 2025 00:00:00 UTC
        $candlesInYear = (int)ceil(365 * 24 * 60 / 15); // 35,040 (15m candles in 1 year)

        ReportServiceImproved::$limit = $candlesInYear;

        $formulaLabel = 'Improved Strategy v1 - 1 Year (Jul 2025-Jul 2026)';
        $finalFormula = ReportServiceImproved::generateCoinReport($this, $formulaLabel, $startTs, null, true);

        $trades = DB::table('coin_reports')->where('formula', $finalFormula)->orderBy('symbol')->get();

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
        $netProfit = round($trades->sum('profit'), 2);

        $this->info('Trades: ' . $total . ' | WR: ' . $wr . '% | Net: ' . $netProfit . '%');

        foreach ($trades->groupBy('symbol') as $sym => $st) {
            $sw = $st->where('profit', '>', 0);
            $sl = $st->where('profit', '<=', 0);
            $net = round($st->sum('profit'), 2);
            $avgW = $sw->count() ? round($sw->avg('profit'), 2) : 0;
            $avgL = $sl->count() ? round($sl->avg('profit'), 2) : 0;
            $this->line('  ' . $sym . ': ' . count($st) . ' tr, ' . count($sw) . 'W/' . count($sl) . 'L, net: ' . $net . '% (avgW: ' . $avgW . '%, avgL: ' . $avgL . '%)');
        }

        $totalWins = count($wins);
        $totalLosses = count($losses);
        $avgW = $totalWins ? round($wins->avg('profit'), 2) : 0;
        $avgL = $totalLosses ? round($losses->avg('profit'), 2) : 0;
        $pf = ($totalLosses && $losses->sum('profit') != 0) ? round(abs($wins->sum('profit') / $losses->sum('profit')), 2) : '∞';
        $this->line('  Overall — avgW: ' . $avgW . '%, avgL: ' . $avgL . '%, PF: ' . $pf);

        $this->info(str_repeat('=', 60));
    }
}
