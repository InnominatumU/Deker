{{-- resources/views/sistema/partials/unidadeform.blade.php --}}
@php
  /** @var \stdClass|array|null $unidade */
  $u = $unidade ?? null;

  // Helpers globais simples
  if (!function_exists('view_upper')) {
      function view_upper($s) { return mb_strtoupper((string)$s, 'UTF-8'); }
  }
  if (!function_exists('view_strip_accents')) {
      function view_strip_accents(string $s): string {
          $s = view_upper($s);
          $trans = ['Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ç'=>'C'];
          return strtr($s, $trans);
      }
  }
  if (!function_exists('view_canon_from')) {
      function view_canon_from(?string $raw, array $options): string {
          $raw = (string)$raw;
          $nRaw = view_strip_accents($raw);
          foreach (array_keys($options) as $k) {
              if (view_strip_accents($k) === $nRaw) return $k;
          }
          return $raw;
      }
  }

  // Perfil — lista canônica
  $PERFIS = [
    'COMPLEXO PENITENCIÁRIO'              => 'COMPLEXO PENITENCIÁRIO',
    'PENITENCIÁRIA'                       => 'PENITENCIÁRIA',
    'PRESÍDIO'                            => 'PRESÍDIO',
    'UNIDADE DE REMANEJAMENTO/ TRÂNSITO'  => 'UNIDADE DE REMANEJAMENTO / TRÂNSITO',
    'UNIDADE DE TRIAGEM/ OBSERVAÇÃO'      => 'UNIDADE DE TRIAGEM / OBSERVAÇÃO',
    'COLÔNIA PENAL AGRÍCOLA/ INDUSTRIAL'  => 'COLÔNIA PENAL AGRÍCOLA / INDUSTRIAL',
    'CASA DE ALBERGADO'                   => 'CASA DE ALBERGADO',
    'CADEIA PÚBLICA'                      => 'CADEIA PÚBLICA',
    'UNIDADE ESPECIAL'                    => 'UNIDADE ESPECIAL',
    'CDP'                                 => 'CDP — CENTRO DE DETENÇÃO PROVISÓRIA',
    'CPP'                                 => 'CPP — CENTRO DE PROGRESSÃO PENITENCIÁRIA',
    'PPP'                                 => 'PPP — PARCERIA PÚBLICO PRIVADA',
    'OUTROS'                              => 'OUTROS',
  ];

  // Campos básicos
  $nome  = old('nome',  data_get($u, 'nome',  ''));
  $sigla = old('sigla', data_get($u, 'sigla', ''));

  // Endereço
  $end = [
    'logradouro' => old('end_logradouro',  data_get($u, 'end_logradouro',  '')),
    'numero'     => old('end_numero',      data_get($u, 'end_numero',      '')),
    'compl'      => old('end_complemento', data_get($u, 'end_complemento', '')),
    'bairro'     => old('end_bairro',      data_get($u, 'end_bairro',      '')),
    'municipio'  => old('end_municipio',   data_get($u, 'end_municipio',   '')),
    'uf'         => old('end_uf',          data_get($u, 'end_uf',          '')),
    'cep'        => old('end_cep',         data_get($u, 'end_cep',         '')),
  ];

  // Classificação
  $porte = view_upper(old('porte', data_get($u, 'porte', '')));
  if ($porte === 'MEDIO') $porte = 'MÉDIO';

  $perfilRaw    = old('perfil', data_get($u, 'perfil', ''));
  $perfilCanon  = view_canon_from($perfilRaw, $PERFIS);
  $perfilOutro  = old('perfil_outro', data_get($u, 'perfil_outro', ''));

  // Capacidade
  $capacidade = old('capacidade_vagas', data_get($u, 'capacidade_vagas'));

  // ========================= MAPEAMENTO =========================
  // Preferir o que veio do controller -> $mapeamento_itens
  $initItens = old('mapeamento_itens', $mapeamento_itens ?? []);

  // Fallbacks se vier vazio (lê direto da coluna JSON bruta)
  if (empty($initItens)) {
      $legacy = data_get($u, 'mapeamento');
      if (!$legacy) {
          $json = data_get($u, 'mapeamento_json');
          if (is_string($json) && $json !== '') {
              $decoded = json_decode($json, true);
              if (is_array($decoded)) $legacy = $decoded;
          }
      }
      if (is_array($legacy)) {
          if (array_key_exists('itens', $legacy) && is_array($legacy['itens'])) {
              $legacy = $legacy['itens'];
          } elseif (array_key_exists('items', $legacy) && is_array($legacy['items'])) {
              $legacy = $legacy['items'];
          }

          $pushItem = function(array &$arr, $tipo, $valor, $ext='') {
              $tipo  = view_upper(trim((string)$tipo));
              $valor = trim((string)$valor);
              $ext   = trim((string)$ext);
              if ($tipo !== '' && $valor !== '') {
                  $arr[] = ['tipo'=>$tipo,'valor'=>$valor,'valor_extenso'=>$ext];
              }
          };

          $isAssocMap = array_keys($legacy) !== range(0, count($legacy) - 1);
          if ($isAssocMap) {
              foreach ($legacy as $tipo => $vals) {
                  if (is_array($vals)) {
                      foreach ($vals as $v) $pushItem($initItens, $tipo, $v);
                  } else {
                      $pushItem($initItens, $tipo, $vals);
                  }
              }
          } else {
              foreach ($legacy as $row) {
                  if (is_array($row) && isset($row['tipo'])) {
                      if (isset($row['valor'])) {
                          $pushItem($initItens, $row['tipo'], $row['valor'], $row['valor_extenso'] ?? '');
                          continue;
                      }
                      if (isset($row['valores']) && is_array($row['valores'])) {
                          foreach ($row['valores'] as $v) $pushItem($initItens, $row['tipo'], $v);
                          continue;
                      }
                  }
                  if (is_array($row)) {
                      foreach (['setor','bloco','ala','galeria','cela','portaria','guarita','muralha','cancela','passarela','gaiola','outros'] as $k) {
                          if (!array_key_exists($k, $row)) continue;
                          $val = $row[$k];
                          if (is_array($val)) {
                              foreach ($val as $v) $pushItem($initItens, $k, $v);
                          } else {
                              $pushItem($initItens, $k, $val);
                          }
                      }
                  }
              }
          }
      }
  }
  // ======================= /MAPEAMENTO =======================
