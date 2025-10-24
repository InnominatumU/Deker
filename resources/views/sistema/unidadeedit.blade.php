{{-- resources/views/sistema/unidadeedit.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">
      Gestão do Sistema • Unidades — {{ (isset($unidade) && $unidade) ? 'Editar' : 'Buscar' }}
    </h1>
  </x-slot>

  <div class="max-w-6xl mx-auto p-6 space-y-6">
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
          @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
      </div>
    @endif

    @if (!isset($unidade) || !$unidade)
      @php $q = $q ?? []; @endphp

      <form method="GET" action="{{ route('sistema.unidades.search') }}" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <label class="block">
            <span class="text-sm font-semibold">Nome</span>
            <input type="text" name="nome" value="{{ $q['q_nome'] ?? '' }}"
                   class="mt-1 w-full rounded-md border-gray-300"
                   oninput="this.value=this.value.toUpperCase()" autocomplete="off" spellcheck="false">
          </label>
          <label class="block">
            <span class="text-sm font-semibold">Sigla / Apelido</span>
            <input type="text" name="sigla" value="{{ $q['q_sigla'] ?? '' }}"
                   class="mt-1 w-full rounded-md border-gray-300"
                   oninput="this.value=this.value.toUpperCase()" autocomplete="off" spellcheck="false">
          </label>
        </div>

        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-600">
            Resultados por página
            <select name="pp" class="border rounded px-2 py-1">
              @foreach([10,15,20,30,50] as $n)
                <option value="{{ $n }}" @selected(($q['q_perpage'] ?? 15) == $n)>{{ $n }}</option>
              @endforeach
            </select>
          </div>
          <div class="flex gap-2">
            <a href="{{ route('sistema.unidades.create') }}"
               class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Cadastrar Unidade</a>
            <button class="px-4 py-2 rounded bg-gray-900 text-white hover:bg-black">Buscar</button>
          </div>
        </div>
      </form>

      @if(($didSearch ?? false) && isset($resultados))
        <div class="rounded-lg border bg-white mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr class="text-left">
                <th class="p-3">Nome</th>
                <th class="p-3">Sigla</th>
                <th class="p-3">Porte</th>
                <th class="p-3">Perfil</th>
                <th class="p-3">Capacidade (vagas)</th>
                <th class="p-3">Atualizado em</th>
                <th class="p-3 text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              @forelse($resultados as $u)
                <tr class="border-t">
                  <td class="p-3 font-medium">{{ $u->nome }}</td>
                  <td class="p-3">{{ $u->sigla ?? '—' }}</td>
                  <td class="p-3">{{ $u->porte ?? '—' }}</td>
                  <td class="p-3">{{ $u->perfil ?? '—' }}</td>
                  <td class="p-3">{{ is_null($u->capacidade_vagas) ? '—' : number_format((int)$u->capacidade_vagas,0,',','.') }}</td>
                  <td class="p-3">
                    {{ $u->updated_at ? \Carbon\Carbon::parse($u->updated_at)->format('d/m/Y H:i') : '—' }}
                  </td>
                  <td class="p-3 text-right">
                    <a href="{{ route('sistema.unidades.edit', $u->id) }}"
                       class="px-3 py-1 rounded bg-yellow-500 text-white hover:bg-yellow-600">Editar</a>
                    <form method="POST" action="{{ route('sistema.unidades.destroy', $u->id) }}"
                          class="inline"
                          onsubmit="return confirm('Confirma excluir esta unidade?');">
                      @csrf @method('DELETE')
                      <button class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700">Excluir</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="p-6 text-center text-gray-600">Nenhuma unidade encontrada.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-2">{{ $resultados->links() }}</div>
      @endif

    @else
      {{-- =============== EDIÇÃO =============== --}}
      <form method="POST" action="{{ route('sistema.unidades.update', ['unidade' => $unidade->id]) }}" class="space-y-8">
        @csrf @method('PUT')

        @include('sistema.partials.unidadeform', [
          'mode'              => 'edit',
          'unidade'           => $unidade,
          'mapeamento_itens'  => $mapeamento_itens ?? []
        ])

        <div class="flex items-center justify-between gap-3">
          <a href="{{ route('sistema.unidades.search') }}"
             class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">VOLTAR À BUSCA</a>
          <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">ATUALIZAR</button>
          </div>
        </div>
      </form>

      <form method="POST" action="{{ route('sistema.unidades.destroy', ['unidade' => $unidade->id]) }}" class="mt-6"
            onsubmit="return confirm('Tem certeza que deseja excluir esta unidade? Esta ação não pode ser desfeita.');">
        @csrf @method('DELETE')
        <button class="px-4 py-2 rounded bg-red-700 text-white">Excluir unidade</button>
      </form>
    @endif
  </div>
</x-app-layout>
