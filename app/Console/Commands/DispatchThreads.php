<?php

namespace App\Console\Commands;

use App\Jobs\ThreadsOrderBook\LongThread;
use App\Jobs\ThreadsOrderBook\TriggersThread;
use App\Services\OrderBookFormula\OrderBookFormulaLong;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DispatchThreads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:dispatch-threads';

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
        $threads = DB::table('workers')->pluck('worker_id');
        Artisan::call('queue:clear');
        $this->info('Preparing to dispatch ' . count($threads) . ' threads...');
        sleep(1);
        foreach ($threads as $workerId) {
            TriggersThread::dispatch($workerId);
            $this->info('Dispatched thread: ' . $workerId);
        }
    }
}
