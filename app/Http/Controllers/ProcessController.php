<?php

namespace App\Http\Controllers;

use App\Services\SupervisorService;
use Illuminate\Http\Request;
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
    public function performAction($action)
    {

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
}
