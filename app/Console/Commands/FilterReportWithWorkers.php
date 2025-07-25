<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use Illuminate\Console\Command;

class FilterReportWithWorkers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:filter-trades {formula} {workerLimit=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Filters trades by formula and assigns them to available workers, Default workers will be 5!';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $formula = $this->argument('formula');
        $workerLimit = (int) $this->argument('workerLimit');
        $this->info("Filteration in progress...");

        $filterResult = CommonHelpers::filterReportOnWorkerLimit($formula, $workerLimit);

        if ($filterResult)
            $this->info("Report Filtered: Filtered (".$workerLimit.") - " . $formula);
        else {
            $this->error('Error during filteration...');
        }
    }
}
