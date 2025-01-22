@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Create Trade</div>
                    <div class="card-body">
                        <form action="{{ route('dynamic-trading.store', ['market' => 'SPOT']) }}" method="POST" id="tradeForm"
                            class="form-horizontal">
                            @csrf
                            <input type="hidden" name="tradeAccount" value="{{ auth()->user()->id }}">

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="side">Side</label>
                                    <select name="side" id="side" class="select2 form-control"
                                        onchange="toggleFields()">
                                        <option value="">Select an option</option>
                                        <option value="BUY">Buy</option>
                                        <option value="SELL">Sell</option>
                                        <option value="TRADEPAIR">Trade Pair</option>
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
                                <div class="form-group col-md-6" id="amountGroup">
                                    <label for="amount">Amount</label>
                                    <input type="number" name="amount" step="0.00000001" 
                                        class="form-control">
                                </div>

                                <div class="form-group col-md-6" id="qtyGroup">
                                    <label for="qty">Quantity</label>
                                    <input type="number" name="qty" id="qty" step="0.00000001"
                                        class="form-control">
                                    <button type="button" id="maxQty" class="btn btn-warning">Max</button>
                                </div>

                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6" id="priceLockBuyGroup">
                                    <label for="priceLockBuy">Price Lock Buy (Price to Trigger Action)</label>
                                    <input type="number" name="priceLockBuy" step="0.00000001" class="form-control">
                                </div>
                                <div class="form-group col-md-6" id="priceLockBuyBufferGroup">
                                    <label for="priceLockBuyBuffer">Price Lock Buy Buffer (Percentage Margin for Locked
                                        Price)</label>
                                    <input type="number" name="priceLockBuyBuffer" step="0.00000001" class="form-control">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6" id="priceLockSellGroup">
                                    <label for="priceLockSell">Price Lock Sell (Price to Trigger Action)</label>
                                    <input type="number" name="priceLockSell" step="0.00000001" class="form-control">
                                </div>
                                <div class="form-group col-md-6" id="priceLockSellBufferGroup">
                                    <label for="priceLockSellBuffer">Price Lock Sell Buffer (Percentage Margin for Locked
                                        Price)</label>
                                    <input type="number" name="priceLockSellBuffer" step="0.0000000000001"
                                        class="form-control">
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
            // document.getElementById('stopLossGroup').style.display = isSell || isTradePair ? 'block' : 'none';
            // document.getElementById('stopLossBufferGroup').style.display = isSell || isTradePair ? 'block' : 'none';
        }
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            let priceInterval; // Variable to store the interval ID

            // Function to update price
            function updatePrice(symbol) {
                $.ajax({
                    url: '{{ route('get.current.price') }}', // Adjust this to your route that fetches the price
                    type: 'GET',
                    data: {
                        symbol: symbol,
                        market: 'SPOT'
                    },
                    success: function(response) {
                        $('label[for="symbol"]').html(`Symbol ( ${response} USDT)`); // Assuming the response has a 'price' attribute
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching current price:', error);
                    }
                });
            }

            // Event handler for the symbol input field
            $('input[name="symbol"]').on('input', function() {
                const symbol = $(this).val();
                clearInterval(priceInterval); // Clear existing interval

                if (symbol && symbol.includes("USDT")) {
                    updatePrice(symbol); // Update immediately
                    priceInterval = setInterval(() => updatePrice(symbol), 2000); // Update every 2 seconds
                }
            });
            $('#maxQty').click(function() {
                var symbol = $('input[name="symbol"]')
                    .val(); // Assuming the symbol input has an ID of 'symbol'
                console.log(symbol)
                if (!symbol) {
                    return;
                }

                $.ajax({
                    url: '{{ route('get.available.balance') }}',
                    type: 'GET',
                    data: {
                        symbol: symbol,
                        market: 'SPOT'
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
