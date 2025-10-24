@extends('layouts.app')

@section('content')
@php
    // Cores para tipos (badges em “cubo”)
    $tipoBg = [
        'PONTO_FACULTATIVO' => 'bg-blue-600',
        'FERIADO_MUNICIPAL' => 'bg-emerald-600',
        'FERIADO_ESTADUAL'  => 'bg-amber-600',
        'FERIADO_FEDERAL'   => 'bg-red-600', // valor técnico persiste; rótulo mostrado é "FERIADO NACIONAL"
    ];

    // Cor de fim de semana quando NÃO há evento
    $satText = 'text-orange-600';
    $sunText = 'text-red-600';

    $badgeClassFor = function($events) use ($tipoBg) {
        if (!$events || count($events) === 0) return null;
        $t = $events[0]->tipo ?? null;
        return $t && isset($tipoBg[$t]) ? $tipoBg[$t] : 'bg-gray-700';
    };

    $routeAno  = fn($y)    => route('calendario.index', ['ano'=>$y]);
    $routeMes  = fn($y,$m) => route('calendario.index', ['ano'=>$y,'mes'=>$m]);

    // Hoje (hora do servidor)
    $todayYmd = now()->toDateString();
@endphp

<div class="max-w-7xl mx-auto px-4 py-6 uppercase">
    {{-- Cabeçalho / seletor de ano --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            @if(!empty($mes))
                <a href="{{ $routeAno($ano) }}"
                   class="inline-flex items-center gap-2 rounded-md bg-cyan-700 px-4 py-2 text-white hover:bg-cyan-800">
                    {{-- ícone seta esquerda --}}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    VOLTAR AO ANO
                </a>
            @endif

            <h1 class="text-2xl md:text-3xl font-semibold">
                CALENDÁRIO — @if(!empty($mes)) {{ $mesLabel }} / @endif {{ $ano }}
            </h1>
        </div>

        <form method="GET" action="{{ route('calendario.index') }}" class="flex items-center gap-3">
            <label for="ano" class="text-sm text-gray-200">ANO:</label>

            {{-- seletor com apenas a seta nativa do navegador --}}
            <select id="ano" name="ano"
                    class="w-36 md:w-44 rounded border-gray-300 bg-white text-gray-900
                           px-3 py-2 text-2xl md:text-3xl font-bold leading-tight">
                @for($y=2000; $y<=now()->year+5; $y++)
                    <option value="{{ $y }}" @selected($y==$ano)>{{ $y }}</option>
                @endfor
            </select>

            @if(!empty($mes))
                <input type="hidden" name="mes" value="{{ $mes }}">
            @endif

            <button class="rounded bg-white/10 px-4 py-2 text-sm md:text-base text-white hover:bg-white/20">
                IR
            </button>
        </form>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="mt-4 rounded-lg bg-emerald-600/15 text-emerald-800 px-4 py-2 border border-emerald-600/30">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mt-4 rounded-lg bg-red-600/15 text-red-800 px-4 py-2 border border-red-600/30">
            {{ session('error') }}
        </div>
    @endif

    {{-- LEGENDAS (linha única, com scroll horizontal se necessário) --}}
    <div class="mt-6 overflow-x-auto">
        <div class="flex items-center gap-4 text-sm whitespace-nowrap">
            <span class="inline-flex items-center gap-2">
                <span class="inline-flex w-5 h-5 rounded-md bg-red-600"></span><span>FERIADO NACIONAL</span>
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="inline-flex w-5 h-5 rounded-md bg-amber-600"></span><span>FERIADO ESTADUAL</span>
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="inline-flex w-5 h-5 rounded-md bg-emerald-600"></span><span>FERIADO MUNICIPAL</span>
            </span>
            <span class="inline-flex items-center gap-2">
                <span class="inline-flex w-5 h-5 rounded-md bg-blue-600"></span><span>PONTO FACULTATIVO</span>
            </span>

            {{-- Nota opcional sobre dias úteis (sem sábado/domingo) --}}
            <span class="text-white/80 ml-4">DIAS ÚTEIS (PRETO)</span>
        </div>
    </div>

    {{-- FORM DE INSERÇÃO RÁPIDA --}}
    <div class="mt-4 rounded-xl bg-white text-gray-900 shadow-sm ring-1 ring-black/5 p-4">
        <form method="POST" action="{{ route('calendario.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600">DATA</label>
                <input type="date" name="data" class="mt-1 w-full rounded border-gray-300"
                       value="{{ !empty($mes) ? \Carbon\Carbon::create($ano,$mes,1)->toDateString() : \Carbon\Carbon::create($ano,1,1)->toDateString() }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">TIPO</label>
                <select name="tipo" class="mt-1 w-full rounded border-gray-300">
                    <option value="FERIADO_FEDERAL">FERIADO NACIONAL</option>
                    <option value="FERIADO_ESTADUAL">FERIADO ESTADUAL</option>
                    <option value="FERIADO_MUNICIPAL">FERIADO MUNICIPAL</option>
                    <option value="PONTO_FACULTATIVO">PONTO FACULTATIVO</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600">TÍTULO (OPCIONAL)</label>
                <input type="text" name="titulo" class="mt-1 w-full rounded border-gray-300" placeholder="EX.: CORPUS CHRISTI, CARNAVAL, ETC.">
            </div>
            <div class="md:col-span-4">
                <button class="rounded bg-gray-900 text-white px-4 py-2 hover:opacity-95">ADICIONAR EVENTO</button>
            </div>
        </form>
    </div>

    {{-- ===== VISÃO MENSAL ===== --}}
    @if(!empty($mes))
        <div class="mt-6 rounded-2xl bg-white text-gray-900 shadow-sm ring-1 ring-black/5 p-4">
            <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold text-gray-500">
                @foreach($diasCab as $cab)
                    <div class="py-1">{{ $cab }}</div>
                @endforeach
            </div>

            <div class="mt-1 grid grid-cols-7 gap-2">
                @foreach($matrix as $week)
                    @foreach($week as $day)
                        @php
                            $isCurrentMonth = $day['inMonth'];
                            $hasEvents      = !empty($day['events']);
                            $badge          = $badgeClassFor($day['events']);
                            $textClass      = $isCurrentMonth ? 'text-gray-900' : 'text-gray-400';
                            if(!$hasEvents && $isCurrentMonth){
                                if($day['isSun'])   $textClass = $sunText;
                                if($day['isSat'])   $textClass = $satText;
                            }
                            // Hoje?
                            $cellYmd = null;
                            if ($isCurrentMonth && is_numeric($day['label'])) {
                                $cellYmd = \Carbon\Carbon::create($ano, $mes, (int)$day['label'])->toDateString();
                            }
                            $isToday = $cellYmd === $todayYmd;
                        @endphp
                        <div class="aspect-square rounded-lg {{ $isCurrentMonth ? 'bg-white' : 'bg-gray-50' }} border border-gray-200 flex items-center justify-center">
                            @if($hasEvents)
                                <span class="inline-flex w-10 h-10 items-center justify-center rounded-md text-white font-semibold text-lg {{ $badge }} ring-2 {{ $isToday ? 'ring-cyan-600' : 'ring-white' }} shadow">
                                    {{ $day['label'] }}
                                </span>
                            @else
                                @if($isToday)
                                    <span class="inline-flex w-10 h-10 items-center justify-center rounded-full ring-2 ring-cyan-600 text-lg {{ $textClass }}">
                                        {{ $day['label'] }}
                                    </span>
                                @else
                                    <span class="text-lg {{ $textClass }}">{{ $day['label'] }}</span>
                                @endif
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Lista de eventos do mês --}}
        <div class="mt-6 rounded-2xl bg-white text-gray-900 shadow-sm ring-1 ring-black/5 p-4">
            <div class="text-sm font-semibold mb-3">EVENTOS EM {{ $mesLabel }}/{{ $ano }}</div>

            @if(empty($eventosMes))
                <div class="text-sm text-gray-600">NENHUM EVENTO CADASTRADO NESTE MÊS.</div>
            @else
                <ul class="space-y-2">
                    @foreach($eventosMes as $ev)
                        @php
                            $d  = \Carbon\Carbon::parse($ev->data);
                            $bg = $tipoBg[$ev->tipo] ?? 'bg-gray-700';
                            $tipoLabel = match($ev->tipo) {
                                'FERIADO_FEDERAL'   => 'FERIADO NACIONAL',
                                'FERIADO_ESTADUAL'  => 'FERIADO ESTADUAL',
                                'FERIADO_MUNICIPAL' => 'FERIADO MUNICIPAL',
                                'PONTO_FACULTATIVO' => 'PONTO FACULTATIVO',
                                default => strtoupper($ev->tipo)
                            };
                        @endphp
                        <li class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex w-7 h-7 rounded-md {{ $bg }} ring-2 ring-white shadow"></span>
                                <div class="text-sm">
                                    <div class="font-medium">
                                        {{ $d->format('d/m/Y') }} — {{ $tipoLabel }}
                                    </div>
                                    @if($ev->titulo)
                                        <div class="text-gray-600">{{ strtoupper($ev->titulo) }}</div>
                                    @endif
                                </div>
                            </div>

                            <form method="POST" action="{{ route('calendario.destroy', $ev->id) }}"
                                  onsubmit="return confirm('REMOVER ESTE EVENTO?');">
                                @csrf @method('DELETE')
                                <button class="text-sm rounded px-3 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 border border-red-200">
                                    REMOVER
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Botão voltar (reforço visual ao final da página) --}}
            <div class="mt-6">
                <a href="{{ $routeAno($ano) }}"
                   class="inline-flex items-center gap-2 rounded-md bg-cyan-700 px-4 py-2 text-white hover:bg-cyan-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    VOLTAR AO ANO
                </a>
            </div>
        </div>
    @else
    {{-- ===== VISÃO ANUAL (3 colunas × 4 linhas) ===== --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($meses as $m)
                <a href="{{ $routeMes($ano, $m['num']) }}"
                   class="group rounded-2xl bg-white text-gray-900 shadow-sm ring-1 ring-black/5 p-4 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold">{{ $m['label'] }}</div>
                        @if($m['temEv'])
                            <span class="text-xs rounded bg-blue-600 text-white px-2 py-0.5">COM EVENTOS</span>
                        @endif
                    </div>

                    <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] md:text-[12px] text-gray-500">
                        @foreach($diasCab as $cab)
                            <div>{{ $cab }}</div>
                        @endforeach
                    </div>

                    <div class="mt-1 grid grid-cols-7 gap-1">
                        @foreach($m['matrix'] as $week)
                            @foreach($week as $day)
                                @php
                                    $isCurrentMonth = $day['inMonth'];
                                    $hasEvents      = !empty($day['events']);
                                    $badge          = $badgeClassFor($day['events']);
                                    $textClass      = $isCurrentMonth ? 'text-gray-900' : 'text-gray-300';
                                    if(!$hasEvents && $isCurrentMonth){
                                        if($day['isSun']) $textClass = $sunText;
                                        if($day['isSat']) $textClass = $satText;
                                    }
                                    // Hoje? (visão anual)
                                    $cellYmd = null;
                                    if ($isCurrentMonth && is_numeric($day['label'])) {
                                        $cellYmd = \Carbon\Carbon::create($ano, $m['num'], (int)$day['label'])->toDateString();
                                    }
                                    $isToday = $cellYmd === $todayYmd;
                                @endphp
                                <div class="aspect-square rounded-lg {{ $isCurrentMonth ? 'bg-white' : 'bg-gray-50' }} border border-gray-100 flex items-center justify-center">
                                    @if($hasEvents)
                                        <span class="inline-flex w-7 h-7 items-center justify-center rounded-[6px] text-[15px] text-white font-semibold {{ $badge }} ring-1 {{ $isToday ? 'ring-cyan-600' : 'ring-white' }} shadow">
                                            {{ $day['label'] }}
                                        </span>
                                    @else
                                        @if($isToday)
                                            <span class="inline-flex w-7 h-7 items-center justify-center rounded-full ring-2 ring-cyan-600 text-[15px] {{ $textClass }}">
                                                {{ $day['label'] }}
                                            </span>
                                        @else
                                            <span class="text-[15px] {{ $textClass }}">{{ $day['label'] }}</span>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
