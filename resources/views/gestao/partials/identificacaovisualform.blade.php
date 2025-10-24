{{-- resources/views/gestao/partials/identificacaovisualform.blade.php --}}
{{-- PARCIAL ÚNICO • IDENTIFICAÇÃO (create/edit) --}}

@php
  use Illuminate\Support\Facades\Route;

  // ========= CONTEXTO =========
  $registro = $registro ?? null;
  $mode     = $mode     ?? null;

  // ID da pessoa (quando vier por query, mantém)
  $id   = $registro->id ?? request('id');

  // Dados básicos vindos de dados_pessoais (continuidade do wizard)
  $cadpen     = old('cadpen', $registro->cadpen ?? '');
  $nome       = $registro->nome_completo ?? ($registro->nome ?? '');
  $mae        = $registro->filiacao_mae ?? ($registro->mae ?? '');
  $pai        = $registro->filiacao_pai ?? ($registro->pai ?? '');
  $nasc       = $registro->data_nascimento ?? '';
  // Local de nascimento (tenta compor; usa o que existir)
  $ln_cidade  = $registro->naturalidade_cidade ?? ($registro->cidade_nascimento ?? '');
  $ln_uf      = $registro->naturalidade_uf ?? ($registro->uf_nascimento ?? '');
  $naturalidade = trim(($ln_cidade ? $ln_cidade : '').($ln_uf ? ' / '.$ln_uf : ''));

  // Rota segura para PULAR
  if (Route::has('gestao.ficha.individuo.create') && $id) {
    $rotaPular = route('gestao.ficha.individuo.create', $id);
  } elseif (Route::has('gestao.identificacao.visual.search')) {
    $rotaPular = route('gestao.identificacao.visual.search');
  } else {
    $rotaPular = route('inicio');
  }

  // ========= CATÁLOGOS =========
  $simNao = ['SIM','NÃO'];

  // helper para ordenar e garantir "OUTROS" por último
  $ordenaComOutrosNoFim = function(array $arr): array {
    $outros = [];
    $norm   = [];
    foreach ($arr as $v) {
      if (mb_strtoupper($v,'UTF-8') === 'OUTROS' || mb_strtoupper($v,'UTF-8') === 'OUTRO LOCAL') {
        $outros[] = $v;
      } else {
        $norm[] = $v;
      }
    }
    sort($norm, SORT_LOCALE_STRING);
    return array_merge($norm, $outros ?: []);
  };

  $etnias = $ordenaComOutrosNoFim(['AMARELA','BRANCA','INDÍGENA','PARDA','PREFERE NÃO INFORMAR','PRETA','OUTROS']);

  $coresOlhos = $ordenaComOutrosNoFim(['AZUIS','BICOLOR','CASTANHOS','CINZAS','MEL','PRETOS','VERDES','OUTROS']);
  $tiposOlhos = $ordenaComOutrosNoFim(['AMÊNDOA','CAÍDOS','CÍLIOS LONGOS','ESTRABISMO','GRANDES','OLHEIRAS','ORIENTAIS','PÁLPEBRAS CAÍDAS','PEQUENOS','PROFUNDOS','SALIENTES','OUTROS']);

  $coresCabelos = $ordenaComOutrosNoFim(['BRANCOS','CASTANHOS','GRISALHOS','LOUROS','PRETOS','RUIVOS','OUTROS']);
  $tiposCabelos = $ordenaComOutrosNoFim(['CACHEADO','CALVO(A)','CRESPO','LISO','ONDULADO','OUTROS']);

  $tiposOrelhas = $ordenaComOutrosNoFim(['ASSIMÉTRICAS','GRANDES','LÓBULOS GRANDES','LÓBULOS PEQUENOS','MÉDIAS','PEQUENAS','PIERCING','PROEMINENTES (DE ABANO)','OUTROS']);
  $tiposNariz = $ordenaComOutrosNoFim(['ADUNCO','ARREBITADO','FINO','GRANDE','LARGO','MÉDIO','PEQUENO','SEPTO DESVIADO','TORTO','OUTROS']);
  $tiposBocaLabios = $ordenaComOutrosNoFim(['AUSÊNCIA DENTÁRIA','BOCA GRANDE','BOCA MÉDIA','BOCA PEQUENA','DENTES DESALINHADOS','LÁBIO INFERIOR GROSSO','LÁBIO SUPERIOR FINO','LÁBIOS FINOS','LÁBIOS GROSSOS','OUTROS']);
  $formatosRosto = $ordenaComOutrosNoFim(['CORAÇÃO','LOSANGO','OVAL','QUADRADO','RETANGULAR','REDONDO','TRIANGULAR','OUTROS']);
  $tiposSobrancelhas = $ordenaComOutrosNoFim(['ARQUEADAS','FALHAS','FINAS','GROSSAS','RETAS','UNIDAS','OUTROS']);
  $tiposBarbaBigode = $ordenaComOutrosNoFim(['BARBA CHEIA','BARBA RALA','BIGODE CHEIO','BIGODE FINO','CAVANHAQUE','COSTELETAS','SEM BARBA/BIGODE','OUTROS']);

  $locaisCorpo = $ordenaComOutrosNoFim([
    'ABDOMEN','ANTEBRAÇO DIR.','ANTEBRAÇO ESQ.',
    'BOCA / LÁBIOS','BRAÇO DIR.','BRAÇO ESQ.',
    'COSTELAS DIR.','COSTELAS ESQ.','COXA DIR.','COXA ESQ.',
    'DEDOS MÃO DIR.','DEDOS MÃO ESQ.','DORSO (COSTAS)',
    'FACE — FRONTAL','FACE — LADO DIREITO','FACE — LADO ESQUERDO',
    'GENITÁLIA (DESCR.)',
    'MAÇÃ DO ROSTO DIR.','MAÇÃ DO ROSTO ESQ.','MANDÍBULA','MÃO DIR.','MÃO ESQ.',
    'NARIZ','NUCA',
    'OMBRO DIR.','OMBRO ESQ.','ORELHA DIREITA','ORELHA ESQUERDA',
    'PESCOÇO — DIR.','PESCOÇO — ESQ.','PÉ DIR.','PÉ ESQ.','PERNA DIR.','PERNA ESQ.',
    'QUEIXO','SOBRANCELHAS',
    'TORNOZELO DIR.','TORNOZELO ESQ.','TÓRAX / PEITO',
    'OUTRO LOCAL',

  ]);

  $tiposMarca = $ordenaComOutrosNoFim(['AMPUTAÇÃO PARCIAL','CALO','CICATRIZ','CORTES','FERIMENTO ANTIGO','MANCHA','QUEIMADURA','SINAL','OUTROS']);
  $tiposDeficiencia = $ordenaComOutrosNoFim(['AUDITIVA','FALA','FÍSICA (LOCOMOÇÃO)','FÍSICA (MEMBROS)','INTELECTUAL','TRANSTORNOS DO ESPECTRO AUTISTA','VISUAL','OUTROS']);

  // Tatuagens — lista
  $tatuagensLista = $ordenaComOutrosNoFim([
'ABELHA','ÂNCORA','ALGEMA','AKUMA (STREET FIGHTER)','ÁGUIA','ANJO','ANJO DA MORTE','ANJOS','APÓSTOLO','ARARA','ARCANJO','ARCO-ÍRIS','ARLEQUINA','AVIÃO','AVIÃO DE PAPEL','ÁRVORE','ÁRVORE DA VIDA',
'BARCO','BATMAN','BEIJA-FLOR','BÍBLIA','BLACKWORK','BOB MARLEY','BORBOLETA','BÚSSOLA',
'CACHORRO','CADEADO','CÁLICE','CARAVELA','CARPA','CARTA DE BARALHO','CAVALO','CAVEIRA','CHAMA','CHARLES CHAPLIN','CHAVE','CHICO BENTO','CITAÇÃO','COBRA','COELHO','COQUEIRO','CORACÃO','CORAÇÃO ALADO','CORAÇÃO COM FLECHA','CORAÇÃO COM NOME','CORAÇÃO COM PUNHAL','COROA','CORRENTE','CORUJA','CRUZ','CRUZ CELTA','CRUZ DE CRISTO','CRUZ DE MALTA','CRUZ MALTESA',
'DADO','DATA (ROMANOS)','DATA','DATA DE NASCIMENTO','DIAMANTE','DRAGÃO','DRAGÃO ORIENTAL','DUENDE',
'ECLIPSE','ELVIS','ESFINGE','ESCORPIÃO','ESTRELA','ESTRELA DE DAVI',
'FADA','FAMÍLIA ADDAMS','FANTASMA','FAROL','FÊNIX','FLECHA','FLOR','FLOR DE LÓTUS','FOLHA','FOLHA DE MACONHA','FOGO','FONE DE OUVIDO',
'GÁRGULA','GATO','GIRASSOL','GOLFINHO','GOTA','GUEIXA',
'HOMEM-ARANHA','HOMEM DE FERRO','HOMEM',
'ILHA','INICIAIS','INDÍGENA',
'JOANINHA',
'KANJI (JAPONÊS)','KATRINA (CAVEIRA MEXICANA)',
'LAGARTO','LEÃO','LETRA','LETRAS ÁRABES','LIVRO','LOBO','LOURENÇO','LUA',
'MANDALA FLORAL','MÃO DE FÁTIMA (HAMSÁ)','MARIA','MÁSCARA','MICROFONE',
'NOME PRÓPRIO','NOSSA SENHORA',
'OSSOS',
'PALAVRA-CHAVE','PALHAÇO','PALHAÇO MALIGNO','PALHAÇO TRISTE','PANTERA','PARTITURA / NOTAS MUSICAIS','PEIXE','PIERROT','POMBA DA PAZ',
'RELÓGIO','ROSA','ROSAS',
'SAGRADO CORAÇÃO','SAGRADA FAMÍLIA','SÃO JORGE','SIFRÃO ($)','SOL',
'TAÇA','TERÇO','TIGRE','TREVO','TRIBAL','TRIBAL CELTA','TRIBAL GEOMÉTRICO','TRIBAL MAORI','TRIBAL POLINÉSIO','TUBARÃO',
'OUTROS',

  ]);

  // ========= VALORES EXISTENTES =========
  $etniaSel = old('etnia', $registro->etnia ?? '');

