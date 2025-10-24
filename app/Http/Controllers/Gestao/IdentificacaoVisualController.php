<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Throwable;

class IdentificacaoVisualController extends Controller
{
    /** relação 1:1 com dados_pessoais */
    private string $pessoasTable = 'dados_pessoais';
    private string $table        = 'identificacoes_visuais';

    /** Log helper enxuto */
    private function dbg(string $tag, array $ctx = []): void
    {
        Log::info("[IDENT-VISUAL] {$tag}", $ctx);
    }

    /**
     * GET /gestao/identificacao/visual/create?id={idPessoa}
     * Continuidade do fluxo: carrega a pessoa por ID e mescla com a identificação (se houver).
     */
    public function create(Request $request)
    {
        $id = (int) $request->input('id', 0);

        $pessoa = null;
        $ident  = null;

        if (Schema::hasTable($this->pessoasTable) && $id > 0) {
            $pessoa = DB::table($this->pessoasTable)->where('id', $id)->first();
        }
        if (Schema::hasTable($this->table) && $id > 0) {
            $ident = DB::table($this->table)->where('dados_pessoais_id', $id)->first();
        }

        $registro = null;
        if ($pessoa) {
            $arr = (array) $pessoa;
            if ($ident) {
                $d = (array) $ident;
                $d['identificacao_id'] = $ident->id ?? null;
                unset($d['id']); // evita sobrescrever id da pessoa
                $arr = array_merge($arr, $d);
            }
            $registro = (object) $arr;
        }

        // Decodifica JSONs para pré-popular (se existir registro)
        if ($registro) {
            foreach (['sinais_compostos','biometria_json'] as $jf) {
                if (isset($registro->$jf) && is_string($registro->$jf)) {
                    $dec = json_decode($registro->$jf, true);
                    $registro->$jf = is_array($dec) ? $dec : [];
                }
            }
        }

        return view('gestao.identificacaovisualcreate', compact('registro'));
    }

    /**
     * POST /gestao/identificacao/visual
     * Upsert por pessoa + avança o wizard para “ficha”.
     */
    public function store(Request $request)
    {
        $this->dbg('STORE:hit', [
            'method' => $request->method(),
            'has_files' => [
                'le' => (bool)$request->file('foto_le'),
                'fr' => (bool)$request->file('foto_frontal'),
                'ld' => (bool)$request->file('foto_ld'),
            ],
        ]);

        $payload = $request->validate([
            'id'               => ['required','integer','min:1'], // id da PESSOA (dados_pessoais.id)
            'cadpen'           => ['required','string','max:50'],
            'etnia'            => ['nullable','string','max:50'],

            // listas em JSON (string ou array)
            'sinais_compostos' => ['nullable'],
            'biometrias'       => ['nullable'],

            // fotos 3x4
            'foto_le'          => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'foto_frontal'     => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'foto_ld'          => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],

            'observacoes'      => ['nullable','string','max:5000'],
        ]);

        $pessoaId = (int) $payload['id'];
        unset($payload['id']);

        // Garantias de existência
        if (!Schema::hasTable($this->pessoasTable) || !DB::table($this->pessoasTable)->where('id', $pessoaId)->exists()) {
            return redirect()->route('gestao.dados.search')->with('error', 'Registro inválido para salvar Identificação.');
        }
        if (!Schema::hasTable($this->table)) {
            return back()->with('error','A tabela de Identificação ainda não existe. Rode as migrations.');
        }

