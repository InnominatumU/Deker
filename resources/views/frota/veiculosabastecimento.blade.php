{{-- resources/views/frota/veiculosabastecimento.blade.php --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">FROTA — ABASTECIMENTOS</h2></x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto bg-white dark:bg-gray-900 p-6 rounded-xl shadow">

            {{-- FLASH MESSAGES (embutido) --}}
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-green-700 bg-green-700 text-white px-4 py-3" data-flash>
                    <div class="font-semibold">Sucesso</div>
                    <div class="text-sm">{{ session('success') }}</div>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-700 bg-red-700 text-white px-4 py-3" data-flash>
                    <div class="font-semibold">Erro</div>
                    <div class="text-sm">{{ session('error') }}</div>
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-yellow-600 bg-yellow-50 text-yellow-900 px-4 py-3" data-flash>
                    <div class="font-semibold">Há {{ $errors->count() }} erro(s) no formulário:</div>
                    <ul class="list-disc pl-5 mt-2 text-sm">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('frota.veiculos.abastecimentos.store') }}" class="uppercase">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium">PLACA *</label>
                        <input name="placa" value="{{ old('placa') }}" class="mt-1 w-full rounded border p-2 uppercase" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">DATA *</label>
                        <input type="datetime-local" name="data_hora" value="{{ old('data_hora') }}" class="mt-1 w-full rounded border p-2 uppercase" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">LITROS</label>
                        <input type="number" step="0.01" name="litros" value="{{ old('litros') }}" class="mt-1 w-full rounded border p-2 uppercase" min="0" placeholder="OPCIONAL">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">VALOR TOTAL (R$)</label>
                        <input type="number" step="0.01" name="valor_total" value="{{ old('valor_total') }}" class="mt-1 w-full rounded border p-2 uppercase" min="0">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">ODÔMETRO (KM) *</label>
                        <input type="number" name="odometro_km" value="{{ old('odometro_km') }}" class="mt-1 w-full rounded border p-2 uppercase" min="0" required>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium">OBSERVAÇÕES</label>
                        <textarea name="observacoes" rows="3" class="mt-1 w-full rounded border p-2 uppercase">{{ old('observacoes') }}</textarea>
                    </div>
                </div>

                <div class="mt-6">
                    <button class="px-4 py-2 rounded bg-blue-900 text-white">SALVAR</button>
                </div>
            </form>
        </div>
    </div>

   {{-- Mantém o aviso na tela (sem auto-ocultar). Opcional: rolar para o topo para o usuário ver a mensagem. --}}
@if (session('success') || session('error') || $errors->any())
    <script>
        // rola para o topo para destacar a mensagem (pode remover se não quiser)
        try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch {}
    </script>
@endif
</x-app-layout>