@endphp

<div class="space-y-6">

  {{-- ====== CABEÇALHO ====== --}}
  <div class="bg-white border rounded p-4 space-y-1">
    <div class="text-base md:text-lg"><span class="font-semibold text-gray-700">CadPen:</span> <span class="font-mono">{{ $cadpen }}</span></div>
    <div class="text-base md:text-lg"><span class="font-semibold text-gray-700">Nome:</span> {{ $nome }}</div>
    <div class="text-base md:text-lg"><span class="font-semibold text-gray-700">Nascimento:</span> {{ $nasc }} @if(!empty($naturalidade)) • {{ $naturalidade }} @endif</div>
    <div class="text-base md:text-lg"><span class="font-semibold text-gray-700">Filiação (Mãe):</span> {{ $mae }}</div>
    <div class="text-base md:text-lg"><span class="font-semibold text-gray-700">Filiação (Pai):</span> {{ $pai }}</div>
  </div>

  {{-- garante que o CADPEN vai no POST --}}
  <input type="hidden" name="cadpen" value="{{ $cadpen }}">

  {{-- ====== ETNIA ====== --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <label class="block">
      <span class="text-sm font-semibold">ETNIA</span>
      <select name="etnia" class="mt-1 w-full rounded-md border-gray-300 uppercase">
        <option value="">— SELECIONE A OPÇÃO —</option>
        @foreach ($etnias as $opt)
          <option value="{{ $opt }}" @selected($etniaSel === $opt)>{{ $opt }}</option>
        @endforeach
      </select>
    </label>
  </div>

  {{-- ====== (várias seções — igual ao anterior) ====== --}}
  {{-- -------------- OLHOS -------------- --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">CABEÇA / ROSTO — OLHOS</h3>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
      <div>
        <span class="text-xs font-semibold">COR</span>
        <div class="mt-1 flex gap-2">
          <select id="olhos_cor" class="w-full rounded-md border-gray-300 uppercase">
            <option value="">— SELECIONE A OPÇÃO —</option>
            @foreach ($coresOlhos as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
          </select>
          <button type="button" id="btn_add_olhos_cor" class="px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">INSERIR</button>
        </div>
      </div>
      <div>
        <span class="text-xs font-semibold">TIPO</span>
        <div class="mt-1 flex gap-2">
          <select id="olhos_tipo" class="w-full rounded-md border-gray-300 uppercase">
            <option value="">— SELECIONE A OPÇÃO —</option>
            @foreach ($tiposOlhos as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
          </select>
          <button type="button" id="btn_add_olhos_tipo" class="px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">INSERIR</button>
        </div>
      </div>
      <div>
        <span class="text-xs font-semibold">ÓCULOS?</span>
        <div class="mt-1 flex gap-2">
          <select id="olhos_oculos" class="w-full rounded-md border-gray-300 uppercase">
            <option value="">— SELECIONE A OPÇÃO —</option>
            @foreach ($simNao as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
          </select>
          <button type="button" id="btn_add_olhos_oculos" class="px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">INSERIR</button>
        </div>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">OUTROS / DETALHE</span>
        <div class="mt-1 flex gap-2">
          <input type="text" id="olhos_det" class="w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
          <button type="button" id="btn_add_olhos_det" class="px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">INSERIR</button>
        </div>
      </div>
    </div>
    <div id="list_olhos" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- -------------- SOBRANCELHAS -------------- --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">CABEÇA / ROSTO — SOBRANCELHAS</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">TIPO</span>
        <select id="sobr_tipo" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($tiposSobrancelhas as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">OUTROS / DETALHE</span>
        <input type="text" id="sobr_det" class="mt-1 w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
      </div>
    </div>
    <button type="button" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" id="btn_add_sobr">INSERIR</button>
    <div id="list_sobr" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- -------------- NARIZ -------------- --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">CABEÇA / ROSTO — NARIZ</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">TIPO</span>
        <select id="nariz_tipo" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($tiposNariz as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">OUTROS / DETALHE</span>
        <input type="text" id="nariz_det" class="mt-1 w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
      </div>
    </div>
    <button type="button" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" id="btn_add_nariz">INSERIR</button>
    <div id="list_nariz" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- -------------- BOCA / LÁBIOS -------------- --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">CABEÇA / ROSTO — BOCA / LÁBIOS</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">TIPO</span>
        <select id="boca_tipo" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($tiposBocaLabios as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">OUTROS / DETALHE</span>
        <input type="text" id="boca_det" class="mt-1 w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
      </div>
    </div>
    <button type="button" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" id="btn_add_boca">INSERIR</button>
    <div id="list_boca" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- -------------- ORELHAS -------------- --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">CABEÇA / ROSTO — ORELHAS</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">TIPO</span>
        <select id="orelhas_tipo" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($tiposOrelhas as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">OUTROS / DETALHE</span>
        <input type="text" id="orelhas_det" class="mt-1 w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
      </div>
    </div>
    <button type="button" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" id="btn_add_orelhas">INSERIR</button>
    <div id="list_orelhas" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- -------------- CABELO / FACE -------------- --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">CABEÇA / ROSTO — CABELO / FACE</h3>
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <div>
        <span class="text-xs font-semibold">COR</span>
        <div class="mt-1 flex gap-2">
          <select id="cabelo_cor" class="w-full rounded-md border-gray-300 uppercase">
            <option value="">— SELECIONE A OPÇÃO —</option>
            @foreach ($coresCabelos as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
          </select>
          <button type="button" id="btn_add_cabelo_cor" class="px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">INSERIR</button>
        </div>
      </div>
      <div>
        <span class="text-xs font-semibold">TIPO</span>
        <div class="mt-1 flex gap-2">
          <select id="cabelo_tipo" class="w-full rounded-md border-gray-300 uppercase">
            <option value="">— SELECIONE A OPÇÃO —</option>
            @foreach ($tiposCabelos as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
          </select>
          <button type="button" id="btn_add_cabelo_tipo" class="px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">INSERIR</button>
        </div>
      </div>
      <div>
        <span class="text-xs font-semibold">BARBA/BIGODE</span>
        <div class="mt-1 flex gap-2">
          <select id="barba_bigode" class="w-full rounded-md border-gray-300 uppercase">
            <option value="">— SELECIONE A OPÇÃO —</option>
            @foreach ($tiposBarbaBigode as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
          </select>
          <button type="button" id="btn_add_cabelo_barba" class="px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">INSERIR</button>
        </div>
      </div>
      <div class="md:col-span-3">
        <span class="text-xs font-semibold">OUTROS / DETALHE</span>
        <div class="mt-1 flex gap-2">
          <input type="text" id="cabelo_det" class="w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
          <button type="button" id="btn_add_cabelo_det" class="px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">INSERIR</button>
        </div>
      </div>
    </div>
    <div id="list_cabelo" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- ====== TATUAGENS ====== --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">TATUAGENS</h3>
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">LOCAL DO CORPO</span>
        <select id="tat_local" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($locaisCorpo as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">TEMA</span>
        <select id="tat_tema" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($tatuagensLista as $tema) <option value="{{ $tema }}">{{ $tema }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">OUTROS / DETALHE</span>
        <input type="text" id="tat_det" class="mt-1 w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
      </div>
    </div>
    <button type="button" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" id="btn_add_tat">INSERIR</button>
    <div id="list_tat" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- ====== MARCAS / CICATRIZES ====== --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">MARCAS / CICATRIZES</h3>
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">LOCAL</span>
        <select id="marc_local" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($locaisCorpo as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">TIPO</span>
        <select id="marc_tipo" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($tiposMarca as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">DETALHE (OPCIONAL)</span>
        <input type="text" id="marc_det" class="mt-1 w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
      </div>
    </div>
    <button type="button" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" id="btn_add_marc">INSERIR</button>
    <div id="list_marc" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- ====== DEFICIÊNCIAS ====== --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">DEFICIÊNCIAS</h3>
    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">TIPO</span>
        <select id="def_tipo" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($tiposDeficiencia as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">LOCAL (OPCIONAL)</span>
        <select id="def_local" class="mt-1 w-full rounded-md border-gray-300 uppercase">
          <option value="">— SELECIONE A OPÇÃO —</option>
          @foreach ($locaisCorpo as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <span class="text-xs font-semibold">DETALHE (OPCIONAL)</span>
        <input type="text" id="def_det" class="mt-1 w-full rounded-md border-gray-300 uppercase" placeholder="(opcional)">
      </div>
    </div>
    <button type="button" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700" id="btn_add_def">INSERIR</button>
    <div id="list_def" class="divide-y divide-gray-200 rounded border"></div>
  </div>

  {{-- ====== OBSERVAÇÕES ====== --}}
  <div>
    <label class="block">
      <span class="text-sm font-semibold">OBSERVAÇÕES DE IDENTIFICAÇÃO</span>
      <textarea name="observacoes" rows="3" class="mt-1 w-full rounded-md border-gray-300 uppercase" oninput="this.value=this.value.toUpperCase()">{{ old('observacoes', $registro->observacoes ?? '') }}</textarea>
    </label>
  </div>

  {{-- ====== FOTOGRAFIAS (3x4) ====== --}}
  <div class="space-y-2">
    <h3 class="text-sm font-semibold">FOTOGRAFIAS (3x4)</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      @php
        $fotoFields = [
          ['id' => 'foto_le',      'label' => 'LADO ESQUERDO', 'prev' => 'prev_le',      'url' => $registro->foto_le_url      ?? null],
          ['id' => 'foto_frontal', 'label' => 'FRONTAL',       'prev' => 'prev_frontal', 'url' => $registro->foto_frontal_url ?? null],
          ['id' => 'foto_ld',      'label' => 'LADO DIREITO',  'prev' => 'prev_ld',      'url' => $registro->foto_ld_url      ?? null],
        ];
      @endphp
      @foreach($fotoFields as $f)
        <label class="block">
          <span class="text-xs font-semibold">{{ $f['label'] }}</span>
          <div class="mt-1 w-full rounded border border-gray-300 p-2">
            <div class="w-full rounded bg-gray-50 flex items-center justify-center overflow-hidden" style="aspect-ratio: 3 / 4;">
              <img id="{{ $f['prev'] }}" alt="PRÉVIA {{ $f['label'] }}" class="max-w-full max-h-full {{ $f['url'] ? '' : 'hidden' }}" src="{{ $f['url'] ?? '' }}">
            </div>
            <input type="file" name="{{ $f['id'] }}" accept="image/*" class="mt-2 w-full rounded border-gray-300" id="{{ $f['id'] }}">
            @if(!empty($f['url']))
              <a href="{{ $f['url'] }}" target="_blank" class="text-blue-700 underline text-xs mt-2 inline-block">VER ATUAL</a>
            @endif
          </div>
        </label>
      @endforeach
    </div>
  </div>

  {{-- ====== BIOMETRIA — IMPRESSÃO DIGITAL (10 DEDOS) ====== --}}
  <div class="space-y-3">
    <h3 class="text-sm font-semibold">BIOMETRIA — IMPRESSÃO DIGITAL (10 DEDOS)</h3>

    <div class="text-sm">
      <span class="font-semibold">Dedo atual:</span>
      <span id="bio_dedo_atual" class="font-mono"></span>
      <div class="mt-2 flex flex-wrap gap-2">
        <button type="button" id="bio_btn_capturar" class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Capturar</button>
        <button type="button" id="bio_btn_confirmar" class="px-3 py-2 rounded bg-green-600 text-white hover:bg-green-700" disabled>Confirmar</button>
        <button type="button" id="bio_btn_repetir" class="px-3 py-2 rounded bg-yellow-600 text-white hover:bg-yellow-700" disabled>Repetir</button>
        <button type="button" id="bio_btn_cancelar" class="px-3 py-2 rounded bg-red-600 text-white hover:bg-red-700" disabled>Cancelar dedo</button>
      </div>
      <input type="file" id="bio_file" accept="image/*" class="hidden">
      <p class="text-xs text-gray-500 mt-1">Para testes: selecione uma imagem para simular a leitura.</p>
    </div>

    @php
      $dedos = [
        'MÃO ESQ. • MINDINHO','MÃO ESQ. • ANELAR','MÃO ESQ. • MÉDIO','MÃO ESQ. • INDICADOR','MÃO ESQ. • POLEGAR',
        'MÃO DIR. • POLEGAR','MÃO DIR. • INDICADOR','MÃO DIR. • MÉDIO','MÃO DIR. • ANELAR','MÃO DIR. • MINDINHO',
      ];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3" id="bio_grid">
      @foreach($dedos as $i => $label)
        <div class="border rounded p-2" data-idx="{{ $i }}">
          <div class="text-xs font-semibold mb-1">{{ ($i+1).'. '.$label }}</div>
          <div class="w-full rounded bg-gray-50 flex items-center justify-center overflow-hidden border" style="aspect-ratio: 3 / 4;">
            <img id="bio_prev_{{ $i }}" class="max-w-full max-h-full hidden" alt="Prévia dedo {{ $i+1 }}">
          </div>
          <div class="mt-1 text-xs text-gray-600" id="bio_status_{{ $i }}">Aguardando captura…</div>
        </div>
      @endforeach
    </div>

    {{-- Hidden JSON com os 10 dedos --}}
    <input type="hidden" name="biometrias" id="biometrias" value="{{ json_encode($registro->biometria_json ?? [], JSON_UNESCAPED_UNICODE) }}">
  </div>

  {{-- ====== HIDDEN MASTER: SINAIS COMPOSTOS ====== --}}
  <input type="hidden" name="sinais_compostos" id="sinais_compostos" value="{{ json_encode($registro->sinais_compostos ?? [], JSON_UNESCAPED_UNICODE) }}">

  {{-- ====== BOTÕES ====== --}}
  <div class="pt-2 border-t border-gray-200 flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <a href="{{ route('inicio') }}" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">CANCELAR</a>
      <a href="{{ $rotaPular }}" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">PULAR</a>
    </div>
    <div class="flex items-center gap-3">
      @if(Route::has('gestao.identificacao.visual.search'))
        <a href="{{ route('gestao.identificacao.visual.search') }}" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">VOLTAR À BUSCA</a>
      @endif
      {{-- Agora é SUBMIT de verdade --}}
      <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">
        {{ $mode === 'edit' ? 'ATUALIZAR E AVANÇAR' : 'SALVAR E AVANÇAR' }}
      </button>
    </div>
  </div>
</div>

{{-- ========== SCRIPTS ========== --}}
<script>
document.addEventListener('DOMContentLoaded', function(){
  // ===== Helpers =====
  const uc = s => (s||'').toUpperCase();
  const qs = sel => document.querySelector(sel);
  const qsa = sel => Array.from(document.querySelectorAll(sel));
  function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }

  // ===== Fotos 3x4 — prévia =====
  function bindPreview(inputId, imgId){
    const inp = document.getElementById(inputId);
    const img = document.getElementById(imgId);
    if(!inp || !img) return;
    inp.addEventListener('change', () => {
      const f = inp.files && inp.files[0];
      if (f) {
        img.src = URL.createObjectURL(f);
        img.classList.remove('hidden');
      } else {
        img.src = '';
        img.classList.add('hidden');
      }
    });
  }
  bindPreview('foto_frontal','prev_frontal');
  bindPreview('foto_ld','prev_ld');
  bindPreview('foto_le','prev_le');

  // Carrega listas iniciais (PHP -> JS)
  let itens = [];
  try { itens = JSON.parse(qs('#sinais_compostos')?.value || '[]') || []; if(!Array.isArray(itens)) itens = []; } catch(e){ itens = []; }

  // ===== Lista mestre (sinais_compostos) =====
  const hiddenSC = qs('#sinais_compostos');
  function syncSC(){ hiddenSC.value = JSON.stringify(itens); }

  function addItem(local, tipo, desc){
    itens.push({ local: uc(local||''), tipo: uc(tipo||''), descricao: uc(desc||'') });
    syncSC();
  }

  function renderList(containerId, filtroLocal){
    const cont = qs(containerId);
    if (!cont) return;
    const list = itens.filter(x => x.local === uc(filtroLocal));
    cont.innerHTML = '';
    if (!list.length) {
      const empty = document.createElement('div');
      empty.className = 'p-3 text-center text-gray-600';
      empty.textContent = 'Nenhum item.';
      cont.appendChild(empty);
      return;
    }
    list.forEach((d, iLocal) => {
      const idxGlobal = itens.findIndex((it, ix) => it === list[iLocal]);
      const row = document.createElement('div');
      row.className = 'p-3 flex items-center justify-between';
      row.innerHTML = `
        <div class="min-w-0">
          <div class="text-sm font-medium">${escapeHtml(d.local)} — ${escapeHtml(d.tipo)}</div>
          <div class="text-sm text-gray-700 break-words">${escapeHtml(d.descricao || '')}</div>
        </div>
        <div class="shrink-0">
          <button type="button" data-idx="${idxGlobal}" class="px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700 sc-rem">Remover</button>
        </div>
      `;
      cont.appendChild(row);
    });
  }

  function renderAll(){
    renderList('#list_olhos','OLHOS');
    renderList('#list_sobr','SOBRANCELHAS');
    renderList('#list_nariz','NARIZ');
    renderList('#list_boca','BOCA / LÁBIOS');
    renderList('#list_orelhas','ORELHAS');
    renderList('#list_cabelo','CABELO / FACE');
    renderList('#list_tat','TATUAGENS');
    renderList('#list_marc','MARCAS / CICATRIZES');
    renderList('#list_def','DEFICIÊNCIAS');
  }
  renderAll();

  // Remoção
  qsa('.divide-y').forEach(box => {
    box.addEventListener('click', (e)=>{
      const btn = e.target.closest('.sc-rem');
      if (!btn) return;
      const idx = parseInt(btn.getAttribute('data-idx'),10);
      if (!Number.isNaN(idx) && itens[idx]) {
        itens.splice(idx, 1);
        syncSC();
        renderAll();
      }
    });
  });

  // ===== Inserções (mesmo código de antes)… =====
  qs('#btn_add_olhos_cor')?.addEventListener('click', ()=>{ const v = uc(qs('#olhos_cor')?.value || ''); if (v) { addItem('OLHOS','COR', v); qs('#olhos_cor').value=''; renderAll(); }});
  qs('#btn_add_olhos_tipo')?.addEventListener('click', ()=>{ const v = uc(qs('#olhos_tipo')?.value || ''); if (v) { addItem('OLHOS','TIPO', v); qs('#olhos_tipo').value=''; renderAll(); }});
  qs('#btn_add_olhos_oculos')?.addEventListener('click', ()=>{ const v = uc(qs('#olhos_oculos')?.value || ''); if (v) { addItem('OLHOS','ÓCULOS', v); qs('#olhos_oculos').value=''; renderAll(); }});
  qs('#btn_add_olhos_det')?.addEventListener('click', ()=>{ const v = uc(qs('#olhos_det')?.value || ''); if (v) { addItem('OLHOS','DETALHE', v); qs('#olhos_det').value=''; renderAll(); }});

  qs('#btn_add_sobr')?.addEventListener('click', ()=>{ const t = uc(qs('#sobr_tipo')?.value || ''), d = uc(qs('#sobr_det')?.value || ''); if (t) { addItem('SOBRANCELHAS','TIPO', d ? `${t} — ${d}` : t); qs('#sobr_tipo').value=''; qs('#sobr_det').value=''; renderAll(); }});
  qs('#btn_add_nariz')?.addEventListener('click', ()=>{ const t = uc(qs('#nariz_tipo')?.value || ''), d = uc(qs('#nariz_det')?.value || ''); if (t) { addItem('NARIZ','TIPO', d ? `${t} — ${d}` : t); qs('#nariz_tipo').value=''; qs('#nariz_det').value=''; renderAll(); }});
  qs('#btn_add_boca')?.addEventListener('click', ()=>{ const t = uc(qs('#boca_tipo')?.value || ''), d = uc(qs('#boca_det')?.value || ''); if (t) { addItem('BOCA / LÁBIOS','TIPO', d ? `${t} — ${d}` : t); qs('#boca_tipo').value=''; qs('#boca_det').value=''; renderAll(); }});
  qs('#btn_add_orelhas')?.addEventListener('click', ()=>{ const t = uc(qs('#orelhas_tipo')?.value || ''), d = uc(qs('#orelhas_det')?.value || ''); if (t) { addItem('ORELHAS','TIPO', d ? `${t} — ${d}` : t); qs('#orelhas_tipo').value=''; qs('#orelhas_det').value=''; renderAll(); }});
  qs('#btn_add_cabelo_cor')?.addEventListener('click', ()=>{ const c = uc(qs('#cabelo_cor')?.value || ''); if (c) { addItem('CABELO / FACE','COR', c); qs('#cabelo_cor').value=''; renderAll(); }});
  qs('#btn_add_cabelo_tipo')?.addEventListener('click', ()=>{ const t = uc(qs('#cabelo_tipo')?.value || ''); if (t) { addItem('CABELO / FACE','TIPO', t); qs('#cabelo_tipo').value=''; renderAll(); }});
  qs('#btn_add_cabelo_barba')?.addEventListener('click', ()=>{ const b = uc(qs('#barba_bigode')?.value || ''); if (b) { addItem('CABELO / FACE','BARBA/BIGODE', b); qs('#barba_bigode').value=''; renderAll(); }});
  qs('#btn_add_cabelo_det')?.addEventListener('click', ()=>{ const d = uc(qs('#cabelo_det')?.value || ''); if (d) { addItem('CABELO / FACE','DETALHE', d); qs('#cabelo_det').value=''; renderAll(); }});
  qs('#btn_add_tat')?.addEventListener('click', ()=>{ const loc = uc(qs('#tat_local')?.value || ''), tema = uc(qs('#tat_tema')?.value || ''), det = uc(qs('#tat_det')?.value || ''); if (loc && tema) { addItem('TATUAGENS', loc, det ? `${tema} — ${det}` : tema); qs('#tat_local').value=''; qs('#tat_tema').value=''; qs('#tat_det').value=''; renderAll(); }});
  qs('#btn_add_marc')?.addEventListener('click', ()=>{ const loc = uc(qs('#marc_local')?.value || ''), tipo = uc(qs('#marc_tipo')?.value || ''), det = uc(qs('#marc_det')?.value || ''); if (loc && tipo) { addItem('MARCAS / CICATRIZES', loc, det ? `${tipo} — ${det}` : tipo); qs('#marc_local').value=''; qs('#marc_tipo').value=''; qs('#marc_det').value=''; renderAll(); }});
  qs('#btn_add_def')?.addEventListener('click', ()=>{ const tipo = uc(qs('#def_tipo')?.value || ''), loc  = uc(qs('#def_local')?.value || ''), det  = uc(qs('#def_det')?.value || ''); if (tipo) { addItem('DEFICIÊNCIAS', tipo, [loc,det].filter(Boolean).join(' — ')); qs('#def_tipo').value=''; qs('#def_local').value=''; qs('#def_det').value=''; renderAll(); }});

  // ===== Biometria =====
  const dedos = [
    'MÃO ESQ. • MINDINHO','MÃO ESQ. • ANELAR','MÃO ESQ. • MÉDIO','MÃO ESQ. • INDICADOR','MÃO ESQ. • POLEGAR',
    'MÃO DIR. • POLEGAR','MÃO DIR. • INDICADOR','MÃO DIR. • MÉDIO','MÃO DIR. • ANELAR','MÃO DIR. • MINDINHO',
  ];
  const bioHidden = qs('#biometrias');
  let bio = []; try { bio = JSON.parse(bioHidden.value || '[]') || []; if(!Array.isArray(bio)) bio = []; } catch(e){ bio = []; }
  if (bio.length !== 10) { bio = dedos.map((label) => ({ dedo: label, imagem: null, status: 'vazio' })); }
  function syncBio(){ bioHidden.value = JSON.stringify(bio); }
  function setStatus(i, text){ const el = qs('#bio_status_'+i); if (el) el.textContent = text; }
  function setPrev(i, url){ const img = qs('#bio_prev_'+i); if (img){ if (url){ img.src = url; img.classList.remove('hidden'); } else { img.src=''; img.classList.add('hidden'); } } }
  bio.forEach((d, i) => { if (d.imagem) setPrev(i, d.imagem); setStatus(i, d.status === 'confirmado' ? 'Confirmado' : (d.status === 'capturado' ? 'Capturado — aguarde confirmação.' : 'Aguardando captura…')); });
  let atual = Math.min(bio.findIndex(x => x.status !== 'confirmado'), 9); if (atual < 0) atual = 0;
  function atualizarDedoAtual(){ const st = bio[atual]?.status || 'vazio'; (qs('#bio_dedo_atual').textContent = dedos[atual] || 'Concluído'); qs('#bio_btn_confirmar').disabled = (st !== 'capturado'); qs('#bio_btn_repetir').disabled = (st === 'vazio'); qs('#bio_btn_cancelar').disabled = (st === 'vazio'); }
  atualizarDedoAtual();
  const bioFile = qs('#bio_file');
  qs('#bio_btn_capturar')?.addEventListener('click', ()=>{ if (atual < 10) bioFile.click(); });
  bioFile?.addEventListener('change', ()=>{ const f = bioFile.files && bioFile.files[0]; if (!f) return; const reader = new FileReader(); reader.onload = e => { const url = e.target.result; bio[atual].imagem = url; bio[atual].status = 'capturado'; setPrev(atual, url); setStatus(atual, 'Capturado — aguarde confirmação.'); syncBio(); atualizarDedoAtual(); }; reader.readAsDataURL(f); });
  qs('#bio_btn_confirmar')?.addEventListener('click', ()=>{ if (atual >= 10) return; if (bio[atual].status === 'capturado') { bio[atual].status = 'confirmado'; setStatus(atual,'Confirmado'); syncBio(); atual = Math.min(atual+1, 9); atualizarDedoAtual(); }});
  qs('#bio_btn_repetir')?.addEventListener('click', ()=>{ if (atual >= 10) return; bio[atual].status='vazio'; bio[atual].imagem=null; setPrev(atual,null); setStatus(atual,'Aguardando captura…'); syncBio(); atualizarDedoAtual(); });
  qs('#bio_btn_cancelar')?.addEventListener('click', ()=>{ if (atual >= 10) return; bio[atual].status='vazio'; bio[atual].imagem=null; setPrev(atual,null); setStatus(atual,'Cancelado. (sem captura)'); syncBio(); atual = Math.min(atual+1, 9); atualizarDedoAtual(); });

  // ===== Submit robusto: sincroniza JSONs SEM depender de clique específico
  const form = document.querySelector('form[enctype="multipart/form-data"]');
  form?.addEventListener('submit', ()=>{
    syncSC();
    syncBio();
  });
});
</script>
