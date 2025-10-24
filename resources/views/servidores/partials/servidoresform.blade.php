{{-- resources/views/servidores/partials/servidoresform.blade.php --}}
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Facades\Schema;

    $user = auth()->user();

    // Unidades (com perfil_outro)
    $unidades = $unidades ?? DB::table('unidades')
        ->select('id','nome','perfil','perfil_outro')
        ->orderBy('nome')
        ->get();

    $selectedUnidadeId = old('unidade_id',
        isset($servidor) ? ($servidor->unidade_id ?? null) : ($user->unidade_id ?? null)
    );

    // Perfil atual (OUTROS + perfil_outro quando existir)
    $selectedUnidadePerfil = '';
    if ($selectedUnidadeId) {
        try {
            $u = $unidades->firstWhere('id', (int)$selectedUnidadeId);
            if ($u) {
                $selectedUnidadePerfil = $u->perfil === 'OUTROS' && $u->perfil_outro
                    ? 'OUTROS - '.$u->perfil_outro
                    : $u->perfil;
            }
        } catch (\Throwable $e) { $selectedUnidadePerfil = ''; }
    }

    $isGlobalAdmin = Gate::check('menu.sistema');
    $lockUnidade   = !$isGlobalAdmin;

    // Catálogos (cargo/função)
    if (!isset($cargos) && Schema::hasTable('cargos')) {
        try { $cargos = DB::table('cargos')->select('id','nome')->orderBy('nome')->get(); }
        catch (\Throwable $e) { $cargos = collect(); }
    } else { $cargos = collect($cargos ?? []); }

    if (!isset($funcoes) && Schema::hasTable('funcoes')) {
        try { $funcoes = DB::table('funcoes')->select('id','nome')->orderBy('nome')->get(); }
        catch (\Throwable $e) { $funcoes = collect(); }
    } else { $funcoes = collect($funcoes ?? []); }

    // Opções de PLANTÃO a partir de cargas_horarias.nome_plantao
    $optTiposPlantao = collect();
    if (Schema::hasTable('cargas_horarias')) {
        try {
            $optTiposPlantao = DB::table('cargas_horarias')
                ->select('nome_plantao')
                ->whereNotNull('nome_plantao')
                ->when($user?->unidade_id, fn($q) =>
                    $q->where(fn($qq) => $qq->whereNull('unidade_id')->orWhere('unidade_id', $user->unidade_id))
                )
                ->groupBy('nome_plantao')
                ->orderBy('nome_plantao')
                ->pluck('nome_plantao');
        } catch (\Throwable $e) {
            $optTiposPlantao = collect();
        }
    }

    // Helpers
    $val = fn($name, $default = '') => old($name, isset($servidor) ? ($servidor->{$name} ?? $default) : $default);
    $isCreate = !isset($servidor) || empty($servidor?->id);

    // --------- AJUSTES PARA EDIT / FALLBACKS ----------
    $prefill = isset($prefill) && is_array($prefill) ? $prefill : (array)($prefill ?? []);

    $selectedCargoId  = old('cargo_id',  $selectedCargoId  ?? null);
    $selectedFuncaoId = old('funcao_id', $selectedFuncaoId ?? null);

    $selectedTipoPlantao = old('tipo_plantao', $prefill['tipo_plantao'] ?? null);

    $cargaNumerica = old('carga_horaria', isset($servidor) ? ($servidor->carga_horaria ?? null) : null);

    // Normalizador
    $normalize = function ($s) {
        $s = trim((string)$s);
        $s = preg_replace('/\s+/', ' ', $s);
        $up = mb_strtoupper($s, 'UTF-8');
        try {
            $noAcc = @iconv('UTF-8','ASCII//TRANSLIT',$up);
            if ($noAcc !== false && $noAcc !== null) $up = $noAcc;
        } catch (\Throwable $e) {}
        return preg_replace('/[^A-Z0-9\/\- ]/', '', $up);
    };

    if ($selectedTipoPlantao && $normalize($selectedTipoPlantao) === 'PLANTONISTA') {
        $candidato = collect($optTiposPlantao)->first(function($r) use ($normalize){
            $n = $normalize($r);
            if (in_array($n, ['DIARISTA','ADMINISTRATIVO'])) return false;
            return preg_match('/[0-9]|\/|X/', $n) === 1;
        });
        if (!$candidato) {
            $candidato = collect($optTiposPlantao)->first(function($r) use ($normalize){
                $n = $normalize($r);
                return !in_array($n, ['DIARISTA','ADMINISTRATIVO']);
            });
        }
        if ($candidato) $selectedTipoPlantao = $candidato;
    }

    $tipoOptions = [];
    foreach ($optTiposPlantao as $nome) {
        $tipoOptions[] = ['raw' => $nome, 'norm' => $normalize($nome)];
    }
    $selectedTipoPlantaoNorm = $normalize($selectedTipoPlantao ?? '');

    // URL do store da frequência APENAS se existir ID (evita erro no create)
    $servidorId = isset($servidor)? ($servidor->id ?? null) : null;
    $freqUrl = null;
    if ($servidorId) {
        try { $freqUrl = route('servidores.frequencia.store', $servidorId); } catch (\Throwable $e) { $freqUrl = null; }
    }

    // ===== NOVO: Carregar lançamentos (apenas ocorrências especiais) da FREQUÊNCIA =====
    $freqESPECIAIS = collect(['FOLGA','FERIAS','LICENCA','ATESTADO','OUTROS']);
    $freqRegistros = collect();
    if ($servidorId && Schema::hasTable('servidores_frequencia')) {
        try {
            $freqRegistros = DB::table('servidores_frequencia')
                ->select('data','tipo')
                ->where('servidor_id', $servidorId)
                ->whereIn('tipo', $freqESPECIAIS->all())
                ->orderBy('data','asc') // facilita agrupar intervalos
                ->get();
        } catch (\Throwable $e) {
            $freqRegistros = collect();
        }
    }

    // ===== NOVO: Agrupar por intervalos contínuos (por TIPO) =====
    $faixas = [];
    if ($freqRegistros->isNotEmpty()) {
        $label = fn($t) => match (mb_strtoupper($t)) {
            'FERIAS'  => 'FÉRIAS',
            'LICENCA' => 'LICENÇA',
            default   => mb_strtoupper($t),
        };

        $curTipo = null;
        $ini     = null;
        $prev    = null;

        foreach ($freqRegistros as $r) {
            $d = \Carbon\Carbon::parse($r->data)->startOfDay();
            $t = mb_strtoupper($r->tipo);

            if ($curTipo === null) {
                $curTipo = $t; $ini = $d->copy(); $prev = $d->copy();
                continue;
            }

            // se mesmo tipo e data CONTÍNUA (prev + 1 dia): estende faixa
            if ($t === $curTipo && $d->equalTo($prev->copy()->addDay())) {
                $prev = $d->copy();
                continue;
            }

            // quebrou intervalo (tipo diferente ou pulo de data): salva e reinicia
            $faixas[] = [
                'tipo'    => $label($curTipo),
                'inicio'  => $ini->copy(),
                'fim'     => $prev->copy(),
                'retorno' => $prev->copy()->addDay(),
            ];
            $curTipo = $t; $ini = $d->copy(); $prev = $d->copy();
        }

        // fecha a última faixa
        if ($curTipo !== null && $ini !== null && $prev !== null) {
            $faixas[] = [
                'tipo'    => $label($curTipo),
                'inicio'  => $ini->copy(),
                'fim'     => $prev->copy(),
                'retorno' => $prev->copy()->addDay(),
            ];
        }
    }
