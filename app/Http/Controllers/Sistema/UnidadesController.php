<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UnidadesController extends Controller
{
    public function search(Request $request)
    {
        $qNome  = trim((string) $request->query('nome', ''));
        $qSigla = trim((string) $request->query('sigla', ''));
        $perPage = (int) $request->query('pp', 15);
        if ($perPage <= 0) $perPage = 15;

        $didSearch  = ($qNome !== '' || $qSigla !== '');
        $resultados = null;

        if ($didSearch) {
            $nomeLike  = mb_strtoupper($qNome,  'UTF-8');
            $siglaLike = mb_strtoupper($qSigla, 'UTF-8');

            $qb = DB::table('unidades')->select(
                'id','nome','sigla','porte','perfil','perfil_outro',
                'capacidade_vagas','created_at','updated_at'
            );

            if ($nomeLike !== '')  $qb->where('nome', 'like', "%{$nomeLike}%");
            if ($siglaLike !== '') $qb->where('sigla', 'like', "%{$siglaLike}%");

            $qb->orderBy('nome');
            $resultados = $qb->paginate($perPage)->appends($request->query());
        }

        return view('sistema.unidadeedit', [
            'unidade'    => null,          // modo BUSCA
            'didSearch'  => $didSearch,
            'resultados' => $resultados,
            'q' => [
                'q_nome'    => $qNome,
                'q_sigla'   => $qSigla,
                'q_perpage' => $perPage,
            ],
            'mapeamento_itens' => [],      // sem mapeamento na busca
        ]);
    }

    public function create(Request $request)
    {
        // Ajuste se sua view de create tiver outro nome
        return view('sistema.unidadecreate', [
            'mapeamento_itens' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $id = DB::table('unidades')->insertGetId([
            'nome'                 => mb_strtoupper($data['nome']),
            'sigla'                => $data['sigla'] ?? null,

            'end_logradouro'       => $data['end_logradouro'] ?? null,
            'end_numero'           => $data['end_numero'] ?? null,
            'end_complemento'      => $data['end_complemento'] ?? null,
            'end_bairro'           => $data['end_bairro'] ?? null,
            'end_municipio'        => $data['end_municipio'] ?? null,
            'end_uf'               => $data['end_uf'] ?? null,
            'end_cep'              => $data['end_cep'] ?? null,

            'porte'                => $data['porte'],
            'perfil'               => $data['perfil'],
            'perfil_outro'         => $data['perfil'] === 'OUTROS' ? ($data['perfil_outro'] ?? null) : null,

            'capacidade_vagas'     => $data['capacidade_vagas'] ?? null,

            'mapeamento_json'      => !empty($data['mapeamento_itens'])
                ? json_encode($data['mapeamento_itens'], JSON_UNESCAPED_UNICODE)
                : null,

            'observacoes'          => $data['observacoes'] ?? null,
            'created_by'           => Auth::id(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        return redirect()
            ->route('sistema.unidades.edit', ['unidade' => $id])
            ->with('success', 'Unidade cadastrada com sucesso.');
    }

    public function edit($unidade)
    {
        $u = DB::table('unidades')->where('id', (int) $unidade)->first();
        abort_if(!$u, 404);

        // Normaliza o JSON de mapeamento para o formato esperado pelo front
        $itens = $this->normalizeMapeamento($u->mapeamento_json);

        // Cosmético: "MEDIO" -> "MÉDIO"
        if (isset($u->porte) && mb_strtoupper($u->porte,'UTF-8') === 'MEDIO') {
            $u->porte = 'MÉDIO';
        }

        return view('sistema.unidadeedit', [
            'unidade'           => $u,
            'mapeamento_itens'  => $itens,
        ]);
    }

    public function update(Request $request, $unidade)
    {
        $u = DB::table('unidades')->where('id', (int) $unidade)->first();
        abort_if(!$u, 404);

        $data = $this->validateData($request, (int) $unidade);

        DB::table('unidades')->where('id', (int) $unidade)->update([
            'nome'                 => mb_strtoupper($data['nome']),
            'sigla'                => $data['sigla'] ?? null,

            'end_logradouro'       => $data['end_logradouro'] ?? null,
            'end_numero'           => $data['end_numero'] ?? null,
            'end_complemento'      => $data['end_complemento'] ?? null,
            'end_bairro'           => $data['end_bairro'] ?? null,
            'end_municipio'        => $data['end_municipio'] ?? null,
            'end_uf'               => $data['end_uf'] ?? null,
            'end_cep'              => $data['end_cep'] ?? null,

            'porte'                => $data['porte'],
            'perfil'               => $data['perfil'],
            'perfil_outro'         => $data['perfil'] === 'OUTROS' ? ($data['perfil_outro'] ?? null) : null,

            'capacidade_vagas'     => $data['capacidade_vagas'] ?? null,

            'mapeamento_json'      => !empty($data['mapeamento_itens'])
                ? json_encode($data['mapeamento_itens'], JSON_UNESCAPED_UNICODE)
                : null,

            'observacoes'          => $data['observacoes'] ?? null,
            'updated_at'           => now(),
        ]);

        return back()->with('success', 'Unidade atualizada com sucesso.');
    }

    public function destroy($unidade)
    {
        DB::table('unidades')->where('id', (int) $unidade)->delete();
        return redirect()
            ->route('sistema.unidades.create')
            ->with('success', 'Unidade excluída.');
    }

    // ================= helpers =================

    private function validateData(Request $request, ?int $id = null): array
    {
        $portes = ['PEQUENO','MÉDIO','GRANDE'];

        $rules = [
            'nome'               => 'required|string|max:150|unique:unidades,nome' . ($id ? ',' . $id : ''),
            'sigla'              => 'nullable|string|max:30',

            'end_logradouro'     => 'nullable|string|max:120',
            'end_numero'         => 'nullable|string|max:10',
            'end_complemento'    => 'nullable|string|max:60',
            'end_bairro'         => 'nullable|string|max:80',
            'end_municipio'      => 'nullable|string|max:80',
            'end_uf'             => 'nullable|string|size:2',
            'end_cep'            => 'nullable|string|max:12',

            'porte'              => 'required|string|max:10',
            'perfil'             => 'required|string|max:60',
            'perfil_outro'       => 'nullable|string|max:120',

            'capacidade_vagas'   => 'nullable|integer|min:0',

            'mapeamento_itens'                 => 'array',
            'mapeamento_itens.*.tipo'          => 'required_with:mapeamento_itens|string|in:SETOR,BLOCO,ALA,GALERIA,CELA,PORTARIA,GUARITA,MURALHA,CANCELA,PASSARELA,GAIOLA,OUTROS',
            'mapeamento_itens.*.valor'         => 'required_with:mapeamento_itens|string|max:120',
            'mapeamento_itens.*.valor_extenso' => 'nullable|string|max:120',

            'observacoes'        => 'nullable|string',
        ];

        $messages = [
            'required'      => 'O campo :attribute é obrigatório.',
            'required_with' => 'O campo :attribute é obrigatório.',
            'unique'        => 'Já existe uma unidade com esse nome.',
            'integer'       => 'O campo :attribute deve ser um número inteiro.',
            'min'           => 'O campo :attribute deve ser no mínimo :min.',
            'size'          => 'O campo :attribute deve ter :size caracteres.',
            'array'         => 'O campo :attribute deve ser uma lista.',
            'in'            => 'O campo :attribute possui um valor inválido.',
            'max'           => 'O campo :attribute deve ter no máximo :max caracteres.',
        ];

        $attrs = [
            'nome'                     => 'Nome da unidade',
            'sigla'                    => 'Sigla',
            'porte'                    => 'Porte',
            'perfil'                   => 'Perfil da unidade',
            'perfil_outro'             => 'Perfil — outro (qual)',
            'capacidade_vagas'         => 'Número de vagas',
            'mapeamento_itens'         => 'Mapeamento',
            'mapeamento_itens.*.tipo'  => 'Tipo do mapeamento',
            'mapeamento_itens.*.valor' => 'Valor do mapeamento',
        ];

        $data = $request->validate($rules, $messages, $attrs);

        // Normalizações
        $porte = mb_strtoupper(trim((string)($data['porte'] ?? '')), 'UTF-8');
        if ($porte === 'MEDIO') { $porte = 'MÉDIO'; }
        $data['porte'] = in_array($porte, $portes, true) ? $porte : 'MÉDIO';

        $data['perfil'] = mb_strtoupper(trim((string)($data['perfil'] ?? '')), 'UTF-8');
        if ($data['perfil'] !== 'OUTROS') {
            $data['perfil_outro'] = null;
        }

        // Sanitiza mapeamento_itens
        $items = [];
        foreach ((array)($data['mapeamento_itens'] ?? []) as $row) {
            $tipo  = isset($row['tipo'])  ? (string)$row['tipo']  : '';
            $valor = isset($row['valor']) ? (string)$row['valor'] : '';
            $ext   = isset($row['valor_extenso']) ? (string)$row['valor_extenso'] : '';

            $tipo  = mb_strtoupper(trim($tipo),'UTF-8');
            $valor = trim($valor);
            $ext   = trim($ext);

            if ($tipo !== '' && $valor !== '') {
                $items[] = [
                    'tipo'          => mb_substr($tipo,  0, 30,  'UTF-8'),
                    'valor'         => mb_substr($valor, 0, 120, 'UTF-8'),
                    'valor_extenso' => $ext !== '' ? mb_substr($ext, 0, 120, 'UTF-8') : '',
                ];
            }
        }
        $data['mapeamento_itens'] = $items;

        return $data;
    }

    /**
     * Normaliza QUALQUER forma de mapeamento para:
     * [ [ 'tipo'=>'BLOCO', 'valor'=>'1', 'valor_extenso'=>'' ], ... ]
     */
    private function normalizeMapeamento($raw): array
    {
        $push = function(array &$arr, $tipo, $valor, $ext = '') {
            $tipo  = mb_strtoupper(trim((string)$tipo),'UTF-8');
            $valor = trim((string)$valor);
            $ext   = trim((string)$ext);
            if ($tipo !== '' && $valor !== '') {
                $arr[] = ['tipo'=>$tipo,'valor'=>$valor,'valor_extenso'=>$ext];
            }
        };

        $legacy = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $legacy = $decoded;
        } elseif (is_array($raw)) {
            $legacy = $raw;
        } else {
            return [];
        }

        // wrappers {itens:[...]} / {items:[...]}
        if (array_key_exists('itens', $legacy) && is_array($legacy['itens'])) {
            $legacy = $legacy['itens'];
        } elseif (array_key_exists('items', $legacy) && is_array($legacy['items'])) {
            $legacy = $legacy['items'];
        }

        $out = [];
        $isAssocMap = is_array($legacy) && (array_keys($legacy) !== range(0, count($legacy) - 1));

        if ($isAssocMap) {
            foreach ($legacy as $tipo => $vals) {
                if (is_array($vals)) {
                    foreach ($vals as $v) $push($out, $tipo, $v);
                } else {
                    $push($out, $tipo, $vals);
                }
            }
            return $out;
        }

        foreach ((array)$legacy as $row) {
            if (is_array($row) && isset($row['tipo'])) {
                if (isset($row['valor'])) {
                    $push($out, $row['tipo'], $row['valor'], $row['valor_extenso'] ?? '');
                    continue;
                }
                if (isset($row['valores']) && is_array($row['valores'])) {
                    foreach ($row['valores'] as $v) $push($out, $row['tipo'], $v);
                    continue;
                }
            }

            if (is_array($row)) {
                foreach (['setor','bloco','ala','galeria','cela','portaria','guarita','muralha','cancela','passarela','gaiola','outros'] as $k) {
                    if (!array_key_exists($k, $row)) continue;
                    $val = $row[$k];
                    if (is_array($val)) {
                        foreach ($val as $v) $push($out, $k, $v);
                    } else {
                        $push($out, $k, $val);
                    }
                }
            }
        }

        return $out;
    }
}
