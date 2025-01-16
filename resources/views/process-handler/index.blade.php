@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Trade Handler Settings</h2>

        <table class="table">
            <thead>
                <tr>
                    <th>Program Name</th>
                    <th>Status</th>
                    <th>Uptime</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($processes as $process)
                    <tr>
                        <td>{{ $process->program_name }}</td>
                        <td>{{ $process->status }}</td>
                        <td>{{ $process->uptime }}</td>
                        <td>{{ $process->updated_at }}</td>
                        <td><a href="{{route('process-handler.restart',$process->program_name)}}" class="btn btn-primary">Restart</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
