@extends('layouts.app')

@section('title', $title . ' · FirmaDoc')

@section('content')
    <div class="card mx-auto mt-8 max-w-md p-10 text-center">
        <div class="brand-mark mx-auto mb-4 size-12">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                <path d="M5 19l2.5-.6L18 7.9a2 2 0 0 0-2.8-2.8L4.6 15.5 4 18z"/><path d="M14.5 6.5l3 3"/>
            </svg>
        </div>
        <h1 class="text-xl text-ink">{{ $title }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-muted">{{ $message }}</p>
    </div>
@endsection
