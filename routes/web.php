<?php
// routes/web.php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* ================== CONTROLLERS ================== */

// Gestão (Indivíduos)
use App\Http\Controllers\Gestao\DadosController;
use App\Http\Controllers\Gestao\DocumentosController;
use App\Http\Controllers\Gestao\IdentificacaoVisualController;
use App\Http\Controllers\Gestao\FichaIndividuoController;
use App\Http\Controllers\Gestao\VisitasController;
use App\Http\Controllers\Gestao\FichaVisitaController;
use App\Http\Controllers\Gestao\FichaVisitanteController;

// Sistema (Usuários / Unidades)
use App\Http\Controllers\Sistema\UsuariosController;
use App\Http\Controllers\Sistema\UnidadesController;

// Frota
use App\Http\Controllers\Frota\FrotaController;

// Servidores
use App\Http\Controllers\Servidores\ServidoresController;
use App\Http\Controllers\Servidores\FrequenciaController;

// Calendário
use App\Http\Controllers\Calendario\CalendarioController;

// Ciclo de Vínculos (NOVO)
use App\Http\Controllers\Ciclo\AdmissoesController;

require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
*/

// Se não logado -> /login; se logado -> /inicio
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('inicio')
        : redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Rotas autenticadas
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // ===== Início (liberada para todos autenticados) =====
    Route::view('/inicio', 'inicio.index')->name('inicio');

    // ===== Menus (casca) — protegidos por Gates específicos =====
    Route::view('/ciclo',        'menus.ciclo.index')->middleware('can:menu.ciclo')->name('ciclo.index');
    Route::view('/atendimentos', 'menus.atendimentos.index')->middleware('can:menu.atendimentos')->name('atendimentos.index');

    // (corrigido: nomes)
    Route::view('/relatorios',   'menus.relatorios.index')->middleware('can:menu.relatorios')->name('relatorios.index');
    Route::view('/sobre',        'menus.sobre.index')->middleware('can:menu.sobre')->name('sobre.index');
    // (não criamos casca para /frota e /servidores; menus abrem dropdowns diretamente)

    /* ================== RELATÓRIOS (SUBMENUS) ================== */
    Route::prefix('relatorios')
        ->name('relatorios.')
        ->middleware('can:menu.relatorios')
        ->group(function () {
            Route::get('/cadeia', fn () => response('Relatórios: Cadeia de Custódia (em construção)'))
                ->name('cadeia.index');

            Route::prefix('atendimentos')->name('atendimentos.')->group(function () {
                Route::get('/internos', fn () => response('Relatórios: Atendimentos Internos (em construção)'))
                    ->name('internos.index');
                Route::get('/externos', fn () => response('Relatórios: Atendimentos Externos (em construção)'))
                    ->name('externos.index');
            });
        });

    /* ================== ATENDIMENTOS TÉCNICOS (SUBMENUS) ================== */
    Route::prefix('atendimentos')
        ->name('atendimentos.')
        ->middleware('can:menu.atendimentos')
        ->group(function () {
            Route::get('/interno', fn () => response('Atendimentos Técnicos — Interno (em construção)'))
                ->name('interno.index');
            Route::get('/externo', fn () => response('Atendimentos Técnicos — Externo (em construção)'))
                ->name('externo.index');
        });

    /* ================== CICLO DE VÍNCULOS (SUBMENUS) ================== */
    Route::prefix('ciclo')
        ->name('ciclo.')
        ->middleware('can:menu.ciclo')
        ->group(function () {
            // View principal da Admissão (agora aponta para a sua blade pronta)
            Route::view('/admissao', 'ciclo.admissao')->name('admissao.index');

            // CRUD mínimo de admissões (lista simples + store)
            Route::get('/admissoes', [AdmissoesController::class, 'index'])->name('admissoes.index');
            Route::post('/admissoes', [AdmissoesController::class, 'store'])->name('admissoes.store');

            // Demais submenus (mantidos como placeholders)
            Route::get('/objetos', fn () => response('Ciclo: Objetos (em construção)'))
                ->name('objetos.index');
            Route::get('/mov-interna', fn () => response('Ciclo: Movimentação Interna (em construção)'))
                ->name('movimentacao.interna.index');
            Route::get('/mov-externa', fn () => response('Ciclo: Movimentação Externa (em construção)'))
                ->name('movimentacao.externa.index');
            Route::get('/visitacao', fn () => response('Ciclo: Visitação (em construção)'))
                ->name('visitacao.index');
        });

    /* ================== AJAX (Indivíduos) ================== */
    // Handler compartilhado para compatibilidade com /ajax/... e /api/...
    $ajaxFindByCadpen = function (string $cadpen) {
        $raw  = trim($cadpen);
        $norm = strtoupper($raw);

        $q = DB::table('dados_pessoais');

        // 1) Match exato em cadpen
        $registro = (clone $q)->where('cadpen', '=', $norm)->first();

        // 2) Só dígitos -> cadpen_number
        if (!$registro && ctype_digit($norm)) {
            $registro = (clone $q)->where('cadpen_number', '=', (int)$norm)->first();
        }

        // 3) Sem hífen, com 10+ dígitos -> YYYY-xxxxxx
        if (!$registro && preg_match('/^[0-9]{10,}$/', $norm)) {
            $year   = substr($norm, 0, 4);
            $number = substr($norm, 4);
            if (ctype_digit($year) && ctype_digit($number)) {
                $formatted = sprintf('%s-%06d', $year, (int)$number);
                $registro  = (clone $q)->where('cadpen', '=', $formatted)->first();
            }
        }

        // 4) Sufixo "-%06d" (ex.: 123 -> "-000123")
        if (!$registro && ctype_digit($norm) && strlen($norm) <= 6) {
            $suffix   = sprintf('-%06d', (int)$norm);
            $registro = (clone $q)->where('cadpen', 'like', '%' . $suffix)->first();
        }

        if (!$registro) {
            return response()->json(['message' => 'not found'], 404);
        }

        // FOTO FRONTAL — tabela correta (PLURAL): identificacoes_visuais
        $fotoUrl = null;
        if (Schema::hasTable('identificacoes_visuais')) {
            $foto = DB::table('identificacoes_visuais')
                ->where('dados_pessoais_id', $registro->id)
                ->select('foto_frontal_url')
                ->first();

            // handleUploads salva como URL completa (http://.../storage/...)
            $fotoUrl = $foto->foto_frontal_url ?? null;
        }

        return response()->json([
            'id'               => $registro->id,
            'cadpen'           => $registro->cadpen,
            'nome_completo'    => $registro->nome_completo ?? null,
            'mae'              => $registro->mae ?? null,
            'pai'              => $registro->pai ?? null,
            'nome_mae'         => $registro->mae ?? null, // compat c/ blade
            'nome_pai'         => $registro->pai ?? null, // compat c/ blade
            'data_nascimento'  => $registro->data_nascimento ?? null,
            'foto_frontal_url' => $fotoUrl,
        ]);
    };

    // Compatível com o fetch antigo (/api/...) e o novo (/ajax/...)
    Route::get('ajax/individuos/by-cadpen/{cadpen}', $ajaxFindByCadpen)->name('ajax.individuos.by_cadpen');
    Route::get('api/individuos/by-cadpen/{cadpen}',  $ajaxFindByCadpen)->name('api.individuos.by_cadpen');

    /* ================== GESTÃO DE INDIVÍDUOS (módulo) ================== */
    Route::prefix('gestao')
        ->name('gestao.')
        ->middleware('can:menu.individuos')
        ->group(function () {

            /* ---------- DADOS PESSOAIS ---------- */

            // Edit SEM {id}: abre BUSCA na própria tela de editar
            Route::get('dados/edit', [DadosController::class, 'search'])->name('dados.search');

            Route::get('dados/create',       [DadosController::class, 'create'])->name('dados.create');
            Route::post('dados',             [DadosController::class, 'store'])->name('dados.store');
            Route::get('dados/{id}/edit',    [DadosController::class, 'edit'])->whereNumber('id')->name('dados.edit');
            Route::put('dados/{id}',         [DadosController::class, 'update'])->whereNumber('id')->name('dados.update');

            /* ---------- DOCUMENTOS ---------- */

            // Editar sem ID usa a BUSCA dos dados
            Route::get('documentos/edit', function () {
                return redirect()->route('gestao.dados.search', ['for' => 'documentos']);
            })->name('documentos.search');

            Route::get('documentos/create',    [DocumentosController::class, 'create'])->name('documentos.create'); // aceita ?id=
            Route::post('documentos',          [DocumentosController::class, 'store'])->name('documentos.store');
            Route::get('documentos/{id}/edit', [DocumentosController::class, 'edit'])->whereNumber('id')->name('documentos.edit');
            Route::put('documentos/{id}',      [DocumentosController::class, 'update'])->whereNumber('id')->name('documentos.update');

            /* ---------- IDENTIFICAÇÃO VISUAL ---------- */

            // Editar sem ID usa BUSCA
            Route::get('identificacao/visual/edit', function () {
                return redirect()->route('gestao.dados.search', ['for' => 'identificacao']);
            })->name('identificacao.visual.search');

            Route::get('identificacao/visual/create',    [IdentificacaoVisualController::class, 'create'])->name('identificacao.visual.create'); // aceita ?id=
            Route::post('identificacao/visual',          [IdentificacaoVisualController::class, 'store'])->name('identificacao.visual.store');
            Route::get('identificacao/visual/{id}/edit', [IdentificacaoVisualController::class, 'edit'])->whereNumber('id')->name('identificacao.visual.edit');
            Route::put('identificacao/visual/{id}',      [IdentificacaoVisualController::class, 'update'])->whereNumber('id')->name('identificacao.visual.update');

            /* ---------- FICHA DO INDIVÍDUO ---------- */

            // Acesso inicial da Ficha -> manda para a busca com o filtro "for=ficha"
            Route::get('ficha', function () {
                return redirect()->route('gestao.dados.search', ['for' => 'ficha']);
            })->name('ficha.index');

            // Ficha detalhada (A4) - por ID (somente visualização/print)
            Route::get('ficha/individuo/{id}/create', [FichaIndividuoController::class, 'create'])
                ->whereNumber('id')
                ->name('ficha.individuo.create');

            // Alias opcional sem "/create" (mesma action)
            Route::get('ficha/individuo/{id}', [FichaIndividuoController::class, 'create'])
                ->whereNumber('id')
                ->name('ficha.individuo.show');

            /* ---------- FICHA DO VISITANTE (LANÇADOR + FICHA) ---------- */

            // Lançador (busca por CPF/ID)
            Route::get('visitantes/ficha', [FichaVisitanteController::class, 'index'])
                ->name('visitantes.ficha.index');

            // Ficha A4 (exibição/print) — usa o método create($id) do controller
            Route::get('visitantes/{id}/ficha', [FichaVisitanteController::class, 'create'])
                ->whereNumber('id')
                ->name('visitantes.ficha');

            /* ---------- CADASTRO DE VISITAS (com search para não quebrar menu) ---------- */
            Route::prefix('visitas')->name('visitas.')->group(function () {
                // Link atual do menu (placeholder)
                Route::get('search', [VisitasController::class, 'search'])
                    ->name('search');

                // AJAX (buscar por CadPen)
                Route::get('ajax/find-individuo', [VisitasController::class, 'ajaxFindIndividuo'])
                    ->name('ajax.find_individuo');

                // CRUD principal
                Route::get('create',       [VisitasController::class, 'create'])->name('create');
                Route::post('',            [VisitasController::class, 'store'])->name('store');
                Route::get('{id}/edit',    [VisitasController::class, 'edit'])->whereNumber('id')->name('edit');
                Route::put('{id}',         [VisitasController::class, 'update'])->whereNumber('id')->name('update');
            });
        });

    /* ================== GESTÃO DO SISTEMA (Usuários / Unidades) ================== */
    Route::prefix('sistema')
        ->name('sistema.')
        ->middleware('can:menu.sistema')
        ->group(function () {

            /* ---------- USUÁRIOS (sem index) ---------- */

            // Edit/Buscar sem {id}
            Route::get('usuarios/edit',   [UsuariosController::class, 'search'])->name('usuarios.search');

            // Create/Store
            Route::get('usuarios/create', [UsuariosController::class, 'create'])->name('usuarios.create');
            Route::post('usuarios',       [UsuariosController::class, 'store'])->name('usuarios.store');

            // Edit/Update por ID
            Route::get('usuarios/{usuario}/edit', [UsuariosController::class, 'edit'])->name('usuarios.edit');
            Route::put('usuarios/{usuario}',      [UsuariosController::class, 'update'])->name('usuarios.update');

            // Destroy
            Route::delete('usuarios/{usuario}',   [UsuariosController::class, 'destroy'])->name('usuarios.destroy');

            /* ---------- UNIDADES (sem index) ---------- */

            // Edit/Buscar sem {id}
            Route::get('unidades/edit',   [UnidadesController::class, 'search'])->name('unidades.search');

            // Create/Store
            Route::get('unidades/create', [UnidadesController::class, 'create'])->name('unidades.create');
            Route::post('unidades',       [UnidadesController::class, 'store'])->name('unidades.store');

            // Edit/Update por ID
            Route::get('unidades/{unidade}/edit', [UnidadesController::class, 'edit'])->name('unidades.edit');
            Route::put('unidades/{unidade}',      [UnidadesController::class, 'update'])->name('unidades.update');

            // Destroy
            Route::delete('unidades/{unidade}',   [UnidadesController::class, 'destroy'])->name('unidades.destroy');
        });

    /* ================== CALENDÁRIO (feriados & pontos facultativos) ================== */
    Route::prefix('calendario')
        ->name('calendario.')
        ->middleware('can:menu.sistema')
        ->group(function () {
            Route::get('/',           [CalendarioController::class, 'index'])->name('index');               // visão anual/mensal
            Route::post('/',          [CalendarioController::class, 'store'])->name('store');               // adicionar evento
            Route::delete('/{id}',    [CalendarioController::class, 'destroy'])->whereNumber('id')->name('destroy'); // remover evento
        });

    // ===== JSON público (apenas autenticado) para a frequência: dias úteis/feriados do mês =====
    // OBS.: fora do can:menu.sistema para que quem lança frequência possa consumir o endpoint.
    Route::get('calendario/{ano}/{mes}', [CalendarioController::class, 'mesInfo'])
        ->whereNumber('ano')->whereNumber('mes')
        ->name('calendario.mes-info');

    /* ================== FROTA (módulo dedicado) ================== */
    Route::prefix('frota')
        ->name('frota.')
        ->middleware('can:menu.frota')
        ->group(function () {

            // Veículos — Cadastrar / Buscar / Editar
            Route::get('veiculos/create',    [FrotaController::class, 'create'])->name('veiculos.create');
            Route::post('veiculos',          [FrotaController::class, 'store'])->name('veiculos.store');
            Route::get('veiculos/edit',      [FrotaController::class, 'search'])->name('veiculos.search');
            Route::get('veiculos/{id}/edit', [FrotaController::class, 'edit'])->whereNumber('id')->name('veiculos.edit');
            Route::put('veiculos/{id}',      [FrotaController::class, 'update'])->whereNumber('id')->name('veiculos.update');
            Route::delete('veiculos/{id}',   [FrotaController::class, 'destroy'])->whereNumber('id')->name('veiculos.destroy');

            // Submenus operacionais
            Route::get('uso',  [FrotaController::class, 'uso'])->name('veiculos.uso');
            Route::post('uso', [FrotaController::class, 'usoStore'])->name('veiculos.uso.store');

            Route::get('abastecimentos',  [FrotaController::class, 'abastecimentos'])->name('veiculos.abastecimentos');
            Route::post('abastecimentos', [FrotaController::class, 'abastecimentosStore'])->name('veiculos.abastecimentos.store');

            Route::get('deslocamentos',  [FrotaController::class, 'deslocamentos'])->name('veiculos.deslocamentos');
            Route::post('deslocamentos', [FrotaController::class, 'deslocamentosStore'])->name('veiculos.deslocamentos.store');

            Route::get('manutencoes',  [FrotaController::class, 'manutencoes'])->name('veiculos.manutencoes');
            Route::post('manutencoes', [FrotaController::class, 'manutencoesStore'])->name('veiculos.manutencoes.store');

            Route::get('documentos',  [FrotaController::class, 'documentos'])->name('veiculos.documentos');
            Route::post('documentos', [FrotaController::class, 'documentosStore'])->name('veiculos.documentos.store');

            Route::get('relatorios',     [FrotaController::class, 'relatorios'])->name('veiculos.relatorios');
            Route::get('relatorios/run', [FrotaController::class, 'relatoriosRun'])->name('veiculos.relatorios.run');

            // (LEGADO) Operações unificadas — mantido para não quebrar links existentes
            Route::get('operacoes',  [FrotaController::class, 'operacoesIndex'])->name('operacoes.index');
            Route::post('operacoes', [FrotaController::class, 'operacoesStore'])->name('operacoes.store');
        });

    /* ================== SERVIDORES (módulo dedicado) ================== */
    Route::prefix('servidores')
        ->name('servidores.')
        ->middleware('can:menu.servidores')
        ->group(function () {

            // Configurar Carga Horária (formulário de catálogos locais)
            Route::view('carga-horaria', 'servidores.cargahoraria')->name('cargahoraria.index');

            // Listagem principal
            Route::get('/', [ServidoresController::class, 'index'])->name('index');

            // Tela de busca/edição (sem ID)
            Route::get('edit', [ServidoresController::class, 'search'])->name('search');

            // Busca dedicada para FREQUÊNCIA (sem ID) — apenas view
            Route::view('frequencia', 'servidores.servidoresfrequencia')->name('frequencia.search');

            // Cadastrar servidor
            Route::get('create', [ServidoresController::class, 'create'])->name('create');
            Route::post('',      [ServidoresController::class, 'store'])->name('store');

            // Editar / Atualizar / Excluir
            Route::get('{id}/edit', [ServidoresController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('{id}',      [ServidoresController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('{id}',   [ServidoresController::class, 'destroy'])->whereNumber('id')->name('destroy');

            // Transferência de Unidade
            Route::post('{id}/transferir', [ServidoresController::class, 'transferir'])
                ->whereNumber('id')
                ->name('transferir');

            /* ---------- FREQUÊNCIA (UNIFICADA COM SALVAR/JSON NO FrequenciaController) ---------- */

            // GET da folha individual — usado por views que chamam route('servidores.frequencia.show', $id)
            Route::get('{id}/frequencia', [ServidoresController::class, 'frequenciaShow'])
                ->whereNumber('id')
                ->name('frequencia.show');

            // DELETE de um lançamento específico — usado por views via route('servidores.frequencia.destroy', [$id, $freqId])
            Route::delete('{id}/frequencia/{freqId}', [ServidoresController::class, 'frequenciaDestroy'])
                ->whereNumber('id')
                ->whereNumber('freqId')
                ->name('frequencia.destroy');

            // POST dos segmentos (front usa: route('servidores.frequencia.store', $id))
            Route::post('{servidor}/frequencia', [FrequenciaController::class, 'store'])
                ->whereNumber('servidor')
                ->name('frequencia.store');

            // GET JSON do mês (front usa: /servidores/{id}/frequencia/json?mes=YYYY-MM)
            Route::get('{servidor}/frequencia/json', [FrequenciaController::class, 'json'])
                ->whereNumber('servidor')
                ->name('frequencia.json');
        });

});
