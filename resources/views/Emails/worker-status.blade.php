<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Status Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            padding: 20px;
        }
        .container {
            background-color: #f8f8f8;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        .header {
            background-color: #004aad;
            color: #ffffff;
            padding: 10px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 0.8em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Worker Status Alert</h1>
        </div>
        <p>Hello,</p>
        <p>We would like to inform you that the following task has been started:</p>
        <p><strong>{{ $details }}</strong></p>
        <p>If you did not initiate this task, please contact our support team immediately.</p>
        <div class="footer">
            <p>Thank you,<br>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
