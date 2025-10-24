{{-- resources/views/frota/veiculosuso.blade.php --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">FROTA — USO / DIÁRIAS / CHECKLIST</h2></x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto bg-white dark:bg-gray-900 p-6 rounded-xl shadow">

            {{-- FLASH MESSAGES (padrão unificado, permanente) --}}
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-green-700 bg-green-700 text-white px-4 py-3">
                    <div class="font-semibold">Sucesso</div>
                    <div class="text-sm">{{ session('success') }}</div>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-700 bg-red-700 text-white px-4 py-3">
                    <div class="font-semibold">Erro</div>
                    <div class="text-sm">{{ session('error') }}</div>
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-yellow-600 bg-yellow-50 text-yellow-900 px-4 py-3">
                    <div class="font-semibold">Há {{ $errors->count() }} erro(s) no formulário:</div>
                    <ul class="list-disc pl-5 mt-2 text-sm">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('frota.operacoes.store') }}" class="uppercase" id="frm-uso">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium">PLACA *</label>
                        <input name="placa" value="{{ old('placa') }}" class="mt-1 w-full rounded border p-2 uppercase" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">DATA *</label>
                        <input type="datetime-local" name="data_uso" value="{{ old('data_uso') }}" class="mt-1 w-full rounded border p-2 uppercase" required>
                    </div>
                </div>

                {{-- RESPONSÁVEL PELA CHECKLIST --}}
                <div class="mt-4 rounded-lg border p-4">
                    <h4 class="font-semibold mb-3">RESPONSÁVEL PELA CHECKLIST</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium">TIPO *</label>
                            @php $rt = old('resp_tipo',''); @endphp
                            <select name="resp_tipo" id="resp_tipo" class="mt-1 w-full rounded border p-2 uppercase" required>
                                <option value="">-SELECIONE-</option>
                                <option value="SERVIDOR"   @selected($rt==='SERVIDOR')>SERVIDOR</option>
                                <option value="PRESTADOR"  @selected($rt==='PRESTADOR')>PRESTADOR DE SERVIÇO</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium">MATRÍCULA/CPF/NOME *</label>
                            <input name="resp_query" id="resp_query" value="{{ old('resp_query') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="DIGITE O IDENTIFICADOR" required>
                            <input type="hidden" name="resp_id" id="resp_id" value="{{ old('resp_id') }}">
                            <p class="text-xs text-gray-500 mt-1">DIGITE MATRÍCULA, CPF OU NOME DO SERVIDOR/PRESTADOR. (BUSCA SERÁ IMPLEMENTADA)</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium">CHECKLIST (LIVRE)</label>
                    <textarea name="checklist" rows="2" class="mt-1 w-full rounded border p-2 uppercase" placeholder="FARÓIS OK; CALIBRAGEM OK; ...">{{ old('checklist') }}</textarea>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" id="chk-calibrar" class="rounded border"><span>MODO CALIBRAR HOTSPOTS</span></label>
                    <button type="button" id="btn-exportar" class="px-3 py-1.5 rounded border text-sm">EXPORTAR COORDS</button>
                    <button type="button" id="btn-reset"    class="px-3 py-1.5 rounded border text-sm">RESETAR COORDS</button>
                </div>

                <div class="mt-3">
                    <div class="relative w-full">
                        <svg id="checklist-svg" viewBox="0 0 1200 675" class="w-full h-auto border rounded">
                            <defs>
                                <style>
                                    .hotspot{fill:#fff;stroke:#111;stroke-width:2;cursor:pointer}
                                    .hotspot:hover{fill:#f59e0b}
                                    .hotspot.drag{fill:#60a5fa}
                                    .pin{fill:#ef4444;stroke:#fff;stroke-width:2}
                                    .pin-text{fill:#fff;font:700 16px/1 sans-serif;text-anchor:middle;dominant-baseline:central}
                                </style>
                            </defs>
                            <image href="{{ asset('images/checklist.svg') }}" x="0" y="0" width="1200" height="675"/>
                            <g id="hotspots"></g>
                            <g id="pins"></g>
                        </svg>
                    </div>

                    <div class="mt-4 flex gap-2 items-stretch">
                        <input id="composer" type="text" class="flex-1 rounded border p-2 uppercase" placeholder="CLIQUE EM UM CÍRCULO PARA INICIAR UMA NOTA..." autocomplete="off">
                        <button type="button" id="btn-inserir" class="px-4 py-2 rounded bg-blue-900 text-white disabled:opacity-50" disabled>INSERIR</button>
                        <button type="button" id="btn-limpar"  class="px-4 py-2 rounded border">LIMPAR</button>
                    </div>

                    <div class="mt-3"><ol id="lista-avarias" class="list-decimal pl-6 text-sm space-y-1"></ol></div>
                    <input type="hidden" name="avarias_json" id="avarias_json" value='{{ old('avarias_json','{"avarias":[]}') }}' />
                </div>

                <div class="mt-6"><button class="px-4 py-2 rounded bg-blue-900 text-white">SALVAR</button></div>
            </form>
        </div>
    </div>

    <script>
    (function(){
        "use strict";

        const LABELS = [
            // FRENTE
            'FAROL ESQUERDO','FAROL DIREITO','FAROL DE NEBLINA ESQUERDO','FAROL DE NEBLINA DIREITO',
            'PARA-CHOQUE DIANTEIRO (LADO ESQ.)','PARA-CHOQUE DIANTEIRO (LADO DIR.)','GRADE DIANTEIRA',
            'CAPÔ','TETO','TAMPA DO PORTA-MALAS','PARA-BRISA DIANTEIRO (ESQ.)','PARA-BRISA DIANTEIRO (DIR.)',
            'RETROVISOR ESQUERDO','RETROVISOR DIREITO','PLACA DIANTEIRA',

            // TRASEIRA
            'LANTERNA TRASEIRA ESQUERDA','LANTERNA TRASEIRA DIREITA',
            'PARA-CHOQUE TRASEIRO (LADO ESQ.)','PARA-CHOQUE TRASEIRO (LADO DIR.)',
            'VIDRO TRASEIRO (ESQ.)','VIDRO TRASEIRO (DIR.)','PLACA TRASEIRA',

            // LATERAL DIREITA
            'PARALAMA DIANTEIRO DIREITO','PORTA DIANTEIRA DIREITA','PORTA TRASEIRA DIREITA','PARALAMA TRASEIRO DIREITO',
            'VIDRO DIANTEIRO DIREITO','VIDRO TRASEIRO DIREITO',
            'RODA DIANTEIRA DIREITA','RODA TRASEIRA DIREITA',
            'CAIXA DE AR DIREITA',
            'SAIA LATERAL DIANTEIRA DIREITA','SAIA LATERAL TRASEIRA DIREITA','COLUNA B DIREITA',

            // LATERAL ESQUERDA
            'PARALAMA DIANTEIRO ESQUERDO','PORTA DIANTEIRA ESQUERDA','PORTA TRASEIRA ESQUERDA','PARALAMA TRASEIRO ESQUERDO',
            'VIDRO DIANTEIRO ESQUERDO','VIDRO TRASEIRO ESQUERDO',
            'RODA DIANTEIRA ESQUERDA','RODA TRASEIRA ESQUERDA',
            'CAIXA DE AR ESQUERDA',
            'SAIA LATERAL DIANTEIRA ESQUERDA','SAIA LATERAL TRASEIRA ESQUERDA','COLUNA B ESQUERDA'
        ];

        // === COORDS BASE (em %) ===
        const COORDS_BASE = {
          "FAROL ESQUERDO":[36.168872085696286,21.840256559177792],
          "FAROL DIREITO":[13.886006547765817,22.07716443889608],
          "FAROL DE NEBLINA ESQUERDO":[35.23944549464399,32.08727052347163],
          "FAROL DE NEBLINA DIREITO":[13.722826086956522,31.91959798994975],
          "PARA-CHOQUE DIANTEIRO (LADO ESQ.)":[36.68872085696282,26.970632054746098],
          "PARA-CHOQUE DIANTEIRO (LADO DIR.)":[12.907608695652172,26.77386934673367],
          "GRADE DIANTEIRA":[24.32065217391304,25.809045226130657],
          "CAPÔ":[52.04831716939262,81.04068637422104],
          "PARA-BRISA DIANTEIRO (ESQ.)":[17.87168502232816,10.01636347693875],
          "PARA-BRISA DIANTEIRO (DIR.)":[31.628157447741156,10.205222217245176],
          "RETROVISOR ESQUERDO":[39.244643982356656,14.541855003661524],
          "RETROVISOR DIREITO":[10.371376811594203,14.311557788944723],
          "PLACA DIANTEIRA":[24.501811594202902,32.88442211055276],
          "LANTERNA TRASEIRA ESQUERDA":[14.80978260869565,72.08040201005025],
          "LANTERNA TRASEIRA DIREITA":[35.552536231884055,71.59798994974874],
          "PARA-CHOQUE TRASEIRO (LADO ESQ.)":[15.08152173913044,81.20603015075378],
          "PARA-CHOQUE TRASEIRO (LADO DIR.)":[34.73731884057971,81.52763819095476],
          "TAMPA DO PORTA-MALAS":[86.192244733021,81.25731559238072],
          "VIDRO TRASEIRO (ESQ.)":[73.7534662540514,37.27744248882603],
          "VIDRO TRASEIRO (DIR.)":[63.4227635812241,5.354879859300246],
          "PLACA TRASEIRA":[24.23007246376811,75.25628140703517],
          "PARALAMA DIANTEIRO DIREITO":[84.55025204788909,12.077270775990506],
          "PORTA DIANTEIRA DIREITA":[72.24716446124765,15.281230271962832],
          "PORTA TRASEIRA DIREITA":[63.08876811594203,15.035175879396984],
          "PARALAMA TRASEIRO DIREITO":[83.65036231884058,44.301507537688444],
          "VIDRO DIANTEIRO DIREITO":[71.70762444864525,5.814802656498573],
          "VIDRO TRASEIRO DIREITO":[30.842391304347828,63.79899497487437],
          "RODA DIANTEIRA DIREITA":[83.10688405797102,20.984924623115578],
          "RODA TRASEIRA DIREITA":[55.93297101449275,21.14572864321608],
          "SAIA LATERAL DIANTEIRA DIREITA":[88.17934782608697,22.87437185929648],
          "SAIA LATERAL TRASEIRA DIREITA":[50.04528985507245,22.030150753768847],
          "COLUNA B DIREITA":[66.9836956521739,5.306532663316582],
          "PARALAMA DIANTEIRO ESQUERDO":[54.06427221172022,44.69543698391455],
          "PORTA DIANTEIRA ESQUERDA":[64.44746376811594,47.23618090452262],
          "PORTA TRASEIRA ESQUERDA":[73.68659420289855,46.67336683417086],
          "PARALAMA TRASEIRO ESQUERDO":[53.03442028985508,12.462311557788944],
          "VIDRO DIANTEIRO ESQUERDO":[66.16847826086956,37.869346733668344],
          "VIDRO TRASEIRO ESQUERDO":[19.157608695652172,63.55778894472361],
          "RODA DIANTEIRA ESQUERDA":[53.57986767485823,53.29239160627256],
          "RODA TRASEIRA ESQUERDA":[81.38586956521739,53.869346733668344],
          "SAIA LATERAL DIANTEIRA ESQUERDA":[47.50905797101449,54.552763819095475],
          "SAIA LATERAL TRASEIRA ESQUERDA":[87.09239130434784,54.030150753768844],
          "COLUNA B ESQUERDA":[70.06340579710145,36.743718592964825]
        };

        // TETO (se ausente) = média de CAPÔ e TAMPA
        if (!COORDS_BASE['TETO']) {
            const c = COORDS_BASE['CAPÔ'], t = COORDS_BASE['TAMPA DO PORTA-MALAS'];
            COORDS_BASE['TETO'] = (c && t) ? [ (c[0]+t[0])/2, (c[1]+t[1])/2 ] : [50,50];
        }
        // CAIXAS DE AR (se ausentes) = média entre rodas do lado
        if (!COORDS_BASE['CAIXA DE AR DIREITA']) {
            const a = COORDS_BASE['RODA DIANTEIRA DIREITA'], b = COORDS_BASE['RODA TRASEIRA DIREITA'];
            COORDS_BASE['CAIXA DE AR DIREITA'] = (a && b) ? [ (a[0]+b[0])/2, (a[1]+b[1])/2 ] : [69.52,21.07];
        }
        if (!COORDS_BASE['CAIXA DE AR ESQUERDA']) {
            const a = COORDS_BASE['RODA DIANTEIRA ESQUERDA'], b = COORDS_BASE['RODA TRASEIRA ESQUERDA'];
            COORDS_BASE['CAIXA DE AR ESQUERDA'] = (a && b) ? [ (a[0]+b[0])/2, (a[1]+b[1])/2 ] : [67.48,53.58];
        }

        // localStorage override
        let COORDS = (() => {
            const s = localStorage.getItem('DKR_HOTSPOTS');
            if (!s) return {...COORDS_BASE};
            try { const o = JSON.parse(s); return {...COORDS_BASE, ...o}; } catch { return {...COORDS_BASE}; }
        })();

        const svg=document.getElementById('checklist-svg');
        const gHot=document.getElementById('hotspots');
        const gPins=document.getElementById('pins');
        const composer=document.getElementById('composer');
        const btnIns=document.getElementById('btn-inserir');
        const btnClr=document.getElementById('btn-limpar');
        const ol=document.getElementById('lista-avarias');
        const hidden=document.getElementById('avarias_json');
        const chkCal=document.getElementById('chk-calibrar');
        const btnExp=document.getElementById('btn-exportar');
        const btnReset=document.getElementById('btn-reset');

        const VIEW_W=1200, VIEW_H=675;
        const hotMap={};
        let items=[], pending=null;

        // Se veio old('avarias_json'), carrega
        try {
            const prev = JSON.parse(hidden.value || '{"avarias":[]}');
            if (Array.isArray(prev.avarias)) items = prev.avarias.map((a,i)=>({l:String(a.l||''), nota:String(a.nota||''), seq: Number(a.seq||i+1)}));
        } catch {}

        // Render hotspots
        LABELS.forEach(lab=>{
            const [px,py]=COORDS[lab] ?? [50,50];
            const cx=(px/100)*VIEW_W, cy=(py/100)*VIEW_H;
            const c=document.createElementNS('http://www.w3.org/2000/svg','circle');
            c.setAttribute('class','hotspot'); c.setAttribute('cx',cx); c.setAttribute('cy',cy); c.setAttribute('r',14);
            const t=document.createElementNS('http://www.w3.org/2000/svg','title'); t.textContent=lab; c.appendChild(t);
            c.addEventListener('click',()=>{ if(!chkCal.checked) startPending(lab); });
            enableDrag(c,lab);
            gHot.appendChild(c); hotMap[lab]=c;
        });

        // Render pins/lista que já existiam
        items.forEach(i=>{ addListRow(i); addPinFor(i.l, i.seq); });
        syncHidden();

        function startPending(label){
            pending={l:label};
            const next=items.length+1, base=`${next} - ${label}: `;
            composer.value=base; composer.focus(); composer.setSelectionRange(base.length,base.length);
            btnIns.disabled=false;
        }
        function inserir(){
            if(!pending) return;
            let val=(composer.value||'').toUpperCase().trim(); if(!val) return;
            const i=val.indexOf(':'), nota=i>=0?val.slice(i+1).trim():''; if(!nota) return;
            items.push({l:pending.l, nota, seq:items.length+1});
            addListRow(items.at(-1)); addPinFor(pending.l, items.length);
            pending=null; composer.value=''; btnIns.disabled=true; syncHidden();
        }
        function addListRow(it){
            const li=document.createElement('li'); li.dataset.seq=String(it.seq);
            li.innerHTML=`<span>${it.seq} - ${it.l}: ${it.nota}</span>
                          <button type="button" class="ml-2 px-2 py-0.5 rounded border text-xs" data-del="${it.seq}">EXCLUIR</button>`;
            ol.appendChild(li); li.querySelector('[data-del]').addEventListener('click',()=>removeBySeq(it.seq));
        }
        function addPinFor(label,seq){
            const c=hotMap[label]; if(!c) return;
            const cx=Number(c.getAttribute('cx')), cy=Number(c.getAttribute('cy'));
            const g=document.createElementNS('http://www.w3.org/2000/svg','g'); g.setAttribute('data-pin-seq',String(seq));
            const circle=document.createElementNS('http://www.w3.org/2000/svg','circle'); circle.setAttribute('class','pin'); circle.setAttribute('cx',cx); circle.setAttribute('cy',cy); circle.setAttribute('r',10);
            const text=document.createElementNS('http://www.w3.org/2000/svg','text'); text.setAttribute('class','pin-text'); text.setAttribute('x',cx); text.setAttribute('y',cy); text.textContent=String(seq);
            g.appendChild(circle); g.appendChild(text); gPins.appendChild(g);
        }
        function removeBySeq(seq){
            items=items.filter(i=>i.seq!==seq); items.forEach((i,idx)=>i.seq=idx+1);
            ol.innerHTML=''; gPins.innerHTML=''; items.forEach(i=>{addListRow(i); addPinFor(i.l,i.seq);});
            pending=null; composer.value=''; btnIns.disabled=true; syncHidden();
        }
        function syncHidden(){ hidden.value=JSON.stringify({avarias:items}); }

        function enableDrag(circle,label){
            let dragging=false, offX=0, offY=0;
            circle.addEventListener('mousedown',e=>{
                if(!chkCal.checked) return; dragging=true; circle.classList.add('drag');
                const cx=Number(circle.getAttribute('cx')), cy=Number(circle.getAttribute('cy'));
                offX=e.clientX-cx; offY=e.clientY-cy; e.preventDefault();
            });
            window.addEventListener('mousemove',e=>{
                if(!dragging) return;
                const rect=svg.getBoundingClientRect();
                const px=(e.clientX-offX-rect.left)/rect.width*VIEW_W;
                const py=(e.clientY-offY-rect.top)/rect.height*VIEW_H;
                const cx=Math.max(0,Math.min(VIEW_W,px));
                const cy=Math.max(0,Math.min(VIEW_H,py));
                circle.setAttribute('cx',cx); circle.setAttribute('cy',cy);
            });
            window.addEventListener('mouseup',()=>{
                if(!dragging) return; dragging=false; circle.classList.remove('drag');
                const cx=Number(circle.getAttribute('cx')), cy=Number(circle.getAttribute('cy'));
                COORDS[label]=[(cx/VIEW_W)*100,(cy/VIEW_H)*100];
                localStorage.setItem('DKR_HOTSPOTS', JSON.stringify(COORDS));
            });
        }

        document.getElementById('btn-inserir').addEventListener('click', inserir);
        document.getElementById('btn-limpar').addEventListener('click', ()=>{ pending=null; composer.value=''; btnIns.disabled=true; });
        composer.addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); inserir(); }});

        document.getElementById('btn-exportar').addEventListener('click',()=>{
            const data=JSON.stringify(COORDS,null,2);
            navigator.clipboard?.writeText(data).catch(()=>{});
            alert('COORDENADAS COPIADAS.');
        });
        document.getElementById('btn-reset').addEventListener('click',()=>{ localStorage.removeItem('DKR_HOTSPOTS'); location.reload(); });
    })();
    </script>
</x-app-layout>
