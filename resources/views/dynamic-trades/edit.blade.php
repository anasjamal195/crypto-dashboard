@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Edit Trade #{{ $trade->id }}</div>
                <div class="card-body">
                    <form action="{{ route('dynamic-trading.update', $trade->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="market">Market</label>
                            <select name="market" class="select2 form-control">
                                <option value="SPOT" {{ $trade->market == 'SPOT' ? 'selected' : '' }}>SPOT</option>
                                <option value="FUTURE" {{ $trade->market == 'FUTURE' ? 'selected' : '' }}>FUTURE</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="side">Side</label>
                            <select name="side" class="select2 form-control">
                                <option value="BUY" {{ $trade->side == 'BUY' ? 'selected' : '' }}>BUY</option>
                                <option value="SELL" {{ $trade->side == 'SELL' ? 'selected' : '' }}>SELL</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="symbol">Symbol</label>
                            <input type="text" name="symbol" class="form-control" value="{{ $trade->symbol }}" required>
                        </div>

                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <input type="number" name="amount" class="form-control" value="{{ $trade->amount }}">
                        </div>

                        <div class="form-group">
                            <label for="qty">Quantity</label>
                            <input type="number" name="qty" class="form-control" value="{{ $trade->qty }}">
                        </div>

                        <div class="form-group">
                            <label for="leverage">Leverage</label>
                            <input type="number" name="leverage" class="form-control" value="{{ $trade->leverage }}">
                        </div>

                        <div class="form-group">
                            <label for="priceLock">Price Lock</label>
                            <input type="number" name="priceLock" class="form-control" value="{{ $trade->priceLock }}">
                        </div>

                        <div class="form-group">
                            <label for="priceLockBuffer">Price Lock Buffer</label>
                            <input type="number" name="priceLockBuffer" class="form-control" value="{{ $trade->priceLockBuffer }}">
                        </div>

                        <div class="form-group">
                            <label for="isActive">Active</label>
                            <select name="isActive" class="select2  form-control">
                                <option value="1" {{ $trade->isActive ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ !$trade->isActive ? 'selected' : '' }}>No</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Trade</button>
                            <a href="{{ route('dynamic-trading.index') }}" class="btn btn-default">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
