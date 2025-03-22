@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Order Book Snapshots Overview</h2>


        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Sr. </th>
                    <th>Symbol</th>
                    <th>Total Long</th>
                    <th>Total Short</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

                @foreach ($snapshots as $index => $snapshot)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $snapshot->symbol }}</td>
                        <td>{{ $snapshot->long_count }}</td>
                        <td>{{ $snapshot->short_count }}</td>
                        <td>{{ $snapshot->latest_snapshot_time }}</td>

                        <td>
                            <a href="{{ route('order-book.index', ['symbol' => $snapshot->symbol]) }}"
                                class="btn btn-info btn-sm">View</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            
        </table>

    </div>
@endsection
