@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Trade Handler Settings</h2>

        <a href="{{ route('process-handler.start-multithread') }}" class="btn btn-primary btn-sm m-2">Start Multithread</a>
        {{-- <a href="{{ route('process-handler.action', 'START') }}" class="btn btn-primary btn-sm m-2">Restart All</a> --}}
        <a href="{{ route('process-handler.action', 'RESTART') }}" class="btn btn-primary btn-sm m-2">Restart Current</a>
        <a href="{{ route('process-handler.action', 'STOP') }}" class="btn btn-danger btn-sm m-2">Stop All</a>
        <a href="{{ route('process-handler.action', 'CLEANUP') }}" class="btn btn-secondary btn-sm m-2">Cleanup</a>

        <a href="{{ route('user.toggle-position', 'LONG') }}"
            class="btn  btn-sm m-2 {{ \App\CommonHelpers::getMetaValue(auth()->user()->id, 'enable_long_multithread', 0) == 1 ? 'btn-success' : 'btn-danger' }}">{{ \App\CommonHelpers::getMetaValue(auth()->user()->id, 'enable_long_multithread', 0) == 1 ? 'LONG is Enabled' : 'Long is Disabled' }}</a>

        <a href="{{ route('user.toggle-position', 'SHORT') }}"
            class="btn  btn-sm m-2 {{ \App\CommonHelpers::getMetaValue(auth()->user()->id, 'enable_short_multithread', 0) == 1 ? 'btn-success' : 'btn-danger' }}">{{ \App\CommonHelpers::getMetaValue(auth()->user()->id, 'enable_short_multithread', 0) == 1 ? 'SHORT is Enabled' : 'Short is Disabled' }}</a>

        <a href="{{ route('user.toggle-market') }}"
            class="btn  btn-sm m-2 {{ \App\CommonHelpers::getMetaValue(auth()->user()->id, 'enable_spot', 0) == 1 ? 'btn-info' : 'btn-primary' }}">{{ \App\CommonHelpers::getMetaValue(auth()->user()->id, 'enable_spot', 0) == 1 ? 'Spot Mode' : 'Future Mode' }}</a>


        <table class="table">
            <thead>
                <tr>
                    <th>Program Name</th>
                    <th>Status</th>
                    <th>Uptime</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

                @foreach ($processes as $process)
                    <tr>
                        <td>{{ ucwords(str_replace('worker', '', str_replace('laravel', '', str_replace('_', ' ', $process['processName'])))) }}
                        </td>
                        <td>
                            <span class="badge {{ $process['status'] == 'RUNNING' ? 'badge-success' : 'badge-danger' }}">
                                {{ $process['status'] }}
                            </span>
                        </td>
                        <td>{{ $process['uptime'] }}</td>
                        <td>
                            @if ($process['status'] == 'RUNNING')
                                <a href="{{ route('process-handler.stop', $process['processName']) }}"
                                    class="btn btn-danger btn-sm">Stop</a>
                            @else
                                <a href="{{ route('process-handler.restart', $process['processName']) }}"
                                    class="btn btn-primary btn-sm">Restart</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
