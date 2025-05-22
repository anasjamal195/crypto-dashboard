@extends('layouts.app')

@section('content')
    <style>
        .wallet-card {
            background: rgba(31, 41, 55, 0.95);
            border: 1px solid rgba(55, 65, 81, 0.6);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .wallet-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(59, 130, 246, 0.1));
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.15), rgba(59, 130, 246, 0.15));
            border-color: rgba(79, 70, 229, 0.3);
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: #60a5fa;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .profit-positive {
            color: #10b981 !important;
        }

        .profit-negative {
            color: #ef4444 !important;
        }

        .positions-table {
            background: rgba(17, 24, 39, 0.8);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(55, 65, 81, 0.4);
        }

        .positions-table th {
            background: rgba(17, 24, 39, 0.9);
            border-color: rgba(55, 65, 81, 0.4);
            color: #60a5fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            padding: 1rem 0.75rem;
        }

        .positions-table td {
            border-color: rgba(55, 65, 81, 0.3);
            color: #f3f4f6;
            padding: 0.875rem 0.75rem;
            vertical-align: middle;
        }

        .position-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .position-long {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .position-short {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .refresh-btn {
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            border: none;
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
        }

        .refresh-btn:hover {
            background: linear-gradient(135deg, #3730a3, #2563eb);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px -1px rgba(79, 70, 229, 0.4);
            color: white;
        }

        .loading-skeleton {
            background: linear-gradient(90deg, rgba(75, 85, 99, 0.3) 25%, rgba(107, 114, 128, 0.5) 50%, rgba(75, 85, 99, 0.3) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
            height: 1.25rem;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        .crypto-symbol {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .crypto-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            color: white;
        }

        .no-positions {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }

        .section-title {
            color: #f3f4f6;
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .wallet-header {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(99, 102, 241, 0.1));
            border-bottom: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 12px 12px 0 0;
            padding: 1.25rem 1.5rem;
        }

        .wallet-title {
            color: #f3f4f6;
            font-weight: 600;
            font-size: 1.25rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .icon-gradient {
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>

    <div class="header bg-gradient-primary pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">
                            <i class="fas fa-wallet mr-2"></i>
                            Binance Wallet Dashboard
                        </h6>

                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--7">
        {{-- Futures Wallet Section --}}
        <div class="row mb-4">
            <div class="col">
                <div class="card wallet-card">
                    <div class="wallet-header">
                        <h5 class="wallet-title">
                            <i class="fas fa-chart-line icon-gradient"></i>
                            Futures Wallet
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="futuresWalletStats" class="row mb-4">
                            @if ($futureWallet)
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value">${{ number_format($futureWallet['wallet_balance'], 2) }}
                                        </div>
                                        <div class="stat-label">Total Wallet Balance</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="stat-card">
                                        <div
                                            class="stat-value {{ $futureWallet['unrealized_profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                                            {{ $futureWallet['unrealized_profit'] >= 0 ? '+' : '' }}${{ number_format($futureWallet['unrealized_profit'], 2) }}
                                        </div>
                                        <div class="stat-label">Unrealized Profit</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value">${{ number_format($futureWallet['margin_balance'], 2) }}
                                        </div>
                                        <div class="stat-label">Margin Balance</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value">${{ number_format($futureWallet['available_balance'], 2) }}
                                        </div>
                                        <div class="stat-label">Available Balance</div>
                                    </div>
                                </div>
                            @else
                                <div class="col-12">
                                    <div class="alert alert-warning" role="alert">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Unable to fetch futures wallet data. Please check your API credentials.
                                    </div>
                                </div>
                            @endif
                        </div>

                        <h6 class="section-title">
                            <i class="fas fa-list-ul"></i>
                            Active Positions
                        </h6>

                        <div class="table-responsive">
                            <table class="table positions-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Side</th>
                                        <th>Size</th>
                                        <th>Entry Price</th>
                                        <th>Mark Price</th>
                                        <th>PNL</th>
                                        <th>Leverage</th>
                                        <th>Margin</th>
                                    </tr>
                                </thead>
                                <tbody id="futuresPositions">
                                    @if ($futureWallet && count($futureWallet['positions']) > 0)
                                        @foreach ($futureWallet['positions'] as $position)
                                            <tr>
                                                <td>
                                                    <div class="crypto-symbol">
                                                        <div class="crypto-icon">
                                                            {{ substr($position['symbol'], 0, 1) }}
                                                        </div>
                                                        {{ $position['symbol'] }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="position-badge {{ floatval($position['positionAmt']) > 0 ? 'position-long' : 'position-short' }}">
                                                        {{ floatval($position['positionAmt']) > 0 ? 'Long' : 'Short' }}
                                                    </span>
                                                </td>
                                                <td>{{ abs(floatval($position['positionAmt'])) }}</td>
                                                <td>${{ number_format(floatval($position['entryPrice']), 4) }}</td>
                                                <td>${{ number_format(floatval($position['markPrice']), 4) }}</td>
                                                <td
                                                    class="{{ floatval($position['unRealizedProfit']) >= 0 ? 'profit-positive' : 'profit-negative' }}">
                                                    {{ floatval($position['unRealizedProfit']) >= 0 ? '+' : '' }}${{ number_format(floatval($position['unRealizedProfit']), 2) }}
                                                </td>
                                                <td>{{ $position['leverage'] }}x</td>
                                                <td>
                                                    <span
                                                        class="badge badge-{{ $position['marginType'] === 'cross' ? 'info' : 'warning' }}">
                                                        {{ ucfirst($position['marginType']) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="8">
                                                <div class="no-positions">
                                                    <i class="fas fa-inbox fa-2x mb-3 opacity-50"></i>
                                                    <p class="mb-0">No active futures positions</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Spot Wallet Section --}}
        <div class="row mb-4">
            <div class="col">
                <div class="card wallet-card">
                    <div class="wallet-header">
                        <h5 class="wallet-title">
                            <i class="fas fa-coins icon-gradient"></i>
                            Spot Wallet
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            @if ($spotWallet)
                                {{-- Calculate total spot balance --}}
                                @php
                                    $totalSpotBalance = 0;
                                    $totalSpotLocked = 0;
                                    if (isset($spotWallet['total_assets'])) {
                                        foreach ($spotWallet['total_assets'] as $asset) {
                                            $totalSpotBalance += floatval($asset['free']);
                                            $totalSpotLocked += floatval($asset['locked']);
                                        }
                                    }
                                @endphp

                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value">${{ number_format($totalSpotBalance, 2) }}</div>
                                        <div class="stat-label">Available Balance</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value">${{ number_format($totalSpotLocked, 2) }}</div>
                                        <div class="stat-label">Locked Balance</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value">
                                            ${{ number_format($totalSpotBalance + $totalSpotLocked, 2) }}</div>
                                        <div class="stat-label">Total Balance</div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="stat-card">
                                        <div class="stat-value">
                                            {{ isset($spotWallet['open_orders']) ? count($spotWallet['open_orders']) : 0 }}
                                        </div>
                                        <div class="stat-label">Open Orders</div>
                                    </div>
                                </div>
                            @else
                                <div class="col-12">
                                    <div class="alert alert-warning" role="alert">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Unable to fetch spot wallet data. Please check your API credentials.
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Spot Assets --}}
                        <h6 class="section-title">
                            <i class="fas fa-coins"></i>
                            Assets
                        </h6>

                        <div class="table-responsive">
                            <table class="table positions-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Free</th>
                                        <th>Locked</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($spotWallet && isset($spotWallet['total_assets']) && count($spotWallet['total_assets']) > 0)
                                        @foreach ($spotWallet['total_assets'] as $asset)
                                            <tr>
                                                <td>
                                                    <div class="crypto-symbol">
                                                        <div class="crypto-icon">
                                                            {{ substr($asset['asset'], 0, 1) }}
                                                        </div>
                                                        {{ $asset['asset'] }}
                                                    </div>
                                                </td>
                                                <td>{{ number_format(floatval($asset['free']), 8) }}</td>
                                                <td>{{ number_format(floatval($asset['locked']), 8) }}</td>
                                                <td>{{ number_format(floatval($asset['free']) + floatval($asset['locked']), 8) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="4">
                                                <div class="no-positions">
                                                    <i class="fas fa-inbox fa-2x mb-3 opacity-50"></i>
                                                    <p class="mb-0">No assets found</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        {{-- Open Orders --}}
                        @if ($spotWallet && isset($spotWallet['open_orders']) && count($spotWallet['open_orders']) > 0)
                            <h6 class="section-title mt-4">
                                <i class="fas fa-clock"></i>
                                Open Orders
                            </h6>

                            <div class="table-responsive">
                                <table class="table positions-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Symbol</th>
                                            <th>Side</th>
                                            <th>Type</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($spotWallet['open_orders'] as $order)
                                            <tr>
                                                <td>
                                                    <div class="crypto-symbol">
                                                        <div class="crypto-icon">
                                                            {{ substr($order['symbol'], 0, 1) }}
                                                        </div>
                                                        {{ $order['symbol'] }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="position-badge {{ $order['side'] === 'BUY' ? 'position-long' : 'position-short' }}">
                                                        {{ $order['side'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $order['type'] }}</td>
                                                <td>{{ $order['origQty'] }}</td>
                                                <td>${{ number_format(floatval($order['price']), 4) }}</td>
                                                <td>
                                                    <span class="badge badge-warning">
                                                        {{ $order['status'] }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
