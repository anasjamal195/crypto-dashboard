<!DOCTYPE html>
<html>

<head>
    <title>Crypto Api Notification</title>
</head>

<body>
    <h1>Order Placed (Dynamic FUTURE)</h1>
    <h3>Symbol: {{ $details['symbol'] ?? 'N/A' }}</h3>
    <h3>{!! isset($details['amount']) ? 'Trade Amount: ' . e($details['amount']) . ' USDT' : '' !!}</h3>
    <h3>Leverage: {{ $details['leverage'] ?? 'N/A' }}</h3>
    <h3>Position: {{ $details['side'] == 'BUY'?'LONG':'SHORT';  }}</h3>
    <h3>Position Type: {{ ucwords($details['type']) ?? 'N/A' }}</h3>
    <h3>orderId: {{ $details['orderId'] ?? 'N/A' }}</h3>
    <h3>Side: {{ $details['side'] ?? 'N/A' }}</h3>
    <h3>Price: {{ $details['price'] ?? 'N/A' }} USDT</h3>
    <h3>Quantity: {{ $details['qty'] ?? '0' }} {{ $details['symbol'] ?? '' }}</h3>
    <hr>

</body>

</html>
