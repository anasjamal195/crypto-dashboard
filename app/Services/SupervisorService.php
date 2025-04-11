<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SupervisorService
{

    public static $processes = [
        'laravel_spot_coin_dumper',
        'laravel_future_coin_dumper',
        'laravel_spot_coin_report_worker',
        'laravel_future_coin_report_worker',
        'laravel_spot_setting_worker',
        'laravel_future_setting_worker',
        'laravel_spot_live_trade_worker',
        'laravel_future_live_trade_worker',
        'laravel_spot_dynamic_trade_worker',
        'laravel_future_dynamic_trade_worker'
    ];

    public static function getProcesses()
    {
        return self::$processes;
    }
    /**
     * Execute a Supervisor command and format the output.
     *
     * @param string $command The command to execute.
     * @return array The formatted output as an associative array.
     */
    public static function executeCommand($command)
    {
        $process = Process::fromShellCommandline($command);
        try {
            $process->mustRun();
            return [
                'success' => true,
                'message' => trim($process->getOutput())
            ];
        } catch (ProcessFailedException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * Start a specific Supervisor program or all programs.
     *
     * @param string|null $program The name of the program or null for all.
     * @return array
     */
    public static function start($program = null)
    {
        $cmd = is_null($program) ? 'sudo supervisorctl start all' : 'sudo supervisorctl start ' . $program;
        return self::executeCommand($cmd);
    }

    /**
     * Stop a specific Supervisor program or all programs.
     *
     * @param string|null $program The name of the program or null for all.
     * @return array
     */
    public static function stop($program = null)
    {
        $cmd = is_null($program) ? 'sudo supervisorctl stop all' : 'sudo supervisorctl stop ' . $program;
        return self::executeCommand($cmd);
    }

    /**
     * Restart a specific Supervisor program or all programs.
     *
     * @param string|null $program The name of the program or null for all.
     * @return array
     */
    public static function restart($program = null)
    {
        $cmd = is_null($program) ? 'sudo supervisorctl restart all' : 'sudo supervisorctl restart ' . $program;
        return self::executeCommand($cmd);
    }

    /**
     * Reread the Supervisor configuration files.
     *
     * @return array
     */
    public static function reread()
    {
        return self::executeCommand('sudo supervisorctl reread');
    }

    /**
     * Update Supervisor to apply any configuration changes.
     *
     * @return array
     */
    public static function update()
    {
        return self::executeCommand('sudo supervisorctl update');
    }

    /**
     * Get the status of one or all Supervisor programs.
     *
     * @param string|null $program The name of the program or null for all.
     * @return array
     */
    public static function getStatus($program = null)
    {
        $cmd = is_null($program) ? 'sudo supervisorctl status' : 'sudo supervisorctl status ' . $program;
        $output = self::executeCommand($cmd);


        return [
            'success' => true,
            'data' => self::parseStatusOutput($output['message'])
        ];
    }

    /**
     * Parse the status output into a structured array.
     *
     * @param string $output The raw output from supervisorctl status command.
     * @return array
     */
    private static function parseStatusOutput($output)
    {
        $lines = explode("\n", trim($output));
        $statusArray = [];
        // dd($lines);
        foreach ($lines as $line) {
            if (preg_match('/^(.*?)\s+(RUNNING|STOPPED|STARTING|STOPPING|EXITED|FATAL|UNKNOWN)\s+pid\s+(\d+)?,?\s*uptime\s+(\d+:\d+:\d+)/', $line, $matches)) {
                $statusArray[] = [
                    'processName' => $matches[1],
                    'status' => $matches[2],
                    'pid' => $matches[3] ?? 'N/A', // Handle cases where pid might not be available
                    'uptime' => $matches[4]
                ];
            } elseif (preg_match('/^(.*?)\s+(RUNNING|STOPPED|STARTING|STOPPING|EXITED|FATAL|UNKNOWN)/', $line, $matches)) {
                // Catch cases where uptime or pid is not provided
                $statusArray[] = [
                    'processName' => $matches[1],
                    'status' => $matches[2],
                    'pid' => 'N/A',
                    'uptime' => 'N/A'
                ];
            }
        }

        return $statusArray;
    }
}
