@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-primary">
                    <h4 class="card-title ">Market Trends Report</h4>
                    <p class="card-category">Here is a list of market trends for various symbols</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="text-primary">
                                <tr>
                                    <th>Symbol</th>
                                    <th>Market</th>
                                    <th>Interval</th>
                                    <th>Signal</th>
                                    <th>Trade Type</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trends as $trend)
                                <tr>
                                    <td>{{ $trend->symbol }}</td>
                                    <td>{{ $trend->market }}</td>
                                    <td>{{ $trend->interval }}</td>
                                    <td>
                                        <span class="badge {{ $trend->signal === 'positive' ? 'badge-success' : 'badge-danger' }}">
                                            {{ $trend->signal === 'positive' ? 'Green' : 'Red' }}
                                        </span>
                                    </td>
                                    <td>{{ $trend->tradeType }}</td>
                                    <td>{{ \Carbon\Carbon::parse($trend->updated_at)->timezone('Asia/Karachi')->format('d M Y, h:i A') }}</td>
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
