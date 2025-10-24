<?php

namespace App\Http\Controllers\Ciclo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdmissoesController extends Controller
{
    private string $tableAdmissoes   = 'admissoes';
    private string $tablePessoas     = 'dados_pessoais';

    /** Lista simples (opcional) */
    public function index(Request $request)
    {
        if (!Schema::hasTable($this->tableAdmissoes)) {
            return back()->with('error', 'A tabela ADMISSOES ainda não existe. Rode as migrations.');
        }

        $rows = DB::table($this->tableAdmissoes)->orderByDesc('id')->limit(50)->get();
        return response()->view('ciclo.admissoes-index', ['registros' => $rows], 200);
    }

    public function store(Request $request)
    {
        // Garante tabela
        if (!Schema::hasTable($this->tableAdmissoes)) {
            return back()->with('error', 'A tabela ADMISSOES ainda não existe. Rode: php artisan migrate');
        }

        // Validação
        $payload = $request->validate([
            'unidade_id'                      => ['required','integer','min:1'],
            'tipo'                            => ['required','string','in:ADMISSAO,TRANSFERENCIA,TRANSITO,DESLIGAMENTO'],
            'cadpen'                          => ['required','string','max:50'],

            'origem'                          => ['nullable','string','max:60'],
            'origem_complemento'              => ['nullable','string','max:190'],
            'uf_origem'                       => ['nullable','string','size:2'],

            // JSON hierárquico vindo do front (LEI -> ARTIGOS -> campos)
            'enquadramentos_json'             => ['nullable','string'],

            'motivo'                          => ['nullable','string','max:60'],
            'motivo_descricao'                => ['nullable','string','max:500'],

            'destino_unidade_id'              => ['nullable','integer','min:1'],

            'desligamento_tipo'               => ['nullable','string','in:ALVARA,RELAXAMENTO_DE_PRISAO,LIBERDADE_CONDICIONAL,TRANSFERENCIA,TRANSITO,OUTROS'],
            'desligamento_destino_unidade_id' => ['nullable','integer','min:1'],
            'desligamento_observacao'         => ['nullable','string','max:2000'],
        ]);

        // CAIXA ALTA onde cabe
        $upper = static fn($v) => is_string($v) ? mb_strtoupper($v, 'UTF-8') : $v;
        foreach (['tipo','cadpen','origem','origem_complemento','uf_origem','motivo','motivo_descricao','desligamento_tipo','desligamento_observacao'] as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] !== null) {
                $payload[$k] = $upper($payload[$k]);
            }
        }

        $cadpen = (string) $payload['cadpen'];

        // Resolve pessoa por cadpen (se existir a tabela)
        $dadosPessoaisId = null;
        if (Schema::hasTable($this->tablePessoas)) {
            $dadosPessoaisId = $this->resolvePessoaIdPorCadpen($cadpen);
        }

        // Vínculo ativo único por CADPEN (para tipos ativos)
        if (in_array($payload['tipo'], ['ADMISSAO','TRANSFERENCIA','TRANSITO'], true)) {
            $jaAtivo = DB::table($this->tableAdmissoes)
                ->where('cadpen', '=', $cadpen)
                ->where('ativo', '=', 1)
                ->exists();
            if ($jaAtivo) {
                return back()->withInput()->with('error', 'Já existe um vínculo ATIVO para este CADPEN.');
            }
        }

        $now = now();

        // Helper: só inclui a coluna se ela existir
        $put = function(array &$arr, string $col, $val) {
            if (Schema::hasColumn($this->tableAdmissoes, $col)) {
                $arr[$col] = $val;
            }
        };

        $row = [];

        // Campos base (com checagem de coluna)
        $put($row, 'unidade_id',                    (int)$payload['unidade_id']);
        $put($row, 'dados_pessoais_id',             $dadosPessoaisId);
        $put($row, 'cadpen',                        $cadpen);
        $put($row, 'tipo',                          $payload['tipo']);
        $put($row, 'status',                        $this->statusInicial($payload['tipo']));
        $put($row, 'ativo',                         in_array($payload['tipo'], ['ADMISSAO','TRANSFERENCIA','TRANSITO'], true) ? 1 : 0);

        // Origem
        $put($row, 'origem',                        $payload['origem'] ?? null);
        $put($row, 'origem_complemento',            $payload['origem_complemento'] ?? null);
        $put($row, 'uf_origem',                     $payload['uf_origem'] ?? null);

        // Destino (se coluna existir)
        $put($row, 'destino_unidade_id',            $request->filled('destino_unidade_id') ? (int)$request->input('destino_unidade_id') : null);

        // Enquadramentos JSON (hierárquico)
        $put($row, 'enquadramentos_json',           $payload['enquadramentos_json'] ?? null);

        // Motivo (admissão)
        $put($row, 'motivo',                        $payload['motivo'] ?? null);
        $put($row, 'motivo_descricao',              $payload['motivo_descricao'] ?? null);

        // Timestamps
        $put($row, 'admissao_at',                   in_array($payload['tipo'], ['ADMISSAO','TRANSFERENCIA','TRANSITO'], true) ? $now : null);

        // Desligamento (se vier no post e a coluna existir)
        $put($row, 'desligamento_tipo',             $payload['desligamento_tipo'] ?? null);
        $put($row, 'desligamento_destino_unidade_id',
                                                 $request->filled('desligamento_destino_unidade_id') ? (int)$request->input('desligamento_destino_unidade_id') : null);
        $put($row, 'desligamento_observacao',       $payload['desligamento_observacao'] ?? null);
        $put($row, 'desligamento_at',               null);

        // Base timestamps
        $put($row, 'created_at',                    $now);
        $put($row, 'updated_at',                    $now);

        // Se já veio como DESLIGAMENTO imediato:
        if ($payload['tipo'] === 'DESLIGAMENTO') {
            $put($row, 'status', 'DESLIGADO');
            $put($row, 'ativo', 0);
            $put($row, 'desligamento_at', $now);
        }

        DB::table($this->tableAdmissoes)->insert($row);

        return redirect()->route('ciclo.admissao.index')->with('success', 'REGISTRO SALVO COM SUCESSO.');
    }

    /** Resolve ID na tabela dados_pessoais a partir de vários formatos de CADPEN */
    private function resolvePessoaIdPorCadpen(string $cadpen): ?int
    {
        if (!Schema::hasTable($this->tablePessoas)) return null;

        $raw  = trim($cadpen);
        $norm = mb_strtoupper($raw, 'UTF-8');
        $q    = DB::table($this->tablePessoas);

        // 1) exato
        $id = (clone $q)->where('cadpen', '=', $norm)->value('id');
        if ($id) return (int)$id;

        // 2) dígitos -> cadpen_number
        $digits = preg_replace('/\D/', '', $norm);
        if ($digits !== '' && ctype_digit($digits)) {
            $id = (clone $q)->where('cadpen_number', '=', (int)$digits)->value('id');
            if ($id) return (int)$id;
        }

        // 3) YYYYxxxxxx -> YYYY-xxxxxx
        if (preg_match('/^[0-9]{10,}$/', $digits)) {
            $year   = substr($digits, 0, 4);
            $number = substr($digits, 4);
            if (ctype_digit($year) && ctype_digit($number)) {
                $formatted = sprintf('%s-%06d', $year, (int)$number);
                $id = (clone $q)->where('cadpen', '=', $formatted)->value('id');
                if ($id) return (int)$id;
            }
        }

        // 4) sufixo -%06d
        if ($digits !== '' && strlen($digits) <= 6) {
            $suffix = sprintf('-%06d', (int)$digits);
            $id = (clone $q)->where('cadpen', 'like', '%' . $suffix)->value('id');
            if ($id) return (int)$id;
        }

        return null;
    }

    private function statusInicial(string $tipo): string
    {
        return match ($tipo) {
            'TRANSFERENCIA', 'TRANSITO' => 'EM_DESLOCAMENTO',
            'DESLIGAMENTO'              => 'DESLIGADO',
            default                     => 'ATIVO', // ADMISSAO
        };
    }
}
