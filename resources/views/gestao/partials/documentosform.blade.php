{{-- resources/views/gestao/partials/documentosform.blade.php --}}
@php
  $ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];

  // valores atuais
  $cpf                = old('cpf',                $registro->cpf                ?? '');
  $rg_numero          = old('rg_numero',          $registro->rg_numero          ?? '');
  $rg_orgao_emissor   = old('rg_orgao_emissor',   $registro->rg_orgao_emissor   ?? '');
  $rg_uf              = old('rg_uf',              $registro->rg_uf              ?? '');
  $prontuario         = old('prontuario',         $registro->prontuario         ?? '');
  $observacoes        = old('observacoes',        $registro->observacoes        ?? '');

  // Outros documentos em JSON (array de objetos [{tipo, numero}])
  $outros_docs_json   = old('outros_documentos',  $registro->outros_documentos  ?? '[]');
  if ($outros_docs_json === '' || $outros_docs_json === null) $outros_docs_json = '[]';

  // Tipos de documentos pré-listados
  $doc_tipos = [
    'CTPS' => 'CTPS (Carteira de Trabalho)',
    'PIS' => 'PIS',
    'PASEP' => 'PASEP',
    'TIT_ELEITOR' => 'Título de Eleitor',
    'CNH' => 'CNH',
    'PASSAPORTE' => 'Passaporte',
    'CERT_NASC' => 'Certidão de Nascimento',
    'CERT_CAS' => 'Certidão de Casamento',
    'RESERVISTA' => 'Certificado de Reservista',
    'CNS' => 'Cartão Nacional de Saúde (CNS)',
    'RG_PROF' => 'Carteira Profissional (OAB/CRM/CREA etc.)',
    'OUTRO' => 'Outro'
  ];

  // exibe CPF já mascarado (se 11 dígitos)
  $cpf_digits = preg_replace('/\D+/', '', (string)$cpf);
  $cpf_masked = (strlen($cpf_digits) === 11)
      ? substr($cpf_digits,0,3).'.'.substr($cpf_digits,3,3).'.'.substr($cpf_digits,6,3).'-'.substr($cpf_digits,9,2)
      : $cpf;

@endphp

