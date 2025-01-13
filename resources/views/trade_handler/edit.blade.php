@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Edit Trade Handler</h2>
        <form action="{{ route('trade-handler.update', $tradeHandler->id) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="tradeAccount" value="{{ auth()->user()->id }}">

            @include('trade_handler.partials.form')
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
@endsection
