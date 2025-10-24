{{-- resources/views/gestao/partials/dadosform.blade.php --}}
@php
  // Garante variável definida
  $registro = $registro ?? null;

  // ORDEM ALFABÉTICA (exceto regras específicas abaixo)
  $ufs = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
  sort($ufs, SORT_STRING);

  $estadosCivis = ['CASADO(A)','DIVORCIADO(A)','SEPARADO(A)','SOLTEIRO(A)','UNIÃO ESTÁVEL','VIÚVO(A)'];
  sort($estadosCivis, SORT_STRING);

  $escolaridadeSituacoes = ['CONCLUÍDO','EM ANDAMENTO','INCOMPLETO'];
  sort($escolaridadeSituacoes, SORT_STRING);

  // Países EM CAIXA ALTA
  $paises = [
    'ALEMANHA','ARGENTINA','BOLÍVIA','BRASIL','CANADÁ','CHILE','CHINA','COLÔMBIA','COREIA DO SUL','ESPANHA',
    'ESTADOS UNIDOS','FRANÇA','ÍNDIA','ITÁLIA','JAPÃO','MÉXICO','PARAGUAI','PERU','PORTUGAL','REINO UNIDO',
    'URUGUAI','VENEZUELA'
  ];
  sort($paises, SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);

  // EXCEÇÕES: gênero (MASCULINO antes de FEMININO) e escolaridade por grau
  $generos = ['MASCULINO','FEMININO','NÃO-BINÁRIO','PREFERE NÃO INFORMAR'];
  $escolaridadeNiveis = [
    'NÃO ALFABETIZADO (NÃO ASSINA O NOME)',
    'NÃO ALFABETIZADO (ASSINA O NOME)',
    'FUNDAMENTAL',
    'MÉDIO',
    'TÉCNICO',
    'SUPERIOR',
    'PÓS-GRADUAÇÃO',
    'MESTRADO',
    'DOUTORADO',
  ];

  // Helper seguro com nullsafe
  $v = function (string $k, $default = '') use ($registro) {
      $fromRegistro = $registro?->{$k} ?? null;
      return old($k, $fromRegistro ?? $default);
  };

  // ESTADOS INICIAIS (checkboxes/controles)
  $paisAtual = $v('nacionalidade','BRASIL') ?: 'BRASIL';
  $isEstrangeiro = $paisAtual !== 'BRASIL';
  $isObito = (string)$v('obito','') === '1';
@endphp

<style>
  /* força caixa alta nos campos (exceto e-mail e JSON) */
  .force-upper { text-transform: uppercase; }
</style>

{{-- ================= DADOS PESSOAIS ================= --}}
<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Dados Pessoais</h2>

  <label class="block">
    <span class="text-sm text-gray-700">CadPen</span>
    <input type="text" value="{{ $registro?->cadpen ?? 'GERADO AUTOMATICAMENTE AO SALVAR' }}"
           class="mt-1 w-full rounded-md border-gray-300 bg-gray-100 text-gray-600 force-upper" disabled>
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Nome completo *</span>
    <input type="text" name="nome_completo" value="{{ $v('nome_completo') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper" required>
    @error('nome_completo') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Nome social</span>
    <input type="text" name="nome_social" value="{{ $v('nome_social') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('nome_social') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Alcunha</span>
    <input type="text" name="alcunha" value="{{ $v('alcunha') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('alcunha') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Data de nascimento</span>
    <input type="date" name="data_nascimento"
           value="{{ $v('data_nascimento') ? \Illuminate\Support\Str::of($v('data_nascimento'))->substr(0,10) : '' }}"
           class="mt-1 w-full rounded-md border-gray-300">
    @error('data_nascimento') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Sexo / Gênero</span>
    <select name="genero_sexo" class="mt-1 w-full rounded-md border-gray-300 force-upper">
      <option value="">— SELECIONE —</option>
      @foreach ($generos as $opt)
        <option value="{{ $opt }}" @selected($v('genero_sexo') === $opt)>{{ $opt }}</option>
      @endforeach
    </select>
    @error('genero_sexo') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  {{-- >>> NOVO CAMPO: PROFISSÃO (conforme migration/controller) --}}
  <label class="block">
    <span class="text-sm font-semibold">Profissão</span>
    <input type="text" name="profissao" value="{{ $v('profissao') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper" maxlength="120" placeholder="Ex.: PEDREIRO, PROFESSORA, MOTORISTA">
    @error('profissao') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>
