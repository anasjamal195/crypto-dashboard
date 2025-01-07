
    <style>
        .nav .nav {
            padding-left: 20px;
            /* Adjust this value as needed */
        }

        .nav .collapse .nav-item {
            padding-left: 15px;
            /* Adjust this value to get the desired indentation */
        }
    </style>
    <div class="sidebar" data="blue">
        <div class="sidebar-wrapper">
            <div class="logo">
                <a href="#" class="simple-text logo-mini">{{ __('C') }}</a>
                <a href="#" class="simple-text logo-normal">{{ __('Crypto Api Store') }}</a>
            </div>
            {{-- 1m-CandleSticks Parent Tab --}}
            <ul class="nav">
                <li class="{{ request()->is('1m-candlesticks/*') ? 'active' : '' }}">
                    <a data-toggle="collapse" href="#candlesticksMenu" aria-expanded="true">
                        <i class="tim-icons icon-bank"></i>
                        <p>{{ __('1m-CandleSticks') }}
                            <b class="caret"></b>
                        </p>
                    </a>
                    <div class="collapse show" id="candlesticksMenu">
                        <ul class="nav">
                            {{-- SPOT Child Tab --}}
                            <li
                                class="{{ $pageSlug == 'CoinReportSPOT' || $pageSlug == 'MarketTrendsSPOT' || $pageSlug == 'averageCandlesticksSPOT' ? 'active' : '' }}">
                                <a data-toggle="collapse" href="#spotMenu">
                                    <i class="tim-icons icon-components"></i>
                                    <p>{{ __('SPOT') }}
                                        <b class="caret"></b>
                                    </p>
                                </a>
                                <div class="collapse" id="spotMenu">
                                    <ul class="nav">
                                        <li @if ($pageSlug == 'CoinReportSPOT') class="active" @endif>
                                            <a href="{{ route('coinReport', 'SPOT') . '?interval=1m' }}">
                                                <i class="tim-icons icon-coins"></i>
                                                <p>{{ __('Coin Reports') }}</p>
                                            </a>
                                        </li>
                                        <li @if ($pageSlug == 'MarketTrendsSPOT') class="active" @endif>
                                            <a href="{{ route('marketTrends', 'SPOT') . '?interval=1m' }}">
                                                <i class="tim-icons icon-chart-bar-32"></i>
                                                <p>{{ __('Market Trends') }}</p>
                                            </a>
                                        </li>
                                        <li @if ($pageSlug == 'averageCandlesticksSPOT') class="active" @endif>
                                            <a href="{{ route('candle.averages', 'SPOT').'?interval=1m' }}">
                                                <i class="tim-icons icon-bell-55"></i>
                                                <p>{{ __('Ideal Indicators') }}</p>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                            {{-- FUTURE Child Tab --}}
                            <li
                                class="{{ $pageSlug == 'CoinReportFUTURE' || $pageSlug == 'MarketTrendsFUTURE' || $pageSlug == 'averageCandlesticksFUTURE' ? 'active' : '' }}">
                                <a data-toggle="collapse" href="#futureMenu">
                                    <i class="tim-icons icon-spaceship"></i>
                                    <p>{{ __('FUTURE') }}
                                        <b class="caret"></b>
                                    </p>
                                </a>
                                <div class="collapse" id="futureMenu">
                                    <ul class="nav">
                                        <li @if ($pageSlug == 'CoinReportFUTURE') class="active" @endif>
                                            <a href="{{ route('coinReport', 'FUTURE') . '?interval=1m' }}">
                                                <i class="tim-icons icon-coins"></i>
                                                <p>{{ __('Coin Reports') }}</p>
                                            </a>
                                        </li>
                                        <li @if ($pageSlug == 'MarketTrendsFUTURE') class="active" @endif>
                                            <a href="{{ route('marketTrends', 'FUTURE') . '?interval=1m' }}">
                                                <i class="tim-icons icon-chart-bar-32"></i>
                                                <p>{{ __('Market Trends') }}</p>
                                            </a>
                                        </li>
                                        <li @if ($pageSlug == 'averageCandlesticksFUTURE') class="active" @endif>
                                            <a href="{{ route('candle.averages', 'FUTURE').'?interval=1m' }}">
                                                <i class="tim-icons icon-bell-55"></i>
                                                <p>{{ __('Ideal Indicators') }}</p>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        </ul>
                    </div>
                </li>
            </ul>
        </div>
    </div>
