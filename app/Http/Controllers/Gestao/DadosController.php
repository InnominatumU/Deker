<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class DadosController extends Controller
{
    /**
     * Nome da tabela padronizado (não usar "individuos").
     */
    private string $table = 'dados_pessoais';

    /**
     * Exibe BUSCA dentro da própria tela de EDITAR quando acessada sem {id}.
     * Agora com campos separados (cadpen, nome, mae, pai, data_nascimento, naturalidade_municipio, naturalidade_uf),
     * combinados com AND somente para os que forem preenchidos.
     */
    public function search(Request $request)
    {
        // parâmetros base da UI
        $for     = in_array($request->get('for'), ['dados','documentos','identificacao','ficha'], true) ? $request->get('for') : 'dados';
        $perPage = (int) max(10, min(50, (int) $request->get('pp', 10)));

        // Se a tabela não existir: retorna paginator vazio (compatível com ->links())
        if (!Schema::hasTable($this->table)) {
            session()->flash('warning', 'A tabela DADOS_PESSOAIS ainda não existe. Rode as migrations (php artisan migrate).');

            $emptyPaginator = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: $perPage,
                currentPage: Paginator::resolveCurrentPage() ?: 1,
                options: [
                    'path'  => url()->current(),
                    'query' => $request->query(),
                ]
            );

            return view('gestao.dadosedit', [
                'registro'   => null,
                'q'          => '',           // legado (não usado mais no form)
                'for'        => $for,
                'perPage'    => $perPage,
                'resultados' => $emptyPaginator,
            ]);
        }

        // --- Campos de busca separados ---
        $cadpen = trim((string) $request->get('cadpen', ''));
        $nome   = trim((string) $request->get('nome', ''));
        $mae    = trim((string) $request->get('mae', ''));
        $pai    = trim((string) $request->get('pai', ''));
        $dn     = trim((string) $request->get('data_nascimento', '')); // yyyy-mm-dd
        $mun    = trim((string) $request->get('naturalidade_municipio', ''));
        $uf     = mb_strtoupper(trim((string) $request->get('naturalidade_uf', '')), 'UTF-8');

        $builder = DB::table($this->table);

        // Filtros AND somente quando o campo vier preenchido
        if ($cadpen !== '') {
            $builder->where('cadpen', '=', $cadpen);
        }
        if ($nome !== '') {
            $builder->whereRaw('UPPER(nome_completo) LIKE ?', ['%'.mb_strtoupper($nome,'UTF-8').'%']);
        }
        if ($mae !== '') {
            $builder->whereRaw('UPPER(mae) LIKE ?', ['%'.mb_strtoupper($mae,'UTF-8').'%']);
        }
        if ($pai !== '') {
            $builder->whereRaw('UPPER(pai) LIKE ?', ['%'.mb_strtoupper($pai,'UTF-8').'%']);
        }
        if ($dn !== '') {
            $builder->where('data_nascimento', '=', $dn);
        }
        if ($mun !== '') {
            $builder->whereRaw('UPPER(naturalidade_municipio) LIKE ?', ['%'.mb_strtoupper($mun,'UTF-8').'%']);
        }
        if ($uf !== '') {
            $builder->where('naturalidade_uf', '=', $uf);
        }

        // Ordenação e paginação (para $resultados->links())
        $resultados = $builder
            ->orderBy('nome_completo')
            ->paginate($perPage)
            ->appends($request->query());

        // Renderiza a MESMA view de editar, mas sem $registro (modo BUSCA)
        return view('gestao.dadosedit', [
            'registro'   => null,
            'q'          => '', // legado (não usado)
            'for'        => $for,
            'perPage'    => $perPage,
            'resultados' => $resultados,
        ]);
    }

    // CREATE (form)
    public function create()
    {
        return view('gestao.dadoscreate');
    }

    // STORE
    public function store(Request $request)
    {
        if (!Schema::hasTable($this->table)) {
            return redirect()
                ->route('gestao.dados.search')
                ->with('error', 'Não foi possível salvar: a tabela DADOS_PESSOAIS não existe. Rode as migrations.');
        }

        $data = $this->validateData($request);
        $data = $this->normalize($data, $request);

        // Se a tabela tiver colunas de CADPEN, gerar automaticamente
        $this->ensureCadPenFields($data);

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = (int) DB::table($this->table)->insertGetId($data, 'id');

        // Confere persistência
        $created = DB::table($this->table)->where('id', $id)->first();
        if (!$created) {
            Log::warning('STORE: ID retornado mas não encontrado ao ler.', ['id' => $id]);
            return redirect()
                ->route('gestao.dados.create')
                ->with('error', "NÃO FOI POSSÍVEL LOCALIZAR O REGISTRO APÓS SALVAR (ID={$id}).");
        }

        Log::info('STORE OK', ['id' => $id]);

        return redirect()->route('gestao.documentos.create', ['id' => $id])
            ->with('success', 'DADOS SALVOS COM SUCESSO. Prossiga com os Documentos.');

    }

    // EDIT (form com {id})
    public function edit($id)
    {
        $id = (int) $id;
        Log::info('EDIT HIT', ['id' => $id, 'url' => request()->fullUrl()]);

        if (!Schema::hasTable($this->table)) {
            return redirect()
                ->route('gestao.dados.search')
                ->with('warning', 'A tabela DADOS_PESSOAIS ainda não existe. Rode as migrations.');
        }

        $total    = DB::table($this->table)->count();
        $registro = DB::table($this->table)->where('id', $id)->first();

        if (!$registro) {
            $ultimo = DB::table($this->table)->max('id');
            Log::warning('EDIT NOT FOUND', ['id' => $id, 'total' => $total, 'last_id' => $ultimo]);

            // Volta para BUSCA
            return redirect()
                ->route('gestao.dados.search', ['q' => request('q')]) // mantém compat com links antigos
                ->with('warning', "REGISTRO ID={$id} NÃO ENCONTRADO. TOTAL NA TABELA: {$total}.");
        }

        return view('gestao.dadosedit', compact('registro'));
    }

    // UPDATE
    public function update(Request $request, $id)
    {
        $id = (int) $id;

        if (!Schema::hasTable($this->table)) {
            return redirect()
                ->route('gestao.dados.search')
                ->with('error', 'Não foi possível atualizar: a tabela DADOS_PESSOAIS não existe. Rode as migrations.');
        }

        $exists = DB::table($this->table)->where('id', $id)->exists();

        if (!$exists) {
            Log::warning('UPDATE NOT FOUND', ['id' => $id]);
            return redirect()
                ->route('gestao.dados.search')
                ->with('error', "NÃO FOI POSSÍVEL ATUALIZAR: REGISTRO (ID={$id}) NÃO EXISTE.");
        }

        $data = $this->validateData($request);
        $data = $this->normalize($data, $request);

        // Não alterar cadpen/cadpen_number em update
        unset($data['cadpen'], $data['cadpen_number']);

        $data['updated_at'] = now();

        DB::table($this->table)->where('id', $id)->update($data);

        Log::info('UPDATE OK', ['id' => $id]);

        return redirect("/gestao/dados/{$id}/edit")
            ->with('success', 'DADOS ATUALIZADOS COM SUCESSO.');
    }

    // ===== Helpers =====

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nome_completo'          => ['required','string','max:200'],
            'nome_social'            => ['nullable','string','max:200'],
            'alcunha'                => ['nullable','string','max:200'],
            'data_nascimento'        => ['nullable','date','before_or_equal:today'],
            'genero_sexo'            => ['nullable','string','max:30'],

            'mae'                    => ['required','string','max:200'],
            'pai'                    => ['nullable','string','max:200'],

            'nacionalidade'          => ['nullable','string','max:60'],
            'naturalidade_uf'        => ['nullable','string','size:2'],
            'naturalidade_municipio' => ['nullable','string','max:120'],

            'estado_civil'           => ['nullable','string','max:40'],
            'escolaridade_nivel'     => ['nullable','string','max:60'],
            'escolaridade_situacao'  => ['nullable','string','max:60'],
            'profissao'              => ['nullable','string','max:120'],

            'end_logradouro'         => ['nullable','string','max:200'],
            'end_numero'             => ['nullable','string','max:20'],
            'end_complemento'        => ['nullable','string','max:120'],
            'end_bairro'             => ['nullable','string','max:120'],
            'end_municipio'          => ['nullable','string','max:120'],
            'end_uf'                 => ['nullable','string','size:2'],
            'end_cep'                => ['nullable','string','max:15'],

            'telefone_principal'     => ['nullable','string','max:30'],
            'telefones_adicionais'   => ['nullable'], // texto JSON
            'email'                  => ['nullable','email','max:190'],

            'obito'                  => ['nullable'], // '0' ou '1'
            'data_obito'             => ['nullable','date'],
            'observacoes'            => ['nullable','string'],
        ]);
    }

    private function normalize(array $data, Request $request): array
    {
        // checkbox ÓBITO → booleano
        $data['obito'] = isset($data['obito']) && (string)$data['obito'] === '1';

        // Telefones adicionais: vazio → null
        if (array_key_exists('telefones_adicionais', $data) && $data['telefones_adicionais'] === '') {
            $data['telefones_adicionais'] = null;
        }

        // CAIXA ALTA (exceto e-mail/JSON e datas)
        foreach ([
            'nome_completo','nome_social','alcunha','genero_sexo',
            'mae','pai','nacionalidade','naturalidade_uf','naturalidade_municipio',
            'estado_civil','escolaridade_nivel','escolaridade_situacao','profissao',
            'end_logradouro','end_numero','end_complemento','end_bairro','end_municipio','end_uf','end_cep',
            'observacoes'
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && is_string($data[$field])) {
                $data[$field] = mb_strtoupper($data[$field], 'UTF-8');
            }
        }

        return $data;
    }

    /**
 * Se a tabela tiver colunas de CADPEN, gera valores coerentes no CREATE:
 * - cadpen_number: MAX()+1
 * - cadpen: YYYY-%06d  (ex.: 2025-000123)
 */
private function ensureCadPenFields(array &$data): void
{
    $hasCadPenStr = Schema::hasColumn($this->table, 'cadpen');
    $hasCadPenNum = Schema::hasColumn($this->table, 'cadpen_number');

    if (!$hasCadPenStr && !$hasCadPenNum) {
        return; // tabela sem essas colunas
    }

    // Sequencial interno
    if ($hasCadPenNum && empty($data['cadpen_number'])) {
        $maxRow = DB::table($this->table)->selectRaw('MAX(cadpen_number) as mx')->first();
        $next = (int) (($maxRow->mx ?? 0) + 1);
        if ($next < 1) $next = 1;
        $data['cadpen_number'] = $next;
    }

    // Formato externo exibido: ANO-000000
    if ($hasCadPenStr && empty($data['cadpen'])) {
        $num = (int)($data['cadpen_number'] ?? 1);
        $data['cadpen'] = sprintf('%s-%06d', now()->format('Y'), $num);
    }
}

}
