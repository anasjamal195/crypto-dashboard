@props([
    'symbol',
    'interval',
    'coinData' => [],
    'isIndicatorForm' => false,
    'currentCandle' => [],
    'prevCandle' => [],
    'heading' => 'Trading Dashboard'
])

<div class="row">
    <div class="col-12">
        <div class="card card-chart">
            <div class="card-header">
                <div class="row align-items-center mb-3">
                    <div class="col-12">
                        <h3 class="card-title text-white mb-0">{{ $heading }}</h3>
                       
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <form action="" method="GET" class="trading-form">
                            <div class="row align-items-end">
                                <!-- Symbol Input -->
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <div class="form-group">
                                        <label for="symbol" class="form-label text-white">Symbol</label>
                                        <input type="text" 
                                               class="form-control form-control bg-dark mb-0 text-white border-secondary" 
                                               id="symbol" 
                                               name="symbol"
                                               value="{{ $symbol }}"
                                               placeholder="BTC/USDT">
                                    </div>
                                </div>

                                <!-- Interval Select -->
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <div class="form-group">
                                        <label for="interval" class="form-label text-white">Interval</label>
                                        <select name="interval" 
                                                id="interval" 
                                                class="form-control form-control-lg bg-dark text-white border-secondary select2">
                                            @foreach (\App\CommonHelpers::$binanceIntervals as $key => $value)
                                                <option value="{{ $key }}" {{ $interval === $key ? 'selected' : '' }}>
                                                    {{ $key }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Hidden Limit Input -->
                                <input type="hidden" class="form-control" id="limit" name="limit" value="1000">

                                @if ($isIndicatorForm)
                                    <!-- First Candle Select -->
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                        <div class="form-group">
                                            <label for="candle1" class="form-label text-white">First Candle</label>
                                            <select name="candle1" 
                                                    id="candle1" 
                                                    class="form-control form-control-lg bg-dark text-white border-secondary select2">
                                                @foreach (array_reverse($coinData) as $candle)
                                                    <option value="{{ $candle['binance_timestamp'] }}"
                                                            {{ $prevCandle['binance_timestamp'] === $candle['binance_timestamp'] ? 'selected' : '' }}>
                                                        {{ $candle['timestampReadable'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Second Candle Select -->
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                        <div class="form-group">
                                            <label for="candle2" class="form-label text-white">Second Candle</label>
                                            <select name="candle2" 
                                                    id="candle2" 
                                                    class="form-control form-control-lg bg-dark text-white border-secondary select2">
                                                @foreach (array_reverse($coinData) as $candle)
                                                    <option value="{{ $candle['binance_timestamp'] }}"
                                                            {{ $currentCandle['binance_timestamp'] === $candle['binance_timestamp'] ? 'selected' : '' }}>
                                                        {{ $candle['timestampReadable'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Update Button for Indicator Form -->
                                    <div class="col-lg-2 col-md-2 col-sm-6 mb-3">
                                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                                            <i class="fa fa-refresh mr-2"></i>Update
                                        </button>
                                    </div>
                                @else
                                    <!-- Update Button for Regular Form -->
                                    <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                                            <i class="fa fa-refresh mr-2"></i>Update
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles for Black Dashboard theme consistency */
.trading-form .form-control {
    background-color: #1e1e2f !important;
    border: 1px solid #4c4c6d !important;
    color: #ffffff !important;
    transition: all 0.3s ease;
}

.trading-form .form-control:focus {
    background-color: #252542 !important;
    border-color: #e14eca !important;
    box-shadow: 0 0 0 0.2rem rgba(225, 78, 202, 0.25) !important;
    color: #ffffff !important;
}

.trading-form .form-control::placeholder {
    color: #9a9a9a !important;
}

.trading-form .form-label {
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.trading-form .btn-primary {
    background: linear-gradient(45deg, #e14eca, #ba54f5) !important;
    border: none !important;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    height: 48px;
}

.trading-form .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 14px rgba(225, 78, 202, 0.4);
}

.card-chart {
    background: linear-gradient(87deg, #1a1a2e 0, #16213e 100%) !important;
    border: 1px solid #2d3748 !important;
}

.card-header {
    border-bottom: 1px solid #2d3748 !important;
    background: transparent !important;
}

.card-title {
    font-size: 1.5rem;
    font-weight: 600;
    background: linear-gradient(45deg, #e14eca, #ba54f5);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.card-category {
    font-size: 0.875rem;
    color: #8898aa !important;
}

/* Select2 Dark Theme Overrides */
.select2-container--default .select2-selection--single {
    background-color: #1e1e2f !important;
    border: 1px solid #4c4c6d !important;
    color: #ffffff !important;
    height: 48px !important;
    line-height: 46px !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #ffffff !important;
    padding-left: 12px;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 46px !important;
}

.select2-dropdown {
    background-color: #1e1e2f !important;
    border: 1px solid #4c4c6d !important;
    max-height: 300px;
    overflow-y:auto;
}

.select2-container--default .select2-results__option {
    background-color: #1e1e2f !important;
    color: #ffffff !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #e14eca !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .trading-form .col-lg-2,
    .trading-form .col-lg-3 {
        margin-bottom: 1rem;
    }
    
    .card-title {
        font-size: 1.25rem;
    }
}
</style>