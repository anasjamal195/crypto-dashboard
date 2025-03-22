@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Order Book Snapshots</h2>
        <!-- Filter Form -->
        <form method="GET" action="{{ route('order-book.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-3">
                    <input type="text" name="symbol" class="form-control" placeholder="Symbol"
                        value="{{ request('symbol') }}">
                </div>
                <div class="col-md-3">
                    <select name="signal" class="form-control select2">
                        <option value="">Select Signal</option>
                        <option value="LONG" {{ request('signal') == 'LONG' ? 'selected' : '' }}>LONG</option>
                        <option value="SHORT" {{ request('signal') == 'SHORT' ? 'selected' : '' }}>SHORT</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="date_from" class="form-control flatpickr-input" placeholder="From"
                        value="{{ request('date_from') }}">
                </div>
                <div class="col-md-3">
                    <input type="date" name="date_to" class="form-control flatpickr-input" placeholder="To"
                        value="{{ request('date_to') }}">
                </div>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('order-book.index') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Sr. </th>
                    <th>Symbol</th>
                    <th>Snapshot Time</th>
                    <th>Bid Volume</th>
                    <th>Ask Volume</th>
                    <th>Signal</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $startIndex = request()->get('page') ?? 1;

                    $startIndex = ($startIndex - 1) * 100;
                @endphp
                @foreach ($snapshots as $index => $snapshot)
                    <tr>
                        <td>{{ $index + 1 + $startIndex }}</td>
                        <td>{{ $snapshot->symbol }}</td>
                        <td>{{ $snapshot->snapshot_time }}</td>
                        <td>{{ number_format($snapshot->bid_volume, 2) }}</td>
                        <td>{{ number_format($snapshot->ask_volume, 2) }}</td>
                        <td>
                            @if ($snapshot->signal == 'LONG')
                                <span class="badge badge-success">{{ $snapshot->signal }}</span>
                            @elseif($snapshot->signal == 'SHORT')
                                <span class="badge badge-danger">{{ $snapshot->signal }}</span>
                            @else
                                <span class="badge badge-primary">{{ $snapshot->signal }}</span>
                            @endif
                        </td>

                        <td>
                            <a href="{{ route('order-book.show', $snapshot->id) }}" class="btn btn-info btn-sm">View</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="text-center">
                        @if ($snapshots->onFirstPage())
                            <span class="btn btn-secondary disabled">Previous</span>
                        @else
                            <a href="{{ $snapshots->previousPageUrl() }}" class="btn btn-primary">Previous</a>
                        @endif

                        @if ($snapshots->hasMorePages())
                            <a href="{{ $snapshots->nextPageUrl() }}" class="btn btn-primary">Next</a>
                        @else
                            <span class="btn btn-secondary disabled">Next</span>
                        @endif

                        <div class="mt-2">
                            Page {{ $snapshots->currentPage() }} of {{ $snapshots->lastPage() }} - Showing
                            {{ $snapshots->count() }} entries out of {{ $snapshots->total() }} entries.
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>

    </div>
@endsection
