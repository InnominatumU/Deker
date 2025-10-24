<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VisitasController extends Controller
{
    // Tabelas base usadas nas buscas (prioriza dados_pessoais)
    private string $tbPessoas = 'dados_pessoais';
    private ?string $tbIndividuos = 'individuos'; // opcional

    // ===== Listas (ordenadas; "OUTRO" no fim das que exigem) =====
    private function tipos(): array
    {
        return [
            'SOCIAL'      => 'VISITA SOCIAL',
            'ASSISTIDA'   => 'VISITA ASSISTIDA',
            'JURIDICA'    => 'VISITA JURÍDICA (ADVOGADO)',
            'RELIGIOSA'   => 'VISITA RELIGIOSA',
            'AUTORIDADES' => 'VISITA DE AUTORIDADES',
            'PRESTADOR'   => 'PRESTADOR DE SERVIÇOS', // << NOVO
            'OUTRAS'      => 'OUTRAS VISITAS',
        ];
    }

    private function parentescos(): array
    {
        $lista = [
            'AMIGO(A)','AVÓ','AVÔ','CUNHADO(A)','ENTEADO(A)','ESPOSO(A)','FILHO(A)',
            'IRMÃO(Ã)','MADRASTA','MÃE','NAMORADO(A)','NOIVO(A)','PADRASTO','PAI',
            'PRIMO(A)','SOGRO(A)','TIO(A)','TUTOR(A)/GUARDIÃO(Ã)',
        ];
        sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
        $lista[] = 'OUTRO';
        return $lista;
    }

    private function religioes(): array
    {
        $lista = [
            'ADVENTISTA','AFRO-BRASILEIRA','AGNÓSTICO(A)','ATEU(ATEIA)','BUDISTA','CATÓLICA',
            'CANDOMBLÉ','ESPÍRITA','EVANGÉLICA','HINDU','ISLÂMICA','JUDAICA','TESTEMUNHAS DE JEOVÁ','UMBANDA',
        ];
        sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
        $lista[] = 'OUTRA';
        return $lista;
    }

    private function autoridadesCargos(): array
    {
        $lista = [
            'ALMIRANTE','BRIGADEIRO',
            'CARGOS MILITARES — OUTRO','CAPITÃO','CEL./CORONEL','COMANDANTE DA GUARDA MUNICIPAL','COMANDANTE-GERAL','CONSELHEIRO(A) DE TRIBUNAL',
            'DEFENSOR(A) PÚBLICO(A)-GERAL','DELEGADO(A)','DELEGADO(A)-GERAL','DEPUTADO(A) DISTRITAL','DEPUTADO(A) ESTADUAL','DEPUTADO(A) FEDERAL','DESEMBARGADOR(A)','DIRETOR(A) DE UNIDADE PRISIONAL',
            'EMBAIXADOR(A)','GENERAL','GOVERNADOR(A)','JUIZ(A)','MAJOR','MINISTRO(A) DE ESTADO','OFICIAL SUPERIOR',
            'PREFEITO(A)','PRESIDENTE DA CÂMARA DOS DEPUTADOS','PRESIDENTE DA REPÚBLICA','PRESIDENTE DE TRIBUNAL','PRESIDENTE DO SENADO','PROMOTOR(A) DE JUSTIÇA','PROCURADOR(A)-GERAL','PROCURADOR(A)-GERAL DE JUSTIÇA',
            'SARGENTO','SECRETÁRIO(A) DE ESTADO','SECRETÁRIO(A) MUNICIPAL','SENADOR(A)','SUBTENENTE','TENENTE',
            'VEREADOR(A)','VICE-GOVERNADOR(A)','VICE-PREFEITO(A)','VICE-PRESIDENTE DA CÂMARA DOS DEPUTADOS','VICE-PRESIDENTE DA REPÚBLICA','VICE-PRESIDENTE DE TRIBUNAL','VICE-PRESIDENTE DO SENADO',
        ];
        sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
        $lista[] = 'OUTRO';
        return $lista;
    }

    private function autoridadesOrgaos(): array
    {
        $lista = [
            'AERONÁUTICA','ASSEMBLEIA LEGISLATIVA','CÂMARA DOS DEPUTADOS','CÂMARA LEGISLATIVA','CNJ','CORPO DE BOMBEIROS',
            'DEFENSORIA PÚBLICA','EXÉRCITO','FORÇAS ARMADAS','GOVERNO ESTADUAL','GUARDA MUNICIPAL',
            'ITAMARATY','MARINHA','MINISTÉRIO PÚBLICO','PF','POLÍCIA CIVIL','POLÍCIA MILITAR',
            'PODER JUDICIÁRIO — STF','PODER JUDICIÁRIO — STJ','PODER JUDICIÁRIO — TJ',
            'PODER JUDICIÁRIO — TRF','PODER JUDICIÁRIO — TRE','PODER JUDICIÁRIO — TRT',
            'PREFEITURA','PRESIDÊNCIA DA REPÚBLICA','PRF','SENADO FEDERAL',
            'TRIBUNAIS SUPERIORES — OUTRO',
        ];
        sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
        $lista[] = 'OUTRO';
        return $lista;
    }

    private function destinos(): array
    {
        return ['INDIVIDUOS' => 'INDIVÍDUO(S)', 'UNIDADE' => 'UNIDADE'];
    }

    // ===== Rotas/views =====

    public function search(Request $request)
    {
        return $this->create($request);
    }

    public function create(Request $request)
    {
        $tipos       = $this->tipos();
        $parentescos = $this->parentescos();
        $religioes   = $this->religioes();
        $cargos      = $this->autoridadesCargos();
        $orgaos      = $this->autoridadesOrgaos();
        $destinos    = $this->destinos();

        $unidades = [];
        if (Schema::hasTable('unidades')) {
            $unidades = DB::table('unidades')->select('id','nome')->orderBy('nome')->get();
        }

        return view('gestao.visitascreate', compact(
            'tipos','parentescos','religioes','cargos','orgaos','destinos','unidades'
        ));
    }

    public function edit(int $id)
    {
        $visita = DB::table('visitas')->where('id', $id)->first();
        abort_if(!$visita, 404);

        $visitante = DB::table('visitantes')->where('id', $visita->visitante_id)->first();

        $vinculos = DB::table('visita_individuo')
            ->where('visita_id', $id)
            ->get()
            ->map(function ($row) {
                $cadpen = null;

                // Tenta em dados_pessoais primeiro
                if (Schema::hasTable($this->tbPessoas)) {
                    $p = DB::table($this->tbPessoas)->where('id', $row->individuo_id)->first();
                    if ($p) {
                        $cadpen = $p->cadpen ?? ($p->registro_interno ?? null);
                    }
                }

                // Fallback: individuos (se existir)
                if (!$cadpen && $this->tbIndividuos && Schema::hasTable($this->tbIndividuos)) {
                    $ind = DB::table($this->tbIndividuos)->where('id', $row->individuo_id)->first();
                    if ($ind) $cadpen = $ind->cadpen ?? ($ind->registro_interno ?? null);
                }

                return [
                    'individuo_id' => $row->individuo_id,
                    'cadpen'       => $cadpen,
                    'parentesco'   => $row->parentesco
                ];
            });

        $tipos       = $this->tipos();
        $parentescos = $this->parentescos();
        $religioes   = $this->religioes();
        $cargos      = $this->autoridadesCargos();
        $orgaos      = $this->autoridadesOrgaos();
        $destinos    = $this->destinos();

        $unidades = [];
        if (Schema::hasTable('unidades')) {
            $unidades = DB::table('unidades')->select('id','nome')->orderBy('nome')->get();
        }

        return view('gestao.visitasedit', compact(
            'visita','visitante','vinculos',
            'tipos','parentescos','religioes','cargos','orgaos','destinos','unidades'
        ));
    }

    // ===== Store / Update =====

    public function store(Request $request)
    {
        $tipos    = array_keys($this->tipos());
        $destinos = array_keys($this->destinos());

        $data = $request->validate(
            [
                'tipo'           => 'required|string|in:'.implode(',', $tipos),
                'destino'        => 'required|string|in:'.implode(',', $destinos),

                // Visitante
                'nome_completo'  => 'required|string|max:150',
                'cpf'            => 'nullable|string|max:20',
                'rg'             => 'nullable|string|max:30',
                'oab'            => 'nullable|string|max:30',

                // Campos condicionais
                'religiao'           => 'nullable|string|max:60',
                'autoridade_cargo'   => 'nullable|string|max:80',
                'autoridade_orgao'   => 'nullable|string|max:120',

                // PRESTADOR (livre; normalizamos no back)
                'prestador_empresa'  => 'nullable|string|max:150',
                'prestador_cnpj'     => 'nullable|string|max:20',

                'descricao_outros'   => 'nullable|string',
                'observacoes'        => 'nullable|string',

                // Unidade (sempre opcional)
                'unidade_id'         => 'nullable|integer',

                // Vinculação (CadPen/confirm/individuo_id)
                'vinculos'                    => 'array',
                'vinculos.*.cadpen'           => 'nullable|string|max:50',
                'vinculos.*.parentesco'       => 'nullable|string|max:40',
                'vinculos.*.individuo_id'     => 'nullable|integer',
                'vinculos.*.confirmed'        => 'nullable|integer|in:0,1',
            ],
            $this->messages(),
            $this->attributes()
        );

        // Regras condicionais
        if ($data['tipo'] === 'JURIDICA') {
            $request->validate(['oab' => 'required|string|max:30'], $this->messages(), $this->attributes());
        }
        if ($data['tipo'] === 'RELIGIOSA') {
            $request->validate(['religiao' => 'required|string|max:60'], $this->messages(), $this->attributes());
        }
        if ($data['tipo'] === 'AUTORIDADES') {
            $request->validate([
                'autoridade_cargo' => 'required|string|max:80',
                'autoridade_orgao' => 'required|string|max:120',
            ], $this->messages(), $this->attributes());
        }
        if ($data['tipo'] === 'PRESTADOR') {
            $request->validate([
                'prestador_empresa' => 'required|string|max:150',
                'prestador_cnpj'    => 'required|string|max:20',
            ], $this->messages(), $this->attributes());
        }
        if ($data['tipo'] === 'OUTRAS') {
            $request->validate(['descricao_outros' => 'required|string'], $this->messages(), $this->attributes());
        }
        if ($data['destino'] === 'INDIVIDUOS') {
            $request->validate(['vinculos' => 'required|array|min:1'], $this->messages(), $this->attributes());
        }

        // Força unidade_id = null quando destino = UNIDADE (não vinculamos na ficha)
        if ($data['destino'] === 'UNIDADE') {
            $data['unidade_id'] = null;
            $data['vinculos']   = [];
        }

        // Normalização de CNPJ (aceita com/sem pontuação)
        $prestadorCnpj = ($data['tipo'] === 'PRESTADOR')
            ? $this->normalizeCnpj($request->input('prestador_cnpj'))
            : null;

        $prestadorEmpresa = ($data['tipo'] === 'PRESTADOR')
            ? mb_strtoupper((string) $request->input('prestador_empresa', ''))
            : null;

        // Upsert do visitante (pelo CPF se houver)
        $visitanteId = DB::transaction(function () use ($data) {
            $visitanteId = null;
            if (!empty($data['cpf'])) {
                $existe = DB::table('visitantes')->where('cpf', $data['cpf'])->first();
                if ($existe) {
                    $visitanteId = $existe->id;
                    DB::table('visitantes')->where('id', $visitanteId)->update([
                        'nome_completo' => mb_strtoupper($data['nome_completo']),
                        'rg'            => $data['rg'] ?? null,
                        'oab'           => $data['oab'] ?? null,
                        'updated_at'    => now(),
                    ]);
                }
            }
            if (!$visitanteId) {
                $visitanteId = DB::table('visitantes')->insertGetId([
                    'nome_completo' => mb_strtoupper($data['nome_completo']),
                    'cpf'           => $data['cpf'] ?? null,
                    'rg'            => $data['rg'] ?? null,
                    'oab'           => $data['oab'] ?? null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
            return $visitanteId;
        });

        // Cria a visita
        $payload = [
            'visitante_id'      => $visitanteId,
            'tipo'              => $data['tipo'],
            'destino'           => $data['destino'],
            'unidade_id'        => ($data['destino'] === 'UNIDADE') ? null : ($data['unidade_id'] ?? null),
            'religiao'          => $data['religiao'] ?? null,
            'autoridade_cargo'  => $data['autoridade_cargo'] ?? null,
            'autoridade_orgao'  => $data['autoridade_orgao'] ?? null,
            'descricao_outros'  => $data['descricao_outros'] ?? null,
            'observacoes'       => $data['observacoes'] ?? null,
            'created_by'        => Auth::id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
        // Adiciona prestador_* somente se as colunas existirem (evita quebra)
        if (Schema::hasColumn('visitas', 'prestador_empresa')) {
            $payload['prestador_empresa'] = $prestadorEmpresa;
        }
        if (Schema::hasColumn('visitas', 'prestador_cnpj')) {
            $payload['prestador_cnpj'] = $prestadorCnpj;
        }

        $visitaId = DB::table('visitas')->insertGetId($payload);

        // Resolver vínculos (INDIVIDUOS)
        if ($data['destino'] === 'INDIVIDUOS') {
            foreach ((array) ($data['vinculos'] ?? []) as $row) {
                if (array_key_exists('confirmed', $row) && (int)($row['confirmed'] ?? 0) !== 1) {
                    continue;
                }
                $individuoId = isset($row['individuo_id']) ? (int)$row['individuo_id'] : null;
                if (!$individuoId) {
                    $cadpen = trim((string) ($row['cadpen'] ?? ''));
                    if ($cadpen !== '') {
                        $individuoId = $this->resolveIndividuoIdByCadPen($cadpen);
                    }
                }
                if ($individuoId) {
                    DB::table('visita_individuo')->insert([
                        'visita_id'    => $visitaId,
                        'individuo_id' => $individuoId,
                        'parentesco'   => $row['parentesco'] ?? null,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }
        }

        return redirect()->route('gestao.visitas.edit', ['id' => $visitaId])
            ->with('success', 'Visita cadastrada com sucesso.');
    }

    public function update(Request $request, int $id)
    {
        $visita = DB::table('visitas')->where('id', $id)->first();
        abort_if(!$visita, 404);

        // Garante tipo/destino presentes para a validação (evita undefined e falha no required)
        $request->merge([
            'tipo'    => $request->input('tipo', $visita->tipo),
            'destino' => $request->input('destino', $visita->destino),
        ]);

        // Regras iguais às do store (com mensagens e aliases)
        $this->storeLikeValidate($request);

        $destino   = $request->input('destino', $visita->destino);
        $unidadeId = $request->input('unidade_id') ?: null;
        if ($destino === 'UNIDADE') {
            $unidadeId = null; // não vinculamos unidade na ficha
        }

        // Normalização de CNPJ (aceita com/sem pontuação)
        $prestadorCnpj = ($request->input('tipo') === 'PRESTADOR')
            ? $this->normalizeCnpj($request->input('prestador_cnpj'))
            : null;

        $prestadorEmpresa = ($request->input('tipo') === 'PRESTADOR')
            ? mb_strtoupper((string) $request->input('prestador_empresa', ''))
            : null;

        // Atualiza Visitante
        $visitanteId = $visita->visitante_id;
        DB::table('visitantes')->where('id', $visitanteId)->update([
            'nome_completo' => mb_strtoupper($request->input('nome_completo', '')),
            'cpf'           => $request->input('cpf') ?: null,
            'rg'            => $request->input('rg') ?: null,
            'oab'           => $request->input('oab') ?: null,
            'updated_at'    => now(),
        ]);

        // Atualiza Visita
        $payload = [
            'tipo'              => $request->input('tipo'),
            'destino'           => $destino,
            'unidade_id'        => $unidadeId,
            'religiao'          => $request->input('religiao') ?: null,
            'autoridade_cargo'  => $request->input('autoridade_cargo') ?: null,
            'autoridade_orgao'  => $request->input('autoridade_orgao') ?: null,
            'descricao_outros'  => $request->input('descricao_outros') ?: null,
            'observacoes'       => $request->input('observacoes') ?: null,
            'updated_at'        => now(),
        ];
        if (Schema::hasColumn('visitas', 'prestador_empresa')) {
            $payload['prestador_empresa'] = $prestadorEmpresa;
        }
        if (Schema::hasColumn('visitas', 'prestador_cnpj')) {
            $payload['prestador_cnpj'] = $prestadorCnpj;
        }

        DB::table('visitas')->where('id', $id)->update($payload);

        // Sincroniza vínculos
        DB::table('visita_individuo')->where('visita_id', $id)->delete();
        if ($destino === 'INDIVIDUOS') {
            foreach ((array) $request->input('vinculos', []) as $row) {
                if (array_key_exists('confirmed', $row) && (int)($row['confirmed'] ?? 0) !== 1) {
                    continue;
                }
                $individuoId = isset($row['individuo_id']) ? (int)$row['individuo_id'] : null;
                if (!$individuoId) {
                    $cadpen = trim((string) ($row['cadpen'] ?? ''));
                    if ($cadpen !== '') {
                        $individuoId = $this->resolveIndividuoIdByCadPen($cadpen);
                    }
                }
                if ($individuoId) {
                    DB::table('visita_individuo')->insert([
                        'visita_id'    => $id,
                        'individuo_id' => $individuoId,
                        'parentesco'   => $row['parentesco'] ?? null,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }
        }

        return back()->with('success', 'Visita atualizada com sucesso.');
    }

    // ===== AJAX: buscar indivíduo por CadPen (para o botão "Buscar") =====
    public function ajaxFindIndividuo(Request $request)
    {
        $cadpen = trim((string) $request->query('cadpen', ''));
        if ($cadpen === '') {
            return response()->json(['message' => 'Informe o CadPen.'], 422);
        }

        // Prioriza dados_pessoais
        if (Schema::hasTable($this->tbPessoas)) {
            $q = DB::table($this->tbPessoas);
            $q->where('cadpen', $cadpen);
            if (Schema::hasColumn($this->tbPessoas, 'registro_interno')) {
                $q->orWhere('registro_interno', $cadpen);
            }
            $p = $q->first();
            if ($p) {
                $nome      = $p->nome ?? ($p->nome_completo ?? ($p->nome_social ?? 'Indivíduo'));
                $cadpenOut = $p->cadpen ?? (Schema::hasColumn($this->tbPessoas,'registro_interno') ? ($p->registro_interno ?? $cadpen) : $cadpen);
                return response()->json([
                    'id'     => (int) $p->id,
                    'nome'   => $nome,
                    'cadpen' => $cadpenOut,
                ]);
            }
        }

        // Fallback: tabela individuos (se existir)
        if ($this->tbIndividuos && Schema::hasTable($this->tbIndividuos)) {
            $ind = DB::table($this->tbIndividuos)
                ->where('cadpen', $cadpen)
                ->orWhere('registro_interno', $cadpen)
                ->first();

            if ($ind) {
                $nome      = $ind->nome ?? ($ind->nome_completo ?? ($ind->nome_social ?? 'Indivíduo'));
                $cadpenOut = $ind->cadpen ?? ($ind->registro_interno ?? $cadpen);
                return response()->json([
                    'id'     => (int) $ind->id,
                    'nome'   => $nome,
                    'cadpen' => $cadpenOut,
                ]);
            }
        }

        return response()->json(['message' => 'Indivíduo não encontrado para o CadPen informado.'], 404);
    }

    // ========== Helpers ==========
    private function storeLikeValidate(Request $request): void
    {
        $tipos    = array_keys($this->tipos());
        $destinos = array_keys($this->destinos());

        $request->validate(
            [
                'tipo'           => 'required|string|in:'.implode(',', $tipos),
                'destino'        => 'required|string|in:'.implode(',', $destinos),
                'nome_completo'  => 'required|string|max:150',
                'cpf'            => 'nullable|string|max:20',
                'rg'             => 'nullable|string|max:30',
                'oab'            => 'nullable|string|max:30',

                'religiao'           => 'nullable|string|max:60',
                'autoridade_cargo'   => 'nullable|string|max:80',
                'autoridade_orgao'   => 'nullable|string|max:120',

                // PRESTADOR
                'prestador_empresa'  => 'nullable|string|max:150',
                'prestador_cnpj'     => 'nullable|string|max:20',

                'descricao_outros'   => 'nullable|string',
                'observacoes'        => 'nullable|string',

                'unidade_id'         => 'nullable|integer',

                'vinculos'                    => 'array',
                'vinculos.*.cadpen'           => 'nullable|string|max:50',
                'vinculos.*.parentesco'       => 'nullable|string|max:40',
                'vinculos.*.individuo_id'     => 'nullable|integer',
                'vinculos.*.confirmed'        => 'nullable|integer|in:0,1',
            ],
            $this->messages(),
            $this->attributes()
        );

        if ($request->input('tipo') === 'JURIDICA') {
            $request->validate(['oab' => 'required|string|max:30'], $this->messages(), $this->attributes());
        }
        if ($request->input('tipo') === 'RELIGIOSA') {
            $request->validate(['religiao' => 'required|string|max:60'], $this->messages(), $this->attributes());
        }
        if ($request->input('tipo') === 'AUTORIDADES') {
            $request->validate([
                'autoridade_cargo' => 'required|string|max:80',
                'autoridade_orgao' => 'required|string|max:120',
            ], $this->messages(), $this->attributes());
        }
        if ($request->input('tipo') === 'PRESTADOR') {
            $request->validate([
                'prestador_empresa' => 'required|string|max:150',
                'prestador_cnpj'    => 'required|string|max:20',
            ], $this->messages(), $this->attributes());
        }
        if ($request->input('tipo') === 'OUTRAS') {
            $request->validate(['descricao_outros' => 'required|string'], $this->messages(), $this->attributes());
        }
        if ($request->input('destino') === 'INDIVIDUOS') {
            $request->validate(['vinculos' => 'required|array|min:1'], $this->messages(), $this->attributes());
        }
    }

    private function resolveIndividuoIdByCadPen(string $cadpen): ?int
    {
        // 1) dados_pessoais
        if (Schema::hasTable($this->tbPessoas)) {
            $q = DB::table($this->tbPessoas)->select('id')->where('cadpen', $cadpen);
            if (Schema::hasColumn($this->tbPessoas, 'registro_interno')) {
                $q->orWhere('registro_interno', $cadpen);
            }
            $p = $q->first();
            if ($p) return (int) $p->id;
        }

        // 2) fallback individuos
        if ($this->tbIndividuos && Schema::hasTable($this->tbIndividuos)) {
            $ind = DB::table($this->tbIndividuos)
                ->select('id')
                ->where('cadpen', $cadpen)
                ->orWhere('registro_interno', $cadpen)
                ->first();
            if ($ind) return (int) $ind->id;
        }

        return null;
    }

    private function normalizeCnpj(?string $v): ?string
    {
        if ($v === null) return null;
        $digits = preg_replace('/\D/', '', $v);
        if ($digits === '') return null;
        return substr($digits, 0, 14);
    }

    // Mensagens e aliases de atributos
    private function messages(): array
    {
        return [
            'required'   => 'O campo :attribute é obrigatório.',
            'in'         => 'O campo :attribute possui um valor inválido.',
            'array'      => 'O campo :attribute deve ser uma lista.',
            'integer'    => 'O campo :attribute deve ser um número inteiro.',
            'max.string' => 'O campo :attribute deve ter no máximo :max caracteres.',
            'min.array'  => 'Selecione ao menos :min item em :attribute.',
        ];
    }

    private function attributes(): array
    {
        return [
            'tipo'                  => 'Tipo da visita',
            'destino'               => 'Destino',
            'nome_completo'         => 'Nome completo',
            'cpf'                   => 'CPF',
            'rg'                    => 'RG',
            'oab'                   => 'OAB',
            'religiao'              => 'Religião',
            'autoridade_cargo'      => 'Cargo',
            'autoridade_orgao'      => 'Órgão',
            'prestador_empresa'     => 'Empresa (Prestador)',
            'prestador_cnpj'        => 'CNPJ (Prestador)',
            'descricao_outros'      => 'Descrição',
            'observacoes'           => 'Observações',
            'unidade_id'            => 'Unidade',
            'vinculos'              => 'Vínculos',
            'vinculos.*.cadpen'     => 'CadPen',
            'vinculos.*.parentesco' => 'Parentesco/Relacionamento',
            'vinculos.*.individuo_id' => 'Indivíduo',
            'vinculos.*.confirmed'    => 'Confirmação do vínculo',
        ];
    }
}
