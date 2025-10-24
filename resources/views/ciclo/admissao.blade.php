{{-- resources/views/ciclo/admissao.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">CICLO DE VÍNCULOS — Admissão</h2>
    </x-slot>

    @php
        // Sem "use" em Blade
        $user = \Illuminate\Support\Facades\Auth::user();
        $podeTrocarUnidade = $user && $user->can('menu.sistema');

        $unidadeAtualId   = $user->unidade_id   ?? null;
        $unidadeAtualNome = $user->unidade_nome ?? null;

        if (!$unidadeAtualNome && $unidadeAtualId && \Illuminate\Support\Facades\Schema::hasTable('unidades')) {
            $row = \Illuminate\Support\Facades\DB::table('unidades')->where('id', $unidadeAtualId)->first();
            $unidadeAtualNome = $row->nome ?? null;
        }

        $todasUnidades = [];
        if ($podeTrocarUnidade && \Illuminate\Support\Facades\Schema::hasTable('unidades')) {
            $todasUnidades = \Illuminate\Support\Facades\DB::table('unidades')
                ->orderBy('nome')
                ->get(['id','nome'])
                ->toArray();
        }
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-6" x-data="admissaoCiclo({
            unidadePadraoId: {{ $unidadeAtualId ? (int)$unidadeAtualId : 'null' }},
            unidadePadraoNome: @js($unidadeAtualNome),
            podeTrocarUnidade: {{ $podeTrocarUnidade ? 'true' : 'false' }},
            unidadesLista: @js($todasUnidades),
        })">

        {{-- BUSCA POR INDIVÍDUO --}}
        <section class="bg-gray-100 rounded-xl p-4 md:p-6 shadow">
            <h3 class="text-lg font-semibold mb-4">Localizar Indivíduo</h3>

            <div class="grid md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="cadpen" class="block text-sm font-medium">CADPEN (Nº)</label>
                    <input x-model.trim="cadpen" id="cadpen" type="text" inputmode="numeric"
                           class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                           placeholder="DIGITE O CADPEN"/>
                </div>

                <div class="flex gap-2">
                    <button @click="carregarPorCadpen()" type="button"
                            class="min-h-[44px] px-4 rounded-lg bg-blue-900 text-white font-medium hover:bg-blue-800 disabled:opacity-50 inline-flex items-center justify-center whitespace-normal text-center">
                        CARREGAR PELO CADPEN
                    </button>

                    <button @click="identificarPorBiometria()" type="button"
                            class="min-h-[44px] px-4 rounded-lg bg-gray-800 text-white font-medium hover:bg-gray-700 inline-flex items-center justify-center whitespace-normal text-center">
                        IDENTIFICAR PELA BIOMETRIA
                    </button>
                </div>

                <div class="text-gray-600">
                    <div class="text-base md:text-xl">DATA/HORA DA ADMISSÃO:</div>
                    <div class="font-mono text-gray-900 mt-1 text-lg md:text-2xl" x-text="agoraBR"></div>
                </div>
            </div>

            <template x-if="loading">
                <div class="mt-4 text-sm text-gray-600">Carregando dados do indivíduo…</div>
            </template>

            <template x-if="notFound">
                <div class="mt-4 p-4 rounded-lg border border-amber-300 bg-amber-50 text-amber-900">
                    Indivíduo não encontrado para o CadPen informado.
                    <div class="mt-2">
                        <a href="{{ route('gestao.dados.create') }}"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-900 text-white hover:bg-blue-800">
                            Cadastrar Indivíduo
                        </a>
                    </div>
                </div>
            </template>
        </section>

        {{-- DADOS DO INDIVÍDUO --}}
        <section class="mt-6" x-show="individuo" x-cloak>
            <div class="bg-white rounded-xl shadow p-4 md:p-6">
                <h3 class="text-lg font-semibold mb-4">Dados do Indivíduo</h3>

                <div class="grid md:grid-cols-4 gap-6">
                    <div class="md:col-span-3">
                        <dl class="grid grid-cols-1 gap-y-2 text-sm leading-7">
                            <div class="grid grid-cols-3">
                                <dt class="font-semibold text-gray-600">CADPEN</dt>
                                <dd class="col-span-2 text-lg" x-text="individuo?.cadpen ?? '—'"></dd>
                            </div>

                            <div class="grid grid-cols-3">
                                <dt class="font-semibold text-gray-600">NOME COMPLETO</dt>
                                <dd class="col-span-2 text-lg" x-text="individuo?.nome ?? '—'"></dd>
                            </div>

                            <div class="grid grid-cols-3">
                                <dt class="font-semibold text-gray-600">MÃE</dt>
                                <dd class="col-span-2 text-lg" x-text="individuo?.mae ?? '—'"></dd>
                            </div>

                            <div class="grid grid-cols-3">
                                <dt class="font-semibold text-gray-600">PAI</dt>
                                <dd class="col-span-2 text-lg" x-text="individuo?.pai ?? '—'"></dd>
                            </div>

                            <div class="grid grid-cols-3">
                                <dt class="font-semibold text-gray-600">DATA DE NASC.</dt>
                                <dd class="col-span-2 text-lg" x-text="dataNascBR()"></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="flex md:justify-end">
                        <img :src="fotoFrontal()" alt="Foto frontal"
                             class="h-40 w-40 object-cover rounded-lg border border-gray-200"
                             onerror="this.onerror=null; this.src='{{ asset('images/placeholder-face.png') }}'">
                    </div>
                </div>
            </div>
        </section>

        {{-- FORMULÁRIO PRINCIPAL --}}
        <section class="mt-6" x-bind:class="!individuo ? 'opacity-50 pointer-events-none' : ''">
            <form method="POST" action="{{ route('ciclo.admissoes.store') }}" class="bg-white rounded-xl shadow p-4 md:p-6" @submit="prepareSubmit">
                @csrf

                {{-- UNIDADE --}}
                <div class="mb-6">
                    <label class="block text-sm font-medium">UNIDADE</label>

                    <template x-if="!podeTrocarUnidade">
                        <div>
                            <input type="text" :value="unidadeNome()" class="mt-1 w-full rounded-lg border-gray-300 bg-gray-100" disabled>
                            <input type="hidden" name="unidade_id" :value="unidadeId">
                        </div>
                    </template>

                    <template x-if="podeTrocarUnidade">
                        <select name="unidade_id"
                                x-model="unidadeId"
                                class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                            <option value="">— SELECIONE —</option>
                            <template x-for="u in unidades" :key="u.id">
                                <option :value="u.id" x-text="u.nome"></option>
                            </template>
                        </select>
                    </template>
                </div>

                {{-- Tipo de Vínculo --}}
                <div>
                    <h3 class="text-lg font-semibold mb-4">Tipo de Vínculo</h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="tipo='ADMISSAO'"      :class="btnTipo('ADMISSAO')">ADMISSÃO</button>
                        <button type="button" @click="tipo='TRANSFERENCIA'" :class="btnTipo('TRANSFERENCIA')">TRANSFERÊNCIA</button>
                        <button type="button" @click="tipo='TRANSITO'"      :class="btnTipo('TRANSITO')">TRÂNSITO</button>
                        <button type="button" @click="tipo='DESLIGAMENTO'"  :class="btnTipo('DESLIGAMENTO')">DESLIGAMENTO</button>
                    </div>
                    <input type="hidden" name="tipo" :value="tipo">
                    <input type="hidden" name="cadpen" :value="individuo?.cadpen ?? ''">
                </div>

                <hr class="my-6">

                {{-- Origem + UF (não se aplica a Desligamento) --}}
                <div class="grid md:grid-cols-3 gap-4" x-show="tipo!=='DESLIGAMENTO'">
                    <div>
                        <label class="block text-sm font-medium">ORIGEM (ÓRGÃO)</label>
                        <select name="origem"
                                class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                            <option value="">— SELECIONE —</option>
                            <option>PC</option>
                            <option>PM</option>
                            <option>PF</option>
                            <option>PRF</option>
                            <option>UNIDADE PRISIONAL</option>
                            <option>OUTROS</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Ex.: “12ª DP DE URUAÇU”.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">COMPLEMENTO DA ORIGEM</label>
                        <input name="origem_complemento" type="text"
                               class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                               placeholder="DESCRIÇÃO COMPLEMENTAR (DELEGACIA/UNIDADE/ÓRGÃO)">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">UF DE ORIGEM</label>
                        <select name="uf_origem"
                                class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                            <option value="">— SELECIONE —</option>
                            @php
                                $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            @endphp
                            @foreach ($ufs as $uf)
                                <option value="{{ $uf }}">{{ $uf }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- ===================== ENQUADRAMENTO (LEI -> ARTIGOS COM CONFIRMAR) ===================== --}}
                <div class="mt-6" x-show="tipo!=='DESLIGAMENTO'" x-data="enquadramentoLeiArtigos()" x-cloak>
                    <h4 class="font-semibold mb-3">Enquadramento</h4>

                    {{-- LEI EM EDIÇÃO (permanece no topo) --}}
                    <div class="grid md:grid-cols-6 gap-4 bg-gray-50 rounded-xl p-4 border">
                        <div>
                            <label class="block text-sm font-medium">LEI Nº *</label>
                            <input x-model.trim="lei.numero"
                                   x-on:input="lei.numero = toDigits(lei.numero)"
                                   class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                   placeholder="Ex.: 123">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">ANO *</label>
                            <input x-model.trim="lei.ano"
                                   x-on:input="lei.ano = toDigits(lei.ano)"
                                   maxlength="4"
                                   class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Ex.: 2018">
                        </div>
                        <div class="md:col-span-4 flex items-end gap-2">
                            <span class="text-sm text-gray-600">Adicione os artigos abaixo e confirme cada um.</span>
                        </div>
                    </div>

                    {{-- ARTIGO ATUAL (edição unitária) --}}
                    <div class="mt-4 bg-white rounded-xl border p-4">
                        <h5 class="font-semibold mb-3">Artigo (Edição)</h5>
                        <div class="grid md:grid-cols-6 gap-4">
                            <div>
                                <label class="block text-sm font-medium">ARTIGO *</label>
                                <input x-model.trim="artigo.artigo"
                                       x-on:input="artigo.artigo = normalizeArtigo(artigo.artigo)"
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                       placeholder="1, 2, 10, 45 (pode digitar 1º se quiser)">
                                <p class="text-xs text-gray-500 mt-1">Aceita “1” ou “1º”. O “º” é aplicado apenas na exibição quando 1..9.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium">INCISO(S)</label>
                                <input x-model.trim="artigo.incisos"
                                       x-on:input="artigo.incisos = normalizeIncisos(artigo.incisos)"
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                       placeholder="I, II, IV (use vírgulas e/ou “e”)">
                            </div>

                            <div>
                                <label class="block text-sm font-medium">ALÍNEA(S)</label>
                                <input x-model.trim="artigo.alineas"
                                       x-on:input="artigo.alineas = normalizeAlineas(artigo.alineas)"
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                       placeholder="a, b, c (use vírgulas e/ou “e”)">
                            </div>

                            <div>
                                <label class="block text-sm font-medium">PARÁGRAFO(S)</label>
                                <input x-model.trim="artigo.paragrafos"
                                       x-on:input="artigo.paragrafos = normalizeParagrafos(artigo.paragrafos)"
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                       placeholder="único, 2, 3 (use vírgulas e/ou “e”)">
                            </div>

                            <div class="flex items-end gap-2">
                                <button type="button" class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-700"
                                        @click="confirmarArtigo()">CONFIRMAR ARTIGO</button>

                                <button type="button" class="px-4 py-2 rounded-lg border hover:bg-gray-50"
                                        @click="novoArtigo()">NOVO ARTIGO</button>
                            </div>
                        </div>

                        {{-- Lista de artigos confirmados para esta LEI --}}
                        <template x-if="lei.artigos.length">
                            <div class="mt-4">
                                <div class="text-sm font-semibold mb-2">ARTIGOS CONFIRMADOS PARA ESTA LEI</div>
                                <ul class="space-y-2">
                                    <template x-for="(a, i) in lei.artigos" :key="i">
                                        <li class="flex items-start justify-between gap-4">
                                            <div class="text-base leading-7" x-html="fmtArtigoLinha(a)"></div>
                                            <button type="button" class="px-3 py-1 rounded border hover:bg-gray-50"
                                                    @click="remArtigo(i)">Remover</button>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>

                    {{-- Ações sobre a LEI em edição --}}
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="px-4 py-2 rounded-lg bg-blue-900 text-white hover:bg-blue-800"
                                @click="inserirLei()">INSERIR</button>

                        <button type="button" class="px-4 py-2 rounded-lg border border-blue-900 text-blue-900 hover:bg-blue-50"
                                @click="inserirLeiCombinado()">COMBINADO COM</button>
                    </div>

                    {{-- Leis inseridas (resultado final) --}}
                    <div class="mt-6">
                        <h4 class="font-semibold mb-2">ENQUADRAMENTOS INSERIDOS</h4>
                        <template x-if="leis.length === 0">
                            <div class="text-sm text-gray-500">Nenhum enquadramento adicionado.</div>
                        </template>

                        <template x-for="(L, idx) in leis" :key="idx">
                            <div class="mb-3 p-3 rounded-lg border bg-white">
                                <div class="flex justify-between items-start">
                                    <div class="font-medium" x-text="formatLeiCabecalho(L)"></div>
                                    <button type="button" class="text-sm px-3 py-1 rounded-lg border hover:bg-gray-50"
                                            @click="remLei(idx)">Remover</button>
                                </div>
                                <div class="text-xl mt-2 leading-8" x-html="formatLeiDetalhe(L)"></div>
                            </div>
                        </template>
                    </div>

                    {{-- JSON final --}}
                    <input type="hidden" name="enquadramentos_json" :value="JSON.stringify(leis)">
                </div>
                {{-- ===================== /ENQUADRAMENTO ===================== --}}

                {{-- Motivo (ADMISSAO) --}}
                <div class="mt-6 grid md:grid-cols-2 gap-4" x-show="tipo==='ADMISSAO'">
                    <div>
                        <label class="block text-sm font-medium">MOTIVO</label>
                        <select name="motivo"
                                class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                            <option value="">— SELECIONE —</option>
                            <option value="PRISAO_EM_FLAGRANTE">PRISÃO EM FLAGRANTE</option>
                            <option value="MANDADO_DE_PRISAO">MANDADO DE PRISÃO</option>
                            <option value="PRISAO_TEMPORARIA">PRISÃO TEMPORÁRIA</option>
                            <option value="PRISAO_PREVENTIVA">PRISÃO PREVENTIVA</option>
                            <option value="CUMPRIMENTO_DE_SENTENCA">CUMPRIMENTO DE SENTENÇA</option>
                            <option value="RECAPTURA">RECAPTURA</option>
                            <option value="APRESENTACAO_ESPONTANEA">APRESENTAÇÃO ESPONTÂNEA</option>
                            <option value="MEDIDA_DE_SEGURANCA">MEDIDA DE SEGURANÇA</option>
                            <option value="AUDIENCIA_DE_CUSTODIA">APÓS AUDIÊNCIA DE CUSTÓDIA</option>
                            <option value="OUTROS">OUTROS</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">DESCRIÇÃO DO MOTIVO (SE “OUTROS”)</label>
                        <input name="motivo_descricao" type="text"
                               class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                               placeholder="DETALHE O MOTIVO QUANDO 'OUTROS' FOR SELECIONADO">
                    </div>
                </div>

                {{-- UNIDADE DE DESTINO (transferência/transito ou desligamento com destino) --}}
                <div class="mt-6" x-show="tipo==='TRANSFERENCIA' || tipo==='TRANSITO' || desligamento.tipo==='TRANSFERENCIA' || desligamento.tipo==='TRANSITO'">
                    <label class="block text-sm font-medium">UNIDADE DE DESTINO</label>
                    <template x-if="podeTrocarUnidade">
                        <select name="destino_unidade_id"
                                x-model="destinoUnidadeId"
                                class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                            <option value="">— SELECIONE —</option>
                            <template x-for="u in unidades" :key="'dest-'+u.id">
                                <option :value="u.id" x-text="u.nome"></option>
                            </template>
                        </select>
                    </template>
                    <template x-if="!podeTrocarUnidade">
                        <input type="text" class="mt-1 w-full rounded-lg border-gray-300 bg-gray-50" placeholder="SELEÇÃO PELO USUÁRIO DE PERFIL SUPERIOR" disabled>
                    </template>
                </div>

                {{-- DESLIGAMENTO --}}
                <div class="mt-8 border-t pt-6" x-show="tipo==='DESLIGAMENTO'">
                    <h3 class="text-lg font-semibold mb-4">Desligamento</h3>

                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium">TIPO</label>
                            <select x-model="desligamento.tipo"
                                    name="desligamento_tipo"
                                    class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                                <option value="">— SELECIONE —</option>
                                <option value="ALVARA">ALVARÁ</option>
                                <option value="RELAXAMENTO_DE_PRISAO">RELAXAMENTO DE PRISÃO</option>
                                <option value="LIBERDADE_CONDICIONAL">LIBERDADE CONDICIONAL</option>
                                <option value="TRANSFERENCIA">TRANSFERÊNCIA</option>
                                <option value="TRANSITO">TRÂNSITO</option>
                                <option value="OUTROS">OUTROS</option>
                            </select>
                        </div>

                        <div x-show="desligamento.tipo==='TRANSFERENCIA' || desligamento.tipo==='TRANSITO'">
                            <label class="block text-sm font-medium">DESTINO</label>
                            <template x-if="podeTrocarUnidade">
                                <select x-model="destinoUnidadeId"
                                        name="desligamento_destino_unidade_id"
                                        class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                                    <option value="">— SELECIONE —</option>
                                    <template x-for="u in unidades" :key="'d2-'+u.id">
                                        <option :value="u.id" x-text="u.nome"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="!podeTrocarUnidade">
                                <input type="text" class="mt-1 w-full rounded-lg border-gray-300 bg-gray-50" placeholder="SELEÇÃO PELO USUÁRIO DE PERFIL SUPERIOR" disabled>
                            </template>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">OBSERVAÇÃO</label>
                            <input name="desligamento_observacao" type="text"
                                   class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                   placeholder="OPCIONAL">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="button"
                                class="px-4 py-2 rounded-lg bg-red-700 text-white hover:bg-red-600"
                                @click="iniciarDesligamento()">
                            INICIAR DESLIGAMENTO
                        </button>
                        <p class="text-xs text-gray-500 mt-2">
                            Ao salvar, o sistema registrará a DATA/HORA do desligamento automaticamente.
                            Em TRANSFERÊNCIAS/TRÂNSITO o status ficará <strong>EM DESLOCAMENTO</strong> até o aceite na unidade de destino.
                        </p>
                    </div>
                </div>

                {{-- AÇÕES --}}
                <div class="mt-8 flex items-center gap-3">
                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-blue-900 text-white font-semibold hover:bg-blue-800">
                        REGISTRAR
                    </button>
                    <a href="{{ route('ciclo.admissoes.index') }}"
                       class="px-4 py-2 rounded-xl border border-gray-300 hover:bg-gray-50">CANCELAR</a>
                </div>
            </form>
        </section>

        {{-- HISTÓRICO (placeholder) --}}
        <section class="mt-10">
            <h3 class="text-lg font-semibold mb-3">Histórico de Vínculos</h3>
            <div class="rounded-xl border bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs uppercase tracking-wider text-gray-600">
                                <th class="px-4 py-2">DATA ADMISSÃO</th>
                                <th class="px-4 py-2">HORA ADMISSÃO</th>
                                <th class="px-4 py-2">UNIDADE</th>
                                <th class="px-4 py-2">DATA DESLIG.</th>
                                <th class="px-4 py-2">HORA DESLIG.</th>
                                <th class="px-4 py-2">MOTIVO DESLIG.</th>
                                <th class="px-4 py-2">STATUS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm">
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                    A listagem será exibida aqui após implantarmos a leitura do histórico no controller.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    {{-- Alpine.js --}}
    <script>
        function admissaoCiclo(cfg) {
            return {
                cadpen: '',
                individuo: null,
                notFound: false,
                loading: false,
                tipo: 'ADMISSAO',
                agoraBR: new Date().toLocaleString('pt-BR'),

                podeTrocarUnidade: !!cfg?.podeTrocarUnidade,
                unidadeId: cfg?.unidadePadraoId ?? null,
                unidadeNomePadrao: cfg?.unidadePadraoNome ?? null,
                unidades: Array.isArray(cfg?.unidadesLista) ? cfg.unidadesLista : [],
                destinoUnidadeId: null,

                desligamento: { tipo: '', observacao: '' },

                btnTipo(t) {
                    return [
                        'px-3','py-2','rounded-lg','border','text-sm',
                        this.tipo===t ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-gray-800 border-gray-300 hover:bg-gray-50'
                    ].join(' ');
                },
                unidadeNome() {
                    if (this.podeTrocarUnidade) {
                        const u = this.unidades.find(x => String(x.id) === String(this.unidadeId));
                        return u?.nome || this.unidadeNomePadrao || '—';
                    }
                    return this.unidadeNomePadrao || '—';
                },

                async carregarPorCadpen() {
                    if (!this.cadpen) return;
                    this.loading = true; this.notFound = false; this.individuo = null;
                    try {
                        const resp = await fetch(`/ajax/individuos/by-cadpen/${encodeURIComponent(this.cadpen)}`);
                        if (!resp.ok) throw new Error('Falha na busca');
                        const data = await resp.json();
                        if (!data || !data.id) {
                            this.notFound = true;
                        } else {
                            this.individuo = {
                                id: data.id,
                                cadpen: data.cadpen,
                                nome: data.nome_completo ?? data.nome,
                                mae: data.mae ?? data.nome_mae,
                                pai: data.pai ?? data.nome_pai,
                                data_nascimento: data.data_nascimento,
                                foto_frontal_url: data.foto_frontal_url || null
                            };
                        }
                    } catch (e) {
                        console.error(e);
                        this.notFound = true;
                    } finally {
                        this.loading = false;
                    }
                },

                identificarPorBiometria() {
                    alert('Integração de biometria será feita no módulo de Identificação.');
                },

                dataNascBR() {
                    if (!this.individuo?.data_nascimento) return '—';
                    try {
                        const d = new Date(this.individuo.data_nascimento);
                        return isNaN(d) ? this.individuo.data_nascimento : d.toLocaleDateString('pt-BR');
                    } catch { return this.individuo.data_nascimento; }
                },

                fotoFrontal() {
                    return this.individuo?.foto_frontal_url || '{{ asset('images/placeholder-face.png') }}';
                },

                iniciarDesligamento() {
                    if (!this.desligamento.tipo) {
                        alert('Selecione o TIPO do desligamento.');
                        return;
                    }
                    alert('Desligamento iniciado. A data/hora será registrada ao salvar.');
                },

                prepareSubmit(e) {
                    if (!this.unidadeId) {
                        alert('UNIDADE obrigatória.');
                        e.preventDefault();
                        return;
                    }
                    if ((this.tipo==='TRANSFERENCIA' || this.tipo==='TRANSITO') && !this.destinoUnidadeId && this.podeTrocarUnidade) {
                        alert('Selecione a UNIDADE DE DESTINO.');
                        e.preventDefault();
                        return;
                    }
                    if (this.tipo==='DESLIGAMENTO' && (this.desligamento.tipo==='TRANSFERENCIA' || this.desligamento.tipo==='TRANSITO') && !this.destinoUnidadeId && this.podeTrocarUnidade) {
                        alert('Selecione a UNIDADE DE DESTINO para o desligamento.');
                        e.preventDefault();
                        return;
                    }
                },
            }
        }

        // ======= Enquadramento: LEI (mantém no topo) -> múltiplos ARTIGOS confirmados =======
        function enquadramentoLeiArtigos() {
            return {
                leis: [], // coleção final
                lei: { numero: '', ano: '', artigos: [], combinadoCom: false },
                artigo: { artigo:'', incisos:'', alineas:'', paragrafos:'' },

                toDigits(v){ return (v||'').replace(/[^0-9]/g,''); },

                // Normalizadores
                normalizeArtigo(v){
                    // aceita números e opcional "º", mas não auto-injeta "º" (evita 1º2)
                    return (v||'').toUpperCase().replace(/[^0-9º]/g,'');
                },
                normalizeIncisos(v){
                    v = (v||'').toUpperCase().replace(/[^IVX, E]/g,'');
                    return v.replace(/\s+/g,' ').trim();
                },
                normalizeAlineas(v){
                    v = (v||'').toUpperCase().replace(/[^A-Z0-9, E]/g,'');
                    return v.replace(/\s+/g,' ').trim();
                },
                normalizeParagrafos(v){
                    v = (v||'').toUpperCase().replace(/[^0-9ÚNICO, E]/g,'');
                    return v.replace(/\s+/g,' ').trim();
                },

                // Confirmar um artigo (adiciona à lei atual)
                confirmarArtigo(){
                    if(!this.lei.numero || !this.lei.ano){
                        alert('Preencha LEI e ANO antes de confirmar artigos.');
                        return;
                    }
                    if(!this.artigo.artigo){
                        alert('Informe o número do ARTIGO.');
                        return;
                    }
                    const obj = {
                        artigo: this.artigo.artigo,
                        incisos: this.splitMulti(this.artigo.incisos, 'romano'),
                        alineas: this.splitMulti(this.artigo.alineas, 'alinea'),
                        paragrafos: this.splitMulti(this.artigo.paragrafos, 'paragrafo'),
                    };
                    this.lei.artigos.push(obj);
                    this.novoArtigo(); // limpa os campos para digitar o próximo
                },

                novoArtigo(){
                    this.artigo = { artigo:'', incisos:'', alineas:'', paragrafos:'' };
                },

                remArtigo(i){
                    this.lei.artigos.splice(i,1);
                },

                // Inserir a lei (como principal)
                inserirLei(){
                    if(!this.lei.numero || !this.lei.ano){
                        alert('Preencha LEI e ANO.');
                        return;
                    }
                    if(this.lei.artigos.length===0){
                        alert('Confirme ao menos 1 ARTIGO para esta lei.');
                        return;
                    }
                    const push = JSON.parse(JSON.stringify(this.lei));
                    push.numero = this.toDigits(push.numero);
                    push.ano    = this.toDigits(push.ano);
                    push.combinadoCom = false;
                    this.leis.push(push);
                    this.resetLei();
                },

                // Inserir a lei como "COMBINADO COM"
                inserirLeiCombinado(){
                    if(!this.lei.numero || !this.lei.ano){
                        alert('Preencha LEI e ANO.');
                        return;
                    }
                    if(this.lei.artigos.length===0){
                        alert('Confirme ao menos 1 ARTIGO para esta lei.');
                        return;
                    }
                    const push = JSON.parse(JSON.stringify(this.lei));
                    push.numero = this.toDigits(push.numero);
                    push.ano    = this.toDigits(push.ano);
                    push.combinadoCom = true;
                    this.leis.push(push);
                    this.resetLei();
                },

                resetLei(){
                    this.lei = { numero:'', ano:'', artigos:[], combinadoCom:false };
                    this.novoArtigo();
                },

                remLei(i){ this.leis.splice(i,1); },

                // Helpers de parsing
                splitMulti(s, tipo){
                    if(!s) return [];
                    s = s.replace(/\s+E\s+/gi, ',');
                    return s.split(',')
                        .map(x => x.trim())
                        .filter(Boolean)
                        .map(x => {
                            if(tipo==='romano'){ return x.replace(/[^IVX]/gi,'').toUpperCase(); }
                            if(tipo==='alinea'){ return x.toUpperCase(); }
                            if(tipo==='paragrafo'){
                                x = x.toUpperCase();
                                return x === 'ÚNICO' ? 'ÚNICO' : x.replace(/[^0-9]/g,'');
                            }
                            return x;
                        });
                },

                // Formatação visual
                artLabel(n){
                    // “1”/“1º” => exibe 1º; 10 => 10
                    const raw = String(n||'').toUpperCase();
                    const m = raw.match(/^(\d+)(?:º)?$/);
                    if(m){
                        const v = parseInt(m[1],10);
                        return (v>=1 && v<=9) ? `${v}º` : `${v}`;
                    }
                    return raw;
                },
                incisosLabel(arr){ return arr && arr.length ? 'INCISO ' + arr.join(', ') : ''; },
                alineasLabel(arr){ return arr && arr.length ? 'ALÍNEA ' + arr.join(', ') : ''; },
                paragrafosLabel(arr){
                    if(!arr || !arr.length) return '';
                    const itens = arr.map(p => p==='ÚNICO' ? '§ ÚNICO' : `§ ${parseInt(p,10)}º`);
                    return itens.join(', ');
                },

                fmtArtigoLinha(a){
                    const partes = [];
                    if(a.incisos?.length)   partes.push(this.incisosLabel(a.incisos));
                    if(a.alineas?.length)   partes.push(this.alineasLabel(a.alineas));
                    if(a.paragrafos?.length)partes.push(this.paragrafosLabel(a.paragrafos));
                    return partes.length
                        ? `ART. ${this.artLabel(a.artigo)} (${partes.join('; ')})`
                        : `ART. ${this.artLabel(a.artigo)}`;
                },

                formatLeiCabecalho(L){
                    const head = `LEI ${L.numero}/${L.ano}`;
                    return L.combinadoCom ? `${head} — COMBINADO COM` : head;
                },
                formatLeiDetalhe(L){
                    return (L.artigos||[]).map(a => this.fmtArtigoLinha(a)).join(' | ');
                }
            };
        }
    </script>
</x-app-layout>
