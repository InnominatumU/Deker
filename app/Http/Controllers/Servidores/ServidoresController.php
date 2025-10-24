<?php

namespace App\Http\Controllers\Servidores;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ServidoresController extends Controller
{
    /** ========================== UTILITÁRIOS ========================== */
    private function onlyDigits(?string $v): string { return preg_replace('/\D+/', '', $v ?? ''); }
    private function up(?string $v): string { return mb_strtoupper($v ?? '', 'UTF-8'); }

    private function coalesceExpr(string $table, array $candidates): string
    {
        $cols = [];
        foreach ($candidates as $c) if (Schema::hasColumn($table, $c)) $cols[] = $c;
        return empty($cols) ? 'id' : 'COALESCE('.implode(',', $cols).')';
    }

    private function labelFromTable(string $table, int $id, array $candidates): ?string
    {
        if (!Schema::hasTable($table)) return null;
        $expr = $this->coalesceExpr($table, $candidates).' as nome';
        $row  = DB::table($table)->select(DB::raw($expr))->where('id',$id)->first();
        return $row && isset($row->nome) ? $this->up((string)$row->nome) : null;
    }

    private function coalesceNomeFrom(string $table, string|int|null $id): ?string
    {
        if (!$id || !Schema::hasTable($table)) return null;
        try {
            $expr = $this->coalesceExpr($table, ['descricao','nome','tipo_semana','rotulo','titulo','title']).' as nome';
            $row  = DB::table($table)->select(DB::raw($expr))->where('id', $id)->first();
            return $row ? $this->up((string)$row->nome) : null;
        } catch (\Throwable $e) { return null; }
    }

    private function parseCargaSemanalToInt(?string $txt): ?int
    {
        if (!$txt) return null;
        $t = $this->up(trim($txt));

        if (preg_match('/(\d+)\s*[Xx]\s*(\d+)/', $t, $m)) {
            $on  = (int)$m[1]; $off = (int)$m[2];
            $avg = ($on + $off) > 0 ? ($on / ($on + $off)) * 168.0 : 0.0;
            return max(1, (int) round($avg));
        }
        if (preg_match('/(\d{1,3})/', $t, $m)) {
            $h = (int)$m[1];
            return ($h >= 1 && $h <= 80) ? $h : null;
        }
        return null;
    }

    private function derivarPlantao(?string $plantaoSIMNAO, ?string $tipoPlantao): string
    {
        $p = $this->up($plantaoSIMNAO ?? '');
        if (in_array($p, ['SIM','NAO'], true)) return $p;

        $tp = $this->up(trim((string)$tipoPlantao));
        if ($tp === '' || $tp === 'DIARISTA' || $tp === 'ADMINISTRATIVO') return 'NAO';
        return 'SIM';
    }

    /** ---------- Tags em observações (sem migrations) ---------- */
    private function obsSetTag(string $obs, string $key, string $value): string
    {
        $line = $key.': '.$value;
        if ($obs === '') return $line;
        if (preg_match('/(^|\R)'.preg_quote($key, '/').':\s*.*/i', $obs)) {
            return preg_replace('/(^|\R)'.preg_quote($key, '/').':\s*.*/i', "\n".$line, $obs, 1);
        }
        return rtrim($obs)."\n".$line;
    }
    private function obsGetTag(?string $obs, string $key): ?string
    {
        if (!$obs) return null;
        if (preg_match('/'.preg_quote($key, '/').':\s*(.+)/i', $obs, $m)) return $this->up(trim($m[1]));
        return null;
    }

    /** ---------- Plantão: persistir rótulo sem migration ---------- */
    private function mergeObsTipoPlantao(string $obs, string $label): string
    {
        return $this->obsSetTag($obs, 'TIPO_PLANTAO', $label);
    }
    private function extractObsTipoPlantao(?string $obs): ?string
    {
        return $this->obsGetTag($obs, 'TIPO_PLANTAO');
    }
    private function setTipoPlantaoOnPayload(array &$payload, array $data, ?string $obsBase = null): void
    {
        $tipoPlantaoTxt = $this->up($data['tipo_plantao'] ?? '');
        if ($tipoPlantaoTxt === '') return;

        if (Schema::hasColumn('servidores','tipo_plantao')) {
            $payload['tipo_plantao'] = $tipoPlantaoTxt;
            return;
        }
        if (Schema::hasColumn('servidores','tipo_plantao_id') && Schema::hasTable('tipos_plantao')) {
            $row = DB::table('tipos_plantao')
                ->select('id')
                ->whereRaw('UPPER('.$this->coalesceExpr('tipos_plantao',['descricao','nome','rotulo']).') = ?', [$tipoPlantaoTxt])
                ->first();
            if ($row) { $payload['tipo_plantao_id'] = (int)$row->id; return; }
        }
        $payload['observacoes'] = $this->mergeObsTipoPlantao((string)($payload['observacoes'] ?? $obsBase ?? ''), $tipoPlantaoTxt);
    }

