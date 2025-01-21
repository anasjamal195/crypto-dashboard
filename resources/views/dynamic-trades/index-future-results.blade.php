@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Future Trades Results</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Order ID</th>
                                    <th>Trade ID</th>
                                    <th>Symbol</th>
                                    <th>Side</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Quantity</th>
                                    <th>Leverage</th>
                                    <th>Liquidation Price</th>
                                    <th>Price</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($results as $result)
                                    <tr>
                                        <td>{{ $result->id }}</td>
                                        <td>{{ $result->orderId }}</td>
                                        <td>{{ $result->tradeId }}</td>
                                        <td>{{ $result->symbol }}</td>
                                        <td>{{ $result->side }}</td>
                                        <td>{{ $result->type }}</td>
                                        <td>{{ number_format($result->amount, 2) }}</td>
                                        <td>{{ number_format($result->qty, 2) }}</td>
                                        <td>{{ $result->leverage }}</td>
                                        <td>{{ number_format($result->liqPrice, 2) }}</td>
                                        <td>{{ number_format($result->price, 2) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($result->created_at)->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
