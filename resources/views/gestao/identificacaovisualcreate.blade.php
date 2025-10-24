<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">GESTÃO • IDENTIFICAÇÃO — NOVO</h1>
  </x-slot>

  <div class="max-w-5xl mx-auto p-6 space-y-6">
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

    @php $id = $registro->id ?? request('id'); @endphp

    <form method="POST" action="{{ route('gestao.identificacao.visual.store') }}" enctype="multipart/form-data" class="space-y-8">
      @csrf
      @if($id)<input type="hidden" name="id" value="{{ (int)$id }}">@endif
      <input type="hidden" name="action" value="save">

      @include('gestao.partials.identificacaovisualform', [
        'registro' => $registro ?? null,
        'mode'     => 'create'
      ])
    </form>
  </div>
</x-app-layout>
