{{-- resources/views/frota/veiculosdeslocamento.blade.php --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">FROTA — DESLOCAMENTOS</h2></x-slot>

    @php
        // Endpoint AJAX que já existe no sistema para localizar indivíduos
        $URL_FIND_IND = route('gestao.visitas.ajax.find_individuo');
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto bg-white dark:bg-gray-900 p-6 rounded-xl shadow">

            {{-- FLASH MESSAGES (embutido) --}}
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-green-700 bg-green-700 text-white px-4 py-3" data-flash>
                    <div class="font-semibold">Sucesso</div>
                    <div class="text-sm">{{ session('success') }}</div>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-700 bg-red-700 text-white px-4 py-3" data-flash>
                    <div class="font-semibold">Erro</div>
                    <div class="text-sm">{{ session('error') }}</div>
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-yellow-600 bg-yellow-50 text-yellow-900 px-4 py-3" data-flash>
                    <div class="font-semibold">Há {{ $errors->count() }} erro(s) no formulário:</div>
                    <ul class="list-disc pl-5 mt-2 text-sm">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('frota.veiculos.deslocamentos.store') }}" class="uppercase" id="frm-desloc">
                @csrf

                {{-- DADOS GERAIS --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium">PLACA *</label>
                        <input name="placa" value="{{ old('placa') }}" class="mt-1 w-full rounded border p-2 uppercase" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">SAÍDA *</label>
                        <input type="datetime-local" name="saida_em" value="{{ old('saida_em') }}" class="mt-1 w-full rounded border p-2 uppercase" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">RETORNO</label>
                        <input type="datetime-local" name="retorno_em" value="{{ old('retorno_em') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">ORIGEM</label>
                        <input name="origem" value="{{ old('origem') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">DESTINO</label>
                        <input name="destino" value="{{ old('destino') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">FINALIDADE *</label>
                        @php $fin = old('finalidade',''); @endphp
                        <select name="finalidade" id="finalidade" class="mt-1 w-full rounded border p-2 uppercase" required>
                            <option value="">-SELECIONE-</option>
                            @foreach (['ESCOLTA_HOSPITALAR','ESCOLTA_JUDICIAL','TRANSFERENCIA','ESCOLTA_OUTROS','ADMINISTRATIVO','SERVICO','MANUTENCAO','OUTROS'] as $opt)
                                <option value="{{ $opt }}" @selected($fin===$opt)>{{ str_replace('_',' ', $opt) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- BLOCO MANUTENÇÃO (condicional) --}}
                <div id="box-manut" class="mt-4 rounded-lg border p-4" style="display:none">
                    <h4 class="font-semibold mb-3">DADOS DE MANUTENÇÃO</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium">TIPO *</label>
                            @php $mt = old('manutencao_tipo',''); @endphp
                            <select name="manutencao_tipo" id="manutencao_tipo" class="mt-1 w-full rounded border p-2 uppercase">
                                <option value="">-SELECIONE-</option>
                                @foreach (['PREVENTIVA','CORRETIVA','PROGRAMACAO'] as $opt)
                                    <option value="{{ $opt }}" @selected($mt===$opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">DATA</label>
                            <input type="date" name="manutencao_data" id="manutencao_data" value="{{ old('manutencao_data') }}" class="mt-1 w-full rounded border p-2 uppercase">
                        </div>
                        <div class="md:col-span-1"></div>
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium">DESCRIÇÃO / OS</label>
                            <textarea name="manutencao_descricao" id="manutencao_descricao" rows="3" class="mt-1 w-full rounded border p-2 uppercase">{{ old('manutencao_descricao') }}</textarea>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">EM MANUTENÇÃO, O MOTORISTA PODE SER PRESTADOR DE SERVIÇO.</p>
                </div>

                {{-- EQUIPE --}}
                <div class="mt-6">
                    <h3 class="font-semibold mb-2">EQUIPE</h3>

                    {{-- MOTORISTA --}}
                    <div class="rounded-lg border p-4">
                        <h4 class="font-semibold mb-3">MOTORISTA</h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium">TIPO DE MOTORISTA *</label>
                                @php $mtipo = old('motorista_tipo',''); @endphp
                                <select name="motorista_tipo" id="motorista_tipo" class="mt-1 w-full rounded border p-2 uppercase" required>
                                    <option value="">-SELECIONE-</option>
                                    <option value="SERVIDOR" @selected($mtipo==='SERVIDOR')>SERVIDOR</option>
                                    <option value="PRESTADOR" @selected($mtipo==='PRESTADOR')>PRESTADOR DE SERVIÇO</option>
                                </select>
                            </div>

                            {{-- SERVIDOR --}}
                            <div class="md:col-span-2" id="motorista_servidor_box" style="display:none">
                                <label class="block text-sm font-medium">MATRÍCULA/CPF/NOME (SERVIDOR) *</label>
                                <div class="flex gap-2">
                                    <input id="motorista_servidor_query" name="motorista_servidor_query"
                                           value="{{ old('motorista_servidor_query') }}"
                                           class="mt-1 w-full rounded border p-2 uppercase" placeholder="DIGITE PARA BUSCAR" />
                                    <button type="button" class="px-3 py-2 rounded border" disabled>BUSCAR</button>
                                </div>
                                <input type="hidden" name="motorista_servidor_id" id="motorista_servidor_id" value="{{ old('motorista_servidor_id') }}">

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                    <div>
                                        <label class="block text-sm font-medium">CNH Nº *</label>
                                        <input name="motorista_servidor_cnh" id="motorista_servidor_cnh" value="{{ old('motorista_servidor_cnh') }}" class="mt-1 w-full rounded border p-2 uppercase" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">CNH CATEGORIA</label>
                                        @php $cat = old('motorista_servidor_cnh_cat',''); @endphp
                                        <select name="motorista_servidor_cnh_cat" id="motorista_servidor_cnh_cat" class="mt-1 w-full rounded border p-2 uppercase">
                                            <option value="">-SELECIONE-</option>
                                            @foreach (['A','B','C','D','E'] as $c)
                                                <option value="{{ $c }}" @selected($cat===$c)>{{ $c }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">VALIDADE</label>
                                        <input type="date" name="motorista_servidor_cnh_val" id="motorista_servidor_cnh_val" value="{{ old('motorista_servidor_cnh_val') }}" class="mt-1 w-full rounded border p-2 uppercase">
                                    </div>
                                </div>
                            </div>

                            {{-- PRESTADOR --}}
                            <div class="md:col-span-2" id="motorista_prestador_box" style="display:none">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium">NOME *</label>
                                        <input name="motorista_prestador_nome" id="motorista_prestador_nome" value="{{ old('motorista_prestador_nome') }}" class="mt-1 w-full rounded border p-2 uppercase">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">CPF/CNPJ *</label>
                                        <input name="motorista_prestador_doc" id="motorista_prestador_doc" value="{{ old('motorista_prestador_doc') }}" class="mt-1 w-full rounded border p-2 uppercase">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">EMPRESA/ÓRGÃO *</label>
                                        <input name="motorista_prestador_emp" id="motorista_prestador_emp" value="{{ old('motorista_prestador_emp') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="NOME DA EMPRESA/ÓRGÃO">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                    <div>
                                        <label class="block text-sm font-medium">CNH Nº *</label>
                                        <input name="motorista_prestador_cnh" id="motorista_prestador_cnh" value="{{ old('motorista_prestador_cnh') }}" class="mt-1 w-full rounded border p-2 uppercase">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">CNH CATEGORIA</label>
                                        @php $pcat = old('motorista_prestador_cnh_cat',''); @endphp
                                        <select name="motorista_prestador_cnh_cat" id="motorista_prestador_cnh_cat" class="mt-1 w-full rounded border p-2 uppercase">
                                            <option value="">-SELECIONE-</option>
                                            @foreach (['A','B','C','D','E'] as $c)
                                                <option value="{{ $c }}" @selected($pcat===$c)>{{ $c }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">VALIDADE</label>
                                        <input type="date" name="motorista_prestador_cnh_val" id="motorista_prestador_cnh_val" value="{{ old('motorista_prestador_cnh_val') }}" class="mt-1 w-full rounded border p-2 uppercase">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">MOTORISTA PREFERENCIALMENTE DEVE SER SERVIDOR.</p>
                    </div>

                    {{-- PASSAGEIROS --}}
                    <div class="rounded-lg border p-4 mt-4">
                        <h4 class="font-semibold mb-3">PASSAGEIROS</h4>

                        <div class="grid grid-cols-1 md:grid-cols-7 gap-4 items-end">
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium">NOME</label>
                                <input id="psg_nome" class="mt-1 w-full rounded border p-2 uppercase" placeholder="NOME COMPLETO (OPCIONAL SE JÁ CADASTRADO)">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium">MATRÍCULA/CPF/CADPEN</label>
                                <input id="psg_doc" class="mt-1 w-full rounded border p-2 uppercase" placeholder="RECOMENDADO">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">&nbsp;</label>
                                <button type="button" id="btn_psg_buscar" class="px-4 py-2 rounded border w-full">BUSCAR</button>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">&nbsp;</label>
                                <button type="button" id="btn_psg_add" class="px-4 py-2 rounded bg-blue-900 text-white w-full">INSERIR</button>
                            </div>
                            <div class="md:col-span-7">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" id="psg_nao_cad" class="rounded border">
                                    <span>NÃO CADASTRADO — EXIGIR MATRÍCULA/CPF</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2 pr-3">#</th>
                                    <th class="text-left py-2 pr-3">NOME</th>
                                    <th class="text-left py-2 pr-3">MATRÍCULA/CPF/CADPEN</th>
                                    <th class="text-left py-2 pr-3">NÃO CAD.</th>
                                    <th class="text-left py-2 pr-3">AÇÕES</th>
                                </tr>
                                </thead>
                                <tbody id="psg_tbody"></tbody>
                            </table>
                        </div>

                        <input type="hidden" name="passageiros_json" id="passageiros_json" value='{"passageiros":[]}' />
                        <p class="text-xs text-gray-500 mt-2">SUPORTA GRANDES QUANTIDADES (ÔNIBUS ETC.).</p>
                    </div>

                    {{-- ESCOLTADOS (CadPen) - condicional por finalidade --}}
                    <div class="rounded-lg border p-4 mt-4" id="box-escolta" style="display:none">
                        <h4 class="font-semibold mb-3">ESCOLTADOS (CADPEN)</h4>

                        {{-- ESCOLTA OUTROS TIPOS - DESCRIÇÃO --}}
                        <div id="box-escolta-outros" class="mb-4" style="display:none">
                            <label class="block text-sm font-medium">DESCREVA O TIPO DE ESCOLTA *</label>
                            <input type="text" name="escolta_outros_tipo" id="escolta_outros_tipo" value="{{ old('escolta_outros_tipo') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="Ex.: VELÓRIO, BANCO, ATENDIMENTO MÉDICO PARTICULAR, ETC.">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-7 gap-4 items-end">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium">CADPEN / ID *</label>
                                <input id="esc_id" class="mt-1 w-full rounded border p-2 uppercase" placeholder="OBRIGATÓRIO PARA INSERIR">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium">NOME</label>
                                <input id="esc_nome" class="mt-1 w-full rounded border p-2 uppercase" placeholder="PREENCHIDO APÓS BUSCA OU DIGITE">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">&nbsp;</label>
                                <button type="button" id="btn_esc_buscar" class="px-4 py-2 rounded border w-full">BUSCAR</button>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium">&nbsp;</label>
                                <button type="button" id="btn_esc_add" class="px-4 py-2 rounded bg-blue-900 text-white w-full">INSERIR</button>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2 pr-3">#</th>
                                    <th class="text-left py-2 pr-3">CADPEN/ID</th>
                                    <th class="text-left py-2 pr-3">NOME</th>
                                    <th class="text-left py-2 pr-3">AÇÕES</th>
                                </tr>
                                </thead>
                                <tbody id="esc_tbody"></tbody>
                            </table>
                        </div>

                        <input type="hidden" name="escoltados_json" id="escoltados_json" value='{"escoltados":[]}' />
                        <p class="text-xs text-gray-500 mt-2">INSIRA TODOS OS CUSTODIADOS QUE ESTÃO SENDO ESCOLTADOS.</p>
                    </div>
                </div>

                {{-- OBSERVAÇÕES --}}
                <div class="mt-6">
                    <label class="block text-sm font-medium">OBSERVAÇÕES</label>
                    <textarea name="observacoes" rows="3" class="mt-1 w-full rounded border p-2 uppercase">{{ old('observacoes') }}</textarea>
                </div>

                <div class="mt-6">
                    <button class="px-4 py-2 rounded bg-blue-900 text-white">SALVAR</button>
                </div>

                {{-- HIDDEN JSON PASSAGEIROS (duplicado por compat) --}}
                <input type="hidden" name="passageiros_json" id="passageiros_json_bottom">
            </form>
        </div>
    </div>

    {{-- MODAL DE RESULTADOS (reutilizado para Passageiros e Escoltados) --}}
    <div id="modal-busca" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow max-w-3xl w-full">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <h3 class="font-semibold">RESULTADOS DA BUSCA</h3>
                    <button type="button" id="modal-close" class="px-3 py-1 rounded border">FECHAR</button>
                </div>
                <div class="p-4">
                    <div id="modal-msg" class="text-sm text-gray-600 mb-3"></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2 pr-3">CadPen</th>
                                    <th class="text-left py-2 pr-3">Nome</th>
                                    <th class="text-left py-2 pr-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="modal-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        "use strict";

        const URL_FIND_IND = @json($URL_FIND_IND);

        // -------- Finalidade / Manutenção / Motorista regras --------
        const finalidade = document.getElementById('finalidade');
        const boxManut   = document.getElementById('box-manut');
        const manutTipo  = document.getElementById('manutencao_tipo');

        const selTipo = document.getElementById('motorista_tipo');
        const boxServ = document.getElementById('motorista_servidor_box');
        const boxPrest= document.getElementById('motorista_prestador_box');

        const qServ   = document.getElementById('motorista_servidor_query');
        const cnhServ = document.getElementById('motorista_servidor_cnh');

        const pNome   = document.getElementById('motorista_prestador_nome');
        const pDoc    = document.getElementById('motorista_prestador_doc');
        const pEmp    = document.getElementById('motorista_prestador_emp');
        const cnhPrest= document.getElementById('motorista_prestador_cnh');

        const boxEscolta       = document.getElementById('box-escolta');
        const boxEscoltaOutros = document.getElementById('box-escolta-outros');
        const inEscoltaOutros  = document.getElementById('escolta_outros_tipo');

        // Agora ESCOLTA_OUTROS também exige escoltados
        const FINALIDADES_ESCOLTA = new Set(['ESCOLTA_HOSPITALAR','ESCOLTA_JUDICIAL','TRANSFERENCIA','ESCOLTA_OUTROS']);

        function setReq(el, on){ if(!el) return; on ? el.setAttribute('required','required') : el.removeAttribute('required'); }

        function syncFinalidadeUI(){
            const val   = finalidade.value;
            const isMan = (val === 'MANUTENCAO');
            const isEsc = FINALIDADES_ESCOLTA.has(val);
            const isEscOutros = (val === 'ESCOLTA_OUTROS');

            boxManut.style.display   = isMan ? '' : 'none';
            boxEscolta.style.display = isEsc ? '' : 'none';

            setReq(manutTipo, isMan);

            // ESCOLTA_OUTROS: campo descrição obrigatório
            boxEscoltaOutros.style.display = isEscOutros ? '' : 'none';
            setReq(inEscoltaOutros, isEscOutros);

            if (!isMan && selTipo.value === 'PRESTADOR'){
                selTipo.value = 'SERVIDOR';
                syncMotoristaUI();
                alert('MOTORISTA PRESTADOR SOMENTE EM MANUTENÇÃO. ALTERADO PARA SERVIDOR.');
            }
        }
        function syncMotoristaUI(){
            const v = selTipo.value;
            const isMan = (finalidade.value === 'MANUTENCAO');
            if (v === 'SERVIDOR') {
                boxServ.style.display  = '';
                boxPrest.style.display = 'none';
                setReq(qServ, true); setReq(cnhServ, true);
                setReq(pNome,false); setReq(pDoc,false); setReq(pEmp,false); setReq(cnhPrest,false);
            } else if (v === 'PRESTADOR') {
                if (!isMan){ selTipo.value='SERVIDOR'; syncMotoristaUI(); alert('MOTORISTA PRESTADOR SOMENTE EM MANUTENÇÃO.'); return; }
                boxServ.style.display  = 'none';
                boxPrest.style.display = '';
                setReq(qServ,false); setReq(cnhServ,false);
                setReq(pNome,true); setReq(pDoc,true); setReq(pEmp,true); setReq(cnhPrest,true);
            } else {
                boxServ.style.display='none'; boxPrest.style.display='none';
                setReq(qServ,false); setReq(cnhServ,false); setReq(pNome,false); setReq(pDoc,false); setReq(pEmp,false); setReq(cnhPrest,false);
            }
        }
        finalidade.addEventListener('change', syncFinalidadeUI);
        selTipo.addEventListener('change', syncMotoristaUI);
        // Pré-popula UI baseada no old()
        syncFinalidadeUI(); syncMotoristaUI();

        // -------- Modal de busca (reutilizável) --------
        const modal   = document.getElementById('modal-busca');
        const btnClose = document.getElementById('modal-close');
        const mdlMsg  = document.getElementById('modal-msg');
        const mdlBody = document.getElementById('modal-tbody');
        let pickCb = null;

        function openModal(rows, cb){
            pickCb = cb;
            mdlBody.innerHTML = '';
            if (!rows || !rows.length){
                mdlMsg.textContent = 'Nenhum resultado encontrado.';
            } else {
                mdlMsg.textContent = `${rows.length} resultado(s).`;
                rows.forEach(r => {
                    const cadpen = (r.cadpen || r.CadPen || r.CADPEN || r.id || '').toString().toUpperCase();
                    const nome   = (r.nome_completo || r.nome || r.NOME || '').toString().toUpperCase();
                    const tr = document.createElement('tr');
                    tr.className = 'border-b';
                    tr.innerHTML = `
                        <td class="py-2 pr-3">${cadpen}</td>
                        <td class="py-2 pr-3">${nome}</td>
                        <td class="py-2 pr-3"><button type="button" class="px-2 py-1 rounded border text-xs">SELECIONAR</button></td>
                    `;
                    tr.querySelector('button').addEventListener('click', () => { if(pickCb) pickCb({cadpen, nome}); closeModal(); });
                    mdlBody.appendChild(tr);
                });
            }
            modal.classList.remove('hidden');
        }
        function closeModal(){ modal.classList.add('hidden'); pickCb = null; }
        btnClose.addEventListener('click', closeModal);
        modal.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });

        // ---- Busca por nome e por CadPen, unindo resultados ----
        async function buscarIndividuos(term){
            if (!term) return [];
            const hdrs = {'X-Requested-With':'XMLHttpRequest'};

            const urls = [
                URL_FIND_IND + '?q=' + encodeURIComponent(term),
                URL_FIND_IND + '?cadpen=' + encodeURIComponent(term),
            ];

            const all = [];
            for (const u of urls) {
                try {
                    const res = await fetch(u, { headers: hdrs });
                    if (!res.ok) continue;
                    const data = await res.json().catch(() => null);

                    const rows = Array.isArray(data) ? data
                              : Array.isArray(data?.data) ? data.data
                              : Array.isArray(data?.results) ? data.results
                              : [];

                    if (rows.length) all.push(...rows);
                } catch {
                    // ignora erro e segue
                }
            }

            // Deduplicar por CadPen (ou id se não vier CadPen)
            const seen = new Set();
            const uniq = [];
            for (const r of all) {
                const cad = (r.cadpen || r.CadPen || r.CADPEN || '').toString().toUpperCase();
                const key = cad || ('ID:' + (r.id ?? '').toString());
                if (seen.has(key)) continue;
                seen.add(key);
                uniq.push(r);
            }
            return uniq;
        }

        // -------- Passageiros --------
        const tbBody = document.getElementById('psg_tbody');
        const hidTop = document.getElementById('passageiros_json');
        const hidBot = document.getElementById('passageiros_json_bottom');
        const btnAdd = document.getElementById('btn_psg_add');
        const btnFind= document.getElementById('btn_psg_buscar');
        const inNome = document.getElementById('psg_nome');
        const inDoc  = document.getElementById('psg_doc');
        const inNC   = document.getElementById('psg_nao_cad');

        let passageiros = []; // {seq, nome, doc, nao_cadastrado}

        function psgSyncHidden(){
            const v = JSON.stringify({ passageiros });
            hidTop.value = v; hidBot.value = v;
        }
        function psgRedraw(){
            tbBody.innerHTML = '';
            passageiros.forEach(p => {
                const tr = document.createElement('tr');
                tr.className = 'border-b';
                tr.innerHTML = `
                    <td class="py-2 pr-3">${p.seq}</td>
                    <td class="py-2 pr-3">${p.nome || ''}</td>
                    <td class="py-2 pr-3">${p.doc || ''}</td>
                    <td class="py-2 pr-3">${p.nao_cadastrado ? 'SIM' : 'NÃO'}</td>
                    <td class="py-2 pr-3"><button type="button" data-del="${p.seq}" class="px-2 py-1 rounded border text-xs">REMOVER</button></td>
                `;
                tbBody.appendChild(tr);
                tr.querySelector('[data-del]').addEventListener('click', () => {
                    passageiros = passageiros.filter(x => x.seq !== p.seq).map((x,i)=>({...x, seq:i+1}));
                    psgRedraw(); psgSyncHidden();
                });
            });
        }
        btnAdd.addEventListener('click', () => {
            const nome = (inNome.value || '').toUpperCase().trim();
            const doc  = (inDoc.value  || '').toUpperCase().trim();
            const nao  = !!inNC.checked;

            if (nao && !doc) { alert('INFORME MATRÍCULA/CPF/CADPEN PARA NÃO CADASTRADO.'); inDoc.focus(); return; }
            if (!nome && !doc) { alert('INFORME NOME OU MATRÍCULA/CPF/CADPEN.'); inNome.focus(); return; }
            if (doc && passageiros.some(p => p.doc === doc)) { alert('JÁ EXISTE PASSAGEIRO COM MESMA IDENTIFICAÇÃO.'); return; }

            passageiros.push({ seq: passageiros.length+1, nome, doc, nao_cadastrado: nao });
            inNome.value=''; inDoc.value=''; inNC.checked=false;
            psgRedraw(); psgSyncHidden(); inNome.focus();
        });

        btnFind.addEventListener('click', async () => {
            const term = (inDoc.value || inNome.value || '').trim();
            if (!term){ alert('DIGITE NOME OU CADPEN/MATRÍCULA/CPF PARA BUSCAR.'); return; }
            const rows = await buscarIndividuos(term);
            openModal(rows, ({cadpen, nome}) => {
                inDoc.value  = cadpen;
                inNome.value = nome;
            });
        });

        psgSyncHidden();

        // -------- Escoltados (CadPen) --------
        const escTBody = document.getElementById('esc_tbody');
        const escHid   = document.getElementById('escoltados_json');
        const escBtn   = document.getElementById('btn_esc_add');
        const escFind  = document.getElementById('btn_esc_buscar');
        const escId    = document.getElementById('esc_id');
        const escNome  = document.getElementById('esc_nome');

        let escoltados = []; // {seq, cadpen, nome}

        function escSyncHidden(){ escHid.value = JSON.stringify({ escoltados }); }
        function escRedraw(){
            escTBody.innerHTML = '';
            escoltados.forEach(e => {
                const tr = document.createElement('tr');
                tr.className = 'border-b';
                tr.innerHTML = `
                    <td class="py-2 pr-3">${e.seq}</td>
                    <td class="py-2 pr-3">${e.cadpen}</td>
                    <td class="py-2 pr-3">${e.nome || ''}</td>
                    <td class="py-2 pr-3"><button type="button" data-del="${e.seq}" class="px-2 py-1 rounded border text-xs">REMOVER</button></td>
                `;
                escTBody.appendChild(tr);
                tr.querySelector('[data-del]').addEventListener('click', () => {
                    escoltados = escoltados.filter(x => x.seq !== e.seq).map((x,i)=>({...x, seq:i+1}));
                    escRedraw(); escSyncHidden();
                });
            });
        }

        escBtn.addEventListener('click', () => {
            const id  = (escId.value   || '').toUpperCase().trim();
            const nom = (escNome.value || '').toUpperCase().trim();
            if (!id) { alert('INFORME O CADPEN/ID.'); escId.focus(); return; }
            if (escoltados.some(e => e.cadpen === id)) { alert('JÁ INSERIDO.'); return; }
            escoltados.push({ seq: escoltados.length+1, cadpen: id, nome: nom });
            escId.value=''; escNome.value='';
            escRedraw(); escSyncHidden(); escId.focus();
        });

        escFind.addEventListener('click', async () => {
            const term = (escId.value || escNome.value || '').trim();
            if (!term){ alert('DIGITE CADPEN OU NOME PARA BUSCAR.'); return; }
            const rows = await buscarIndividuos(term);
            openModal(rows, ({cadpen, nome}) => {
                escId.value   = cadpen;
                escNome.value = nome;
            });
        });

        escSyncHidden();

        // -------- Validações no submit --------
        document.getElementById('frm-desloc').addEventListener('submit', (e) => {
            const FINALIDADES_ESCOLTA = new Set(['ESCOLTA_HOSPITALAR','ESCOLTA_JUDICIAL','TRANSFERENCIA','ESCOLTA_OUTROS']);
            const val   = finalidade.value;
            const isMan = (val === 'MANUTENCAO');
            const isEsc = FINALIDADES_ESCOLTA.has(val);
            const isEscOutros = (val === 'ESCOLTA_OUTROS');

            if (!isMan && selTipo.value === 'PRESTADOR'){
                e.preventDefault();
                alert('MOTORISTA PRESTADOR SOMENTE EM MANUTENÇÃO.');
                return;
            }
            if (isEsc && escoltados.length === 0){
                e.preventDefault();
                alert('INSIRA AO MENOS UM ESCOLTADO PARA ESTA FINALIDADE.');
                return;
            }
            if (isEscOutros){
                const desc = (inEscoltaOutros.value || '').trim();
                if (!desc){
                    e.preventDefault();
                    alert('DESCREVA O TIPO DE ESCOLTA EM "OUTROS TIPOS".');
                    inEscoltaOutros.focus();
                    return;
                }
            }
        });
    })();
    </script>

    {{-- Mantém o aviso na tela (sem auto-ocultar). Opcional: rolar para o topo para o usuário ver a mensagem. --}}
@if (session('success') || session('error') || $errors->any())
    <script>
        // rola para o topo para destacar a mensagem (pode remover se não quiser)
        try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch {}
    </script>
@endif
</x-app-layout>

