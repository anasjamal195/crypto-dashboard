@section('content')

    @php
        $accountTradeDetails = DB::table('account_trade_details')->latest()->get();
        $futureWallet = App\Services\BinanceApiService::fetchFutureWalletDetails(auth()->user()->id);
        $spotWallet = App\Services\BinanceApiService::fetchSpotWalletDetails(auth()->user()->id);

        $longAccuracyDetails = App\Jobs\ThreadsOrderBook\TriggersThread::getAccuracy('LONG');
        if ($longAccuracyDetails['accuracy'] < 0) {
            $longAccuracyDetails['accuracy'] = 100;
        }
        $shortAccuracyDetails = App\Jobs\ThreadsOrderBook\TriggersThread::getAccuracy('SHORT');
        if ($shortAccuracyDetails['accuracy'] < 0) {
            $shortAccuracyDetails['accuracy'] = 100;
        }
        // dd($accuracyDetailsLong,$accuracyDetailsShort);
    @endphp
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

    <div class="row">
        <div class="col-12">
            <div class="card card-stats mb-4">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="text-uppercase text-light ls-1 mb-1">Trading Performance</h6>
                            <h2 class="text-white mb-0">Last 6 Hours</h2>
                        </div>
                        <div class="col-auto">
                            <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                                <i class="tim-icons icon-chart-pie-36"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- LONG Positions Card --}}
        <div class="col-xl-6 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">
                                <i class="tim-icons icon-trend-up text-success mr-2"></i>
                                Long Positions
                            </h5>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="progress-wrapper" style="width: 60px; height: 60px;">
                                            <div class="progress-circle"
                                                data-percentage="{{ number_format($longAccuracyDetails['accuracy'], 1) }}">
                                                <span class="progress-left">
                                                    <span class="progress-bar border-success"></span>
                                                </span>
                                                <span class="progress-right">
                                                    <span class="progress-bar border-success"></span>
                                                </span>
                                                <div
                                                    class="progress-value w-100 h-100 rounded-circle d-flex align-items-center justify-content-center">
                                                    <div class="h6 font-weight-bold text-success">
                                                        {{ number_format($longAccuracyDetails['accuracy'], 1) }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-right">
                                        @if ($longAccuracyDetails['accuracy'] >= 75)
                                            <span class="badge badge-success badge-pill px-3 py-2">
                                                <i class="tim-icons icon-check-2 mr-1"></i>
                                                ACTIVE
                                            </span>
                                        @else
                                            <span class="badge badge-warning badge-pill px-3 py-2">
                                                <i class="tim-icons icon-button-pause mr-1"></i>
                                                PAUSED
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="row">
                        <div class="col-4 text-center">
                            <div class="text-success">
                                <h3 class="text-white mb-0">{{ $longAccuracyDetails['profits'] }}</h3>
                                <span class="text-success text-sm font-weight-bold">Profits</span>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="text-danger">
                                <h3 class="text-white mb-0">{{ $longAccuracyDetails['losses'] }}</h3>
                                <span class="text-danger text-sm font-weight-bold">Losses</span>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="text-info">
                                <h3 class="text-white mb-0">{{ $longAccuracyDetails['total'] }}</h3>
                                <span class="text-info text-sm font-weight-bold">Total</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="stats">
                        <i class="tim-icons icon-refresh-01 text-warning"></i>
                        Accuracy Threshold: 75%
                    </div>
                    <div class="stats">
                        <i class="tim-icons icon-refresh-01 text-warning"></i>
                        Last Updated: {{ $longAccuracyDetails['lastUpdateTime'] }}
                    </div>
                </div>
            </div>
        </div>

        {{-- SHORT Positions Card --}}
        <div class="col-xl-6 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">
                                <i class="tim-icons icon-trend-down text-danger mr-2"></i>
                                Short Positions
                            </h5>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="progress-wrapper" style="width: 60px; height: 60px;">
                                            <div class="progress-circle"
                                                data-percentage="{{ number_format($shortAccuracyDetails['accuracy'], 1) }}">
                                                <span class="progress-left">
                                                    <span class="progress-bar border-danger"></span>
                                                </span>
                                                <span class="progress-right">
                                                    <span class="progress-bar border-danger"></span>
                                                </span>
                                                <div
                                                    class="progress-value w-100 h-100 rounded-circle d-flex align-items-center justify-content-center">
                                                    <div class="h6 font-weight-bold text-danger">
                                                        {{ number_format($shortAccuracyDetails['accuracy'], 1) }}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-right">
                                        @if ($shortAccuracyDetails['accuracy'] >= 77)
                                            <span class="badge badge-success badge-pill px-3 py-2">
                                                <i class="tim-icons icon-check-2 mr-1"></i>
                                                ACTIVE
                                            </span>
                                        @else
                                            <span class="badge badge-warning badge-pill px-3 py-2">
                                                <i class="tim-icons icon-button-pause mr-1"></i>
                                                PAUSED
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="row">
                        <div class="col-4 text-center">
                            <div class="text-success">
                                <h3 class="text-white mb-0">{{ $shortAccuracyDetails['profits'] }}</h3>
                                <span class="text-success text-sm font-weight-bold">Profits</span>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="text-danger">
                                <h3 class="text-white mb-0">{{ $shortAccuracyDetails['losses'] }}</h3>
                                <span class="text-danger text-sm font-weight-bold">Losses</span>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="text-info">
                                <h3 class="text-white mb-0">{{ $shortAccuracyDetails['total'] }}</h3>
                                <span class="text-info text-sm font-weight-bold">Total</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="stats">
                        <i class="tim-icons icon-refresh-01 text-warning"></i>
                        Accuracy Threshold: 77%
                    </div>
                    <div class="stats">
                        <i class="tim-icons icon-refresh-01 text-warning"></i>
                        Last Updated: {{ $shortAccuracyDetails['lastUpdateTime'] }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Custom CSS for circular progress --}}
    <style>
        .progress-circle {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 60px;
        }

        .progress-circle .progress-left,
        .progress-circle .progress-right {
            position: absolute;
            top: 0;
            width: 30px;
            height: 60px;
            overflow: hidden;
        }

        .progress-circle .progress-left {
            left: 0;
        }

        .progress-circle .progress-right {
            right: 0;
        }

        .progress-circle .progress-bar {
            position: absolute;
            top: 0;
            width: 60px;
            height: 60px;
            box-sizing: border-box;
            border: 3px solid transparent;
            border-radius: 50%;
            background: transparent;
        }

        .progress-circle .progress-left .progress-bar {
            left: 0;
            border-right: 3px solid transparent;
            animation: loading-1 1.5s linear forwards;
        }

        .progress-circle .progress-right .progress-bar {
            right: 0;
            border-left: 3px solid transparent;
            animation: loading-2 1.5s linear forwards;
            animation-delay: 1.5s;
        }

        .progress-value {
            position: absolute;
            top: 0;
            left: 0;
        }

        @keyframes loading-1 {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(180deg);
            }
        }

        @keyframes loading-2 {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(180deg);
            }
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
                                        <div class="stat-value">
                                            ${{ number_format($futureWallet['available_balance'], 2) }}
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





        {{-- Section for account wallet details --}}
        <!-- Trading Bot Wallet Cards Component -->
        <style>
            .wallet-card {
                background: #27293d;
                border: none;
                border-radius: 15px;
                box-shadow: 0 4px 20px 0px rgba(0, 0, 0, 0.14), 0 7px 10px -5px rgba(0, 0, 0, 0.4);
                transition: all 0.3s ease;
                margin-bottom: 30px;
            }

            .wallet-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 30px 0px rgba(0, 0, 0, 0.2), 0 10px 15px -5px rgba(0, 0, 0, 0.5);
            }

            .wallet-card .card-header {
                background: transparent;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                padding: 20px 25px 10px;
            }

            .wallet-card .card-body {
                padding: 20px 25px;
                color: #ffffff;
            }

            .wallet-card .card-title {
                color: #ffffff;
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 0;
            }

            .wallet-card .card-subtitle {
                color: #9A9A9A;
                font-size: 14px;
                margin-bottom: 0;
            }

            .stat-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }

            .stat-item:last-child {
                border-bottom: none;
            }

            .stat-label {
                color: #9A9A9A;
                font-size: 14px;
                font-weight: 500;
            }

            .stat-value {
                color: #ffffff;
                font-weight: 600;
                font-size: 16px;
            }

            .stat-value.positive {
                color: #00f2c3;
            }

            .stat-value.negative {
                color: #fd5d93;
            }

            .stat-value.neutral {
                color: #1d8cf8;
            }

            .wallet-section {
                background: rgba(255, 255, 255, 0.05);
                border-radius: 10px;
                padding: 15px;
                margin: 15px 0;
            }

            .wallet-section h6 {
                color: #e14eca;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .asset-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 5px 0;
                font-size: 13px;
            }

            .asset-symbol {
                color: #ffffff;
                font-weight: 600;
            }

            .asset-amount {
                color: #9A9A9A;
            }

            .status-badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .status-active {
                background: rgba(0, 242, 195, 0.2);
                color: #00f2c3;
            }

            .status-inactive {
                background: rgba(154, 154, 154, 0.2);
                color: #9A9A9A;
            }
        </style>

        <div class="row">
            @foreach ($accountTradeDetails as $account)
                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                    <div class="card wallet-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    @php
                                        $user = \App\Models\User::find($account->account);
                                    @endphp
                                    <h5 class="card-title my-1">{{ $user->name ?? 'Unknown User' }}</h5>
                                    <p class="card-subtitle">Account ID: {{ $account->account }}</p>
                                </div>
                                <span class="badge bg-info badge-lg">
                                    {{ \Carbon\Carbon::parse($account->created_at)->format('l, F j, Y h:i A') }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Trading Statistics -->
                            <div class="stat-item">
                                <span class="stat-label">
                                    <i class="fas fa-chart-line me-2"></i>Total Trades
                                </span>
                                <span class="stat-value neutral">{{ $account->totalTrades }}</span>
                            </div>

                            <div class="stat-item">
                                <span class="stat-label">
                                    <i class="fas fa-sync-alt me-2"></i>Open Trades
                                </span>
                                <span class="stat-value {{ $account->openTrades > 0 ? 'positive' : 'neutral' }}">
                                    {{ $account->openTrades }}
                                </span>
                            </div>

                            <div class="stat-item">
                                <span class="stat-label">
                                    <i class="fas fa-dollar-sign me-2"></i>Realized P&L
                                </span>
                                <span
                                    class="stat-value {{ floatval($account->realizedPnl) > 0 ? 'positive' : (floatval($account->realizedPnl) < 0 ? 'negative' : 'neutral') }}">
                                    ${{ number_format(floatval($account->realizedPnl), 2) }}
                                </span>
                            </div>

                            <!-- Spot Wallet Section -->
                            @php
                                $spotWallet = json_decode($account->spotWalletCurrent, true);
                                $spotTotal = 0;
                            @endphp

                            @if ($spotWallet && isset($spotWallet['total_assets']))
                                <div class="wallet-section">
                                    <h6><i class="fas fa-wallet me-2"></i>Spot Wallet</h6>
                                    @foreach ($spotWallet['total_assets'] as $asset)
                                        @php $spotTotal += floatval($asset['free']); @endphp
                                        <div class="asset-item">
                                            <span class="asset-symbol">{{ $asset['asset'] }}</span>
                                            <span
                                                class="asset-amount">{{ number_format(floatval($asset['free']), 8) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Futures Wallet Section -->
                            @php
                                $futuresWallet = json_decode($account->futureWalletCurrent, true);
                            @endphp

                            @if ($futuresWallet)
                                <div class="wallet-section">
                                    <h6><i class="fas fa-chart-area me-2"></i>Futures Wallet</h6>
                                    <div class="asset-item">
                                        <span class="asset-symbol">Balance</span>
                                        <span
                                            class="asset-amount">${{ number_format($futuresWallet['wallet_balance'], 2) }}</span>
                                    </div>
                                    <div class="asset-item">
                                        <span class="asset-symbol">Available</span>
                                        <span
                                            class="asset-amount">${{ number_format($futuresWallet['available_balance'], 2) }}</span>
                                    </div>
                                    <div class="asset-item">
                                        <span class="asset-symbol">Unrealized P&L</span>
                                        <span
                                            class="asset-amount {{ $futuresWallet['unrealized_profit'] > 0 ? 'text-success' : ($futuresWallet['unrealized_profit'] < 0 ? 'text-danger' : '') }}">
                                            ${{ number_format($futuresWallet['unrealized_profit'], 2) }}
                                        </span>
                                    </div>
                                    @if (isset($futuresWallet['positions']) && count($futuresWallet['positions']) > 0)
                                        <div class="asset-item">
                                            <span class="asset-symbol">Open Positions</span>
                                            <span class="asset-amount">{{ count($futuresWallet['positions']) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <!-- Last Update -->
                            <div class="stat-item mt-3">
                                <span class="stat-label">
                                    <i class="fas fa-clock me-2"></i>Last Updated
                                </span>
                                <span class="stat-value" style="font-size: 12px;">
                                    {{ \Carbon\Carbon::parse($account->updated_at)->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
