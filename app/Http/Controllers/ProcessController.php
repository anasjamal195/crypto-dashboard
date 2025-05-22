<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Jobs\ThreadsOrderBook\TriggersThread;
use App\Services\SupervisorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ProcessController extends Controller
{
    public function index()
    {
        $processes = SupervisorService::getStatus();

        return view('process-handler.index', ['processes' => $processes['data'], 'pageSlug' => 'processHandler']);
    }

    public function restart($process)
    {
        $process = SupervisorService::restart($process);
        if ($process['success'])
            return redirect()->back()->withSuccess('Successfully Restarted');

        return redirect()->back()->withError('Failed to restart');
    }
    public function stop($process)
    {
        $process = SupervisorService::stop($process);

        if ($process['success'])
            return redirect()->back()->withSuccess('Successfully Stopped');

        return redirect()->back()->withError('Failed to Stop');
    }


    public function startMultithread()
    {

        try {
            // Cleanup
            DB::statement('UPDATE trade_handler SET isWorkerDispatched = 0');
            DB::statement('UPDATE workers SET symbol_count = 0');
            DB::statement('UPDATE workers SET trade_status = 0');
            DB::statement('DELETE FROM worker_symbols WHERE 1');
            Artisan::call('queue:flush');
            Artisan::call('queue:clear');
            SupervisorService::executeCommand('killall -9 php');
            DB::table('jobs')->truncate();

            $threads = DB::table('workers')->where('active_status', 1)->pluck('worker_id');

            // Prepare Processes for start sequence
            // Start Sequence
            $processes = [
                'laravel_saftey_worker',
                'laravel_future_coin_dumper',
                'laravel_order_book_signals_worker',
            ];

            foreach ($threads as $index => $thread) {
                $processes[] = 'laravel_thread_workers:laravel_thread_workers_' . sprintf("%02d", $index);
            }
            $processes[] = 'acc_2_order_book_long_worker';

            // ===================================



            foreach ($processes as $process)
                SupervisorService::restart($process);

            // Dispatch All threads
            Artisan::call('queue:flush');
            foreach ($threads as $workerId) {
                TriggersThread::dispatch($workerId, 2);
            }
            return redirect()->back()->withSuccess('Action ' . 'Multithread Started');
        } catch (\Throwable $th) {
            return redirect()->back()->withError('Failed to Perform Multithread Restart ');
        }
    }


    public function performAction($action)
    {
        CommonHelpers::clearLogs();
        if ($action == 'RESTART') {
            $currentlyRunning = SupervisorService::getStatus();
            foreach ($currentlyRunning['data'] as $process) {
                if ($process['status'] == 'RUNNING') {
                    SupervisorService::restart($process['processName']);
                }
            }
            return redirect()->back()->withSuccess('Action ' . $action);
        }
        if ($action == 'CLEANUP') {
            DB::statement('UPDATE trade_handler SET isWorkerDispatched = 0');
            DB::statement('UPDATE workers SET symbol_count = 0');
            DB::statement('UPDATE workers SET trade_status = 0');
            DB::statement('DELETE FROM worker_symbols WHERE 1');
            Artisan::call('queue:flush');
            Artisan::call('queue:clear');
            SupervisorService::executeCommand('killall -9 php');
            DB::table('jobs')->truncate();
            return redirect()->back()->withSuccess('Action ' . $action);
        }
        $process = $action == 'START' ? SupervisorService::start() : SupervisorService::stop();
        if ($process['success'])
            return redirect()->back()->withSuccess('Action ' . $action);

        return redirect()->back()->withError('Failed to Perform Action ' . $action);
    }
    public function performActionOnPleskGit($apiKey, $action)
    {
        if ($action == 'RESTART' && $apiKey == config('binance.bot.api_key')) {
            $currentlyRunning = SupervisorService::getStatus();
            foreach ($currentlyRunning['data'] as $process) {
                if ($process['status'] == 'RUNNING') {
                    SupervisorService::restart($process['programName']);
                }
            }
            return true;
        }
        return false;
    }

    public function togglePosition($position)
    {

        if ($position === 'LONG') {

            $currentStatus = CommonHelpers::getMetaValue(auth()->user()->id, 'enable_long_multithread', 0);

            DB::table('user_meta')->where('user_id', auth()->user()->id)->where('meta_key', 'enable_long_multithread')->update([
                'meta_value' => !$currentStatus
            ]);
        } else if ($position === 'SHORT') {
            $currentStatus = CommonHelpers::getMetaValue(auth()->user()->id, 'enable_short_multithread', 0);
            DB::table('user_meta')->where('user_id', auth()->user()->id)->where('meta_key', 'enable_short_multithread')->update([
                'meta_value' => !$currentStatus
            ]);
        }


        return redirect()->back()->withSuccess('Toggled  ' . $position . ' status');
    }

    public function toggleMarket()
    {

        $currentStatus = CommonHelpers::getMetaValue(auth()->user()->id, 'enable_spot', 0);


        DB::table('user_meta')->where('user_id', auth()->user()->id)->where('meta_key', 'enable_spot')->update([
            'meta_value' => !$currentStatus
        ]);


        return redirect()->back()->withSuccess('Toggled Position status');
    }



    // Worker Handler Cruds
    public function workerIndex()
    {
        $workers = DB::table('workers')->get();
        $workerSymbols = DB::table('worker_symbols')->get()->groupBy('worker_id');

        return view('process-handler.index-worker', ['workers' => $workers, 'workerSymbols' => $workerSymbols, 'pageSlug' => 'workerHandler']);
    }
    public function flushWorker($worker_id)
    {
        CommonHelpers::workerFreeAllSymbols($worker_id);
        return redirect()->back()->withSuccess('Worker  ' . $worker_id . ' flushed');
    }
}
