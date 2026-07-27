# Project Context — Crypto Dashboard Backtesting System

## What We Are Trying To Accomplish

Build and iteratively improve an automated **15m futures trading strategy** that combines **MACD multi-step entries** (primary) with **Support/Resistance scoring entries** (secondary), using **ATR-based dynamic TP/SL** that adapts per coin. The strategy is backtested on 3 coins (BTCUSDT, ETHUSDT, SOLUSDT) and must perform consistently across different market conditions (bullish, bearish, ranging).

### Entry Methods (in priority order)

1. **MACD multi-step** (`checkConditionSetLongMACD` / `checkConditionSetShortMACD`): 5-step sequential confirmation using volume, Bollinger Band position, RSI, %B, and higher-trend alignment. Most reliable method (~60-65% WR).
2. **S/R Scoring** (`checkConditionSetLongSR` / `checkConditionSetShortSR`): Scored entry using `SupportResistanceAnalyzer` signals + momentum/trend/volume/price-action sub-scores. Must score >= 80, be near support/resistance, pass volatility and ATR noise filters.

### Exit Logic

- **Trailing TP**: When price hits dynamicTP, it trails by `dynamicTPSLgap%` (0.4%), with SL moved to half the gap behind.
- **Fixed SL**: `dynamicSL` set at entry based on ATR.
- **Early close**: If within 3 candles of entry and price closes outside BB twice consecutively, close at market.

### ATR-Based TP/SL (overrides fixed percentages)

- `slPct = max(0.4, min(2.0, atrPercent * 0.8))`
- `tpPct = max(0.5, min(3.0, atrPercent * 1.2))`
- TP/SL are set once at entry using these percentages.

## Project Architecture

### Key Files

| File | Purpose |
|------|---------|
| `app/Services/InternalTrader/ReportServiceImproved.php` | **Main strategy file** — clone this to create new experiments |
| `app/Console/Commands/GenerateInternalReport.php` | Artisan command that runs the backtest |
| `app/CommonHelpers.php` | Shared utilities (5835 lines) — **DO NOT MODIFY** |
| `app/Services/InternalTrader/ReportServiceSafeMode.php` | Another variant |
| `app/Services/InternalTrader/BaseReportWorkers/BaseReport5m.php` | Base 5m worker (2030 lines) |
| `app/Services/InternalTrader/ReportService.php` | Original version |
| `app/Services/InternalTrader/ReportServiceHyperLiquid.php` | HyperLiquid variant |
| `app/Services/InternalTrader/ReportServiceTimeBased.php` | Time-based variant |

### How To Create a New Formula (Clone)

1. **Copy** `app/Services/InternalTrader/ReportServiceImproved.php` to a new file (e.g., `ReportServiceMyExperiment.php`)
2. Rename the class inside (e.g., `ReportServiceMyExperiment`)
3. In `app/Console/Commands/GenerateInternalReport.php`, **change the `use` statement** (line 5) to point to your new class:

```php
use App\Services\InternalTrader\ReportServiceMyExperiment;
```

4. Run with `php artisan app:generate-internal-report`

> Every run auto-generates a unique formula name (`{YourFormula} - {Day}, {Date} {Time}`) in `addFormulaDetails()`.

### DB Tables

**`coin_reports`** — One row per completed trade:
```
id, exchange, symbol, interval, market, openingTimestamp, position, 
previousCandle (JSON), buyingCandle (JSON), sellingCandle (JSON), 
buyingPrice, liquidationPrice, sellingPrice, lowestPrice, 
lowestPricePercentage, profit, closed_early, duration (minutes), 
created_at, formula, tagName (MACD/SR), openingVolumes, closingVolumes, 
confirmCandle, highestCandle
```

**`formula_details`** — One row per run (stores config + backup):
```
id, formula, details (HTML), report_config (JSON), created_at, 
updated_at, progress
```

**`confirmed_trades`** — Tracks MACD multi-step progression per coin:
```
ict_id, formula, exchange, coin_name, type, intention, 
openingTimestamp, confirm_candle_timestamp, candles_to_check, 
checkpoints (int, default 0), checkpoint_timestamp, trade_confirmed, ...
```

### How Timestamps Work

All timestamps are **millisecond Unix** (`binance_timestamp` in candle data).

- **`$backTestTimeUnix`**: Starting point. When `null`, it auto-calculates as `now - (interval_ms * limit)` (default: 15m * 1000 = ~10.4 days back).
- **`self::$limit = 1000`**: Number of 15m candles to fetch (~10.4 days).
- **`self::$initialWaitingCandles = 200`**: Skip first 200 candles (~2 days) to warm up indicators.
- **`CommonHelpers::$backtestingTimestamps`** (line 43 of CommonHelpers.php): A list of preset timestamps for different market conditions (bullish/bearish/flat). Use a specific one to cross-test.
- **`filterCandlestickData($data, $startTs, $endTs)`**: Filters array by `binance_timestamp` range. Used by `setCurrentFVG` (1h) and `setSRLevels` (4h) to align higher-TF data to current index.

### Data Flow

```
GenerateInternalReport (artisan)
  └─ ReportServiceImproved::generateCoinReport()
       ├─ addFormulaDetails() — registers run in formula_details, saves file backup
       ├─ For each coin:
       │    BinanceApiService::getCandleStickDataExtended(symbol, 15m, 1000, $backTestTimeUnix, FUTURE)
       │    └─ processCandles(symbol, data)
       │         ├─ Fetch 4h & 1h data for S/R and FVG
       │         ├─ Skip first 200 candles (initialWaitingCandles)
       │         ├─ setCurrentFVG() / setSRLevels() — per-candle state
       │         ├─ handleOpeningConditions() — tries MACD → SR
       │         │    ├─ checkConditionSetLongMACD → detectLongEntryWithSR
       │         │    └─ checkConditionSetShortMACD → detectShortEntryWithSR
       │         ├── If entry: set ATR-based TP/SL, record buyingCandle
       │         ├── Else (in trade): handleClosingConditions() — trail TP, check SL, early close
       │         └── When closed: insert into $trades[]
       │    └─ DB::table('coin_reports')->insert(self::$trades)
       └─ Returns formula name
```