    /** ---------- Feriados: leitura opcional de tabela ---------- */
    private function feriadosBetween(Carbon $ini, Carbon $fim, ?string $uf = null, ?string $munIbge = null): array
    {
        $byDate = [];
        if (!Schema::hasTable('feriados')) return $byDate;

        $hasData   = Schema::hasColumn('feriados','data') || Schema::hasColumn('feriados','dia');
        $colData   = Schema::hasColumn('feriados','data') ? 'data' : (Schema::hasColumn('feriados','dia') ? 'dia' : null);
        if (!$hasData || !$colData) return $byDate;

        $colNome   = Schema::hasColumn('feriados','nome') ? 'nome' : (Schema::hasColumn('feriados','titulo') ? 'titulo' : (Schema::hasColumn('feriados','descricao') ? 'descricao' : null));
        $colAbr    = Schema::hasColumn('feriados','abrangencia') ? 'abrangencia' : (Schema::hasColumn('feriados','tipo') ? 'tipo' : null);
        $colUF     = Schema::hasColumn('feriados','uf') ? 'uf' : null;
        $colMun    = Schema::hasColumn('feriados','municipio_ibge') ? 'municipio_ibge' : (Schema::hasColumn('feriados','codigo_municipio') ? 'codigo_municipio' : null);
        $colAtivo  = Schema::hasColumn('feriados','ativo') ? 'ativo' : null;

        $q = DB::table('feriados')->select([$colData.' as data'])
            ->when($colNome, fn($qq) => $qq->addSelect($colNome.' as nome'))
            ->when($colAbr,  fn($qq) => $qq->addSelect($colAbr.' as abrangencia'))
            ->when($colUF,   fn($qq) => $qq->addSelect($colUF.' as uf'))
            ->when($colMun,  fn($qq) => $qq->addSelect($colMun.' as municipio_ibge'))
            ->whereBetween($colData, [$ini->toDateString(), $fim->toDateString()]);

        if ($colAtivo) $q->where($colAtivo, 1);

        $rows = $q->get();
        foreach ($rows as $r) {
            $d = (string)$r->data;
            $byDate[$d][] = [
                'nome'        => $r->nome ?? 'FERIADO',
                'abrangencia' => $this->up($r->abrangencia ?? 'F'),
                'uf'          => $r->uf ?? null,
                'municipio'   => $r->municipio_ibge ?? null,
            ];
        }
        return $byDate;
    }

    /** ========================== VALIDAÇÃO ========================== */
    private function validarServidor(Request $request, ?int $ignoreId = null): array
    {
        $id = $ignoreId;

        return $request->validate([
            'unidade_id'            => ['nullable','integer'],

            'nome'                  => ['required','string','max:150'],
            'cpf'                   => ['required','string','max:14', Rule::unique('servidores','cpf')->ignore($id)],
            'matricula'             => ['required','string','max:40', Rule::unique('servidores','matricula')->ignore($id)],

            'cargo_funcao'          => ['nullable','string','max:120'],
            'carga_horaria'         => ['nullable','integer','min:1','max:80'],
            'plantao'               => ['nullable', Rule::in(['SIM','NAO'])],

            'cargo'                 => ['nullable','string','max:120'],
            'funcao'                => ['nullable','string','max:120'],
            'cargo_id'              => ['nullable','integer'],
            'funcao_id'             => ['nullable','integer'],
            'carga_horaria_id'      => ['nullable','integer'],
            'carga_horaria_semanal' => ['nullable','string','max:50'],

            'tipo_plantao'          => ['nullable','string','max:100'],
            'tipo_plantao_id'       => ['nullable','integer'],

            'ativo'                 => ['nullable', Rule::in(['0','1',0,1,true,false])],
            'motivo_inatividade'    => ['nullable','string','max:1000'],
            'observacoes'           => ['nullable','string','max:1000'],
        ], [], ['carga_horaria' => 'carga horária']);
    }

    /** ========================== BUSCA / LISTAGEM ========================== */
    public function search(Request $request)
    {
        $q = trim((string)$request->query('q', ''));

        if ($q === '') {
            return view('servidores.servidoresedit', [
                'encontrados'    => collect(),
                'transferencias' => collect(),
            ]);
        }

        $qLike     = '%'.mb_strtoupper($q, 'UTF-8').'%';
        $cpfDigits = $this->onlyDigits($q);

        $rows = DB::table('servidores')
            ->select('id','nome','matricula','cpf')
            ->where(function($w) use ($qLike, $cpfDigits) {
                $w->whereRaw('UPPER(nome) LIKE ?', [$qLike])
                  ->orWhereRaw('UPPER(matricula) LIKE ?', [$qLike]);
                if ($cpfDigits !== '') $w->orWhere('cpf','like','%'.$cpfDigits.'%');
            })
            ->orderBy('nome')
            ->limit(50)
            ->get();

        if ($rows->count() === 0) {
            return view('servidores.servidoresedit', [
                'encontrados'    => collect(),
                'transferencias' => collect(),
            ])->with('error','Nenhum servidor encontrado para a busca informada.');
        }

        return view('servidores.servidoresedit', [
            'encontrados'    => $rows,
            'transferencias' => collect(),
        ]);
    }

    public function index(Request $request)
    {
        return redirect()->route('servidores.search');
    }

    /** ========================== CREATE/STORE ========================== */
    public function create()
    {
        $cargos = collect();
        if (Schema::hasTable('cargos')) {
            $expr = $this->coalesceExpr('cargos',['nome','descricao','rotulo']).' as nome';
            $cargos = DB::table('cargos')->select('id', DB::raw($expr))->orderBy('nome')->get();
        }
        $funcoes = collect();
        if (Schema::hasTable('funcoes')) {
            $expr = $this->coalesceExpr('funcoes',['nome','descricao','rotulo']).' as nome';
            $funcoes = DB::table('funcoes')->select('id', DB::raw($expr))->orderBy('nome')->get();
        }

        return view('servidores.servidorescreate', compact('cargos','funcoes'));
    }