</div>

{{-- ================= FILIAÇÃO ================= --}}
<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Filiação</h2>

  <label class="block">
    <span class="text-sm font-semibold">Mãe *</span>
    <input type="text" name="mae" value="{{ $v('mae') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper" required>
    @error('mae') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Pai</span>
    <input type="text" name="pai" value="{{ $v('pai') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('pai') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>
</div>

{{-- ================= NACIONALIDADE & NATURALIDADE ================= --}}
<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Nacionalidade & Naturalidade</h2>

  <div class="flex items-center gap-2">
    <input id="chkEstrangeiro" type="checkbox" class="rounded border-gray-300" {{ $isEstrangeiro ? 'checked' : '' }}>
    <label for="chkEstrangeiro" class="text-sm text-gray-700">Estrangeiro?</label>
  </div>

  <label class="block">
    <span class="text-sm font-semibold">País</span>
    <select id="selNacionalidade" name="nacionalidade"
            class="mt-1 w-full rounded-md border-gray-300 force-upper"
            {{ $isEstrangeiro ? '' : 'disabled' }}>
      @foreach($paises as $pais)
        <option value="{{ $pais }}" {{ ($paisAtual === $pais) ? 'selected' : '' }}>{{ $pais }}</option>
      @endforeach
    </select>
    @error('nacionalidade') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
    {{-- se não estrangeiro, envia BRASIL mesmo com select desabilitado --}}
    @unless($isEstrangeiro)
      <input type="hidden" name="nacionalidade" value="BRASIL">
    @endunless
  </label>

  <label class="block">
    <span class="text-sm font-semibold">UF de nascimento</span>
    <select name="naturalidade_uf" class="mt-1 w-full rounded-md border-gray-300 force-upper">
      <option value="">— SELECIONE —</option>
      @foreach ($ufs as $uf)
        <option value="{{ $uf }}" @selected($v('naturalidade_uf') === $uf)>{{ $uf }}</option>
      @endforeach
    </select>
    @error('naturalidade_uf') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Município de nascimento</span>
    <input type="text" name="naturalidade_municipio" value="{{ $v('naturalidade_municipio') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('naturalidade_municipio') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>
</div>

{{-- ================= SITUAÇÃO CIVIL & ESCOLARIDADE ================= --}}
<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Situação Civil & Escolaridade</h2>

  <label class="block">
    <span class="text-sm font-semibold">Estado civil</span>
    <select name="estado_civil" class="mt-1 w-full rounded-md border-gray-300 force-upper">
      <option value="">— SELECIONE —</option>
      @foreach ($estadosCivis as $opt)
        <option value="{{ $opt }}" @selected($v('estado_civil') === $opt)>{{ $opt }}</option>
      @endforeach
    </select>
    @error('estado_civil') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Escolaridade (nível)</span>
    <select name="escolaridade_nivel" class="mt-1 w-full rounded-md border-gray-300 force-upper">
      <option value="">— SELECIONE —</option>
      @foreach ($escolaridadeNiveis as $opt)
        <option value="{{ $opt }}" @selected($v('escolaridade_nivel') === $opt)>{{ $opt }}</option>
      @endforeach
    </select>
    @error('escolaridade_nivel') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Escolaridade (situação)</span>
    <select name="escolaridade_situacao" class="mt-1 w-full rounded-md border-gray-300 force-upper">
      <option value="">— SELECIONE —</option>
      @foreach ($escolaridadeSituacoes as $opt)
        <option value="{{ $opt }}" @selected($v('escolaridade_situacao') === $opt)>{{ $opt }}</option>
      @endforeach
    </select>
    @error('escolaridade_situacao') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>
