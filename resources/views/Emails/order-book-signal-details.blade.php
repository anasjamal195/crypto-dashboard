<!DOCTYPE html>
<html>

<head>
    <title>Order Book Snapshot - {{ $snapshot->symbol }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 600px;
            margin: auto;
            padding: 20px;
        }

        h2 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Order Book Snapshot for {{ $snapshot->symbol }}</h2>
        <p><strong>Snapshot Time:</strong> {{ $snapshot->snapshot_time }}</p>
        <p><strong>Depth:</strong> {{ $snapshot->depth }}</p>

        <h3>Market Data</h3>
        <table>
            <tr>
                <th>Highest Bid</th>
                <td>{{ $snapshot->highest_bid }}</td>
            </tr>
            <tr>
                <th>Lowest Ask</th>
                <td>{{ $snapshot->lowest_ask }}</td>
            </tr>
            <tr>
                <th>Spread</th>
                <td>{{ $snapshot->spread }}</td>
            </tr>
        </table>

        <h3>Volume Analysis</h3>
        <table>
            <tr>
                <th>Bid Volume</th>
                <td>{{ $snapshot->bid_volume }}</td>
            </tr>
            <tr>
                <th>Ask Volume</th>
                <td>{{ $snapshot->ask_volume }}</td>
            </tr>
            <tr>
                <th>Volume Imbalance</th>
                <td>{{ $snapshot->volume_imbalance }}</td>
            </tr>
        </table>

        <h3>Support & Resistance</h3>
        <table>
            <tr>
                <th>Support Levels</th>
                <td>{{ implode(', ', $snapshot->support_levels) }}</td>
            </tr>
            <tr>
                <th>Resistance Levels</th>
                <td>{{ implode(', ', $snapshot->resistance_levels) }}</td>
            </tr>
            <tr>
                <th>Thin Liquidity Areas</th>
                <td>{{ implode(', ', $snapshot->thin_liquidity_areas) }}</td>
            </tr>
        </table>

        <h3>Trading Signals</h3>
        <table>
            <tr>
                <th>Signal Recommendation</th>
                <td>{{ $snapshot->signal }}</td>
            </tr>
            <tr>
                <th>Long Strength</th>
                <td>{{ $snapshot->long_strength }}</td>
            </tr>
            <tr>
                <th>Short Strength</th>
                <td>{{ $snapshot->short_strength }}</td>
            </tr>
            <tr>
                <th>Long Entry Points</th>
                <td>{{ implode(', ', $snapshot->long_entry_points) }}</td>
            </tr>
            <tr>
                <th>Short Entry Points</th>
                <td>{{ implode(', ', $snapshot->short_entry_points) }}</td>
            </tr>
        </table>
    </div>
</body>

</html>