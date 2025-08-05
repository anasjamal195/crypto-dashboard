<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    private function getLogPath()
    {
        return env('LOG_FILE_PATH', storage_path('logs/laravel.log'));
    }

    public function getContent()
    {
        $logPath = $this->getLogPath();
        
        if (!File::exists($logPath)) {
            return response()->json(['content' => 'Log file not found.', 'stats' => ['size' => '0 KB']]);
        }

        $content = File::get($logPath);
        $size = File::size($logPath);
        
        return response()->json([
            'content' => $content,
            'stats' => ['size' => $this->formatBytes($size)]
        ]);
    }

    public function getLatest()
    {
        // Implementation for getting only new log entries
        // You might want to track last read position
        return response()->json(['hasNewContent' => false, 'newContent' => '', 'stats' => []]);
    }

    public function download()
    {
        $logPath = $this->getLogPath();
        return response()->download($logPath, 'laravel-log-' . date('Y-m-d') . '.log');
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}