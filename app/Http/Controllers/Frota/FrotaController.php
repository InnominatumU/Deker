<?php
// app/Http/Controllers/Frota/FrotaController.php

namespace App\Http\Controllers\Frota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FrotaController extends Controller
{
    /**
     * Buscar / Editar (sem {id}) -> usa a mesma view do editar
     */
    public function search(Request $request)
    {
        $qPlaca   = mb_strtoupper(trim((string) $request->query('placa', '')), 'UTF-8');
        $qRenavam = mb_strtoupper(trim((string) $request->query('renavam', '')), 'UTF-8');
        $qModelo  = mb_strtoupper(trim((string) $request->query('modelo', '')), 'UTF-8');

        $perPage   = max(10, (int) $request->query('pp', 15));
        $didSearch = ($qPlaca !== '' || $qRenavam !== '' || $qModelo !== '');

        $resultados = null;

        if ($didSearch) {
            $qb = DB::table('veiculos')->select(
                'id','placa','renavam','chassi','marca','modelo',
                'ano_modelo','cor','status','updated_at'
            );

            if ($qPlaca   !== '') $qb->where('placa', 'like', "%{$qPlaca}%");
            if ($qRenavam !== '') $qb->where('renavam', 'like', "%{$qRenavam}%");
            if ($qModelo  !== '') $qb->where('modelo', 'like', "%{$qModelo}%");

            $qb->orderBy('placa');
            $resultados = $qb->paginate($perPage)->appends($request->query());
        }

        return view('frota.veiculosedit', [
            'veiculo'    => null, // modo busca
            'didSearch'  => $didSearch,
            'resultados' => $resultados,
            'q' => [
                'q_placa'   => $qPlaca,
                'q_renavam' => $qRenavam,
                'q_modelo'  => $qModelo,
                'q_perpage' => $perPage,
            ],
        ]);
    }

    /** Tela de cadastro */
    public function create()
    {
        return view('frota.veiculoscreate');
    }

