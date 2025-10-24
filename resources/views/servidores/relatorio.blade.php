{{-- resources/views/servidores/relatorio.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl uppercase">
            Relatório — {{ $servidor->nome ?? 'Servidores' }}
            @if(isset($servidor)) <span class="text-sm normal-case text-gray-500">Matrícula: {{ $servidor->matricula }}</span> @endif
        </h2>
    </x-slot>

    {{-- Flash persistente --}}
    @if (session('success') || session('error') || $errors->any())
        <div data-flash class="mb-4 rounded border p-3
            {{ session('success') ? 'border-green-600 bg-green-50' : '' }}
            {{ session('error') ? 'border-red-600 bg-red-50' : '' }}
            {{ $errors->any() ? 'border-yellow-600 bg-yellow-50' : '' }}">
            @if (session('success')) <div class="font-semibold text-green-800">{{ session('success') }}</div> @endif
            @if (session('error'))   <div class="font-semibold text-red-800">{{ session('error') }}</div>   @endif
            @if ($errors->any())
                <div class="font-semibold text-yellow-800">Há pendências no formulário.</div>
                <ul class="mt-1 list-inside list-disc text-sm text-yellow-900">
                    @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div id="report-root" class="space-y-4">
        {{-- FILTROS --}}
        <div class="rounded-xl bg-gray-800 p-4 shadow print:hidden">
            <div class="rounded-lg bg-white p-4">
                <form method="GET" action="{{ route('servidores.relatorios.index', $servidor->id ?? null) }}" class="grid grid-cols-1 gap-4 md:grid-cols-6">
                    {{-- Mês de referência --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium">Mês *</label>
                        <input type="month" name="mes" value="{{ request('mes', now()->format('Y-m')) }}"
                               class="mt-1 w-full rounded border p-2" required>
                    </div>

                    {{-- Tipo --}}
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium">Tipo</label>
                        @php $tipo = strtoupper(request('tipo','')); @endphp
                        <select name="tipo" class="mt-1 w-full rounded border p-2 uppercase">
                            <option value="">(Todos)</option>
                            <option value="NORMAL"   {{ $tipo==='NORMAL'   ? 'selected' : '' }}>NORMAL</option>
                            <option value="FOLGA"    {{ $tipo==='FOLGA'    ? 'selected' : '' }}>FOLGA</option>
                            <option value="LICENCA"  {{ $tipo==='LICENCA'  ? 'selected' : '' }}>LICENÇA</option>
                            <option value="FERIAS"   {{ $tipo==='FERIAS'   ? 'selected' : '' }}>FÉRIAS</option>
                            <option value="ATESTADO" {{ $tipo==='ATESTADO' ? 'selected' : '' }}>ATESTADO</option>
                        </select>
                    </div>

                    {{-- Plantão --}}
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium">Plantão</label>
                        @php $plantao = strtoupper(request('plantao','')); @endphp
                        <select name="plantao" class="mt-1 w-full rounded border p-2 uppercase">
                            <option value="">(Todos)</option>
                            <option value="SIM" {{ $plantao==='SIM' ? 'selected' : '' }}>SIM</option>
                            <option value="NAO" {{ $plantao==='NAO' ? 'selected' : '' }}>NÃO</option>
                        </select>
                    </div>

                    {{-- Situação --}}
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium">Situação</label>
                        @php $ativo = request('ativo',''); @endphp
                        <select name="ativo" class="mt-1 w-full rounded border p-2 uppercase">
                            <option value="">(Todas)</option>
                            <option value="1" {{ $ativo==='1' ? 'selected' : '' }}>ATIVO</option>
                            <option value="0" {{ $ativo==='0' ? 'selected' : '' }}>INATIVO</option>
                        </select>
                    </div>

                    {{-- Ações --}}
                    <div class="md:col-span-6 flex flex-wrap items-end justify-end gap-2">
                        <a href="{{ route('servidores.relatorios.index', $servidor->id ?? null) }}"
                           class="rounded border px-4 py-2 hover:bg-gray-50">Limpar</a>

                        {{-- Exportar PDF (rota opcional) --}}
                        <a href="{{ route('servidores.relatorios.export', $servidor->id ?? null) }}?{{ http_build_query(request()->query()) }}"
                           class="rounded border px-4 py-2 hover:bg-gray-50">Exportar PDF</a>

                        {{-- Imprimir --}}
                        <button type="button" onclick="window.print()"
                                class="rounded border px-4 py-2 hover:bg-gray-50">Imprimir</button>

                        <button class="rounded bg-blue-900 px-5 py-2 text-white shadow hover:opacity-95">
                            Aplicar filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- CABEÇALHO DO RELATÓRIO (IMPRIMÍVEL) --}}
        <div class="rounded-xl bg-white p-6 shadow print:shadow-none">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase text-gray-500">Relatório de Frequência</div>
                    <div class="text-lg font-semibold uppercase">
                        {{ $servidor->nome ?? 'Consolidado de Servidores' }}
                    </div>
                    @if(isset($servidor))
                        <div class="text-xs uppercase text-gray-600">
                            Matrícula: {{ $servidor->matricula }} • CPF: {{ $servidor->cpf }} • Cargo: {{ $servidor->cargo_funcao }}
                        </div>
                    @endif
                </div>
                <div class="text-right text-xs text-gray-600">
                    Referência: <strong>{{ \Carbon\Carbon::createFromFormat('Y-m', request('mes', now()->format('Y-m')))->translatedFormat('F/Y') }}</strong><br>
                    Gerado em: {{ now()->format('d/m/Y H:i') }}
                </div>
            </div>

            {{-- RESUMO MENSAL --}}
            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="rounded border p-3">
                    <div class="text-xs text-gray-500 uppercase">Carga Prevista (h)</div>
                    <div class="text-2xl font-semibold">
                        {{ number_format($resumoMensal['carga_prevista'] ?? 0, 2, ',', '.') }}
                    </div>
                </div>
                <div class="rounded border p-3">
                    <div class="text-xs text-gray-500 uppercase">Horas Trabalhadas (h)</div>
                    <div class="text-2xl font-semibold">
                        {{ number_format($resumoMensal['horas_trabalhadas'] ?? 0, 2, ',', '.') }}
                    </div>
                </div>
                <div class="rounded border p-3">
                    <div class="text-xs text-gray-500 uppercase">Justificadas (h)</div>
                    <div class="text-2xl font-semibold">
                        {{ number_format($resumoMensal['horas_justificadas'] ?? 0, 2, ',', '.') }}
                    </div>
                </div>
                <div class="rounded border p-3">
                    <div class="text-xs text-gray-500 uppercase">Banco de Horas (h)</div>
                    @php
                        $saldo = ($resumoMensal['saldo_banco'] ?? 0.0);
                        $saldoClasse = $saldo >= 0 ? 'text-green-700' : 'text-red-700';
                    @endphp
                    <div class="text-2xl font-semibold {{ $saldoClasse }}">
                        {{ number_format($saldo, 2, ',', '.') }}
                    </div>
                    <div class="text-[10px] uppercase text-gray-500">Fechamento mensal</div>
                </div>
            </div>

            {{-- TABELA DETALHADA --}}
            <div class="overflow-x-auto rounded border">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">Data</th>
                            <th class="px-3 py-2 text-left">Entrada</th>
                            <th class="px-3 py-2 text-left">Saída</th>
                            <th class="px-3 py-2 text-left">Horas (h)</th>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-left">Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $r)
                            <tr class="border-b last:border-0">
                                <td class="px-3 py-2">{{ \Carbon\Carbon::parse($r->data)->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">{{ $r->hora_entrada ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $r->hora_saida ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    {{ number_format((float)($r->horas ?? 0), 2, ',', '.') }}
                                </td>
                                <td class="px-3 py-2 uppercase">{{ $r->tipo ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $r->observacoes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                                    Nenhum registro no período/critério selecionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginação --}}
            @if(method_exists($registros, 'links'))
                <div class="mt-3 print:hidden">
                    {{ $registros->withQueryString()->links() }}
                </div>
            @endif

            {{-- Rodapé do relatório (imprimível) --}}
            <div class="mt-6 text-xs text-gray-500">
                <div>Página <span class="pageNumber"></span> de <span class="totalPages"></span></div>
                @if(isset($servidor))
                    <div class="uppercase">Responsável: ____________________________________ | Assinatura: ________________________________</div>
                @endif
            </div>
        </div>
    </div>

    {{-- CSS de impressão A4 (esconde menu/header da aplicação) --}}
    <style>
        @page { size: A4; margin: 12mm; }
        @media print {
            header, nav, .print\:hidden, [data-flash] { display: none !important; }
            #report-root { margin: 0 !important; padding: 0 !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    {{-- Numeração de páginas na impressão (best-effort em browsers) --}}
    <script>
        // Atualiza contadores após print
        function updatePageCounters() {
            const pn = document.querySelector('.pageNumber');
            const tp = document.querySelector('.totalPages');
            // Muitos navegadores não expõem contagem real; mantemos placeholder.
            if (pn && tp) { pn.textContent = '1'; tp.textContent = '1'; }
        }
        document.addEventListener('DOMContentLoaded', updatePageCounters, { passive: true });
        window.addEventListener('afterprint', updatePageCounters, { passive: true });
    </script>
</x-app-layout>
