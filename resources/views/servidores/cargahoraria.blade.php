{{-- resources/views/servidores/cargahoraria.blade.php --}}
@php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ==============================================
 *  AÇÕES (INSERT) — tudo no próprio Blade (GET)
 * ==============================================
 * Este bloco processa inserções quando há ?action=...
 * Sem alterar rotas/controllers por enquanto.
 */

$flash = null;

try {
    // Sanitizador simples (uppercase trim)
    $UP = fn($v) => mb_strtoupper(trim((string)($v ?? '')), 'UTF-8');

    if (request()->has('action')) {
        $act = request('action');

        // 1) Inserir CARGA / PLANTÃO
        if ($act === 'add_carga' && Schema::hasTable('cargas_horarias')) {
            $tipo   = $UP(request('tipo_semana'));
            $nome   = $UP(request('nome_plantao'));
            $cph    = (int) request('carga_horaria_plantao');
            $ent    = request('entrada');   // HH:MM
            $interv = request('intervalo'); // HH:MM

            // Converte intervalo HH:MM -> minutos
            $intervaloMin = 0;
            if ($interv && preg_match('/^\d{2}:\d{2}$/', $interv)) {
                [$hh,$mm] = array_map('intval', explode(':', $interv));
                $intervaloMin = max(0, $hh*60 + $mm);
            }

            // Validação mínima
            if ($tipo === '' && $nome === '' && !$cph && !$ent && !$intervaloMin) {
                $flash = ['type' => 'error', 'msg' => 'Preencha ao menos um campo antes de inserir.'];
            } else {
                // Insere (com unique lógico por (tipo_semana, nome_plantao) — se quiser evitar duplicado idêntico)
                DB::table('cargas_horarias')->insert([
                    'unidade_id'            => auth()->user()->unidade_id ?? null,
                    'tipo_semana'           => $tipo ?: null,
                    'nome_plantao'          => $nome ?: null,
                    'carga_horaria_plantao' => $cph ?: null,
                    'hora_entrada'          => $ent ?: null,
                    'intervalo_minutos'     => $intervaloMin,
                    'ativo'                 => 1,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
                $flash = ['type' => 'success', 'msg' => 'Carga/plantão inserido com sucesso.'];
            }
        }

        // 2) Inserir CARGO
        if ($act === 'add_cargo' && Schema::hasTable('cargos')) {
            $nome = $UP(request('cargo_nome'));
            if ($nome === '') {
                $flash = ['type' => 'error', 'msg' => 'Informe o nome do cargo.'];
            } else {
                // Evita duplicado por unidade
                $exists = DB::table('cargos')
                    ->where('unidade_id', auth()->user()->unidade_id ?? null)
                    ->whereRaw('UPPER(nome) = ?', [$nome])
                    ->exists();

                if ($exists) {
                    $flash = ['type' => 'error', 'msg' => 'Já existe um cargo com esse nome.'];
                } else {
                    DB::table('cargos')->insert([
                        'unidade_id' => auth()->user()->unidade_id ?? null,
                        'nome'       => $nome,
                        'ativo'      => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $flash = ['type' => 'success', 'msg' => 'Cargo inserido com sucesso.'];
                }
            }
        }

        // 3) Inserir FUNÇÃO
        if ($act === 'add_funcao' && Schema::hasTable('funcoes')) {
            $nome = $UP(request('funcao_nome'));
            if ($nome === '') {
                $flash = ['type' => 'error', 'msg' => 'Informe o nome da função.'];
            } else {
                // Evita duplicado por unidade
                $exists = DB::table('funcoes')
                    ->where('unidade_id', auth()->user()->unidade_id ?? null)
                    ->whereRaw('UPPER(nome) = ?', [$nome])
                    ->exists();

                if ($exists) {
                    $flash = ['type' => 'error', 'msg' => 'Já existe uma função com esse nome.'];
                } else {
                    DB::table('funcoes')->insert([
                        'unidade_id' => auth()->user()->unidade_id ?? null,
                        'nome'       => $nome,
                        'ativo'      => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $flash = ['type' => 'success', 'msg' => 'Função inserida com sucesso.'];
                }
            }
        }

        // Limpa a querystring após inserir (evita re-submit ao recarregar)
        // Obs.: fazemos via JS mais abaixo para manter o mesmo Route::view.
    }
} catch (\Throwable $e) {
    $flash = ['type' => 'error', 'msg' => 'Erro ao salvar: '.$e->getMessage()];
}

/**
 * ==============================================
 *  CARREGAMENTO DAS LISTAS (permanentes)
 * ==============================================
 */
$listaCargas  = collect();
$listaCargos  = collect();
$listaFuncoes = collect();

try {
    if (Schema::hasTable('cargas_horarias')) {
        $listaCargas = DB::table('cargas_horarias')
            ->where(function($q){
                $q->whereNull('unidade_id')
                  ->orWhere('unidade_id', auth()->user()->unidade_id ?? null);
            })
            ->orderBy('tipo_semana')
            ->orderBy('nome_plantao')
            ->orderBy('hora_entrada')
            ->get();
    }
    if (Schema::hasTable('cargos')) {
        $listaCargos = DB::table('cargos')
            ->where(function($q){
                $q->whereNull('unidade_id')
                  ->orWhere('unidade_id', auth()->user()->unidade_id ?? null);
            })
            ->orderBy('nome')
            ->get();
    }
    if (Schema::hasTable('funcoes')) {
        $listaFuncoes = DB::table('funcoes')
            ->where(function($q){
                $q->whereNull('unidade_id')
                  ->orWhere('unidade_id', auth()->user()->unidade_id ?? null);
            })
            ->orderBy('nome')
            ->get();
    }
} catch (\Throwable $e) {
    // Mantém listas vazias em caso de erro
}
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl uppercase">Servidores — Configurar Carga Horária</h2>
    </x-slot>

    {{-- Flash --}}
    @if ($flash || session('success') || session('error') || $errors->any())
        @php
            $klass = 'border-blue-600 bg-blue-50';
            if (($flash['type'] ?? null) === 'success' || session('success')) $klass = 'border-green-600 bg-green-50';
            if (($flash['type'] ?? null) === 'error'   || session('error'))   $klass = 'border-red-600 bg-red-50';
            if ($errors->any()) $klass = 'border-yellow-600 bg-yellow-50';
        @endphp
        <div data-flash class="mb-4 rounded border p-3 {{ $klass }}">
            @if ($flash)
                <div class="font-semibold {{ $flash['type']==='error' ? 'text-red-800' : 'text-green-800' }}">
                    {{ $flash['msg'] }}
                </div>
            @endif
            @if (session('success')) <div class="font-semibold text-green-800">{{ session('success') }}</div> @endif
            @if (session('error'))   <div class="font-semibold text-red-800">{{ session('error') }}</div>   @endif
            @if ($errors->any())
                <div class="font-semibold text-yellow-800">Há pendências no formulário.</div>
                <ul class="mt-1 list-inside list-disc text-sm text-yellow-900">
                    @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- =================== BLOCO 1: CONFIGURAR CARGA HORÁRIA =================== --}}
    <div class="rounded-xl bg-gray-800 p-4 shadow">
        <div class="rounded-lg bg-white p-4">
            {{-- Form envia via GET para mesma rota (Route::view) --}}
            <form class="space-y-6" method="get" autocomplete="off">
                <input type="hidden" name="action" value="add_carga">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3 uppercase">
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium">Tipo de carga horária semanal</label>
                        <input name="tipo_semana" class="mt-1 w-full rounded border p-2 uppercase"
                               placeholder="EX.: 40H, 30H, 20H" data-upcase>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Nome do plantão</label>
                        <input name="nome_plantao" class="mt-1 w-full rounded border p-2 uppercase"
                               placeholder="EX.: 24X72, 12X36, DIARISTA" data-upcase>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Carga horária plantão (h)</label>
                        <input type="number" name="carga_horaria_plantao" class="mt-1 w-full rounded border p-2"
                               min="0" max="168" step="1" placeholder="Ex.: 24" data-carga-plantao>
                        <p class="mt-1 text-[11px] normal-case text-gray-500">
                            Ex.: 24, 36, 48... (duração total do plantão, em horas).
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Entrada (padrão)</label>
                        <input type="time" name="entrada" class="mt-1 w-full rounded border p-2" data-hora-entrada>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Intervalo (padrão)</label>
                        <input type="time" name="intervalo" class="mt-1 w-full rounded border p-2" data-hora-intervalo placeholder="01:00">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Saída (auto)</label>
                        <input type="time" name="saida_preview" class="mt-1 w-full rounded border p-2" data-hora-saida readonly>
                        <p class="mt-1 text-[11px] normal-case text-gray-500">
                            Calculada: <em>entrada + carga do plantão (h) + intervalo</em>. (Não é salva)
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="reset" class="rounded border px-4 py-2 hover:bg-gray-50">
                        Limpar
                    </button>
                    <button type="submit" class="rounded bg-blue-900 px-5 py-2 text-white shadow hover:opacity-95">
                        Inserir
                    </button>
                </div>
            </form>
        </div>

        {{-- Lista persistente (BD) com ordenação client-side --}}
        <div class="mt-4 overflow-x-auto rounded-lg bg-white p-2">
            <table class="min-w-full text-sm uppercase" id="grid-cargas">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left cursor-pointer" data-sort="tipo_semana">Tipo semanal</th>
                        <th class="px-3 py-2 text-left cursor-pointer" data-sort="nome_plantao">Plantão (nome)</th>
                        <th class="px-3 py-2 text-left cursor-pointer" data-sort="carga_horaria_plantao">Carga plantão (h)</th>
                        <th class="px-3 py-2 text-left cursor-pointer" data-sort="hora_entrada">Entrada</th>
                        <th class="px-3 py-2 text-left cursor-pointer" data-sort="intervalo_minutos">Intervalo</th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    @forelse($listaCargas as $row)
                        <tr class="border-b last:border-0">
                            <td class="px-3 py-2" data-col="tipo_semana">{{ $row->tipo_semana ?? '—' }}</td>
                            <td class="px-3 py-2" data-col="nome_plantao">{{ $row->nome_plantao ?? '—' }}</td>
                            <td class="px-3 py-2" data-col="carga_horaria_plantao">{{ $row->carga_horaria_plantao ?? '—' }}</td>
                            <td class="px-3 py-2" data-col="hora_entrada">{{ $row->hora_entrada ? substr($row->hora_entrada,0,5) : '—' }}</td>
                            <td class="px-3 py-2" data-col="intervalo_minutos">
                                @php
                                    $m = (int)($row->intervalo_minutos ?? 0);
                                    $hh = str_pad((string)floor($m/60),2,'0',STR_PAD_LEFT);
                                    $mm = str_pad((string)($m%60),2,'0',STR_PAD_LEFT);
                                @endphp
                                {{ $m ? "$hh:$mm" : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr id="grid-empty">
                            <td colspan="5" class="px-3 py-6 text-center text-gray-500 normal-case">
                                Nenhum lançamento ainda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- =================== BLOCO 2: CATÁLOGO DE CARGOS & FUNÇÕES =================== --}}
    <div class="mt-6 rounded-xl bg-gray-800 p-4 shadow">
        <div class="rounded-lg bg-white p-4">
            <h3 class="mb-3 text-lg font-semibold uppercase">Catálogo de Cargos & Funções da Unidade</h3>
            <p class="mb-4 text-sm text-gray-600 normal-case">
                Cadastre aqui os <strong>cargos</strong> e <strong>funções</strong> disponíveis.
                Eles serão oferecidos como opção no formulário de cadastro de servidores.
            </p>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                {{-- Coluna CARGOS --}}
                <div>
                    <div class="rounded-lg border p-3">
                        <form class="flex items-end gap-2" method="get" autocomplete="off">
                            <input type="hidden" name="action" value="add_cargo">
                            <div class="flex-1">
                                <label class="block text-sm font-medium">Novo Cargo</label>
                                <input type="text" name="cargo_nome" class="mt-1 w-full rounded border p-2 uppercase" data-upcase placeholder="EX.: POLICIAL PENAL, PSICÓLOGO, ASSISTENTE SOCIAL">
                            </div>
                            <button type="submit" class="rounded bg-blue-900 px-4 py-2 text-white shadow hover:opacity-95">
                                Inserir
                            </button>
                        </form>
                    </div>

                    <div class="mt-3 overflow-hidden rounded-lg border">
                        <div class="bg-gray-50 px-3 py-2 text-sm font-semibold uppercase">Cargos cadastrados</div>
                        <div class="max-h-72 overflow-auto">
                            <table class="min-w-full text-sm uppercase" id="tbl-cargos">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-full cursor-pointer" data-sort="nome">Cargo</th>
                                    </tr>
                                </thead>
                                <tbody id="cargos-body">
                                    @forelse($listaCargos as $c)
                                        <tr class="border-b last:border-0">
                                            <td class="px-3 py-2" data-col="nome">{{ $c->nome }}</td>
                                        </tr>
                                    @empty
                                        <tr id="cargos-empty">
                                            <td class="px-3 py-4 text-center text-gray-500 normal-case">Nenhum cargo cadastrado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Coluna FUNÇÕES --}}
                <div>
                    <div class="rounded-lg border p-3">
                        <form class="flex items-end gap-2" method="get" autocomplete="off">
                            <input type="hidden" name="action" value="add_funcao">
                            <div class="flex-1">
                                <label class="block text-sm font-medium">Nova Função</label>
                                <input type="text" name="funcao_nome" class="mt-1 w-full rounded border p-2 uppercase" data-upcase placeholder="EX.: DIRETOR GERAL, COORDENADOR">
                            </div>
                            <button type="submit" class="rounded bg-blue-900 px-4 py-2 text-white shadow hover:opacity-95">
                                Inserir
                            </button>
                        </form>
                    </div>

                    <div class="mt-3 overflow-hidden rounded-lg border">
                        <div class="bg-gray-50 px-3 py-2 text-sm font-semibold uppercase">Funções cadastradas</div>
                        <div class="max-h-72 overflow-auto">
                            <table class="min-w-full text-sm uppercase" id="tbl-funcoes">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-full cursor-pointer" data-sort="nome">Função</th>
                                    </tr>
                                </thead>
                                <tbody id="funcoes-body">
                                    @forelse($listaFuncoes as $f)
                                        <tr class="border-b last:border-0">
                                            <td class="px-3 py-2" data-col="nome">{{ $f->nome }}</td>
                                        </tr>
                                    @empty
                                        <tr id="funcoes-empty">
                                            <td class="px-3 py-4 text-center text-gray-500 normal-case">Nenhuma função cadastrada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div> {{-- /col --}}
            </div> {{-- /grid --}}
        </div>
    </div>

    {{-- =================== SCRIPTS =================== --}}
    <script>
    (function(){
        /* ----- Limpa a querystring após inserção (se houver action=...) ----- */
        const url = new URL(window.location.href);
        if (url.searchParams.get('action')) {
            // Mostra o flash e limpa a URL depois de um mini delay
            setTimeout(() => {
                url.searchParams.delete('action');
                url.searchParams.delete('tipo_semana');
                url.searchParams.delete('nome_plantao');
                url.searchParams.delete('carga_horaria_plantao');
                url.searchParams.delete('entrada');
                url.searchParams.delete('intervalo');
                url.searchParams.delete('saida_preview');
                url.searchParams.delete('cargo_nome');
                url.searchParams.delete('funcao_nome');
                window.history.replaceState({}, '', url.toString());
            }, 300);
        }

        /* ---------- Helpers tempo ---------- */
        function toMinutes(hhmm) {
            if(!hhmm || !/^\d{2}:\d{2}$/.test(hhmm)) return null;
            const [h,m] = hhmm.split(':').map(Number);
            return h*60 + m;
        }
        function fromMinutes(total) {
            if (total == null) return '';
            const norm = ((total % (24*60)) + (24*60)) % (24*60); // 0..1439
            const h = String(Math.floor(norm/60)).padStart(2,'0');
            const m = String(norm%60).padStart(2,'0');
            return `${h}:${m}`;
        }

        /* ---------- Uppercase em tempo real ---------- */
        function bindUppercase() {
            document.querySelectorAll('[data-upcase]').forEach(el => {
                const toUp = () => { el.value = (el.value || '').toUpperCase(); };
                el.addEventListener('input', toUp, {passive:true});
                el.addEventListener('change', toUp, {passive:true});
                toUp();
            });
        }

        /* ---------- Cálculo da SAÍDA (preview) ---------- */
        const entrada   = document.querySelector('[data-hora-entrada]');
        const intervalo = document.querySelector('[data-hora-intervalo]');
        const saida     = document.querySelector('[data-hora-saida]');
        const cargaEl   = document.querySelector('[data-carga-plantao]');

        function recalcSaida() {
            const eMin = toMinutes(entrada?.value);
            const iMin = toMinutes(intervalo?.value) ?? 0;
            const cargaHoras = parseFloat(cargaEl?.value || '0');
            const cMin = isFinite(cargaHoras) ? Math.round(cargaHoras * 60) : 0;
            if (eMin == null) return;
            const total = eMin + cMin + (iMin ?? 0);
            if (saida) saida.value = fromMinutes(total);
        }
        ['change','input'].forEach(evt => {
            if (entrada)   entrada.addEventListener(evt, recalcSaida, {passive:true});
            if (intervalo) intervalo.addEventListener(evt, recalcSaida, {passive:true});
            if (cargaEl)   cargaEl.addEventListener(evt, recalcSaida, {passive:true});
        });

        /* ---------- Ordenação client-side para as tabelas ---------- */
        function makeSortable(tableId) {
            const table = document.getElementById(tableId);
            if (!table) return;

            const tbody = table.querySelector('tbody');
            const ths = table.querySelectorAll('thead th[data-sort]');
            ths.forEach(th => {
                th.addEventListener('click', () => {
                    const col = th.getAttribute('data-sort');
                    const dir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
                    ths.forEach(t => t.removeAttribute('data-dir'));
                    th.setAttribute('data-dir', dir);

                    const rows = Array.from(tbody.querySelectorAll('tr')).filter(tr => !tr.id);
                    rows.sort((a, b) => {
                        const va = (a.querySelector(`[data-col="${col}"]`)?.textContent || '').trim();
                        const vb = (b.querySelector(`[data-col="${col}"]`)?.textContent || '').trim();

                        // Numérico quando fizer sentido
                        const na = parseFloat(va.replace(',', '.'));
                        const nb = parseFloat(vb.replace(',', '.'));
                        const isNum = !isNaN(na) && !isNaN(nb);

                        if (isNum) {
                            return dir === 'asc' ? (na - nb) : (nb - na);
                        }
                        return dir === 'asc'
                            ? va.localeCompare(vb, 'pt-BR', {numeric:true})
                            : vb.localeCompare(va, 'pt-BR', {numeric:true});
                    });

                    // Reanexa na nova ordem
                    rows.forEach(r => tbody.appendChild(r));
                }, {passive:true});
            });
        }

        makeSortable('grid-cargas');
        makeSortable('tbl-cargos');
        makeSortable('tbl-funcoes');

        /* ---------- init ---------- */
        bindUppercase();
        recalcSaida();
    })();
    </script>
</x-app-layout>
