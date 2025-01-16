<?php

namespace App\Http\Controllers;

use App\Services\SupervisorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcessController extends Controller
{
    public function index()
    {
        $processes = DB::table('process_handlers')->get();
        // foreach($processes as $process){
        //     $status = SupervisorService::getStatus($process->program_name);
        //     $data = $status['data'][0];
            
        // }
        return view('process-handler.index', ['processes' => $processes,'pageSlug'=>'processHandler']);
    }
}
