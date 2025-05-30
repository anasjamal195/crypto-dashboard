@props([
    'data',
    'symbol' => 'BTC/USDT',
    'interval' => '15m',
    'id' => 'chart',
    'indicators',
    'markers' => [], // New prop for markers
])


<div class="chart-wrapper">
    <div class="chart-header">
        <div class="symbol-info">
            <h3 class="symbol-title">{{ $symbol }}</h3>
            <span class="interval-badge">{{ strtoupper($interval) }}</span>
        </div>

        <div class="indicator-controls">
            <!-- Moving Averages -->
            <div class="indicator-group">
                <span class="group-title">Moving Averages</span>
                <div class="indicators">
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="ma7" {{ in_array('ma7', $indicators) ? 'checked' : '' }}>
                        <span class="ma7-color"></span>
                        <span class="indicator-text">MA 7</span>
                        <span class="legend-info" title="7-period Moving Average">(Orange)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="ma14"
                            {{ in_array('ma14', $indicators) ? 'checked' : '' }}>
                        <span class="ma14-color"></span>
                        <span class="indicator-text">MA 14</span>
                        <span class="legend-info" title="14-period Moving Average">(Teal)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="ma25"
                            {{ in_array('ma25', $indicators) ? 'checked' : '' }}>
                        <span class="ma25-color"></span>
                        <span class="indicator-text">MA 25</span>
                        <span class="legend-info" title="25-period Moving Average">(Blue)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="ma99"
                            {{ in_array('ma99', $indicators) ? 'checked' : '' }}>
                        <span class="ma99-color"></span>
                        <span class="indicator-text">MA 99</span>
                        <span class="legend-info" title="99-period Moving Average">(Green)</span>
                    </label>
                </div>
            </div>

            <!-- Overlays -->
            <div class="indicator-group">
                <span class="group-title">Overlays</span>
                <div class="indicators">
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="bb" {{ in_array('bb', $indicators) ? 'checked' : '' }}>
                        <span class="bb-color"></span>
                        <span class="indicator-text">Bollinger Bands</span>
                        <span class="legend-info" title="Bollinger Bands - Upper/Middle/Lower">(Yellow)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="sar" {{ in_array('sar', $indicators) ? 'checked' : '' }}>
                        <span class="sar-color"></span>
                        <span class="indicator-text">SAR</span>
                        <span class="legend-info" title="Parabolic SAR">(Pink)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="vwap"
                            {{ in_array('vwap', $indicators) ? 'checked' : '' }}>
                        <span class="vwap-color"></span>
                        <span class="indicator-text">VWAP</span>
                        <span class="legend-info" title="Volume Weighted Average Price">(Light Green)</span>
                    </label>
                </div>
            </div>

            <!-- Oscillators -->
            <div class="indicator-group">
                <span class="group-title">Oscillators</span>
                <div class="indicators">
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="volume"
                            {{ in_array('volume', $indicators) ? 'checked' : '' }}>
                        <span class="volume-color"></span>
                        <span class="indicator-text">Volume</span>
                        <span class="legend-info" title="Trading Volume">(Blue)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="rsi6"
                            {{ in_array('rsi6', $indicators) ? 'checked' : '' }}>
                        <span class="rsi-color"></span>
                        <span class="indicator-text">RSI(6)</span>
                        <span class="legend-info" title="6-period Relative Strength Index">(Red)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="stoch_rsi"
                            {{ in_array('stoch_rsi', $indicators) ? 'checked' : '' }}>
                        <span class="stoch-color"></span>
                        <span class="indicator-text">Stochastic RSI</span>
                        <span class="legend-info" title="Stochastic RSI %K and %D">(Teal/Light Teal)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="macd_hist"
                            {{ in_array('histogram', $indicators) ? 'checked' : '' }}>
                        <span class="macd-color"></span>
                        <span class="indicator-text">MACD</span>
                        <span class="legend-info" title="MACD Histogram, DIF and DEA lines">(Hist/DIF/DEA)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="mfi"
                            {{ in_array('mfi', $indicators) ? 'checked' : '' }}>
                        <span class="mfi-color"></span>
                        <span class="indicator-text">MFI</span>
                        <span class="legend-info" title="Money Flow Index">(Purple)</span>
                    </label>
                    <label class="indicator-label">
                        <input type="checkbox" data-indicator="adx"
                            {{ in_array('adx', $indicators) ? 'checked' : '' }}>
                        <span class="adx-color"></span>
                        <span class="indicator-text">ADX</span>
                        <span class="legend-info" title="ADX with DI+ and DI-">(Blue/Green/Red)</span>
                    </label>
                </div>
            </div>
        </div>


    </div>

    <div class="chart-controls">
        <div class="chart-actions">
            <button class="chart-btn" id="resetZoom">Reset Zoom</button>
            <button class="chart-btn" id="resetScale">Reset Scale</button>
            <button class="chart-btn" id="fitContent">Fit Content</button>
        </div>
        <div class="price-info" id="priceInfo">
            <span class="current-price"></span>
        </div>
    </div>

    <div class="indicator-values-panel" id="indicatorValues">
        <div class="values-header">
            <h4>Indicator Values</h4>
            <span class="hover-instruction">Hover over chart to see values</span>
        </div>
        <div class="values-grid" id="valuesGrid">
            <!-- OHLC Row -->
            <div class="values-row ohlc-row">
                <div class="row-title">OHLC</div>
                <div class="value-item">
                    <span class="value-label">O:</span>
                    <span class="value-data" id="openData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">H:</span>
                    <span class="value-data" id="highData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">L:</span>
                    <span class="value-data" id="lowData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">C:</span>
                    <span class="value-data" id="closeData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">Vol:</span>
                    <span class="value-data" id="volumeData">-</span>
                </div>
            </div>

            <!-- Moving Averages & Overlays Row -->
            <div class="values-row ma-row">
                <div class="row-title">MA & Overlays</div>
                <div class="value-item">
                    <span class="value-label">MA7:</span>
                    <span class="value-data" id="ma7Data">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">MA14:</span>
                    <span class="value-data" id="ma14Data">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">MA25:</span>
                    <span class="value-data" id="ma25Data">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">MA99:</span>
                    <span class="value-data" id="ma99Data">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">BB U:</span>
                    <span class="value-data" id="bbUpperData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">BB M:</span>
                    <span class="value-data" id="bbMiddleData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">BB L:</span>
                    <span class="value-data" id="bbLowerData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">SAR:</span>
                    <span class="value-data" id="sarData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">VWAP:</span>
                    <span class="value-data" id="vwapData">-</span>
                </div>
            </div>

            <!-- Oscillators Row -->
            <div class="values-row oscillators-row">
                <div class="row-title">Oscillators</div>
                <div class="value-item">
                    <span class="value-label">RSI:</span>
                    <span class="value-data" id="rsi6Data">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">MFI:</span>
                    <span class="value-data" id="mfiData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">Stoch K:</span>
                    <span class="value-data" id="stochKData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">Stoch D:</span>
                    <span class="value-data" id="stochDData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">MACD:</span>
                    <span class="value-data" id="macdHistData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">DIF:</span>
                    <span class="value-data" id="difData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">DEA:</span>
                    <span class="value-data" id="deaData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">ADX:</span>
                    <span class="value-data" id="adxData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">DI+:</span>
                    <span class="value-data" id="diPlusData">-</span>
                </div>
                <div class="value-item">
                    <span class="value-label">DI-:</span>
                    <span class="value-data" id="diMinusData">-</span>
                </div>
            </div>
        </div>
    </div>

    <div id="{{ $id }}" class="chart-container"></div>