### How the MACD Multi-Step Works

The `confirmed_trades` table acts as a state machine:

1. When **step 0 condition** is met and **no pending entry exists**, `insertConfirmBasicTradeEntry()` creates a row with `checkpoints=0`.
2. On each subsequent candle, if the **next step condition** fires AND the previous checkpoint matches, `updateConfirmTradeCheckpoint()` increments `checkpoints`.
3. Each step has a `candlesToCheck` timeout: if the candle window expires, the entry expires (`trade_confirmed=1`).
4. When **final step (step 4)** is reached, `confirmOpening()` marks it and `checkTrendOnHigherCandles()` + OBV/RSI filters run. If they pass, we get `'LONG'` / `'SHORT'`.

### How the S/R Scoring Entry Works

No state machine — it's a single-candle decision:

1. `SupportResistanceAnalyzer` analyzes 100 candles with `lookback=2` for structure.
2. `detectLongEntryWithSR` / `detectShortEntryWithSR` scores the candle on:
   - **SR signal** (buy/sell from analyzer, confidence >= 80, near support/resistance)
   - **Trend score** (MA alignment, BB position)
   - **Momentum score** (RSI, Stoch, Williams %R, MACD histogram)
   - **Volume score** (volume vs MA, OBV, MFI)
   - **Price action score** (wick/body ratio, engulfing, HH/HL patterns)
   - **Structure score** (support vs resistance counts, nearest level distance)
3. Also checks: RSI crossover (LONG: RSI6 crosses above 30 from below; SHORT: crosses below 65 from above), volatility filter (BB width vs ATR-adaptive threshold), ATR noise filter (`atrPct > 0.3`), VWAP distance (`> 5%`), histogram direction.

### ATR-Based Helpers

```php
getATRPercent($data, $index)  // Returns ATR14/close * 100, falls back to prev candle, default 0.8
getVolatilityThreshold($data, $index)  // max(0.04, min(0.15, atrPct * 0.04)) — adaptive BB width threshold
```

### Candle Data Available

Each candle object contains (from `BinanceApiService::processData`):
`timestamp_pst, timestamp, timestampReadable, symbol, interval, market, binance_timestamp, open, high, low, close, volume, body_min, body_max, body_size, upper_wick, lower_wick, volumeMA5, volumeMA10, avl, ma7, ma14, ma25, ma99, bb_middle, bb_upper, bb_lower, rsi6, rsi14, atr14, per, dif, dea, histogram, sar, should_buy, should_sell, obv, cvd, mfi, vwap, stoch_rsi, stoch_k, stoch_d, wr, K, D, J, previousObvHigh, previousObvLow, adx, di_plus, di_minus, ema12, ema26, ema200, cacheResetTime, exchange, formulaType, dynamicTP, dynamicSL, currentSupport, currentResistance, tagName`

## How To Test

```bash
# Run full backtest (all coins)
php artisan app:generate-internal-report

# Quick results read
php artisan tinker --execute="
\$f = DB::table('formula_details')->orderBy('created_at', 'DESC')->first();
\$t = DB::table('coin_reports')->where('formula', \$f->formula)->orderBy('id')->get();
\$w=\$t->where('profit','>',0); \$l=\$t->where('profit','<=',0);
echo 'Total: '.count(\$t).' | WR: '.round(count(\$w)/count(\$t)*100,1).'% | Avg: '.round(\$t->avg('profit'),2).'%';
"

# To test on a specific timestamp (e.g., bearish period):
# In GenerateInternalReport.php, add 'timestamp' => 1747925580000 to $reportDetails
```

### Selected Backtesting Timestamps (from CommonHelpers)

| Timestamp | Period |
|-----------|--------|
| `1746126000000` | Recent |
| `1740728740000` | Earlier |
| `1744830000000` | Mid period |
| `1748504740000` | Latest |
| `1732561200000` | Nov 2024 |
| `1744225200000` | Apr 2025 |
| `1722152740000` | Jul 2024 |
| `1719819940000` | Jul 2024 |
| `1725176740000` | Sep 2024 |

## Key Rules for Any Modification

1. **Do NOT modify `CommonHelpers.php`** — too large, too many side effects. Any needed improvements must be local to the strategy file.
2. **Do NOT modify `BinanceApiService.php`** (or any other shared service).
3. **Do NOT modify existing `ReportService*.php` files** — clone `ReportServiceImproved.php` to create experiments.
4. **All new logic goes inside the cloned strategy file** — entry conditions, scoring, filters, exit logic, helpers.
5. **The `$formulaACoins` / `$formulaBCoins` arrays** at the top of the class control which coins are tested.
6. **The `GenerateInternalReport.php` command** is the single entry point — change the `use` statement to switch strategies.

## Current Best Results (v4 — Improved Strategy v1 - Base)

| Metric | Value |
|--------|-------|
| Trades | 14 |
| Win Rate | 64.3% |
| Avg Profit | +0.27% |
| Profit Factor | 3.16 |
| MACD WR | 62.5% (8 trades) |
| SR WR | 66.7% (6 trades) |
| Period | ~10 days (14 Jul — 26 Jul 2026) |
