<?php

namespace App\Http\Controllers\Ciclo;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdmissoesController extends Controller
{
    private string $tableAdmissoes = 'admissoes';
    private string $tablePessoas   = 'dados_pessoais';

    /** Lista simples (opcional) */
    public function index(Request $request)
    {
        if (!Schema::hasTable($this->tableAdmissoes)) {
            return back()->with('error', 'A tabela ADMISSOES ainda não existe. Rode as migrations.');
        }

        $rows = DB::table($this->tableAdmissoes)->orderByDesc('id')->limit(50)->get();
        return response()->view('ciclo.admissoes-index', ['registros' => $rows], 200);
    }

    /** Histórico por CADPEN (JSON para a tabela da Blade) */
    public function historico(string $cadpen)
    {
        if (!Schema::hasTable($this->tableAdmissoes)) {
            return response()->json([], 200);
        }

        $unidades = [];
        if (Schema::hasTable('unidades')) {
            $unidades = DB::table('unidades')->pluck('nome','id')->toArray();
        }

        $rows = DB::table($this->tableAdmissoes)
            ->where('cadpen', mb_strtoupper($cadpen,'UTF-8'))
            ->orderByDesc('id')
            ->get();

        $fmt = static function($ts) {
            if (!$ts) return null;
            try { return Carbon::parse($ts)->format('d/m/Y H:i'); } catch (\Throwable $e) { return $ts; }
        };

        $out = [];
        foreach ($rows as $r) {
            $inicio = match($r->tipo) {
                'TRANSFERENCIA' => $r->transferencia_inicio_at ?: $r->admissao_at,
                'TRANSITO'      => $r->transito_inicio_at ?: $r->admissao_at,
                'DESLIGAMENTO'  => $r->desligamento_at,
                default         => $r->admissao_at,
            };
            $fim = match($r->tipo) {
                'TRANSFERENCIA' => $r->transferencia_aceite_at,
                'TRANSITO'      => $r->transito_retorno_conclusao_at ?: $r->transito_aceite_at,
                'DESLIGAMENTO'  => $r->desligamento_at,
                default         => null,
            };

            $obs = null;
            if ($r->tipo === 'TRANSFERENCIA') $obs = $r->transferencia_motivo;
            elseif ($r->tipo === 'TRANSITO')  $obs = $r->transito_motivo;
            elseif ($r->tipo === 'DESLIGAMENTO') $obs = $r->desligamento_observacao;

            $out[] = [
                'id'                   => $r->id,
                'tipo'                 => $r->tipo,
                'unidade_id'           => $r->unidade_id,
                'unidade_nome'         => $unidades[$r->unidade_id] ?? null,
                'destino_unidade_id'   => $r->destino_unidade_id,
                'destino_unidade_nome' => $r->destino_unidade_id ? ($unidades[$r->destino_unidade_id] ?? null) : null,
                'status'               => $r->status,
                'inicio'               => $inicio,
                'inicio_br'            => $fmt($inicio),
                'fim'                  => $fim,
                'fim_br'               => $fmt($fim),
                'obs'                  => $obs,
            ];
        }

        return response()->json($out, 200);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable($this->tableAdmissoes)) {
            return back()->with('error', 'A tabela ADMISSOES ainda não existe. Rode: php artisan migrate');
        }

        $table = $this->tableAdmissoes;
        $upper = static fn($v) => is_string($v) ? mb_strtoupper($v,'UTF-8') : $v;
        $minhaUnidadeId = Auth::user()?->unidade_id ? (int)Auth::user()->unidade_id : null;
        $agora = now();

        $put = function(array &$arr, string $col, $val) use ($table) {
            if (Schema::hasColumn($table, $col)) $arr[$col] = $val;
        };

        $admissaoAtiva = function (string $cadpen): ?object {
            return DB::table($this->tableAdmissoes)
                ->where('cadpen', $cadpen)
                ->where('tipo', 'ADMISSAO')
                ->where('ativo', 1)
                ->orderByDesc('id')
                ->first();
        };

