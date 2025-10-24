<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-100 leading-tight">
            Cadastro de Visitas — Editar #{{ $visita->id }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-2xl">
                <div class="p-6 text-gray-900">
                    @include('gestao.partials.visitasform')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