@endphp

<div class="space-y-6 uppercase">
    {{-- Linha 0: Unidade + Perfil da Unidade (auto) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium">UNIDADE <span class="text-red-600">*</span></label>
            <select name="unidade_id" id="unidade_id"
                    class="mt-1 w-full rounded border p-2 {{ $lockUnidade ? 'bg-gray-100 text-gray-600' : '' }}"
                    {{ $lockUnidade ? 'disabled' : 'required' }}>
                <option value="" disabled {{ !$selectedUnidadeId ? 'selected' : '' }}>— SELECIONAR —</option>
                @foreach($unidades as $u)
                    <option value="{{ $u->id }}"
                            data-perfil="{{ $u->perfil }}"
                            data-perfil-outro="{{ $u->perfil_outro }}"
                            {{ (string)$u->id === (string)$selectedUnidadeId ? 'selected' : '' }}>
                        {{ $u->nome }}
                    </option>
                @endforeach
            </select>
            @if($lockUnidade)
                <input type="hidden" name="unidade_id" value="{{ $selectedUnidadeId }}">
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium">PERFIL DA UNIDADE <span class="text-red-600">*</span></label>
            <input id="perfil_unidade_view"
                   class="mt-1 w-full rounded border p-2 bg-gray-100"
                   value="{{ old('perfil_unidade', $selectedUnidadePerfil) }}"
                   readonly>
            <input type="hidden" name="perfil_unidade" id="perfil_unidade" value="{{ old('perfil_unidade', $selectedUnidadePerfil) }}">
        </div>
    </div>

    {{-- Linha 0A: Data/Hora do cadastro (apenas no CREATE) --}}
    @if($isCreate)
        @php $createdNow = now(); @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">DATA/HORA DO CADASTRO</label>
                <input class="mt-1 w-full rounded border p-2 bg-gray-100"
                       value="{{ $createdNow->format('d/m/Y H:i') }}"
                       disabled>
                <input type="hidden" name="created_at" value="{{ $createdNow->format('Y-m-d H:i:s') }}">
            </div>
        </div>
    @endif

    {{-- Linha 1: Nome do Servidor --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">NOME DO SERVIDOR <span class="text-red-600">*</span></label>
            <input name="nome" value="{{ $val('nome') }}" class="mt-1 w-full rounded border p-2 uppercase" data-upcase required maxlength="150">
        </div>
    </div>

    {{-- Linha 2: CPF & Matrícula --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium">CPF <span class="text-red-600">*</span></label>
            <input name="cpf" value="{{ $val('cpf') }}" class="mt-1 w-full rounded border p-2"
                   data-mask="cpf" inputmode="numeric" autocomplete="off" required>
        </div>
        <div>
            <label class="block text-sm font-medium">MATRÍCULA <span class="text-red-600">*</span></label>
            <input name="matricula" value="{{ $val('matricula') }}" class="mt-1 w-full rounded border p-2 uppercase"
                   data-upcase maxlength="40" required>
        </div>
    </div>

    {{-- Linha 3: Cargo (OBRIGATÓRIO) & Função (OPCIONAL) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium">CARGO <span class="text-red-600">*</span></label>
            @if($cargos->count())
                <select name="cargo_id" class="mt-1 w-full rounded border p-2" required>
                    <option value="">— SELECIONAR —</option>
                    @foreach($cargos as $c)
                        <option value="{{ $c->id }}" {{ (string)$selectedCargoId === (string)$c->id ? 'selected' : '' }}>
                            {{ $c->nome }}
                        </option>
                    @endforeach
                </select>
            @else
                <input name="cargo" value="{{ $val('cargo') }}" class="mt-1 w-full rounded border p-2 uppercase"
                       data-upcase placeholder="DIGITE O CARGO" required>
                <p class="mt-1 text-[11px] normal-case text-gray-500">Catálogo de cargos ainda não configurado.</p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium">FUNÇÃO (OPCIONAL)</label>
            @if($funcoes->count())
                <select name="funcao_id" class="mt-1 w-full rounded border p-2">
                    <option value="">— SELECIONAR —</option>
                    @foreach($funcoes as $f)
                        <option value="{{ $f->id }}" {{ (string)$selectedFuncaoId === (string)$f->id ? 'selected' : '' }}>
                            {{ $f->nome }}
                        </option>
                    @endforeach
                </select>
            @else
                <input name="funcao" value="{{ $val('funcao') }}" class="mt-1 w-full rounded border p-2 uppercase"
                       data-upcase placeholder="(DEIXE EM BRANCO SE O CARGO JÁ FOR A FUNÇÃO PRINCIPAL)">
            @endif
        </div>
    </div>

    {{-- Linha 4: Carga horária (número) & Tipo de plantão --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium">CARGA HORÁRIA SEMANAL (NÚMERO) <span class="text-red-600">*</span></label>
            <input type="number" name="carga_horaria"
                   value="{{ $cargaNumerica }}"
                   class="mt-1 w-full rounded border p-2"
                   inputmode="numeric" min="1" max="80" step="1" required
                   placeholder="EX.: 40">
            <p class="mt-1 text-[11px] normal-case text-gray-500">Informe somente o número de horas (1 a 80). Ex.: <strong>40</strong>.</p>
        </div>
        <div>
            <label class="block text-sm font-medium">TIPO DE PLANTÃO <span class="text-red-600">*</span></label>
            @if(count($tipoOptions))
                <select name="tipo_plantao" class="mt-1 w-full rounded border p-2" required>
                    <option value="">— SELECIONAR —</option>
                    @foreach($tipoOptions as $opt)
                        <option value="{{ $opt['raw'] }}" {{ $opt['norm'] === $selectedTipoPlantaoNorm ? 'selected' : '' }}>
                            {{ $opt['raw'] }}
                        </option>
                    @endforeach
                </select>
            @else
                <input name="tipo_plantao" value="{{ $selectedTipoPlantao }}"
                       class="mt-1 w-full rounded border p-2 uppercase" data-upcase
                       placeholder="EX.: PLANTÃO 24/72, DIARISTA" required>
                <p class="mt-1 text-[11px] normal-case text-gray-500">Sem catálogos configurados; digite o rótulo exatamente como usa na unidade.</p>
            @endif
        </div>
    </div>

    {{-- Linha 5: Situação --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">SITUAÇÃO</label>
            @php $ativoChecked = old('ativo', isset($servidor) ? (int)($servidor->ativo ?? 1) : 1); @endphp
            <div class="mt-1 flex items-center gap-4">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="ativo" value="1" {{ (string)$ativoChecked === '1' ? 'checked' : '' }}>
                    <span>ATIVO</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="ativo" value="0" {{ (string)$ativoChecked === '0' ? 'checked' : '' }} data-inativo>
                    <span>INATIVO</span>
                </label>
            </div>

            <div id="wrap-motivo-inatividade" class="mt-2 {{ (string)$ativoChecked === '0' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium">MOTIVO DA INATIVIDADE</label>
                <textarea name="motivo_inatividade" rows="3" class="mt-1 w-full rounded border p-2 uppercase" data-upcase
                          placeholder="EX.: EXONERAÇÃO PUBLICADA NO DIÁRIO OFICIAL EM 23/05/2025">{{ $val('motivo_inatividade') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ================= NOVA SEÇÃO: LANÇAMENTOS (OCORRÊNCIAS) ================= --}}
    @if($freqUrl)
    <div class="rounded border">
        <div class="px-3 py-2 font-semibold bg-gray-50">LANÇAMENTOS (OCORRÊNCIAS)</div>

        <div class="p-3" x-data="lancamentosServForm('{{ $freqUrl }}')">
            <p class="text-xs text-gray-600 normal-case">
                Informe períodos de <span class="uppercase">FOLGA / FÉRIAS / LICENÇA / ATESTADO / OUTROS</span>. Cada dia do intervalo será lançado na FREQUÊNCIA.
            </p>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left bg-gray-50">
                            <th class="px-3 py-2 w-44">DATA INÍCIO</th>
                            <th class="px-3 py-2 w-44">DATA FIM</th>
                            <th class="px-3 py-2 w-48">TIPO</th>
                            <th class="px-3 py-2">OBSERVAÇÕES</th>
                            <th class="px-3 py-2 w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(l, i) in lancamentos" :key="i">
                            <tr class="border-t">
                                <td class="px-3 py-2">
                                    <input type="date" x-model="l.ini" class="w-full rounded border p-2 uppercase">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="date" x-model="l.fim" class="w-full rounded border p-2 uppercase" :min="l.ini || null">
                                </td>
                                <td class="px-3 py-2">
                                    <select x-model="l.tipo" class="w-full rounded border p-2 uppercase">
                                        <option value="">— SELECIONAR —</option>
                                        <option value="FOLGA">FOLGA</option>
                                        <option value="FERIAS">FÉRIAS</option>
                                        <option value="LICENCA">LICENÇA</option>
                                        <option value="ATESTADO">ATESTADO</option>
                                        <option value="OUTROS">OUTROS</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text"
                                           x-model="l.obs"
                                           class="w-full rounded border p-2 uppercase"
                                           placeholder="EX.: DOC., PUBLICAÇÃO, DETALHES"
                                           oninput="this.value=this.value.toUpperCase()">
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="text-red-700 hover:underline" @click="remover(i)">REMOVER</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 flex items-center gap-3">
                <button type="button"
                        class="rounded border px-4 py-2 hover:bg-gray-50 font-semibold"
                        @click="adicionar()">+ LANÇAMENTO</button>

                <button type="button"
                        class="rounded bg-blue-900 px-5 py-2 text-white hover:opacity-95 disabled:opacity-60"
                        :disabled="submitting || lancamentos.length===0"
                        @click="salvar()">
                    <span x-show="!submitting">SALVAR LANÇAMENTOS</span>
                    <span x-show="submitting">ENVIANDO…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ======== NOVA SUBSEÇÃO: RESUMO DE OCORRÊNCIAS (INTERVALOS) ======== --}}
    <div class="rounded border mt-4">
        <div class="px-3 py-2 font-semibold bg-gray-50">RESUMO DE OCORRÊNCIAS (APENAS FOLGA / FÉRIAS / LICENÇA / ATESTADO / OUTROS)</div>

        <div class="p-3">
            @if(empty($faixas))
                <div class="rounded border border-amber-200 bg-amber-50 p-3 text-amber-900 normal-case">
                    Nenhum lançamento especial encontrado para este servidor.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left bg-gray-50">
                                <th class="px-3 py-2 w-40">TIPO</th>
                                <th class="px-3 py-2 w-40">INÍCIO</th>
                                <th class="px-3 py-2 w-40">FIM</th>
                                <th class="px-3 py-2 w-48">RETORNO PREVISTO</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($faixas as $fx)
                                <tr class="border-t">
                                    <td class="px-3 py-2">{{ $fx['tipo'] }}</td>
                                    <td class="px-3 py-2">{{ $fx['inicio']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $fx['fim']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $fx['retorno']->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    {{-- ======== / RESUMO ======== --}}
    @else
    <div class="rounded border bg-amber-50 border-amber-200 p-3 text-amber-900 normal-case">
        A seção <strong>LANÇAMENTOS (OCORRÊNCIAS)</strong> ficará disponível após salvar o servidor.
    </div>
    @endif
    {{-- ================= / NOVA SEÇÃO ================= --}}
</div>

{{-- Scripts: Uppercase + toggle Motivo + perfil (OUTROS) --}}
<script>
(function(){
    // Garante CSRF para o fetch
    if (!window.CSRF_TOKEN) { window.CSRF_TOKEN = '{{ csrf_token() }}'; }

    function bindUppercase() {
        document.querySelectorAll('[data-upcase]').forEach(el => {
            const toUp = () => { el.value = (el.value || '').toUpperCase(); };
            el.addEventListener('input', toUp, {passive:true});
            el.addEventListener('change', toUp, {passive:true});
            toUp();
        });
    }

    function bindInatividade() {
        const wrap = document.getElementById('wrap-motivo-inatividade');
        const radios = document.querySelectorAll('input[name="ativo"]');
        if (!wrap || !radios.length) return;
        const onChange = () => {
            const v = document.querySelector('input[name="ativo"]:checked')?.value;
            if (v === '0') wrap.classList.remove('hidden'); else wrap.classList.add('hidden');
        };
        radios.forEach(r => r.addEventListener('change', onChange, {passive:true}));
        onChange();
    }

    // Perfil auto (inclui OUTROS)
    function bindPerfilUnidade() {
        const sel   = document.getElementById('unidade_id');
        const view  = document.getElementById('perfil_unidade_view');
        const hid   = document.getElementById('perfil_unidade');
        if (!sel || !view || !hid) return;

        const apply = () => {
            const opt    = sel.options[sel.selectedIndex];
            const perfil = (opt?.getAttribute('data-perfil') || '').toUpperCase();
            const outro  = opt?.getAttribute('data-perfil-outro') || '';
            const combined = (perfil === 'OUTROS' && outro) ? `OUTROS - ${outro}` : perfil;
            view.value = combined || '';
            hid.value  = combined || '';
        };

        sel.addEventListener('change', apply, {passive:true});
        apply(); // inicial
    }

    bindUppercase();
    bindInatividade();
    bindPerfilUnidade();
})();
</script>

{{-- Script da NOVA SEÇÃO: LANÇAMENTOS (usa o endpoint de FREQUÊNCIA) --}}
<script>
function lancamentosServForm(urlStore){
    return {
        lancamentos: [],
        submitting: false,

        adicionar(){ this.lancamentos.push({ini:'', fim:'', tipo:'', obs:''}); },
        remover(i){ this.lancamentos.splice(i,1); },

        async salvar(){
            if (!urlStore) { alert('ID DO SERVIDOR INDISPONÍVEL. SALVE O CADASTRO PRIMEIRO.'); return; }

            // validação simples
            for (const [idx,l] of this.lancamentos.entries()) {
                if (!l.ini || !l.tipo) {
                    alert(`PREENCHA DATA INÍCIO E TIPO NO LANÇAMENTO #${idx+1}.`);
                    return;
                }
                if (l.fim && l.fim < l.ini) {
                    alert(`DATA FIM NÃO PODE SER ANTERIOR À INÍCIO NO LANÇAMENTO #${idx+1}.`);
                    return;
                }
            }

            this.submitting = true;
            try {
                for (const l of this.lancamentos) {
                    const start = new Date(l.ini);
                    const end   = l.fim ? new Date(l.fim) : new Date(l.ini);

                    for (let d = new Date(start); d <= end; d.setDate(d.getDate()+1)) {
                        const iso = d.toISOString().slice(0,10);

                        const form = new FormData();
                        form.append('_token', window.CSRF_TOKEN);
                        form.append('data', iso);
                        form.append('hora_entrada', '');
                        form.append('hora_saida',   '');
                        form.append('tipo', (l.tipo || '').toUpperCase());
                        form.append('observacoes', (l.obs || '').toUpperCase());

                        const resp = await fetch(urlStore, { method: 'POST', body: form });
                        if (!resp.ok) {
                            const txt = await resp.text();
                            alert('FALHA AO LANÇAR UMA OCORRÊNCIA:\n' + txt);
                            this.submitting = false;
                            return;
                        }
                    }
                }

                this.lancamentos = [];
                alert('LANÇAMENTOS SALVOS NA FREQUÊNCIA.');
                // recarrega para refletir no resumo de intervalos
                try { location.reload(); } catch(e){}
            } catch(e) {
                alert('ERRO INESPERADO AO SALVAR LANÇAMENTOS.');
            }
            this.submitting = false;
        }
    }
}
</script>
