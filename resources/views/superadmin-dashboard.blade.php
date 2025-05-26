{{-- Updated PHP setup --}}


@section('content')

    @php
        $users = \App\Models\User::where('role', 'trader')->get();
        $walletsData = [];

        foreach ($users as $user) {
            $walletsData[] = [
                'user' => $user,
                'futureWallet' => json_decode(
                    json_encode(\App\Services\BinanceApiService::fetchFutureWalletDetails($user->id)),
                    true,
                ),
                'spotWallet' => json_decode(
                    json_encode(\App\Services\BinanceApiService::fetchSpotWalletDetails($user->id)),
                    true,
                ),
            ];
        }
    @endphp

    <style>
        .compact-wallet-card {
            background: rgba(31, 41, 55, 0.95);
            border: 1px solid rgba(55, 65, 81, 0.6);
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .compact-wallet-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .user-header {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(99, 102, 241, 0.1));
            border-bottom: 1px solid rgba(79, 70, 229, 0.2);
            padding: 0.75rem 1rem;
            border-radius: 8px 8px 0 0;
        }

        .user-title {
            color: #f3f4f6;
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }

        .domain-badge {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .compact-stat {
            text-align: center;
            padding: 0.75rem 0.5rem;
        }

        .compact-stat-value {
            font-size: 1rem;
            font-weight: 600;
            color: #60a5fa;
            margin-bottom: 0.25rem;
        }

        .compact-stat-label {
            font-size: 0.6rem;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profit-positive {
            color: #10b981 !important;
        }

        .profit-negative {
            color: #ef4444 !important;
        }

        .wallet-section {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(55, 65, 81, 0.3);
        }

        .wallet-section:last-child {
            border-bottom: none;
        }

        .section-header {
            color: #e5e7eb;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .positions-summary {
            background: rgba(17, 24, 39, 0.6);
            border-radius: 4px;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }

        .position-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0;
            font-size: 0.75rem;
            color: #d1d5db;
        }

        .position-pnl {
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            color: #6b7280;
            font-size: 0.75rem;
            padding: 0.5rem;
            font-style: italic;
        }

        .alert-compact {
            padding: 0.5rem;
            margin: 0.5rem 0;
            font-size: 0.75rem;
            border-radius: 4px;
        }
    </style>

    <div class="header bg-gradient-primary pb-4 pt-3">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-2">
                    <div class="col-12">
                        <h6 class="h3 text-white d-inline-block mb-0">
                            <i class="fas fa-users mr-2"></i>
                            Superadmin Dashboard
                        </h6>
                    </div>
                </div>
            </div>
            <form action="{{ route('master-process.master-process.sync-domain') }}" method="POST" class="d-flex align-items-center"
                style="gap: 5px;">
                @csrf
                <input type="text" name="domain_name" class="form-control form-control-sm"
                    placeholder="Sync new domain..." style="max-width: 180px;">
                <button type="submit" class="btn btn-sm btn-primary">Go</button>
            </form>
        </div>

    </div>

    <div class="container-fluid mt--6">


        <div class="row">
            @foreach ($walletsData as $walletData)
                <div class="col-lg-6 col-xl-4 mb-3">
                    <div class="card compact-wallet-card">
                        {{-- User Header --}}
                        <div class="user-header">
                            <div class="user-title">
                                <div class="user-info">
                                    <div class="user-avatar">
                                        {{ strtoupper(substr($walletData['user']->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div>{{ $walletData['user']->name }}</div>
                                        @if (isset($walletData['user']->domain_name))
                                            <small class="domain-badge">{{ $walletData['user']->domain_name }}</small>
                                        @endif
                                    </div>
                                </div>
                                <small class="text-muted">ID: {{ $walletData['user']->id }}</small>
                            </div>
                        </div>

                        {{-- Futures Wallet Section --}}
                        <div class="wallet-section">
                            <div class="section-header">
                                <i class="fas fa-chart-line"></i>
                                Futures
                            </div>

                            @if ($walletData['futureWallet'])
                                <div class="row no-gutters">
                                    <div class="col-6">
                                        <div class="compact-stat">
                                            <div class="compact-stat-value">
                                                ${{ number_format($walletData['futureWallet']['wallet_balance'], 0) }}
                                            </div>
                                            <div class="compact-stat-label">Balance</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="compact-stat">
                                            <div
                                                class="compact-stat-value {{ $walletData['futureWallet']['unrealized_profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                                                {{ $walletData['futureWallet']['unrealized_profit'] >= 0 ? '+' : '' }}${{ number_format($walletData['futureWallet']['unrealized_profit'], 0) }}
                                            </div>
                                            <div class="compact-stat-label">PNL</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Active Positions Summary --}}
                                @if (count($walletData['futureWallet']['positions']) > 0)
                                    <div class="positions-summary">
                                        <small class="text-muted d-block mb-1">
                                            <i class="fas fa-list"></i>
                                            Active Positions ({{ count($walletData['futureWallet']['positions']) }})
                                        </small>
                                        @foreach (array_slice($walletData['futureWallet']['positions'], 0, 3) as $position)
                                            <div class="position-item">
                                                <span>
                                                    {{ $position['symbol'] }}
                                                    <small
                                                        class="ml-1 {{ floatval($position['positionAmt']) > 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ floatval($position['positionAmt']) > 0 ? 'L' : 'S' }}
                                                    </small>
                                                </span>
                                                <span
                                                    class="position-pnl {{ floatval($position['unRealizedProfit']) >= 0 ? 'profit-positive' : 'profit-negative' }}">
                                                    ${{ number_format(floatval($position['unRealizedProfit']), 4) }}
                                                </span>
                                            </div>
                                        @endforeach
                                        @if (count($walletData['futureWallet']['positions']) > 3)
                                            <small
                                                class="text-muted">+{{ count($walletData['futureWallet']['positions']) - 3 }}
                                                more...</small>
                                        @endif
                                    </div>
                                @else
                                    <div class="no-data">No active positions</div>
                                @endif
                            @else
                                <div class="alert alert-warning alert-compact">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Futures API Error
                                </div>
                            @endif
                        </div>

                        {{-- Spot Wallet Section --}}
                        <div class="wallet-section">
                            <div class="section-header">
                                <i class="fas fa-coins"></i>
                                Spot
                            </div>

                            @if ($walletData['spotWallet'])
                                @php
                                    $totalSpotBalance = 0;
                                    $totalSpotLocked = 0;
                                    if (isset($walletData['spotWallet']['total_assets'])) {
                                        foreach ($walletData['spotWallet']['total_assets'] as $asset) {
                                            $totalSpotBalance += floatval($asset['free']);
                                            $totalSpotLocked += floatval($asset['locked']);
                                        }
                                    }
                                    $openOrdersCount = isset($walletData['spotWallet']['open_orders'])
                                        ? count($walletData['spotWallet']['open_orders'])
                                        : 0;
                                @endphp

                                <div class="row no-gutters">
                                    <div class="col-4">
                                        <div class="compact-stat">
                                            <div class="compact-stat-value">
                                                ${{ number_format($totalSpotBalance, 0) }}
                                            </div>
                                            <div class="compact-stat-label">Available</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="compact-stat">
                                            <div class="compact-stat-value">
                                                ${{ number_format($totalSpotLocked, 0) }}
                                            </div>
                                            <div class="compact-stat-label">Locked</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="compact-stat">
                                            <div class="compact-stat-value">
                                                {{ $openOrdersCount }}
                                            </div>
                                            <div class="compact-stat-label">Orders</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Top Assets Summary --}}
                                @if (isset($walletData['spotWallet']['total_assets']) && count($walletData['spotWallet']['total_assets']) > 0)
                                    <div class="positions-summary">
                                        <small class="text-muted d-block mb-1">
                                            <i class="fas fa-coins"></i>
                                            Top Assets
                                        </small>
                                        @foreach (array_slice($walletData['spotWallet']['total_assets'], 0, 3) as $asset)
                                            @if (floatval($asset['free']) + floatval($asset['locked']) > 0)
                                                <div class="position-item">
                                                    <span>{{ $asset['asset'] }}</span>
                                                    <span>{{ number_format(floatval($asset['free']) + floatval($asset['locked']), 4) }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <div class="no-data">No assets found</div>
                                @endif
                            @else
                                <div class="alert alert-warning alert-compact">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Spot API Error
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
@endsection