    /** Persistir veículo novo */
    public function store(Request $request)
    {
        $data = $this->validateVeiculo($request);

        $hodometro = $data['odometro_km'] ?? null;
        $status    = $this->normalizeStatus($data['status'] ?? 'DISPONIVEL');

        $id = DB::table('veiculos')->insertGetId([
            'placa'             => mb_strtoupper($data['placa'], 'UTF-8'),
            'renavam'           => $data['renavam'] ?? null,
            'chassi'            => $data['chassi'] ?? null,
            'marca'             => mb_strtoupper($data['marca'] ?? '', 'UTF-8'),
            'modelo'            => mb_strtoupper($data['modelo'] ?? '', 'UTF-8'),
            'tipo'              => mb_strtoupper($data['tipo'] ?? '', 'UTF-8'),
            'ano_fabricacao'    => $data['ano_fabricacao'] ?? null,
            'ano_modelo'        => $data['ano_modelo'] ?? null,
            'cor'               => mb_strtoupper($data['cor'] ?? '', 'UTF-8'),
            'propriedade'       => mb_strtoupper($data['propriedade'] ?? '', 'UTF-8'),
            'tipo_combustivel'  => $request->filled('tipo_combustivel') ? mb_strtoupper($request->input('tipo_combustivel'), 'UTF-8') : null,
            'capacidade_tanque' => $request->filled('capacidade_tanque') ? (int) $request->input('capacidade_tanque') : null,
            'unidade_id'        => $request->filled('unidade_id') ? (int) $request->input('unidade_id') : null,
            'status'            => $status,
            'hodometro_atual'   => $hodometro,
            'observacoes'       => $data['observacoes'] ?? null,
            'created_by'        => Auth::id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return redirect()
            ->route('frota.veiculos.edit', ['id' => $id])
            ->with('success', 'Veículo cadastrado com sucesso.');
    }

    /** Abrir edição por ID */
    public function edit(int $id)
    {
        $v = DB::table('veiculos')->where('id', $id)->first();
        abort_if(!$v, 404);

        return view('frota.veiculosedit', [
            'veiculo'    => $v,
            'didSearch'  => false,
            'resultados' => null,
            'q'          => [],
        ]);
    }

    /** Atualizar veículo */
    public function update(Request $request, int $id)
    {
        $v = DB::table('veiculos')->where('id', $id)->first();
        abort_if(!$v, 404);

        $data = $this->validateVeiculo($request, $id);

        $hodometro = $data['odometro_km'] ?? null;
        $status    = $this->normalizeStatus($data['status'] ?? $v->status);

        DB::table('veiculos')->where('id', $id)->update([
            'placa'             => mb_strtoupper($data['placa'], 'UTF-8'),
            'renavam'           => $data['renavam'] ?? null,
            'chassi'            => $data['chassi'] ?? null,
            'marca'             => mb_strtoupper($data['marca'] ?? '', 'UTF-8'),
            'modelo'            => mb_strtoupper($data['modelo'] ?? '', 'UTF-8'),
            'tipo'              => mb_strtoupper($data['tipo'] ?? '', 'UTF-8'),
            'ano_fabricacao'    => $data['ano_fabricacao'] ?? null,
            'ano_modelo'        => $data['ano_modelo'] ?? null,
            'cor'               => mb_strtoupper($data['cor'] ?? '', 'UTF-8'),
            'propriedade'       => mb_strtoupper($data['propriedade'] ?? '', 'UTF-8'),
            'tipo_combustivel'  => $request->filled('tipo_combustivel') ? mb_strtoupper($request->input('tipo_combustivel'), 'UTF-8') : null,
            'capacidade_tanque' => $request->filled('capacidade_tanque') ? (int) $request->input('capacidade_tanque') : null,
            'unidade_id'        => $request->filled('unidade_id') ? (int) $request->input('unidade_id') : null,
            'status'            => $status,
            'hodometro_atual'   => $hodometro,
            'observacoes'       => $data['observacoes'] ?? null,
            'updated_at'        => now(),
        ]);

        return back()->with('success', 'Veículo atualizado com sucesso.');
    }

    /** Excluir veículo */
    public function destroy(int $id)
    {
        DB::table('veiculos')->where('id', $id)->delete();

        return redirect()
            ->route('frota.veiculos.search')
            ->with('success', 'Veículo excluído.');
    }

    // ========= Submenus — telas (views permanecem as mesmas) =========
    public function uso()            { return view('frota.veiculosuso'); }
    public function abastecimentos() { return view('frota.veiculosabastecimento'); }
    public function deslocamentos()  { return view('frota.veiculosdeslocamento'); }
    public function manutencoes()    { return view('frota.veiculosmanutencao'); }
    public function documentos()     { return view('frota.veiculosdocumentos'); }
    public function relatorios()     { return view('frota.veiculosrelatorios'); }

    // ========= Submenus — gravação via veiculos_eventos =========

    /** USO / DIÁRIAS / CHECKLIST -> grava em veiculos_eventos */
    public function usoStore(Request $request)
    {
        $data = $request->validate([
            'placa'      => ['required','string','max:10'],
            'data_uso'   => ['required','date'],
            'resp_tipo'  => ['required', Rule::in(['SERVIDOR','PRESTADOR'])],
            'resp_query' => ['required','string','max:255'],
            'resp_id'    => ['nullable','integer','min:1'],
            'checklist'  => ['nullable','string'],
            'avarias_json' => ['nullable','string'], // JSON {"avarias":[{l,nota,seq}]}
        ], [], [
            'placa'=>'Placa','data_uso'=>'Data','resp_tipo'=>'Tipo',
            'resp_query'=>'Identificador','resp_id'=>'ID','checklist'=>'Checklist'
        ]);

        $placa = mb_strtoupper($data['placa'],'UTF-8');
        $veic  = DB::table('veiculos')->select('id')->where('placa',$placa)->first();
        if (!$veic) return back()->withErrors(['placa'=>'Veículo não encontrado pela PLACA.'])->withInput();

        // payload
        $payload = [
            'resp' => [
                'tipo'  => $data['resp_tipo'],
                'query' => mb_strtoupper($data['resp_query'],'UTF-8'),
                'id'    => $data['resp_id'] ?? null,
            ],
            'checklist'    => $data['checklist'] ?? null,
            'avarias'      => null,
            'placa'        => $placa,
        ];
        if (!empty($data['avarias_json'])) {
            $tmp = json_decode($data['avarias_json'], true);
            $payload['avarias'] = is_array($tmp['avarias'] ?? null) ? $tmp['avarias'] : null;
        }

        $id = DB::table('veiculos_eventos')->insertGetId([
            'veiculo_id'  => $veic->id,
            'tipo'        => 'USO',
            'data_evento' => $data['data_uso'],
            'payload_json'=> json_encode($payload, JSON_UNESCAPED_UNICODE),
            'observacoes' => null,
            'created_by'  => Auth::id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Registro Realizado com Sucesso.')->with('id', $id);
    }

    /** ABASTECIMENTOS -> grava em veiculos_eventos */
    public function abastecimentosStore(Request $request)
    {
        $data = $request->validate([
            'placa'        => ['required','string','max:10'],
            'data_hora'    => ['required','date'],
            'litros'       => ['nullable','numeric','min:0'],
            'valor_total'  => ['nullable','numeric','min:0'],
            'odometro_km'  => ['required','integer','min:0'],
            'observacoes'  => ['nullable','string'],
        ], [], [
            'placa'=>'Placa','data_hora'=>'Data/Hora','odometro_km'=>'Odômetro (km)'
        ]);

        $placa = mb_strtoupper($data['placa'],'UTF-8');
        $veic  = DB::table('veiculos')->select('id')->where('placa',$placa)->first();
        if (!$veic) return back()->withErrors(['placa'=>'Veículo não encontrado pela PLACA.'])->withInput();

        $payload = [
            'placa'       => $placa,
            'litros'      => $data['litros'] ?? null,
            'valor_total' => $data['valor_total'] ?? null,
            'odometro_km' => $data['odometro_km'],
            'obs'         => $data['observacoes'] ?? null,
        ];

        $id = DB::table('veiculos_eventos')->insertGetId([
            'veiculo_id'  => $veic->id,
            'tipo'        => 'ABASTECIMENTO',
            'data_evento' => $data['data_hora'],
            'payload_json'=> json_encode($payload, JSON_UNESCAPED_UNICODE),
            'observacoes' => $data['observacoes'] ?? null,
            'created_by'  => Auth::id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Registro Realizado com Sucesso.')->with('id', $id);
    }

    /** DESLOCAMENTOS -> grava em veiculos_eventos */
    public function deslocamentosStore(Request $request)
    {
        $data = $request->validate([
            'placa'     => ['required','string','max:10'],
            'saida_em'  => ['required','date'],
            'retorno_em'=> ['nullable','date','after_or_equal:saida_em'],
            'origem'    => ['nullable','string','max:120'],
            'destino'   => ['nullable','string','max:120'],
            'finalidade'=> ['required', Rule::in([
                'ESCOLTA_HOSPITALAR','ESCOLTA_JUDICIAL','TRANSFERENCIA',
                'ESCOLTA_OUTROS','ADMINISTRATIVO','SERVICO','MANUTENCAO','OUTROS'
            ])],

            // Manutenção (condicional — mantido por compat na UI)
            'manutencao_tipo'      => ['nullable', Rule::in(['PREVENTIVA','CORRETIVA','PROGRAMACAO'])],
            'manutencao_data'      => ['nullable','date'],
            'manutencao_descricao' => ['nullable','string'],

            // Equipe — Motorista
            'motorista_tipo'             => ['required', Rule::in(['SERVIDOR','PRESTADOR'])],
            'motorista_servidor_query'   => ['nullable','string','max:255'],
            'motorista_servidor_id'      => ['nullable','integer','min:1'],
            'motorista_servidor_cnh'     => ['nullable','string','max:40'],
            'motorista_servidor_cnh_cat' => ['nullable','string','max:2'],
            'motorista_servidor_cnh_val' => ['nullable','date'],

            'motorista_prestador_nome'   => ['nullable','string','max:255'],
            'motorista_prestador_doc'    => ['nullable','string','max:40'],
            'motorista_prestador_emp'    => ['nullable','string','max:120'],
            'motorista_prestador_cnh'    => ['nullable','string','max:40'],
            'motorista_prestador_cnh_cat'=> ['nullable','string','max:2'],
            'motorista_prestador_cnh_val'=> ['nullable','date'],

            // Passageiros / Escoltados (JSON agregados)
            'passageiros_json' => ['nullable','string'],
            'escoltados_json'  => ['nullable','string'],

            // Escolta OUTROS - descrição
            'escolta_outros_tipo' => ['nullable','string','max:160'],

            'observacoes' => ['nullable','string'],
        ]);

        $isMan  = ($data['finalidade'] ?? '') === 'MANUTENCAO';
        $isEsc  = in_array(($data['finalidade'] ?? ''), ['ESCOLTA_HOSPITALAR','ESCOLTA_JUDICIAL','TRANSFERENCIA','ESCOLTA_OUTROS'], true);
        $isEscO = ($data['finalidade'] ?? '') === 'ESCOLTA_OUTROS';

        if (!$isMan && ($data['motorista_tipo'] ?? '') === 'PRESTADOR') {
            return back()->withErrors(['motorista_tipo'=>'Motorista PRESTADOR apenas em MANUTENÇÃO.'])->withInput();
        }
        if ($isEscO && empty($data['escolta_outros_tipo'])) {
            return back()->withErrors(['escolta_outros_tipo'=>'Descreva o tipo de escolta em "OUTROS TIPOS".'])->withInput();
        }

        $placa = mb_strtoupper($data['placa'],'UTF-8');
        $veic  = DB::table('veiculos')->select('id')->where('placa',$placa)->first();
        if (!$veic) return back()->withErrors(['placa'=>'Veículo não encontrado pela PLACA.'])->withInput();

        // Parse JSON agregados
        $passageiros = [];
        if (!empty($data['passageiros_json'])) {
            $tmp = json_decode($data['passageiros_json'], true);
            $passageiros = is_array($tmp['passageiros'] ?? null) ? $tmp['passageiros'] : [];
        }
        $escoltados = [];
        if (!empty($data['escoltados_json'])) {
            $tmp = json_decode($data['escoltados_json'], true);
            $escoltados = is_array($tmp['escoltados'] ?? null) ? $tmp['escoltados'] : [];
        }
        if ($isEsc && count($escoltados) === 0) {
            return back()->withErrors(['escoltados_json'=>'Insira ao menos um ESCOLTADO para esta finalidade.'])->withInput();
        }

        // Monta payload completo
        $payload = [
            'placa'       => $placa,
            'saida_em'    => $data['saida_em'],
            'retorno_em'  => $data['retorno_em'] ?? null,
            'origem'      => mb_strtoupper($data['origem'] ?? '','UTF-8'),
            'destino'     => mb_strtoupper($data['destino'] ?? '','UTF-8'),
            'finalidade'  => $data['finalidade'],
            'escolta_outros_tipo' => $isEscO ? mb_strtoupper($data['escolta_outros_tipo'],'UTF-8') : null,

            'manutencao' => $isMan ? [
                'tipo' => $data['manutencao_tipo'] ?? null,
                'data' => $data['manutencao_data'] ?? null,
                'descricao' => $data['manutencao_descricao'] ?? null,
            ] : null,

            'motorista' => [
                'tipo' => $data['motorista_tipo'],
                'nome' => mb_strtoupper(
                    $data['motorista_tipo'] === 'SERVIDOR'
                        ? ($data['motorista_servidor_query'] ?? '')
                        : ($data['motorista_prestador_nome'] ?? '')
                ,'UTF-8'),
                'doc'  => mb_strtoupper(
                    $data['motorista_tipo'] === 'SERVIDOR'
                        ? (string) ($data['motorista_servidor_id'] ?? '')
                        : (string) ($data['motorista_prestador_doc'] ?? '')
                ,'UTF-8'),
                'cnh'      => $data['motorista_tipo'] === 'SERVIDOR' ? ($data['motorista_servidor_cnh'] ?? null)      : ($data['motorista_prestador_cnh'] ?? null),
                'cnh_cat'  => $data['motorista_tipo'] === 'SERVIDOR' ? ($data['motorista_servidor_cnh_cat'] ?? null)  : ($data['motorista_prestador_cnh_cat'] ?? null),
                'cnh_val'  => $data['motorista_tipo'] === 'SERVIDOR' ? ($data['motorista_servidor_cnh_val'] ?? null)  : ($data['motorista_prestador_cnh_val'] ?? null),
                'empresa'  => $data['motorista_tipo'] === 'PRESTADOR' ? ($data['motorista_prestador_emp'] ?? null)    : null,
            ],

            'passageiros' => $passageiros,
            'escoltados'  => $escoltados,

            'observacoes' => $data['observacoes'] ?? null,
        ];

        $deslocId = DB::table('veiculos_eventos')->insertGetId([
            'veiculo_id'  => $veic->id,
            'tipo'        => 'DESLOCAMENTO',
            'data_evento' => $data['saida_em'],
            'payload_json'=> json_encode($payload, JSON_UNESCAPED_UNICODE),
            'observacoes' => $data['observacoes'] ?? null,
            'created_by'  => Auth::id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Registro Realizado com Sucesso.')->with('id', $deslocId);
    }

    /** DOCUMENTOS + (MULTA opcional) -> grava em veiculos_eventos */
    public function documentosStore(Request $request)
    {
        $data = $request->validate([
            'placa'       => ['required','string','max:10'],
            'tipo'        => ['required','string','max:80'],
            'arquivo'     => ['required','file','max:20480'], // 20MB
            'observacoes' => ['nullable','string'],

            'multa_flag' => ['nullable','in:0,1'],

            // MULTA (opcional)
            'multa_numero'          => ['nullable','string','max:60'],
            'multa_infracao_em'     => ['nullable','date'],
            'multa_orgao'           => ['nullable','string','max:80'],
            'multa_local'           => ['nullable','string','max:160'],
            'multa_municipio_uf'    => ['nullable','string','max:60'],
            'multa_enquadramento'   => ['nullable','string','max:30'],
            'multa_gravidade'       => ['nullable', Rule::in(['LEVE','MÉDIA','GRAVE','GRAVÍSSIMA',''])],
            'multa_pontos'          => ['nullable','integer','min:0','max:40'],
            'multa_descricao'       => ['nullable','string'],
            'multa_motorista_nome'  => ['nullable','string','max:160'],
            'multa_motorista_doc'   => ['nullable','string','max:40'],
            'multa_valor'           => ['nullable','numeric','min:0'],
            'multa_prazo_recurso'   => ['nullable','date'],
            'multa_situacao'        => ['nullable', Rule::in(['EM ABERTO','EM RECURSO','PAGO','CANCELADO',''])],
            'multa_vencimento'      => ['nullable','date'],
            'multa_placa_autuada'   => ['nullable','string','max:10'],
            'multa_anexos.*'        => ['nullable','file','max:20480'], // 20MB cada
        ], [], [
            'placa'=>'Placa','tipo'=>'Tipo Documento','arquivo'=>'Arquivo'
        ]);

        $placa = mb_strtoupper($data['placa'],'UTF-8');
        $veic  = DB::table('veiculos')->select('id')->where('placa',$placa)->first();
        if (!$veic) return back()->withErrors(['placa'=>'Veículo não encontrado pela PLACA.'])->withInput();

        // Upload principal
        $pathDoc = $request->file('arquivo')->store('frota/documentos', 'public');

        // Uploads da multa (opcional)
        $anexos = [];
        if (($data['multa_flag'] ?? '0') === '1' && $request->hasFile('multa_anexos')) {
            foreach ($request->file('multa_anexos') as $file) {
                if ($file) $anexos[] = $file->store('frota/documentos/multas', 'public');
            }
        }

        // Se for multa, exigir mínimos
        $isMulta = ($data['multa_flag'] ?? '0') === '1';
        if ($isMulta) {
            foreach (['multa_numero','multa_infracao_em','multa_enquadramento','multa_placa_autuada'] as $req) {
                if (empty($data[$req])) {
                    return back()->withErrors([$req => 'Campo obrigatório para registrar MULTA.'])->withInput();
                }
            }
        }

        // payload
        $payload = [
            'placa'        => $placa,
            'doc_tipo'     => mb_strtoupper($data['tipo'],'UTF-8'),
            'arquivo_path' => $pathDoc,
            'obs'          => $data['observacoes'] ?? null,
        ];

        if ($isMulta) {
            $payload['multa'] = [
                'numero'       => mb_strtoupper($data['multa_numero'],'UTF-8'),
                'infracao_em'  => $data['multa_infracao_em'],
                'orgao'        => mb_strtoupper($data['multa_orgao'] ?? '','UTF-8'),
                'local'        => mb_strtoupper($data['multa_local'] ?? '','UTF-8'),
                'municipio_uf' => mb_strtoupper($data['multa_municipio_uf'] ?? '','UTF-8'),
                'enquadramento'=> mb_strtoupper($data['multa_enquadramento'],'UTF-8'),
                'gravidade'    => $data['multa_gravidade'] ?? null,
                'pontos'       => $data['multa_pontos'] ?? null,
                'descricao'    => $data['multa_descricao'] ?? null,
                'motorista'    => [
                    'nome' => mb_strtoupper($data['multa_motorista_nome'] ?? '','UTF-8'),
                    'doc'  => mb_strtoupper($data['multa_motorista_doc']  ?? '','UTF-8'),
                ],
                'valor'        => $data['multa_valor'] ?? null,
                'prazo_recurso'=> $data['multa_prazo_recurso'] ?? null,
                'situacao'     => $data['multa_situacao'] ?? null,
                'vencimento'   => $data['multa_vencimento'] ?? null,
                'placa_autuada'=> mb_strtoupper($data['multa_placa_autuada'],'UTF-8'),
                'anexos'       => $anexos ?: null,
            ];
        }

        $docId = DB::table('veiculos_eventos')->insertGetId([
            'veiculo_id'  => $veic->id,
            'tipo'        => 'DOCUMENTO',
            'data_evento' => now(), // ou um campo de data do form, se você preferir
            'payload_json'=> json_encode($payload, JSON_UNESCAPED_UNICODE),
            'observacoes' => $data['observacoes'] ?? null,
            'created_by'  => Auth::id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Documento salvo.')->with('id', $docId);
    }

    /** RELATÓRIOS (apenas acuse de recibo por ora) */
    public function relatoriosRun(Request $request)
    {
        $data = $request->validate([
            'inicio' => ['nullable','date'],
            'fim'    => ['nullable','date','after_or_equal:inicio'],
            'tipos'  => ['required','array','min:1'],
            'tipos.*'=> [Rule::in(['USO','ABASTECIMENTO','DESLOCAMENTO','DOCUMENTO'])],
        ], [], [
            'tipos'=>'Tipos de relatório'
        ]);

        return back()->with('success', 'Relatório solicitado.')->with('filtros', $data);
    }

    /** Legado: Operações unificadas -> aponta para tela de uso */
    public function operacoesIndex()  { return view('frota.veiculosuso'); }
    public function operacoesStore(Request $request) { return $this->usoStore($request); }

    // ================= helpers =================

    private function validateVeiculo(Request $request, ?int $id = null): array
    {
        $rules = [
            'placa'             => ['required','string','max:10', Rule::unique('veiculos','placa')->ignore($id)],
            'renavam'           => ['nullable','string','max:20', Rule::unique('veiculos','renavam')->ignore($id)],
            'chassi'            => ['nullable','string','max:30', Rule::unique('veiculos','chassi')->ignore($id)],
            'marca'             => ['nullable','string','max:60'],
            'modelo'            => ['nullable','string','max:80'],
            'tipo'              => ['nullable','string','max:40'],
            'ano_fabricacao'    => ['nullable','integer','min:1900','max:2100'],
            'ano_modelo'        => ['nullable','integer','min:1900','max:2100'],
            'cor'               => ['nullable','string','max:30'],
            'propriedade'       => ['required', Rule::in(['PROPRIA','ALUGADA','TERCEIRIZADA'])],
            'status'            => ['required', Rule::in(['DISPONIVEL','MANUTENCAO','INATIVO'])],
            'odometro_km'       => ['nullable','integer','min:0','max:2000000'],
            'observacoes'       => ['nullable','string'],

            // campos que precisavam subir
            'tipo_combustivel'  => ['nullable','string','max:20'],
            'capacidade_tanque' => ['nullable','integer','min:0','max:65535'],
            'unidade_id'        => ['nullable','integer','min:1'],
        ];

        $messages = [
            'required' => 'O campo :attribute é obrigatório.',
            'unique'   => 'Já existe um registro com este :attribute.',
            'integer'  => 'O campo :attribute deve ser um número inteiro.',
            'min'      => 'O campo :attribute deve ser no mínimo :min.',
            'max'      => 'O campo :attribute deve ser no máximo :max.',
            'in'       => 'Valor inválido para :attribute.',
        ];

        $attrs = [
            'placa'             => 'Placa',
            'renavam'           => 'RENAVAM',
            'chassi'            => 'Chassi',
            'marca'             => 'Marca',
            'modelo'            => 'Modelo',
            'tipo'              => 'Tipo',
            'ano_fabricacao'    => 'Ano de Fabricação',
            'ano_modelo'        => 'Ano do Modelo',
            'cor'               => 'Cor',
            'propriedade'       => 'Propriedade',
            'status'            => 'Status',
            'odometro_km'       => 'Odômetro (km)',
            'observacoes'       => 'Observações',
            'tipo_combustivel'  => 'Tipo de Combustível',
            'capacidade_tanque' => 'Capacidade do Tanque (L)',
            'unidade_id'        => 'Unidade',
        ];

        return $request->validate($rules, $messages, $attrs);
    }

    private function normalizeStatus(?string $status): string
    {
        return match (mb_strtoupper((string) $status, 'UTF-8')) {
            'DISPONIVEL'  => 'DISPONIVEL',
            'MANUTENCAO'  => 'MANUTENCAO',
            'INATIVO'     => 'INATIVO',
            default       => 'DISPONIVEL',
        };
    }
}
