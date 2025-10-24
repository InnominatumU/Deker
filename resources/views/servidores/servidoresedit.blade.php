{{-- resources/views/servidores/servidoresedit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl uppercase">Servidores — Editar Cadastro</h2>
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

    {{-- CARD: Localizar servidor para editar --}}
    <div class="mb-6 rounded-xl bg-gray-800 p-4 shadow">
        <div class="rounded-lg bg-white p-4">
            <form method="GET" action="{{ route('servidores.search') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3" autocomplete="off">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium">Localizar servidor (nome, matrícula ou CPF)</label>
                    <input name="q" value="{{ request('q', old('q')) }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="EX.: JOÃO, 12345, 111.222.333-44" data-upcase>
                    @isset($servidor)
                        <input type="hidden" name="current_id" value="{{ $servidor->id }}">
                    @endisset
                </div>
                <div class="md:col-span-1 flex items-end">
                    <button class="w-full rounded bg-blue-900 px-5 py-2 text-white shadow hover:opacity-95" type="submit">
                        Buscar
                    </button>
                </div>
            </form>

            @php
                $results = isset($encontrados) ? $encontrados : collect(session('searchResults', []));
                $count   = $results instanceof \Illuminate\Support\Collection ? $results->count() : (is_array($results) ? count($results) : 0);
            @endphp

            @if($count >= 1)
                @php $rows = $results instanceof \Illuminate\Support\Collection ? $results : collect($results); @endphp
                <div class="mt-4 overflow-x-auto">
                    <div class="mb-2 text-sm text-gray-600">Resultados da busca (clique em “Editar”):</div>
                    <table class="min-w-full text-sm uppercase">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left">Nome</th>
                                <th class="px-3 py-2 text-left">Matrícula</th>
                                <th class="px-3 py-2 text-left">CPF</th>
                                <th class="px-3 py-2 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $r)
                                @php
                                    $id  = is_object($r) ? $r->id : $r['id'];
                                    $nom = is_object($r) ? $r->nome : $r['nome'];
                                    $mat = is_object($r) ? ($r->matricula ?? null) : ($r['matricula'] ?? null);
                                    $cpf = is_object($r) ? ($r->cpf ?? null) : ($r['cpf'] ?? null);
                                    $cpfFmt = $cpf ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', preg_replace('/\D+/', '', $cpf)) : null;
                                @endphp
                                <tr class="border-b">
                                    <td class="px-3 py-2">{{ $nom }}</td>
                                    <td class="px-3 py-2">{{ $mat ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $cpfFmt ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex justify-end">
                                            <a href="{{ route('servidores.edit', $id) }}" class="rounded border px-3 py-1 hover:bg-gray-50">Editar</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif(request()->filled('q'))
                <p class="mt-3 text-sm text-gray-600">Nenhum servidor encontrado para “{{ request('q') }}”.</p>
            @endif
        </div>
    </div>

    @isset($servidor)
        {{-- CARD: Edição do cadastro --}}
        <div class="rounded-xl bg-gray-800 p-4 shadow">
            <div class="rounded-lg bg-white p-4">
                <form method="POST" action="{{ route('servidores.update', $servidor->id) }}" class="space-y-6" autocomplete="off">
                    @csrf
                    @method('PUT')

                    {{-- Info de auditoria --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="block text-gray-600">ID do Servidor</span>
                            <span class="font-semibold">{{ $servidor->id }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-600">Criado em</span>
                            <span class="font-semibold">{{ \Carbon\Carbon::parse($servidor->created_at)->format('d/m/Y H:i') }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-600">Atualizado em</span>
                            <span class="font-semibold">{{ \Carbon\Carbon::parse($servidor->updated_at)->format('d/m/Y H:i') }}</span>
                        </div>
                    </div>

                    {{-- Form padrão --}}
                    @include('servidores.partials.servidoresform')

                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('servidores.search') }}" class="rounded border px-4 py-2 hover:bg-gray-50">
                            Voltar à busca
                        </a>
                        <button type="submit" class="rounded bg-blue-900 px-5 py-2 text-white shadow hover:opacity-95">
                            Salvar alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- CARD: Lançar ocorrência (com toggle de horas) --}}
        <div class="mt-6 rounded-xl bg-gray-800 p-4 shadow">
          <div class="rounded-lg bg-white p-4">
            <h3 class="text-lg font-semibold uppercase mb-3">Lançar ocorrência</h3>

            <form method="POST" action="{{ route('servidores.frequencia.store', $servidor->id) }}"
                  class="grid grid-cols-1 md:grid-cols-6 gap-3" data-oc-form>
              @csrf

              <div class="md:col-span-2">
                <label class="block text-sm font-medium">Data *</label>
                <input type="date" name="data" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded border p-2" required>
              </div>

              <div class="md:col-span-2">
                <label class="block text-sm font-medium">Tipo *</label>
                <select name="tipo" class="mt-1 w-full rounded border p-2 uppercase" data-oc-tipo required>
                  <option value="NORMAL">NORMAL</option>
                  <option value="FOLGA">FOLGA</option>
                  <option value="LICENCA">LICENÇA</option>
                  <option value="FERIAS">FÉRIAS</option>
                  <option value="ATESTADO">ATESTADO</option>
                  <option value="OUTROS">OUTROS</option>
                </select>
              </div>

              <div class="md:col-span-1" data-oc-horas>
                <label class="block text-sm font-medium">Entrada</label>
                <input type="time" class="mt-1 w-full rounded border p-2"
                       data-oc-input="entrada" name="hora_entrada" placeholder="hh:mm">
              </div>
              <div class="md:col-span-1" data-oc-horas>
                <label class="block text-sm font-medium">Saída</label>
                <input type="time" class="mt-1 w-full rounded border p-2"
                       data-oc-input="saida" name="hora_saida" placeholder="hh:mm">
              </div>

              <div class="md:col-span-6">
                <label class="block text-sm font-medium">Observações</label>
                <input type="text" name="observacoes" class="mt-1 w-full rounded border p-2 uppercase" data-upcase
                       placeholder="Opcional">
              </div>

              <div class="md:col-span-6 flex justify-end">
                <button class="rounded bg-blue-900 px-5 py-2 text-white shadow hover:opacity-95" type="submit">
                  Lançar
                </button>
              </div>
            </form>
          </div>
        </div>

        {{-- CARD: Transferência de Unidade --}}
        <div class="mt-6 rounded-xl bg-gray-800 p-4 shadow">
            <div class="rounded-lg bg-white p-4 space-y-4">
                <h3 class="text-lg font-semibold uppercase">Transferência de Unidade</h3>

                @php
                    $unidades = \Illuminate\Support\Facades\DB::table('unidades')->select('id','nome')->orderBy('nome')->get();
                    $unidadeAtual = null;
                    if (\Illuminate\Support\Facades\Schema::hasColumn('servidores','unidade_id') && !empty($servidor->unidade_id)) {
                        $unidadeAtual = \Illuminate\Support\Facades\DB::table('unidades')
                            ->where('id', $servidor->unidade_id)
                            ->value('nome');
                    }
                @endphp

                <form method="POST" action="{{ route('servidores.transferir', $servidor->id) }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-1">
                            <label class="block text-sm font-medium">Unidade atual</label>
                            <input class="mt-1 w-full rounded border p-2 bg-gray-100"
                                   value="{{ $unidadeAtual ?: '— SEM UNIDADE VINCULADA —' }}" disabled>
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-sm font-medium">Unidade de destino *</label>
                            <select name="unidade_destino_id" class="mt-1 w-full rounded border p-2" required>
                                <option value="">— SELECIONAR —</option>
                                @foreach($unidades as $u)
                                    <option value="{{ $u->id }}">{{ $u->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-sm font-medium">Data da transferência *</label>
                            <input type="date" name="data_transferencia"
                                   value="{{ now()->toDateString() }}"
                                   class="mt-1 w-full rounded border p-2" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Observações (ex.: referência ao D.O.)</label>
                        <textarea name="observacoes" rows="3" class="mt-1 w-full rounded border p-2 uppercase"
                                  placeholder="EX.: PORTARIA Nº 123/2025, D.O. 03/10/2025, PÁG. 12" data-upcase></textarea>
                    </div>

                    <div class="flex items-center justify-end">
                        <button type="submit" class="rounded bg-blue-900 px-5 py-2 text-white shadow hover:opacity-95">
                            Registrar transferência
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endisset

    {{-- Máscaras e uppercase + trava/espelho do campo Unidade no EDIT + toggle de horas do lançador --}}
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
            if (e.target.matches('[data-upcase]')) {
                e.target.value = (e.target.value || '').toUpperCase();
            }
        }, { passive: true });

        // No EDIT: formata CPF inicial + garante submissão da UNIDADE mesmo desabilitada
        document.addEventListener('DOMContentLoaded', () => {
            @isset($servidor)
                // 1) Formatar qualquer CPF já preenchido
                document.querySelectorAll('form[action*="/servidores/{{ $servidor->id }}"] [data-mask="cpf"]').forEach(el => {
                    el.value = maskCPF(el.value || '');
                });

                // 2) Desabilita visualmente a UNIDADE mas mantém um hidden espelhando o valor para submit
                const formEdit = document.querySelector('form[action*="/servidores/{{ $servidor->id }}"]');
                if (formEdit) {
                    const sel = formEdit.querySelector('select[name="unidade_id"]');
                    if (sel) {
                        sel.setAttribute('disabled','disabled');
                        sel.removeAttribute('required');
                        sel.classList.add('bg-gray-100','cursor-not-allowed');

                        const hint = document.createElement('p');
                        hint.className = 'mt-1 text-xs text-gray-500';
                        hint.textContent = 'A unidade não é editável aqui. Use “Transferência de Unidade”.';
                        sel.parentElement?.appendChild(hint);

                        let hid = formEdit.querySelector('input[type="hidden"][name="unidade_id"]');
                        if (!hid) {
                            hid = document.createElement('input');
                            hid.type = 'hidden';
                            hid.name = 'unidade_id';
                            formEdit.appendChild(hid);
                        }
                        const syncHidden = () => { hid.value = sel.value || ''; };
                        syncHidden();
                        sel.addEventListener('change', syncHidden, { passive: true });
                        formEdit.addEventListener('submit', syncHidden, { passive: true });
                    }
                }

                // 3) Toggle de horas no lançador de ocorrência (não enviar hora quando não é NORMAL)
                const ocForm = document.querySelector('[data-oc-form]');
                if (ocForm) {
                  const tipoEl = ocForm.querySelector('[data-oc-tipo]');
                  const horasGroup = ocForm.querySelectorAll('[data-oc-horas]');
                  const entrada = ocForm.querySelector('[data-oc-input="entrada"]');
                  const saida   = ocForm.querySelector('[data-oc-input="saida"]');

                  function toggleHoras() {
                    const exige = (tipoEl.value === 'NORMAL');
                    horasGroup.forEach(g => g.style.display = exige ? '' : 'none');

                    [entrada, saida].forEach((el) => {
                      if (exige) {
                        if (!el.getAttribute('name')) {
                          const fname = el.dataset.ocInput === 'entrada' ? 'hora_entrada' : 'hora_saida';
                          el.setAttribute('name', fname);
                        }
                        el.removeAttribute('disabled');
                        el.removeAttribute('required');
                      } else {
                        el.value = '';
                        el.removeAttribute('name');   // <- não envia no payload
                        el.setAttribute('disabled','disabled');
                        el.removeAttribute('required');
                      }
                    });
                  }

                  tipoEl.addEventListener('change', toggleHoras, { passive: true });
                  toggleHoras();
                }
            @endisset
        });
    </script>
</x-app-layout>
