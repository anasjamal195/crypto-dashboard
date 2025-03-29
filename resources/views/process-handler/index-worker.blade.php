@extends('layouts.app')

@php
@endphp
@section('content')
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h4 class="card-title">Trading Bot Workers</h4>
                                <p class="card-category">Real-time worker status monitoring</p>
                            </div>
                            <div class="col-4 text-right">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-primary btn-simple active">All</button>
                                    {{-- <button type="button" class="btn btn-primary btn-simple">Active</button>
                                    <button type="button" class="btn btn-primary btn-simple">Trading</button> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-primary">
                                    <tr>
                                        <th>Worker ID</th>
                                        <th>Status</th>
                                        <th>Symbols</th>
                                        <th>Capacity</th>
                                        <th>Last Updated</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($workers as $worker)
                                        @php
                                            $symbolCount = isset($workerSymbols[$worker->worker_id])
                                                ? $workerSymbols[$worker->worker_id]->count()
                                                : 0;
                                            $isTrading = $worker->trade_status ?? false;
                                            $isActive = $worker->active_status ?? false;
                                            $lastUpdate = $worker->updated_at;

                                            // Determine row style based on worker status
                                            $rowClass = '';
                                            if ($symbolCount == 10) {
                                                $rowClass = 'bg-warning-transparent';
                                            } elseif ($symbolCount == 0) {
                                                $rowClass = 'bg-danger-transparent';
                                            } elseif ($isTrading) {
                                                $rowClass = 'bg-success-transparent';
                                            }

                                            // Get worker symbols
                                            $workerSymbolsList = isset($workerSymbols[$worker->worker_id])
                                                ? $workerSymbols[$worker->worker_id]->pluck('symbol')->toArray()
                                                : [];
                                        @endphp

                                        <tr class="{{ $rowClass }}">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="worker-icon mr-3">
                                                        <i class="tim-icons icon-laptop text-primary"></i>
                                                    </div>
                                                    <span class="font-weight-bold">{{ $worker->worker_id }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-row">
                                                    @if ($isActive)
                                                        <span class="badge badge-success m-1">Active</span>
                                                    @else
                                                        <span class="badge badge-secondary m-1">Inactive</span>
                                                    @endif

                                                    @if ($symbolCount == 10)
                                                        <span class="badge badge-warning m-1">Max Capacity</span>
                                                    @elseif ($symbolCount == 0)
                                                        <span class="badge badge-danger m-1">Empty</span>
                                                    @elseif ($isTrading)
                                                        <span class="badge badge-success m-1">Trading</span>
                                                    @else
                                                        <span class="badge badge-info m-1">{{ $symbolCount }}
                                                            Symbol(s)</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="symbols-wrapper">
                                                    @if (count($workerSymbolsList) > 0)
                                                        @foreach ($workerSymbolsList as $symbol)
                                                            @php
                                                                $openOrder = DB::table('live_trades_future_results')
                                                                    ->where('symbol', $symbol)
                                                                    ->where('trade_status', 'open')
                                                                    ->first();
                                                                $openOrder = $isSymbolTrading =
                                                                    $isTrading && $openOrder;
                                                                $badgeClass = $isSymbolTrading
                                                                    ? 'badge-success'
                                                                    : 'badge-info';
                                                            @endphp
                                                            <span
                                                                class="badge {{ $badgeClass }} symbol-badge">{{ $symbol }}</span>
                                                        @endforeach
                                                    @else
                                                        <span class="text-muted">No symbols</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress-container progress-sm mr-2" style="width: 100px;">
                                                        @php
                                                            $progressClass = 'progress-info';
                                                            if ($symbolCount == 10) {
                                                                $progressClass = 'progress-warning';
                                                            } elseif ($symbolCount == 0) {
                                                                $progressClass = 'progress-danger';
                                                            } elseif ($isTrading) {
                                                                $progressClass = 'progress-success';
                                                            }
                                                            $percentage = ($symbolCount / 10) * 100;
                                                        @endphp
                                                        <div class="progress">
                                                            <div class="progress-bar {{ $progressClass }}"
                                                                role="progressbar" style="width: {{ $percentage }}%"
                                                                aria-valuenow="{{ $symbolCount }}" aria-valuemin="0"
                                                                aria-valuemax="10"></div>
                                                        </div>
                                                    </div>
                                                    <span>{{ $symbolCount }}/10</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="tim-icons icon-time-alarm text-muted mr-2"></i>
                                                    <span
                                                        title="{{ $lastUpdate }}">{{ Carbon\Carbon::parse($lastUpdate)->diffForHumans() }}</span>
                                                </div>
                                            </td>
                                            <td class="text-right">
                                                @if ($symbolCount > 0 && !$isTrading)
                                                    <a href="{{ route('worker-handler.flush',$worker->worker_id) }}" class="btn btn-sm btn-danger">
                                                        <i class="tim-icons icon-simple-remove"></i>
                                                        Flush Queue
                                                    </a>
                                                @elseif ($symbolCount == 0)
                                                    <button class="btn btn-sm btn-default" disabled>
                                                        Empty
                                                    </button>
                                                @elseif ($isTrading)
                                                    <button class="btn btn-sm btn-default" disabled>
                                                        Trading
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .bg-warning-transparent {
            background-color: rgba(255, 172, 0, 0.08);
        }

        .bg-danger-transparent {
            background-color: rgba(255, 54, 54, 0.08);
        }

        .bg-success-transparent {
            background-color: rgba(0, 213, 99, 0.08);
        }

        .table td {
            padding: 16px 12px;
            vertical-align: middle;
        }

        .table th {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            padding: 12px;
        }

        .symbols-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 300px;
        }

        .symbol-badge {
            font-size: 0.7rem;
            padding: 5px 8px;
        }

        .progress {
            height: 4px;
            margin-bottom: 0;
        }

        .progress-sm {
            height: 4px;
        }

        .worker-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: rgba(34, 42, 66, 0.2);
        }

        .worker-icon i {
            font-size: 14px;
        }

        tr {
            transition: all 0.2s ease;
        }

        tr:hover {
            background-color: rgba(34, 42, 66, 0.1) !important;
        }
    </style>
@endsection
