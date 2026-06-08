@extends('layouts.app')

@section('title', __('Visitas') . ' · FirmaDoc')

@section('content')
    @php
        $typeLabels = [
            'home' => __('Inicio'),
            'quick' => __('Firma rápida'),
            'sign' => __('Firmar'),
            'login' => __('Entrar'),
            'invite' => __('Invitación'),
            'pro_request' => __('Solicitud Pro'),
            'legal' => __('Legal'),
            'app' => __('Panel'),
        ];
        $flag = fn ($c) => $c ? collect(str_split(strtoupper($c)))->map(fn ($l) => mb_chr(127397 + ord($l)))->join('') : '';
        $maxDay = max(1, count($byDay) ? max($byDay) : 1);
        $maxHour = max(1, max($byHour));
        $maxType = max(1, count($byType) ? max($byType) : 1);
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="eyebrow">{{ __('Administración') }}</p>
            <h1 class="mt-1 text-2xl text-ink">{{ __('Visitas') }}</h1>
        </div>
        <div class="flex items-center gap-1 rounded-full border border-line bg-surface p-1 text-xs">
            @foreach ([7 => '7d', 30 => '30d', 90 => '90d', 365 => '1a'] as $d => $lbl)
                <a href="{{ route('admin.visits', ['days' => $d]) }}"
                   class="rounded-full px-3 py-1 {{ $days === $d ? 'bg-accent text-paper font-semibold' : 'text-muted hover:text-ink' }}">{{ $lbl }}</a>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="card p-5">
            <p class="eyebrow">{{ __('Visitas totales') }}</p>
            <p class="mt-1 text-3xl font-semibold text-ink" style="font-family:var(--font-display)">{{ number_format($total, 0, ',', '.') }}</p>
        </div>
        <div class="card p-5">
            <p class="eyebrow">{{ __('Visitantes únicos') }}</p>
            <p class="mt-1 text-3xl font-semibold text-ink" style="font-family:var(--font-display)">{{ number_format($unique, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Visitas por día --}}
    <div class="card mt-4 p-5">
        <h2 class="text-sm font-semibold text-ink">{{ __('Visitas por día') }}</h2>
        <div class="mt-4 flex h-32 items-end gap-px">
            @foreach ($byDay as $date => $count)
                <div class="group relative flex-1" title="{{ $date }}: {{ $count }}">
                    <div class="w-full rounded-t bg-accent/80 transition-colors group-hover:bg-accent" style="height:{{ max(2, round($count / $maxDay * 120)) }}px"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-[11px] text-faint">
            <span>{{ \Illuminate\Support\Carbon::parse(array_key_first($byDay))->format('d/m') }}</span>
            <span>{{ \Illuminate\Support\Carbon::parse(array_key_last($byDay))->format('d/m') }}</span>
        </div>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        {{-- Por tipo de página --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-ink">{{ __('Por sección') }}</h2>
            <div class="mt-3 space-y-2">
                @forelse ($byType as $type => $count)
                    <div>
                        <div class="flex justify-between text-xs">
                            <span class="text-muted">{{ $typeLabels[$type] ?? $type }}</span>
                            <span class="font-semibold text-ink">{{ $count }}</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full" style="background:rgba(28,25,19,0.06)">
                            <div class="h-full rounded-full bg-accent" style="width:{{ round($count / $maxType * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-faint">{{ __('Sin datos todavía.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Top países --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-ink">{{ __('Top países') }}</h2>
            <div class="mt-3 space-y-1.5">
                @forelse ($topCountries as $code => $count)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted">{{ $flag($code) }} {{ $code }}</span>
                        <span class="font-semibold text-ink">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-faint">{{ __('Sin datos de país (GeoIP).') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Por hora --}}
    <div class="card mt-4 p-5">
        <h2 class="text-sm font-semibold text-ink">{{ __('Por hora del día') }}</h2>
        <div class="mt-4 flex h-24 items-end gap-1">
            @foreach ($byHour as $h => $count)
                <div class="flex-1" title="{{ $h }}h: {{ $count }}">
                    <div class="w-full rounded-t bg-accent/70" style="height:{{ max(2, round($count / $maxHour * 80)) }}px"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-[11px] text-faint"><span>0h</span><span>12h</span><span>23h</span></div>
    </div>
@endsection
