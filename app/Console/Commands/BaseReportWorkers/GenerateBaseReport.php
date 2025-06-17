<?php

namespace App\Console\Commands\BaseReportWorkers;

use App\Services\InternalTrader\BaseReportWorkers\BaseReport5m;
use Illuminate\Console\Command;

class GenerateBaseReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-base-report {interval?}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This Command is used to generate base reports on multiple intervals';

    /**
     * Execute the console command.
     */
    public function handle()
    {


        $interval = $this->argument('interval');


        while (true) {
            try {
                $formula = 'Base Report';
                $timestamp = null;
                $formula = $formula . " $interval";

                switch ($interval) {
                    case '5m':
                        BaseReport5m::generateCoinReport($this, $formula, $timestamp, '', true);
                        break;
                    case '15m':
                        BaseReport5m::generateCoinReport($this, $formula, $timestamp, '', true);
                        break;
                    default:
                        $this->info('No interval specified');
                        break;
                }
            } catch (\Throwable $th) {
                $this->error($th->getMessage());
            }
        }
    }
}