        // Normalizações (CAIXA ALTA)
        $toUpper = static fn($v) => is_string($v) ? mb_strtoupper($v, 'UTF-8') : $v;
        foreach (['cadpen','etnia','observacoes'] as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] !== null) {
                $payload[$k] = $toUpper($payload[$k]);
            }
        }

        // Parse robusto dos JSONs
        $sinaisArr = $this->normalizeSinais($payload['sinais_compostos'] ?? null, $jsonOkSinais);
        if ($jsonOkSinais === false) {
            return back()->withInput()->with('error','O JSON de sinais_compostos é inválido.');
        }

        $bioArr = $this->normalizeBio($payload['biometrias'] ?? null, $jsonOkBio);
        if ($jsonOkBio === false) {
            return back()->withInput()->with('error','O JSON de biometrias é inválido.');
        }

        // Unicidade do CADPEN (pertence só a este dados_pessoais_id)
        $cadpenEmUsoPorOutro = DB::table($this->table)
            ->where('cadpen', $payload['cadpen'])
            ->where('dados_pessoais_id', '<>', $pessoaId)
            ->exists();
        if ($cadpenEmUsoPorOutro) {
            return back()->withInput()->with('error', 'CADPEN já está em uso por outro registro de identificação.');
        }

        // Uploads (pasta por CADPEN)
        $urls = $this->handleUploads($request, $payload['cadpen']);
        $this->dbg('STORE:uploads', ['urls_keys' => array_keys($urls)]);

        // Upsert + avanço wizard (com logs e captura de falhas)
        try {
            DB::beginTransaction();

            $now = now();

            // base comum
            $rowBase = [
                'cadpen'           => $payload['cadpen'] ?? null,
                'etnia'            => $payload['etnia'] ?? null,
                'observacoes'      => $payload['observacoes'] ?? null,
                'sinais_compostos' => json_encode($sinaisArr, JSON_UNESCAPED_UNICODE),
                'biometria_json'   => json_encode($bioArr, JSON_UNESCAPED_UNICODE),
                'updated_at'       => $now,
            ];

            $exists = DB::table($this->table)->where('dados_pessoais_id', $pessoaId)->exists();
            $this->dbg('STORE:exists', ['pessoaId' => $pessoaId, 'exists' => $exists]);

            if ($exists) {
                // UPDATE: aplica fotos só se houver upload novo
                $rowUpdate = $rowBase;
                foreach (['foto_le_url','foto_frontal_url','foto_ld_url'] as $k) {
                    if (isset($urls[$k])) {
                        $rowUpdate[$k] = $urls[$k];
                    }
                }
                $affected = DB::table($this->table)->where('dados_pessoais_id', $pessoaId)->update($rowUpdate);
                $this->dbg('STORE:update', ['affected' => $affected, 'rowUpdate_has_fotos' => array_key_exists('foto_frontal_url', $rowUpdate)]);
            } else {
                // INSERT
                $rowInsert = $rowBase + [
                    'dados_pessoais_id' => $pessoaId,
                    'created_at'        => $now,
                    'foto_le_url'       => $urls['foto_le_url']      ?? null,
                    'foto_frontal_url'  => $urls['foto_frontal_url'] ?? null,
                    'foto_ld_url'       => $urls['foto_ld_url']      ?? null,
                ];
                DB::table($this->table)->insert($rowInsert);
                $this->dbg('STORE:insert', ['cadpen' => $rowInsert['cadpen']]);
            }

            // Atualiza wizard na pessoa apenas se as colunas existirem
            $wizUpdate = [];
            if (Schema::hasColumn($this->pessoasTable, 'wizard_stage')) $wizUpdate['wizard_stage'] = 'ficha';
            if (Schema::hasColumn($this->pessoasTable, 'updated_at'))   $wizUpdate['updated_at']   = $now;
            $affWizard = 0;
            if ($wizUpdate) {
                $affWizard = DB::table($this->pessoasTable)->where('id', $pessoaId)->update($wizUpdate);
            }
            $this->dbg('STORE:wizard', ['affWizard' => $affWizard, 'cols' => array_keys($wizUpdate)]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->dbg('STORE:exception', [
                'msg' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace_head' => substr($e->getTraceAsString(), 0, 800),
            ]);
            return back()->withInput()->with('error', 'Falha ao salvar a Identificação (verifique o log).');
        }

        // Redirecionamento seguro
        if (Route::has('gestao.ficha.individuo.create')) {
            return redirect()->route('gestao.ficha.individuo.create', ['id' => $pessoaId])
                ->with('success','IDENTIFICAÇÃO SALVA. Avançando para a Ficha do Indivíduo.');
        }
        if (Route::has('gestao.identificacao.visual.edit')) {
            return redirect()->route('gestao.identificacao.visual.edit', $pessoaId)
                ->with('success','IDENTIFICAÇÃO SALVA.');
        }
        return redirect()->route('inicio')->with('success','IDENTIFICAÇÃO SALVA.');
    }

    /**
     * GET /gestao/identificacao/visual/{id}/edit   (id = id da PESSOA)
     */
    public function edit($id)
    {
        $id = (int) $id;

        if (!Schema::hasTable($this->pessoasTable)) {
            return redirect()->route('gestao.dados.search')->with('warning','A tabela DADOS_PESSOAIS ainda não existe. Rode as migrations.');
        }

        $pessoa = DB::table($this->pessoasTable)->where('id', $id)->first();
        abort_if(!$pessoa, 404);

        $ident = Schema::hasTable($this->table)
            ? DB::table($this->table)->where('dados_pessoais_id', $id)->first()
            : null;

        $arr = (array) $pessoa;
        if ($ident) {
            $d = (array) $ident;
            $d['identificacao_id'] = $ident->id ?? null;
            unset($d['id']);
            $arr = array_merge($arr, $d);
        }
        $registro = (object) $arr;

        // Decodifica JSONs para a view
        foreach (['sinais_compostos','biometria_json'] as $jf) {
            if (isset($registro->$jf) && is_string($registro->$jf)) {
                $dec = json_decode($registro->$jf, true);
                $registro->$jf = is_array($dec) ? $dec : [];
            }
        }

        return view('gestao.identificacaovisualedit', compact('registro'));
    }

    /**
     * PUT /gestao/identificacao/visual/{id}   (id = id da PESSOA)
     * Agora é UPSERT: se não existir linha para a pessoa, insere.
     */
    public function update(Request $request, $id)
    {
        $this->dbg('UPDATE:hit', [
            'id' => (int)$id,
            'method' => $request->method(),
            'has_files' => [
                'le' => (bool)$request->file('foto_le'),
                'fr' => (bool)$request->file('foto_frontal'),
                'ld' => (bool)$request->file('foto_ld'),
            ],
        ]);

        $id = (int) $id;

        if (!Schema::hasTable($this->pessoasTable) || !DB::table($this->pessoasTable)->where('id',$id)->exists()) {
            abort(404);
        }
        if (!Schema::hasTable($this->table)) {
            return back()->with('error','A tabela de Identificação ainda não existe. Rode as migrations.');
        }

        $payload = $request->validate([
            'cadpen'           => ['required','string','max:50'],
            'etnia'            => ['nullable','string','max:50'],

            'sinais_compostos' => ['nullable'],
            'biometrias'       => ['nullable'],

            'foto_le'          => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'foto_frontal'     => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'foto_ld'          => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],

            'observacoes'      => ['nullable','string','max:5000'],
        ]);

        // Normalizações
        $toUpper = static fn($v) => is_string($v) ? mb_strtoupper($v, 'UTF-8') : $v;
        foreach (['cadpen','etnia','observacoes'] as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] !== null) {
                $payload[$k] = $toUpper($payload[$k]);
            }
        }

        // Parse JSONs
        $sinaisArr = $this->normalizeSinais($payload['sinais_compostos'] ?? null, $jsonOkSinais);
        if ($jsonOkSinais === false) {
            return back()->withInput()->with('error','O JSON de sinais_compostos é inválido.');
        }
        $bioArr = $this->normalizeBio($payload['biometrias'] ?? null, $jsonOkBio);
        if ($jsonOkBio === false) {
            return back()->withInput()->with('error','O JSON de biometrias é inválido.');
        }

        // Unicidade do CADPEN no update (não pode pertencer a outro dados_pessoais_id)
        $cadpenEmUsoPorOutro = DB::table($this->table)
            ->where('cadpen', $payload['cadpen'])
            ->where('dados_pessoais_id', '<>', $id)
            ->exists();
        if ($cadpenEmUsoPorOutro) {
            return back()->withInput()->with('error', 'CADPEN já está em uso por outro registro de identificação.');
        }

        // Uploads
        $urls = $this->handleUploads($request, $payload['cadpen']);

        // Monta atualização
        $row = [
            'cadpen'           => $payload['cadpen'] ?? null,
            'etnia'            => $payload['etnia'] ?? null,
            'observacoes'      => $payload['observacoes'] ?? null,

            'sinais_compostos' => json_encode($sinaisArr, JSON_UNESCAPED_UNICODE),
            'biometria_json'   => json_encode($bioArr, JSON_UNESCAPED_UNICODE),
            'updated_at'       => now(),
        ];
        // aplica URLs novas (se houver upload)
        foreach (['foto_le_url','foto_frontal_url','foto_ld_url'] as $k) {
            if (isset($urls[$k])) {
                $row[$k] = $urls[$k];
            }
        }

        try {
            // Tenta atualizar
            $affected = DB::table($this->table)->where('dados_pessoais_id', $id)->update($row);

            if ($affected === 0) {
                // Se não atualizou, checa se realmente NÃO existe
                $exists = DB::table($this->table)->where('dados_pessoais_id', $id)->exists();

                if (!$exists) {
                    // Faz INSERT (UPSERT)
                    $now = now();
                    $rowInsert = $row + [
                        'dados_pessoais_id' => $id,
                        'created_at'        => $now,
                        // garante que as chaves de fotos existam, mesmo se não houve upload
                        'foto_le_url'       => $row['foto_le_url']      ?? ($urls['foto_le_url']      ?? null),
                        'foto_frontal_url'  => $row['foto_frontal_url'] ?? ($urls['foto_frontal_url'] ?? null),
                        'foto_ld_url'       => $row['foto_ld_url']      ?? ($urls['foto_ld_url']      ?? null),
                    ];
                    DB::table($this->table)->insert($rowInsert);
                    $this->dbg('UPDATE:insert_on_missing', ['id' => $id, 'cadpen' => $rowInsert['cadpen']]);
                    $affected = 1; // para fins de log abaixo
                } else {
                    // Existia, mas não houve mudança real (mesmos valores)
                    $this->dbg('UPDATE:no_change', ['id' => $id]);
                }
            }

            $this->dbg('UPDATE:done', ['affected' => $affected]);
        } catch (Throwable $e) {
            $this->dbg('UPDATE:exception', [
                'msg' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace_head' => substr($e->getTraceAsString(), 0, 800),
            ]);
            return back()->withInput()->with('error', 'Falha ao atualizar a Identificação (verifique o log).');
        }

        // Atualiza wizard na pessoa apenas se as colunas existirem
        $wizUpdate = [];
        if (Schema::hasColumn($this->pessoasTable, 'wizard_stage')) $wizUpdate['wizard_stage'] = 'ficha';
        if (Schema::hasColumn($this->pessoasTable, 'updated_at'))   $wizUpdate['updated_at']   = now();
        if ($wizUpdate) {
            DB::table($this->pessoasTable)->where('id', $id)->update($wizUpdate);
        }

        // Redirecionamento seguro
        if (Route::has('gestao.ficha.individuo.create')) {
            return redirect()->route('gestao.ficha.individuo.create', ['id' => $id])
                ->with('success','IDENTIFICAÇÃO ATUALIZADA. Avançando para a Ficha do Indivíduo.');
        }
        if (Route::has('gestao.identificacao.visual.edit')) {
            return redirect()->route('gestao.identificacao.visual.edit', $id)
                ->with('success','IDENTIFICAÇÃO ATUALIZADA.');
        }
        return redirect()->route('inicio')->with('success','IDENTIFICAÇÃO ATUALIZADA.');
    }

    /** uploads para storage/app/public/identificacao/{CADPEN}/... */
    private function handleUploads(Request $request, string $cadpen): array
    {
        // Nome de pasta: somente A-Z 0-9 _ -
        $safeCad = preg_replace('/[^A-Z0-9_\-]/','_', mb_strtoupper($cadpen,'UTF-8'));
        $folder  = "identificacao/{$safeCad}";

        $out = [];
        foreach ([
            'foto_le'      => 'foto_le_url',
            'foto_frontal' => 'foto_frontal_url',
            'foto_ld'      => 'foto_ld_url',
        ] as $input => $dest) {
            if ($request->hasFile($input)) {
                // Salva no DISCO public, sem prefixar "public/"
                $path = $request->file($input)->store($folder, 'public');
                $out[$dest] = url(Storage::url($path)); // /storage/identificacao/{CADPEN}/arquivo.ext
            }
        }
        return $out;
    }

    /**
     * Normaliza sinais_compostos (array de objetos {local,tipo,descricao}) em CAIXA ALTA.
     * Retorna $array; define $jsonOk=false quando string JSON inválida.
     */
    private function normalizeSinais(string|array|null $val, ?bool &$jsonOk = null): array
    {
        $jsonOk = true;
        if ($val === null || $val === '') return [];
        if (is_string($val)) {
            $tmp = json_decode($val, true);
            if (json_last_error() !== JSON_ERROR_NONE) { $jsonOk = false; return []; }
            $val = $tmp;
        }
        if (!is_array($val)) return [];
        $out = [];
        foreach ($val as $it) {
            $out[] = [
                'local'     => isset($it['local']) ? mb_strtoupper((string)$it['local'],'UTF-8') : null,
                'tipo'      => isset($it['tipo']) ? mb_strtoupper((string)$it['tipo'],'UTF-8') : null,
                'descricao' => isset($it['descricao']) ? mb_strtoupper((string)$it['descricao'],'UTF-8') : null,
            ];
        }
        return $out;
    }

    /**
     * Normaliza biometrias (array de objetos {dedo,imagem,status}).
     * Retorna $array; define $jsonOk=false quando string JSON inválida.
     */
    private function normalizeBio(string|array|null $val, ?bool &$jsonOk = null): array
    {
        $jsonOk = true;
        if ($val === null || $val === '') return [];
        if (is_string($val)) {
            $tmp = json_decode($val, true);
            if (json_last_error() !== JSON_ERROR_NONE) { $jsonOk = false; return []; }
            $val = $tmp;
        }
        return is_array($val) ? $val : [];
    }
}
