<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">FROTA — CADASTRAR VEÍCULO</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto bg-white dark:bg-gray-900 p-6 rounded-xl shadow">

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

            <form method="POST" action="{{ route('frota.veiculos.store') }}" class="uppercase">
                @csrf
                @include('frota.partials.veiculosform')

                <div class="mt-6 flex gap-3">
                    <button type="submit" class="px-4 py-2 rounded bg-blue-900 text-white">Salvar</button>
                    <a href="{{ route('frota.veiculos.search') }}" class="px-4 py-2 rounded border">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
