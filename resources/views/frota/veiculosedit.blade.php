<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">FROTA — BUSCAR / EDITAR VEÍCULO</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto bg-white dark:bg-gray-900 p-6 rounded-xl shadow">

            {{-- ALERTAS --}}
            @if (session('success'))
                <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 p-3 rounded bg-red-100 text-red-800">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- MODO BUSCA (quando $veiculo é null) --}}
            @if (empty($veiculo))
                <form method="GET" action="{{ route('frota.veiculos.search') }}" class="uppercase">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium">Placa</label>
                            <input name="placa" value="{{ strtoupper($q['q_placa'] ?? '') }}" class="mt-1 w-full rounded border p-2 uppercase">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">RENAVAM</label>
                            <input name="renavam" value="{{ strtoupper($q['q_renavam'] ?? '') }}" class="mt-1 w-full rounded border p-2 uppercase">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Modelo</label>
                            <input name="modelo" value="{{ strtoupper($q['q_modelo'] ?? '') }}" class="mt-1 w-full rounded border p-2 uppercase">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Por página</label>
                            <select name="pp" class="mt-1 w-full rounded border p-2 uppercase">
                                <option value="">-SELECIONE-</option>
                                @foreach ([10,15,25,50] as $n)
                                    <option value="{{ $n }}" @selected(($q['q_perpage'] ?? 15) == $n)>{{ $n }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button class="px-4 py-2 rounded bg-blue-900 text-white">Buscar</button>
                        <a href="{{ route('frota.veiculos.create') }}" class="px-4 py-2 rounded border">Cadastrar novo</a>
                        <a href="{{ route('frota.veiculos.search') }}" class="px-4 py-2 rounded border">Limpar</a>
                    </div>
                </form>

                @if(!empty($didSearch) && $didSearch && $resultados)
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm uppercase">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2 px-2">Placa</th>
                                    <th class="text-left py-2 px-2">RENAVAM</th>
                                    <th class="text-left py-2 px-2">Modelo</th>
                                    <th class="text-left py-2 px-2">Status</th>
                                    <th class="text-left py-2 px-2">Atualizado</th>
                                    <th class="text-left py-2 px-2">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($resultados as $r)
                                <tr class="border-b">
                                    <td class="py-2 px-2">{{ $r->placa }}</td>
                                    <td class="py-2 px-2">{{ $r->renavam }}</td>
                                    <td class="py-2 px-2">{{ $r->modelo }}</td>
                                    <td class="py-2 px-2">{{ $r->status }}</td>
                                    <td class="py-2 px-2">{{ \Carbon\Carbon::parse($r->updated_at)->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 px-2">
                                        <a class="text-blue-700 hover:underline" href="{{ route('frota.veiculos.edit', $r->id) }}">Editar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="py-4 px-2 text-gray-500" colspan="6">Nenhum resultado.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $resultados->links() }}
                    </div>
                @endif

            {{-- MODO EDIÇÃO (quando $veiculo existe) --}}
            @else
                <form method="POST" action="{{ route('frota.veiculos.update', $veiculo->id) }}" class="uppercase">
                    @csrf
                    @method('PUT')
                    @include('frota.partials.veiculosform', ['veiculo' => $veiculo])

                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="px-4 py-2 rounded bg-blue-900 text-white">Atualizar</button>
                        <a href="{{ route('frota.veiculos.search') }}" class="px-4 py-2 rounded border">Cancelar</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
