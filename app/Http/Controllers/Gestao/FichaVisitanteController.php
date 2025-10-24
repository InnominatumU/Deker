<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class FichaVisitanteController extends Controller
{
    // Tabelas auxiliares para resolver nome/CadPen dos vínculos (opcionais)
    private ?string $tbPessoas    = 'dados_pessoais';
    private ?string $tbIndividuos = 'individuos';

    /**
     * Lançador/Busca: GET /gestao/visitantes/ficha?cpf=...&id=...
     * - Busca principal: CPF (sem máscara)
     * - Fallback: ID
     * - Se encontrar, redireciona para a rota da ficha; senão, exibe a view em modo "search"
     */
    public function index(Request $request)
    {
        $cpf = trim((string) $request->query('cpf', ''));
        $id  = trim((string) $request->query('id', ''));

        if ($cpf !== '' || $id !== '') {
            $q = DB::table('visitantes');

            if ($cpf !== '') {
                $digits = preg_replace('/\D+/', '', $cpf);
                // compara CPF normalizado (sem ., -, /)
                $q->orWhereRaw("REPLACE(REPLACE(REPLACE(IFNULL(cpf,''),'.',''),'-',''),'/','') = ?", [$digits]);
            }

            if ($id !== '') {
                $q->orWhere('id', (int) $id);
            }

            $v = $q->first();

            if ($v) {
                return redirect()->route('gestao.visitantes.ficha', ['id' => $v->id]);
            }

            // não encontrado -> mostra a view em modo busca com mensagem
            return view('gestao.fichavisitacreate', [
                'mode'  => 'search',
                'error' => 'Visitante não encontrado.',
            ]);
        }

        // primeira entrada (sem parâmetros) -> mostra apenas a busca
        return view('gestao.fichavisitacreate', ['mode' => 'search']);
    }

    /**
     * Ficha: GET /gestao/visitantes/{id}/ficha
     * Renderiza a ficha A4 (visualização/print) no mesmo padrão da ficha do indivíduo.
     */
    public function create(int $id)
    {
        $visitante = DB::table('visitantes')->where('id', $id)->first();
        abort_if(!$visitante, 404, 'Visitante não encontrado.');

        // Visitas do visitante (mais recentes primeiro)
        $visitas = DB::table('visitas as v')
            ->select(
                'v.id','v.tipo','v.destino','v.unidade_id',
                'v.religiao','v.autoridade_cargo','v.autoridade_orgao',
                'v.descricao_outros','v.observacoes','v.created_at','v.updated_at'
            )
            ->where('v.visitante_id', $id)
            ->orderByDesc('v.created_at')
            ->get();

        // Unidades mapeadas (se existir a tabela)
        $unidadesById = [];
        if (Schema::hasTable('unidades')) {
            $unidadesById = DB::table('unidades')->pluck('nome','id')->toArray();
        }

        // Vínculos por visita
        $vinculosPorVisita = [];
        if ($visitas->isNotEmpty()) {
            $vinc = DB::table('visita_individuo')
                ->whereIn('visita_id', $visitas->pluck('id')->all())
                ->orderBy('id')
                ->get();

            foreach ($vinc as $row) {
                $nome = null; $cadpen = null;

                // 1) dados_pessoais
                if ($this->tbPessoas && Schema::hasTable($this->tbPessoas)) {
                    $p = DB::table($this->tbPessoas)->where('id', $row->individuo_id)->first();
                    if ($p) {
                        $nome   = $p->nome ?? ($p->nome_completo ?? ($p->nome_social ?? null));
                        $cadpen = $p->cadpen ?? (Schema::hasColumn($this->tbPessoas,'registro_interno') ? ($p->registro_interno ?? null) : null);
                    }
                }

                // 2) fallback: individuos
                if (!$nome && $this->tbIndividuos && Schema::hasTable($this->tbIndividuos)) {
                    $i = DB::table($this->tbIndividuos)->where('id', $row->individuo_id)->first();
                    if ($i) {
                        $nome   = $i->nome ?? ($i->nome_completo ?? ($i->nome_social ?? null));
                        $cadpen = $i->cadpen ?? ($i->registro_interno ?? null);
                    }
                }

                $vinculosPorVisita[$row->visita_id][] = [
                    'individuo_id' => $row->individuo_id,
                    'parentesco'   => $row->parentesco,
                    'nome'         => $nome,
                    'cadpen'       => $cadpen,
                ];
            }
        }

        // Cabeçalho/rodapé: usuário e org
        [$usuarioNome, $usuarioMatricula] = $this->resolveUsuario();
        $orgNome      = config('app.org_nome', 'ÓRGÃO / SECRETARIA');
        $orgSubtitulo = config('app.org_subtitulo', 'SISTEMA DE GESTÃO DE CUSTODIADOS');
        $orgBrasaoUrl = config('app.org_brasao_url'); // ex: storage/brasao.png
        $geradoEm     = now();

        // Dicionários (labels amigáveis)
        $tipos = [
            'SOCIAL'      => 'VISITA SOCIAL',
            'ASSISTIDA'   => 'VISITA ASSISTIDA',
            'JURIDICA'    => 'VISITA JURÍDICA (ADVOGADO)',
            'RELIGIOSA'   => 'VISITA RELIGIOSA',
            'AUTORIDADES' => 'VISITA DE AUTORIDADES',
            'OUTRAS'      => 'OUTRAS VISITAS',
        ];
        $destinos = ['INDIVIDUOS' => 'INDIVÍDUO(S)', 'UNIDADE' => 'UNIDADE'];

        return view('gestao.fichavisitacreate', [
            'mode'              => 'show',
            'visitante'         => $visitante,
            'visitas'           => $visitas,
            'unidadesById'      => $unidadesById,
            'vinculosPorVisita' => $vinculosPorVisita,

            // cabeçalho/rodapé
            'usuarioNome'       => $usuarioNome,
            'usuarioMatricula'  => $usuarioMatricula,
            'orgNome'           => $orgNome,
            'orgSubtitulo'      => $orgSubtitulo,
            'orgBrasaoUrl'      => $orgBrasaoUrl,
            'geradoEm'          => $geradoEm,

            // dicionários
            'tipos'             => $tipos,
            'destinos'          => $destinos,
        ]);
    }

    private function resolveUsuario(): array
    {
        try { $user = Auth::user(); } catch (\Throwable $e) { $user = null; }

        $nome = $user->name
            ?? $user->nome
            ?? $user->email
            ?? 'USUÁRIO';

        $matr = $user->matricula
            ?? $user->registration
            ?? null;

        return [$nome, $matr];
    }
}
