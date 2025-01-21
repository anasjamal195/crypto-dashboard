@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">{{ $trade ? 'Edit Future Trade' : 'Create Future Trade' }}</div>
                <div class="card-body">
                    <form action="{{ $trade ? route('dynamic-trading.update', $trade->id) : route('dynamic-trading.store') }}" method="POST" class="form-horizontal">
                        @csrf
                        @if ($trade)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="tradeAccount" value="{{ auth()->user()->id }}">
                        <input type="hidden" name="market" value="FUTURE">

                        <div class="form-group row">
                            <label for="position" class="col-md-2 col-form-label">Side</label>
                            <div class="col-md-4">
                                <select name="position" class="select2 form-control">
                                    <option value="">Select an option</option>
                                    <option value="BUY" {{ $trade && $trade->position == 'BUY' ? 'selected' : '' }}>BUY/LONG</option>
                                    <option value="SELL" {{ $trade && $trade->position == 'SELL' ? 'selected' : '' }}>SELL/SHORT</option>
                                </select>
                            </div>

                            <label for="symbol" class="col-md-2 col-form-label">Symbol</label>
                            <div class="col-md-4">
                                <input type="text" name="symbol" class="form-control" required value="{{ $trade ? $trade->symbol : '' }}">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="amount" class="col-md-2 col-form-label">Amount</label>
                            <div class="col-md-4">
                                <input type="number" name="amount" step="0.00000001" class="form-control" value="{{ $trade ? $trade->amount : '' }}">
                            </div>

                            <label for="leverage" class="col-md-2 col-form-label">Leverage</label>
                            <div class="col-md-4">
                                <input type="number" name="leverage" step="1" class="form-control" value="{{ $trade ? $trade->leverage : '' }}">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="priceLockOpen" class="col-md-2 col-form-label">Price Lock Open</label>
                            <div class="col-md-4">
                                <input type="number" name="priceLockOpen" step="0.00000001" class="form-control" value="{{ $trade ? $trade->priceLockOpen : '' }}">
                            </div>

                            <label for="priceLockOpenBuffer" class="col-md-2 col-form-label">Price Lock Buffer Open</label>
                            <div class="col-md-4">
                                <input type="number" name="priceLockOpenBuffer" step="0.00000001" class="form-control" value="{{ $trade ? $trade->priceLockOpenBuffer : '' }}">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="allowClose" class="col-md-2 col-form-label">Allow Close</label>
                            <div class="col-md-4">
                                <input type="checkbox" name="allowClose" id="allowClose" {{ $trade && $trade->allowClose ? 'checked' : '' }} onchange="toggleCloseFields()">
                            </div>

                            <div class="col-md-6" id="closeFields" style="{{ $trade && $trade->allowClose ? '' : 'display:none;' }}">
                                <div class="row">
                                    <label for="priceLockClose" class="col-md-6 col-form-label">Price Lock Close</label>
                                    <div class="col-md-6">
                                        <input type="number" name="priceLockClose" step="0.00000001" class="form-control" value="{{ $trade ? $trade->priceLockClose : '' }}">
                                    </div>

                                    <label for="priceLockCloseBuffer" class="col-md-6 col-form-label">Price Lock Close Buffer</label>
                                    <div class="col-md-6">
                                        <input type="number" name="priceLockCloseBuffer" step="0.00000001" class="form-control" value="{{ $trade ? $trade->priceLockCloseBuffer : '' }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-10 offset-md-2">
                                <button type="submit" class="btn btn-success">{{ $trade ? 'Update Trade' : 'Create Trade' }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function toggleCloseFields() {
        const allowCloseCheckbox = document.getElementById('allowClose');
        document.getElementById('closeFields').style.display = allowCloseCheckbox.checked ? 'block' : 'none';
    }
</script>
@endsection
