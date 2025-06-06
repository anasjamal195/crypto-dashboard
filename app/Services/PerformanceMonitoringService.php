<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;
use Exception;

class PerformanceMonitoringService
{
    private $host;
    private $username;
    private $password;
    private $port;
    private $connection;
    private $cachePrefix = 'server_stats_';
    private $cacheTtl = 30; // 30 seconds cache

    public function __construct($host, $username, $password, $port = 22)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }

    /**
     * Get all server statistics in one call
     */
    public function getServerStats($useCache = true)
    {
        $cacheKey = $this->cachePrefix . md5($this->host);
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $this->connect();
            
            // Execute all commands in a single SSH session for efficiency
            $commands = $this->buildStatsCommands();
            $results = $this->executeMultipleCommands($commands);
            
            $stats = [
                'timestamp' => now()->toISOString(),
                'server_ip' => $this->host,
                'mysql_status' => $this->parseMysqlStatus($results['mysql_status']),
                'system_resources' => $this->parseSystemResources($results),
                'supervisor_status' => $this->parseSupervisorStatus($results['supervisor_status']),
                'supervisor_processes' => $this->parseSupervisorProcesses($results['supervisor_processes']),
            ];

            $this->disconnect();

            // Cache the results
            if ($useCache) {
                Cache::put($cacheKey, $stats, $this->cacheTtl);
            }

            return $stats;

        } catch (Exception $e) {
            Log::error('Server monitoring error: ' . $e->getMessage());
            $this->disconnect();
            throw $e;
        }
    }

    /**
     * Get MySQL service status
     */
    public function getMysqlStatus()
    {
        try {
            $this->connect();
            $output = $this->executeCommand('systemctl is-active mysql || systemctl is-active mysqld');
            $this->disconnect();
            
            return [
                'status' => trim($output) === 'active' ? 'running' : 'stopped',
                'service_name' => $this->detectMysqlServiceName()
            ];
        } catch (Exception $e) {
            Log::error('MySQL status check failed: ' . $e->getMessage());
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    /**
     * Start MySQL service
     */
    public function startMysql()
    {
        return $this->manageMysqlService('start');
    }

    /**
     * Stop MySQL service
     */
    public function stopMysql()
    {
        return $this->manageMysqlService('stop');
    }

    /**
     * Stop all supervisor processes
     */
    public function stopAllSupervisorProcesses()
    {
        try {
            $this->connect();
            $output = $this->executeCommand('supervisorctl stop all');
            $this->disconnect();
            
            // Clear cache to force refresh on next call
            $this->clearCache();
            
            return [
                'success' => true,
                'message' => 'All supervisor processes stopped',
                'output' => $output
            ];
        } catch (Exception $e) {
            Log::error('Failed to stop supervisor processes: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to stop supervisor processes',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get lightweight server health check
     */
    public function getQuickHealthCheck()
    {
        $cacheKey = $this->cachePrefix . 'quick_' . md5($this->host);
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $this->connect();
            
            // Minimal commands for quick health check
            $commands = [
                'uptime' => 'uptime',
                'load' => 'cat /proc/loadavg',
                'memory' => 'free -m | awk \'NR==2{printf "%.1f", $3*100/$2}\'',
                'mysql' => 'systemctl is-active mysql || systemctl is-active mysqld'
            ];
            
            $results = $this->executeMultipleCommands($commands);
            $this->disconnect();
            
            $health = [
                'timestamp' => now()->toISOString(),
                'uptime' => trim($results['uptime']),
                'load_average' => explode(' ', trim($results['load']))[0],
                'memory_usage' => (float) trim($results['memory']),
                'mysql_running' => trim($results['mysql']) === 'active'
            ];

            Cache::put($cacheKey, $health, 15); // Shorter cache for quick checks
            return $health;

        } catch (Exception $e) {
            Log::error('Quick health check failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Manage MySQL service (start/stop)
     */
    private function manageMysqlService($action)
    {
        try {
            $this->connect();
            $serviceName = $this->detectMysqlServiceName();
            $command = "systemctl {$action} {$serviceName}";
            
            $output = $this->executeCommand($command);
            
            // Verify the action
            sleep(2); // Give service time to change state
            $status = $this->executeCommand("systemctl is-active {$serviceName}");
            
            $this->disconnect();
            
            // Clear cache to force refresh
            $this->clearCache();
            
            $isRunning = trim($status) === 'active';
            $expectedState = $action === 'start' ? true : false;
            
            return [
                'success' => $isRunning === $expectedState,
                'action' => $action,
                'current_status' => $isRunning ? 'running' : 'stopped',
                'service_name' => $serviceName,
                'output' => $output
            ];
            
        } catch (Exception $e) {
            Log::error("MySQL {$action} failed: " . $e->getMessage());
            return [
                'success' => false,
                'action' => $action,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build optimized commands for getting all stats
     */
    private function buildStatsCommands()
    {
        return [
            'mysql_status' => 'systemctl is-active mysql || systemctl is-active mysqld',
            'cpu_usage' => 'top -bn1 | grep "Cpu(s)" | awk \'{print $2}\' | sed \'s/%us,//\'',
            'memory_info' => 'free -m',
            'disk_usage' => 'df -h / | awk \'NR==2 {print $5}\'',
            'load_avg' => 'uptime | awk -F\'load average:\' \'{print $2}\'',
            'supervisor_status' => 'systemctl is-active supervisor 2>/dev/null || echo "inactive"',
            'supervisor_processes' => 'supervisorctl status 2>/dev/null || echo "No supervisor"'
        ];
    }

    /**
     * Execute multiple commands efficiently
     */
    private function executeMultipleCommands($commands)
    {
        $results = [];
        
        foreach ($commands as $key => $command) {
            try {
                $results[$key] = $this->executeCommand($command);
            } catch (Exception $e) {
                $results[$key] = "Error: " . $e->getMessage();
                Log::warning("Command '{$command}' failed: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Parse MySQL status
     */
    private function parseMysqlStatus($output)
    {
        $status = trim($output);
        return [
            'running' => $status === 'active',
            'status' => $status === 'active' ? 'running' : 'stopped',
            'service_name' => $this->detectMysqlServiceName()
        ];
    }

    /**
     * Parse system resources
     */
    private function parseSystemResources($results)
    {
        // Parse CPU
        $cpuUsage = (float) str_replace('%', '', trim($results['cpu_usage']));
        
        // Parse Memory
        $memoryLines = explode("\n", $results['memory_info']);
        $memoryLine = '';
        foreach ($memoryLines as $line) {
            if (strpos($line, 'Mem:') !== false) {
                $memoryLine = $line;
                break;
            }
        }
        
        $memoryParts = preg_split('/\s+/', trim($memoryLine));
        $totalMem = isset($memoryParts[1]) ? (int) $memoryParts[1] : 0;
        $usedMem = isset($memoryParts[2]) ? (int) $memoryParts[2] : 0;
        $memoryUsage = $totalMem > 0 ? round(($usedMem / $totalMem) * 100, 1) : 0;
        
        // Parse Disk
        $diskUsage = (float) str_replace('%', '', trim($results['disk_usage']));
        
        // Parse Load Average
        $loadAvg = trim($results['load_avg']);
        
        return [
            'cpu_usage_percent' => $cpuUsage,
            'memory_usage_percent' => $memoryUsage,
            'memory_total_mb' => $totalMem,
            'memory_used_mb' => $usedMem,
            'disk_usage_percent' => $diskUsage,
            'load_average' => $loadAvg
        ];
    }

    /**
     * Parse supervisor status
     */
    private function parseSupervisorStatus($output)
    {
        $status = trim($output);
        return [
            'running' => $status === 'active',
            'status' => $status
        ];
    }

    /**
     * Parse supervisor processes
     */
    private function parseSupervisorProcesses($output)
    {
        if (strpos($output, 'No supervisor') !== false || strpos($output, 'Error') !== false) {
            return ['processes' => [], 'total_count' => 0];
        }

        $lines = explode("\n", trim($output));
        $processes = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $parts = preg_split('/\s+/', trim($line), 3);
            if (count($parts) >= 2) {
                $processes[] = [
                    'name' => $parts[0],
                    'status' => $parts[1],
                    'description' => isset($parts[2]) ? $parts[2] : ''
                ];
            }
        }
        
        return [
            'processes' => $processes,
            'total_count' => count($processes),
            'running_count' => count(array_filter($processes, function($p) {
                return $p['status'] === 'RUNNING';
            }))
        ];
    }

    /**
     * Detect MySQL service name
     */
    private function detectMysqlServiceName()
    {
        try {
            $this->connect();
            $mysqlCheck = $this->executeCommand('systemctl list-units --type=service | grep -E "(mysql|mysqld)" | head -1');
            $this->disconnect();
            
            if (strpos($mysqlCheck, 'mysqld') !== false) {
                return 'mysqld';
            }
            return 'mysql';
        } catch (Exception $e) {
            return 'mysql'; // Default fallback
        }
    }

    /**
     * SSH Connection management
     */
    private function connect()
    {
        if ($this->connection && $this->connection->isConnected()) {
            return;
        }

        $this->connection = new SSH2($this->host, $this->port);
        
        if (!$this->connection->login($this->username, $this->password)) {
            throw new Exception("Authentication failed for {$this->username}@{$this->host}");
        }

        // Set timeout for commands
        $this->connection->setTimeout(30);
    }

    /**
     * Execute SSH command
     */
    private function executeCommand($command)
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            throw new Exception("No SSH connection available");
        }

        $output = $this->connection->exec($command);
        
        if ($output === false) {
            throw new Exception("Failed to execute command: {$command}");
        }

        return $output;
    }

    /**
     * Disconnect SSH
     */
    private function disconnect()
    {
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    /**
     * Clear cache
     */
    private function clearCache()
    {
        $keys = [
            $this->cachePrefix . md5($this->host),
            $this->cachePrefix . 'quick_' . md5($this->host)
        ];
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Set cache TTL
     */
    public function setCacheTtl($seconds)
    {
        $this->cacheTtl = $seconds;
        return $this;
    }

    /**
     * Destructor to ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}