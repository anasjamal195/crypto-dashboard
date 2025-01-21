@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Trades List</h4>
                        <a href="{{ route('dynamic-trading.create', ['market' => request()->get('market')]) }}"
                            class="btn btn-success"><i class="fa fa-plus"></i> Add New Trade</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Symbol</th>
                                        <th>Amount</th>
                                        <th>Quantity</th>
                                        <th>Side</th>
                                        <th>Price Lock Buy</th>
                                        <th>Price Lock Buffer Buy</th>
                                        <th>Price Lock Sell</th>
                                        <th>Price Lock Buffer Sell</th>
                                        <th>Status</th>
                                        <th>Active</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trades as $trade)
                                        <tr>
                                            <td>{{ $trade->id }}</td>
                                            <td>{{ $trade->symbol }}</td>
                                            <td>{{ number_format($trade->amount, 5) }}</td>
                                            <td>{{ number_format($trade->qty, 5) }}</td>
                                            <td>{{ $trade->side }}</td>
                                            <td>
                                                {{ number_format($trade->priceLockBuy, 5) }}
                                            </td>

                                            <td>
                                                {{ $trade->priceLockBuyBuffer }}
                                            </td>
                                            <td>
                                                {{ number_format($trade->priceLockSell, 5) }}
                                            </td>

                                            <td>
                                                {{ $trade->priceLockSellBuffer }}
                                            </td>
                                            <td>{{ $trade->status }}</td>
                                            <td>{{ $trade->isActive ? 'Yes' : 'No' }}</td>
                                            <td class="action-btn">
                                                <a href="{{ route('dynamic-trading.edit', $trade->id) }}?market=SPOT"
                                                    class="btn btn-primary btn-sm"><i class="fa fa-edit"></i></a>
                                                <form action="{{ route('dynamic-trading.destroy', $trade->id) }}"
                                                    method="POST" style="display:inline;">
                                                    @csrf
                                                    <input type="hidden" name="market" value="SPOT">
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm"><i
                                                            class="fa fa-trash"></i></button>
                                                </form>
                                            </td>
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
