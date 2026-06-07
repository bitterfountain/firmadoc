<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FirmaDoc')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Hanken+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full antialiased">
    <header class="border-b border-line bg-surface/70 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <span class="brand-mark size-9">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                        <path d="M5 19l2.5-.6L18 7.9a2 2 0 0 0-2.8-2.8L4.6 15.5 4 18z"/>
                        <path d="M14.5 6.5l3 3"/>
                    </svg>
                </span>
                <span class="text-lg font-semibold tracking-tight text-ink" style="font-family:var(--font-display)">FirmaDoc</span>
            </a>

            @auth
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-muted sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost px-4 py-1.5">Salir</button>
                    </form>
                </div>
            @else
                <span class="eyebrow hidden sm:block">Firma electrónica</span>
            @endauth
        </div>
    </header>

    @if (session('status'))
        <div class="mx-auto mt-5 max-w-5xl px-5">
            <div class="rounded-xl border border-accent/20 bg-accent-soft px-4 py-3 text-sm text-accent">
                {{ session('status') }}
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mx-auto mt-5 max-w-5xl px-5">
            <div class="rounded-xl px-4 py-3 text-sm" style="background:var(--color-danger-soft);color:var(--color-danger)">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <main class="mx-auto max-w-5xl px-5 py-8">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-5xl px-5 pb-10 pt-4">
        <p class="text-xs text-faint">FirmaDoc · Firma electrónica de documentos</p>
    </footer>

    @stack('scripts')
</body>
</html>
