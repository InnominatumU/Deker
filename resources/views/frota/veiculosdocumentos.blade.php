{{-- resources/views/frota/veiculosdocumentos.blade.php --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">FROTA — DOCUMENTOS</h2></x-slot>

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

        <form method="POST" action="{{ route('frota.veiculos.documentos.store') }}" enctype="multipart/form-data" class="uppercase" id="frm-docs">
            @csrf

            {{-- ====== DOCUMENTO DIGITALIZADO (ORIGINAL) ====== --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">Placa *</label>
                    <input name="placa" id="placa" value="{{ old('placa') }}" class="mt-1 w-full rounded border p-2 uppercase" required>
                </div>
                <div>
                    <label class="block text-sm font-medium">Tipo Documento *</label>
                    <input name="tipo" value="{{ old('tipo') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="CRLV, SEGURO, VISTORIA..." required>
                </div>
                <div>
                    <label class="block text-sm font-medium">Arquivo *</label>
                    <input type="file" name="arquivo" class="mt-1 w-full rounded border p-2 uppercase" required>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium">Observações</label>
                    <textarea name="observacoes" rows="3" class="mt-1 w-full rounded border p-2 uppercase">{{ old('observacoes') }}</textarea>
                </div>
            </div>

            {{-- ====== MULTA DE TRÂNSITO (OPCIONAL) ====== --}}
            <div class="mt-8 rounded-xl border p-4">
                <div class="flex items-center justify-between gap-4">
                    <h3 class="font-semibold text-lg">Registrar dados de MULTA (opcional)</h3>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" id="ck-multa" class="rounded border">
                        <span>Adicionar informações de multa</span>
                    </label>
                </div>

                <div id="box-multa" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4" style="display:none">
                    {{-- Identificação da multa --}}
                    <div>
                        <label class="block text-sm font-medium">Número da infração *</label>
                        <input name="multa_numero" id="multa_numero" value="{{ old('multa_numero') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Data/Hora da infração *</label>
                        <input type="datetime-local" name="multa_infracao_em" id="multa_infracao_em" value="{{ old('multa_infracao_em') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Órgão autuador</label>
                        <input name="multa_orgao" id="multa_orgao" value="{{ old('multa_orgao') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="DETRAN, PRF, SMTT...">
                    </div>

                    {{-- Local e enquadramento --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium">Local da infração</label>
                        <input name="multa_local" id="multa_local" value="{{ old('multa_local') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="Logradouro, KM, ponto de referência">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Município/UF</label>
                        <input name="multa_municipio_uf" id="multa_municipio_uf" value="{{ old('multa_municipio_uf') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="CIDADE/UF">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Enquadramento (código) *</label>
                        <input name="multa_enquadramento" id="multa_enquadramento" value="{{ old('multa_enquadramento') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="Ex.: 545-00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Gravidade</label>
                        @php $grav = old('multa_gravidade',''); @endphp
                        <select name="multa_gravidade" id="multa_gravidade" class="mt-1 w-full rounded border p-2 uppercase">
                            <option value="">-SELECIONE-</option>
                            <option @selected($grav==='LEVE')>LEVE</option>
                            <option @selected($grav==='MÉDIA')>MÉDIA</option>
                            <option @selected($grav==='GRAVE')>GRAVE</option>
                            <option @selected($grav==='GRAVÍSSIMA')>GRAVÍSSIMA</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Pontos na CNH</label>
                        <input type="number" min="0" max="40" name="multa_pontos" id="multa_pontos" value="{{ old('multa_pontos') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium">Descrição / Observações da infração</label>
                        <textarea name="multa_descricao" id="multa_descricao" rows="2" class="mt-1 w-full rounded border p-2 uppercase">{{ old('multa_descricao') }}</textarea>
                    </div>

                    {{-- Motorista --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium">Motorista identificado</label>
                        <input name="multa_motorista_nome" id="multa_motorista_nome" value="{{ old('multa_motorista_nome') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="NOME COMPLETO">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Documento (CPF/Matrícula)</label>
                        <input name="multa_motorista_doc" id="multa_motorista_doc" value="{{ old('multa_motorista_doc') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>

                    {{-- Valores e prazos --}}
                    <div>
                        <label class="block text-sm font-medium">Valor (R$)</label>
                        <input type="number" step="0.01" min="0" name="multa_valor" id="multa_valor" value="{{ old('multa_valor') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="0,00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Prazo p/ recurso (data)</label>
                        <input type="date" name="multa_prazo_recurso" id="multa_prazo_recurso" value="{{ old('multa_prazo_recurso') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>
                    <div class="flex items-end">
                        <div class="w-full">
                            <label class="block text-sm font-medium">Alerta de prazo</label>
                            <div id="badge_prazo" class="mt-1 text-xs px-2 py-2 rounded border text-gray-600">
                                Informe o prazo para exibir o alerta.
                            </div>
                        </div>
                    </div>

                    <div>
                        @php $sit = old('multa_situacao',''); @endphp
                        <label class="block text-sm font-medium">Situação</label>
                        <select name="multa_situacao" id="multa_situacao" class="mt-1 w-full rounded border p-2 uppercase">
                            <option value="">-SELECIONE-</option>
                            <option @selected($sit==='EM ABERTO')>EM ABERTO</option>
                            <option @selected($sit==='EM RECURSO')>EM RECURSO</option>
                            <option @selected($sit==='PAGO')>PAGO</option>
                            <option @selected($sit==='CANCELADO')>CANCELADO</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Data de vencimento (pagamento)</label>
                        <input type="date" name="multa_vencimento" id="multa_vencimento" value="{{ old('multa_vencimento') }}" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Placa autuada</label>
                        <input name="multa_placa_autuada" id="multa_placa_autuada" value="{{ old('multa_placa_autuada') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="Preenchido pela placa acima">
                    </div>

                    {{-- Anexos adicionais --}}
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium">Anexos adicionais (opcional)</label>
                        <input type="file" name="multa_anexos[]" multiple class="mt-1 w-full rounded border p-2 uppercase">
                        <p class="text-xs text-gray-500 mt-1 normal-case">Use para anexar cópia do auto, comprovantes, AR, defesa, etc.</p>
                    </div>

                    {{-- Hidden flag --}}
                    <input type="hidden" name="multa_flag" id="multa_flag" value="0" />
                </div>
            </div>

            <div class="mt-6">
                <button class="px-4 py-2 rounded bg-blue-900 text-white">Salvar</button>
            </div>
        </form>
        </div>
    </div>

    <script>
    (function(){
        "use strict";

        const ckMulta   = document.getElementById('ck-multa');
        const boxMulta  = document.getElementById('box-multa');
        const flagMulta = document.getElementById('multa_flag');

        const inPrazo   = document.getElementById('multa_prazo_recurso');
        const badgePrazo= document.getElementById('badge_prazo');

        const placa     = document.getElementById('placa');
        const placaAutu = document.getElementById('multa_placa_autuada');

        // espelha placa autuada por padrão
        function syncPlacaAutuada(){
            if (!placaAutu.value) placaAutu.value = (placa.value || '').toUpperCase();
        }
        placa.addEventListener('input', syncPlacaAutuada);
        syncPlacaAutuada();

        // toggle seção multa
        function toggleMulta(){
            const on = ckMulta.checked;
            boxMulta.style.display = on ? '' : 'none';
            flagMulta.value = on ? '1' : '0';

            // Requisitos mínimos quando marcado
            const reqIds = ['multa_numero','multa_infracao_em','multa_enquadramento'];
            reqIds.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                if (on) el.setAttribute('required','required'); else el.removeAttribute('required');
            });
        }
        ckMulta.addEventListener('change', toggleMulta);
        toggleMulta();

        // badge de prazo para recurso
        function pintaBadge(diff){
            badgePrazo.className = 'mt-1 text-xs px-2 py-2 rounded border';
            if (isNaN(diff)){
                badgePrazo.classList.add('text-gray-600','border-gray-300');
                badgePrazo.textContent = 'Informe o prazo para exibir o alerta.';
                return;
            }
            if (diff < 0){
                badgePrazo.classList.add('text-white','bg-red-700','border-red-700');
                badgePrazo.textContent = `Prazo encerrado há ${Math.abs(diff)} dia(s).`;
            } else if (diff <= 3){
                badgePrazo.classList.add('text-white','bg-red-600','border-red-600');
                badgePrazo.textContent = `Faltam ${diff} dia(s) — URGENTE!`;
            } else if (diff <= 10){
                badgePrazo.classList.add('text-white','bg-yellow-600','border-yellow-600');
                badgePrazo.textContent = `Faltam ${diff} dia(s). Atenção.`;
            } else {
                badgePrazo.classList.add('text-white','bg-green-700','border-green-700');
                badgePrazo.textContent = `Faltam ${diff} dia(s).`;
            }
        }
        function calcPrazo(){
            const v = inPrazo.value;
            if (!v){ pintaBadge(NaN); return; }
            try{
                const today = new Date(); today.setHours(0,0,0,0);
                const prazo = new Date(v + 'T00:00:00'); prazo.setHours(0,0,0,0);
                const ms = prazo.getTime() - today.getTime();
                const diff = Math.ceil(ms / 86400000);
                pintaBadge(diff);
            }catch{
                pintaBadge(NaN);
            }
        }
        inPrazo && inPrazo.addEventListener('change', calcPrazo);
        calcPrazo();

        // validação final no submit (se multa marcada)
        document.getElementById('frm-docs').addEventListener('submit', (e) => {
            if (ckMulta.checked){
                if (!placaAutu.value){
                    e.preventDefault();
                    alert('Informe a PLACA AUTUADA.');
                    placaAutu.focus();
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

