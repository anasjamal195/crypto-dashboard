@extends('layouts.app')

@php
    use App\CommonHelpers;
    $buyTriggers = [];
    $sellTriggers = [];
    $confirmTriggers = [];
    $highestTriggers = [];
    $lowestTriggers = [];
    $liquidationTriggers = [];
    $oneHourMarks = [];
    $position = request()->get('position');
    $supportTriggers = [];
    $resistanceTriggers = [];

    $safeModeLogs = DB::table('safe_mode_logs')->where('symbol', $symbol)->where('formula', $formula)->first();

    $safeModeEnableTimestamps = $safeModeLogs ? json_decode($safeModeLogs->enable_timestamps, true) : [];
    $safeModeDisableTimestamps = $safeModeLogs ? json_decode($safeModeLogs->disable_timestamps, true) : [];

@endphp

@section('content')
    <style>
        .table-responsive {
            overflow-x: auto;
        }

        .tablesorter th:hover {
            cursor: pointer;
            background: rgba(255, 255, 255, 0.1);
        }

        .bg-dark {
            background-color: #1e1e2f !important;
        }

        .text-success {
            color: #00f2c3 !important;
        }

        .text-danger {
            color: #fd5d93 !important;
        }

        #candle-comparison td,
        #candle-comparison th {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        #candle-comparison thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
    </style>
    <h2 class="mb-4 text-white">
        @if (isset($position))
            @if (strtoupper($position) == 'LONG')
                <i class="fa fa-arrow-up text-success" title="Long Position"></i>
            @elseif(strtoupper($position) == 'SHORT')
                <i class="fa fa-arrow-down text-danger" title="Short Position"></i>
            @endif
        @endif
        Trade Details for {{ $symbol }} - {{ $interval }} ({{ $market }})
    </h2>

    <div class="row">
        <div class="col-md-12">



            <x-candlestick-chart :data="$data" symbol="{{ $symbol }}" interval="{{ $interval }}"
                :indicators="[]" :trades="$tradeMarkers" :markers="$otherMarkers" :lines="$lines" />

        </div>
    </div>


    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-primary">
                    <h4 class="card-title ">Trade Details</h4>
                    <p class="card-category"> Here is a subtitle for this table</p>
                </div>

                <div class="card-body">

                    <div class="table-responsive">
                        <table class="table">
                            <thead class="text-primary">
                                <tr>
                                    <th>Sr No.</th>
                                    <th>Buying Price (USDT)</th>
                                    <th>Extreme Price (USDT)</th>
                                    <th>Selling Price (USDT)</th>
                                    <th>Buying Time</th>
                                    <th>Selling Time</th>
                                    <th>Profit (%)</th>
                                    <th>Duration (mins)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trades as $indexTrades => $trade)
                                    @php

                                        $buyCandle = json_decode(json_encode($trade->buyingCandle), true);
                                        $sellCandle = json_decode(json_encode($trade->sellingCandle), true);

                                    @endphp


                                    <tr @if ($trade->profit <= 0) class="bg-danger" @endif>
                                        <td>
                                            {{ $indexTrades + 1 }}

                                        </td>
                                        <td>{{ number_format($trade->buyingPrice, 4) }}</td>

                                        <td>{{ number_format($trade->lowestPrice, 4) }}
                                            ({{ number_format($trade->lowestPricePercentage, 2) }}%)
                                        </td>
                                        <td>{{ number_format($trade->sellingPrice, 4) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($buyCandle['timestamp'])->format('h:i A') }}
                                        </td>

                                        <td>{{ \Carbon\Carbon::parse($sellCandle['timestamp'])->format('h:i A') }}
                                        </td>
                                        <td>{{ number_format($trade->profit, 2) }}%</td>
                                        <td>
                                            {{ \Carbon\CarbonInterval::minutes($trade->duration)->cascade()->forHumans() }}
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

    <script>
        function toggleDetails(tradeId) {
            const detailRow = document.getElementById(`details-${tradeId}`);
            const isHidden = detailRow.classList.contains('d-none');
            document.querySelectorAll('.trade-details').forEach(row => row.classList.add('d-none'));
            if (isHidden) {
                detailRow.classList.remove('d-none');
            }
        }
    </script>

    </div>


@endsection
