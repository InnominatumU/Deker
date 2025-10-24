{{-- resources/views/gestao/fichaindividuocreate.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">FICHA DO INDIVÍDUO</h1>
  </x-slot>

  @php
    $pessoalGrupos = $pessoalGrupos ?? [];
    $pessoalExtras = $pessoalExtras ?? [];
    $bio = is_array($bio ?? null) ? $bio : [];

    // Logo (brasão) automático
    $logoUrl = $orgBrasaoUrl ?? null;
    if (!$logoUrl) {
      foreach (['png','jpg','jpeg'] as $ext) {
        $p = public_path("images/brasao.$ext");
        if (file_exists($p)) { $logoUrl = asset("images/brasao.$ext"); break; }
      }
    }

    // Datas e campos principais
    $dnBR = !empty($pessoa->data_nascimento) ? \Carbon\Carbon::parse($pessoa->data_nascimento)->format('d/m/Y') : '—';
    $maeShow = $pessoa->mae ?? ($pessoa->filiacao_mae ?? '—');
    $paiShow = $pessoa->pai ?? ($pessoa->filiacao_pai ?? '—');

    // Evitar duplicatas do bloco HERO
    $skipUpper = [
      'CADPEN','NOME','NOME COMPLETO','DATA DE NASCIMENTO','NATURALIDADE',
      'MÃE','PAI','FILIAÇÃO','FILIAÇÃO (MÃE)','FILIAÇÃO (PAI)',
      'FILIAÇÃO — MÃE','FILIAÇÃO — PAI',
      'NATURALIDADE — MUNICÍPIO','NATURALIDADE — UF',
    ];

    $orgNomeSafe      = $orgNome      ?? 'ÓRGÃO / SECRETARIA';
    $orgSubtituloSafe = $orgSubtitulo ?? 'SISTEMA DE GESTÃO DE CUSTODIADOS';
    $usuarioNomeSafe  = $usuarioNome  ?? 'USUÁRIO';
    $usuarioMatrSafe  = $usuarioMatricula ?? null;
    $geradoEmSafe     = ($geradoEm ?? now())->format('d/m/Y H:i');

    $normalizeLabel = fn(string $k) => mb_strtoupper(preg_replace('/[_-]+/',' ', $k), 'UTF-8');

    // Rotas dos botões
    $hrefVoltar = null;
    foreach (['gestao.ficha', 'gestao.ficha.index', 'ficha.index'] as $r) {
      if (\Illuminate\Support\Facades\Route::has($r)) { $hrefVoltar = route($r); break; }
    }
    if (!$hrefVoltar) { $hrefVoltar = url('/gestao/ficha'); }
    $hrefSair = \Illuminate\Support\Facades\Route::has('inicio') ? route('inicio') : url('/');

    // ===== Helpers de formatação (somente VISUAL) =====
    $onlyDigits = function ($s) {
      return preg_replace('/\D+/', '', (string)$s);
    };
    $fmtCpf = function ($s) use ($onlyDigits) {
      $d = $onlyDigits($s);
      if (strlen($d) === 11) {
        return substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2);
      }
      return $s;
    };
    $fmtPhone = function ($s) use ($onlyDigits) {
      $d = $onlyDigits($s);
      if (strlen($d) === 11) {
        // (00) 0 0000-0000
        return sprintf('(%s) %s %s-%s', substr($d,0,2), substr($d,2,1), substr($d,3,4), substr($d,7,4));
      } elseif (strlen($d) === 10) {
        // (00) 0000-0000 (fallback para fixo)
        return sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,4), substr($d,6,4));
      }
      return $s;
    };
  @endphp

  <style>
    * { box-sizing: border-box; }

    :root{
      --page-w: 210mm;
      --page-h: 297mm;
      --header-gap: 10mm;
      --footer-gap: 10mm;
      --pad-left: 20mm;
      --pad-right: 20mm;
      --logo-box: 24mm;
      --photo-h: 50mm;
    }

    .sheet { width: var(--page-w); margin: 0 auto; }
    .print-root { display: block; }

    .page {
      width: var(--page-w);
      min-height: var(--page-h);
      margin: 0 auto 12mm;
      background: #fff; color: #111827;
      display: flex; flex-direction: column;
      box-shadow: 0 1px 4px rgba(0,0,0,.1), 0 8px 24px rgba(0,0,0,.08);
      position: relative;
    }

    .page-head {
      padding: 10mm var(--pad-right) 5mm var(--pad-left);
      border-bottom: 1px solid #e5e7eb;
      break-inside: avoid; page-break-inside: avoid;
    }
    .hbox { display: grid; grid-template-columns: 28mm 1fr; gap: 10px; align-items: center; }
    .hleft { width: var(--logo-box); height: var(--logo-box); border: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .hleft img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .hright { display: flex; flex-direction: column; justify-content: center; }
    .org-title { font-weight: 800; font-size: 12pt; }
    .org-sub   { font-size: 10pt; color: #374151; }
    .userline  { font-size: 9pt; color: #111827; margin-top: 2mm; }

    .page-body { padding: var(--header-gap) var(--pad-right) var(--footer-gap) var(--pad-left); display: block; flex: 1; }

    .page-foot {
      padding: 5mm var(--pad-right) 10mm var(--pad-left);
      border-top: 1px solid #e5e7eb;
      display: flex; justify-content: space-between; align-items: center;
      font-size: 10pt; color: #374151;
      break-inside: avoid; page-break-inside: avoid;
    }

    .sect { margin-bottom: 12px; }
    .sect h2 {
      margin: 0 0 6px 0;
      font-weight: 700; font-size: 11pt;
      padding: 6px 10px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px;
      break-inside: avoid; page-break-inside: avoid;
    }

    .kv-grid {
      display: grid; grid-template-columns: 1fr 2fr;
      gap: 8px 12px; padding: 10px;
      border: 1px solid #e5e7eb; border-radius: 6px; background: #fff;
      break-inside: avoid; page-break-inside: avoid;
    }
    .kv-k { font-size: 9pt; color: #6b7280; }
    .kv-v { font-size: 10.5pt; font-weight: 600; color: #111827; word-break: break-word; }

    .id-hero {
      display: grid; grid-template-columns: 42mm 1fr;
      gap: 12px; align-items: start;
      padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; margin-top: 6px;
      break-inside: avoid; page-break-inside: avoid;
    }
    .photo-frontal .ph { width: 100%; height: var(--photo-h); background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .photo-frontal img { width: 100%; height: 100%; object-fit: cover; }
    .photo-frontal .cap { margin-top: 6px; font-size: 8pt; color: #374151; text-align:center; }
    .id-hero .id-kv .row { display:grid; grid-template-columns: 1fr 2fr; gap: 8px 12px; margin-bottom: 6px; }
    .id-hero .id-kv .k { font-size: 9pt; color:#6b7280; }
    .id-hero .id-kv .v { font-size: 10.5pt; font-weight:600; color:#111827; }

    .sinais-group { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start; }
    .sinais-card { border: 1px solid #e5e7eb; border-radius: 6px; background:#fff; margin: 0; break-inside: avoid; page-break-inside: avoid; }
    .sinais-card h3 { font-weight: 700; font-size: 10.5pt; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; background: #fafafa; margin: 0; }
    .sinais-card .it { padding: 8px 10px; font-size: 10.5pt; color: #111827; }
    .sinais-card .it + .it { border-top: 1px dashed #e5e7eb; }
    .sinais-card .lbl { font-weight: 700; }

    .bio-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
    .bio-card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 6px; background:#fff; break-inside: avoid; page-break-inside: avoid; }
    .bio-card .ph { aspect-ratio: 3/4; display:flex; align-items:center; justify-content:center; background:#f9fafb; border:1px solid #e5e7eb; border-radius: 4px; overflow:hidden; }
    .bio-card .lbl { font-size: 9pt; font-weight: 700; margin-bottom: 4px; color: #111827; }
    .bio-card .st  { margin-top: 4px; font-size: 9pt; color: #111827; }

    @media print {
      body * { visibility: hidden !important; }
      .print-root, .print-root * { visibility: visible !important; }
      .print-root { position: absolute; left: 0; top: 0; width: 100%; }
      @page { size: A4; margin: 0; }
      .page { box-shadow: none; page-break-after: always; break-after: page; }
      .page:last-child { page-break-after: auto; break-after: auto; }
      .bio-grid { display: flex; flex-wrap: wrap; gap: 8px; }
      .bio-card { width: calc(20% - 8px); }
      .no-print { display: none !important; }
    }
  </style>

  {{-- Botões (não imprime) --}}
  <div class="no-print sheet">
    <div class="btn-group" style="display:flex; gap:8px; flex-wrap:wrap;">
      <button class="btn" onclick="window.print()" title="Imprimir / Exportar PDF" style="padding:8px 12px; border-radius:6px; background:#2563eb; color:#fff;">Imprimir / Exportar PDF</button>
      <a href="{{ $hrefVoltar }}" class="btn btn-secondary" role="button" title="Voltar para a tela inicial da Ficha" style="padding:8px 12px; border-radius:6px; background:#6b7280; color:#fff; text-decoration:none;">Voltar</a>
      <a href="{{ $hrefSair }}" class="btn btn-danger" role="button" title="Sair para a página inicial do sistema" style="padding:8px 12px; border-radius:6px; background:#dc2626; color:#fff; text-decoration:none;">Sair</a>
    </div>
  </div>

  {{-- ====== FICHA — CONTEÚDO ÚNICO A IMPRIMIR ====== --}}
  <div class="print-root">

    {{-- ============= PÁGINA 1 ============= --}}
    <section class="page">
      <div class="page-head">
        <div class="sheet">
          <div class="hbox">
            <div class="hleft">
              @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="Brasão">
              @else
                <svg viewBox="0 0 100 100" width="100%" height="100%">
                  <rect x="5" y="5" width="90" height="90" fill="#f3f4f6" stroke="#d1d5db"/>
                  <text x="50" y="54" text-anchor="middle" font-size="10" fill="#6b7280">BRASÃO</text>
                </svg>
              @endif
            </div>
            <div class="hright">
              <div class="org-title">{{ mb_strtoupper($orgNomeSafe, 'UTF-8') }}</div>
              <div class="org-sub">{{ $orgSubtituloSafe }}</div>
              <div class="userline">
                <strong>Emitido por:</strong> {{ $usuarioNomeSafe }}@if($usuarioMatrSafe) — Matrícula {{ $usuarioMatrSafe }} @endif
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        {{-- Identificação inicial (foto + resumo) --}}
        <div class="sect">
          <h2>IDENTIFICAÇÃO INICIAL</h2>
          <div class="id-hero">
            <div class="photo-frontal">
              <div class="ph">
                @php $front = $fotos['frontal'] ?? null; @endphp
                @if(!empty($front))
                  <img src="{{ $front }}" alt="Foto frontal">
                @else
                  <span class="muted" style="font-size:.8rem">Sem imagem</span>
                @endif
              </div>
              <div class="cap">FRONTAL</div>
            </div>

            <div class="id-kv">
              <div class="row"><div class="k">CADPEN</div><div class="v">{{ $ident->cadpen ?? ($pessoa->cadpen ?? '—') }}</div></div>
              <div class="row"><div class="k">NOME</div><div class="v">{{ $pessoa->nome_completo ?? ($pessoa->nome ?? '—') }}</div></div>
              <div class="row"><div class="k">DATA DE NASCIMENTO</div><div class="v">{{ $dnBR }}</div></div>
              <div class="row"><div class="k">NATURALIDADE</div><div class="v">{{ $naturalidade ?: '—' }}</div></div>
              <div class="row"><div class="k">FILIAÇÃO (MÃE)</div><div class="v">{{ $maeShow ?: '—' }}</div></div>
              <div class="row"><div class="k">FILIAÇÃO (PAI)</div><div class="v">{{ $paiShow ?: '—' }}</div></div>
            </div>
          </div>
        </div>

        {{-- DADOS PESSOAIS — 1 coluna; máscaras para CPF/telefone --}}
        <div class="sect">
          <h2>DADOS PESSOAIS</h2>
          @php
            // 1) Começa com o que vier dos arrays (exceto duplicados do HERO)
            $pairs = [];
            foreach(($pessoalGrupos ?? []) as $sec => $kv){
              foreach($kv as $k => $v){
                $U = mb_strtoupper(preg_replace('/\s+/', ' ', (string)$k), 'UTF-8');
                if (in_array($U, $skipUpper, true)) continue;
                $pairs[$k] = $v;
              }
            }
            foreach(($pessoalExtras ?? []) as $k => $v){
              $U = mb_strtoupper(preg_replace('/\s+/', ' ', (string)$k), 'UTF-8');
              if (in_array($U, $skipUpper, true)) continue;
              $pairs[$k] = $v;
            }

            // 2) Endereço compacto
            $addrPartsLine1 = [];
            if (!empty($pessoa->end_logradouro))   $addrPartsLine1[] = $pessoa->end_logradouro;
            if (!empty($pessoa->end_numero))       $addrPartsLine1[] = $pessoa->end_numero;
            $addrLine1 = count($addrPartsLine1) ? ($addrPartsLine1[0].(isset($addrPartsLine1[1]) ? ', '.$addrPartsLine1[1] : '')) : '';

            $addrPartsLine2 = [];
            if (!empty($pessoa->end_complemento))  $addrPartsLine2[] = $pessoa->end_complemento;
            if (!empty($pessoa->end_bairro))       $addrPartsLine2[] = $pessoa->end_bairro;

            $addrCityUF = (!empty($pessoa->end_municipio) || !empty($pessoa->end_uf))
              ? trim(($pessoa->end_municipio ?? '').(!empty($pessoa->end_uf) ? '/'.$pessoa->end_uf : ''))
              : '';

            $addrCEP = !empty($pessoa->end_cep) ? $pessoa->end_cep : '';

            $enderecoCompact = trim(implode(' — ', array_filter([
              $addrLine1 ?: null,
              count($addrPartsLine2) ? implode(' • ', $addrPartsLine2) : null,
              $addrCityUF ?: null,
              $addrCEP ?: null,
            ], fn($x)=> !empty($x))));

            // 3) Campos da migration relevantes
            $want = [
              'Nome social'             => $pessoa->nome_social ?? null,
              'Alcunha'                 => $pessoa->alcunha ?? null,
              'Sexo / Gênero'           => $pessoa->genero_sexo ?? null,
              'Nacionalidade'           => $pessoa->nacionalidade ?? null,
              'Estado civil'            => $pessoa->estado_civil ?? null,
              'Escolaridade (nível)'    => $pessoa->escolaridade_nivel ?? null,
              'Escolaridade (situação)' => $pessoa->escolaridade_situacao ?? null,
              'Profissão'               => $pessoa->profissao ?? null,
              'Endereço'                => $enderecoCompact ?: null,
              'Telefone principal'      => $pessoa->telefone_principal ?? null,
              'Telefones adicionais'    => $pessoa->telefones_adicionais ?? null,
              'E-mail'                  => $pessoa->email ?? null,
              'Óbito'                   => isset($pessoa->obito) ? ((int)$pessoa->obito === 1 ? 'SIM' : 'NÃO') : null,
              'Data do óbito'           => !empty($pessoa->data_obito) ? \Carbon\Carbon::parse($pessoa->data_obito)->format('d/m/Y') : null,
            ];

            $haveUppers = array_map(fn($k)=>mb_strtoupper(preg_replace('/\s+/', ' ', (string)$k),'UTF-8'), array_keys($pairs));
            foreach($want as $label => $val){
              $U = mb_strtoupper(preg_replace('/\s+/', ' ', $label),'UTF-8');
              if (in_array($U, $skipUpper, true)) continue;
              if (in_array($U, $haveUppers, true)) continue;
              if ($val !== null && $val !== '' && $val !== []) $pairs[$label] = $val;
            }

            // 4) Ordenação
            $order = [
              'Nome social','Alcunha','Sexo / Gênero','Nacionalidade',
              'Estado civil','Escolaridade (nível)','Escolaridade (situação)','Profissão',
              'Endereço','Telefone principal','Telefones adicionais','E-mail',
              'Óbito','Data do óbito'
            ];
            $pairsOrdered = [];
            foreach($order as $k){ if(array_key_exists($k,$pairs)){ $pairsOrdered[$k]=$pairs[$k]; unset($pairs[$k]); } }
            foreach($pairs as $k=>$v){ $pairsOrdered[$k]=$v; }
          @endphp

          @if(empty($pairsOrdered))
            <div class="kv-grid" style="margin-top:6px">
              <div class="kv-k">—</div><div class="kv-v">Sem dados adicionais.</div>
            </div>
          @else
            <div class="kv-grid" style="margin-top:6px">
              @foreach($pairsOrdered as $k => $v)
                <div class="kv-k">{{ $k }}</div>
                <div class="kv-v">
                  @php $labelU = mb_strtoupper(preg_replace('/\s+/', ' ', (string)$k),'UTF-8'); @endphp

                  @if($labelU === 'TELEFONE PRINCIPAL')
                    {{ $fmtPhone($v) }}

                  @elseif($labelU === 'TELEFONES ADICIONAIS')
                    @php
                      $arr = is_string($v) ? json_decode($v, true) : (is_array($v) ? $v : null);
                    @endphp
                    @if(is_array($arr) && count($arr))
                      {{ implode(' • ', array_map($fmtPhone, array_filter(array_map('strval', $arr)))) }}
                    @else
                      —
                    @endif

                  @else
                    @if(is_string($v) || is_numeric($v))
                      {{ $v }}
                    @elseif(is_array($v) || is_object($v))
                      <pre class="text-xs whitespace-pre-wrap">{{ json_encode($v, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                    @else
                      —
                    @endif
                  @endif
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <div class="page-foot">
        <div><span class="muted">Gerado em:</span> {{ $geradoEmSafe }} <span class="muted">• Indivíduo:</span> {{ $pessoa->nome_completo ?? ($pessoa->nome ?? '—') }}</div>
        <div class="page-no">Página <span class="cur"></span> de <span class="tot"></span></div>
      </div>
    </section>

    {{-- ============= PÁGINA 2 — DOCUMENTOS ============= --}}
    <section class="page">
      <div class="page-head">
        <div class="sheet">
          <div class="hbox">
            <div class="hleft">
              @if(!empty($logoUrl)) <img src="{{ $logoUrl }}" alt="Brasão">
              @else
                <svg viewBox="0 0 100 100" width="100%" height="100%">
                  <rect x="5" y="5" width="90" height="90" fill="#f3f4f6" stroke="#d1d5db"/>
                  <text x="50" y="54" text-anchor="middle" font-size="10" fill="#6b7280">BRASÃO</text>
                </svg>
              @endif
            </div>
            <div class="hright">
              <div class="org-title">{{ mb_strtoupper($orgNomeSafe, 'UTF-8') }}</div>
              <div class="org-sub">{{ $orgSubtituloSafe }}</div>
              <div class="userline"><strong>Emitido por:</strong> {{ $usuarioNomeSafe }}@if($usuarioMatrSafe) — Matrícula {{ $usuarioMatrSafe }} @endif</div>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="sect">
          <h2>DOCUMENTOS</h2>
          @if(empty($docKv))
            <div class="kv-grid"><div class="kv-k">—</div><div class="kv-v">Sem documentos cadastrados.</div></div>
          @else
            <div class="kv-grid">
              @foreach($docKv as $k => $v)
                @php $label = $normalizeLabel($k); @endphp
                <div class="kv-k">{{ $label }}</div>
                <div class="kv-v">
                  @if($label === 'CPF')
                    {{ $fmtCpf($v) }}

                  @elseif($label === 'OUTROS DOCUMENTOS')
                    @php $od = is_string($v) ? json_decode($v, true) : (is_array($v) ? $v : []); @endphp
                    @if(is_array($od) && count($od))
                      @foreach($od as $it)
                        @php
                          $tipo = $it['tipo'] ?? $it['TIPO'] ?? '—';
                          $num  = $it['numero'] ?? $it['NUMERO'] ?? $it['NÚMERO'] ?? '—';
                          // se o tipo for CPF, aplica máscara
                          $numFmt = (mb_strtoupper($tipo,'UTF-8') === 'CPF') ? $fmtCpf($num) : $num;
                        @endphp
                        TIPO: {{ $tipo }} — NÚMERO: {{ $numFmt }}@if(!$loop->last)<br>@endif
                      @endforeach
                    @else
                      —
                    @endif

                  @else
                    @if(is_string($v) || is_numeric($v))
                      {{ $v }}
                    @elseif(is_array($v) || is_object($v))
                      <pre class="text-xs whitespace-pre-wrap">{{ json_encode($v, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                    @else
                      —
                    @endif
                  @endif
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <div class="page-foot">
        <div><span class="muted">Gerado em:</span> {{ $geradoEmSafe }} <span class="muted">• Indivíduo:</span> {{ $pessoa->nome_completo ?? ($pessoa->nome ?? '—') }}</div>
        <div class="page-no">Página <span class="cur"></span> de <span class="tot"></span></div>
      </div>
    </section>

    {{-- ============= PÁGINA 3 — IDENTIFICAÇÃO (CARACTERÍSTICAS) ============= --}}
    <section class="page">
      <div class="page-head">
        <div class="sheet">
          <div class="hbox">
            <div class="hleft">
              @if(!empty($logoUrl)) <img src="{{ $logoUrl }}" alt="Brasão">
              @else
                <svg viewBox="0 0 100 100" width="100%" height="100%">
                  <rect x="5" y="5" width="90" height="90" fill="#f3f4f6" stroke="#d1d5db"/>
                  <text x="50" y="54" text-anchor="middle" font-size="10" fill="#6b7280">BRASÃO</text>
                </svg>
              @endif
            </div>
            <div class="hright">
              <div class="org-title">{{ mb_strtoupper($orgNomeSafe, 'UTF-8') }}</div>
              <div class="org-sub">{{ $orgSubtituloSafe }}</div>
              <div class="userline"><strong>Emitido por:</strong> {{ $usuarioNomeSafe }}@if($usuarioMatrSafe) — Matrícula {{ $usuarioMatrSafe }} @endif</div>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="sect">
          <h2>IDENTIFICAÇÃO — CARACTERÍSTICAS E SINAIS</h2>
          @if(empty($sinais))
            <div class="kv-grid"><div class="kv-k">—</div><div class="kv-v">Nenhuma característica registrada.</div></div>
          @else
            <div class="sinais-group">
              @foreach($sinaisPorLocal as $local => $itens)
                <div class="sinais-card">
                  <h3>{{ $local }}</h3>
                  @foreach($itens as $i)
                    @php
                      $lbl = $i['tipo'] ?? '—';
                      $val = $i['descricao'] ?? null;
                    @endphp
                    <div class="it">
                      @if($val)
                        <span class="lbl">{{ $lbl }}:</span> <span class="desc">{{ $val }}</span>
                      @else
                        <span class="lbl">{{ $lbl }}</span>
                      @endif
                    </div>
                  @endforeach
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <div class="page-foot">
        <div><span class="muted">Gerado em:</span> {{ $geradoEmSafe }} <span class="muted">• Indivíduo:</span> {{ $pessoa->nome_completo ?? ($pessoa->nome ?? '—') }}</div>
        <div class="page-no">Página <span class="cur"></span> de <span class="tot"></span></div>
      </div>
    </section>

    {{-- ============= PÁGINA 4 — OBSERVAÇÕES + BIOMETRIA ============= --}}
    <section class="page">
      <div class="page-head">
        <div class="sheet">
          <div class="hbox">
            <div class="hleft">
              @if(!empty($logoUrl)) <img src="{{ $logoUrl }}" alt="Brasão">
              @else
                <svg viewBox="0 0 100 100" width="100%" height="100%">
                  <rect x="5" y="5" width="90" height="90" fill="#f3f4f6" stroke="#d1d5db"/>
                  <text x="50" y="54" text-anchor="middle" font-size="10" fill="#6b7280">BRASÃO</text>
                </svg>
              @endif
            </div>
            <div class="hright">
              <div class="org-title">{{ mb_strtoupper($orgNomeSafe, 'UTF-8') }}</div>
              <div class="org-sub">{{ $orgSubtituloSafe }}</div>
              <div class="userline"><strong>Emitido por:</strong> {{ $usuarioNomeSafe }}@if($usuarioMatrSafe) — Matrícula {{ $usuarioMatrSafe }} @endif</div>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="sect">
          <h2>OBSERVAÇÕES (IDENTIFICAÇÃO)</h2>
          <div class="kv-grid">
            <div class="kv-k">OBSERVAÇÕES</div>
            <div class="kv-v">{{ $ident->observacoes ?? '—' }}</div>
          </div>
        </div>

        <div class="sect">
          <h2>BIOMETRIA — IMPRESSÃO DIGITAL</h2>
          @php $confirmados = array_values(array_filter($bio, fn($b) => ($b['status'] ?? null) === 'confirmado')); @endphp
          <div class="kv-grid" style="margin-bottom:10px">
            <div class="kv-k">DEDOS CONFIRMADOS</div>
            <div class="kv-v">{{ count($confirmados) }} / 10</div>
          </div>

          @if(empty($bio))
            <div class="kv-grid"><div class="kv-k">—</div><div class="kv-v">Nenhuma biometria registrada.</div></div>
          @else
            <div class="bio-grid">
              @foreach($bio as $i => $d)
                <div class="bio-card">
                  <div class="lbl">{{ $d['dedo'] ?? ('DEDO '.($i+1)) }}</div>
                  <div class="ph">
                    @if(!empty($d['imagem']))
                      <img src="{{ $d['imagem'] }}" alt="Dedo {{ $i+1 }}">
                    @else
                      <span class="muted" style="font-size:.8rem">Sem imagem</span>
                    @endif
                  </div>
                  <div class="st">{{ ucfirst($d['status'] ?? 'vazio') }}</div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <div class="page-foot">
        <div><span class="muted">Gerado em:</span> {{ $geradoEmSafe }} <span class="muted">• Indivíduo:</span> {{ $pessoa->nome_completo ?? ($pessoa->nome ?? '—') }}</div>
        <div class="page-no">Página <span class="cur"></span> de <span class="tot"></span></div>
      </div>
    </section>

  </div>

  {{-- Numeração "Página X de Y" via JS --}}
  <script>
    (function() {
      function updatePageNumbers() {
        const pages = Array.from(document.querySelectorAll('.print-root .page'))
          .filter(p => getComputedStyle(p).display !== 'none');
        const total = pages.length;
        pages.forEach((p, i) => {
          const cur = p.querySelector('.page-no .cur');
          const tot = p.querySelector('.page-no .tot');
          if (cur) cur.textContent = String(i + 1);
          if (tot) tot.textContent = String(total);
        });
      }
      document.addEventListener('DOMContentLoaded', updatePageNumbers);
      window.addEventListener('resize', updatePageNumbers);
      window.addEventListener('beforeprint', updatePageNumbers);

      if (window.matchMedia) {
        const mm = window.matchMedia('print');
        if (mm.addEventListener) {
          mm.addEventListener('change', e => { if (e.matches) updatePageNumbers(); });
        } else if (mm.addListener) {
          mm.addListener(e => { if (e.matches) updatePageNumbers(); });
        }
      }
    })();
  </script>
</x-app-layout>
