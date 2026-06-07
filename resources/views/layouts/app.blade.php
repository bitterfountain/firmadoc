<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FirmaDoc')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="{{ route('documents.index') }}" class="flex items-center gap-2 font-semibold text-slate-900">
                <span class="grid size-8 place-items-center rounded-lg bg-indigo-600 text-white">✎</span>
                FirmaDoc
            </a>
            <span class="text-sm text-slate-400">Firma de documentos</span>
        </div>
    </header>

    @if (session('status'))
        <div class="mx-auto mt-4 max-w-5xl px-4">
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mx-auto mt-4 max-w-5xl px-4">
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <main class="mx-auto max-w-5xl px-4 py-6">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