<div class="bg-white border rounded p-4 space-y-6" id="doc-form-wrap">

  {{-- Linha: CPF --}}
  <div class="pb-4 border-b border-gray-200">
    <label for="cpf" class="block text-sm font-semibold">CPF</label>
    <input
      type="text"
      id="cpf"
      name="cpf"
      value="{{ $cpf_masked }}"
      inputmode="numeric"
      autocomplete="off"
      class="mt-1 w-full rounded-md border-gray-300"
      placeholder="000.000.000-00"
      maxlength="14"
    >
    <p class="text-xs text-gray-500 mt-1">Digite apenas os números (pontos/traço são automáticos, mas se digitar também é aceito).</p>
  </div>

  {{-- Linha: RG (Número, Órgão Emissor, UF) --}}
  <div class="pb-4 border-b border-gray-200">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-semibold">RG — Número</label>
        <input type="text" name="rg_numero" value="{{ $rg_numero }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Ex.: 1234567">
      </div>

      <div>
        <label class="block text-sm font-semibold">RG — Órgão Emissor</label>
        <input type="text" name="rg_orgao_emissor" value="{{ $rg_orgao_emissor }}" class="mt-1 w-full rounded-md border-gray-300 uppercase" placeholder="Ex.: SSP, DETRAN, IF...">
      </div>

      <div>
        <label class="block text-sm font-semibold">RG — UF</label>
        <select name="rg_uf" class="mt-1 w-full rounded-md border-gray-300">
          <option value="">Selecione...</option>
          @foreach($ufs as $uf)
            <option value="{{ $uf }}" @selected($rg_uf===$uf)>{{ $uf }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  {{-- Linha: Prontuário Polícia Civil --}}
  <div class="pb-4 border-b border-gray-200">
    <label class="block text-sm font-semibold">Prontuário Polícia Civil</label>
    <input type="text" name="prontuario" value="{{ $prontuario }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="Ex.: 12345-A">
  </div>

  {{-- Linha: Outros Documentos (dropdown + número + inserir) --}}
  <div class="pb-4 border-b border-gray-200 space-y-3">
    <label class="block text-sm font-semibold">Outros Documentos</label>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
      <div class="md:col-span-2">
        <select id="doc_tipo" class="mt-1 w-full rounded-md border-gray-300">
          @foreach($doc_tipos as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <input type="text" id="doc_numero" class="mt-1 w-full rounded-md border-gray-300" placeholder="Número / Identificador (texto livre)">
      </div>
      <div class="md:col-span-1">
        <button type="button" id="btn_add_doc" class="mt-1 w-full px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Inserir</button>
      </div>
    </div>

    {{-- Hidden JSON do array de documentos --}}
    <input type="hidden" name="outros_documentos" id="outros_documentos" value='@json(json_decode($outros_docs_json, true) ?? [])'>

    {{-- Lista com separador por item --}}
    <div id="outros_docs_list" class="divide-y divide-gray-200 rounded border">
      {{-- items renderizados via JS --}}
    </div>

    <p class="text-xs text-gray-500">Você pode inserir vários documentos. Cada item aparece separado por uma linha. O “Número” aceita letras e símbolos.</p>
  </div>

  {{-- Linha: Observações (opcional) --}}
  <div class="pb-0">
    <label class="block text-sm font-semibold">Observações</label>
    <textarea name="observacoes" rows="4" class="mt-1 w-full rounded-md border-gray-300" placeholder="Anotações sobre documentos, particularidades, etc.">{{ $observacoes }}</textarea>
  </div>
</div>

{{-- JS: máscara de CPF + gestão de “outros documentos” --}}
<script>
(function() {
  // ===== CPF mask (exibe formatado; envia dígitos) =====
  const cpfInput = document.getElementById('cpf');
  function onlyDigits(s) { return (s || '').replace(/\D+/g, ''); }
  function formatCPF(digits) {
    digits = onlyDigits(digits).slice(0, 11);
    let out = '';
    for (let i = 0; i < digits.length; i++) {
      out += digits[i];
      if (i === 2 || i === 5) out += '.';
      if (i === 8) out += '-';
    }
    return out;
  }
  if (cpfInput) {
    cpfInput.addEventListener('input', function() {
      const formatted = formatCPF(cpfInput.value);
      cpfInput.value = formatted;
    });

    // No submit: envia apenas dígitos
    const form = cpfInput.closest('form');
    if (form) {
      form.addEventListener('submit', function() {
        cpfInput.value = onlyDigits(cpfInput.value);
      });
    }
  }

    // Força caixa alta enquanto digita no Órgão Emissor
    const rgOrg = document.querySelector('input[name="rg_orgao_emissor"]');
    if (rgOrg) {
    rgOrg.addEventListener('input', () => { rgOrg.value = rgOrg.value.toUpperCase(); });
    }

  // ===== Outros Documentos: dropdown + número + lista (JSON hidden) =====
  const hidden = document.getElementById('outros_documentos');
  const list = document.getElementById('outros_docs_list');
  const btnAdd = document.getElementById('btn_add_doc');
  const tipoSel = document.getElementById('doc_tipo');
  const numeroInp = document.getElementById('doc_numero');

  let docs = [];
  try {
    docs = JSON.parse(hidden.value || '[]') || [];
    if (!Array.isArray(docs)) docs = [];
  } catch(e) { docs = []; }

  function labelTipo(k) {
    const map = {
      'CTPS':'CTPS (Carteira de Trabalho)','PIS':'PIS','PASEP':'PASEP','TIT_ELEITOR':'Título de Eleitor',
      'CNH':'CNH','PASSAPORTE':'Passaporte','CERT_NASC':'Certidão de Nascimento','CERT_CAS':'Certidão de Casamento',
      'RESERVISTA':'Certificado de Reservista','CNS':'Cartão Nacional de Saúde (CNS)','RG_PROF':'Carteira Profissional (OAB/CRM/CREA etc.)',
      'OUTRO':'Outro'
    };
    return map[k] || k;
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function syncHidden() {
    hidden.value = JSON.stringify(docs);
  }

  function renderDocs() {
    list.innerHTML = '';
    if (!docs.length) {
      const empty = document.createElement('div');
      empty.className = 'p-4 text-center text-gray-600';
      empty.textContent = 'Nenhum documento adicionado.';
      list.appendChild(empty);
      return;
    }
    docs.forEach((d, i) => {
      const row = document.createElement('div');
      row.className = 'p-3 flex items-center justify-between';
      row.innerHTML = `
        <div class="min-w-0">
          <div class="text-sm font-medium">${escapeHtml(labelTipo(d.tipo))}</div>
          <div class="text-sm text-gray-700 break-words">${escapeHtml(d.numero || '')}</div>
        </div>
        <div class="shrink-0">
          <button type="button" data-idx="${i}" class="px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700 btn-rem-doc">Remover</button>
        </div>
      `;
      list.appendChild(row);
    });
  }

  if (btnAdd) {
    btnAdd.addEventListener('click', function() {
      const tipo = (tipoSel.value || '').toUpperCase();
      const numero = (numeroInp.value || '').trim();
      if (!tipo || !numero) return;

      docs.push({ tipo, numero });
      numeroInp.value = '';
      syncHidden();
      renderDocs();
      numeroInp.focus();
    });
  }

  list.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-rem-doc');
    if (!btn) return;
    const idx = parseInt(btn.getAttribute('data-idx'), 10);
    if (!Number.isNaN(idx)) {
      docs.splice(idx, 1);
      syncHidden();
      renderDocs();
    }
  });

  renderDocs();
})();
</script>
