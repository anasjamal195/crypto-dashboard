@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Workers and Symbols</h2>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Queues</th>

                    @foreach ($workers as $worker)
                        <th>{{ $worker->worker_id }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $workerSymbols = DB::table('worker_symbols')->get()->groupBy('worker_id');
                    $maxSymbols = $workerSymbols->max(fn($symbols) => $symbols->count());
                    $workerIds = DB::table('workers')->pluck('worker_id');
                @endphp
                
                @for ($i = 0; $i < $maxSymbols; $i++)
                    <tr>
                        @foreach ($workerIds as $workerId)
                            <td>
                                @if (isset($workerSymbols[$workerId][$i]))
                                    {{ $workerSymbols[$workerId][$i]->symbol }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>
@endsection