@endphp

<style>.ucase{ text-transform: uppercase; }</style>

{{-- JSON seguro para o Alpine ler (evita quebrar o x-init com aspas/acentos) --}}
<script type="application/json" id="unidades-init-itens">@json($initItens)</script>
<script type="application/json" id="unidades-init-perfil">@json($perfilCanon)</script>
<script type="application/json" id="unidades-init-perfil-outro">@json($perfilOutro)</script>

<div x-data="unidadesForm()" x-init="initFromDom()" class="space-y-6">

  <input type="hidden" id="perfil_server_value" value="{{ $perfilCanon }}"/>

  {{-- Identificação --}}
  <div class="bg-white shadow-sm sm:rounded-2xl p-6">
    <h2 class="font-semibold mb-4">Identificação da Unidade</h2>
    <div class="grid md:grid-cols-3 gap-4">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Nome da unidade <span class="text-red-600">*</span></label>
        <input type="text" name="nome" value="{{ $nome }}" required class="w-full rounded border-gray-300 ucase" maxlength="150" data-upcase autocomplete="off" spellcheck="false">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Sigla/Apelido</label>
        <input type="text" name="sigla" value="{{ $sigla }}" class="w-full rounded border-gray-300 ucase" maxlength="30" data-upcase autocomplete="off" spellcheck="false">
      </div>
    </div>
  </div>

  {{-- Endereço --}}
  <div class="bg-white shadow-sm sm:rounded-2xl p-6">
    <h2 class="font-semibold mb-4">Endereço</h2>
    <div class="grid md:grid-cols-6 gap-4">
      <div class="md:col-span-3">
        <label class="block text-sm font-medium mb-1">Logradouro</label>
        <input type="text" name="end_logradouro" value="{{ $end['logradouro'] }}" class="w-full rounded border-gray-300 ucase" data-upcase autocomplete="off" spellcheck="false">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Número</label>
        <input type="text" name="end_numero" value="{{ $end['numero'] }}" class="w-full rounded border-gray-300 ucase" data-upcase autocomplete="off" spellcheck="false">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Complemento</label>
        <input type="text" name="end_complemento" value="{{ $end['compl'] }}" class="w-full rounded border-gray-300 ucase" data-upcase autocomplete="off" spellcheck="false">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Bairro</label>
        <input type="text" name="end_bairro" value="{{ $end['bairro'] }}" class="w-full rounded border-gray-300 ucase" data-upcase autocomplete="off" spellcheck="false">
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Município</label>
        <input type="text" name="end_municipio" value="{{ $end['municipio'] }}" class="w-full rounded border-gray-300 ucase" data-upcase autocomplete="off" spellcheck="false">
      </div>

      {{-- UF --}}
      <div>
        <label class="block text-sm font-medium mb-1">UF</label>
        @php
          $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
          $ufSel = strtoupper($end['uf'] ?? '');
        @endphp
        <select name="end_uf" class="w-full rounded border-gray-300">
          <option value="">- SELECIONE -</option>
          @foreach($ufs as $uf)
            <option value="{{ $uf }}" @selected($ufSel === $uf)>{{ $uf }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">CEP</label>
        <input type="text" name="end_cep" value="{{ $end['cep'] }}" class="w-full rounded border-gray-300" inputmode="numeric" autocomplete="off" spellcheck="false">
      </div>
    </div>
  </div>

  {{-- Classificação + Capacidade --}}
  <div class="bg-white shadow-sm sm:rounded-2xl p-6">
    <h2 class="font-semibold mb-4">Classificação</h2>

    <div class="grid md:grid-cols-4 gap-4">
      <label class="block">
        <span class="text-sm font-medium mb-1">Porte <span class="text-red-600">*</span></span>
        <select name="porte" class="w-full rounded border-gray-300 ucase" required>
          <option value="">- SELECIONE -</option>
          @foreach(['PEQUENO','MÉDIO','GRANDE'] as $opt)
            <option value="{{ $opt }}" @selected($porte === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </label>

      <label class="block md:col-span-3">
        <span class="text-sm font-medium mb-1">Perfil da unidade <span class="text-red-600">*</span></span>
        <select name="perfil" x-model="perfil" class="w-full rounded border-gray-300 ucase" required>
          <option value="">- SELECIONE -</option>
          @foreach($PERFIS as $val => $label)
            <option value="{{ $val }}" @selected($perfilCanon === $val)>{{ $label }}</option>
          @endforeach
        </select>

        <div x-show="perfil === 'OUTROS'" class="mt-2">
          <label class="block text-sm font-medium mb-1">Qual?</label>
          <input type="text" name="perfil_outro" x-model="perfilOutro" value="{{ $perfilOutro }}"
                 class="w-full rounded border-gray-300 ucase" maxlength="120"
                 placeholder="DESCREVA O PERFIL DA UNIDADE" data-upcase autocomplete="off" spellcheck="false">
        </div>
      </label>
    </div>

    <div class="grid md:grid-cols-4 gap-4 mt-4">
      <div>
        <label class="block text-sm font-medium mb-1">Número de vagas</label>
        <input type="number" name="capacidade_vagas" value="{{ $capacidade }}" min="0" class="w-full rounded border-gray-300" inputmode="numeric">
      </div>
    </div>
  </div>

  {{-- Mapeamento Físico --}}
  <div class="bg-white shadow-sm sm:rounded-2xl p-6">
    <div class="flex items-center justify-between mb-2">
      <h2 class="font-semibold">Mapeamento Físico</h2>
    </div>

    <div class="grid md:grid-cols-6 gap-3 items-end">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Tipo</label>
        <select x-model="novo.tipo" class="w-full rounded border-gray-300 ucase">
          <option value="">- SELECIONE -</option>
          <option value="SETOR">SETOR</option>
          <option value="BLOCO">BLOCO</option>
          <option value="ALA">ALA</option>
          <option value="GALERIA">GALERIA</option>
          <option value="CELA">CELA</option>
          <option value="PORTARIA">PORTARIA</option>
          <option value="GUARITA">GUARITA</option>
          <option value="MURALHA">MURALHA</option>
          <option value="CANCELA">CANCELA</option>
          <option value="PASSARELA">PASSARELA</option>
          <option value="GAIOLA">GAIOLA</option>
          <option value="OUTROS">OUTROS</option>
        </select>
      </div>

      <template x-if="['SETOR','OUTROS'].includes(novo.tipo)">
        <div class="md:col-span-3">
          <label class="block text-sm font-medium mb-1">Valor</label>
          <input type="text" x-model="novo.valor" class="w-full rounded border-gray-300 ucase" placeholder="EX.: SETOR ADMINISTRATIVO / OBSERVATÓRIO" data-upcase autocomplete="off" spellcheck="false">
        </div>
      </template>

      <template x-if="rangeTypes.includes(novo.tipo)">
        <div class="md:col-span-3 grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Início</label>
            <input type="text" x-model="novo.inicio" class="w-full rounded border-gray-300 ucase" placeholder="EX.: 1 OU A" data-upcase autocomplete="off" spellcheck="false">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Fim</label>
            <input type="text" x-model="novo.fim" class="w-full rounded border-gray-300 ucase" placeholder="EX.: 5 OU J" data-upcase autocomplete="off" spellcheck="false">
          </div>
        </div>
      </template>

      <div>
        <button type="button" class="w-full px-3 py-2 rounded bg-blue-900 text-white" @click="inserirItem()">Inserir</button>
      </div>
    </div>

    <div class="mt-4">
      <template x-if="itens.length === 0">
        <div class="p-3 rounded border border-gray-200 bg-gray-50 text-sm text-gray-700">
          Nenhum item adicionado.
        </div>
      </template>

      <template x-if="itens.length > 0">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left border-b">
                <th class="py-2 pr-3">#</th>
                <th class="py-2 pr-3">Tipo</th>
                <th class="py-2 pr-3">Valor</th>
                <th class="py-2 pr-3">Valor (extenso)</th>
                <th class="py-2">Ações</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="(it, i) in itens" :key="i">
                <tr class="border-b">
                  <td class="py-2 pr-3" x-text="i+1"></td>
                  <td class="py-2 pr-3" x-text="it.tipo"></td>
                  <td class="py-2 pr-3" x-text="it.valor"></td>
                  <td class="py-2 pr-3" x-text="it.valor_extenso || '—'"></td>
                  <td class="py-2">
                    <button type="button" class="px-2 py-1 rounded bg-gray-200" @click="removeItem(i)">Remover</button>
                  </td>

                  {{-- Inputs ocultos para submit --}}
                  <td class="hidden">
                    <input type="hidden" :name="`mapeamento_itens[${i}][tipo]`" :value="it.tipo">
                    <input type="hidden" :name="`mapeamento_itens[${i}][valor]`" :value="it.valor">
                    <input type="hidden" :name="`mapeamento_itens[${i}][valor_extenso]`" :value="it.valor_extenso || ''">
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>

  {{-- Observações --}}
  <div class="bg-white shadow-sm sm:rounded-2xl p-6">
    <h2 class="font-semibold mb-4">Observações</h2>
    <textarea name="observacoes" rows="4" class="w-full rounded border-gray-300 ucase" data-upcase autocomplete="off" spellcheck="false">{{ old('observacoes', data_get($u, 'observacoes', '')) }}</textarea>
  </div>
</div>

<script>
function unidadesForm() {
  return {
    perfil: '',
    perfilOutro: '',
    itens: [],
    rangeTypes: ['BLOCO','ALA','GALERIA','CELA','PORTARIA','GUARITA','MURALHA','CANCELA','PASSARELA','GAIOLA'],
    novo: { tipo: '', valor: '', inicio: '', fim: '' },

    // Lê JSON dos <script type="application/json"> e delega para init(...)
    initFromDom() {
      const readJson = (id) => {
        const el = document.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
      };
      const itens  = readJson('unidades-init-itens') || [];
      const perfil = readJson('unidades-init-perfil') || '';
      const outro  = readJson('unidades-init-perfil-outro') || '';
      this.init(itens, perfil, outro);
    },

    init(itensInit, perfilInit, perfilOutroInit) {
      const toUp = (s) => String(s ?? '').toLocaleUpperCase('pt-BR');

      this.itens = Array.isArray(itensInit) ? itensInit.map(it => ({
        tipo: toUp(it.tipo ?? ''),
        valor: toUp(it.valor ?? ''),
        valor_extenso: it.valor_extenso ? toUp(it.valor_extenso) : ''
      })) : [];

      const serverVal = toUp(perfilInit || '');
      if (serverVal) {
        this.perfil = serverVal;
      } else {
        const sel = document.querySelector('select[name="perfil"]');
        if (sel) this.perfil = toUp(sel.value || (document.getElementById('perfil_server_value')?.value || ''));
      }

      this.perfilOutro = toUp(perfilOutroInit || '');
    },

    removeItem(i){ if (i > -1) this.itens.splice(i,1); },

    inserirItem() {
      const toUp = (s) => String(s ?? '').toLocaleUpperCase('pt-BR');
      const t = (this.novo.tipo || '').toUpperCase();
      if (!t) { alert('Selecione o TIPO do mapeamento.'); return; }

      if (['SETOR','OUTROS'].includes(t)) {
        const v = toUp((this.novo.valor || '').trim());
        if (!v) { alert('Informe um valor.'); return; }
        this.itens.push({ tipo: t, valor: v, valor_extenso: '' });
        this.novo.valor = '';
        return;
      }

      if (this.rangeTypes.includes(t)) {
        const ini = (this.novo.inicio || '').trim();
        const fim = (this.novo.fim || '').trim();
        if (!ini || !fim) { alert('Informe início e fim.'); return; }
        const seq = this._expandRange(ini, fim);
        if (seq.length === 0) { alert('Faixa inválida.'); return; }
        for (const raw of seq) {
          const v = toUp(raw);
          const ext = this._valorExtenso(v);
          this.itens.push({ tipo: t, valor: v, valor_extenso: ext });
        }
        this.novo.inicio = '';
        this.novo.fim = '';
        return;
      }

      alert('Tipo inválido.');
    },

    _expandRange(a, b) {
      const isNum = (s) => /^[0-9]+$/.test(s);
      const isLet = (s) => /^[a-zA-Z]$/.test(s);

      if (isNum(a) && isNum(b)) {
        const start = parseInt(a,10), end = parseInt(b,10);
        if (end < start) return [];
        const out = [];
        for (let i=start;i<=end;i++) out.push(String(i));
        return out;
      }

      if (isLet(a) && isLet(b)) {
        const s = a.toUpperCase().charCodeAt(0);
        const e = b.toUpperCase().charCodeAt(0);
        if (e < s) return [];
        const out = [];
        for (let c=s;c<=e;c++) out.push(String.fromCharCode(c));
        return out;
      }

      return [];
    },

    _valorExtenso(v) {
      const map = { 'A':'ALFA','B':'BRAVO','C':'CHARLIE','D':'DELTA','E':'ECO','F':'FÓXTROT','G':'GOLF','H':'HOTEL','I':'ÍNDIA','J':'JULIET','K':'KILO','L':'LIMA','M':'MIKE','N':'NOVEMBER','O':'ÓSCAR','P':'PAPA','Q':'QUEBEC','R':'ROMEU','S':'SIERRA','T':'TANGO','U':'UNIFORME','V':'VICTOR','W':'WHISKEY','X':'X-RAY','Y':'YANKEE','Z':'ZULU' };
      const up = String(v).toUpperCase();
      return map[up] ? (up + ' — ' + map[up]) : '';
    },
  }
}
</script>

<script>
(function(){
  const toUp = (el) => {
    const start = el.selectionStart, end = el.selectionEnd;
    el.value = (el.value || '').toLocaleUpperCase('pt-BR');
    if (typeof start === 'number' && typeof end === 'number') {
      try { el.setSelectionRange(start, end); } catch(_) {}
    }
  };
  const hook = (root) => {
    const els = root.querySelectorAll('[data-upcase]');
    els.forEach(el => {
      toUp(el);
      el.addEventListener('input', () => toUp(el));
      el.addEventListener('change', () => toUp(el));
      el.addEventListener('blur', () => toUp(el));
    });
  };
  document.addEventListener('DOMContentLoaded', () => hook(document));
})();
</script>
