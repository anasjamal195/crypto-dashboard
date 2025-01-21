@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Create Future Trade</div>
                <div class="card-body">
                    <form action="{{ route('dynamic-trading.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="tradeAccount" value="{{ auth()->user()->id }}">
                        <input type="hidden" name="market" value="FUTURE">
                        
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="position">Side</label>
                                <select name="position" class="select2 form-control">
                                    <option value="">Select an option</option>
                                    <option value="BUY">BUY/LONG</option>
                                    <option value="SELL">SELL/SHORT</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="symbol">Symbol</label>
                                <input type="text" name="symbol" class="form-control" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="isActive">Active</label>
                                <select name="isActive" class="select2 form-control">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="amount">Amount</label>
                                <input type="number" name="amount" step="0.00000001" class="form-control">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="leverage">Leverage</label>
                                <input type="number" name="leverage" step="1" class="form-control">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="priceLockOpen">Price Lock Open (Price to Trigger Open)</label>
                                <input type="number" name="priceLockOpen" step="0.00000001" class="form-control">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="priceLockOpenBuffer">Price Lock Buffer Open</label>
                                <input type="number" name="priceLockOpenBuffer" step="0.00000001" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6" id="stopLossGroup">
                                <label for="stopLoss">Stop Loss</label>
                                <input type="number" name="stopLoss" step="0.00000001" class="form-control">
                            </div>
                            {{-- <div class="form-group col-md-6" id="stopLossBufferGroup">
                                <label for="stopLossBuffer">Stop Loss Buffer</label>
                                <input type="number" name="priceLockSellBuffer" step="0.00000001" class="form-control">
                            </div> --}}
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="allowClose">Allow Close</label>
                                <input type="checkbox" name="allowClose" id="allowClose" onchange="toggleCloseFields()">
                            </div>
                            <div class="form-group col-md-6" id="closeFields" style="display:none;">
                                <div class="form-row">
                                    <div class="col-md-6">
                                        <label for="priceLockClose">Price Lock Close (Price to Trigger Close)</label>
                                        <input type="number" name="priceLockClose" step="0.00000001" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="priceLockCloseBuffer">Price Lock Close Buffer </label>
                                        <input type="number" name="priceLockCloseBuffer" step="0.00000001" class="form-control">
                                    </div>
                                </div>
                            </div>
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
<script>
    function toggleCloseFields() {
        const allowCloseCheckbox = document.getElementById('allowClose');
        document.getElementById('closeFields').style.display = allowCloseCheckbox.checked ? 'block' : 'none';
    }
</script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#maxQty').click(function() {
                var symbol = $('input[name="symbol"]').val(); // Assuming the symbol input has an ID of 'symbol'
                console.log(symbol)
                if (!symbol) {
                    return;
                }

                $.ajax({
                    url: '{{ route('get.available.balance') }}' ,
                    type: 'GET',
                    data: {
                        symbol: symbol,
                        market: 'FUTURE'
                    },
                    success: function(response) {
                        $('input[name="qty"]').val(response
                        .free); // Update the Quantity field with the free balance
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching balance:', error);
                    }
                });
            });
        });
    </script>
@endsection
