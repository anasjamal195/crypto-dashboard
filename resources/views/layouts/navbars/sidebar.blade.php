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
                            class="{{ $pageSlug == 'CoinReportSPOT' || $pageSlug == 'MarketTrendsSPOT' || $pageSlug == 'averageCandlesticksSPOT' || $pageSlug == 'liveTradeResultsSPOT' ? 'active' : '' }}">
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
                                    {{-- <li @if ($pageSlug == 'averageCandlesticksSPOT') class="active" @endif>
                                        <a href="{{ route('candle.averages', 'SPOT') . '?interval=1m' }}">
                                            <i class="tim-icons icon-bell-55"></i>
                                            <p>{{ __('Ideal Indicators') }}</p>
                                        </a>
                                    </li> --}}


                                    <li @if ($pageSlug == 'liveTradeResultsSPOT') class="active" @endif>
                                        <a href="{{ route('live.trades.result', 'SPOT') . '?interval=1m' }}">
                                            <i class="tim-icons icon-bell-55"></i>
                                            <p>{{ __('Live Trades') }}</p>
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
                                    {{-- <li @if ($pageSlug == 'averageCandlesticksFUTURE') class="active" @endif>
                                        <a href="{{ route('candle.averages', 'FUTURE') . '?interval=1m' }}">
                                            <i class="tim-icons icon-bell-55"></i>
                                            <p>{{ __('Ideal Indicators') }}</p>
                                        </a>
                                    </li> --}}

                                    <li @if ($pageSlug == 'liveTradeResultsFUTURE') class="active" @endif>
                                        <a href="{{ route('live.trades.result', 'FUTURE') . '?interval=1m' }}">
                                            <i class="tim-icons icon-bell-55"></i>
                                            <p>{{ __('Live Trades') }}</p>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        </li>

                    </ul>
                </div>
            </li>
            {{-- New Trade Handlers Menu --}}
            <li class="{{ request()->is('trade-handlers*') ? 'active' : '' }}">
                <a data-toggle="collapse" href="#tradeHandlersMenu"
                    aria-expanded="{{ request()->is('trade-handlers*') ? 'true' : 'false' }}">
                    <i class="tim-icons icon-chart-pie-36"></i>
                    <p>{{ __('Trade Handlers') }}
                        <b class="caret"></b>
                    </p>
                </a>
                <div class="collapse {{ request()->is('trade-handlers*') ? 'show' : '' }}" id="tradeHandlersMenu">
                    <ul class="nav">
                        <li class="{{ request()->routeIs('trade-handler.index') ? 'active' : '' }}">
                            <a href="{{ route('trade-handler.index') }}">
                                <i class="tim-icons icon-bullet-list-67"></i>
                                <p>{{ __('View Handlers') }}</p>
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('trade-handler.create') ? 'active' : '' }}">
                            <a href="{{ route('trade-handler.create') }}">
                                <i class="tim-icons icon-simple-add"></i>
                                <p>{{ __('Add Handler') }}</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- Dynamic Trading Module Menu --}}
            <li class="{{ request()->is('dynamic-trading*') ? 'active' : '' }}">
                <a data-toggle="collapse" href="#dynamicTradingMenu"
                    aria-expanded="{{ request()->is('dynamic-trading*') ? 'true' : 'false' }}">
                    <i class="tim-icons icon-chart-pie-36"></i>
                    <p>{{ __('Dynamic Trading') }}
                        <b class="caret"></b>
                    </p>
                </a>
                <div class="collapse {{ request()->is('dynamic-trading*') ? 'show' : '' }}" id="dynamicTradingMenu">
                    <ul class="nav">
                        <li class="{{ request()->routeIs('dynamic-trading.manage') ? 'active' : '' }}">
                            <!-- Example additional management link -->
                            <a href="{{ route('dynamic-trading.index') }}">
                                <i class="tim-icons icon-settings-gear-63"></i>
                                <p>{{ __('Spot Trades') }}</p>
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('dynamic-trading.create') ? 'active' : '' }}">
                            <a href="{{ route('dynamic-trading.create') }}">
                                <i class="tim-icons icon-simple-add"></i>
                                <p>{{ __('Add Trade') }}</p>
                            </a>
                        </li>

                    </ul>
                </div>
            </li>
            <li class="{{ request()->is('process-handlers*') ? 'active' : '' }}">
                <a data-toggle="collapse" href="#processHandlersCollapse" aria-expanded="false">
                    <i class="tim-icons icon-settings"></i>
                    <p>{{ __('Process Handlers') }}
                        <b class="caret"></b>
                    </p>
                </a>
                <div class="collapse {{ request()->is('process-handlers*') ? 'show' : '' }}"
                    id="processHandlersCollapse">
                    <ul class="nav">
                        <li class="{{ request()->routeIs('process-handler.index') ? 'active' : '' }}">
                            <a href="{{ route('process-handler.index') }}">
                                <i class="tim-icons icon-bullet-list-67"></i>
                                <p>{{ __('View All Processes') }}</p>
                            </a>
                        </li>

                    </ul>
                </div>
            </li>
            <li class="{{ request()->routeIs('internal.trader.settings', 'live.trader.settings') ? 'active' : '' }}">
                <a data-toggle="collapse" href="#settingsMenu"
                    aria-expanded="{{ request()->routeIs('internal.trader.settings', 'live.trader.settings') ? 'true' : 'false' }}">
                    <i class="tim-icons icon-settings"></i>
                    <p>{{ __('Settings') }}
                        <b class="caret"></b>
                    </p>
                </a>
                <div class="collapse {{ request()->routeIs('internal.trader.settings', 'live.trader.settings') ? 'show' : '' }}"
                    id="settingsMenu">
                    <ul class="nav">
                        <li class="{{ request()->routeIs('internal.trader.settings') ? 'active' : '' }}">
                            <a href="{{ route('internal.trader.settings') }}">
                                <i class="tim-icons icon-components"></i>
                                <p>{{ __('Internal Trader') }}</p>
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('live.trader.settings') ? 'active' : '' }}">
                            <a href="{{ route('live.trader.settings') }}">
                                <i class="tim-icons icon-spaceship"></i>
                                <p>{{ __('Live Trader') }}</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</div>
