<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentosController extends Controller
{
    /**
     * Tabela de pessoas e tabela de documentos (relação 1:1).
     */
    private string $pessoasTable = 'dados_pessoais';
    private string $table = 'documentos';

    /**
     * GET /gestao/documentos/create?id={id}
     * Exibe o formulário de criação/edição a partir do id da PESSOA.
     */
    public function create(Request $request)
    {
        $id = (int) $request->input('id', 0);

        $pessoa = null;
        $docs   = null;

        if (Schema::hasTable($this->pessoasTable) && $id > 0) {
            $pessoa = DB::table($this->pessoasTable)->where('id', $id)->first();
        }
        if (Schema::hasTable($this->table) && $id > 0) {
            $docs = DB::table($this->table)->where('dados_pessoais_id', $id)->first();
        }

        // Mescla: mantém id da PESSOA em 'id' e expõe 'documentos_id' do registro de documentos (se houver).
        $registro = null;
        if ($pessoa) {
            $registroArr = (array) $pessoa;
            if ($docs) {
                $d = (array) $docs;
                $d['documentos_id'] = $docs->id ?? null; // preserva id da linha de documentos
                unset($d['id']); // evita sobrescrever o id da pessoa
                $registroArr = array_merge($registroArr, $d);
            }
            $registro = (object) $registroArr;
        }

        return view('gestao.documentoscreate', compact('registro'));
    }

    /**
     * POST /gestao/documentos
     * Salva (upsert) os documentos de uma PESSOA e avança o wizard para "identificacao".
     */
    public function store(Request $request)
    {
        $ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];

        $payload = $request->validate([
            'id'                => ['required','integer','min:1'], // id da PESSOA
            'cpf'               => ['nullable','string','max:20'],
            'rg_numero'         => ['nullable','string','max:30'],
            'rg_orgao_emissor'  => ['nullable','string','max:30'],
            'rg_uf'             => ['nullable','in:'.implode(',',$ufs)],
            'prontuario'        => ['nullable','string','max:120'],
            'outros_documentos' => ['nullable'], // JSON string ou array
            'observacoes'       => ['nullable','string'],
        ]);

        $pessoaId = (int) $payload['id'];
        unset($payload['id']);

        // Garantias de schema e existência
        if (!Schema::hasTable($this->pessoasTable) || !DB::table($this->pessoasTable)->where('id', $pessoaId)->exists()) {
            return redirect()->route('gestao.dados.search')
                ->with('error', 'Registro inválido para salvar Documentos.');
        }
        if (!Schema::hasTable($this->table)) {
            return back()->withInput()->with('error', 'A tabela de Documentos ainda não existe. Rode as migrations.');
        }

        // Normalizações básicas (trim)
        $trim = static fn($v) => is_string($v) ? trim($v) : $v;
        foreach (['rg_numero','rg_orgao_emissor','rg_uf','prontuario','observacoes','cpf'] as $k) {
            if (array_key_exists($k, $payload)) $payload[$k] = $trim($payload[$k]);
        }

        // CPF: dígitos e tamanho 11
        if (array_key_exists('cpf', $payload) && $payload['cpf'] !== null) {
            $digits = preg_replace('/\D+/', '', $payload['cpf']);
            $payload['cpf'] = $digits !== '' ? $digits : null;
            if ($payload['cpf'] !== null && strlen($payload['cpf']) !== 11) {
                return back()->withInput()->with('error', 'CPF deve conter 11 dígitos numéricos.');
            }
        }

        // Caixa alta (não mexe no JSON)
        foreach (['rg_numero','rg_orgao_emissor','rg_uf','prontuario','observacoes'] as $f) {
            if (array_key_exists($f, $payload) && $payload[$f] !== null) {
                $payload[$f] = mb_strtoupper($payload[$f], 'UTF-8');
            }
        }

        // JSON robusto para "outros_documentos"
        if (array_key_exists('outros_documentos', $payload) && $payload['outros_documentos'] !== null) {
            if (is_array($payload['outros_documentos'])) {
                $payload['outros_documentos'] = json_encode($payload['outros_documentos'], JSON_UNESCAPED_UNICODE);
            } elseif (is_string($payload['outros_documentos'])) {
                $str = trim($payload['outros_documentos']);
                if ($str === '') {
                    $payload['outros_documentos'] = null;
                } else {
                    $decoded = json_decode($str, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return back()->withInput()->with('error', 'O campo "Outros documentos" deve ser um JSON válido.');
                    }
                    $payload['outros_documentos'] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }
            }
        }

        // Checagem amigável de CPF duplicado (antes do UNIQUE do banco)
        if (!empty($payload['cpf'])) {
            $cpfEmUso = DB::table($this->table)
                ->where('cpf', $payload['cpf'])
                ->where('dados_pessoais_id', '<>', $pessoaId)
                ->exists();
            if ($cpfEmUso) {
                return back()->withInput()->with('error', 'CPF já está cadastrado em outro registro.');
            }
        }

        // Upsert + avanço do wizard dentro de transação
        DB::transaction(function () use ($pessoaId, $payload) {
            $now = now();
            $exists = DB::table($this->table)->where('dados_pessoais_id', $pessoaId)->exists();

            if ($exists) {
                DB::table($this->table)
                    ->where('dados_pessoais_id', $pessoaId)
                    ->update(array_merge($payload, ['updated_at' => $now]));
            } else {
                DB::table($this->table)->insert(array_merge($payload, [
                    'dados_pessoais_id' => $pessoaId,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]));
            }

            DB::table($this->pessoasTable)->where('id', $pessoaId)->update([
                'wizard_stage' => 'identificacao',
                'updated_at'   => $now,
            ]);
        });

        return redirect()->route('gestao.identificacao.visual.edit', $pessoaId)
            ->with('success', 'DOCUMENTOS SALVOS COM SUCESSO.');
    }

    /**
     * GET /gestao/documentos/{id}/edit   (id = id da PESSOA)
     * Abre o formulário populado para edição.
     */
    public function edit($id)
    {
        $id = (int) $id;

        if (!Schema::hasTable($this->pessoasTable)) {
            return redirect()->route('gestao.dados.search')
                ->with('warning', 'A tabela DADOS_PESSOAIS ainda não existe. Rode as migrations.');
        }

        $pessoa = DB::table($this->pessoasTable)->where('id', $id)->first();
        abort_if(!$pessoa, 404);

        $docs = Schema::hasTable($this->table)
            ? DB::table($this->table)->where('dados_pessoais_id', $id)->first()
            : null;

        // Mescla para a view
        $registroArr = (array) $pessoa;
        if ($docs) {
            $d = (array) $docs;
            $d['documentos_id'] = $docs->id ?? null;
            unset($d['id']); // não sobrescreve id da pessoa
            $registroArr = array_merge($registroArr, $d);
        }
        $registro = (object) $registroArr;

        return view('gestao.documentosedit', compact('registro'));
    }

    /**
     * PUT /gestao/documentos/{id}   (id = id da PESSOA)
     * Atualiza os documentos e avança o wizard.
     */
    public function update(Request $request, $id)
    {
        $id = (int) $id;

        if (!Schema::hasTable($this->pessoasTable) || !DB::table($this->pessoasTable)->where('id',$id)->exists()) {
            abort(404);
        }
        if (!Schema::hasTable($this->table)) {
            return back()->with('error', 'A tabela de Documentos ainda não existe. Rode as migrations.');
        }

        $ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];

        $data = $request->validate([
            'cpf'               => ['nullable','string','max:20'],
            'rg_numero'         => ['nullable','string','max:30'],
            'rg_orgao_emissor'  => ['nullable','string','max:30'],
            'rg_uf'             => ['nullable','in:'.implode(',',$ufs)],
            'prontuario'        => ['nullable','string','max:120'],
            'outros_documentos' => ['nullable'],
            'observacoes'       => ['nullable','string'],
        ]);

        // Trim
        $trim = static fn($v) => is_string($v) ? trim($v) : $v;
        foreach (['rg_numero','rg_orgao_emissor','rg_uf','prontuario','observacoes','cpf'] as $k) {
            if (array_key_exists($k, $data)) $data[$k] = $trim($data[$k]);
        }

        // CPF
        if (array_key_exists('cpf', $data) && $data['cpf'] !== null) {
            $digits = preg_replace('/\D+/', '', $data['cpf']);
            $data['cpf'] = $digits !== '' ? $digits : null;
            if ($data['cpf'] !== null && strlen($data['cpf']) !== 11) {
                return back()->withInput()->with('error', 'CPF deve conter 11 dígitos numéricos.');
            }
        }

        // Caixa alta
        foreach (['rg_numero','rg_orgao_emissor','rg_uf','prontuario','observacoes'] as $f) {
            if (isset($data[$f]) && $data[$f] !== null) {
                $data[$f] = mb_strtoupper($data[$f], 'UTF-8');
            }
        }

        // JSON robusto
        if (array_key_exists('outros_documentos', $data) && $data['outros_documentos'] !== null) {
            if (is_array($data['outros_documentos'])) {
                $data['outros_documentos'] = json_encode($data['outros_documentos'], JSON_UNESCAPED_UNICODE);
            } elseif (is_string($data['outros_documentos'])) {
                $str = trim($data['outros_documentos']);
                if ($str === '') {
                    $data['outros_documentos'] = null;
                } else {
                    $decoded = json_decode($str, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return back()->withInput()->with('error', 'O campo "Outros documentos" deve ser um JSON válido.');
                    }
                    $data['outros_documentos'] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }
            }
        }

        // Duplicidade de CPF (amigável)
        if (!empty($data['cpf'])) {
            $cpfEmUso = DB::table($this->table)
                ->where('cpf', $data['cpf'])
                ->where('dados_pessoais_id', '<>', $id)
                ->exists();
            if ($cpfEmUso) {
                return back()->withInput()->with('error', 'CPF já está cadastrado em outro registro.');
            }
        }

        // Upsert + avanço do wizard em transação
        DB::transaction(function () use ($id, $data) {
            $now = now();
            $exists = DB::table($this->table)->where('dados_pessoais_id', $id)->exists();

            if ($exists) {
                DB::table($this->table)->where('dados_pessoais_id', $id)->update(array_merge($data, ['updated_at' => $now]));
            } else {
                DB::table($this->table)->insert(array_merge($data, [
                    'dados_pessoais_id' => $id,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]));
            }

            DB::table($this->pessoasTable)->where('id', $id)->update([
                'wizard_stage' => 'identificacao',
                'updated_at'   => $now,
            ]);
        });

        return redirect()->route('gestao.identificacao.visual.edit', $id)
            ->with('success', 'DOCUMENTOS ATUALIZADOS COM SUCESSO.');
    }
}
