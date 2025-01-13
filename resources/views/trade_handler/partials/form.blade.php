<div class="form-group">
    <label for="market">Market</label>
    <input type="text" class="form-control" id="market" name="market"
        value="{{ old('market', $tradeHandler->market ?? '') }}" required>
</div>

<div class="form-group">
    <label for="symbol">Symbol</label>
    <input type="text" class="form-control" id="symbol" name="symbol"
        value="{{ old('symbol', $tradeHandler->symbol ?? '') }}" required>
</div>

<div class="form-group">
    <label for="interval">Interval</label>
    <input type="text" class="form-control" id="interval" name="interval"
        value="{{ old('interval', $tradeHandler->interval ?? '') }}">
</div>

<div class="form-group">
    <label for="buyPrice">Buy Price</label>
    <input type="number" class="form-control" id="buyPrice" name="buyPrice" step="0.0001"
        value="{{ old('buyPrice', $tradeHandler->buyPrice ?? '') }}">
</div>

<div class="form-group">
    <label for="targetProfit">Target Profit</label>
    <input type="number" class="form-control" id="targetProfit" name="targetProfit" step="0.0001"
        value="{{ old('targetProfit', $tradeHandler->targetProfit ?? 0.5) }}">
</div>

<div class="form-group">
    <label for="rsiThreshold">RSI Threshold</label>
    <input type="number" class="form-control" id="rsiThreshold" name="rsiThreshold" step="0.0001"
        value="{{ old('rsiThreshold', $tradeHandler->rsiThreshold ?? 20) }}">
</div>

<div class="form-group">
    <label for="obvLimit">OBV Limit</label>
    <input type="number" class="form-control" id="obvLimit" name="obvLimit" step="0.0001"
        value="{{ old('obvLimit', $tradeHandler->obvLimit ?? 20) }}">
</div>

<div class="form-group">
    <label for="stochLimit">Stochastic Limit</label>
    <input type="number" class="form-control" id="stochLimit" name="stochLimit" step="0.0001"
        value="{{ old('stochLimit', $tradeHandler->stochLimit ?? 80) }}">
</div>

<div class="form-group">
    <label for="wrLimit">Williams %R Limit</label>
    <input type="number" class="form-control" id="wrLimit" name="wrLimit" step="0.0001"
        value="{{ old('wrLimit', $tradeHandler->wrLimit ?? -98) }}">
</div>

<input type="hidden" name="isActive" value="0">
<div class="form-group">
    <label for="isActive">Active</label>
    <input type="checkbox" id="isActive" name="isActive"
        {{ old('isActive', $tradeHandler->isActive ?? 0) ? 'checked' : '' }}>
</div>
