<?php

namespace App\Http\Controllers\Calendario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CalendarioController extends Controller
{
    /** Mapeia nomes PT-BR dos meses (1..12) */
    private array $meses = [
        1=>'JANEIRO',2=>'FEVEREIRO',3=>'MARÇO',4=>'ABRIL',5=>'MAIO',6=>'JUNHO',
        7=>'JULHO',8=>'AGOSTO',9=>'SETEMBRO',10=>'OUTUBRO',11=>'NOVEMBRO',12=>'DEZEMBRO'
    ];

    /** Cabeçalhos de dias (Dom..Sáb) */
    private array $diasCab = ['DOM','SEG','TER','QUA','QUI','SEX','SÁB'];

    /** Tipos suportados */
    private array $tipos = ['PONTO_FACULTATIVO','FERIADO_MUNICIPAL','FERIADO_ESTADUAL','FERIADO_FEDERAL'];

    public function index(Request $request)
    {
        $ano = (int) ($request->query('ano') ?: now()->year);
        if ($ano < 2000) $ano = 2000;
        if ($ano > 2100) $ano = 2100;

        $mes = $request->query('mes');
        $mes = $mes ? max(1, min(12, (int)$mes)) : null;

        // Busca eventos do ano inteiro
        $eventosAno = DB::table('calendario_feriados')
            ->whereYear('data', $ano)
            ->orderBy('data')
            ->get()
            ->groupBy(function($r){ return (new Carbon($r->data))->toDateString(); }); // 'Y-m-d' => [events]

        // Mapa rápido por data
        $mapEventos = [];
        foreach ($eventosAno as $data => $rows) {
            $mapEventos[$data] = $rows->all();
        }

        // Monta estrutura para 1 mês (visão mensal) ou 12 meses (ano)
        if ($mes) {
            $cal = $this->buildMonthMatrix($ano, $mes, $mapEventos);
            return view('calendario.index', [
                'ano'        => $ano,
                'mes'        => $mes,
                'mesLabel'   => $this->meses[$mes],
                'diasCab'    => $this->diasCab,
                'matrix'     => $cal['matrix'],
                'eventosMes' => $cal['eventosMes'],
                'tipos'      => $this->tipos,
            ]);
        }

        // Ano completo (mini-calendários)
        $meses = [];
        for ($m=1; $m<=12; $m++) {
            $cal = $this->buildMonthMatrix($ano, $m, $mapEventos);
            $meses[] = [
                'num'     => $m,
                'label'   => $this->meses[$m],
                'matrix'  => $cal['matrix'],
                'temEv'   => count($cal['eventosMes']) > 0,
            ];
        }

        return view('calendario.index', [
            'ano'     => $ano,
            'mes'     => null,
            'meses'   => $meses,
            'diasCab' => $this->diasCab,
            'tipos'   => $this->tipos,
        ]);
    }

    /** Cadastrar um evento (feriado/ponto facultativo) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'data'  => ['required','date'],
            'tipo'  => ['required', Rule::in($this->tipos)],
            'titulo'=> ['nullable','string','max:120'],
        ]);

        // Evita duplicado do mesmo tipo na mesma data
        $exists = DB::table('calendario_feriados')
            ->whereDate('data', $data['data'])
            ->where('tipo', $data['tipo'])
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Já existe este tipo de evento na data informada.');
        }

        DB::table('calendario_feriados')->insert([
            'data'       => $data['data'],
            'tipo'       => $data['tipo'],
            'titulo'     => $data['titulo'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $d = Carbon::parse($data['data']);
        return redirect()->route('calendario.index', ['ano'=>$d->year,'mes'=>$d->month])
            ->with('success', 'Evento cadastrado com sucesso.');
    }

    /** Remover um evento pelo ID */
    public function destroy(int $id)
    {
        $row = DB::table('calendario_feriados')->where('id',$id)->first();
        if (!$row) return back()->with('error','Evento não encontrado.');

        DB::table('calendario_feriados')->where('id',$id)->delete();

        $d = Carbon::parse($row->data);
        return redirect()->route('calendario.index', ['ano'=>$d->year,'mes'=>$d->month])
            ->with('success','Evento removido.');
    }

    /**
     * JSON para a frequência: devolve feriados e pontos facultativos do mês/ano.
     * GET /calendario/{ano}/{mes}
     * Resposta: { feriados: ["YYYY-MM-DD", ...], facultativos: ["YYYY-MM-DD", ...] }
     */
    public function mesInfo(int $ano, int $mes)
    {
        if ($ano < 1900 || $ano > 2100 || $mes < 1 || $mes > 12) {
            abort(404);
        }

        $ini = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = (clone $ini)->endOfMonth()->endOfDay();

        $feriados = [];
        $facult   = [];

        // Lê da sua tabela principal 'calendario_feriados'
        $rows = DB::table('calendario_feriados')
            ->select('data','tipo')
            ->whereBetween('data', [$ini->toDateString(), $fim->toDateString()])
            ->get();

        foreach ($rows as $r) {
            $d = (string) $r->data;
            $t = strtoupper((string) $r->tipo);
            if ($t === 'PONTO_FACULTATIVO') {
                $facult[] = $d;
            } elseif (str_starts_with($t, 'FERIADO_')) {
                $feriados[] = $d;
            }
        }

        // Normaliza e ordena
        $feriados = array_values(array_unique($feriados));
        sort($feriados);
        $facult   = array_values(array_unique($facult));
        sort($facult);

        return response()->json([
            'feriados'     => $feriados,
            'facultativos' => $facult,
        ]);
    }

    /**
     * Gera a matriz de um mês (linhas=semanas, 7 colunas domingo..sábado),
     * incluindo marcações de eventos e flags de fim de semana.
     */
    private function buildMonthMatrix(int $ano, int $mes, array $mapEventos): array
    {
        $ini = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
        $fim = (clone $ini)->endOfMonth();

        // Apresentação: semanas completas (começando domingo, terminando sábado)
        $start = (clone $ini)->startOfWeek(CarbonInterface::SUNDAY);
        $end   = (clone $fim)->endOfWeek(CarbonInterface::SATURDAY);

        $matrix = [];
        $cursor = $start->copy();

        $eventosMes = []; // lista plana para o mês (para exibição abaixo do calendário mensal)

        while ($cursor->lessThanOrEqualTo($end)) {
            $semana = [];
            for ($dow=0; $dow<7; $dow++) {
                $d = $cursor->toDateString();
                $inMonth = $cursor->month == $mes;
                $isSun = $cursor->dayOfWeek === CarbonInterface::SUNDAY;
                $isSat = $cursor->dayOfWeek === CarbonInterface::SATURDAY;

                $events = $mapEventos[$d] ?? [];

                if ($inMonth && !empty($events)) {
                    foreach ($events as $e) {
                        $eventosMes[] = $e; // usado na lista mensal
                    }
                }

                $semana[] = [
                    'label'     => $cursor->day,
                    'date'      => $d,
                    'inMonth'   => $inMonth,
                    'isSun'     => $isSun,
                    'isSat'     => $isSat,
                    'events'    => $events,
                ];
                $cursor->addDay();
            }
            $matrix[] = $semana;
        }

        // Ordena lista mensal por data/tipo
        usort($eventosMes, function($a,$b){
            $da = strtotime($a->data); $db = strtotime($b->data);
            if ($da === $db) return strcmp($a->tipo, $b->tipo);
            return $da <=> $db;
        });

        return ['matrix'=>$matrix, 'eventosMes'=>$eventosMes];
    }
}
