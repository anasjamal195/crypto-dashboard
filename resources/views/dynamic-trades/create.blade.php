@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Create Trade</div>
                <div class="card-body">
                    <form action="{{ route('dynamic-trading.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="tradeAccount" value="{{auth()->user()->id}}">
                        <div class="form-group">
                            <label for="market">Market</label>
                            <select name="market" class=" select2 form-control">
                                <option value="">Select an option</option>
                                <option value="SPOT">SPOT</option>
                                <option value="FUTURE">FUTURE</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="side">Side</label>
                            <select name="side" class="select2 form-control">
                                <option value="">Select an option</option>
                                <option value="BUY">BUY</option>
                                <option value="SELL">SELL</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="symbol">Symbol</label>
                            <input type="text" name="symbol" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount (Leave blank if SELL Selected)</label>
                            <input type="number" name="amount" step="0.001" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="qty">Quantity (Leave blank if BUY Selected)</label>
                            <input type="number" name="qty" step="0.001" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="leverage">Leverage (Leave blank if SPOT Selected)</label>
                            <input type="number" name="leverage"  class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="priceLock">Price Lock (Price to Trigger Action)</label>
                            <input type="number" name="priceLock" step="0.001" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="priceLockBuffer">Price Lock Buffer (Percentage Margin for Locked Price)</label>
                            <input type="number" name="priceLockBuffer" step="0.001" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="isActive">Active</label>
                            <select name="isActive" class="select2 form-control">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">Create Trade</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
