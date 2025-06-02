<?php

namespace App\Console\Commands\Supervisors\FutureTrades\Accounts2\OrderBookFormula;

use App\CommonHelpers;

use App\Services\OrderBookFormula\OrderBookFormulaLong;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LongWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:acc-2-order-book-long-worker';

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
        $account = 2;
        DB::table('confirmed_trades')->truncate();
        CommonHelpers::initiateLiveTradeSession($account);
        while (true) {
            try {
                OrderBookFormulaLong::performLiveTrades('FUTURE', $account);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayS(1);
        }
    }
}
