@php

    $longAccuracyDetails = App\Jobs\ThreadsOrderBook\TriggersThread::getAccuracy(
        'LONG',
        $safeModeAccuracyFormula,
        $tagName,
    );
    if ($longAccuracyDetails['accuracy'] < 0) {
        $longAccuracyDetails['accuracy'] = 100;
    }
    $shortAccuracyDetails = App\Jobs\ThreadsOrderBook\TriggersThread::getAccuracy(
        'SHORT',
        $safeModeAccuracyFormula,
        $tagName,
    );
    if ($shortAccuracyDetails['accuracy'] < 0) {
        $shortAccuracyDetails['accuracy'] = 100;
    }
@endphp



<div class="row">
    {{-- LONG Positions Card --}}
    <div class="col-xl-6 col-md-6">
        <div class="card card-stats">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <h5 class="card-title text-uppercase text-muted mb-0">
                            <i class="tim-icons icon-trend-up text-success mr-2"></i>
                            Long Positions ({{ $safeModeAccuracyFormula }}) - {{ $tagName }}
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
                    Accuracy Threshold: {{ $longThreshold }}%
                </div>
                <div class="stats">
                    <i class="tim-icons icon-time-alarm text-info"></i>
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
                            Short Positions ({{ $safeModeAccuracyFormula }}) - {{ $tagName }}
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
                    Accuracy Threshold: {{ $shortThreshold }}%
                </div>
                <div class="stats">
                    <i class="tim-icons icon-time-alarm text-info"></i>
                    Last Updated: {{ $shortAccuracyDetails['lastUpdateTime'] }}
                </div>
            </div>
        </div>
    </div>
</div>
