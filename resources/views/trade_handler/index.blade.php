@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Trade Handler Settings</h2>

        <a href="{{ route('trade-handler.create') }}" class="btn btn-primary mb-2">Add New</a>
        <a href="{{ route('trade-handler.delete.all') }}" class="btn btn-danger mb-2">Delete All</a>
        <a href="{{ route('user.toggle-auto-update') }}"
            class="btn mb-2 float-right {{ \DB::table('user_meta')->where('user_id', auth()->user()->id)->where('meta_key', 'is_auto_update_enable_spot')->first()->meta_value == 'on'? 'btn-danger': 'btn-success' }}"
            >{{ \DB::table('user_meta')->where('user_id', auth()->user()->id)->where('meta_key', 'is_auto_update_enable_spot')->first()->meta_value == 'on'? 'Disable Auto-Update': 'Enable Auto-Update' }}</a>
        <table class="table">
            <thead>
                <tr>
                    <th>Market</th>
                    <th>Symbol</th>
                    <th>Interval</th>
                    <th>Buy Price</th>
                    <th>Target Profit</th>
                    <th>RSI Threshold</th>
                    <th>OBV Limit</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tradeHandlers as $handler)
                    <tr>
                        <td>{{ $handler->market }}</td>
                        <td>{{ $handler->symbol }}</td>
                        <td>{{ $handler->interval }}</td>
                        <td>{{ $handler->buyPrice }}</td>
                        <td>{{ $handler->targetProfit }}</td>
                        <td>{{ round($handler->rsiThreshold, 2) }}</td>
                        <td>{{ round($handler->obvLimit, 2) }}</td>
                        <td>
                            <span class="badge {{ $handler->isActive ? 'badge-success' : 'badge-danger' }}">
                                {{ $handler->isActive ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="row">
                                <div class="col-md-6">
                                    <a href="{{ route('trade-handler.edit', $handler->id) }}"
                                        class="btn btn-primary btn-sm">Edit</a>
                                </div>
                                <div class="col-md-6">
                                    <form action="{{ route('trade-handler.destroy', $handler->id) }}" method="POST"
                                        style="display: inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </div>
                            </div>


                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
