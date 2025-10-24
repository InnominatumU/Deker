{{-- resources/views/layouts/navigation.blade.php --}}
@php
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Facades\Route;

    $linkBase = 'hover:underline px-1';
    $active   = 'font-semibold underline';

    // Usuário atual
    $user  = auth()->user();
    $email = strtolower($user->email ?? '');

    // ======= VISIBILIDADE POR GATE =======
    $canSistema    = Gate::check('menu.sistema');
    $canIndividuos = Gate::check('menu.individuos');
    $canRelatorios = Gate::check('menu.relatorios');
    $canAtend      = Gate::check('menu.atendimentos');
    $canCiclo      = Gate::check('menu.ciclo');
    $canSobre      = Gate::check('menu.sobre');

    // Gates específicos (herdam do canSistema se não existirem)
    $canFrota      = Gate::check('menu.frota')      || $canSistema;
    $canServidores = Gate::check('menu.servidores') || $canSistema;

    // ======= FALLBACK DE DESENVOLVIMENTO =======
    $envAllows        = app()->environment(['local','development']) || config('app.debug');
    $allowedDevCsv    = (string) env('DEKER_DEV_MENU_EMAILS', 'gestor@deker.local,admin@deker.local');
    $allowedDevEmails = array_map(fn($s) => strtolower(trim($s)), array_filter(explode(',', $allowedDevCsv)));
    $canByDev         = $envAllows && $email && in_array($email, $allowedDevEmails, true);

    if ($canByDev) {
        $canSistema = $canIndividuos = $canRelatorios = $canAtend = $canCiclo = $canSobre = true;
        $canFrota = $canServidores = true;
    }

    // ======= Disponibilidade de rotas (evita exceções) =======
    // Frota
    $hasFrotaVeicCreate = Route::has('frota.veiculos.create');
    $hasFrotaVeicSearch = Route::has('frota.veiculos.search');
    $hasFrotaOperacoes  = Route::has('frota.operacoes.index');
    $hasFrotaUso        = Route::has('frota.veiculos.uso');
    $hasFrotaAbast      = Route::has('frota.veiculos.abastecimentos');
    $hasFrotaDesloc     = Route::has('frota.veiculos.deslocamentos');
    $hasFrotaDocs       = Route::has('frota.veiculos.documentos');
    $hasFrotaRel        = Route::has('frota.veiculos.relatorios');

    // Servidores
    $hasServCreate      = Route::has('servidores.create');
    $hasServSearch      = Route::has('servidores.search');
    $hasServIndex       = Route::has('servidores.index');
    $hasServFreq        = Route::has('servidores.frequencia.show');
    $hasServRel         = Route::has('servidores.relatorios.index');
    $hasServCarga       = Route::has('servidores.cargahoraria.index');

    // Busca dedicada de frequência
    $hasServFreqSearch  = Route::has('servidores.frequencia.search');

    // Calendário
    $hasCalendario      = Route::has('calendario.index');
@endphp

