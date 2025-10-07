@extends('layouts.app')

@section('content')
    <div class="content">
        <div class="container-fluid">

            {{-- ===== HEADER ===== --}}
            <div class="row mb-4">
                <div class="col-md-12 text-center">
                    <h3 class="text-white font-weight-bold mb-1">📊 Annual Performance Report</h3>
                    <p class="text-muted mb-0">Global summary, monthly stats, and visual performance overview</p>
                </div>
            </div>

            {{-- ===== PERFORMANCE CHART ===== --}}
            @if (isset($stats['monthly']) && count($stats['monthly']) > 0)
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bg-dark border-0 shadow-sm">
                            <div class="card-header pb-2">
                                <h5 class="text-info mb-0">📈 Performance Summary Chart</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="performanceChart" height="520"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            {{-- ===== GLOBAL STATS ===== --}}
            @if (isset($stats['global']))
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card bg-dark border-0 shadow-sm">
                            <div class="card-header pb-2">
                                <h5 class="text-info mb-0">🌍 Global Overview</h5>
                            </div>
                            <div class="card-body pt-2 pb-3">

                                {{-- Compact KPI Grid --}}
                                <div class="row text-center">
                                    <div class="col-4 col-md-2 mb-3">
                                        <h6 class="text-muted small">Trades</h6><span
                                            class="text-white font-weight-bold">{{ $stats['global']['total_trades'] }}</span>
                                    </div>
                                    <div class="col-4 col-md-2 mb-3">
                                        <h6 class="text-muted small">Long</h6><span
                                            class="text-info font-weight-bold">{{ $stats['global']['total_long'] }}</span>
                                    </div>
                                    <div class="col-4 col-md-2 mb-3">
                                        <h6 class="text-muted small">Short</h6><span
                                            class="text-info font-weight-bold">{{ $stats['global']['total_short'] }}</span>
                                    </div>
                                    <div class="col-4 col-md-2 mb-3">
                                        <h6 class="text-muted small">Win Rate</h6><span
                                            class="text-success font-weight-bold">{{ $stats['global']['win_rate'] }}%</span>
                                    </div>
                                    <div class="col-4 col-md-2 mb-3">
                                        <h6 class="text-muted small">Fee</h6><span
                                            class="text-warning font-weight-bold">{{ number_format($stats['global']['total_fee'], 2) }}%</span>
                                    </div>
                                    <div class="col-4 col-md-2 mb-3">
                                        <h6 class="text-muted small">Net Profit</h6><span
                                            class="{{ $stats['global']['net_profit'] >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">{{ number_format($stats['global']['net_profit'], 2) }}%</span>
                                    </div>
                                </div>

                                {{-- Strategy Table Compact --}}
                                <h6 class="text-info mt-4 mb-2">🎯 Strategy Performance</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-dark table-hover mb-0">
                                        <thead class="text-primary small">
                                            <tr>
                                                <th>Strategy</th>
                                                <th>Trades</th>
                                                <th>Win%</th>
                                                <th>Profit%</th>
                                                <th>Loss%</th>
                                                <th>Fee%</th>
                                                <th>Net%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($stats['global']['strategy_wise_results'] as $strategy => $s)
                                                <tr>
                                                    <td>{{ $strategy }}</td>
                                                    <td>{{ $s['total_trades'] }}</td>
                                                    <td>{{ $s['win_rate'] }}%</td>
                                                    <td class="text-success">{{ number_format($s['total_profit'], 2) }}%
                                                    </td>
                                                    <td class="text-danger">{{ number_format($s['total_loss'], 2) }}%</td>
                                                    <td class="text-warning">{{ number_format($s['total_fee'], 2) }}%</td>
                                                    <td
                                                        class="{{ $s['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($s['net_profit'], 2) }}%</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            @endif


            {{-- ===== MONTHLY STATS (Accordion) ===== --}}
            <div class="row">
                <div class="col-md-12">
                    <h5 class="text-white mb-3">📅 Monthly Breakdown</h5>

                    @foreach ($stats['monthly'] ?? [] as $month => $m)
                        <div class="card bg-dark border-0 shadow-sm mb-3">

                            {{-- ===== ALWAYS VISIBLE HEADER SECTION ===== --}}
                            <div class="card-body pb-2">

                                {{-- Month Header + Buttons --}}
                                <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                    <h6 class="text-info font-weight-bold mb-0">
                                        {{ ucfirst($month) }}
                                    </h6>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <a target="_blank"
                                            href="{{ route('marketTrends', 'FUTURE') }}?month={{ urlencode($month) }}&year={{ $stats['year'] ?? date('Y') }}&symbol={{ $stats['symbol'] ?? 'BTCUSDT' }}&interval={{ $stats['interval'] ?? '1h' }}&download=no&rerun=no"
                                            class="btn btn-sm btn-outline-info d-flex align-items-center px-2 py-1">
                                            <i class="fa fa-chart-line mr-1"></i> View Report
                                        </a>

                                        <a href="{{ route('marketTrends', 'FUTURE') }}?month={{ urlencode($month) }}&year={{ $stats['year'] ?? date('Y') }}&symbol={{ $stats['symbol'] ?? 'BTCUSDT' }}&interval={{ $stats['interval'] ?? '1h' }}&download=yes&rerun=no"
                                            class="btn btn-sm btn-outline-success d-flex align-items-center px-2 py-1">
                                            <i class="fa fa-download mr-1"></i> Download
                                        </a>

                                        {{-- Toggle Button for Strategy Details --}}
                                        <button class="btn btn-sm btn-outline-secondary px-2 py-1" data-toggle="collapse"
                                            data-target="#collapse-{{ $month }}">
                                            <i class="fa fa-list mr-1"></i> Strategies
                                        </button>
                                    </div>
                                </div>

                                {{-- Compact KPI Row (Always Visible) --}}
                                <div class="row text-center mb-2">
                                    <div class="col-4 col-md-2">
                                        <small class="text-muted">Trades</small><br>
                                        <span class="text-white font-weight-bold">{{ $m['total_trades'] }}</span>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <small class="text-muted">Win%</small><br>
                                        <span class="text-success font-weight-bold">{{ $m['win_rate'] }}%</span>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <small class="text-muted">Profit%</small><br>
                                        <span
                                            class="text-success font-weight-bold">{{ number_format($m['total_profit'], 2) }}%</span>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <small class="text-muted">Loss%</small><br>
                                        <span
                                            class="text-danger font-weight-bold">{{ number_format($m['total_loss'], 2) }}%</span>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <small class="text-muted">Fee%</small><br>
                                        <span
                                            class="text-warning font-weight-bold">{{ number_format($m['total_fee'], 2) }}%</span>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <small class="text-muted">Net%</small><br>
                                        <span
                                            class="{{ $m['net_profit'] >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                            {{ number_format($m['net_profit'], 2) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- ===== COLLAPSIBLE STRATEGY DETAILS ===== --}}
                            <div id="collapse-{{ $month }}" class="collapse">
                                <div class="card-body pt-0">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-dark table-hover mb-0">
                                            <thead class="text-primary small">
                                                <tr>
                                                    <th>Strategy</th>
                                                    <th>Trades</th>
                                                    <th>Win%</th>
                                                    <th>Profit%</th>
                                                    <th>Loss%</th>
                                                    <th>Fee%</th>
                                                    <th>Net%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($m['strategy_wise_results'] as $strategy => $s)
                                                    <tr>
                                                        <td>{{ $strategy }}</td>
                                                        <td>{{ $s['total_trades'] }}</td>
                                                        <td>{{ $s['win_rate'] }}%</td>
                                                        <td class="text-success">
                                                            {{ number_format($s['total_profit'], 2) }}%</td>
                                                        <td class="text-danger">{{ number_format($s['total_loss'], 2) }}%
                                                        </td>
                                                        <td class="text-warning">{{ number_format($s['total_fee'], 2) }}%
                                                        </td>
                                                        <td
                                                            class="{{ $s['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                            {{ number_format($s['net_profit'], 2) }}%
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        </div>
                    @endforeach
                </div>
            </div>



        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.2.1"></script>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const ctx = document.getElementById('performanceChart').getContext('2d');

                // Raw PHP data (may be objects, not arrays)
                const monthlyData = @json($stats['monthly'] ?? []);

                // Convert to arrays safely
                const months = Object.keys(monthlyData);
                const netProfit = months.map(m => parseFloat(monthlyData[m].net_profit ?? 0));
                const winRate = months.map(m => parseFloat(monthlyData[m].win_rate ?? 0));
                const totalTrades = months.map(m => parseInt(monthlyData[m].total_trades ?? 0));

                // Dynamic colors for bars
                const barColors = netProfit.map(v => v >= 0 ? 'rgba(0,210,91,0.6)' : 'rgba(255,65,54,0.6)');
                const borderColors = netProfit.map(v => v >= 0 ? '#00d25b' : '#ff4136');

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: months,
                        datasets: [{
                                label: 'Net Profit (%)',
                                data: netProfit,
                                backgroundColor: barColors,
                                borderColor: borderColors,
                                borderWidth: 1.5,
                                yAxisID: 'y',
                                order: 1
                            },
                            {
                                label: 'Win Rate (%)',
                                hidden: true,
                                data: winRate,
                                borderColor: '#1f8ef1',
                                backgroundColor: 'rgba(31,142,241,0.2)',
                                yAxisID: 'y',
                                type: 'line',
                                tension: 0.3,
                                borderWidth: 2,
                                pointRadius: 3,
                                order: 2
                            },
                            {
                                label: 'Total Trades',
                                hidden: true,
                                data: totalTrades,
                                backgroundColor: 'rgba(255,255,255,0.15)',
                                borderColor: '#bbb',
                                borderWidth: 1,
                                yAxisID: 'y1',
                                order: 3
                            }
                        ]
                    },
                    options: {
                        scales: {
                            y: {
                                type: 'linear',
                                position: 'left',
                                ticks: {
                                    color: '#ccc'
                                },
                                grid: {
                                    color: 'rgba(255,255,255,0.05)'
                                },
                                title: {
                                    display: true,
                                    text: 'Profit / Win %',
                                    color: '#aaa'
                                }
                            },
                            y1: {
                                type: 'linear',
                                position: 'right',
                                ticks: {
                                    color: '#aaa'
                                },
                                grid: {
                                    drawOnChartArea: false
                                },
                                title: {
                                    display: true,
                                    text: 'Total Trades',
                                    color: '#aaa'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#ccc'
                                },
                                grid: {
                                    color: 'rgba(255,255,255,0.05)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: '#fff'
                                }
                            },
                            tooltip: {
                                backgroundColor: '#222',
                                titleColor: '#fff',
                                bodyColor: '#eee'
                            },
                            annotation: {
                                annotations: {
                                    zeroLine: {
                                        type: 'line',
                                        yMin: 0,
                                        yMax: 0,
                                        borderColor: '#888',
                                        borderWidth: 1,
                                        borderDash: [4, 4],
                                        label: {
                                            enabled: true,
                                            content: 'Zero PnL',
                                            position: 'start',
                                            color: '#ccc',
                                            backgroundColor: 'rgba(0,0,0,0.6)',
                                            font: {
                                                size: 10
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: false
                    },
                    plugins: [Chart.registry.getPlugin('annotation')]
                });
            });
        </script>
    @endpush

@endsection
