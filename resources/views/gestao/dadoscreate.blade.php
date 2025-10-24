{{-- resources/views/gestao/dadoscreate.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">GESTÃO • DADOS PESSOAIS — CRIAR</h1>
  </x-slot>

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

    <form method="POST" action="{{ route('gestao.dados.store') }}" class="space-y-8">
      @csrf

      @include('gestao.partials.dadosform', ['registro' => null])

      <div class="flex items-center justify-end gap-3">
        <a href="{{ route('inicio') }}" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">CANCELAR</a>
        <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">SALVAR E AVANÇAR</button>
      </div>
    </form>
  </div>
</x-app-layout>
