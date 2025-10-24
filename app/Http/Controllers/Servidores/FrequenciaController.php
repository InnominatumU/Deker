<?php

namespace App\Http\Controllers\Servidores;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FrequenciaController extends Controller
{
    /**
     * POST /servidores/{servidor}/frequencia
     * Grava um lançamento (um segmento) de frequência.
     * Regras:
     *  - NORMAL  -> exige hora_entrada e hora_saida
     *  - FOLGA/FERIAS/LICENCA/ATESTADO/OUTROS -> sem horas; grava horas=0 e times nulos
     */
    public function store(Request $request, int $servidor)
    {
        // Normaliza tipo primeiro
        $tipoIn = strtoupper((string) $request->input('tipo', 'NORMAL'));

        // Regras condicionais
        $rules = [
            'data' => ['required', 'date'],
            'tipo' => ['nullable', Rule::in(['NORMAL','FOLGA','LICENCA','FERIAS','ATESTADO','OUTROS'])],
            'observacoes' => ['nullable','string','max:1000'],
        ];

        if ($tipoIn === 'NORMAL') {
            $rules['hora_entrada'] = ['required','regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'];                 // 00:00–23:59
            $rules['hora_saida']   = ['required','regex:/^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/'];       // aceita 24:00
        } else {
            // Para ocorrências sem horas, os campos podem vir faltando ou vazios
            $rules['hora_entrada'] = ['nullable','regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'];
            $rules['hora_saida']   = ['nullable','regex:/^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/'];
        }

        $messages = [
            'data.required'         => 'A data é obrigatória.',
            'hora_entrada.required' => 'A hora de entrada é obrigatória.',
            'hora_saida.required'   => 'A hora de saída é obrigatória.',
        ];

        $v = Validator::make($request->all(), $rules, $messages);

        if ($v->fails()) {
            // HTML x JSON
            if ($request->expectsJson()) {
                return response()->json(['errors' => $v->errors()], 422);
            }
            return back()->withErrors($v)->withInput();
        }

        $data         = $request->input('data');          // YYYY-MM-DD
        $tipo         = $tipoIn ?: 'NORMAL';
        $observacoes  = (string) $request->input('observacoes');

        // Ocorrências sem hora: gravar times nulos e horas=0
        if ($tipo !== 'NORMAL') {
            DB::table('servidores_frequencia')->insert([
                'servidor_id'  => $servidor,
                'data'         => $data,
                'hora_entrada' => null,
                'hora_saida'   => null,
                'horas'        => 0,
                'tipo'         => $tipo,
                'observacoes'  => mb_substr($observacoes, 0, 1000) ?: null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            if ($request->expectsJson()) {
                return response()->noContent(); // 204
            }
            return back()->with('success', 'Lançamento registrado.');
        }

        // === A partir daqui: tipo NORMAL (exige horas)
        $horaEntrada  = $request->input('hora_entrada');  // HH:MM
        $horaSaida    = $request->input('hora_saida');    // HH:MM

        $minEntrada = $this->toMinutes($horaEntrada);
        $minSaida   = $this->toMinutes($horaSaida);

        if ($minEntrada === null || $minSaida === null) {
            $err = ['horario' => ['Horários inválidos.']];
            if ($request->expectsJson()) return response()->json(['errors' => $err], 422);
            return back()->withErrors($err)->withInput();
        }
        if ($minSaida <= $minEntrada) {
            $err = ['horario' => ['Saída deve ser maior que a entrada.']];
            if ($request->expectsJson()) return response()->json(['errors' => $err], 422);
            return back()->withErrors($err)->withInput();
        }

        // Calcula horas decimais do segmento
        $minutos  = max(0, $minSaida - $minEntrada);
        $horasDec = round($minutos / 60, 2);

        // Upsert para evitar duplicatas idênticas
        DB::table('servidores_frequencia')->updateOrInsert(
            [
                'servidor_id'  => $servidor,
                'data'         => $data,
                'hora_entrada' => $horaEntrada,
                'hora_saida'   => $horaSaida,
            ],
            [
                'tipo'         => $tipo,
                'horas'        => $horasDec,
                'observacoes'  => mb_substr($observacoes, 0, 1000) ?: null,
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        if ($request->expectsJson()) {
            return response()->noContent(); // 204
        }
        return back()->with('success', 'Lançamento registrado.');
    }

    /**
     * GET /servidores/{servidor}/frequencia/json?mes=YYYY-MM
     * Lista os lançamentos do mês (para reidratar no formulário).
     */
    public function mesJson(Request $request, int $servidor)
    {
        $mes = (string) $request->query('mes'); // YYYY-MM
        if (!preg_match('/^\d{4}\-\d{2}$/', $mes)) {
            $mes = now()->format('Y-m');
        }

        $inicio = $mes . '-01';
        $fim    = date('Y-m-t', strtotime($inicio)); // último dia do mês

        $rows = DB::table('servidores_frequencia')
            ->where('servidor_id', $servidor)
            ->whereBetween('data', [$inicio, $fim])
            ->orderBy('data')
            ->orderBy('hora_entrada')
            ->get([
                'data',
                'hora_entrada',
                'hora_saida',
                'horas',
                'tipo',
                'observacoes',
            ]);

        return response()->json($rows);
    }

    /**
     * Alias de compatibilidade.
     */
    public function json(Request $request, int $servidor)
    {
        return $this->mesJson($request, $servidor);
    }

    /**
     * Converte HH:MM em minutos; retorna null se inválido (aceita 24:00).
     */
    private function toMinutes(?string $hhmm): ?int
    {
        if (!$hhmm || !preg_match('/^\d{2}:\d{2}$/', $hhmm)) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        if ($h === 24 && $m === 0) {
            return 24 * 60;
        }

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }
        return ($h * 60) + $m;
    }
}
