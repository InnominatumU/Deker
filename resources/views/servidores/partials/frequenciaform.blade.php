{{-- resources/views/servidores/partials/frequenciaform.blade.php --}}
@php
    /** @var object|array|null $servidor */
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    // ===== GARANTE $servidor SEGURO =====
    $servidor = isset($servidor) ? $servidor : null;
    if (is_array($servidor)) $servidor = (object) $servidor;
    if (!is_object($servidor)) $servidor = (object) [];

    // Resolve ID a partir da rota/query quando não vier no objeto
    $servidorId = data_get($servidor, 'id');
    if (!$servidorId) {
        $servidorId = request()->route('id')
            ?? request()->route('servidor')
            ?? request('servidor_id');
        if ($servidorId) {
            try {
                $row = DB::table('servidores')->where('id', $servidorId)->first();
                if ($row) $servidor = $row;
                else      $servidor = (object)['id' => (int) $servidorId];
            } catch (\Throwable $e) {
                $servidor = (object)['id' => (int) $servidorId];
            }
        }
    }
    $servidorId = data_get($servidor, 'id'); // normaliza

    // Helper de leitura
    $get = fn($k,$d=null)=>data_get($servidor,$k,$d);

    // ==== Utils (PHP) ====
    $normalize = function ($s) {
        $s = mb_strtoupper(trim((string)$s), 'UTF-8');
        $s = preg_replace('/\s+/', ' ', $s);
        try { $noAcc = @iconv('UTF-8','ASCII//TRANSLIT',$s); if ($noAcc) $s = $noAcc; } catch (\Throwable $e) {}
        return preg_replace('/[^A-Z0-9\/X\- ]/', '', $s);
    };
    $parseHHMM = function ($t) {
        $t = trim((string)$t);
        if ($t === '') return null;
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            $h = (int)$m[1]; $mi = (int)$m[2];
            if ($h>=0 && $mi>=0 && $mi<60) return sprintf('%02d:%02d', $h, $mi);
        }
        return null;
    };
    $toMin = function ($val) {
        if ($val === null || $val === '') return null;
        if (is_numeric($val)) {
            $n = (int)$val;
            return ($n <= 24 ? $n*60 : $n);
        }
        if (is_string($val) && preg_match('/^(\d{1,2}):(\d{2})$/', trim($val), $m)) {
            return ((int)$m[1])*60 + ((int)$m[2]);
        }
        return null;
    };

    // ===== PERFIL DO SERVIDOR =====
    $tipoPlantaoRaw = trim((string)$get('tipo_plantao',''));
    if ($tipoPlantaoRaw === '' && Schema::hasColumn('servidores','tipo_plantao_id') && Schema::hasTable('tipos_plantao') && $get('tipo_plantao_id')) {
        try {
            $expr = DB::raw("COALESCE(descricao,nome,rotulo) as nome");
            $tipoPlantaoRaw = (string) DB::table('tipos_plantao')
                ->select($expr)
                ->where('id',$get('tipo_plantao_id'))
                ->value('nome');
        } catch (\Throwable $e) {}
    }
    if ($tipoPlantaoRaw === '' && ($obs = (string)$get('observacoes'))) {
        if (preg_match('/TIPO_PLANTAO:\s*(.+)/i', $obs, $m)) {
            $tipoPlantaoRaw = mb_strtoupper(trim($m[1]), 'UTF-8');
        }
    }
    if ($tipoPlantaoRaw === '') {
        $plantaoSIMNAO = mb_strtoupper((string)$get('plantao',''), 'UTF-8');
        if ($plantaoSIMNAO === 'NAO')      $tipoPlantaoRaw = 'DIARISTA';
        elseif ($plantaoSIMNAO === 'SIM')  $tipoPlantaoRaw = 'PLANTONISTA';
    }
    $tipoNorm = $normalize($tipoPlantaoRaw);

    $is24x72   = preg_match('/24[\/X]?\s*72/',$tipoNorm)===1;
    $is12x36   = preg_match('/12[\/X]?\s*36/',$tipoNorm)===1;
    $is4x1     = preg_match('/(^|[^0-9])4[\/X]?\s*1($|[^0-9])/', $tipoNorm)===1;
    $isDiarista= ($tipoNorm==='DIARISTA') || preg_match('/(^|[^0-9])8(H| HORAS)?($|[^0-9])/', $tipoNorm)===1;

    // ===== CARGO/FUNÇÃO — MESMO PADRÃO DE BUSCA DO TIPO DE PLANTÃO =====
    $cargoNome  = '';
    $funcaoNome = '';

    // Direto no objeto
    $cargoNome  = trim((string)($get('cargo')  ?? $get('cargo_nome')  ?? $get('cargoTexto')  ?? $get('cargo_atual')  ?? $get('cargo_descricao')  ?? ''));
    $funcaoNome = trim((string)($get('funcao') ?? $get('funcao_nome') ?? $get('funcaoTexto') ?? $get('funcao_atual') ?? $get('funcao_descricao') ?? ''));

    // Se vierem como objetos/arrays
    $carObj = $get('cargo');
    if ($cargoNome === '' && (is_object($carObj) || is_array($carObj))) {
        $cargoNome = trim((string)(
            data_get($carObj,'nome') ??
            data_get($carObj,'titulo') ??
            data_get($carObj,'rotulo') ??
            data_get($carObj,'descricao') ??
            data_get($carObj,'nome_cargo') ?? ''
        ));
    }
    $funObj = $get('funcao');
    if ($funcaoNome === '' && (is_object($funObj) || is_array($funObj))) {
        $funcaoNome = trim((string)(
            data_get($funObj,'nome') ??
            data_get($funObj,'titulo') ??
            data_get($funObj,'rotulo') ??
            data_get($funObj,'descricao') ??
            data_get($funObj,'nome_funcao') ?? ''
        ));
    }

    // Tenta por IDs nos catálogos
    $cargoId  = $get('cargo_id')  ?? $get('id_cargo')  ?? $get('cargoId')  ?? data_get($carObj,'id');
    $funcaoId = $get('funcao_id') ?? $get('id_funcao') ?? $get('funcaoId') ?? data_get($funObj,'id');

    if ($cargoNome === '' && !empty($cargoId) && Schema::hasTable('cargos')) {
        try {
            $cargoNome = (string) DB::table('cargos')
                ->where('id', $cargoId)
                ->selectRaw('COALESCE(nome, titulo, rotulo, descricao, nome_cargo) AS n')
                ->value('n') ?? '';
        } catch (\Throwable $e) {}
    }
    if ($funcaoNome === '' && !empty($funcaoId) && Schema::hasTable('funcoes')) {
        try {
            $funcaoNome = (string) DB::table('funcoes')
                ->where('id', $funcaoId)
                ->selectRaw('COALESCE(nome, titulo, rotulo, descricao, nome_funcao) AS n')
                ->value('n') ?? '';
        } catch (\Throwable $e) {}
    }

    // Fallbacks texto na tabela servidores
    if ($cargoNome === '' && Schema::hasColumn('servidores','cargo_texto')) {
        try { $cargoNome = (string) DB::table('servidores')->where('id',$servidorId)->value('cargo_texto') ?? ''; } catch (\Throwable $e) {}
    }
    if ($funcaoNome === '' && Schema::hasColumn('servidores','funcao_texto')) {
        try { $funcaoNome = (string) DB::table('servidores')->where('id',$servidorId)->value('funcao_texto') ?? ''; } catch (\Throwable $e) {}
    }

    // === MESMO PADRÃO do tipo_plantao: tentar cargas_horarias por nome + unidade ===
    if (($cargoNome === '' || $funcaoNome === '') && Schema::hasTable('cargas_horarias')) {
        try {
            $nomePlantao = trim((string) $tipoPlantaoRaw);
            $unidadeId   = data_get($servidor,'unidade_id');

            $rowCF = DB::table('cargas_horarias')
                ->select(
                    'nome_plantao',
                    DB::raw('COALESCE(cargo, cargo_nome, nome_cargo, cargo_texto)   AS cargo_ref'),
                    DB::raw('COALESCE(funcao, funcao_nome, nome_funcao, funcao_texto) AS funcao_ref'),
                    DB::raw('NULLIF(cargo_id, 0)  AS cargo_id_ref'),
                    DB::raw('NULLIF(funcao_id, 0) AS funcao_id_ref')
                )
                ->whereNotNull('nome_plantao')
                ->whereRaw('UPPER(REPLACE(REPLACE(nome_plantao, "x","/"),"X","/")) = ?', [
                    strtoupper(str_replace(['x','X'],'/',$nomePlantao))
                ])
                ->when($unidadeId, fn($q) => $q->where(fn($qq) => $qq->whereNull('unidade_id')->orWhere('unidade_id',$unidadeId)))
                ->orderByRaw('CASE WHEN unidade_id IS NULL THEN 1 ELSE 0 END')
                ->first();

            if ($rowCF) {
                if ($cargoNome === ''  && !empty($rowCF->cargo_ref))  $cargoNome  = trim((string)$rowCF->cargo_ref);
                if ($funcaoNome === '' && !empty($rowCF->funcao_ref)) $funcaoNome = trim((string)$rowCF->funcao_ref);

                // se só IDs:
                if ($cargoNome === '' && !empty($rowCF->cargo_id_ref) && Schema::hasTable('cargos')) {
                    try {
                        $cargoNome = (string) DB::table('cargos')
                            ->where('id', $rowCF->cargo_id_ref)
                            ->selectRaw('COALESCE(nome, titulo, rotulo, descricao, nome_cargo) AS n')
                            ->value('n') ?? '';
                    } catch (\Throwable $e) {}
                }
                if ($funcaoNome === '' && !empty($rowCF->funcao_id_ref) && Schema::hasTable('funcoes')) {
                    try {
                        $funcaoNome = (string) DB::table('funcoes')
                            ->where('id', $rowCF->funcao_id_ref)
                            ->selectRaw('COALESCE(nome, titulo, rotulo, descricao, nome_funcao) AS n')
                            ->value('n') ?? '';
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {}
    }

    // ===== CARGA DIÁRIA & INÍCIO PREVISTO =====
    $cargaDiariaMin = null;
    $inicioPrevisto = null;

    foreach (['carga_diaria_min','carga_diaria','carga_diaria_horas','horas_dia','jornada_diaria'] as $field) {
        $cargaDiariaMin = $cargaDiariaMin ?? $toMin($get($field));
    }
    foreach (['hora_inicio_previsto','inicio_previsto','hora_prevista','hora_inicio','inicio'] as $field) {
        $inicioPrevisto = $inicioPrevisto ?? $parseHHMM($get($field));
    }

    if (Schema::hasTable('cargas_horarias') && ($cargaDiariaMin === null || !$inicioPrevisto)) {
        try {
            $nomePlantao = trim((string) $tipoPlantaoRaw);
            $unidadeId   = data_get($servidor,'unidade_id');

            $row = DB::table('cargas_horarias')
                ->select(
                    'nome_plantao',
                    DB::raw('COALESCE(carga_diaria_min, carga_diaria, horas_dia) as carga_ref'),
                    DB::raw('COALESCE(inicio_previsto, hora_inicio, hora_prevista, inicio) as inicio_ref')
                )
                ->whereNotNull('nome_plantao')
                ->whereRaw('UPPER(REPLACE(REPLACE(nome_plantao, "x","/"),"X","/")) = ?', [strtoupper(str_replace(['x','X'],'/',$nomePlantao))])
                ->when($unidadeId, fn($q) => $q->where(fn($qq) => $qq->whereNull('unidade_id')->orWhere('unidade_id',$unidadeId)))
                ->orderByRaw('CASE WHEN unidade_id IS NULL THEN 1 ELSE 0 END')
                ->first();

            if ($row) {
                if ($cargaDiariaMin === null) $cargaDiariaMin = $toMin($row->carga_ref);
                if (!$inicioPrevisto)         $inicioPrevisto = $parseHHMM($row->inicio_ref);
            }
        } catch (\Throwable $e) {}
    }

    if ($cargaDiariaMin === null) {
        if     ($is24x72)   $cargaDiariaMin = 24*60;
        elseif ($is12x36)   $cargaDiariaMin = 12*60;
        elseif ($is4x1)     $cargaDiariaMin = 8*60;
        else                $cargaDiariaMin = 8*60;
    }
    if (!$inicioPrevisto) {
        if ($is12x36 && stripos($tipoPlantaoRaw,'NOT') !== false) $inicioPrevisto = '19:00';
        else $inicioPrevisto = $is24x72 ? '07:00' : ($is12x36 ? '07:00' : '08:00');
    }

    // Hora sugerida de saída
    try {
        [$hh,$mm] = array_map('intval', explode(':',$inicioPrevisto));
        $out = ($hh*60 + $mm + $cargaDiariaMin) % (24*60);
        $horaSugSaida = sprintf('%02d:%02d', intdiv($out,60), $out%60);
    } catch (\Throwable $e) {
        $horaSugSaida = $is24x72 ? '07:00' : ($is12x36 ? '19:00' : '17:00');
    }
@endphp

<div x-data="freqForm()" x-init="init()" class="uppercase">
    {{-- LINHA 0: MÊS DE REFERÊNCIA --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div class="md:col-span-1">
            <label class="block text-sm font-medium">MÊS DE REFERÊNCIA</label>
            <input type="month" x-model="mesRef" @change="onMesChange()"
                   class="mt-1 w-full rounded border p-2 uppercase">
        </div>
        <div class="md:col-span-2">
            <div class="mt-6 text-gray-600 text-sm" x-text="labelMesUpper(mesRef)"></div>
        </div>
    </div>

    {{-- LINHA 1: TIPO DE PLANTÃO, CARGA DIÁRIA, INÍCIO PREVISTO --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium">TIPO DE PLANTÃO</label>
            <input value="{{ $tipoPlantaoRaw ?: '—' }}" class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
        </div>
        <div>
            <label class="block text-sm font-medium">CARGA DIÁRIA (CADASTRO)</label>
            <input value="{{ sprintf('%02d:%02d', intdiv($cargaDiariaMin,60), $cargaDiariaMin%60) }}"
                   class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
        </div>
        <div>
            <label class="block text-sm font-medium">INÍCIO PREVISTO</label>
            <input value="{{ $inicioPrevisto }}" class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
        </div>
    </div>

    {{-- LINHA 2: CARGO, FUNÇÃO, OBSERVA FERIADOS/PONTO --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium">CARGO</label>
            <input value="{{ ($cargoNome !== '' ? mb_strtoupper($cargoNome,'UTF-8') : '—') }}"
                   class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
        </div>
        <div>
            <label class="block text-sm font-medium">FUNÇÃO</label>
            <input value="{{ ($funcaoNome !== '' ? mb_strtoupper($funcaoNome,'UTF-8') : '—') }}"
                   class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
        </div>
        <div>
            <label class="block text-sm font-medium">OBSERVA FERIADOS / PONTO?</label>
            <select x-model="observaFeriadosNum" @change="rebuildPrevista()" class="mt-1 w-full rounded border p-2">
                <option value="">— SELECIONE —</option>
                <option value="1">SIM (DESCONTAR FERIADO/PONTO)</option>
                <option value="0">NÃO (IGNORAR FERIADO/PONTO)</option>
            </select>
        </div>
    </div>

    {{-- LINHA 3: RESUMO DO MÊS --}}
    <div class="mb-4 grid grid-cols-1 lg-grid-cols-4 lg:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium">CARGA PREVISTA (MÊS)</label>
            <input :value="fmtHoras(mensalPrevistaMin)" class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
        </div>
        <div>
            <label class="block text-sm font-medium">HORAS PREENCHIDAS (MÊS)</label>
            <input :value="fmtHoras(totalTrabalhadasMesMin)" class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
        </div>
        <div>
            <label class="block text-sm font-medium">EXCEDENTE (MÊS)</label>
            <input :value="fmtHoras(excedenteMesMin, true)" class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
        </div>
        <div>
            <label class="block text-sm font-medium">BANCO (MÊS)</label>
            <input :value="fmtHoras(bancoMesMin, true)" class="mt-1 w-full rounded border p-2 bg-gray-100" readonly>
            <div class="mt-2 text-[10px] text-gray-600 normal-case">
                Soma: (trabalhadas + abonos − prevista) + 50% nos dias marcados; se CONVOCADO, 50% aplica sobre todo o período do dia.
            </div>
        </div>
    </div>

    {{-- AVISOS DE OCORRÊNCIAS --}}
    <template x-if="avisos.length">
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 normal-case">
            <div class="font-semibold mb-1">ATENÇÃO</div>
            <ul class="list-disc pl-5 space-y-1">
                <template x-for="(a,i) in avisos" :key="i">
                    <li x-text="a"></li>
                </template>
            </ul>
        </div>
    </template>

    {{-- TABELA DE LANÇAMENTO DIÁRIO --}}
    <div class="rounded border overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2 w-52">DIA</th>
                    <th class="px-3 py-2">ENTRADA</th>
                    <th class="px-3 py-2">INTERVALOS</th>
                    <th class="px-3 py-2">SAÍDA</th>
                    <th class="px-3 py-2 w-28">SALDO DIA</th>
                    <th class="px-3 py-2 w-40">EXCEDENTE</th>
                    <th class="px-3 py-2 w-24">+50%</th>
                    <th class="px-3 py-2 w-28">TROCA</th>
                    <th class="px-3 py-2 w-32">CONVOCADO</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(d, idx) in dias" :key="d.iso">
                    <tr class="border-t" :class="rowClass(d)">
                        <td class="px-3 py-2 align-top">
                            <div class="font-semibold" x-text="d.labelNum"></div>
                            <div class="text-xs text-gray-600" x-text="d.labelDow"></div>
                            <div class="mt-1 space-x-1 space-y-1">
                                <template x-if="d.isFeriado">
                                    <span class="inline-block text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded border border-red-200">FERIADO</span>
                                </template>
                                <template x-if="d.isFacult">
                                    <span class="inline-block text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded border-amber-200">PONTO</span>
                                </template>
                                <template x-for="(ev, k) in d.eventos" :key="k">
                                    <span class="inline-block text-[10px] px-2 py-0.5 rounded border"
                                          :class="badgeClass(ev.tipo)"
                                          x-text="ev.tipo"></span>
                                </template>
                                <template x-if="isDiaDeTrabalhoRegime(idx)">
                                    <span class="inline-block text-[10px] bg-slate-100 text-slate-700 px-2 py-0.5 rounded border border-slate-200">PREVISTO</span>
                                </template>
                            </div>
                        </td>

                        <td class="px-3 py-2 align-top">
                            <input type="time" x-model="d.entrada" step="60" class="w-28 rounded border p-2 uppercase" placeholder="{{ $inicioPrevisto }}">
                        </td>

                        <td class="px-3 py-2">
                            <div class="space-y-1">
                                <template x-for="(iv, j) in d.intervalos" :key="j">
                                    <div class="flex items-center gap-2">
                                        <input type="time" x-model="iv.ini" step="60" class="w-24 rounded border p-2 uppercase" placeholder="11:00">
                                        <span>—</span>
                                        <input type="time" x-model="iv.fim" step="60" class="w-24 rounded border p-2 uppercase" placeholder="12:00">
                                        <button type="button" class="text-red-700 hover:underline" @click="removerIntervalo(idx,j)">REMOVER</button>
                                    </div>
                                </template>

                                <button type="button"
                                        class="mt-1 rounded border px-2 py-1 hover:bg-gray-50 uppercase font-bold leading-none text-[0.75rem]"
                                        @click="adicionarIntervalo(idx)">
                                    +INTERVALO
                                </button>
                            </div>
                        </td>

                        <td class="px-3 py-2 align-top">
                            <input type="time" x-model="d.saida" step="60" class="w-28 rounded border p-2 uppercase" placeholder="{{ $horaSugSaida }}">
                        </td>

                        <td class="px-3 py-2 align-top">
                            <div class="font-mono text-xl font-semibold"
                                 :title="abonoDiaMinPorIdx(idx) > 0 ? ('Inclui abono de ' + fmtHoras(abonoDiaMinPorIdx(idx)) ) : ''"
                                 x-text="fmtHoras(trabalhadasDiaMinPorIdx(idx) + abonoDiaMinPorIdx(idx))"></div>
                            <div class="text-[10px] text-gray-500" x-show="abonoDiaMinPorIdx(idx) > 0">incl. abono</div>
                        </td>

                        {{-- EXCEDENTE (ASSINADO) --}}
                        <td class="px-3 py-2 align-top">
                            <div class="font-mono" :class="excedenteDiaMinPorIdx(idx) < 0 ? 'text-red-700' : ''"
                                 x-text="fmtHoras(excedenteDiaMinPorIdx(idx), true)">
                            </div>
                            <div class="text-[10px] text-gray-500">
                                CARGA-BASE: <span x-text="fmtHoras(previstoBaseParaExcedente(idx))"></span>
                            </div>
                        </td>

                        {{-- +50% NO DIA --}}
                        <td class="px-3 py-2 align-top">
                            <label class="inline-flex items-center gap-2 text-xs normal-case">
                                <input type="checkbox" x-model="d.plus50" class="rounded border">
                                APLICAR
                            </label>
                        </td>

                        {{-- TROCA (INFORMATIVO) --}}
                        <td class="px-3 py-2 align-top">
                            <label class="inline-flex items-center gap-2 text-xs normal-case">
                                <input type="checkbox" x-model="d.troca" class="rounded border">
                                TROCA
                            </label>
                        </td>

                        {{-- CONVOCADO --}}
                        <td class="px-3 py-2 align-top">
                            <label class="inline-flex items-center gap-2 text-xs normal-case">
                                <input type="checkbox" x-model="d.convocado" class="rounded border">
                                CONVOCADO
                            </label>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- AÇÕES --}}
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <button type="button" class="rounded bg-blue-900 px-5 py-2 text-white hover:opacity-95 disabled:opacity-60"
                :disabled="submitting"
                @click="submeterMes()">
            <span x-show="!submitting">SALVAR</span>
            <span x-show="submitting">SALVANDO…</span>
        </button>
        <span class="text-sm text-gray-500 normal-case">
            O <strong>BANCO (MÊS)</strong> considera o previsto mensal (carga do regime) e o bônus de +50% nos dias marcados; se <strong>CONVOCADO</strong>, aplica 50% sobre todo o período.
        </span>
    </div>

    {{-- SCRIPTS --}}
    <script>
        window.CSRF_TOKEN = window.CSRF_TOKEN || '{{ csrf_token() }}';

        function freqForm() {
            return {
                // ==== ESTADO BASE ====
                tipoLabelRaw: @json($tipoPlantaoRaw),
                is24x72: {{ $is24x72 ? 'true' : 'false' }},
                is12x36: {{ $is12x36 ? 'true' : 'false' }},
                is4x1:   {{ $is4x1   ? 'true' : 'false' }},
                isDiarista: {{ $isDiarista ? 'true' : 'false' }},
                cargaDiariaMin: {{ (int)$cargaDiariaMin }},
                inicioPrevisto: '{{ $inicioPrevisto }}',

                // ==== ESTADO DINÂMICO ====
                mesRef: new Date().toISOString().slice(0,7),
                observaFeriadosNum: '', // — SELECIONE —
                dias: [],
                feriados: new Set(),
                facultativos: new Set(),
                submitting: false,

                // Âncoras dos ciclos
                _anchorIdx24: null,
                _anchorIdx12: null,
                _anchorIdx4x1: null,

                // ======= TOTAIS (COM E SEM BÔNUS) =======
                // Sem bônus (trabalhadas + abonos)
                get totalTrabalhadasBaseMesMin() {
                    let tot = 0;
                    for (let i=0; i<this.dias.length; i++) {
                        tot += this.trabalhadasDiaMinPorIdx(i) + this.abonoDiaMinPorIdx(i);
                    }
                    return tot;
                },
                // Bônus de 50% agregado do mês
                get bonusMesMin() {
                    let bonus = 0;
                    for (let i=0; i<this.dias.length; i++) {
                        const d = this.dias[i];
                        if (!d.plus50) continue;

                        const worked = this.trabalhadasDiaMinPorIdx(i) + this.abonoDiaMinPorIdx(i);
                        const excSigned = this.excedenteDiaMinPorIdx(i);

                        if (d.convocado) {
                            // CONVOCADO: 50% sobre TODO o período trabalhado
                            bonus += Math.round(worked * 0.5);
                        } else {
                            // Não convocado: 50% apenas sobre excedente positivo
                            bonus += Math.round(Math.max(0, excSigned) * 0.5);
                        }
                    }
                    return bonus;
                },
                // Horas preenchidas (MÊS) = base + bônus
                get totalTrabalhadasMesMin() {
                    return this.totalTrabalhadasBaseMesMin + this.bonusMesMin;
                },
                // Excedente (MÊS) = soma dos excedentes assinados + bônus
                get excedenteMesMin() {
                    let somaExced = 0;
                    for (let i=0; i<this.dias.length; i++) somaExced += this.excedenteDiaMinPorIdx(i);
                    return somaExced + this.bonusMesMin;
                },
                // Prevista (MÊS)
                mensalPrevistaMin: 0,
                // Banco (MÊS) = (base − prevista) + bônus
                get bancoMesMin() {
                    const baseMensal = this.totalTrabalhadasBaseMesMin - this.mensalPrevistaMin;
                    return baseMensal + this.bonusMesMin;
                },

                // ==== AVISOS (OCORRÊNCIAS) ====
                get avisos() {
                    const spans = this.buildOcorrenciaSpans();
                    return spans.map(s => this.formatAvisoSpan(s));
                },

                // ==== INIT ====
                async init() {
                    this.buildDias();
                    await this.loadCalendarioForMes();
                    this.scheduleHydrateOcorrenciasFromLancTable();
                    await this.loadHorasPreenchidasMes();
                    this.rebuildPrevista();
                },

                scheduleHydrateOcorrenciasFromLancTable() {
                    this.hydrateOcorrenciasFromLancTable();
                    setTimeout(() => this.hydrateOcorrenciasFromLancTable(), 0);
                    window.addEventListener('load', () => this.hydrateOcorrenciasFromLancTable(), { once: true });
                },

                // ==== UI HELPERS ====
                rowClass(d) {
                    if (d.isFeriado) return 'bg-red-50';
                    if (d.isFacult)  return 'bg-amber-50';
                    const dow = new Date(d.iso).getDay();
                    return (dow===0||dow===6) ? 'bg-gray-50' : '';
                },
                badgeClass(tipo) {
                    const t = (tipo || '').toUpperCase();
                    if (t === 'FOLGA')   return 'bg-emerald-100 text-emerald-700 border-emerald-200';
                    if (t === 'FERIAS' || t === 'FÉRIAS') return 'bg-sky-100 text-sky-700 border-sky-200';
                    if (t === 'LICENCA' || t === 'LICENÇA') return 'bg-indigo-100 text-indigo-700 border-indigo-200';
                    if (t === 'ATESTADO') return 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200';
                    return 'bg-slate-100 text-slate-700 border-slate-200';
                },
                labelMesUpper(v) {
                    const meses = ['JANEIRO','FEVEREIRO','MARÇO','ABRIL','MAIO','JUNHO','JULHO','AGOSTO','SETEMBRO','OUTUBRO','NOVEMBRO','DEZEMBRO'];
                    const [Y,M] = String(v||'').split('-').map(n=>parseInt(n,10));
                    if (!Y || !M) return '';
                    return `${meses[M-1]} DE ${Y}`;
                },

                // ==== MÊS ====
                async onMesChange() {
                    this.buildDias();
                    await this.loadCalendarioForMes();
                    this.scheduleHydrateOcorrenciasFromLancTable();
                    await this.loadHorasPreenchidasMes();
                    this.rebuildPrevista();
                },
                buildDias() {
                    const {y, m} = this.parseMes(this.mesRef);
                    const diasNoMes = new Date(y, m, 0).getDate();
                    const dowName = ['DOMINGO','SEGUNDA-FEIRA','TERÇA-FEIRA','QUARTA-FEIRA','QUINTA-FEIRA','SEXTA-FEIRA','SÁBADO'];

                    this.dias = [];
                    for (let d=1; d<=diasNoMes; d++) {
                        const iso = `${y}-${this.pad2(m)}-${this.pad2(d)}`;
                        const dow = new Date(iso).getDay();
                        this.dias.push({
                            iso,
                            labelNum: this.pad2(d),
                            labelDow: dowName[dow],
                            entrada: '',
                            saida: '',
                            intervalos: [],
                            eventos: [],
                            isFeriado: false,
                            isFacult:  false,
                            plus50: false,
                            troca: false,
                            convocado: false,
                        });
                    }
                    this._anchorIdx24 = null;
                    this._anchorIdx12 = null;
                    this._anchorIdx4x1 = null;
                },

                // ==== PREVISTA MENSAL ====
                get observaFeriados(){ return String(this.observaFeriadosNum) === '1'; },
                rebuildPrevista() {
                    let tot = 0;
                    for (let i=0; i<this.dias.length; i++) tot += this.previstoNoDiaMin(i);
                    this.mensalPrevistaMin = tot;
                },

                // ==== CALENDÁRIO ====
                async loadCalendarioForMes() {
                    this.feriados.clear();
                    this.facultativos.clear();

                    const {y, m} = this.parseMes(this.mesRef);
                    const base = "{{ url('/calendario') }}";
                    const url  = `${base}/${y}/${this.pad2(m)}`;

                    try {
                        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (r.ok) {
                            const j = await r.json();
                            (j.feriados || []).forEach(d => this.feriados.add(String(d)));
                            (j.facultativos || []).forEach(d => this.facultativos.add(String(d)));
                        }
                    } catch (e) {
                        console.error('Calendário fetch error', e);
                    }

                    this.dias.forEach(d => {
                        d.isFeriado = this.feriados.has(d.iso);
                        d.isFacult  = this.facultativos.has(d.iso);
                    });
                },

                // ==== INTERVALOS ====
                adicionarIntervalo(idx) { this.dias[idx].intervalos.push({ini:'', fim:''}); },
                removerIntervalo(idx, j) { this.dias[idx].intervalos.splice(j,1); },

                // ==== TRABALHADAS POR DIA ====
                trabalhadasDiaMinPorIdx(i) {
                    const d = this.dias[i];
                    const hasE = this.hasEntrada(d);
                    const hasS = this.hasSaida(d);

                    if (hasE && hasS) return this.calcSameDayMin(d);
                    if (hasE && !hasS) return this.calcUntilMidnightMin(d);
                    if (!hasE && hasS) return this.calcFromMidnightMin(d);
                    return 0;
                },
                calcSameDayMin(d) {
                    const e = this.toMin(d.entrada);
                    const s = this.toMin(d.saida);
                    if (e == null || s == null || s <= e) return 0;
                    const ivs = this.mergeIntervals(d.intervalos, e, s);
                    let worked = s - e;
                    for (const [a,b] of ivs) worked -= Math.max(0, Math.min(s, b) - Math.max(e, a));
                    return Math.max(0, worked);
                },
                calcUntilMidnightMin(d) {
                    const e = this.toMin(d.entrada);
                    if (e == null) return 0;
                    const endPrev = 24*60;
                    const ivPrev  = this.mergeIntervals(d.intervalos, e, endPrev);
                    let workedPrev = endPrev - e;
                    for (const [a,b] of ivPrev) workedPrev -= Math.max(0, Math.min(endPrev, b) - Math.max(e, a));
                    return Math.max(0, workedPrev);
                },
                calcFromMidnightMin(d) {
                    const s = this.toMin(d.saida);
                    if (s == null) return 0;
                    const ivCurr = this.mergeIntervals(d.intervalos, 0, s);
                    let workedCurr = s - 0;
                    for (const [a,b] of ivCurr) workedCurr -= Math.max(0, Math.min(s, b) - Math.max(0, a));
                    return Math.max(0, workedCurr);
                },

                // ==== PREVISTO POR DIA ====
                previstoNoDiaMin(i) {
                    const d = this.dias[i];
                    const dow = new Date(d.iso).getDay();
                    const isFeriado = (this.observaFeriados && (d.isFeriado || d.isFacult));

                    if (this.isDiarista) {
                        if (dow===0 || dow===6) return 0;
                        if (isFeriado) return 0;
                        return this.cargaDiariaMin;
                    }

                    if (!this.isDiaDeTrabalhoRegime(i)) return 0;
                    if (isFeriado) return 0;

                    return this.cargaDiariaMin;
                },

                // ==== ABONOS ====
                abonoDiaMinPorIdx(i) {
                    const d = this.dias[i];
                    const eventos = d.eventos || [];
                    if (!eventos.length) return 0;

                    const hasAbona = eventos.some(ev => this.isAbonaEvent((ev?.tipo || '').toUpperCase()));
                    if (!hasAbona) return 0;

                    if (this.isDiarista) {
                        const dow = new Date(d.iso).getDay();
                        if (dow===0 || dow===6) return 0;
                        if (this.observaFeriados && (d.isFeriado || d.isFacult)) return 0;
                        return this.cargaDiariaMin;
                    }
                    return this.isDiaDeTrabalhoRegime(i) ? this.cargaDiariaMin : 0;
                },
                isAbonaEvent(tipo) {
                    const t = (tipo || '').toUpperCase();
                    return t === 'FERIAS' || t === 'FÉRIAS' || t === 'ATESTADO' || t === 'LICENCA' || t === 'LICENÇA' || t === 'FOLGA';
                },

                // ==== BASE DO EXCEDENTE POR DIA ====
                previstoBaseParaExcedente(i) {
                    const d = this.dias[i];
                    const worked = this.trabalhadasDiaMinPorIdx(i) + this.abonoDiaMinPorIdx(i);
                    const hasRegistro = worked > 0;

                    if (d.convocado) return 0;

                    const previstoRegime = this.previstoNoDiaMin(i);
                    if (previstoRegime > 0) return previstoRegime;

                    if (hasRegistro) return this.cargaDiariaMin; // fora do regime/feriado com lançamento
                    return 0; // sem lançamento não gera excedente
                },

                // ==== EXCEDENTE POR DIA (ASSINADO) ====
                hasRegistroNoDia(i) {
                    return (this.trabalhadasDiaMinPorIdx(i) > 0) || (this.abonoDiaMinPorIdx(i) > 0);
                },
                excedenteDiaMinPorIdx(i) {
                    if (!this.hasRegistroNoDia(i)) return 0;
                    const worked = this.trabalhadasDiaMinPorIdx(i) + this.abonoDiaMinPorIdx(i);
                    const base   = this.previstoBaseParaExcedente(i);
                    return worked - base; // positivo ou negativo
                },

                // ==== CICLOS ====
                isDiaDeTrabalhoRegime(i) {
                    if (this.isDiarista) return false;
                    if (this.is24x72) return this.isDiaDePlantao24(i);
                    if (this.is12x36) return this.isDiaDePlantao12(i);
                    if (this.is4x1)   return this.isDiaDeTrabalho4x1(i);
                    return false;
                },
                getAnchorIdx24() {
                    if (this._anchorIdx24 != null) return this._anchorIdx24;
                    const idx = this.detectAnchorIndex();
                    this._anchorIdx24 = (idx != null ? idx : 0);
                    return this._anchorIdx24;
                },
                getAnchorIdx12() {
                    if (this._anchorIdx12 != null) return this._anchorIdx12;
                    const idx = this.detectAnchorIndex();
                    this._anchorIdx12 = (idx != null ? idx : 0);
                    return this._anchorIdx12;
                },
                getAnchorIdx4x1() {
                    if (this._anchorIdx4x1 != null) return this._anchorIdx4x1;
                    const idx = this.detectAnchorIndex();
                    this._anchorIdx4x1 = (idx != null ? idx : 0);
                    return this._anchorIdx4x1;
                },
                detectAnchorIndex() {
                    for (let i=0;i<this.dias.length;i++) {
                        const d = this.dias[i];
                        if (this.hasEntrada(d) || this.hasSaida(d)) return i;
                    }
                    return 0;
                },
                isDiaDePlantao24(i) {
                    const a = this.getAnchorIdx24();
                    const diff = i - a;
                    return ((diff % 4) + 4) % 4 === 0;
                },
                isDiaDePlantao12(i) {
                    const a = this.getAnchorIdx12();
                    const diff = i - a;
                    return ((diff % 2) + 2) % 2 === 0;
                },
                isDiaDeTrabalho4x1(i) {
                    const a = this.getAnchorIdx4x1();
                    const diff = i - a;
                    const k = ((diff % 5) + 5) % 5;
                    return (k >= 0 && k <= 3); // WWWW F
                },

                // ==== SUBMISSÃO ====
                async submeterMes() {
                    const url = "{{ $servidorId ? route('servidores.frequencia.store', $servidorId) : '' }}";
                    if (!url) { alert('ID DO SERVIDOR INDISPONÍVEL.'); return; }
                    this.submitting = true;
                    let enviados = 0;

                    try {
                        for (let i=0; i<this.dias.length; i++) {
                            const d = this.dias[i];
                            const hasE = this.hasEntrada(d);
                            const hasS = this.hasSaida(d);

                            if (hasE && hasS) {
                                const segs = this.segmentsSameDay(d);
                                enviados += await this.postSegments(url, d.iso, segs, d);
                            }

                            if (hasS && !hasE) {
                                const prev = this.dias[i-1];
                                if (prev && this.hasEntrada(prev) && !this.hasSaida(prev)) {
                                    const segsPrev = this.segmentsClamped(prev, this.toMin(prev.entrada), 24*60, prev.intervalos);
                                    const segsCurr = this.segmentsClamped(d, 0, this.toMin(d.saida), d.intervalos);
                                    enviados += await this.postSegments(url, prev.iso, segsPrev, prev);
                                    enviados += await this.postSegments(url, d.iso,   segsCurr, d);
                                }
                            }
                        }
                    } catch (e) {
                        console.error(e);
                        this.submitting = false;
                        alert('FALHA AO SALVAR ALGUNS REGISTROS. TENTE NOVAMENTE.');
                        return;
                    }

                    this.submitting = false;
                    if (enviados === 0) { alert('NADA PARA SALVAR.'); return; }

                    await this.loadHorasPreenchidasMes();
                    alert('REGISTROS SALVOS.');
                },
                async postSegments(url, isoDate, segs, diaRef) {
                    let count = 0;
                    for (const [iniMin, fimMin] of segs) {
                        if (fimMin <= iniMin) continue;
                        const eHH = this.minToHHMM(iniMin);
                        const sHH = this.minToHHMM(fimMin);

                        const form = new FormData();
                        form.append('_token', window.CSRF_TOKEN || '{{ csrf_token() }}');

                        form.append('data', isoDate);
                        form.append('hora_entrada', eHH);
                        form.append('hora_saida',   sHH);
                        form.append('entrada',      eHH);
                        form.append('saida',        sHH);

                        form.append('tipo', 'NORMAL');
                        const flags = [];
                        if (diaRef?.plus50)   flags.push('PLUS50');
                        if (diaRef?.troca)    flags.push('TROCA');
                        if (diaRef?.convocado)flags.push('CONVOCADO');
                        form.append('observacoes', `AUTO: DIA=${isoDate}${flags.length ? ' ['+flags.join(', ')+']' : ''}`);

                        const resp = await fetch(url, { method: 'POST', body: form });
                        if (!resp.ok) throw new Error('post failed');
                        count++;
                    }
                    return count;
                },

                // ==== REIDRATAÇÃO ====
                async loadHorasPreenchidasMes(){
                    const {y, m} = this.parseMes(this.mesRef);
                    const base = "{{ $servidorId ? url('/servidores/'.$servidorId.'/frequencia/json') : '' }}";
                    if (!base) return;
                    const url  = `${base}?mes=${y}-${this.pad2(m)}`;

                    try {
                        const r = await fetch(url, { headers:{'Accept':'application/json'} });
                        if (!r.ok) return;
                        const rows = await r.json();

                        const map = new Map();
                        const seen = new Set();
                        for (const x of rows) {
                            const iso = String(x.data);
                            const e = (x.hora_entrada||'').slice(0,5);
                            const s = (x.hora_saida||'').slice(0,5);
                            if (!e || !s) continue;
                            const dedupKey = `${iso}|${e}|${s}`;
                            if (seen.has(dedupKey)) continue;
                            seen.add(dedupKey);
                            if (!map.has(iso)) map.set(iso, []);
                            map.get(iso).push([e,s]);
                        }
                        for (const [iso, segs] of map) segs.sort((a,b)=> (a[0]+a[1]).localeCompare(b[0]+b[1]));

                        const fechaEm24 = new Map();
                        const abreDe00  = new Map();

                        for (const [iso, segs] of map) {
                            const idx24 = segs.findIndex(([e,s]) => s === '24:00');
                            if (idx24 >= 0) {
                                const [entradaPrev] = segs[idx24];
                                fechaEm24.set(iso, entradaPrev);
                                segs.splice(idx24,1);

                                const d = new Date(iso + 'T00:00:00');
                                d.setDate(d.getDate()+1);
                                const nextIso = d.toISOString().slice(0,10);

                                const nextSegs = map.get(nextIso) || [];
                                const idx00 = nextSegs.findIndex(([e,s]) => e === '00:00');
                                if (idx00 >= 0) {
                                    abreDe00.set(nextIso, nextSegs[idx00][1]);
                                    nextSegs.splice(idx00,1);
                                }
                            }
                        }

                        for (const d of this.dias) {
                            const segs = map.get(d.iso) || [];
                            d.intervalos = [];

                            if (abreDe00.has(d.iso)) {
                                d.entrada = '';
                                d.saida   = abreDe00.get(d.iso);
                            } else {
                                d.entrada = '';
                                d.saida   = '';
                            }

                            if (fechaEm24.has(d.iso)) d.entrada = fechaEm24.get(d.iso);

                            const norm = segs.filter(([a,b]) => a !== '00:00' && b !== '24:00');

                            if (!d.entrada && !d.saida && norm.length) {
                                const [e1, s1] = norm.shift();
                                d.entrada = e1; d.saida = s1;
                            }

                            for (const [a,b] of norm) d.intervalos.push({ini:a, fim:b});
                        }
                    } catch(e) {
                        console.error('JSON fetch error', e);
                    }
                },

                // ==== HIDRATA OCORRÊNCIAS DA TABELA EXISTENTE ====
                hydrateOcorrenciasFromLancTable(){
                    const tables = Array.from(document.querySelectorAll('table'));
                    let lancTable = null, idxData=-1, idxTipo=-1;

                    for (const t of tables) {
                        const ths = Array.from(t.querySelectorAll('thead th')).map(th => (th.textContent||'').trim().toUpperCase());
                        if (!ths.length) continue;
                        const hasData = ths.some(v => v.startsWith('DATA'));
                        const hasTipo = ths.includes('TIPO');
                        const hasEntrada = ths.includes('ENTRADA');
                        const hasSaida   = ths.includes('SAÍDA') || ths.includes('SAIDA');
                        if (hasData && hasTipo && hasEntrada && hasSaida) {
                            idxData = ths.findIndex(v => v.startsWith('DATA'));
                            idxTipo = ths.findIndex(v => v === 'TIPO');
                            lancTable = t;
                            break;
                        }
                    }
                    if (!lancTable || idxData<0 || idxTipo<0) return;

                    const map = {};
                    const rows = Array.from(lancTable.querySelectorAll('tbody tr'));
                    for (const tr of rows) {
                        const tds = Array.from(tr.children);
                        const dataTxt = (tds[idxData]?.textContent || '').trim();
                        const tipoTxt = ((tds[idxTipo]?.textContent || '').trim() || '').toUpperCase();
                        const iso = this.brToIso(dataTxt);
                        if (!iso || !tipoTxt) continue;
                        if (tipoTxt === 'NORMAL') continue;
                        (map[iso] ||= []).push({tipo: tipoTxt});
                    }

                    this.dias.forEach(d => d.eventos = map[d.iso] || []);

                    const container = lancTable.closest('div') || lancTable.parentElement;
                    if (container) container.style.display = 'none';
                },

                // ==== AVISOS ====
                buildOcorrenciaSpans(){
                    const spans = [];
                    if (!this.dias.length) return spans;

                    const isAbona = (evs) => (evs||[]).some(ev => this.isAbonaEvent((ev.tipo||'').toUpperCase()));
                    const getTipo = (evs) => {
                        const order = ['FERIAS','FOLGA','ATESTADO','LICENCA','FÉRIAS','LICENÇA'];
                        const types = (evs||[]).map(e => (e.tipo||'').toUpperCase());
                        for (const o of order) if (types.includes(o)) return (o==='FÉRIAS'?'FERIAS':(o==='LICENÇA'?'LICENCA':o));
                        return types[0] || null;
                    };

                    let curTipo = null, curIni = null, curFim = null;

                    for (const d of this.dias) {
                        if (isAbona(d.eventos)) {
                            const tp = getTipo(d.eventos);
                            if (curTipo === tp) curFim = d.iso;
                            else {
                                if (curTipo && curIni) spans.push({tipo: curTipo, iniIso: curIni, fimIso: curFim||curIni});
                                curTipo = tp; curIni = d.iso; curFim = d.iso;
                            }
                        } else {
                            if (curTipo && curIni) spans.push({tipo: curTipo, iniIso: curIni, fimIso: curFim||curIni});
                            curTipo = null; curIni = null; curFim = null;
                        }
                    }
                    if (curTipo && curIni) spans.push({tipo: curTipo, iniIso: curIni, fimIso: curFim||curIni});
                    return spans;
                },
                formatAvisoSpan({tipo, iniIso, fimIso}){
                    const tipoTxt = (tipo||'').toUpperCase();
                    const de = this.isoToBr(iniIso);
                    const ate = this.isoToBr(fimIso);
                    if (iniIso === fimIso) return `${this.prettyTipo(tipoTxt)} — DIA ${de}.`;
                    return `${this.prettyTipo(tipoTxt)} — DIA ${de} À ${ate}.`;
                },
                prettyTipo(t){
                    if (t==='FERIAS') return 'FÉRIAS';
                    if (t==='LICENCA') return 'LICENÇA';
                    return t;
                },

                // ===== UTILIDADES =====
                hasEntrada(d){ return this.toMin(d.entrada) != null; },
                hasSaida(d){ return this.toMin(d.saida) != null; },

                segmentsSameDay(d) {
                    const e = this.toMin(d.entrada);
                    const s = this.toMin(d.saida);
                    if (e == null || s == null || s <= e) return [];
                    return this.segmentsClamped(d, e, s, d.intervalos);
                },
                segmentsClamped(d, ini, fim, rawIntervals) {
                    const ivs = this.mergeIntervals(rawIntervals, ini, fim);
                    const segs = [];
                    let cur = ini;
                    for (const [a,b] of ivs) {
                        if (a > cur) segs.push([cur, a]);
                        cur = Math.max(cur, b);
                    }
                    if (cur < fim) segs.push([cur, fim]);
                    return segs;
                },
                mergeIntervals(raw, clampIni, clampFim) {
                    const list = [];
                    for (const it of raw || []) {
                        const a = this.toMin(it.ini);
                        const b = this.toMin(it.fim);
                        if (a == null || b == null) continue;
                        if (b <= a) continue;
                        const A = Math.max(clampIni, a), B = Math.min(clampFim, b);
                        if (B > A) list.push([A,B]);
                    }
                    list.sort((x,y)=>x[0]-y[0]);
                    const merged = [];
                    for (const iv of list) {
                        if (merged.length===0 || iv[0] > merged[merged.length-1][1]) merged.push(iv);
                        else merged[merged.length-1][1] = Math.max(merged[merged.length-1][1], iv[1]);
                    }
                    return merged;
                },

                parseMes(v) {
                    const [Y,M] = String(v || '').split('-').map(n=>parseInt(n,10));
                    const y = isNaN(Y) ? new Date().getFullYear() : Y;
                    const m = isNaN(M) ? (new Date().getMonth()+1) : M;
                    return {y, m};
                },
                toMin(hhmm) {
                    if (!hhmm || !/^\d{2}:\d{2}$/.test(hhmm)) return null;
                    const [h,m] = hhmm.split(':').map(n=>parseInt(n,10));
                    if (isNaN(h)||isNaN(m)) return null;
                    return h*60 + m;
                },
                minToHHMM(min) {
                    min = Math.max(0, Math.round(min));
                    const h = Math.floor(min/60), m = min%60;
                    return this.pad2(h) + ':' + this.pad2(m);
                },
                fmtHoras(min, signed=false) {
                    const sign = signed && min<0 ? '-' : '';
                    const mm = Math.abs(min);
                    const hhmm = this.minToHHMM(mm);
                    return sign + hhmm;
                },
                pad2(n){ return (n<10?'0':'')+n; },

                brToIso(ddmmyyyy){
                    const m = (ddmmyyyy||'').match(/(\d{2})\/(\d{2})\/(\d{4})/);
                    return m ? `${m[3]}-${m[2]}-${m[1]}` : null;
                },
                isoToBr(iso){
                    if (!iso) return '';
                    const [y,m,d] = iso.split('-').map(s=>parseInt(s,10));
                    return `${this.pad2(d)}/${this.pad2(m)}/${y}`;
                },
            };
        }
    </script>
</div>
