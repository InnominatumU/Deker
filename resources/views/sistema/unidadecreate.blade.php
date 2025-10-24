<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">UNIDADES — CADASTRAR</h1>
  </x-slot>

  <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8">
    @if(session('success'))
      <div class="mb-4 p-3 rounded bg-green-100 text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="mb-4 p-3 rounded bg-red-100 text-red-800">
        <ul class="list-disc pl-5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('sistema.unidades.store') }}">
      @csrf
      @include('sistema.partials.unidadeform', ['mode' => 'create'])
      <div class="mt-6 flex gap-3">
        <button class="px-4 py-2 rounded bg-blue-900 text-white">Salvar</button>
        <a href="{{ route('inicio') }}" class="px-4 py-2 rounded bg-gray-800 text-white">Cancelar</a>
      </div>
    </form>
  </div>
</x-app-layout>
