@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Trade Handler Settings</h2>

        <div class="row mb-3">
            <div class="col-md-6">
                <a href="{{ route('process-handler.action', 'START') }}" class="btn btn-primary btn-sm">Restart All</a>
            </div>
        </div>
        <div class="col-md-6">
            <a href="{{ route('process-handler.action', 'STOP') }}" class="btn btn-danger btn-sm">Stop All</a>
        </div>

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
                        <td>{{ ucwords(str_replace('_', ' ', $process['processName'])) }}</td>
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
