<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FichaIndividuoController extends Controller
{
    private string $pessoasTable = 'dados_pessoais';
    private string $docsTable    = 'documentos';
    private string $identTable   = 'identificacoes_visuais';

    public function create($id)
    {
        $id = (int) $id;

        // ===== dados_pessoais obrigatório =====
        if (!Schema::hasTable($this->pessoasTable)) {
            abort(500, 'Tabela dados_pessoais não existe. Rode as migrations.');
        }
        $pessoa = DB::table($this->pessoasTable)->where('id', $id)->first();
        abort_if(!$pessoa, 404);

        // ===== documentos / identificacao (opcionais) =====
        $docs = Schema::hasTable($this->docsTable)
            ? DB::table($this->docsTable)->where('dados_pessoais_id', $id)->first()
            : null;

        $ident = Schema::hasTable($this->identTable)
            ? DB::table($this->identTable)->where('dados_pessoais_id', $id)->first()
            : null;

        // ===== naturalidade (Município/UF; sem barra solta) =====
        $lnCidade = $pessoa->naturalidade_municipio
            ?? $pessoa->naturalidade_cidade
            ?? $pessoa->cidade_nascimento
            ?? null;

        $lnUF = $pessoa->naturalidade_uf
            ?? $pessoa->uf_nascimento
            ?? null;

        if ($lnCidade && $lnUF) {
            $naturalidade = trim($lnCidade) . '/' . trim($lnUF);
        } elseif ($lnCidade) {
            $naturalidade = trim($lnCidade);
        } elseif ($lnUF) {
            $naturalidade = trim($lnUF);
        } else {
            $naturalidade = '';
        }

        // ===== identificação visual (jsons) =====
        $sinais = self::jsonToArray($ident?->sinais_compostos ?? null);
        $bio    = self::jsonToArray($ident?->biometria_json   ?? null);
        $sinaisPorLocal = self::groupSinaisPorLocal($sinais);

        // ===== documentos => pares chave/valor (filtra técnicos) =====
        $docKv = self::keyValuesFlex($docs, [
            'id','dados_pessoais_id','created_at','updated_at','deleted_at',
        ]);

        // ===== usuário atual (com fallback seguro) =====
        [$usuarioNome, $usuarioMatricula] = $this->resolveUsuario();

        // ===== org / cabeçalho =====
        $orgNome      = config('app.org_nome', 'ÓRGÃO / SECRETARIA');
        $orgSubtitulo = config('app.org_subtitulo', 'SISTEMA DE GESTÃO DE CUSTODIADOS');
        $orgBrasaoUrl = config('app.org_brasao_url'); // ex: storage/brasao.png (opcional)

        $geradoEm = now();

        // fotos 3x4
        $fotos = [
            'le'      => $ident?->foto_le_url      ?? null,
            'frontal' => $ident?->foto_frontal_url ?? null,
            'ld'      => $ident?->foto_ld_url      ?? null,
        ];

        // ===== DADOS PESSOAIS: pares na ordem do formulário =====
        $ordem = [
            'nome_completo','nome_social','alcunha','data_nascimento','genero_sexo',
            'mae','pai',
            'nacionalidade','naturalidade_municipio','naturalidade_uf',
            'estado_civil','escolaridade_nivel','escolaridade_situacao','profissao',
            // os campos de endereço serão renderizados como UMA linha "ENDEREÇO" (ver abaixo)
            // 'end_logradouro','end_numero','end_complemento','end_bairro','end_municipio','end_uf','end_cep',
            'telefone_principal','telefones_adicionais','email',
            'obito','data_obito','observacoes',
        ];

        $labels = [
            'nome_completo'          => 'NOME COMPLETO',
            'nome_social'            => 'NOME SOCIAL',
            'alcunha'                => 'ALCUNHA',
            'data_nascimento'        => 'DATA DE NASCIMENTO',
            'genero_sexo'            => 'GÊNERO/SEXO',
            'mae'                    => 'MÃE',
            'pai'                    => 'PAI',
            'nacionalidade'          => 'NACIONALIDADE',
            'naturalidade_municipio' => 'NATURALIDADE — MUNICÍPIO',
            'naturalidade_uf'        => 'NATURALIDADE — UF',
            'estado_civil'           => 'ESTADO CIVIL',
            'escolaridade_nivel'     => 'ESCOLARIDADE — NÍVEL',
            'escolaridade_situacao'  => 'ESCOLARIDADE — SITUAÇÃO',
            'profissao'              => 'PROFISSÃO', // << logo após escolaridade_situacao
            'telefone_principal'     => 'TELEFONE PRINCIPAL',
            'telefones_adicionais'   => 'TELEFONES ADICIONAIS',
            'email'                  => 'E-MAIL',
            'obito'                  => 'ÓBITO',
            'data_obito'             => 'DATA DO ÓBITO',
            'observacoes'            => 'OBSERVAÇÕES (DADOS PESSOAIS)',
        ];

        // Campos que já aparecem no RESUMO para não duplicar
        $resumoKeys = ['nome_completo', 'data_nascimento', 'naturalidade_municipio', 'naturalidade_uf'];

        $pairs = [];
        foreach ($ordem as $key) {
            if (in_array($key, $resumoKeys, true)) continue;             // não repetir o resumo
            if (!property_exists($pessoa, $key)) continue;

            $val = $pessoa->$key;
            if ($val === null || $val === '') continue;

            // Formatações
            if (in_array($key, ['data_nascimento','data_obito'], true)) {
                try { $val = Carbon::parse($val)->format('d/m/Y'); } catch (\Throwable $e) {}
            }
            if ($key === 'obito') {
                $val = (int)$val ? 'SIM' : 'NÃO';
            }
            if ($key === 'telefones_adicionais') {
                $try = is_string($val) ? json_decode($val, true) : (is_array($val) ? $val : null);
                if (is_array($try)) {
                    $flat = array_values(array_filter(array_map('strval', $try)));
                    $val = $flat ? implode(', ', $flat) : null;
                    if ($val === null) continue;
                }
            }

            $pairs[$labels[$key] ?? mb_strtoupper(str_replace('_',' ', $key), 'UTF-8')] = $val;
        }

        // ===== ENDEREÇO em UMA LINHA =====
        $logradouro  = trim((string)($pessoa->end_logradouro   ?? ''));
        $numero      = trim((string)($pessoa->end_numero       ?? ''));
        $complemento = trim((string)($pessoa->end_complemento  ?? ''));
        $bairro      = trim((string)($pessoa->end_bairro       ?? ''));
        $mun         = trim((string)($pessoa->end_municipio    ?? ''));
        $uf          = trim((string)($pessoa->end_uf           ?? ''));
        $cep         = trim((string)($pessoa->end_cep          ?? ''));

        $endParts = [];
        if ($logradouro !== '') {
            $linha = $logradouro;
            if ($numero !== '')      $linha .= ', Nº ' . $numero;
            if ($complemento !== '') $linha .= ', ' . $complemento;
            $endParts[] = $linha;
        }
        if ($bairro !== '') {
            $endParts[] = 'BAIRRO ' . $bairro;
        }
        $cidadeUf = '';
        if ($mun !== '') $cidadeUf = $mun;
        if ($uf  !== '') $cidadeUf .= ($cidadeUf ? '/' : '') . $uf;
        if ($cidadeUf !== '') $endParts[] = $cidadeUf;
        if ($cep !== '')      $endParts[] = 'CEP ' . $cep;

        if ($endParts) {
            // rótulo "ENDEREÇO" com o valor completo; a quebra é automática pelo CSS
            $pairs['ENDEREÇO'] = implode(' - ', $endParts);
        }

        // ===== “Extras” (campos do formulário fora da ordem) =====
        $skipTech = [
            'id','cadpen','cadpen_number','wizard_stage','is_draft',
            'created_at','updated_at','created_by','updated_by',
            'canceled_at','canceled_by',
            // já mapeados acima
            'end_logradouro','end_numero','end_complemento','end_bairro','end_municipio','end_uf','end_cep',
        ];
        $pessoalExtras = [];
        foreach ((array)$pessoa as $k => $v) {
            if (in_array($k, $skipTech, true)) continue;
            if (in_array($k, $ordem, true))   continue;
            if ($v === null || $v === '')     continue;
            $pessoalExtras[mb_strtoupper(str_replace('_',' ', $k), 'UTF-8')] = $v;
        }

        // grupos para a view
        $pessoalGrupos = ['DADOS PESSOAIS' => $pairs];

        return view('gestao.fichaindividuocreate', [
            'pessoa'          => $pessoa,
            'docs'            => $docs,
            'docKv'           => $docKv,
            'ident'           => $ident,
            'sinais'          => $sinais,
            'sinaisPorLocal'  => $sinaisPorLocal,
            'bio'             => $bio,
            'fotos'           => $fotos,
            'naturalidade'    => $naturalidade,

            // cabeçalho/rodapé
            'usuarioNome'      => $usuarioNome,
            'usuarioMatricula' => $usuarioMatricula,
            'orgNome'          => $orgNome,
            'orgSubtitulo'     => $orgSubtitulo,
            'orgBrasaoUrl'     => $orgBrasaoUrl,
            'geradoEm'         => $geradoEm,

            'pessoalGrupos'    => $pessoalGrupos,
            'pessoalExtras'    => $pessoalExtras,
        ]);
    }

    private function resolveUsuario(): array
    {
        try {
            $guard = config('auth.defaults.guard', 'web');
            $user = Auth::guard($guard)->user();
        } catch (\Throwable $e) {
            $user = null;
        }

        $nome = $user->name
            ?? $user->nome
            ?? $user->email
            ?? 'USUÁRIO';

        $matr = $user->matricula
            ?? $user->registration
            ?? null;

        return [$nome, $matr];
    }

    // =========== helpers ===========

    private static function jsonToArray($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }

    /** Agrupa [{local,tipo,descricao}] por local */
    private static function groupSinaisPorLocal(array $sinais): array
    {
        $out = [];
        foreach ($sinais as $it) {
            $local = strtoupper((string)($it['local'] ?? ''));
            if ($local === '') continue;
            $out[$local] = $out[$local] ?? [];
            $out[$local][] = [
                'tipo'      => $it['tipo']      ?? null,
                'descricao' => $it['descricao'] ?? null,
            ];
        }
        ksort($out, SORT_NATURAL);
        return $out;
    }

    /** Converte qualquer objeto em pares chave=>valor (ignorando técnicos) */
    private static function keyValuesFlex($obj, array $skip = []): array
    {
        if (!$obj) return [];
        $kv = [];
        foreach ((array)$obj as $k => $v) {
            if (in_array($k, $skip, true)) continue;
            $kv[self::humanize($k)] = $v;
        }
        ksort($kv, SORT_NATURAL | SORT_FLAG_CASE);
        return $kv;
    }

    private static function humanize(string $key): string
    {
        $s = str_replace(['_', '-'], ' ', $key);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        return mb_strtoupper($s, 'UTF-8');
    }
}
