@extends('layouts.app')


@if (auth()->user()->role === 'superadmin')
    @include('superadmin-dashboard', compact('pageSlug'))
@elseif(auth()->user()->role === 'trader')
    @include('trader-dashboard', compact('pageSlug'))
@elseif(auth()->user()->role === 'analyst')
    @include('analyst-dashboard', compact('pageSlug'))
@endif
