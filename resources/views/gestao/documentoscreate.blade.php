{{-- resources/views/gestao/documentoscreate.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">GESTÃO • DOCUMENTOS — NOVO</h1>
  </x-slot>

  <div class="max-w-4xl mx-auto p-6 space-y-6">
    {{-- Alerts --}}
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

    {{-- Resumo do indivíduo --}}
    @php $id = $registro->id ?? null; @endphp
    <div class="bg-white border rounded p-4 text-sm">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div><span class="font-semibold text-gray-600">CadPen:</span> <span class="font-mono">{{ $registro->cadpen ?? '—' }}</span></div>
        <div class="md:col-span-2"><span class="font-semibold text-gray-600">Nome:</span> {{ $registro->nome_completo ?? '—' }}</div>
        <div><span class="font-semibold text-gray-600">Nascimento:</span> {{ $registro->data_nascimento ?? '—' }}</div>
      </div>
    </div>

    <form method="POST" action="{{ route('gestao.documentos.store') }}" class="space-y-6">
      @csrf
      @if($id)<input type="hidden" name="id" value="{{ $id }}">@endif

      @include('gestao.partials.documentosform', ['registro' => $registro ?? null])

      <div class="flex items-center justify-between gap-3">
        @if($id)
          <a href="{{ route('gestao.dados.edit', $id) }}" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">VOLTAR A DADOS</a>
        @else
          <a href="{{ route('gestao.dados.search') }}" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">VOLTAR</a>
        @endif
        <div class="flex items-center gap-3">
          <a href="{{ route('inicio') }}" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">CANCELAR</a>
          <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">SALVAR E AVANÇAR</button>
        </div>
      </div>
    </form>
  </div>
</x-app-layout>