</div>

@push('scripts')
    @once
        <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js">
        </script>
    @endonce
@endpush

@push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const chartData = @json($data);
            const chartId = "{{ $id }}";

            // New markers prop
            const markers = @json($markers); // Get markers from the props

            // Color scheme for dark theme
            const colors = {
                background: '#0d1421',
                grid: '#1e2329',
                text: '#848e9c',
                textSecondary: '#5e6d82',
                border: '#2b3139',
                upColor: '#02c076',
                downColor: '#f84960',
                ma7: '#ff6b35',
                ma14: '#4ecdc4',
                ma25: '#45b7d1',
                ma99: '#96ceb4',
                bb: '#ffd93d',
                sar: '#ff8c94',
                vwap: '#a8e6cf',
                rsi: '#ff6b6b',
                stoch: '#4ecdc4',
                macd: '#ffd93d',
                volume: 'rgba(130, 170, 227, 0.6)',
                mfi: '#ff9ff3',
                adx: '#54a0ff'
            };

            // Convert timestamp and prepare data
            function convertTime(timestamp) {
                return Math.floor(timestamp / 1000);
            }

            // Parse all data series
            const candles = chartData.map(c => ({
                time: convertTime(c.timestamp_pst),
                open: parseFloat(c.open),
                high: parseFloat(c.high),
                low: parseFloat(c.low),
                close: parseFloat(c.close),
                volume: parseFloat(c.volume),
            }));

            // Prepare all indicator data
            const seriesData = {
                ma7: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.ma7)
                })),
                ma14: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.ma14)
                })),
                ma25: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.ma25)
                })),
                ma99: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.ma99)
                })),
                bb_upper: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.bb_upper)
                })),
                bb_middle: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.bb_middle)
                })),
                bb_lower: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.bb_lower)
                })),
                sar: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.sar)
                })),
                vwap: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.vwap)
                })),
                volume: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.volume),
                    color: parseFloat(c.close) >= parseFloat(c.open) ? colors.upColor : colors
                        .downColor
                })),
                rsi6: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.rsi6)
                })),
                mfi: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.mfi)
                })),
                stoch_k: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.stoch_k)
                })),
                stoch_d: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.stoch_d)
                })),
                adx: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.adx)
                })),
                di_plus: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.di_plus)
                })),
                di_minus: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.di_minus)
                })),
                macd_hist: chartData.map((c, i) => {
                    const current = parseFloat(c.histogram);
                    const previous = i > 0 ? parseFloat(chartData[i - 1].histogram) : current;
                    const isIncreasing = current >= previous;
                    return {
                        time: convertTime(c.timestamp_pst),
                        value: current,
                        color: current >= 0 ?
                            (isIncreasing ? colors.upColor : 'rgba(2, 192, 118, 0.6)') : (isIncreasing ?
                                'rgba(248, 73, 96, 0.6)' : colors.downColor)
                    };
                }),
                dif: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.dif ||
                        0) // Use whichever field name you have in your data
                })),
                dea: chartData.map(c => ({
                    time: convertTime(c.timestamp_pst),
                    value: parseFloat(c.dea ||
                        0) // Use whichever field name you have in your data
                })),
            };

            // Create chart with professional styling
            const chart = LightweightCharts.createChart(document.getElementById(chartId), {
                layout: {
                    background: {
                        color: colors.background
                    },
                    textColor: colors.text,
                    fontSize: 12,
                    fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, sans-serif'
                },
                grid: {
                    vertLines: {
                        color: colors.grid,
                        style: 1,
                        visible: true
                    },
                    horzLines: {
                        color: colors.grid,
                        style: 1,
                        visible: true
                    },
                },
                timeScale: {
                    timeVisible: true,
                    secondsVisible: false,
                    borderColor: colors.border,
                    tickMarkFormatter: (time) => {
                        const date = new Date(time * 1000);
                        return date.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                },
                rightPriceScale: {
                    borderColor: colors.border,
                    textColor: colors.text,
                    scaleMargins: {
                        top: 0.1,
                        bottom: 0.2
                    }
                },
                crosshair: {
                    mode: LightweightCharts.CrosshairMode.Normal,
                    vertLine: {
                        color: colors.textSecondary,
                        labelBackgroundColor: colors.border
                    },
                    horzLine: {
                        color: colors.textSecondary,
                        labelBackgroundColor: colors.border
                    }
                },
                handleScroll: {
                    mouseWheel: true,
                    pressedMouseMove: true
                },
                handleScale: {
                    axisPressedMouseMove: true,
                    mouseWheel: true,
                    pinch: true
                },
            });

            // Main candlestick series
            const candleSeries = chart.addCandlestickSeries({
                upColor: colors.upColor,
                downColor: colors.downColor,
                borderUpColor: colors.upColor,
                borderDownColor: colors.downColor,
                wickUpColor: colors.upColor,
                wickDownColor: colors.downColor,
            });
            candleSeries.setData(candles);

            // Prepare the markers in the format needed by setMarkers
            const formattedMarkers = markers.map(marker => ({
                time: convertTime(marker.timestamp_pst), // Convert timestamp to chart's time format
                position: marker.position || 'aboveBar', // Default position is 'aboveBar'
                shape: marker.shape || 'circle', // Default shape is 'circle'
                color: marker.color || '#ff0000', // Default color is red
                size: marker.size || 1.5, // Default size is 1.5
                text: marker.text || '',
            }));

            // Add markers to the candlestick series
            candleSeries.setMarkers(formattedMarkers);


            // Volume series with proper scaling
            const volumeSeries = chart.addHistogramSeries({
                color: colors.volume,
                priceFormat: {
                    type: 'volume'
                },
                priceScaleId: 'volume',
                scaleMargins: {
                    top: 0.7,
                    bottom: 0.0
                },
                lastValueVisible: false,
                priceLineVisible: false
            });

            // RSI series
            const rsiSeries = chart.addLineSeries({
                color: colors.rsi,
                lineWidth: 2,
                priceScaleId: 'rsi',
                lastValueVisible: false,
                priceLineVisible: false
            });

            // MFI series  
            const mfiSeries = chart.addLineSeries({
                color: colors.mfi,
                lineWidth: 2,
                priceScaleId: 'rsi',
                lastValueVisible: false,
                priceLineVisible: false
            });

            // Stochastic RSI series
            const stochKSeries = chart.addLineSeries({
                color: colors.stoch,
                lineWidth: 2,
                priceScaleId: 'stoch',
                lastValueVisible: false,
                priceLineVisible: false
            });
            const stochDSeries = chart.addLineSeries({
                color: 'rgba(78, 205, 196, 0.7)',
                lineWidth: 2,
                priceScaleId: 'stoch',
                lastValueVisible: false,
                priceLineVisible: false
            });

            // MACD Histogram
            const macdHistSeries = chart.addHistogramSeries({
                priceScaleId: 'macd',
                lastValueVisible: false,
                priceLineVisible: false
            });
            const difSeries = chart.addLineSeries({
                color: '#00d4aa', // Teal color for DIF
                lineWidth: 2,
                priceScaleId: 'macd',
                lastValueVisible: false,
                priceLineVisible: false
            });
            const deaSeries = chart.addLineSeries({
                color: '#ff6b35', // Orange color for DEA
                lineWidth: 2,
                priceScaleId: 'macd',
                lastValueVisible: false,
                priceLineVisible: false
            });

            // ADX and DI series
            const adxSeries = chart.addLineSeries({
                color: colors.adx,
                lineWidth: 2,
                priceScaleId: 'adx',
                lastValueVisible: false,
                priceLineVisible: false
            });
            const diPlusSeries = chart.addLineSeries({
                color: colors.upColor,
                lineWidth: 1,
                priceScaleId: 'adx',
                lastValueVisible: false,
                priceLineVisible: false
            });
            const diMinusSeries = chart.addLineSeries({
                color: colors.downColor,
                lineWidth: 1,
                priceScaleId: 'adx',
                lastValueVisible: false,
                priceLineVisible: false
            });

            // Moving averages
            const ma7Series = chart.addLineSeries({
                color: colors.ma7,
                lineWidth: 2,
                priceLineVisible: false
            });
            const ma14Series = chart.addLineSeries({
                color: colors.ma14,
                lineWidth: 2,
                priceLineVisible: false
            });
            const ma25Series = chart.addLineSeries({
                color: colors.ma25,
                lineWidth: 2,
                priceLineVisible: false
            });
            const ma99Series = chart.addLineSeries({
                color: colors.ma99,
                lineWidth: 2,
                priceLineVisible: false
            });

            // Bollinger Bands
            const bbUpperSeries = chart.addLineSeries({
                color: colors.bb,
                lineWidth: 1,
                lineStyle: 2,
                priceLineVisible: false
            });
            const bbMiddleSeries = chart.addLineSeries({
                color: colors.bb,
                lineWidth: 1,
                lineStyle: 2,
                priceLineVisible: false
            });
            const bbLowerSeries = chart.addLineSeries({
                color: colors.bb,
                lineWidth: 1,
                lineStyle: 2,
                priceLineVisible: false
            });

            // SAR and VWAP
            const sarSeries = chart.addLineSeries({
                color: colors.sar,
                lineWidth: 1,
                lineStyle: 3,
                pointMarkersVisible: true,
                priceLineVisible: false
            });
            const vwapSeries = chart.addLineSeries({
                color: colors.vwap,
                lineWidth: 2,
                priceLineVisible: false
            });

            // Configure price scales with proper spacing
            chart.priceScale('volume').applyOptions({
                scaleMargins: {
                    top: 0.7,
                    bottom: 0.0
                },
                borderColor: colors.border,
            });

            chart.priceScale('rsi').applyOptions({
                scaleMargins: {
                    top: 0.85,
                    bottom: 0.05
                },
                borderColor: colors.border,
            });

            chart.priceScale('stoch').applyOptions({
                scaleMargins: {
                    top: 0.75,
                    bottom: 0.15
                },
                borderColor: colors.border,
            });

            chart.priceScale('macd').applyOptions({
                scaleMargins: {
                    top: 0.65,
                    bottom: 0.25
                },
                borderColor: colors.border,
            });

            chart.priceScale('adx').applyOptions({
                scaleMargins: {
                    top: 0.55,
                    bottom: 0.35
                },
                borderColor: colors.border,
            });

            // Series mapping for easy control
            const seriesMap = {
                ma7: ma7Series,
                ma14: ma14Series,
                ma25: ma25Series,
                ma99: ma99Series,
                bb: [bbUpperSeries, bbMiddleSeries, bbLowerSeries],
                sar: sarSeries,
                vwap: vwapSeries,
                volume: volumeSeries,
                rsi6: rsiSeries,
                mfi: mfiSeries,
                stoch_rsi: [stochKSeries, stochDSeries],
                macd_hist: macdHistSeries,
                adx: [adxSeries, diPlusSeries, diMinusSeries]
            };



            chart.subscribeCrosshairMove(param => {
                if (param.time) {
                    const timeIndex = chartData.findIndex(d => convertTime(d.timestamp_pst) === param
                        .time);
                    if (timeIndex >= 0) {
                        const data = chartData[timeIndex];

                        // Update OHLC data
                        const candleData = param.seriesData.get(candleSeries);
                        if (candleData) {
                            document.getElementById('openData').textContent = candleData.open?.toFixed(2) ||
                                '-';
                            document.getElementById('highData').textContent = candleData.high?.toFixed(2) ||
                                '-';
                            document.getElementById('lowData').textContent = candleData.low?.toFixed(2) ||
                                '-';
                            document.getElementById('closeData').textContent = candleData.close?.toFixed(
                                2) || '-';
                        }

                        // Update all indicator values
                        document.getElementById('volumeData').textContent = parseFloat(data.volume || 0)
                            .toLocaleString();
                        document.getElementById('ma7Data').textContent = parseFloat(data.ma7 || 0).toFixed(
                            4);
                        document.getElementById('ma14Data').textContent = parseFloat(data.ma14 || 0)
                            .toFixed(4);
                        document.getElementById('ma25Data').textContent = parseFloat(data.ma25 || 0)
                            .toFixed(4);
                        document.getElementById('ma99Data').textContent = parseFloat(data.ma99 || 0)
                            .toFixed(4);
                        document.getElementById('bbUpperData').textContent = parseFloat(data.bb_upper || 0)
                            .toFixed(4);
                        document.getElementById('bbMiddleData').textContent = parseFloat(data.bb_middle ||
                            0).toFixed(4);
                        document.getElementById('bbLowerData').textContent = parseFloat(data.bb_lower || 0)
                            .toFixed(4);
                        document.getElementById('sarData').textContent = parseFloat(data.sar || 0).toFixed(
                            4);
                        document.getElementById('vwapData').textContent = parseFloat(data.vwap || 0)
                            .toFixed(4);
                        document.getElementById('rsi6Data').textContent = parseFloat(data.rsi6 || 0)
                            .toFixed(2);
                        document.getElementById('mfiData').textContent = parseFloat(data.mfi || 0).toFixed(
                            2);
                        document.getElementById('stochKData').textContent = parseFloat(data.stoch_k || 0)
                            .toFixed(2);
                        document.getElementById('stochDData').textContent = parseFloat(data.stoch_d || 0)
                            .toFixed(2);
                        document.getElementById('macdHistData').textContent = parseFloat(data.histogram ||
                            0).toFixed(6);
                        document.getElementById('difData').textContent = parseFloat(data.dif || data
                            .macd_line || 0).toFixed(6);
                        document.getElementById('deaData').textContent = parseFloat(data.dea || data
                            .signal_line || 0).toFixed(6);
                        document.getElementById('adxData').textContent = parseFloat(data.adx || 0).toFixed(
                            2);
                        document.getElementById('diPlusData').textContent = parseFloat(data.di_plus || 0)
                            .toFixed(2);
                        document.getElementById('diMinusData').textContent = parseFloat(data.di_minus || 0)
                            .toFixed(2);
                    }
                } else {
                    // Clear values when not hovering
                    document.getElementById('openData').textContent = '-';
                    document.getElementById('highData').textContent = '-';
                    document.getElementById('lowData').textContent = '-';
                    document.getElementById('closeData').textContent = '-';
                    document.getElementById('volumeData').textContent = '-';
                    document.getElementById('ma7Data').textContent = '-';
                    document.getElementById('ma14Data').textContent = '-';
                    document.getElementById('ma25Data').textContent = '-';
                    document.getElementById('ma99Data').textContent = '-';
                    document.getElementById('bbUpperData').textContent = '-';
                    document.getElementById('bbMiddleData').textContent = '-';
                    document.getElementById('bbLowerData').textContent = '-';
                    document.getElementById('sarData').textContent = '-';
                    document.getElementById('vwapData').textContent = '-';
                    document.getElementById('rsi6Data').textContent = '-';
                    document.getElementById('mfiData').textContent = '-';
                    document.getElementById('stochKData').textContent = '-';
                    document.getElementById('stochDData').textContent = '-';
                    document.getElementById('macdHistData').textContent = '-';
                    document.getElementById('difData').textContent = '-';
                    document.getElementById('deaData').textContent = '-';
                    document.getElementById('adxData').textContent = '-';
                    document.getElementById('diPlusData').textContent = '-';
                    document.getElementById('diMinusData').textContent = '-';
                }
            });

            // Update chart function
            function updateChart() {
                document.querySelectorAll('.indicator-controls input[type="checkbox"]').forEach(cb => {
                    const indicator = cb.getAttribute('data-indicator');
                    const isChecked = cb.checked;

                    switch (indicator) {
                        case 'ma7':
                            ma7Series.setData(isChecked ? seriesData.ma7 : []);
                            break;
                        case 'ma14':
                            ma14Series.setData(isChecked ? seriesData.ma14 : []);
                            break;
                        case 'ma25':
                            ma25Series.setData(isChecked ? seriesData.ma25 : []);
                            break;
                        case 'ma99':
                            ma99Series.setData(isChecked ? seriesData.ma99 : []);
                            break;
                        case 'bb':
                            if (isChecked) {
                                bbUpperSeries.setData(seriesData.bb_upper);
                                bbMiddleSeries.setData(seriesData.bb_middle);
                                bbLowerSeries.setData(seriesData.bb_lower);
                            } else {
                                bbUpperSeries.setData([]);
                                bbMiddleSeries.setData([]);
                                bbLowerSeries.setData([]);
                            }
                            break;
                        case 'sar':
                            sarSeries.setData(isChecked ? seriesData.sar : []);
                            break;
                        case 'vwap':
                            vwapSeries.setData(isChecked ? seriesData.vwap : []);
                            break;
                        case 'volume':
                            volumeSeries.setData(isChecked ? seriesData.volume : []);
                            break;
                        case 'rsi6':
                            rsiSeries.setData(isChecked ? seriesData.rsi6 : []);
                            break;
                        case 'mfi':
                            mfiSeries.setData(isChecked ? seriesData.mfi : []);
                            break;
                        case 'stoch_rsi':
                            if (isChecked) {
                                stochKSeries.setData(seriesData.stoch_k);
                                stochDSeries.setData(seriesData.stoch_d);
                            } else {
                                stochKSeries.setData([]);
                                stochDSeries.setData([]);
                            }
                            break;
                        case 'macd_hist':
                            if (isChecked) {
                                macdHistSeries.setData(seriesData.macd_hist);
                                difSeries.setData(seriesData.dif);
                                deaSeries.setData(seriesData.dea);
                            } else {
                                macdHistSeries.setData([]);
                                difSeries.setData([]);
                                deaSeries.setData([]);
                            }
                            break;
                        case 'adx':
                            if (isChecked) {
                                adxSeries.setData(seriesData.adx);
                                diPlusSeries.setData(seriesData.di_plus);
                                diMinusSeries.setData(seriesData.di_minus);
                            } else {
                                adxSeries.setData([]);
                                diPlusSeries.setData([]);
                                diMinusSeries.setData([]);
                            }
                            break;
                    }
                });
            }

            // Initialize chart
            updateChart();

            // Add event listeners
            document.querySelectorAll('.indicator-controls input[type="checkbox"]').forEach(cb => {
                cb.addEventListener('change', updateChart);
            });

            // Chart controls
            document.getElementById('resetZoom').addEventListener('click', () => {
                chart.timeScale().resetTimeScale();
            });

            document.getElementById('fitContent').addEventListener('click', () => {
                chart.timeScale().fitContent();
            });
            document.getElementById('resetScale').addEventListener('click', () => {
                chart.priceScale('right').resetScale();
                chart.priceScale('volume').resetScale();
                chart.priceScale('rsi').resetScale();
                chart.priceScale('stoch').resetScale();
                chart.priceScale('macd').resetScale();
                chart.priceScale('adx').resetScale();
            });


            // Socket Connection for live data
            // Update price info
            // candleSeries.subscribeClick((param) => {
            //     if (param.time) {
            //         const data = param.seriesData.get(candleSeries);
            //         if (data) {
            //             document.getElementById('priceInfo').innerHTML = `
        //                 <span class="price-label">O:</span> <span class="price-value">${data.open.toFixed(2)}</span>
        //                 <span class="price-label">H:</span> <span class="price-value">${data.high.toFixed(2)}</span>
        //                 <span class="price-label">L:</span> <span class="price-value">${data.low.toFixed(2)}</span>
        //                 <span class="price-label">C:</span> <span class="price-value">${data.close.toFixed(2)}</span>
        //             `;
            //         }
            //     }
            // });

            // Set initial price info with latest candle
            // if (candles.length > 0) {
            //     const latest = candles[candles.length - 1];
            //     document.getElementById('priceInfo').innerHTML = `
        //         <span class="price-label">O:</span> <span class="price-value">${latest.open.toFixed(2)}</span>
        //         <span class="price-label">H:</span> <span class="price-value">${latest.high.toFixed(2)}</span>
        //         <span class="price-label">L:</span> <span class="price-value">${latest.low.toFixed(2)}</span>
        //         <span class="price-label">C:</span> <span class="price-value">${latest.close.toFixed(2)}</span>
        //     `;
            // }

            // Handle window resize
            window.addEventListener('resize', () => {
                chart.applyOptions({
                    width: document.getElementById(chartId).clientWidth,
                });
            });


        });
    </script>