<nav class="fixed top-0 inset-x-0 z-50 bg-gray-900 text-white shadow"
     role="navigation" aria-label="Menu principal DEKER"
     x-data="{ giOpen:false, sysOpen:false, cicloOpen:false, atendOpen:false, relOpen:false }">
    <div class="max-w-7xl mx-auto px-4">
        <div class="h-16 flex items-center justify-between">
            {{-- Branding à esquerda (home) --}}
            <a href="{{ route('inicio') }}" class="flex flex-col leading-tight font-semibold tracking-wide" aria-label="Página inicial do DEKER">
                <span class="text-base">DEKER - O GUARDIÃO</span>
                <span class="text-xs text-gray-300">SISTEMA DE GESTÃO DE CUSTODIADOS</span>
            </a>

            {{-- Menu (direita -> esquerda) --}}
            <ul class="flex flex-row-reverse items-center gap-6">
                {{-- Sair --}}
                <li>
                    <a href="#"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                       class="{{ $linkBase }}"
                       aria-label="Sair do sistema">
                        Sair
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                </li>

                {{-- Sobre --}}
                @if($canSobre)
                <li>
                    <a href="{{ route('sobre.index') }}"
                       class="{{ $linkBase }} {{ request()->routeIs('sobre.*') ? $active : '' }}">
                        Sobre
                    </a>
                </li>
                @endif

                {{-- RELATÓRIOS --}}
                @if($canRelatorios)
                <li class="relative"
                    @mouseenter="relOpen = true; sysOpen=false; giOpen=false; cicloOpen=false; atendOpen=false"
                    @mouseleave="relOpen = false">
                    <button type="button"
                            @click="relOpen = !relOpen; sysOpen=false; giOpen=false; cicloOpen=false; atendOpen=false"
                            class="{{ $linkBase }} flex items-center gap-1 {{ request()->routeIs('relatorios.*') ? $active : '' }}">
                        Relatórios
                        <span class="inline-block align-middle">▾</span>
                    </button>
                    <div x-show="relOpen" x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-80 bg-white text-gray-900 rounded-2xl shadow-lg ring-1 ring-black/5 overflow-visible z-50"
                         @click.outside="relOpen = false">
                        <div class="py-2 text-sm">
                            <a href="{{ route('relatorios.cadeia.index') }}" class="block px-4 py-2 hover:bg-gray-100">Cadeia de Custódia</a>
                            <a href="{{ route('relatorios.atendimentos.internos.index') }}" class="block px-4 py-2 hover:bg-gray-100">Atendimentos Internos</a>
                            <a href="{{ route('relatorios.atendimentos.externos.index') }}" class="block px-4 py-2 hover:bg-gray-100">Atendimentos Externos</a>
                        </div>
                    </div>
                </li>
                @endif

                {{-- ATENDIMENTOS TÉCNICOS --}}
                @if($canAtend)
                <li class="relative"
                    @mouseenter="atendOpen = true; sysOpen=false; giOpen=false; cicloOpen=false; relOpen=false"
                    @mouseleave="atendOpen = false">
                    <button type="button"
                            @click="atendOpen = !atendOpen; sysOpen=false; giOpen=false; cicloOpen=false; relOpen=false"
                            class="{{ $linkBase }} flex items-center gap-1 {{ request()->routeIs('atendimentos.*') ? $active : '' }}">
                        Atendimentos Técnicos
                        <span class="inline-block align-middle">▾</span>
                    </button>
                    <div x-show="atendOpen" x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-80 bg-white text-gray-900 rounded-2xl shadow-lg ring-1 ring-black/5 overflow-visible z-50"
                         @click.outside="atendOpen = false">
                        <div class="py-2 text-sm">
                            <a href="{{ route('atendimentos.interno.index') }}" class="block px-4 py-2 hover:bg-gray-100">Interno</a>
                            <a href="{{ route('atendimentos.externo.index') }}" class="block px-4 py-2 hover:bg-gray-100">Externo</a>
                        </div>
                    </div>
                </li>
                @endif

                {{-- CICLO DE VÍNCULOS --}}
                @if($canCiclo)
                <li class="relative"
                    @mouseenter="cicloOpen = true; sysOpen=false; giOpen=false; atendOpen=false; relOpen=false"
                    @mouseleave="cicloOpen = false">
                    <button type="button"
                            @click="cicloOpen = !cicloOpen; sysOpen=false; giOpen=false; atendOpen=false; relOpen=false"
                            class="{{ $linkBase }} flex items-center gap-1 {{ request()->routeIs('ciclo.*') ? $active : '' }}">
                        Ciclo de Vínculos
                        <span class="inline-block align-middle">▾</span>
                    </button>
                    <div x-show="cicloOpen" x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-96 bg-white text-gray-900 rounded-2xl shadow-lg ring-1 ring-black/5 overflow-visible z-50"
                         @click.outside="cicloOpen = false">
                        <div class="py-2 text-sm">
                            <a href="{{ route('ciclo.admissao.index') }}" class="block px-4 py-2 hover:bg-gray-100">Admissão</a>
                            <a href="{{ route('ciclo.objetos.index') }}" class="block px-4 py-2 hover:bg-gray-100">Objetos</a>
                            <a href="{{ route('ciclo.movimentacao.interna.index') }}" class="block px-4 py-2 hover:bg-gray-100">Movimentação Interna</a>
                            <a href="{{ route('ciclo.movimentacao.externa.index') }}" class="block px-4 py-2 hover:bg-gray-100">Movimentação Externa</a>
                            <a href="{{ route('ciclo.visitacao.index') }}" class="block px-4 py-2 hover:bg-gray-100">Visitação</a>
                        </div>
                    </div>
                </li>
                @endif

                {{-- GESTÃO DO SISTEMA (flyouts PARA A DIREITA, sem deslocar dropdown raiz) --}}
                @if($canSistema)
                <li class="relative overflow-visible"
                    @mouseenter="sysOpen = true; giOpen=false; cicloOpen=false; atendOpen=false; relOpen=false"
                    @mouseleave="sysOpen = false">
                    <button type="button"
                            @click="sysOpen = !sysOpen; giOpen=false; cicloOpen=false; atendOpen=false; relOpen=false"
                            class="{{ $linkBase }} flex items-center gap-1
                            {{ (request()->routeIs('sistema.usuarios.*')
                                || request()->routeIs('sistema.unidades.*')
                                || request()->routeIs('servidores.*')
                                || request()->routeIs('frota.*')
                                || request()->routeIs('calendario.*')) ? $active : '' }}">
                        Gestão do Sistema
                        <span class="inline-block align-middle">▾</span>
                    </button>

                    <div x-show="sysOpen" x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-64 bg-white text-gray-900 rounded-2xl shadow-lg ring-1 ring-black/5 overflow-visible z-50"
                         @click.outside="sysOpen = false">
                        <div class="py-2 text-sm">

                            {{-- ===== Usuários (flyout -> direita, com anticorte) ===== --}}
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Usuários</span>
                                    <span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-72 bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2">
                                        <a href="{{ route('sistema.usuarios.create') }}" class="block px-4 py-2 hover:bg-gray-100">Cadastrar</a>
                                        <a href="{{ route('sistema.usuarios.search') }}" class="block px-4 py-2 hover:bg-gray-100">Buscar / Editar</a>
                                    </div>
                                </div>
                            </div>

                            {{-- ===== Unidades (flyout -> direita, com anticorte) ===== --}}
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Unidades</span>
                                    <span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-72 bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2">
                                        <a href="{{ route('sistema.unidades.create') }}" class="block px-4 py-2 hover:bg-gray-100">Cadastrar</a>
                                        <a href="{{ route('sistema.unidades.search') }}" class="block px-4 py-2 hover:bg-gray-100">Buscar / Editar</a>
                                    </div>
                                </div>
                            </div>

                            {{-- ===== Servidores (flyout -> direita, com anticorte) ===== --}}
                            @if($canServidores)
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Servidores</span>
                                    <span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-80 bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2 max-h-96 overflow-auto">
                                        @if($hasServCarga)
                                            <a href="{{ route('servidores.cargahoraria.index') }}" class="block px-4 py-2 hover:bg-gray-100">Cargos/Funções & Carga Horária</a>
                                        @endif
                                        @if($hasServCreate)
                                            <a href="{{ route('servidores.create') }}" class="block px-4 py-2 hover:bg-gray-100">Cadastrar Servidor</a>
                                        @endif
                                        @if($hasServSearch)
                                            <a href="{{ route('servidores.search') }}" class="block px-4 py-2 hover:bg-gray-100">Editar / Listar Servidores</a>
                                        @endif
                                        @if($hasServFreqSearch)
                                            <a href="{{ route('servidores.frequencia.search') }}" class="block px-4 py-2 hover:bg-gray-100">Registrar Frequência</a>
                                        @endif
                                        @if($hasServSearch && $hasServRel)
                                            <a href="{{ route('servidores.search') }}" class="block px-4 py-2 hover:bg-gray-100">Relatórios de Frequência</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endif

                            {{-- ===== Frota (flyout -> direita, com anticorte) ===== --}}
                            @if($canFrota)
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Frota</span>
                                    <span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-[22rem] bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2 max-h-96 overflow-auto">
                                        @if($hasFrotaVeicCreate)
                                            <a href="{{ route('frota.veiculos.create') }}" class="block px-4 py-2 hover:bg-gray-100">Cadastrar Veículo</a>
                                        @endif
                                        @if($hasFrotaVeicSearch)
                                            <a href="{{ route('frota.veiculos.search') }}" class="block px-4 py-2 hover:bg-gray-100">Buscar / Editar Veículo</a>
                                        @endif

                                        <div class="my-2 h-px bg-gray-200"></div>

                                        @if($hasFrotaUso)
                                            <a href="{{ route('frota.veiculos.uso') }}" class="block px-4 py-2 hover:bg-gray-100">Uso / Diárias / Checklists</a>
                                        @elseif($hasFrotaOperacoes)
                                            <a href="{{ route('frota.operacoes.index') }}" class="block px-4 py-2 hover:bg-gray-100">Uso / Diárias / Checklists</a>
                                        @endif

                                        @if($hasFrotaAbast)
                                            <a href="{{ route('frota.veiculos.abastecimentos') }}" class="block px-4 py-2 hover:bg-gray-100">Abastecimentos</a>
                                        @endif
                                        @if($hasFrotaDesloc)
                                            <a href="{{ route('frota.veiculos.deslocamentos') }}" class="block px-4 py-2 hover:bg-gray-100">Deslocamentos / Rotas</a>
                                        @endif

                                        <div class="my-2 h-px bg-gray-200"></div>

                                        @if($hasFrotaDocs)
                                            <a href="{{ route('frota.veiculos.documentos') }}" class="block px-4 py-2 hover:bg-gray-100">Documentos & Multas</a>
                                        @endif

                                        <div class="my-2 h-px bg-gray-200"></div>

                                        @if($hasFrotaRel)
                                            <a href="{{ route('frota.veiculos.relatorios') }}" class="block px-4 py-2 hover:bg-gray-100">Relatórios da Frota</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endif

                            {{-- ===== Calendário (link direto) ===== --}}
                            <div class="relative">
                                @if($hasCalendario)
                                    <a href="{{ route('calendario.index') }}" class="block px-4 py-2 hover:bg-gray-100">
                                        Calendário — Feriados & Pontos Facultativos
                                    </a>
                                @else
                                    <span class="block px-4 py-2 text-gray-400">Calendário — em breve</span>
                                @endif
                            </div>

                        </div>
                    </div>
                </li>
                @endif

                {{-- GESTÃO DE INDIVÍDUOS (flyouts à DIREITA, sem deslocar dropdown raiz) --}}
                @if($canIndividuos)
                <li class="relative overflow-visible"
                    @mouseenter="giOpen = true; sysOpen=false; cicloOpen=false; atendOpen=false; relOpen=false"
                    @mouseleave="giOpen = false">
                    <button type="button"
                            @click="giOpen = !giOpen; sysOpen=false; cicloOpen=false; atendOpen=false; relOpen=false"
                            class="{{ $linkBase }} flex items-center gap-1 {{ request()->routeIs('gestao.*') ? $active : '' }}">
                        Gestão de Indivíduos
                        <span class="inline-block align-middle">▾</span>
                    </button>

                    <div x-show="giOpen" x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-64 bg-white text-gray-900 rounded-2xl shadow-lg ring-1 ring-black/5 overflow-visible z-50"
                         @click.outside="giOpen = false">
                        <div class="py-2 text-sm">

                            {{-- Dados Pessoais --}}
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Dados Pessoais</span><span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-72 bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2">
                                        <a href="{{ route('gestao.dados.create') }}" class="block px-4 py-2 hover:bg-gray-100">Inserir Novo</a>
                                        <a href="{{ route('gestao.dados.search') }}" class="block px-4 py-2 hover:bg-gray-100">Buscar / Editar</a>
                                    </div>
                                </div>
                            </div>

                            {{-- Documentos --}}
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Documentos</span><span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-72 bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2">
                                        <a href="{{ route('gestao.documentos.create') }}" class="block px-4 py-2 hover:bg-gray-100">Cadastrar</a>
                                        <a href="{{ route('gestao.documentos.search') }}" class="block px-4 py-2 hover:bg-gray-100">Buscar / Editar</a>
                                    </div>
                                </div>
                            </div>

                            {{-- Identificação Visual --}}
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Identificação Visual</span><span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-72 bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2">
                                        <a href="{{ route('gestao.identificacao.visual.create') }}" class="block px-4 py-2 hover:bg-gray-100">Cadastrar</a>
                                        <a href="{{ route('gestao.identificacao.visual.search') }}" class="block px-4 py-2 hover:bg-gray-100">Buscar / Editar</a>
                                    </div>
                                </div>
                            </div>

                            {{-- Visitas --}}
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Visitas</span><span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-72 bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2">
                                        <a href="{{ route('gestao.visitas.create') }}" class="block px-4 py-2 hover:bg-gray-100">Cadastrar Visita</a>
                                        <a href="{{ route('gestao.visitas.search') }}" class="block px-4 py-2 hover:bg-gray-100">Buscar / Editar</a>
                                    </div>
                                </div>
                            </div>

                            <div class="my-2 h-px bg-gray-200"></div>

                            {{-- Fichas --}}
                            <div class="relative group" x-data="flyoutRight()"
                                 @mouseenter="open=true; $nextTick(recalc())"
                                 @mouseleave="open=false">
                                <button type="button" class="flex w-full items-center justify-between px-4 py-2 hover:bg-gray-100">
                                    <span>Fichas</span><span class="ml-2">▸</span>
                                </button>
                                <div x-show="open" x-transition.origin.top.left x-ref="panel" :style="style"
                                     class="absolute top-0 left-full ml-2 w-80 bg-white rounded-xl shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                    <div class="py-2">
                                        <a href="{{ route('gestao.ficha.index') }}" class="block px-4 py-2 hover:bg-gray-100">Ficha do Indivíduo</a>
                                        <a href="{{ route('gestao.visitantes.ficha.index') }}" class="block px-4 py-2 hover:bg-gray-100">Ficha de Visitante</a>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </li>
                @endif
            </ul>
        </div>
    </div>
</nav>

{{-- Script Alpine utilitário para “abrir à direita SEM cortar” --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('flyoutRight', () => ({
            open: false,
            style: '',
            recalc() {
                // Correção fina: mantém à direita (left:100% + ml-2), mas se encostar na borda,
                // aplica um translateX negativo só no painel para caber na viewport.
                this.$nextTick(() => {
                    const panel = this.$refs.panel;
                    if (!panel) return;
                    const rect = panel.getBoundingClientRect();
                    const gap  = 8; // margem de respiro da borda direita
                    const overflow = rect.right - (window.innerWidth - gap);
                    if (overflow > 0) {
                        this.style = `transform: translateX(-${overflow}px)`; // puxa só o necessário
                    } else {
                        this.style = '';
                    }
                });
            }
        }));
    });
    // Recalcula no resize para painéis abertos
    window.addEventListener('resize', () => {
        document.querySelectorAll('[x-data^="flyoutRight"]').forEach(el => {
            const comp = Alpine.$data(el);
            if (comp && comp.open && typeof comp.recalc === 'function') comp.recalc();
        });
    });
</script>

<div class="h-16"></div>
