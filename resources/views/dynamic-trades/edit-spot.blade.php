@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">{{ $trade ? 'Edit Trade' : 'Create Trade' }}</div>
                <div class="card-body">
                    <form action="{{ $trade ? route('dynamic-trading.update', [$trade->id, 'market' => 'SPOT']) : route('dynamic-trading.store', ['market' => 'SPOT']) }}" method="POST" class="form-horizontal">
                        @csrf
                        @if ($trade)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="tradeAccount" value="{{ auth()->user()->id }}">

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="side">Side</label>
                                <select name="side" id="side" class="select2 form-control" onchange="toggleFields()">
                                    <option value="">Select an option</option>
                                    <option value="BUY" {{ $trade && $trade->side == 'BUY' ? 'selected' : '' }}>Buy</option>
                                    <option value="SELL" {{ $trade && $trade->side == 'SELL' ? 'selected' : '' }}>Sell</option>
                                    <option value="TRADEPAIR" {{ $trade && $trade->side == 'TRADEPAIR' ? 'selected' : '' }}>Trade Pair</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="symbol">Symbol</label>
                                <input type="text" name="symbol" class="form-control" required value="{{ $trade ? $trade->symbol : '' }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="isActive">Active</label>
                                <select name="isActive" class="select2 form-control">
                                    <option value="1" {{ $trade && $trade->isActive ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ $trade && !$trade->isActive ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6" id="amountGroup" style="{{ $trade && ($trade->side == 'BUY' || $trade->side == 'TRADEPAIR') ? '' : 'display:none;' }}">
                                <label for="amount">Amount</label>
                                <input type="number" name="amount" step="0.00000001" class="form-control" value="{{ $trade ? $trade->amount : '' }}">
                            </div>
                            <div class="form-group col-md-6" id="qtyGroup" style="{{ $trade && $trade->side == 'SELL' ? '' : 'display:none;' }}">
                                <label for="qty">Quantity</label>
                                <input type="number" name="qty" step="0.00000001" class="form-control" value="{{ $trade ? $trade->qty : '' }}">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6" id="priceLockBuyGroup" style="{{ $trade && ($trade->side == 'BUY' || $trade->side == 'TRADEPAIR') ? '' : 'display:none;' }}">
                                <label for="priceLockBuy">Price Lock Buy (Price to Trigger Action)</label>
                                <input type="number" name="priceLockBuy" step="0.00000001" class="form-control" value="{{ $trade ? $trade->priceLockBuy : '' }}">
                            </div>
                            <div class="form-group col-md-6" id="priceLockBuyBufferGroup" style="{{ $trade && ($trade->side == 'BUY' || $trade->side == 'TRADEPAIR') ? '' : 'display:none;' }}">
                                <label for="priceLockBuyBuffer">Price Lock Buy Buffer (Percentage Margin for Locked Price)</label>
                                <input type="number" name="priceLockBuyBuffer" step="0.00000001" class="form-control" value="{{ $trade ? $trade->priceLockBuyBuffer : '' }}">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6" id="priceLockSellGroup" style="{{ $trade && ($trade->side == 'SELL' || $trade->side == 'TRADEPAIR') ? '' : 'display:none;' }}">
                                <label for="priceLockSell">Price Lock Sell (Price to Trigger Action)</label>
                                <input type="number" name="priceLockSell" step="0.00000001" class="form-control" value="{{ $trade ? $trade->priceLockSell : '' }}">
                            </div>
                            <div class="form-group col-md-6" id="priceLockSellBufferGroup" style="{{ $trade && ($trade->side == 'SELL' || $trade->side == 'TRADEPAIR') ? '' : 'display:none;' }}">
                                <label for="priceLockSellBuffer">Price Lock Sell Buffer (Percentage Margin for Locked Price)</label>
                                <input type="number" name="priceLockSellBuffer" step="0.00000001" class="form-control" value="{{ $trade ? $trade->priceLockSellBuffer : '' }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-success">{{ $trade ? 'Update Trade' : 'Create Trade' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function toggleFields() {
        const side = document.getElementById('side').value;
        const isBuy = side === 'BUY';
        const isSell = side === 'SELL';
        const isTradePair = side === 'TRADEPAIR';

        document.getElementById('amountGroup').style.display = isBuy || isTradePair ? 'block' : 'none';
        document.getElementById('qtyGroup').style.display = isSell ? 'block' : 'none';
        document.getElementById('priceLockBuyGroup').style.display = isBuy || isTradePair ? 'block' : 'none';
        document.getElementById('priceLockBuyBufferGroup').style.display = isBuy || isTradePair ? 'block' : 'none';
        document.getElementById('priceLockSellGroup').style.display = isSell || isTradePair ? 'block' : 'none';
        document.getElementById('priceLockSellBufferGroup').style.display = isSell || isTradePair ? 'block' : 'none';
    }
</script>
@endsection
