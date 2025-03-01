<?php

namespace App\Jobs;

use App\CommonHelpers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TestJob implements ShouldQueue
{
    use Queueable;
    public $data;
    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $counter = 0;
        while (true) {
            Log::info($this->data);
            if ($counter == 10)
                break;
            $counter++;
            CommonHelpers::delayS(1);
        }
    }
}
