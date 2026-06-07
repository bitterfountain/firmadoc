@extends('layouts.app')

@section('title', $title . ' · FirmaDoc')

@section('content')
    <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 text-center">
        <div class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-indigo-100 text-2xl">✎</div>
        <h1 class="text-lg font-semibold text-slate-900">{{ $title }}</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $message }}</p>
    </div>
@endsection