        /* ===================== AÇÕES ESPECIAIS (transferência/transito) ===================== */
        if ($request->filled('acao')) {
            try {
                return DB::transaction(function () use ($request, $upper, $admissaoAtiva, $agora, $table, $put, $minhaUnidadeId) {

                    $acao   = $request->input('acao');
                    $cadpen = $upper((string)$request->input('cadpen', ''));
                    if (!$cadpen) return back()->with('error', 'Informe o CADPEN.');

                    $vOrigem = $admissaoAtiva($cadpen);
                    if (!$vOrigem) return back()->with('error', 'Indivíduo não alocado em uma unidade. Gentileza admiti-lo.');
                    $origemId = (int)$vOrigem->unidade_id;

                    switch ($acao) {
                        /* ===== TRANSFERÊNCIA: INICIAR ===== */
                        case 'transferencia_iniciar': {
                            $destinoId = (int)$request->input('destino_unidade_id');
                            $motivo    = $upper((string)$request->input('transferencia_motivo'));
                            if (!$destinoId) return back()->with('error', 'Selecione a UNIDADE DE DESTINO.');

                            $row = [];
                            $put($row,'unidade_id',              $origemId);
                            $put($row,'destino_unidade_id',      $destinoId);
                            $put($row,'cadpen',                  $cadpen);
                            $put($row,'tipo',                    'TRANSFERENCIA');
                            $put($row,'status',                  'EM_DESLOCAMENTO');
                            $put($row,'ativo',                   0);
                            $put($row,'transferencia_motivo',    $motivo ?: null);
                            $put($row,'transferencia_inicio_at', $agora);
                            $put($row,'created_at',              $agora);
                            $put($row,'updated_at',              $agora);
                            DB::table($table)->insert($row);

                            DB::table($table)->where('id',$vOrigem->id)->update([
                                'status'     => Schema::hasColumn($table,'status') ? 'EM_DESLOCAMENTO' : $vOrigem->status,
                                'updated_at' => $agora,
                            ]);

                            return back()->with('success','Transferência iniciada.');
                        }

                        /* ===== TRANSFERÊNCIA: CONCLUIR (destino) ===== */
                        case 'transferencia_concluir': {
                            $destinoId = $minhaUnidadeId ?: 0;
                            $origemIdIn = (int)$request->input('origem_id');
                            if (!$destinoId) return back()->with('error','Sem unidade (destino).');
                            if ($origemIdIn && $origemIdIn !== $origemId) {
                                return back()->with('error','Origem informada não confere com a origem ativa.');
                            }

                            $evento = DB::table($table)
                                ->where('tipo','TRANSFERENCIA')
                                ->where('unidade_id',$origemId)
                                ->where('destino_unidade_id',$destinoId)
                                ->where('cadpen',$cadpen)
                                ->whereNull('transferencia_aceite_at')
                                ->orderByDesc('id')->first();

                            if (!$evento) return back()->with('error','Não há transferência pendente para esta combinação.');

                            $durMin = Schema::hasColumn($table,'transferencia_inicio_at') && $evento->transferencia_inicio_at
                                ? Carbon::parse($evento->transferencia_inicio_at)->diffInMinutes($agora)
                                : null;

                            $up = ['updated_at'=>$agora];
                            if (Schema::hasColumn($table,'transferencia_aceite_at'))   $up['transferencia_aceite_at']   = $agora;
                            if (Schema::hasColumn($table,'transferencia_duracao_min')) $up['transferencia_duracao_min'] = $durMin;
                            if (Schema::hasColumn($table,'status'))                    $up['status'] = 'ENCERRADO';
                            DB::table($table)->where('id',$evento->id)->update($up);

                            // encerra admissão de origem
                            $upOri = ['updated_at'=>$agora];
                            if (Schema::hasColumn($table,'ativo'))               $upOri['ativo'] = 0;
                            if (Schema::hasColumn($table,'status'))              $upOri['status'] = 'ENCERRADO';
                            if (Schema::hasColumn($table,'desligamento_tipo'))   $upOri['desligamento_tipo'] = 'TRANSFERENCIA';
                            if (Schema::hasColumn($table,'desligamento_at'))     $upOri['desligamento_at'] = $agora;
                            DB::table($table)->where('id',$vOrigem->id)->update($upOri);

                            // cria nova admissão no destino
                            $ad = [];
                            $put($ad,'unidade_id',  $destinoId);
                            $put($ad,'cadpen',      $cadpen);
                            $put($ad,'tipo',        'ADMISSAO');
                            $put($ad,'status',      'ATIVO');
                            $put($ad,'ativo',       1);
                            $put($ad,'admissao_at', $agora);
                            $put($ad,'origem',      'TRANSFERENCIA');
                            $put($ad,'created_at',  $agora);
                            $put($ad,'updated_at',  $agora);
                            DB::table($table)->insert($ad);

                            return back()->with('success','Transferência concluída.');
                        }

                        /* ===== TRÂNSITO: INICIAR ===== */
                        case 'transito_iniciar': {
                            $destinoId = (int)$request->input('destino_unidade_id');
                            $prevRet   = $request->input('prev_retorno_data');
                            $motivo    = $upper((string)$request->input('transito_motivo'));
                            if (!$destinoId) return back()->with('error', 'Selecione a UNIDADE DE DESTINO.');

                            $row = [];
                            $put($row,'unidade_id',               $origemId);
                            $put($row,'destino_unidade_id',       $destinoId);
                            $put($row,'cadpen',                   $cadpen);
                            $put($row,'tipo',                     'TRANSITO');
                            $put($row,'status',                   'EM_DESLOCAMENTO');
                            $put($row,'ativo',                    0);
                            $put($row,'transito_motivo',          $motivo ?: null);
                            $put($row,'transito_inicio_at',       $agora);
                            $put($row,'transito_prev_retorno_em', $prevRet ?: null);
                            $put($row,'created_at',               $agora);
                            $put($row,'updated_at',               $agora);
                            DB::table($table)->insert($row);

                            DB::table($table)->where('id',$vOrigem->id)->update([
                                'status'     => Schema::hasColumn($table,'status') ? 'EM_DESLOCAMENTO' : $vOrigem->status,
                                'updated_at' => $agora,
                            ]);

                            return back()->with('success','Trânsito iniciado.');
                        }

                        /* ===== TRÂNSITO: ACEITAR CHEGADA (destino) ===== */
                        case 'transito_aceitar': {
                            $destinoId = $minhaUnidadeId ?: 0;
                            $origemIdIn = (int)$request->input('origem_id');
                            if (!$destinoId) return back()->with('error','Sem unidade (destino).');
                            if ($origemIdIn && $origemIdIn !== $origemId) {
                                return back()->with('error','Origem informada não confere com a origem ativa.');
                            }

                            $evento = DB::table($table)
                                ->where('tipo','TRANSITO')
                                ->where('unidade_id',$origemId)
                                ->where('destino_unidade_id',$destinoId)
                                ->where('cadpen',$cadpen)
                                ->whereNull('transito_aceite_at')
                                ->orderByDesc('id')->first();

                            if (!$evento) return back()->with('error','Nenhum trânsito pendente para aceite.');

                            $durIda = (Schema::hasColumn($table,'transito_inicio_at') && $evento->transito_inicio_at)
                                ? Carbon::parse($evento->transito_inicio_at)->diffInMinutes($agora)
                                : null;

                            $up = ['updated_at'=>$agora];
                            if (Schema::hasColumn($table,'transito_aceite_at'))       $up['transito_aceite_at'] = $agora;
                            if (Schema::hasColumn($table,'transito_ida_duracao_min')) $up['transito_ida_duracao_min'] = $durIda;
                            if (Schema::hasColumn($table,'status'))                   $up['status'] = 'ATIVO';
                            DB::table($table)->where('id',$evento->id)->update($up);

                            return back()->with('success','Chegada em trânsito aceita.');
                        }

                        /* ===== TRÂNSITO: INICIAR RETORNO (destino) ===== */
                        case 'transito_iniciar_retorno': {
                            $destinoId = $minhaUnidadeId ?: 0;
                            $origemIdIn = (int)$request->input('origem_id');
                            if (!$destinoId) return back()->with('error','Sem unidade (destino).');
                            if ($origemIdIn && $origemIdIn !== $origemId) {
                                return back()->with('error','Origem informada não confere com a origem ativa.');
                            }

                            $evento = DB::table($table)
                                ->where('tipo','TRANSITO')
                                ->where('unidade_id',$origemId)
                                ->where('destino_unidade_id',$destinoId)
                                ->where('cadpen',$cadpen)
                                ->whereNotNull('transito_aceite_at')
                                ->whereNull('transito_retorno_inicio_at')
                                ->orderByDesc('id')->first();

                            if (!$evento) return back()->with('error','Nenhum trânsito apto a iniciar retorno.');

                            $up = ['updated_at'=>$agora];
                            if (Schema::hasColumn($table,'transito_retorno_inicio_at')) $up['transito_retorno_inicio_at'] = $agora;
                            if (Schema::hasColumn($table,'status'))                    $up['status'] = 'EM_DESLOCAMENTO';
                            DB::table($table)->where('id',$evento->id)->update($up);

                            return back()->with('success','Retorno do trânsito iniciado.');
                        }

                        /* ===== TRÂNSITO: CONCLUIR (origem) ===== */
                        case 'transito_concluir': {
                            $origemQueConclui = $minhaUnidadeId ?: 0;
                            $destinoId        = (int)$request->input('destino_id');
                            if (!$origemQueConclui || !$destinoId) return back()->with('error','Dados insuficientes (destino_id).');
                            if ($origemQueConclui !== $origemId) {
                                return back()->with('error','Conclusão do trânsito deve ser feita pela unidade de origem.');
                            }

                            $evento = DB::table($table)
                                ->where('tipo','TRANSITO')
                                ->where('unidade_id',$origemId)
                                ->where('destino_unidade_id',$destinoId)
                                ->where('cadpen',$cadpen)
                                ->whereNotNull('transito_retorno_inicio_at')
                                ->whereNull('transito_retorno_conclusao_at')
                                ->orderByDesc('id')->first();

                            if (!$evento) return back()->with('error','Nenhum retorno em trânsito pendente para concluir.');

                            $durRet = (Schema::hasColumn($table,'transito_retorno_inicio_at') && $evento->transito_retorno_inicio_at)
                                ? Carbon::parse($evento->transito_retorno_inicio_at)->diffInMinutes($agora)
                                : null;

                            $up = ['updated_at'=>$agora];
                            if (Schema::hasColumn($table,'transito_retorno_conclusao_at')) $up['transito_retorno_conclusao_at'] = $agora;
                            if (Schema::hasColumn($table,'transito_retorno_duracao_min'))  $up['transito_retorno_duracao_min']  = $durRet;
                            if (Schema::hasColumn($table,'status'))                        $up['status'] = 'ENCERRADO';
                            DB::table($table)->where('id',$evento->id)->update($up);

                            // reativa admissão de origem
                            $upOri = ['updated_at'=>$agora];
                            if (Schema::hasColumn($table,'status')) $upOri['status'] = 'ATIVO';
                            DB::table($table)->where('id',$vOrigem->id)->update($upOri);

                            return back()->with('success','Trânsito concluído.');
                        }
                    }

                    return back()->with('error', 'Ação inválida.');
                });
            } catch (\Throwable $e) {
                Log::error('Erro nas ações (store): '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
                return back()->withInput()->with('error','Falha ao processar a ação.');
            }
        }
        /* ===================== /AÇÕES ESPECIAIS ===================== */

        // ======= Fluxo padrão (Admissão / Desligamento) =======
        $payload = $request->validate([
            'unidade_id'                      => ['required','integer','min:1'],
            'tipo'                            => ['required','string','in:ADMISSAO,TRANSFERENCIA,TRANSITO,DESLIGAMENTO'],
            'cadpen'                          => ['required','string','max:50'],

            // Admissão
            'origem'                          => ['nullable','string','max:60'],
            'origem_complemento'              => ['nullable','string','max:190'],
            'uf_origem'                       => ['nullable','string','size:2'],
            'enquadramentos_json'             => ['nullable','string'],

            // Destino (não usado para ADM padrão neste fluxo)
            'destino_unidade_id'              => ['nullable','integer','min:1'],

            // Desligamento
            'desligamento_tipo'               => ['nullable','string','in:ALVARA,RELAXAMENTO_DE_PRISAO,LIBERDADE_CONDICIONAL,TRANSFERENCIA,TRANSITO,OUTROS'],
            'desligamento_destino_unidade_id' => ['nullable','integer','min:1'],
            'desligamento_observacao'         => ['nullable','string','max:2000'],
        ]);

        foreach (['tipo','cadpen','origem','origem_complemento','uf_origem','desligamento_tipo','desligamento_observacao'] as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] !== null) {
                $payload[$k] = $upper($payload[$k]);
            }
        }

        $cadpen = (string) $payload['cadpen'];
        $now    = now();

        // Regra: operações que não são Admissão exigem admissão ativa
        if (in_array($payload['tipo'], ['TRANSFERENCIA','TRANSITO','DESLIGAMENTO'], true)) {
            $atv = DB::table($this->tableAdmissoes)
                ->where('cadpen', $cadpen)->where('tipo','ADMISSAO')->where('ativo',1)->exists();
            if (!$atv) {
                return back()->withInput()->with('error', 'Operação permitida apenas para indivíduos com ADMISSÃO ATIVA.');
            }
        }

        // 🔒 Regra: não pode haver Admissão se já existe admissão ativa (mesma ou outra unidade)
        if ($payload['tipo'] === 'ADMISSAO') {
            $existeAtiva = DB::table($this->tableAdmissoes)
                ->where('cadpen', $cadpen)
                ->where('tipo','ADMISSAO')
                ->where('ativo',1)
                ->exists();

            if ($existeAtiva) {
                return back()->withInput()->with('error', 'Já existe ADMISSÃO ATIVA para este indivíduo. Use TRANSFERÊNCIA ou TRÂNSITO.');
            }
        }

        $row = [];
        $put($row,'unidade_id',        (int)$payload['unidade_id']);
        $put($row,'cadpen',            $cadpen);
        $put($row,'tipo',              $payload['tipo']);
        $put($row,'status',            $this->statusInicial($payload['tipo']));
        $put($row,'ativo',             $payload['tipo']==='ADMISSAO' ? 1 : 0);

        // Admissão
        $put($row,'origem',            $payload['origem'] ?? null);
        $put($row,'origem_complemento',$payload['origem_complemento'] ?? null);
        $put($row,'uf_origem',         $payload['uf_origem'] ?? null);
        $put($row,'enquadramentos_json',$payload['enquadramentos_json'] ?? null);

        // Destino (apenas por compat; transferências/trânsitos reais usam bloco de ações)
        $put($row,'destino_unidade_id', $request->filled('destino_unidade_id') ? (int)$request->input('destino_unidade_id') : null);

        // Tempos base
        $put($row,'admissao_at',       in_array($payload['tipo'], ['ADMISSAO','TRANSFERENCIA','TRANSITO'], true) ? $now : null);

        // Desligamento
        $put($row,'desligamento_tipo',               $payload['desligamento_tipo'] ?? null);
        $put($row,'desligamento_destino_unidade_id', $request->filled('desligamento_destino_unidade_id') ? (int)$request->input('desligamento_destino_unidade_id') : null);
        $put($row,'desligamento_observacao',         $payload['desligamento_observacao'] ?? null);
        $put($row,'desligamento_at',                 $payload['tipo']==='DESLIGAMENTO' ? $now : null);

        $put($row,'created_at', $now);
        $put($row,'updated_at', $now);

        DB::table($table)->insert($row);

        return redirect()->back()->with('success', 'REGISTRO SALVO COM SUCESSO.');
    }

    private function statusInicial(string $tipo): string
    {
        return match ($tipo) {
            'TRANSFERENCIA', 'TRANSITO' => 'EM_DESLOCAMENTO',
            'DESLIGAMENTO'              => 'ENCERRADO',
            default                     => 'ATIVO',
        };
    }
}
