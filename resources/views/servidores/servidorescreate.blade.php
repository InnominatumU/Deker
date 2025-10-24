{{-- resources/views/servidores/servidorescreate.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl uppercase">Servidores — Novo Cadastro</h2>
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

    <div class="rounded-xl bg-gray-800 p-4 shadow">
        <div class="rounded-lg bg-white p-4">
            <form method="POST" action="{{ route('servidores.store') }}" class="space-y-6" autocomplete="off">
                @csrf

                @include('servidores.partials.servidoresform')

                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('servidores.index') }}" class="rounded border px-4 py-2 hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button type="submit" class="rounded bg-blue-900 px-5 py-2 text-white shadow hover:opacity-95">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Máscaras simples para CPF e CNPJ-like (apenas formatação visual) --}}
    <script>
        const maskCPF = (v) => v
            .replace(/\D/g,'')
            .replace(/(\d{3})(\d)/,'$1.$2')
            .replace(/(\d{3})(\d)/,'$1.$2')
            .replace(/(\d{3})(\d{1,2})$/,'$1-$2')
            .slice(0,14);

        document.addEventListener('input', (e) => {
            if (e.target.matches('[data-mask="cpf"]')) {
                e.target.value = maskCPF(e.target.value);
            }
        }, { passive: true });
    </script>
</x-app-layout>
