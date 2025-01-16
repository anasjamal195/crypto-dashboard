<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SupervisorService
{
    /**
     * Execute a Supervisor command.
     *
     * @param string $command The command to execute.
     * @return string The output from the command.
     */
    private static function executeCommand($command)
    {
        try {
            $process = new Process(explode(' ', $command));
            $process->mustRun();

            return $process->getOutput();
        } catch (ProcessFailedException $exception) {
            return 'Error: ' . $exception->getMessage();
        }
    }

    /**
     * Start a specific Supervisor program or all programs.
     *
     * @param string|null $program The name of the program or null for all.
     * @return string
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
     * @return string
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
     * @return string
     */
    public static function restart($program = null)
    {
        $cmd = is_null($program) ? 'sudo supervisorctl restart all' : 'sudo supervisorctl restart ' . $program;
        return self::executeCommand($cmd);
    }

    /**
     * Reread the Supervisor configuration files.
     *
     * @return string
     */
    public static function reread()
    {
        return self::executeCommand('sudo supervisorctl reread');
    }

    /**
     * Update Supervisor to apply any configuration changes.
     *
     * @return string
     */
    public static function update()
    {
        return self::executeCommand('sudo supervisorctl update');
    }

    /**
     * Get the status of one or all Supervisor programs.
     *
     * @param string|null $program The name of the program or null for all.
     * @return string
     */
    public static function getStatus($program = null)
    {
        $cmd = is_null($program) ? 'sudo supervisorctl status' : 'sudo supervisorctl status ' . $program;
        return self::executeCommand($cmd);
    }
}
