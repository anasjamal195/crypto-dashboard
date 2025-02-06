<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Status Update</title>
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
            <h1>Transaction Alert</h1>
        </div>
        <p>Order ID: {{$details['orderId']}}</p>
        <p>Total Amount: {{$details['total']}}</p>
        <p>Currency: {{$details['cryptoCurrency']}}</p>
        <p>Wallet Address: {{$details['walletAddress']}}</p>
       
</body>
</html>
