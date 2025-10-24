{{-- resources/views/gestao/dadosedit.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">GESTÃO • DADOS PESSOAIS — EDITAR</h1>
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

    @if (!isset($registro) || !$registro)
      {{-- ====================== MODO BUSCA ====================== --}}
      @php
        // valores atuais vindos do controller/request
        $for      = $for      ?? request('for', 'dados');
        $perPage  = $perPage  ?? (int) request('pp', 10);

        $cadpen   = request('cadpen', '');
        $nome     = request('nome', '');
        $mae      = request('mae', '');
        $pai      = request('pai', '');
        $dn       = request('data_nascimento', '');
        $mun      = request('naturalidade_municipio', '');
        $uf       = request('naturalidade_uf', '');

        // flag para só exibir resultados após uma busca
        $didSearch = ($cadpen!=='' || $nome!=='' || $mae!=='' || $pai!=='' || $dn!=='' || $mun!=='' || $uf!=='') || ($searched ?? false);
      @endphp

      <form method="GET" action="{{ route('gestao.dados.search') }}" class="space-y-4">
        <input type="hidden" name="for" value="{{ $for }}">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-semibold">CadPen (único)</label>
            <input type="text" name="cadpen" value="{{ $cadpen }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="2025-000123">
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-semibold">Nome completo</label>
            <input type="text" name="nome" value="{{ $nome }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Ex.: MARIA DE SOUZA">
          </div>

          <div>
            <label class="block text-sm font-semibold">Nome da mãe</label>
            <input type="text" name="mae" value="{{ $mae }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Ex.: ANA SOUZA">
          </div>

          <div>
            <label class="block text-sm font-semibold">Nome do pai</label>
            <input type="text" name="pai" value="{{ $pai }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Ex.: JOÃO SOUZA">
          </div>

          <div>
            <label class="block text-sm font-semibold">Data de nascimento</label>
            <input type="date" name="data_nascimento" value="{{ $dn }}" class="mt-1 w-full rounded-md border-gray-300">
          </div>

          <div>
            <label class="block text-sm font-semibold">Naturalidade — Município</label>
            <input type="text" name="naturalidade_municipio" value="{{ $mun }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Ex.: BELO HORIZONTE">
          </div>

          <div>
            <label class="block text-sm font-semibold">Naturalidade — UF</label>
            <input type="text" name="naturalidade_uf" value="{{ $uf }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Ex.: MG" maxlength="2">
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-600">Resultados por página
            <select name="pp" class="border rounded px-2 py-1">
              @foreach([10,20,30,50] as $n)
                <option value="{{ $n }}" @selected($perPage==$n)>{{ $n }}</option>
              @endforeach
            </select>
          </div>
          <div class="flex gap-2">
            {{-- REMOVIDO: botão de inserir novo --}}
            <button type="submit" class="px-4 py-2 rounded bg-gray-900 text-white hover:bg-black">Buscar</button>
          </div>
        </div>
      </form>

      @if($didSearch)
        @isset($resultados)
          <div class="bg-white rounded-lg border mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50">
                <tr class="text-left">
                  <th class="p-3">CadPen</th>
                  <th class="p-3">Nome completo</th>
                  <th class="p-3">Mãe / Pai</th>
                  <th class="p-3">Nascimento</th>
                  <th class="p-3">Naturalidade</th>
                  <th class="p-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                @forelse($resultados as $r)
                  <tr class="border-t">
                    <td class="p-3 font-mono">{{ $r->cadpen }}</td>
                    <td class="p-3">{{ $r->nome_completo }}</td>
                    <td class="p-3">
                      <div><span class="text-gray-500">Mãe:</span> {{ $r->mae }}</div>
                      <div><span class="text-gray-500">Pai:</span> {{ $r->pai }}</div>
                    </td>
                    <td class="p-3">{{ $r->data_nascimento }}</td>
                    <td class="p-3">
                      {{ $r->naturalidade_municipio }}
                      @if(!empty($r->naturalidade_uf))
                        ({{ $r->naturalidade_uf }})
                      @endif
                    </td>
                    <td class="p-3 text-right">
                        @if(($for ?? 'dados') === 'ficha')
                            {{-- MODO FICHA: botão só-leitura para visualizar/imprimir/exportar PDF --}}
                            <a href="{{ route('gestao.ficha.individuo.show', $r->id) }}"
                            class="px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700"
                            aria-label="Abrir ficha do indivíduo para impressão/PDF">
                            Abrir Ficha
                            </a>
                        @else
                            {{-- Modos padrão (dados/documentos/identificacao): mantém o botão Editar existente --}}
                            @php
                            $destino = match($for ?? 'dados') {
                                'documentos'    => route('gestao.documentos.edit', $r->id),
                                'identificacao' => route('gestao.identificacao.visual.edit', $r->id),
                                default         => route('gestao.dados.edit', $r->id),
                            };
                            @endphp
                            <a href="{{ $destino }}" class="px-3 py-1 rounded bg-green-600 text-white hover:bg-green-700">Editar</a>
                        @endif
                        </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="p-6 text-center text-gray-600">Nenhum resultado. Ajuste os termos e busque novamente.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="mt-2">
            {{ $resultados->links() }}
          </div>
        @endisset
      @endif

    @else
      {{-- ====================== MODO EDIÇÃO (com $registro) ====================== --}}
      <form method="POST" action="{{ route('gestao.dados.update', $registro->id) }}" class="space-y-8 max-w-3xl">
        @csrf
        @method('PUT')

        @include('gestao.partials.dadosform', ['registro' => $registro])

        <div class="flex items-center justify-between gap-3">
          <a href="{{ route('gestao.dados.search') }}" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">VOLTAR À BUSCA</a>
          <div class="flex items-center gap-3">
            <a href="{{ route('inicio') }}" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">CANCELAR</a>
            <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">ATUALIZAR</button>
          </div>
        </div>
      </form>
    @endif
  </div>
</x-app-layout>
