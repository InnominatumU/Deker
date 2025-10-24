@php
    $isEdit = isset($visita);
    $action = $isEdit
        ? route('gestao.visitas.update', ['id' => $visita->id])
        : route('gestao.visitas.store');

    $rows = collect($vinculos ?? [])->map(fn($v) => [
        'cadpen'     => $v['cadpen'] ?? '',
        'parentesco' => $v['parentesco'] ?? null,
    ])->toArray();

    if (!$isEdit && old('vinculos')) $rows = old('vinculos');

    $rowsJson   = json_encode($rows ?: [['cadpen'=>'','parentesco'=>null]]);
    // >>> Ajuste: não forçar defaults para SOCIAL/INDIVIDUOS (deixa vazio para mostrar “— SELECIONE —”)
    $oldTipo    = old('tipo', $visita->tipo ?? '');
    $oldDestino = old('destino', $visita->destino ?? '');
@endphp

<form method="POST" action="{{ $action }}" x-data="visitasForm()"
      x-init='init({ rows: @js($rowsJson), tipo: @js($oldTipo), destino: @js($oldDestino) })'
      class="space-y-6">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @if(session('success'))
        <div class="p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 bg-red-100 text-red-800 rounded">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Tipo da Visita</label>
            <select name="tipo" x-model="tipo" class="w-full rounded border-gray-300">
                {{-- Placeholder visível --}}
                <option value="">— SELECIONE —</option>
                @foreach($tipos as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Destino</label>
            <select name="destino" x-model="destino" class="w-full rounded border-gray-300">
                {{-- Placeholder visível --}}
                <option value="">— SELECIONE —</option>
                @foreach($destinos as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
        </div>

        {{-- REMOVIDO: seletor de Unidade quando destino === "UNIDADE" --}}
    </div>

    <hr class="my-2"/>

    {{-- Visitante --}}
    <div class="grid md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Nome completo</label>
            <input type="text" name="nome_completo" required
                   value="{{ old('nome_completo', $visitante->nome_completo ?? '') }}"
                   class="w-full rounded border-gray-300 uppercase">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">CPF</label>
            <input type="text" name="cpf" value="{{ old('cpf', $visitante->cpf ?? '') }}"
                   class="w-full rounded border-gray-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">RG</label>
            <input type="text" name="rg" value="{{ old('rg', $visitante->rg ?? '') }}"
                   class="w-full rounded border-gray-300">
        </div>

        <div x-show="tipo === 'JURIDICA'">
            <label class="block text-sm font-medium mb-1">OAB</label>
            <input type="text" name="oab" value="{{ old('oab', $visitante->oab ?? '') }}"
                   class="w-full rounded border-gray-300">
        </div>
    </div>

    {{-- Campos condicionais --}}
    <div class="grid md:grid-cols-3 gap-4">
        <div x-show="tipo === 'RELIGIOSA'">
            <label class="block text-sm font-medium mb-1">Religião</label>
            <select name="religiao" class="w-full rounded border-gray-300">
                <option value="">— Selecione —</option>
                @foreach($religioes as $r)
                    <option value="{{ $r }}" @selected(old('religiao', $visita->religiao ?? '') === $r)>{{ $r }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="tipo === 'AUTORIDADES'">
            <label class="block text-sm font-medium mb-1">Cargo</label>
            <select name="autoridade_cargo" class="w-full rounded border-gray-300">
                <option value="">— Selecione —</option>
                @foreach($cargos as $c)
                    <option value="{{ $c }}" @selected(old('autoridade_cargo', $visita->autoridade_cargo ?? '') === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="tipo === 'AUTORIDADES'">
            <label class="block text-sm font-medium mb-1">Órgão</label>
            <select name="autoridade_orgao" class="w-full rounded border-gray-300">
                <option value="">— Selecione —</option>
                @foreach($orgaos as $o)
                    <option value="{{ $o }}" @selected(old('autoridade_orgao', $visita->autoridade_orgao ?? '') === $o)>{{ $o }}</option>
                @endforeach
            </select>
        </div>

        {{-- Prestador de Serviços --}}
        <div class="md:col-span-2" x-show="tipo === 'PRESTADOR'">
            <label class="block text-sm font-medium mb-1">Empresa (Prestador de Serviços)</label>
            <input type="text" name="prestador_empresa"
                   value="{{ old('prestador_empresa', $visita->prestador_empresa ?? '') }}"
                   class="w-full rounded border-gray-300 uppercase"
                   placeholder="NOME DA EMPRESA">
        </div>
        <div x-show="tipo === 'PRESTADOR'">
            <label class="block text-sm font-medium mb-1">CNPJ</label>
            <input type="text" name="prestador_cnpj"
                   value="{{ old('prestador_cnpj', $visita->prestador_cnpj ?? '') }}"
                   class="w-full rounded border-gray-300"
                   placeholder="00.000.000/0000-00"
                   inputmode="numeric" maxlength="18"
                   oninput="
                        this.value = this.value
                          .replace(/\D/g,'')                 // mantém só dígitos
                          .replace(/^(\d{2})(\d)/,'$1.$2')   // 00.
                          .replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3') // 00.000.
                          .replace(/\.(\d{3})(\d)/,'\.$1/$2')          // 00.000.000/
                          .replace(/(\d{4})(\d)/,'$1-$2')    // 00.000.000/0000-00
                          .slice(0,18);
                   ">
        </div>

        <div class="md:col-span-3" x-show="tipo === 'OUTRAS'">
            <label class="block text-sm font-medium mb-1">Descrição (Outras visitas)</label>
            <textarea name="descricao_outros" rows="3" class="w-full rounded border-gray-300">{{ old('descricao_outros', $visita->descricao_outros ?? '') }}</textarea>
        </div>
    </div>

    {{-- Vínculos por CadPen --}}
    <div x-show="destino === 'INDIVIDUOS'" class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold">Vincular Indivíduo(s) pelo CadPen</h3>
            <button type="button" @click="addRow()" class="px-3 py-1 rounded bg-blue-900 text-white">+ Adicionar</button>
        </div>

        @error('vinculos')
            <div class="p-2 mt-2 rounded border border-red-200 bg-red-50 text-red-800 text-sm">
                {{ $message }}
            </div>
        @enderror

        <template x-for="(row, idx) in rows" :key="idx">
            <div class="space-y-2">
                <div class="grid md:grid-cols-7 gap-3 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">CadPen</label>
                        <input type="text" class="w-full rounded border-gray-300"
                               :name="`vinculos[${idx}][cadpen]`" x-model="row.cadpen" placeholder="ex: 2025-000123"
                               :readonly="row.confirmed"
                               @keydown.enter.prevent="buscar(idx)">
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium mb-1">Parentesco/Relacionamento</label>
                        <select class="w-full rounded border-gray-300"
                                :name="`vinculos[${idx}][parentesco]`" x-model="row.parentesco"
                                :disabled="row.loading">
                            <option value="">— Selecione —</option>
                            @foreach($parentescos as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-1">
                        <button type="button"
                                @click="buscar(idx)"
                                class="w-full px-3 py-2 rounded bg-blue-900 text-white disabled:opacity-50"
                                :disabled="row.loading || !row.cadpen">
                            <span x-show="!row.loading">Buscar</span>
                            <span x-show="row.loading">Buscando...</span>
                        </button>
                    </div>

                    <div class="md:col-span-1">
                        <button type="button" @click="removeRow(idx)"
                                class="w-full px-3 py-2 rounded bg-gray-200"
                                :disabled="row.loading">
                            Remover
                        </button>
                    </div>
                </div>

                <div x-show="row.found && !row.confirmed" class="p-3 rounded bg-blue-50 border border-blue-200 text-sm flex items-center justify-between">
                    <div>
                        <strong>Encontrado:</strong>
                        <span x-text="row.found.nome"></span>
                        (<span x-text="row.found.cadpen"></span>)
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="confirmar(idx)" class="px-3 py-1 rounded bg-green-600 text-white">Confirmar</button>
                        <button type="button" @click="cancelar(idx)" class="px-3 py-1 rounded bg-red-600 text-white">Cancelar</button>
                    </div>
                </div>

                <div x-show="row.confirmed" class="p-3 rounded bg-green-50 border border-green-200 text-sm flex items-center justify-between">
                    <div>
                        <strong>Vínculo confirmado:</strong>
                        <span x-text="row.found?.nome || '—'"></span>
                        (<span x-text="row.found?.cadpen || row.cadpen"></span>)
                    </div>
                    <div>
                        <button type="button" @click="cancelar(idx)" class="px-3 py-1 rounded bg-yellow-600 text-white">Desfazer</button>
                    </div>
                    <input type="hidden" :name="`vinculos[${idx}][individuo_id]`" :value="row.found?.id">
                    <input type="hidden" :name="`vinculos[${idx}][confirmed]`" value="1">
                </div>

                <div x-show="row.error" class="p-2 rounded bg-red-50 border border-red-200 text-sm text-red-800" x-text="row.error"></div>

                <div class="h-px bg-gray-200"></div>
            </div>
        </template>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Observações</label>
        <textarea name="observacoes" rows="3" class="w-full rounded border-gray-300">{{ old('observacoes', $visita->observacoes ?? '') }}</textarea>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="px-4 py-2 rounded bg-blue-900 text-white">
            {{ $isEdit ? 'Salvar Alterações' : 'Cadastrar Visita' }}
        </button>
        <a href="{{ route('inicio') }}" class="px-4 py-2 rounded bg-gray-800 text-white">Cancelar</a>
    </div>
</form>

<script>
function visitasForm() {
    const AJAX_FIND_URL = @js(route('gestao.visitas.ajax.find_individuo')); // rota AJAX

    return {
        tipo: '',
        destino: '',
        rows: [{cadpen:'', parentesco:null, loading:false, error:null, found:null, confirmed:false}],
        init({rows, tipo, destino}) {
            try { this.rows = JSON.parse(rows); } catch(e) {}
            if (!Array.isArray(this.rows) || this.rows.length === 0) {
                this.rows = [{cadpen:'', parentesco:null, loading:false, error:null, found:null, confirmed:false}];
            } else {
                this.rows = this.rows.map(r => Object.assign({loading:false, error:null, found:null, confirmed:false}, r));
            }
            // >>> Ajuste: não forçar defaults; usa vazio quando não vier valor
            this.tipo = (typeof tipo === 'string' ? tipo : '') || '';
            this.destino = (typeof destino === 'string' ? destino : '') || '';
        },
        async buscar(i) {
            const row = this.rows[i];
            row.error = null; row.found = null; row.confirmed = false;
            const cad = (row.cadpen || '').trim();
            if (!cad) { row.error = 'Informe o CadPen antes de buscar.'; return; }
            row.loading = true;
            try {
                const url = new URL(AJAX_FIND_URL, window.location.origin);
                url.searchParams.set('cadpen', cad);
                const resp = await fetch(url.toString(), { headers: { 'Accept': 'application/json' }});
                const data = await resp.json();
                if (!resp.ok) row.error = data?.message || 'Não encontrado.';
                else row.found = data; // {id, nome, cadpen}
            } catch (e) {
                row.error = 'Falha ao buscar. Verifique sua conexão.';
            } finally {
                row.loading = false;
            }
        },
        confirmar(i) { if (this.rows[i].found) this.rows[i].confirmed = true; },
        cancelar(i)  { Object.assign(this.rows[i], {confirmed:false, found:null, error:null}); },
        addRow()     { this.rows.push({cadpen:'', parentesco:null, loading:false, error:null, found:null, confirmed:false}); },
        removeRow(i) { if (this.rows.length > 1) this.rows.splice(i,1); },
    }
}
</script>
