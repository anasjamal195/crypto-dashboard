<?php

namespace App\Console\Commands\Supervisors\MasterProcessManagers;

use App\CommonHelpers;
use Illuminate\Console\Command;

class ManageProcesses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:manage-master-processes';

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
        while (true) {

            try {
                $this->info("This is a master worker");
            } catch (\Throwable $th) {
                $this->error($th->getMessage());
            }

            CommonHelpers::delayMS(10);
        }
    }
}