</div>

{{-- ================= ENDEREÇO & CONTATOS ================= --}}
<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Endereço & Contatos</h2>

  <label class="block">
    <span class="text-sm font-semibold">Logradouro</span>
    <input type="text" name="end_logradouro" value="{{ $v('end_logradouro') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('end_logradouro') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Número</span>
    <input type="text" name="end_numero" value="{{ $v('end_numero') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('end_numero') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Complemento</span>
    <input type="text" name="end_complemento" value="{{ $v('end_complemento') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('end_complemento') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Bairro</span>
    <input type="text" name="end_bairro" value="{{ $v('end_bairro') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('end_bairro') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Município</span>
    <input type="text" name="end_municipio" value="{{ $v('end_municipio') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('end_municipio') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">UF</span>
    <select name="end_uf" class="mt-1 w-full rounded-md border-gray-300 force-upper">
      <option value="">— SELECIONE —</option>
      @foreach ($ufs as $uf)
        <option value="{{ $uf }}" @selected($v('end_uf') === $uf)>{{ $uf }}</option>
      @endforeach
    </select>
    @error('end_uf') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">CEP</span>
    <input type="text" name="end_cep" value="{{ $v('end_cep') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('end_cep') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Telefone principal</span>
    <input type="text" name="telefone_principal" value="{{ $v('telefone_principal') }}"
           class="mt-1 w-full rounded-md border-gray-300 force-upper">
    @error('telefone_principal') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Telefones adicionais (JSON)</span>
    <input type="text" name="telefones_adicionais"
           value="{{ is_array($v('telefones_adicionais')) ? json_encode($v('telefones_adicionais')) : $v('telefones_adicionais') }}"
           placeholder='["(31) 99999-9999","(31) 98888-8888"]'
           class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm">
    @error('telefones_adicionais') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">E-mail</span>
    <input type="email" name="email" value="{{ $v('email') }}"
           class="mt-1 w-full rounded-md border-gray-300">
    @error('email') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>
</div>

{{-- ================= ÓBITO & OBSERVAÇÕES ================= --}}
<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Óbito & Observações</h2>

  {{-- Checkbox ÓBITO + hidden para enviar 0 quando desmarcado --}}
  <label class="inline-flex items-center gap-2">
    <input type="hidden" name="obito" value="0">
    <input id="chkObito" type="checkbox" name="obito" value="1" class="rounded border-gray-300"
           {{ $isObito ? 'checked' : '' }}>
    <span class="text-sm font-semibold">ÓBITO</span>
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Data do óbito</span>
    <input id="dtObito" type="date" name="data_obito"
           value="{{ $v('data_obito') ? \Illuminate\Support\Str::of($v('data_obito'))->substr(0,10) : '' }}"
           class="mt-1 w-full rounded-md border-gray-300"
           {{ $isObito ? '' : 'disabled' }}>
    @error('data_obito') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">Observações</span>
    <textarea name="observacoes" rows="4" class="mt-1 w-full rounded-md border-gray-300 force-upper">{{ $v('observacoes') }}</textarea>
    @error('observacoes') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>
</div>

{{-- Scripts: toggle de Estrangeiro e Óbito --}}
<script>
  (function () {
    const chkEstr = document.getElementById('chkEstrangeiro');
    const selPais = document.getElementById('selNacionalidade');
    const chkObito = document.getElementById('chkObito');
    const dtObito  = document.getElementById('dtObito');

    function applyEstrangeiro() {
      if (!chkEstr || !selPais) return;
      if (chkEstr.checked) {
        selPais.disabled = false;
      } else {
        selPais.disabled = true;
        selPais.value = 'BRASIL';
      }
    }

    function applyObito() {
      if (!chkObito || !dtObito) return;
      dtObito.disabled = !chkObito.checked;
      if (dtObito.disabled) dtObito.value = '';
    }

    chkEstr?.addEventListener('change', applyEstrangeiro);
    chkObito?.addEventListener('change', applyObito);

    applyEstrangeiro();
    applyObito();
  })();
</script>
