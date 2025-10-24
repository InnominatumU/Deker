{{-- resources/views/servidores/frequencia.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl uppercase">Servidores — Frequência</h2>
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

    {{-- Cabeçalho com dados do servidor --}}
    <div class="mb-6 rounded-xl bg-gray-800 p-4 shadow">
        <div class="rounded-lg bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm uppercase">
                    @php
                        $cpfDigits = preg_replace('/\D+/', '', (string)($servidor->cpf ?? ''));
                        $cpfFmt = $cpfDigits ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfDigits) : '—';
                    @endphp
                    <div><span class="text-gray-600">Servidor:</span> <span class="font-semibold">{{ $servidor->nome }}</span></div>
                    <div><span class="text-gray-600">Matrícula:</span> <span class="font-semibold">{{ $servidor->matricula ?? '—' }}</span></div>
                    <div><span class="text-gray-600">CPF:</span> <span class="font-semibold">{{ $cpfFmt }}</span></div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('servidores.frequencia.search') }}" class="rounded border px-3 py-2 hover:bg-gray-50">
                        Voltar à busca de frequência
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Form avançado de frequência (plantões/intervalos/ocorrências) --}}
    <div class="rounded-xl bg-gray-800 p-4 shadow">
        <div class="rounded-lg bg-white p-4">
            {{-- CSRF para os fetch do partial --}}
            <script>window.CSRF_TOKEN = "{{ csrf_token() }}";</script>
            @include('servidores.partials.frequenciaform', ['servidor' => $servidor])
        </div>
    </div>

    {{-- Tabela de lançamentos recentes --}}
    <div class="mt-6 rounded-xl bg-gray-800 p-4 shadow">
        <div class="rounded-lg bg-white p-4">
            <h3 class="text-lg font-semibold uppercase mb-3">Lançamentos</h3>

            @if ($frequencias->count() === 0)
                <p class="text-sm text-gray-600">Sem lançamentos de frequência.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm uppercase">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left">Data</th>
                                <th class="px-3 py-2 text-left">Entrada</th>
                                <th class="px-3 py-2 text-left">Saída</th>
                                <th class="px-3 py-2 text-right">Horas</th>
                                <th class="px-3 py-2 text-left">Tipo</th>
                                <th class="px-3 py-2 text-left">Observações</th>
                                <th class="px-3 py-2 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($frequencias as $f)
                                <tr class="border-b">
                                    <td class="px-3 py-2">{{ \Carbon\Carbon::parse($f->data)->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2">{{ $f->hora_entrada ? substr($f->hora_entrada,0,5) : '—' }}</td>
                                    <td class="px-3 py-2">{{ $f->hora_saida   ? substr($f->hora_saida,0,5)   : '—' }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float)$f->horas, 2, ',', '.') }}</td>
                                    <td class="px-3 py-2">{{ $f->tipo }}</td>
                                    <td class="px-3 py-2 normal-case">{{ $f->observacoes }}</td>
                                    <td class="px-3 py-2">
                                        <form method="POST" action="{{ route('servidores.frequencia.destroy', [$servidor->id, $f->id]) }}"
                                              onsubmit="return confirm('Excluir este lançamento?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded border px-3 py-1 hover:bg-red-50">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $frequencias->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