    public function store(Request $request)
    {
        $data = $this->validarServidor($request);

        // Normalizações
        $data['cpf']       = $this->onlyDigits($data['cpf']);
        $data['nome']      = $this->up($data['nome']);
        $data['matricula'] = $this->up($data['matricula']);
        $data['ativo']     = (int) ($request->boolean('ativo') ? 1 : 0);

        // CARGO/FUNÇÃO
        $cargo  = $this->up($data['cargo']  ?? '');
        $funcao = $this->up($data['funcao'] ?? '');

        if (!empty($data['cargo_id']) && Schema::hasTable('cargos')) {
            $row = DB::table('cargos')->select('nome')->where('id',(int)$data['cargo_id'])->first();
            if ($row && !empty($row->nome)) $cargo = $this->up($row->nome);
        }
        if (!empty($data['funcao_id']) && Schema::hasTable('funcoes')) {
            $row = DB::table('funcoes')->select('nome')->where('id',(int)$data['funcao_id'])->first();
            if ($row && !empty($row->nome)) $funcao = $this->up($row->nome);
        }

        $cargo_funcao = $this->up($data['cargo_funcao'] ?? '');
        if ($cargo_funcao === '') $cargo_funcao = trim($cargo . ($cargo && $funcao ? ' / ' : '') . $funcao);

        // CARGA HORÁRIA
        $carga = $data['carga_horaria'] ?? null;
        if (!$carga && !empty($data['carga_horaria_id']) && Schema::hasTable('cargas_horarias')) {
            $expr = $this->coalesceExpr('cargas_horarias',['tipo_semana','descricao','nome']).' as nome';
            $row  = DB::table('cargas_horarias')->select(DB::raw($expr))->where('id',(int)$data['carga_horaria_id'])->first();
            if ($row && !empty($row->nome)) $carga = $this->parseCargaSemanalToInt($row->nome);
        }
        if (!$carga && !empty($data['carga_horaria_semanal'])) {
            $carga = $this->parseCargaSemanalToInt($data['carga_horaria_semanal']);
        }
        $carga = $carga ? (int)$carga : null;

        // PLANTÃO
        $tipoPlantaoTxt = $this->up($data['tipo_plantao'] ?? '');
        if ($tipoPlantaoTxt === '' && !empty($data['tipo_plantao_id']) && Schema::hasTable('tipos_plantao')) {
            $tp = $this->labelFromTable('tipos_plantao',(int)$data['tipo_plantao_id'],['descricao','nome','rotulo']);
            if ($tp) $tipoPlantaoTxt = $tp;
        }
        $plantao = $this->derivarPlantao($data['plantao'] ?? null, $tipoPlantaoTxt);

        // Unidade
        $unidadeId   = !empty($data['unidade_id']) ? (int)$data['unidade_id'] : null;
        $saveUnidade = $unidadeId && Schema::hasColumn('servidores','unidade_id');

        // Regras
        $temCargo = ($cargo !== '') || !empty($data['cargo_id']);
        if (!$temCargo)  return back()->withInput()->withErrors(['cargo' => 'Informe o CARGO.']);
        if (!$carga)     return back()->withInput()->withErrors(['carga_horaria' => 'Informe a carga horária (ex.: 40).']);
        if ($tipoPlantaoTxt === '') return back()->withInput()->withErrors(['tipo_plantao' => 'Selecione o TIPO DE PLANTÃO.']);
        if ($cargo_funcao === '')   $cargo_funcao = $cargo;

        $payload = [
            'nome'          => $data['nome'],
            'cpf'           => $data['cpf'],
            'matricula'     => $data['matricula'],
            'cargo_funcao'  => $cargo_funcao,
            'carga_horaria' => (int)$carga,
            'plantao'       => $plantao,
            'ativo'         => $data['ativo'],
            'observacoes'   => (string)($data['observacoes'] ?? ''),
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
        if ($saveUnidade) $payload['unidade_id'] = $unidadeId;

        if (Schema::hasColumn('servidores','motivo_inatividade')) {
            $payload['motivo_inatividade'] = $this->up($data['motivo_inatividade'] ?? '');
        }

        if (!empty($data['cargo_id']) && Schema::hasColumn('servidores','cargo_id')) $payload['cargo_id'] = (int)$data['cargo_id'];
        if (!empty($data['funcao_id']) && Schema::hasColumn('servidores','funcao_id')) $payload['funcao_id'] = (int)$data['funcao_id'];
        if (!empty($data['carga_horaria_id']) && Schema::hasColumn('servidores','carga_horaria_id')) $payload['carga_horaria_id'] = (int)$data['carga_horaria_id'];

        $this->setTipoPlantaoOnPayload($payload, $data);

        $id = DB::table('servidores')->insertGetId($payload);

        return redirect()->route('servidores.edit', $id)->with('success','Servidor cadastrado com sucesso.');
    }

    /** ========================== EDIT/UPDATE ========================== */
    public function edit(int $id)
    {
        $servidor = DB::table('servidores')->where('id',$id)->first();
        abort_if(!$servidor, 404);

        // Catálogos
        $cargos = collect();
        if (Schema::hasTable('cargos')) {
            $expr = $this->coalesceExpr('cargos',['nome','descricao','rotulo']).' as nome';
            $cargos = DB::table('cargos')->select('id',DB::raw($expr))->orderBy('nome')->get();
        }
        $funcoes = collect();
        if (Schema::hasTable('funcoes')) {
            $expr = $this->coalesceExpr('funcoes',['nome','descricao','rotulo']).' as nome';
            $funcoes = DB::table('funcoes')->select('id',DB::raw($expr))->orderBy('nome')->get();
        }
        $cargasHorarias = collect();
        if (Schema::hasTable('cargas_horarias')) {
            $expr = $this->coalesceExpr('cargas_horarias',['descricao','tipo_semana','nome']).' as nome';
            $cargasHorarias = DB::table('cargas_horarias')->select('id',DB::raw($expr))->orderBy('nome')->get();
        }
        $tiposPlantao = collect();
        if (Schema::hasTable('tipos_plantao')) {
            $expr = $this->coalesceExpr('tipos_plantao',['descricao','nome','rotulo']).' as nome';
            $tiposPlantao = DB::table('tipos_plantao')->select('id',DB::raw($expr))->orderBy('nome')->get();
        }

        // Unidade
        $unidadeNome = null;
        if (Schema::hasColumn('servidores','unidade_id') && !empty($servidor->unidade_id)) {
            $unidadeNome = $this->coalesceNomeFrom('unidades', (int)$servidor->unidade_id);
        }

        // Prefill
        $prefillCargo = null; $prefillFuncao = null;
        if (Schema::hasColumn('servidores','cargo_id') && !empty($servidor->cargo_id)) $prefillCargo = $this->coalesceNomeFrom('cargos',(int)$servidor->cargo_id);
        if (Schema::hasColumn('servidores','funcao_id') && !empty($servidor->funcao_id)) $prefillFuncao = $this->coalesceNomeFrom('funcoes',(int)$servidor->funcao_id);

        if (!$prefillCargo || $prefillCargo === '') {
            $cf = $this->up((string)($servidor->cargo_funcao ?? ''));
            if ($cf !== '') {
                $parts = array_map('trim', explode('/', $cf));
                if (count($parts) >= 1 && $parts[0] !== '') $prefillCargo  = $parts[0];
                if (count($parts) >= 2 && $parts[1] !== '') $prefillFuncao = $parts[1];
            }
        }

        $prefillCargaTxt = (int)($servidor->carga_horaria ?? 0);
        $prefillCargaTxt = $prefillCargaTxt > 0 ? (string)$prefillCargaTxt : null;

        $prefillTpPlantao = null;
        if (Schema::hasColumn('servidores','tipo_plantao') && !empty($servidor->tipo_plantao)) {
            $prefillTpPlantao = $this->up((string)$servidor->tipo_plantao);
        }
        if (!$prefillTpPlantao) $prefillTpPlantao = $this->extractObsTipoPlantao($servidor->observacoes ?? '');
        if (!$prefillTpPlantao && Schema::hasColumn('servidores','tipo_plantao_id') && !empty($servidor->tipo_plantao_id)) {
            $prefillTpPlantao = $this->labelFromTable('tipos_plantao',(int)$servidor->tipo_plantao_id,['descricao','nome','rotulo']);
        }
        if (!$prefillTpPlantao) $prefillTpPlantao = ($this->up($servidor->plantao ?? '') === 'NAO') ? 'DIARISTA' : null;

        $prefill = [
            'unidade_nome'        => $unidadeNome ?: '— SEM UNIDADE VINCULADA —',
            'cargo'               => $prefillCargo,
            'funcao'              => $prefillFuncao,
            'carga_horaria_texto' => $prefillCargaTxt,
            'tipo_plantao'        => $prefillTpPlantao,
        ];

        // Transferências
        $transferencias = collect();
        if (Schema::hasTable('servidores_transferencias')) {
            $transferencias = DB::table('servidores_transferencias as t')
                ->leftJoin('unidades as o','o.id','=','t.unidade_origem_id')
                ->leftJoin('unidades as d','d.id','=','t.unidade_destino_id')
                ->where('t.servidor_id', $id)
                ->orderByDesc('t.data_transferencia')
                ->select(['t.id','t.data_transferencia','t.observacoes','t.created_at','o.nome as origem_nome','d.nome as destino_nome'])
                ->paginate(10);
        }

        // Selected IDs
        $selectedCargoId = (Schema::hasColumn('servidores','cargo_id') && !empty($servidor->cargo_id)) ? (int)$servidor->cargo_id : null;
        $selectedFuncaoId = (Schema::hasColumn('servidores','funcao_id') && !empty($servidor->funcao_id)) ? (int)$servidor->funcao_id : null;
        $selectedCargaHorariaId = (Schema::hasColumn('servidores','carga_horaria_id') && !empty($servidor->carga_horaria_id)) ? (int)$servidor->carga_horaria_id : null;
        $selectedTipoPlantaoId = (Schema::hasColumn('servidores','tipo_plantao_id') && !empty($servidor->tipo_plantao_id)) ? (int)$servidor->tipo_plantao_id : null;

        if (!$selectedCargoId && !empty($prefill['cargo'])) {
            $hit = collect($cargos)->first(fn($r) => $this->up((string)$r->nome) === $this->up((string)$prefill['cargo']));
            if ($hit) $selectedCargoId = (int)$hit->id;
        }
        if (!$selectedFuncaoId && !empty($prefill['funcao'])) {
            $hit = collect($funcoes)->first(fn($r) => $this->up((string)$r->nome) === $this->up((string)$prefill['funcao']));
            if ($hit) $selectedFuncaoId = (int)$hit->id;
        }
        if (!$selectedCargaHorariaId && !empty($prefill['carga_horaria_texto'])) {
            $hit = collect($cargasHorarias)->first(fn($r) => $this->up((string)$r->nome) === $this->up((string)$prefill['carga_horaria_texto']));
            if ($hit) $selectedCargaHorariaId = (int)$hit->id;
        }
        if (!$selectedTipoPlantaoId && !empty($prefill['tipo_plantao'])) {
            $hit = collect($tiposPlantao)->first(fn($r) => $this->up((string)$r->nome) === $this->up((string)$prefill['tipo_plantao']));
            if ($hit) $selectedTipoPlantaoId = (int)$hit->id;
        }

        return view('servidores.servidoresedit', compact(
            'servidor','transferencias','cargos','funcoes','cargasHorarias','tiposPlantao',
            'prefill','selectedCargoId','selectedFuncaoId','selectedCargaHorariaId','selectedTipoPlantaoId'
        ));
    }

    public function update(Request $request, int $id)
    {
        $servidor = DB::table('servidores')->where('id',$id)->first();
        abort_if(!$servidor, 404);

        $data = $this->validarServidor($request, $id);

        // Normalizações
        $data['cpf']       = $this->onlyDigits($data['cpf']);
        $data['nome']      = $this->up($data['nome']);
        $data['matricula'] = $this->up($data['matricula']);
        $data['ativo']     = (int) ($request->boolean('ativo') ? 1 : 0);

        // Cargo/Funcao
        $cargo  = $this->up($data['cargo']  ?? '');
        $funcao = $this->up($data['funcao'] ?? '');

        if (!empty($data['cargo_id']) && Schema::hasTable('cargos')) {
            $row = DB::table('cargos')->select('nome')->where('id',(int)$data['cargo_id'])->first();
            if ($row && !empty($row->nome)) $cargo = $this->up($row->nome);
        }
        if (!empty($data['funcao_id']) && Schema::hasTable('funcoes')) {
            $row = DB::table('funcoes')->select('nome')->where('id',(int)$data['funcao_id'])->first();
            if ($row && !empty($row->nome)) $funcao = $this->up($row->nome);
        }

        $cargo_funcao = $this->up($data['cargo_funcao'] ?? '');
        if ($cargo_funcao === '') $cargo_funcao = trim($cargo . ($cargo && $funcao ? ' / ' : '') . $funcao);

        // Carga horária
        $carga = $data['carga_horaria'] ?? null;
        if (!$carga && !empty($data['carga_horaria_id']) && Schema::hasTable('cargas_horarias')) {
            $expr = $this->coalesceExpr('cargas_horarias',['tipo_semana','descricao','nome']).' as nome';
            $row  = DB::table('cargas_horarias')->select(DB::raw($expr))->where('id',(int)$data['carga_horaria_id'])->first();
            if ($row && !empty($row->nome)) $carga = $this->parseCargaSemanalToInt($row->nome);
        }
        if (!$carga && !empty($data['carga_horaria_semanal'])) {
            $carga = $this->parseCargaSemanalToInt($data['carga_horaria_semanal']);
        }
        $carga = $carga ? (int)$carga : null;

        // Plantão
        $tipoPlantaoTxt = $this->up($data['tipo_plantao'] ?? '');
        if ($tipoPlantaoTxt === '' && !empty($data['tipo_plantao_id']) && Schema::hasTable('tipos_plantao')) {
            $tp = $this->labelFromTable('tipos_plantao',(int)$data['tipo_plantao_id'],['descricao','nome','rotulo']);
            if ($tp) $tipoPlantaoTxt = $tp;
        }
        $plantao = $this->derivarPlantao($data['plantao'] ?? null, $tipoPlantaoTxt);

        $unidadeId   = !empty($data['unidade_id']) ? (int)$data['unidade_id'] : null;
        $saveUnidade = $unidadeId && Schema::hasColumn('servidores','unidade_id');

        // Regras
        $temCargo = ($cargo !== '') || !empty($data['cargo_id']);
        if (!$temCargo)  return back()->withInput()->withErrors(['cargo' => 'Informe o CARGO.']);
        if (!$carga)     return back()->withInput()->withErrors(['carga_horaria' => 'Informe a carga horária (ex.: 40).']);
        if ($tipoPlantaoTxt === '') return back()->withInput()->withErrors(['tipo_plantao' => 'Selecione o TIPO DE PLANTÃO.']);
        if ($cargo_funcao === '')   $cargo_funcao = $cargo;

        $payload = [
            'nome'          => $data['nome'],
            'cpf'           => $data['cpf'],
            'matricula'     => $data['matricula'],
            'cargo_funcao'  => $cargo_funcao,
            'carga_horaria' => (int)$carga,
            'plantao'       => $plantao,
            'ativo'         => $data['ativo'],
            'observacoes'   => (string)($data['observacoes'] ?? ($servidor->observacoes ?? '')),
            'updated_at'    => now(),
        ];
        if ($saveUnidade) $payload['unidade_id'] = $unidadeId;

        if (Schema::hasColumn('servidores','motivo_inatividade')) {
            $payload['motivo_inatividade'] = $this->up($data['motivo_inatividade'] ?? '');
        }

        if (!empty($data['cargo_id']) && Schema::hasColumn('servidores','cargo_id')) $payload['cargo_id'] = (int)$data['cargo_id'];
        if (!empty($data['funcao_id']) && Schema::hasColumn('servidores','funcao_id')) $payload['funcao_id'] = (int)$data['funcao_id'];
        if (!empty($data['carga_horaria_id']) && Schema::hasColumn('servidores','carga_horaria_id')) $payload['carga_horaria_id'] = (int)$data['carga_horaria_id'];

        $this->setTipoPlantaoOnPayload($payload, $data, $servidor->observacoes ?? null);

        DB::table('servidores')->where('id',$id)->update($payload);

        return redirect()->route('servidores.edit',$id)->with('success','Cadastro atualizado com sucesso.');
    }

    /** ========================== FREQUÊNCIA ========================== */
    private function cargaPlanejadaHoras(object $servidor, Carbon $data): float
    {
        // 1) Diarista: aproxima carga diária como carga semanal / 5
        $tp = $this->up($this->extractObsTipoPlantao($servidor->observacoes ?? '') ?? ($servidor->tipo_plantao ?? ''));
        if ($tp === '' && $this->up($servidor->plantao ?? '') === 'NAO') $tp = 'DIARISTA';

        if ($tp === 'DIARISTA' || $this->up($servidor->plantao ?? '') === 'NAO') {
            $semana = (float)($servidor->carga_horaria ?? 40); // padrão
            return max(0.0, round($semana / 5.0, 2));          // seg–sex
        }

        // 2) 24x72: tenta usar âncora; se não houver, tenta inferir do 1º NORMAL
        $ancoraData = null; $ancoraHora = '07:00';
        if (Schema::hasColumn('servidores','plantao_ancora_data')) {
            $ancoraData = $servidor->plantao_ancora_data ?? null;
        }
        if (Schema::hasColumn('servidores','plantao_ancora_hora') && !empty($servidor->plantao_ancora_hora)) {
            $ancoraHora = $servidor->plantao_ancora_hora;
        }

        if (!$ancoraData) {
            $primeiro = DB::table('servidores_frequencia')
                ->where('servidor_id', $servidor->id)
                ->where('tipo', 'NORMAL')
                ->orderBy('data')
                ->first();
            if ($primeiro) {
                $ancoraData = $primeiro->data;
                if (!empty($primeiro->hora_entrada)) $ancoraHora = $primeiro->hora_entrada;
            }
        }

        if ($ancoraData) {
            $anc = Carbon::parse($ancoraData.' '.$ancoraHora)->startOfDay();
            $dias = $anc->diffInDays($data->copy()->startOfDay(), false);
            // Dia de plantão se resto == 0
            return ($dias % 4 === 0) ? 24.0 : 0.0;
        }

        // Sem âncora nem histórico: conservador
        return 0.0;
    }

    public function frequenciaShow(int $servidorId)
    {
        $servidor = DB::table('servidores')->where('id',$servidorId)->first();
        abort_if(!$servidor, 404);

        $mesStr = request()->query('mes', now()->format('Y-m'));
        [$y,$m] = explode('-', $mesStr);
        $iniMes = Carbon::createFromDate((int)$y,(int)$m,1)->startOfDay();
        $fimMes = (clone $iniMes)->endOfMonth()->endOfDay();

        $mesReg = DB::table('servidores_frequencia')
            ->where('servidor_id', $servidorId)
            ->whereBetween('data', [$iniMes->toDateString(), $fimMes->toDateString()])
            ->orderBy('data')->orderBy('hora_entrada')
            ->get()
            ->groupBy('data');

        $feriados = $this->feriadosBetween($iniMes, $fimMes, null, null);

        $ignorarFeriados = $this->up($this->obsGetTag($servidor->observacoes ?? '', 'IGNORAR_FERIADOS') ?? 'NAO') === 'SIM';

        $frequencias = DB::table('servidores_frequencia')
            ->where('servidor_id', $servidorId)
            ->whereBetween('data', [$iniMes->toDateString(), $fimMes->toDateString()])
            ->orderByDesc('data')->orderBy('hora_entrada')
            ->paginate(20)->withQueryString();

        return view('servidores.frequencia', compact(
            'servidor','frequencias','mesStr','iniMes','fimMes','mesReg','feriados','ignorarFeriados'
        ));
    }

    public function frequenciaStore(Request $request, int $servidorId)
    {
        $servidor = DB::table('servidores')->where('id', $servidorId)->first();
        abort_if(!$servidor, 404);

        // Horas só são obrigatórias quando TIPO = NORMAL
        $data = $request->validate([
            'data'          => ['required','date'],
            'tipo'          => ['required', Rule::in(['NORMAL','FOLGA','LICENCA','FERIAS','ATESTADO','OUTROS'])],
            'hora_entrada'  => [
                Rule::requiredIf(fn() => $request->input('tipo') === 'NORMAL'),
                'nullable','date_format:H:i'
            ],
            'hora_saida'    => [
                Rule::requiredIf(fn() => $request->input('tipo') === 'NORMAL'),
                'nullable','date_format:H:i'
            ],
            'observacoes'   => ['nullable','string','max:1000'],
        ]);

        $dia = Carbon::parse($data['data'])->startOfDay();
        $horas = 0.0;

        if ($data['tipo'] === 'NORMAL') {
            $ini = Carbon::parse($data['data'].' '.($data['hora_entrada'] ?? '00:00').':00');
            $fim = Carbon::parse($data['data'].' '.($data['hora_saida']   ?? '00:00').':00');
            if ($fim->lessThan($ini)) $fim = $fim->addDay();
            $horas = max(0, $fim->floatDiffInHours($ini));
        } else {
            // Tipos abonados: credita conforme plantão
            $horas = $this->cargaPlanejadaHoras($servidor, $dia);
        }

        DB::table('servidores_frequencia')->insert([
            'servidor_id'  => $servidorId,
            'data'         => $data['data'],
            'hora_entrada' => $data['tipo'] === 'NORMAL' ? ($data['hora_entrada'] ?: null) : null,
            'hora_saida'   => $data['tipo'] === 'NORMAL' ? ($data['hora_saida']   ?: null) : null,
            'horas'        => $horas,
            'tipo'         => $data['tipo'],
            'observacoes'  => $data['observacoes'] ?? null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return redirect()->route('servidores.frequencia.show', $servidorId)->with('success','Lançamento registrado.');
    }

    public function frequenciaDestroy(int $servidorId, int $freqId)
    {
        $exists = DB::table('servidores_frequencia')->where('id',$freqId)->where('servidor_id',$servidorId)->exists();
        abort_if(!$exists, 404);

        DB::table('servidores_frequencia')->where('id',$freqId)->delete();

        return redirect()->route('servidores.frequencia.show', $servidorId)->with('success','Registro excluído.');
    }

    /** ---------- Férias: gera período inteiro como registros diários ---------- */
    public function feriasStore(Request $request, int $servidorId)
    {
        $servidor = DB::table('servidores')->where('id',$servidorId)->first();
        abort_if(!$servidor, 404);

        $v = $request->validate([
            'data_inicio'        => ['required','date'],
            'dias'               => ['required','integer','min:1','max:60'],
            'tipo_ferias'        => ['required', Rule::in(['REGULAMENTARES','PREMIO','SALDO'])],
            'modo'               => ['required', Rule::in(['UTEIS','CORRIDOS'])],
            'considerar_feriados'=> ['nullable','boolean'],
            'obs'                => ['nullable','string','max:500'],
        ]);

        $ignorarServidor = $this->up($this->obsGetTag($servidor->observacoes ?? '', 'IGNORAR_FERIADOS') ?? 'NAO') === 'SIM';
        $considerarFeriados = (bool)($v['considerar_feriados'] ?? true);
        $usarFeriados = ($v['modo'] === 'UTEIS') && !$ignorarServidor && $considerarFeriados;

        $ini = Carbon::parse($v['data_inicio'])->startOfDay();
        $dias = (int)$v['dias'];

        // Busca feriados num intervalo maior para cálculo de úteis
        $scanIni = (clone $ini)->subDays(1);
        $scanFim = (clone $ini)->addDays(max(65, $dias + 10));
        $feriados = $usarFeriados ? $this->feriadosBetween($scanIni, $scanFim, null, null) : [];

        $datas = [];
        if ($v['modo'] === 'CORRIDOS') {
            $fim = (clone $ini)->addDays($dias - 1);
            for ($d = (clone $ini); $d->lte($fim); $d->addDay()) $datas[] = $d->toDateString();
        } else {
            // ÚTEIS: pula sáb/dom e feriados (quando aplicável)
            $d = (clone $ini);
            while (count($datas) < $dias) {
                $dow = (int)$d->dayOfWeekIso; // 1..7
                $isWeekend = $dow >= 6;
                $isHol = !empty($feriados[$d->toDateString()]);
                if (!$isWeekend && !$isHol) $datas[] = $d->toDateString();
                $d->addDay();
            }
        }

        $meta = sprintf(
            'FERIAS: TIPO=%s | MODO=%s | DIAS=%d | INICIO=%s | FIM=%s%s',
            $v['tipo_ferias'],
            $v['modo'],
            $dias,
            $ini->toDateString(),
            end($datas),
            $v['obs'] ? ' | '.$this->up($v['obs']) : ''
        );

        $rows = [];
        foreach ($datas as $dia) {
            $horasDia = $this->cargaPlanejadaHoras($servidor, Carbon::parse($dia));
            $rows[] = [
                'servidor_id'  => $servidorId,
                'data'         => $dia,
                'hora_entrada' => null,
                'hora_saida'   => null,
                'horas'        => $horasDia,  // ABONA conforme plantão/carga
                'tipo'         => 'FERIAS',
                'observacoes'  => $meta,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }
        DB::table('servidores_frequencia')->insert($rows);

        return redirect()->route('servidores.frequencia.show', $servidorId)
            ->with('success', 'Férias lançadas de '.$datas[0].' a '.end($datas).'.');
    }

    /** ---------- Ignorar feriados para o servidor (sem migration) ---------- */
    public function setIgnorarFeriados(Request $request, int $servidorId)
    {
        $servidor = DB::table('servidores')->where('id',$servidorId)->first();
        abort_if(!$servidor, 404);

        $val = $request->validate(['ignorar' => ['required', Rule::in(['SIM','NAO'])]]);
        $obs = (string)($servidor->observacoes ?? '');
        $obs = $this->obsSetTag($obs, 'IGNORAR_FERIADOS', $val['ignorar']);

        DB::table('servidores')->where('id',$servidorId)->update([
            'observacoes' => $obs,
            'updated_at'  => now(),
        ]);

        return redirect()->route('servidores.frequencia.show', $servidorId)->with('success','Preferência atualizada.');
    }

    /** ---------- Cadastro de feriados (opcional; só se houver tabela) ---------- */
    public function feriadosStore(Request $request)
    {
        if (!Schema::hasTable('feriados')) {
            return back()->with('error', 'Tabela "feriados" não encontrada.');
        }

        $data = $request->validate([
            'data'       => ['required','date'],
            'nome'       => ['required','string','max:150'],
            'abrangencia'=> ['required', Rule::in(['F','E','M'])],
            'uf'         => ['nullable','string','max:2'],
            'municipio'  => ['nullable','string','max:20'],
        ]);

        $payload = [];
        if (Schema::hasColumn('feriados','data'))            $payload['data'] = $data['data'];
        elseif (Schema::hasColumn('feriados','dia'))         $payload['dia']  = $data['data'];

        if (Schema::hasColumn('feriados','nome'))            $payload['nome'] = $data['nome'];
        elseif (Schema::hasColumn('feriados','titulo'))      $payload['titulo'] = $data['nome'];
        elseif (Schema::hasColumn('feriados','descricao'))   $payload['descricao'] = $data['nome'];

        if (Schema::hasColumn('feriados','abrangencia'))     $payload['abrangencia'] = $data['abrangencia'];
        elseif (Schema::hasColumn('feriados','tipo'))        $payload['tipo'] = $data['abrangencia'];

        if (!empty($data['uf']) && Schema::hasColumn('feriados','uf')) $payload['uf'] = $data['uf'];
        if (!empty($data['municipio'])) {
            if (Schema::hasColumn('feriados','municipio_ibge'))   $payload['municipio_ibge'] = $data['municipio'];
            elseif (Schema::hasColumn('feriados','codigo_municipio')) $payload['codigo_municipio'] = $data['municipio'];
        }

        if (Schema::hasColumn('feriados','ativo')) $payload['ativo'] = 1;
        if (Schema::hasColumns('feriados',['created_at','updated_at'])) {
            $payload['created_at'] = now(); $payload['updated_at'] = now();
        }

        DB::table('feriados')->insert($payload);

        return back()->with('success','Feriado cadastrado.');
    }

    /** ========================== RELATÓRIOS ========================== */
    public function relatoriosIndex(Request $request, int $servidorId)
    {
        $servidor = DB::table('servidores')->where('id', $servidorId)->first();
        abort_if(!$servidor, 404);

        $mesStr = $request->query('mes', now()->format('Y-m'));
        [$y,$m] = explode('-', $mesStr);
        $ini = Carbon::createFromDate((int)$y,(int)$m,1)->startOfDay();
        $fim = (clone $ini)->endOfMonth()->endOfDay();

        $tipo = $request->query('tipo');

        $registros = DB::table('servidores_frequencia')
            ->where('servidor_id',$servidorId)
            ->whereBetween('data', [$ini->toDateString(), $fim->toDateString()])
            ->when($tipo, fn($q) => $q->where('tipo',$tipo))
            ->orderBy('data')->orderBy('hora_entrada')
            ->paginate(50)->withQueryString();

        $todasNoMes = DB::table('servidores_frequencia')
            ->select('tipo', DB::raw('SUM(COALESCE(horas,0)) as tot'))
            ->where('servidor_id',$servidorId)
            ->whereBetween('data', [$ini->toDateString(), $fim->toDateString()])
            ->groupBy('tipo')
            ->pluck('tot','tipo');

        $horasNormais = (float)($todasNoMes['NORMAL'] ?? 0);
        $justificadas = (float)(($todasNoMes['FOLGA'] ?? 0) + ($todasNoMes['LICENCA'] ?? 0) + ($todasNoMes['FERIAS'] ?? 0) + ($todasNoMes['ATESTADO'] ?? 0));

        $cargaPrevista = (float)($servidor->carga_horaria ?? 0) * 4.33;

        $resumoMensal = [
            'carga_prevista'    => round($cargaPrevista,2),
            'horas_trabalhadas' => round($horasNormais,2),
            'horas_justificadas'=> round($justificadas,2),
            'saldo_banco'       => round($horasNormais + $justificadas - $cargaPrevista,2),
        ];

        return view('servidores.relatorio', compact('servidor','registros','resumoMensal','mesStr'));
    }

    public function relatoriosExport(Request $request, int $servidorId)
    {
        return $this->relatoriosIndex($request, $servidorId);
    }

    /** ========================== EXTRAS ALINHADOS ÀS ROTAS ========================== */
    public function destroy(int $id)
    {
        if (Schema::hasTable('servidores_frequencia')) {
            DB::table('servidores_frequencia')->where('servidor_id', $id)->delete();
        }
        if (Schema::hasTable('servidores_transferencias')) {
            DB::table('servidores_transferencias')->where('servidor_id', $id)->delete();
        }

        DB::table('servidores')->where('id', $id)->delete();

        return redirect()->route('servidores.index')->with('success', 'Servidor excluído.');
    }

    public function transferir(Request $request, int $id)
    {
        $servidor = DB::table('servidores')->where('id',$id)->first();
        abort_if(!$servidor, 404);

        $v = $request->validate([
            'unidade_destino_id' => ['required','integer'],
            'data_transferencia' => ['nullable','date'],
            'observacoes'        => ['nullable','string','max:500'],
        ]);

        $dest = (int)$v['unidade_destino_id'];
        $data = $v['data_transferencia'] ?? now()->toDateString();
        $obs  = $this->up($v['observacoes'] ?? '');

        if (Schema::hasTable('servidores_transferencias')) {
            DB::table('servidores_transferencias')->insert([
                'servidor_id'        => $id,
                'unidade_origem_id'  => Schema::hasColumn('servidores','unidade_id') ? ($servidor->unidade_id ?? null) : null,
                'unidade_destino_id' => $dest,
                'data_transferencia' => $data,
                'observacoes'        => $obs ?: null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        if (Schema::hasColumn('servidores','unidade_id')) {
            DB::table('servidores')->where('id',$id)->update([
                'unidade_id' => $dest,
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('servidores.edit',$id)->with('success','Transferência registrada.');
    }
}
