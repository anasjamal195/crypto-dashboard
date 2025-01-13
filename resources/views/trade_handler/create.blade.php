@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Create Trade Handler</h2>
    <form action="{{ route('trade-handler.store') }}" method="POST">
        @csrf
        @include('trade_handler.partials.form')
        <input type="hidden" name="tradeAccount" value="{{auth()->user()->id}}">
        
        <button type="submit" class="btn btn-success">Create</button>
    </form>
</div>
@endsection
