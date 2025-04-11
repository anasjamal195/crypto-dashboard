<!-- resources/views/emails/safety-log.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoAPIs Store Trading Bot Alert</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 3px solid #0d6efd;
            margin-bottom: 20px;
        }
        .logo {
            font-weight: bold;
            font-size: 22px;
            color: #0d6efd;
        }
        .alert-card {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #fff;
        }
        .alert-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #212529;
        }
        .alert-timestamp {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .alert-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #0d6efd;
            margin-bottom: 15px;
        }
        .footer {
            font-size: 13px;
            color: #6c757d;
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
        }
        .btn:hover {
            background-color: #0b5ed7;
        }
        .action-tag {
            display: inline-block;
            padding: 3px 8px;
            color: white;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 8px;
        }
        .action-info {
            background-color: #0dcaf0;
        }
        .action-warning {
            background-color: #fd7e14;
        }
        .action-critical {
            background-color: #dc3545;
        }
        .position-long {
            background-color: #198754; /* Green for LONG positions */
        }
        .position-short {
            background-color: #dc3545; /* Red for SHORT positions */
        }
        .description-box {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .description-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .crypto-icon {
            font-size: 18px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">CryptoAPIs Store</div>
        <div>Trading Bot Monitoring System</div>
    </div>

    <div class="alert-card">
        <div class="alert-header">
            Trading Position Status Update
            
            @switch($safetyLog['action'])
                @case('STARTED_LOGGING')
                    <span class="action-tag action-info">SYSTEM START</span>
                    @break
                @case('WARNING_LONG')
                    <span class="action-tag action-warning position-long">LONG WARNING</span>
                    @break
                @case('WARNING_SHORT')
                    <span class="action-tag action-warning position-short">SHORT WARNING</span>
                    @break
                @case('KILLED_LONG')
                    <span class="action-tag action-critical position-long">LONG TERMINATED</span>
                    @break
                @case('KILLED_SHORT')
                    <span class="action-tag action-critical position-short">SHORT TERMINATED</span>
                    @break
                @default
                    <span class="action-tag action-info">{{ $safetyLog['action'] }}</span>
            @endswitch
        </div>
        
        <div class="alert-timestamp">
            Event Time: {{ \Carbon\Carbon::parse($safetyLog['created_at'])->format('M d, Y \a\t h:i:s A') }}
        </div>

        <div class="description-box">
            <div class="description-title">
                @switch($safetyLog['action'])
                    @case('STARTED_LOGGING')
                        <span class="crypto-icon">🔄</span> Trading Bot Monitoring Initiated
                        @break
                    @case('WARNING_LONG')
                        <span class="crypto-icon">⚠️</span> LONG Position Warning
                        @break
                    @case('WARNING_SHORT')
                        <span class="crypto-icon">⚠️</span> SHORT Position Warning
                        @break
                    @case('KILLED_LONG')
                        <span class="crypto-icon">🛑</span> LONG Position Terminated
                        @break
                    @case('KILLED_SHORT')
                        <span class="crypto-icon">🛑</span> SHORT Position Terminated
                        @break
                    @default
                        <span class="crypto-icon">📊</span> Trading Event Details
                @endswitch
            </div>
        </div>

        <div class="alert-details">
            {{ $safetyLog['details'] }}
        </div>

        @switch($safetyLog['action'])
            @case('STARTED_LOGGING')
                <p>The supervisor process has started monitoring Binance trading operations. All systems operational.</p>
                @break
            @case('WARNING_LONG')
                <p>A LONG position has triggered a warning threshold. The position may be experiencing unusual price action or volatility.</p>
                @break
            @case('WARNING_SHORT')
                <p>A SHORT position has triggered a warning threshold. The position may be experiencing unusual price action or volatility.</p>
                @break
            @case('KILLED_LONG')
                <p><strong>Action Taken:</strong> A LONG position has been automatically closed by the supervisor system to prevent further risk exposure.</p>
                @break
            @case('KILLED_SHORT')
                <p><strong>Action Taken:</strong> A SHORT position has been automatically closed by the supervisor system to prevent further risk exposure.</p>
                @break
            @default
                <p>This automated alert has been generated by the CryptoAPIs Store Trading Bot Supervisor Process.</p>
        @endswitch

        
    </div>

    <div class="footer">
        <p>This is an automated message from the CryptoAPIs Store trading system. Please do not reply directly to this email.</p>
        <p>&copy; {{ date('Y') }} CryptoAPIs Store | <a href="{{ route('settings.notifications') }}">Update Email Preferences</a></p>
    </div>
</body>
</html>