@endpush

<style>
    .chart-wrapper {
        background: linear-gradient(135deg, #0d1421 0%, #1a1f2e 100%);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        border: 1px solid #2b3139;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .chart-header {
        margin-bottom: 1.5rem;
        border-bottom: 1px solid #2b3139;
        padding-bottom: 1rem;
    }

    .symbol-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .symbol-title {
        color: #ffffff;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .interval-badge {
        background: linear-gradient(135deg, #4ecdc4, #44a08d);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .indicator-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 2rem;
    }

    .indicator-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .group-title {
        color: #848e9c;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.25rem;
    }

    .indicators {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .indicator-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #ffffff;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s ease;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
    }

    .indicator-label:hover {
        background: rgba(255, 255, 255, 0.05);
        transform: translateY(-1px);
    }

    .indicator-label input[type="checkbox"] {
        accent-color: #4ecdc4;
        scale: 1.1;
    }

    /* Color indicators */
    .ma7-color,
    .ma14-color,
    .ma25-color,
    .ma99-color,
    .bb-color,
    .sar-color,
    .vwap-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }

    .ma7-color {
        background: #ff6b35;
    }

    .ma14-color {
        background: #4ecdc4;
    }

    .ma25-color {
        background: #45b7d1;
    }

    .ma99-color {
        background: #96ceb4;
    }

    .bb-color {
        background: #ffd93d;
    }

    .sar-color {
        background: #ff8c94;
    }

    .vwap-color {
        background: #a8e6cf;
    }

    .chart-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding: 0.75rem;
        background: rgba(43, 49, 57, 0.3);
        border-radius: 8px;
        border: 1px solid #2b3139;
    }

    .chart-actions {
        display: flex;
        gap: 0.5rem;
    }

    .chart-btn {
        background: linear-gradient(135deg, #4ecdc4, #44a08d);
        border: none;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(78, 205, 196, 0.2);
    }

    .chart-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(78, 205, 196, 0.3);
    }

    .price-info {
        display: flex;
        gap: 1rem;
        align-items: center;
        font-family: 'Courier New', monospace;
    }

    .price-label {
        color: #848e9c;
        font-weight: 600;
        font-size: 0.8rem;
    }

    .price-value {
        color: #ffffff;
        font-weight: 700;
        font-size: 0.875rem;
    }

    .chart-container {
        width: 100%;
        height: 700px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2);
        border: 1px solid #2b3139;
    }

    .indicator-values-panel {
        background: rgba(43, 49, 57, 0.4);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid #2b3139;
    }

    .values-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #2b3139;
    }

    .values-header h4 {
        color: #ffffff;
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
    }

    .hover-instruction {
        color: #848e9c;
        font-size: 0.75rem;
        font-style: italic;
    }

    .values-grid {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .values-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 6px;
        border-left: 3px solid transparent;
    }

    .ohlc-row {
        border-left-color: #4ecdc4;
    }

    .ma-row {
        border-left-color: #ffd93d;
    }

    .oscillators-row {
        border-left-color: #ff6b6b;
    }

    .row-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 0.8rem;
        min-width: 80px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .values-row .value-item {
        background: rgba(255, 255, 255, 0.05);
        padding: 0.2rem 0.4rem;
        border-radius: 4px;
        font-size: 0.75rem;
        min-width: 60px;
        flex-shrink: 0;
    }


    .value-label {
        color: #848e9c;
        font-weight: 500;
    }

    .value-data {
        color: #ffffff;
        font-weight: 600;
        font-family: 'Courier New', monospace;
    }

    /* Legend improvements */
    .indicator-text {
        margin-right: 0.25rem;
    }

    .legend-info {
        color: #5e6d82;
        font-size: 0.7rem;
        font-style: italic;
        opacity: 0.8;
    }

    .indicator-label:hover .legend-info {
        opacity: 1;
        color: #848e9c;
    }

    .volume-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        background: rgba(130, 170, 227, 0.8);
    }

    .rsi-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        background: #ff6b6b;
    }

    .stoch-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        background: linear-gradient(45deg, #4ecdc4 50%, rgba(78, 205, 196, 0.7) 50%);
    }

    .macd-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        background: linear-gradient(45deg, #02c076 50%, #f84960 50%);
    }

    .mfi-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        background: #ff9ff3;
    }

    .adx-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        background: linear-gradient(120deg, #54a0ff 33%, #02c076 33%, #02c076 66%, #f84960 66%);
    }

    /* Responsive design */
    @media (max-width: 1024px) {
        .indicator-controls {
            flex-direction: column;
            gap: 1rem;
        }

        .indicators {
            flex-direction: column;
            gap: 0.5rem;
        }

        .chart-controls {
            flex-direction: column;
            gap: 1rem;
        }

        .price-info {
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .chart-wrapper {
            padding: 1rem;
        }

        .symbol-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .chart-container {
            height: 500px;
        }

        .values-grid {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.25rem;
        }

        .value-item {
            font-size: 0.75rem;
            padding: 0.2rem 0.4rem;
        }

        .legend-info {
            display: none;
        }

        .values-row {
            flex-wrap: wrap;
        }

        .row-title {
            width: 100%;
            margin-bottom: 0.25rem;
        }
    }

    @media (max-width: 480px) {
        .values-grid {
            grid-template-columns: 1fr 1fr;
        }

        .values-header {
            flex-direction: column;
            gap: 0.25rem;
            align-items: flex-start;
        }
    }
</style>
