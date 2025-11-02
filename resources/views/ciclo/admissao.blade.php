{{-- resources/views/ciclo/admissao.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">CICLO DE VÍNCULOS — Admissão</h2>
    </x-slot>

    @php
        $user = \Illuminate\Support\Facades\Auth::user();

        // Carrega a lista de unidades para o dropdown
        $todasUnidades = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('unidades')) {
            $todasUnidades = \Illuminate\Support\Facades\DB::table('unidades')
                ->orderBy('nome')
                ->get(['id','nome'])
                ->toArray();
        }

        $unidadeAtualId   = $user->unidade_id   ?? null;
        $unidadeAtualNome = $user->unidade_nome ?? null;
        if (!$unidadeAtualNome && $unidadeAtualId && \Illuminate\Support\Facades\Schema::hasTable('unidades')) {
            $row = \Illuminate\Support\Facades\DB::table('unidades')->where('id', $unidadeAtualId)->first();
            $unidadeAtualNome = $row->nome ?? null;
        }
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-6"
         x-data="admissaoCiclo({
            unidadesLista: @js($todasUnidades),
            unidadeAtualId: {{ $unidadeAtualId ? (int)$unidadeAtualId : 'null' }},
            unidadeAtualNome: @js($unidadeAtualNome),
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
                            class="min-h-[44px] px-4 rounded-lg bg-blue-900 text-white font-medium hover:bg-blue-800 disabled:opacity-50 inline-flex items-center justify-center">
                        CARREGAR PELO CADPEN
                    </button>

                    <button @click="identificarPorBiometria()" type="button"
                            class="min-h-[44px] px-4 rounded-lg bg-gray-800 text-white font-medium hover:bg-gray-700 inline-flex items-center justify-center">
                        IDENTIFICAR PELA BIOMETRIA
                    </button>
                </div>

                <div class="text-gray-600">
                    <div class="text-base md:text-xl">DATA/HORA:</div>
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
            <form method="POST" action="{{ route('ciclo.admissoes.store') }}" class="bg-white rounded-xl shadow p-4 md:p-6"
                  @submit="prepareSubmit">
                @csrf

                {{-- Tipo de Vínculo (BOTÕES) --}}
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

                {{-- UNIDADE (abaixo dos botões) --}}
                <div class="mt-4">
                    <label class="block text-sm font-medium">
                        UNIDADE (contexto; para nova Admissão é obrigatório)
                        <template x-if="admAtiva">
                            <span class="ml-2 text-xs text-amber-700">(indivíduo já admitido — unidade bloqueada)</span>
                        </template>
                    </label>

                    <select name="unidade_id"
                            x-model="unidadeId"
                            :disabled="tipo!=='ADMISSAO' || admAtiva"
                            :class="['mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase',
                                     (tipo!=='ADMISSAO' || admAtiva) ? 'bg-gray-100 text-gray-700 cursor-not-allowed' : '']"
                            :required="tipo==='ADMISSAO'">
                        <option value="">— SELECIONE —</option>
                        <template x-for="u in unidades" :key="u.id">
                            <option :value="u.id" x-text="u.nome"></option>
                        </template>
                    </select>

                    <template x-if="tipo==='ADMISSAO' && admAtiva">
                        <p class="text-xs text-red-700 mt-1">
                            Já existe admissão ativa. Para alocar em outra unidade, use <strong>Transferência</strong> ou <strong>Trânsito</strong>.
                        </p>
                    </template>
                </div>

                <hr class="my-6">

                {{-- BLOCO ADMISSÃO --}}
                <div x-show="tipo==='ADMISSAO'">
                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium">ORIGEM (ÓRGÃO)</label>
                            <select name="origem"
                                    class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                                <option value="">— SELECIONE —</option>
                                <option>PC</option><option>PM</option><option>PF</option><option>PRF</option>
                                <option>UNIDADE PRISIONAL</option><option>OUTROS</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">COMPLEMENTO DA ORIGEM</label>
                            <input name="origem_complemento" type="text"
                                   class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                   placeholder="Ex.: 12ª DP DE URUAÇU">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">UF DE ORIGEM</label>
                            <select name="uf_origem"
                                    class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                                <option value="">—</option>
                                @php $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']; @endphp
                                @foreach ($ufs as $uf) <option value="{{ $uf }}">{{ $uf }}</option> @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- ENQUADRAMENTO --}}
                    <div class="mt-6" x-data="enquadramentoLeiArtigos()">
                        <h4 class="font-semibold mb-3">Enquadramento</h4>
                        <div class="grid md:grid-cols-6 gap-4 bg-gray-50 rounded-xl p-4 border">
                            <div>
                                <label class="block text-sm font-medium">LEI Nº *</label>
                                <input x-model.trim="lei.numero" x-on:input="lei.numero = toDigits(lei.numero)"
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase" placeholder="Ex.: 123">
                            </div>
                            <div>
                                <label class="block text-sm font-medium">ANO *</label>
                                <input x-model.trim="lei.ano" x-on:input="lei.ano = toDigits(lei.ano)" maxlength="4"
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="Ex.: 2018">
                            </div>
                            <div class="md:col-span-4 flex items-end gap-2">
                                <span class="text-sm text-gray-600">Adicione artigos e confirme cada um.</span>
                            </div>
                        </div>

                        <div class="mt-4 bg-white rounded-xl border p-4">
                            <h5 class="font-semibold mb-3">Artigo (Edição)</h5>
                            <div class="grid md:grid-cols-6 gap-4">
                                <div>
                                    <label class="block text-sm font-medium">ARTIGO *</label>
                                    <input x-model.trim="artigo.artigo" x-on:input="artigo.artigo = normalizeArtigo(artigo.artigo)"
                                           class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                           placeholder="1, 2, 10, 45">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">INCISO(S)</label>
                                    <input x-model.trim="artigo.incisos" x-on:input="artigo.incisos = normalizeIncisos(artigo.incisos)"
                                           class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase" placeholder="I, II, IV">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">ALÍNEA(S)</label>
                                    <input x-model.trim="artigo.alineas" x-on:input="artigo.alineas = normalizeAlineas(artigo.alineas)"
                                           class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase" placeholder="a, b, c">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">PARÁGRAFO(S)</label>
                                    <input x-model.trim="artigo.paragrafos" x-on:input="artigo.paragrafos = normalizeParagrafos(artigo.paragrafos)"
                                           class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase" placeholder="único, 2, 3">
                                </div>
                                <div class="flex items-end gap-2">
                                    <button type="button" class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-700"
                                            @click="confirmarArtigo()">CONFIRMAR ARTIGO</button>
                                    <button type="button" class="px-4 py-2 rounded-lg border hover:bg-gray-50"
                                            @click="novoArtigo()">NOVO ARTIGO</button>
                                </div>
                            </div>

                            <template x-if="lei.artigos.length">
                                <div class="mt-4">
                                    <div class="text-sm font-semibold mb-2">ARTIGOS CONFIRMADOS</div>
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

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="px-4 py-2 rounded-lg bg-blue-900 text-white hover:bg-blue-800"
                                    @click="inserirLei()">INSERIR</button>
                            <button type="button" class="px-4 py-2 rounded-lg border border-blue-900 text-blue-900 hover:bg-blue-50"
                                    @click="inserirLeiCombinado()">COMBINADO COM</button>
                        </div>

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

                        <input type="hidden" name="enquadramentos_json" :value="JSON.stringify(leis)">
                    </div>
                </div>

                {{-- BLOCO TRANSFERÊNCIA --}}
                <div x-show="tipo==='TRANSFERENCIA'">
                    <div class="rounded-lg border p-3 mb-3 bg-amber-50 text-amber-900">
                        Transferências só podem ser lançadas para indivíduos com <strong>Admissão ATIVA</strong>.
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium">UNIDADE DE ORIGEM (detectada)</label>
                            <input type="text" x-model="origemNome" class="mt-1 w-full rounded-lg border-gray-300 bg-gray-100" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">UNIDADE DE DESTINO</label>
                            <select name="destino_unidade_id" x-model="destinoUnidadeId"
                                    class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                                <option value="">— SELECIONE —</option>
                                <template x-for="u in unidades" :key="'t-'+u.id">
                                    <option :value="u.id" x-text="u.nome"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">MOTIVO DA TRANSFERÊNCIA</label>
                            <input name="transferencia_motivo" type="text" maxlength="120"
                                   class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                   placeholder="Motivo resumido">
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button name="acao" value="transferencia_iniciar"
                                class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-700">
                            INICIAR TRANSFERÊNCIA
                        </button>

                        <div class="flex items-end gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">ID da Unidade de Origem</label>
                                <input type="number" name="origem_id" class="mt-1 w-40 rounded-lg border-gray-300" placeholder="Ex.: 12">
                            </div>
                            <button name="acao" value="transferencia_concluir"
                                    class="px-4 py-2 rounded-lg border hover:bg-gray-50">
                                CONCLUIR TRANSFERÊNCIA (DESTINO)
                            </button>
                        </div>
                    </div>
                </div>

                {{-- BLOCO TRÂNSITO --}}
                <div x-show="tipo==='TRANSITO'">
                    <div class="rounded-lg border p-3 mb-3 bg-amber-50 text-amber-900">
                        Trânsitos só podem ser lançados para indivíduos com <strong>Admissão ATIVA</strong>.
                    </div>

                    <div class="grid md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium">UNIDADE DE ORIGEM (detectada)</label>
                            <input type="text" x-model="origemNome" class="mt-1 w-full rounded-lg border-gray-300 bg-gray-100" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">UNIDADE DE DESTINO</label>
                            <select name="destino_unidade_id" x-model="destinoUnidadeId"
                                    class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                                <option value="">— SELECIONE —</option>
                                <template x-for="u in unidades" :key="'tr-'+u.id">
                                    <option :value="u.id" x-text="u.nome"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">MOTIVO DO TRÂNSITO</label>
                            <input name="transito_motivo" type="text" maxlength="120"
                                   class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                   placeholder="Motivo resumido">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">PREVISÃO DE RETORNO (DATA)</label>
                            <input type="date" name="prev_retorno_data" class="mt-1 rounded-lg border-gray-300">
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <button name="acao" value="transito_iniciar"
                                    class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-700">
                                INICIAR TRÂNSITO
                            </button>

                            <div class="flex items-end gap-2">
                                <div>
                                    <label class="block text-xs text-gray-600">ID da Unidade de Origem</label>
                                    <input type="number" name="origem_id" class="mt-1 w-40 rounded-lg border-gray-300" placeholder="Ex.: 12">
                                </div>
                                <button name="acao" value="transito_aceitar"
                                        class="px-4 py-2 rounded-lg border hover:bg-gray-50">
                                    ACEITAR CHEGADA (DESTINO)
                                </button>
                            </div>

                            <div class="flex items-end gap-2">
                                <div>
                                    <label class="block text-xs text-gray-600">ID da Unidade de Origem</label>
                                    <input type="number" name="origem_id" class="mt-1 w-40 rounded-lg border-gray-300" placeholder="Ex.: 12">
                                </div>
                                <button name="acao" value="transito_iniciar_retorno"
                                        class="px-4 py-2 rounded-lg border hover:bg-gray-50">
                                    INICIAR RETORNO (DESTINO)
                                </button>
                            </div>

                            <div class="flex items-end gap-2">
                                <div>
                                    <label class="block text-xs text-gray-600">ID da Unidade de Destino</label>
                                    <input type="number" name="destino_id" class="mt-1 w-40 rounded-lg border-gray-300" placeholder="Ex.: 34">
                                </div>
                                <button name="acao" value="transito_concluir"
                                        class="px-4 py-2 rounded-lg bg-blue-900 text-white hover:bg-blue-800">
                                    CONCLUIR TRÂNSITO (ORIGEM)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- DESLIGAMENTO --}}
                <div class="mt-8 border-t pt-6" x-show="tipo==='DESLIGAMENTO'">
                    <h3 class="text-lg font-semibold mb-4">Desligamento</h3>

                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium">TIPO</label>
                            <select x-model="desligamento.tipo" name="desligamento_tipo"
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
                            <select x-model="destinoUnidadeId" name="desligamento_destino_unidade_id"
                                    class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase">
                                <option value="">— SELECIONE —</option>
                                <template x-for="u in unidades" :key="'d2-'+u.id">
                                    <option :value="u.id" x-text="u.nome"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">OBSERVAÇÃO</label>
                            <input name="desligamento_observacao" type="text"
                                   class="mt-1 w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 uppercase"
                                   placeholder="OPCIONAL">
                        </div>
                    </div>
                </div>

                {{-- AÇÕES GERAIS --}}
                <div class="mt-8 flex items-center gap-3">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-900 text-white font-semibold hover:bg-blue-800">
                        REGISTRAR
                    </button>
                    <a href="{{ route('ciclo.admissoes.index') }}"
                       class="px-4 py-2 rounded-xl border border-gray-300 hover:bg-gray-50">CANCELAR</a>
                </div>
            </form>
        </section>

        {{-- HISTÓRICO DO CADPEN --}}
        <section class="mt-10" x-show="individuo">
            <h3 class="text-lg font-semibold mb-3">Histórico de Movimentações</h3>

            <div class="rounded-xl border bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs uppercase tracking-wider text-gray-600">
                                <th class="px-4 py-2">#</th>
                                <th class="px-4 py-2">TIPO</th>
                                <th class="px-4 py-2">UNIDADE</th>
                                <th class="px-4 py-2">DESTINO</th>
                                <th class="px-4 py-2">INÍCIO</th>
                                <th class="px-4 py-2">CONCLUSÃO</th>
                                <th class="px-4 py-2">STATUS</th>
                                <th class="px-4 py-2">OBS/MOTIVO</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-sm" x-show="historico.length">
                            <template x-for="(r, i) in historico" :key="r.id">
                                <tr>
                                    <td class="px-4 py-2" x-text="r.id"></td>
                                    <td class="px-4 py-2" x-text="r.tipo"></td>
                                    <td class="px-4 py-2" x-text="r.unidade_nome || '—'"></td>
                                    <td class="px-4 py-2" x-text="r.destino_unidade_nome || '—'"></td>
                                    <td class="px-4 py-2" x-text="r.inicio_br || '—'"></td>
                                    <td class="px-4 py-2" x-text="r.fim_br || '—'"></td>
                                    <td class="px-4 py-2" x-text="r.status"></td>
                                    <td class="px-4 py-2" x-text="r.obs || '—'"></td>
                                </tr>
                            </template>
                        </tbody>
                        <tbody x-show="!historico.length">
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                    Sem movimentações para este CADPEN.
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

                // Unidades / contexto
                unidades: Array.isArray(cfg?.unidadesLista) ? cfg.unidadesLista : [],
                unidadeId: cfg?.unidadeAtualId ?? null, // default ao logado para nova admissão
                destinoUnidadeId: null,
                origemNome: '—',

                // Estado de admissão ativa do indivíduo
                admAtiva: false,
                admUnidadeId: null,
                admUnidadeNome: null,

                desligamento: { tipo: '', observacao: '' },

                historico: [],

                btnTipo(t) {
                    return [
                        'px-3','py-2','rounded-lg','border','text-sm',
                        this.tipo===t ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-gray-800 border-gray-300 hover:bg-gray-50'
                    ].join(' ');
                },

                async carregarPorCadpen() {
                    if (!this.cadpen) return;
                    this.loading = true; this.notFound = false; this.individuo = null; this.historico = [];
                    this.admAtiva = false; this.admUnidadeId = null; this.admUnidadeNome = null;
                    this.unidadeId = cfg?.unidadeAtualId ?? null; // reset para padrão do usuário
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
                            await this.carregarHistorico();
                            // Se existir admissão ativa, trava o dropdown e seta a unidade do vínculo
                            if (this.admAtiva && this.admUnidadeId) {
                                this.unidadeId = this.admUnidadeId;
                            }
                        }
                    } catch (e) {
                        console.error(e);
                        this.notFound = true;
                    } finally {
                        this.loading = false;
                    }
                },

                async carregarHistorico() {
                    if (!this.individuo?.cadpen) return;
                    try {
                        const r = await fetch(`/ciclo/ajax/historico/${encodeURIComponent(this.individuo.cadpen)}`);
                        if (!r.ok) throw new Error('Falha ao carregar histórico');
                        const arr = await r.json();
                        this.historico = Array.isArray(arr) ? arr : [];

                        const atv = this.historico.find(x => x.tipo==='ADMISSAO' && x.status==='ATIVO');
                        this.admAtiva = !!atv;
                        this.admUnidadeId = atv?.unidade_id ?? null;
                        this.admUnidadeNome = atv?.unidade_nome ?? null;

                        this.origemNome = atv?.unidade_nome || cfg?.unidadeAtualNome || '—';
                    } catch (e) {
                        console.error(e);
                        this.historico = [];
                        this.admAtiva = false;
                        this.admUnidadeId = null;
                        this.admUnidadeNome = null;
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

                prepareSubmit(e) {
                    // Regra: não pode ADMISSÃO se já há admissão ativa
                    if (this.tipo === 'ADMISSAO' && this.admAtiva) {
                        alert('Já existe admissão ativa para este indivíduo. Use TRANSFERÊNCIA ou TRÂNSITO.');
                        e.preventDefault();
                        return;
                    }

                    if (this.tipo === 'ADMISSAO' && !this.unidadeId) {
                        alert('Selecione a UNIDADE para a admissão.');
                        e.preventDefault();
                        return;
                    }

                    if (!this.individuo?.cadpen) {
                        alert('Carregue um CADPEN antes de registrar.');
                        e.preventDefault();
                        return;
                    }

                    if ((this.tipo==='TRANSFERENCIA' || this.tipo==='TRANSITO') && !this.destinoUnidadeId) {
                        alert('Selecione a UNIDADE DE DESTINO.');
                        e.preventDefault();
                        return;
                    }
                },
            }
        }

        // ======= Enquadramento (apenas para Admissão) =======
        function enquadramentoLeiArtigos() {
            return {
                leis: [],
                lei: { numero: '', ano: '', artigos: [], combinadoCom: false },
                artigo: { artigo:'', incisos:'', alineas:'', paragrafos:'' },

                toDigits(v){ return (v||'').replace(/[^0-9]/g,''); },
                normalizeArtigo(v){ return (v||'').toUpperCase().replace(/[^0-9º]/g,''); },
                normalizeIncisos(v){ v=(v||'').toUpperCase().replace(/[^IVX, E]/g,''); return v.replace(/\s+/g,' ').trim(); },
                normalizeAlineas(v){ v=(v||'').toUpperCase().replace(/[^A-Z0-9, E]/g,''); return v.replace(/\s+/g,' ').trim(); },
                normalizeParagrafos(v){ v=(v||'').toUpperCase().replace(/[^0-9ÚNICO, E]/g,''); return v.replace(/\s+/g,' ').trim(); },

                confirmarArtigo(){
                    if(!this.lei.numero || !this.lei.ano){ alert('Preencha LEI e ANO.'); return; }
                    if(!this.artigo.artigo){ alert('Informe o ARTIGO.'); return; }
                    const obj = {
                        artigo: this.artigo.artigo,
                        incisos: this.splitMulti(this.artigo.incisos, 'romano'),
                        alineas: this.splitMulti(this.artigo.alineas, 'alinea'),
                        paragrafos: this.splitMulti(this.artigo.paragrafos, 'paragrafo'),
                    };
                    this.lei.artigos.push(obj);
                    this.novoArtigo();
                },
                novoArtigo(){ this.artigo = { artigo:'', incisos:'', alineas:'', paragrafos:'' }; },
                remArtigo(i){ this.lei.artigos.splice(i,1); },
                inserirLei(){
                    if(!this.lei.numero || !this.lei.ano || this.lei.artigos.length===0){
                        alert('LEI/ANO e ao menos 1 ARTIGO são obrigatórios.'); return;
                    }
                    const push = JSON.parse(JSON.stringify(this.lei));
                    push.numero = this.toDigits(push.numero); push.ano = this.toDigits(push.ano); push.combinadoCom=false;
                    this.leis.push(push); this.resetLei();
                },
                inserirLeiCombinado(){
                    if(!this.lei.numero || !this.lei.ano || this.lei.artigos.length===0){
                        alert('LEI/ANO e ao menos 1 ARTIGO são obrigatórios.'); return;
                    }
                    const push = JSON.parse(JSON.stringify(this.lei));
                    push.numero = this.toDigits(push.numero); push.ano = this.toDigits(push.ano); push.combinadoCom=true;
                    this.leis.push(push); this.resetLei();
                },
                resetLei(){ this.lei = { numero:'', ano:'', artigos:[], combinadoCom:false }; this.novoArtigo(); },
                splitMulti(s,tipo){
                    if(!s) return [];
                    s = s.replace(/\s+E\s+/gi, ',');
                    return s.split(',').map(x=>x.trim()).filter(Boolean).map(x=>{
                        if(tipo==='romano') return x.replace(/[^IVX]/gi,'').toUpperCase();
                        if(tipo==='alinea') return x.toUpperCase();
                        if(tipo==='paragrafo'){ x=x.toUpperCase(); return x==='ÚNICO'?'ÚNICO':x.replace(/[^0-9]/g,''); }
                        return x;
                    });
                },
                artLabel(n){ const m=String(n||'').toUpperCase().match(/^(\d+)(?:º)?$/); if(m){const v=+m[1]; return (v>=1&&v<=9)?`${v}º`:`${v}`;} return String(n||''); },
                incisosLabel(a){ return a?.length ? 'INCISO ' + a.join(', ') : ''; },
                alineasLabel(a){ return a?.length ? 'ALÍNEA ' + a.join(', ') : ''; },
                paragrafosLabel(a){ if(!a?.length) return ''; const itens=a.map(p=>p==='ÚNICO'?'§ ÚNICO':`§ ${parseInt(p,10)}º`); return itens.join(', '); },
                fmtArtigoLinha(a){ const partes=[]; if(a.incisos?.length)partes.push(this.incisosLabel(a.incisos)); if(a.alineas?.length)partes.push(this.alineasLabel(a.alineas)); if(a.paragrafos?.length)partes.push(this.paragrafosLabel(a.paragrafos)); return partes.length?`ART. ${this.artLabel(a.artigo)} (${partes.join('; ')})`:`ART. ${this.artLabel(a.artigo)}`; },
                formatLeiCabecalho(L){ const head=`LEI ${L.numero}/${L.ano}`; return L.combinadoCom?`${head} — COMBINADO COM`:head; },
                formatLeiDetalhe(L){ return (L.artigos||[]).map(a=>this.fmtArtigoLinha(a)).join(' | '); },
            };
        }
    </script>
</x-app-layout>
