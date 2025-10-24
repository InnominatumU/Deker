{{-- resources/views/sistema/usuarioscreate.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">Gestão do Sistema • Novo Usuário</h1>
  </x-slot>

  @php
    // Fallbacks seguros para o botão CANCELAR
    $cancelHref = \Illuminate\Support\Facades\Route::has('sistema.usuarios.search')
      ? route('sistema.usuarios.search')
      : ( \Illuminate\Support\Facades\Route::has('inicio') ? route('inicio') : url('/') );
  @endphp

  <div class="max-w-3xl mx-auto p-6 space-y-6">
    {{-- Alerts: verde=sucesso, amarelo=aviso, vermelho=erro --}}
    @if (session('success') || session('ok'))
      <div class="rounded-md bg-green-100 text-green-800 px-4 py-2">{{ session('success') ?? session('ok') }}</div>
    @endif
    @if (session('warning') || session('info'))
      <div class="rounded-md bg-yellow-100 text-yellow-800 px-4 py-2">{{ session('warning') ?? session('info') }}</div>
    @endif
    @if (session('error'))
      <div class="rounded-md bg-red-100 text-red-800 px-4 py-2">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
      <div class="rounded-md bg-red-100 text-red-800 px-4 py-3">
        <div class="font-semibold mb-1">VERIFIQUE OS CAMPOS:</div>
        <ul class="list-disc list-inside text-sm">
          @foreach ($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('sistema.usuarios.store') }}" class="space-y-8">
      @csrf

      @include('sistema.partials.usuariosform', [
        'usuario' => null,
        'perfis'  => $perfis,
        'isEdit'  => false
      ])

      <div class="flex items-center justify-end gap-3">
        <a href="{{ $cancelHref }}" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">CANCELAR</a>
        <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">SALVAR</button>
      </div>
    </form>
  </div>
</x-app-layout>
