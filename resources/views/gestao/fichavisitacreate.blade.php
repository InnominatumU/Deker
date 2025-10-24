{{-- resources/views/gestao/fichavisitacreate.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">
      @if(($mode ?? 'search') === 'show') FICHA DO VISITANTE @else FICHA DO VISITANTE — BUSCAR @endif
    </h1>
  </x-slot>

  @php
    // Helpers/formatos usados na FICHA
    $orgNomeSafe      = $orgNome      ?? 'ÓRGÃO / SECRETARIA';
    $orgSubtituloSafe = $orgSubtitulo ?? 'SISTEMA DE GESTÃO DE CUSTODIADOS';
    $usuarioNomeSafe  = $usuarioNome  ?? 'USUÁRIO';
    $usuarioMatrSafe  = $usuarioMatricula ?? null;
    $geradoEmSafe     = isset($geradoEm) ? $geradoEm->format('d/m/Y H:i') : now()->format('d/m/Y H:i');

    // Logo (brasão)
    $logoUrl = $orgBrasaoUrl ?? null;
    if (!$logoUrl) {
      foreach (['png','jpg','jpeg'] as $ext) {
        $p = public_path("images/brasao.$ext");
        if (file_exists($p)) { $logoUrl = asset("images/brasao.$ext"); break; }
      }
    }

    // Rotas dos botões
    $hrefVoltar = \Illuminate\Support\Facades\Route::has('gestao.visitantes.ficha.index')
      ? route('gestao.visitantes.ficha.index') : url('/gestao/visitantes/ficha');
    $hrefSair = \Illuminate\Support\Facades\Route::has('inicio') ? route('inicio') : url('/');

    // Funções visuais
    $onlyDigits = fn($s) => preg_replace('/\D+/', '', (string)$s);
    $fmtCpf = function ($s) use ($onlyDigits) {
      $d = $onlyDigits($s);
      return strlen($d) === 11
        ? substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2)
        : ($s ?: '—');
    };
  @endphp

  {{-- ============== MODO BUSCA (LANÇADOR) ============== --}}
  @if(($mode ?? 'search') === 'search')
    <div class="py-6">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        @if(!empty($error))
          <div class="mb-4 p-3 rounded bg-red-100 text-red-800">{{ $error }}</div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl">
          <div class="p-6 text-gray-900 space-y-6">
            <p class="text-sm text-gray-600">
              Pesquise o visitante pelo <strong>CPF</strong> (principal) ou pelo <strong>ID</strong>.
            </p>

            <form method="GET" action="{{ route('gestao.visitantes.ficha.index') }}" class="grid md:grid-cols-3 gap-4">
              <div class="md:col-span-2">
                <label class="block text-sm text-gray-600">CPF</label>
                <input type="text" name="cpf" class="w-full rounded border-gray-300" placeholder="ex: 123.456.789-00" autofocus>
              </div>
              <div>
                <label class="block text-sm text-gray-600">ID (opcional)</label>
                <input type="number" name="id" class="w-full rounded border-gray-300" placeholder="ex: 42">
              </div>
              <div class="md:col-span-3 flex items-end">
                <button class="px-4 py-2 rounded bg-blue-900 text-white">Abrir Ficha</button>
                <a href="{{ route('inicio') }}" class="ml-3 px-4 py-2 rounded bg-gray-800 text-white">Voltar</a>
              </div>
            </form>

            <div class="text-xs text-gray-500">
              Dica: de outras telas, você pode apontar diretamente para esta página usando
              <code>?cpf=</code> ou <code>?id=</code>.
            </div>
          </div>
        </div>
      </div>
    </div>

  @else
  {{-- ============== MODO FICHA (A4/PRINT) ============== --}}

  <style>
    * { box-sizing: border-box; }
    :root{ --page-w:210mm; --page-h:297mm; --header-gap:10mm; --footer-gap:10mm; --pad-left:20mm; --pad-right:20mm; --logo-box:24mm; }
    .sheet{ width:var(--page-w); margin:0 auto; }
    .print-root{ display:block; }
    .page{ width:var(--page-w); min-height:var(--page-h); margin:0 auto 12mm; background:#fff; color:#111827; display:flex; flex-direction:column; box-shadow:0 1px 4px rgba(0,0,0,.1), 0 8px 24px rgba(0,0,0,.08); position:relative; }
    .page-head{ padding:10mm var(--pad-right) 5mm var(--pad-left); border-bottom:1px solid #e5e7eb; break-inside:avoid; page-break-inside:avoid; }
    .hbox{ display:grid; grid-template-columns:28mm 1fr; gap:10px; align-items:center; }
    .hleft{ width:var(--logo-box); height:var(--logo-box); border:1px solid #e5e7eb; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .hleft img{ max-width:100%; max-height:100%; object-fit:contain; }
    .hright{ display:flex; flex-direction:column; justify-content:center; }
    .org-title{ font-weight:800; font-size:12pt; }
    .org-sub{ font-size:10pt; color:#374151; }
    .userline{ font-size:9pt; color:#111827; margin-top:2mm; }
    .page-body{ padding:var(--header-gap) var(--pad-right) var(--footer-gap) var(--pad-left); display:block; flex:1; }
    .page-foot{ padding:5mm var(--pad-right) 10mm var(--pad-left); border-top:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; font-size:10pt; color:#374151; break-inside:avoid; page-break-inside:avoid; }
    .sect{ margin-bottom:12px; }
    .sect h2{ margin:0 0 6px 0; font-weight:700; font-size:11pt; padding:6px 10px; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:6px; break-inside:avoid; page-break-inside:avoid; }
    .kv-grid{ display:grid; grid-template-columns:1fr 2fr; gap:8px 12px; padding:10px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; break-inside:avoid; page-break-inside:avoid; }
    .kv-k{ font-size:9pt; color:#6b7280; } .kv-v{ font-size:10.5pt; font-weight:600; color:#111827; word-break:break-word; }
    .visit-card{ border:1px solid #e5e7eb; border-radius:6px; overflow:hidden; break-inside:avoid; page-break-inside:avoid; }
    .visit-head{ padding:8px 10px; background:#f9fafb; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e5e7eb; }
    .badge{ display:inline-block; padding:2px 6px; font-size:10px; border-radius:4px; background:#eef2ff; color:#3730a3; }
    .badge.gray{ background:#f3f4f6; color:#111827; }
    .visit-body{ padding:10px; display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
    .visit-body .full{ grid-column:1 / -1; }
    table.vinc{ width:100%; font-size:10.5pt; border-collapse:collapse; }
    table.vinc th, table.vinc td{ padding:6px 8px; border-bottom:1px solid #e5e7eb; text-align:left; }
    @media print{
      body *{ visibility:hidden !important; }
      .print-root, .print-root *{ visibility:visible !important; }
      .print-root{ position:absolute; left:0; top:0; width:100%; }
      @page{ size:A4; margin:0; }
      .page{ box-shadow:none; page-break-after:always; break-after:page; }
      .page:last-child{ page-break-after:auto; break-after:auto; }
      .no-print{ display:none !important; }
    }
  </style>

  {{-- Botões (não imprime) --}}
  <div class="no-print sheet">
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <button onclick="window.print()" title="Imprimir / Exportar PDF" style="padding:8px 12px; border-radius:6px; background:#2563eb; color:#fff;">Imprimir / Exportar PDF</button>
      <a href="{{ $hrefVoltar }}" title="Voltar" style="padding:8px 12px; border-radius:6px; background:#6b7280; color:#fff; text-decoration:none;">Voltar</a>
      <a href="{{ $hrefSair }}"   title="Sair"   style="padding:8px 12px; border-radius:6px; background:#dc2626; color:#fff; text-decoration:none;">Sair</a>
    </div>
  </div>

  <div class="print-root">
    {{-- ============= PÁGINA 1 ============= --}}
    <section class="page">
      <div class="page-head">
        <div class="sheet">
          <div class="hbox">
            <div class="hleft">
              @if(!empty($logoUrl)) <img src="{{ $logoUrl }}" alt="Brasão">
              @else
                <svg viewBox="0 0 100 100" width="100%" height="100%"><rect x="5" y="5" width="90" height="90" fill="#f3f4f6" stroke="#d1d5db"/><text x="50" y="54" text-anchor="middle" font-size="10" fill="#6b7280">BRASÃO</text></svg>
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
          <h2>IDENTIFICAÇÃO DO VISITANTE</h2>
          <div class="kv-grid" style="margin-top:6px">
            <div class="kv-k">NOME COMPLETO</div>
            <div class="kv-v uppercase">{{ $visitante->nome_completo ?? '—' }}</div>

            <div class="kv-k">CPF</div>
            <div class="kv-v">{{ isset($visitante->cpf) ? $fmtCpf($visitante->cpf) : '—' }}</div>

            <div class="kv-k">RG</div>
            <div class="kv-v">{{ $visitante->rg ?? '—' }}</div>

            <div class="kv-k">OAB (Visita Jurídica)</div>
            <div class="kv-v">{{ $visitante->oab ?? '—' }}</div>

            <div class="kv-k">CRIADO EM</div>
            <div class="kv-v">
              {{ isset($visitante->created_at) ? \Illuminate\Support\Carbon::parse($visitante->created_at)->format('d/m/Y H:i') : '—' }}
            </div>

            <div class="kv-k">ATUALIZADO EM</div>
            <div class="kv-v">
              {{ isset($visitante->updated_at) ? \Illuminate\Support\Carbon::parse($visitante->updated_at)->format('d/m/Y H:i') : '—' }}
            </div>
          </div>
        </div>
      </div>

      <div class="page-foot">
        <div><span class="muted">Gerado em:</span> {{ $geradoEmSafe }} <span class="muted">• Visitante:</span> {{ $visitante->nome_completo ?? '—' }}</div>
        <div class="page-no">Página <span class="cur"></span> de <span class="tot"></span></div>
      </div>
    </section>

    {{-- ============= PÁGINA 2 — VISITAS ============= --}}
    <section class="page">
      <div class="page-head">
        <div class="sheet">
          <div class="hbox">
            <div class="hleft">
              @if(!empty($logoUrl)) <img src="{{ $logoUrl }}" alt="Brasão">
              @else
                <svg viewBox="0 0 100 100" width="100%" height="100%"><rect x="5" y="5" width="90" height="90" fill="#f3f4f6" stroke="#d1d5db"/><text x="50" y="54" text-anchor="middle" font-size="10" fill="#6b7280">BRASÃO</text></svg>
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
          <h2>HISTÓRICO DE VISITAS</h2>

          @if(empty($visitas) || $visitas->isEmpty())
            <div class="kv-grid" style="margin-top:6px"><div class="kv-k">—</div><div class="kv-v">Nenhuma visita registrada.</div></div>
          @else
            <div class="space-y-3">
              @foreach($visitas as $v)
                @php
                  $tipoLabel    = ($tipos ?? [])[$v->tipo] ?? $v->tipo;
                  $destinoLabel = ($destinos ?? [])[$v->destino] ?? $v->destino;
                @endphp
                <div class="visit-card">
                  <div class="visit-head">
                    <div>
                      <span class="badge">{{ $tipoLabel }}</span>
                      <span class="badge gray" style="margin-left:6px">{{ $destinoLabel }}</span>
                      <span class="text-gray-500 text-sm" style="margin-left:8px">#{{ $v->id }}</span>
                    </div>
                    <div class="text-sm text-gray-600">
                      Criada em {{ \Illuminate\Support\Carbon::parse($v->created_at)->format('d/m/Y H:i') }}
                    </div>
                  </div>

                  <div class="visit-body">
                    <div>
                      <div class="kv-k">Unidade</div>
                      <div class="kv-v">
                        @if($v->destino === 'UNIDADE')
                          — (vínculo de unidade ocorre no momento da visita)
                        @else
                          {{ $v->unidade_id && isset($unidadesById[$v->unidade_id]) ? $unidadesById[$v->unidade_id] : '—' }}
                        @endif
                      </div>
                    </div>

                    <div>
                      <div class="kv-k">Religião (RELIGIOSA)</div>
                      <div class="kv-v">{{ $v->religiao ?? '—' }}</div>
                    </div>

                    <div>
                      <div class="kv-k">Cargo (AUTORIDADES)</div>
                      <div class="kv-v">{{ $v->autoridade_cargo ?? '—' }}</div>
                    </div>

                    <div>
                      <div class="kv-k">Órgão (AUTORIDADES)</div>
                      <div class="kv-v">{{ $v->autoridade_orgao ?? '—' }}</div>
                    </div>

                    <div class="full">
                      <div class="kv-k">Descrição (OUTRAS)</div>
                      <div class="kv-v">{{ $v->descricao_outros ?? '—' }}</div>
                    </div>

                    <div class="full">
                      <div class="kv-k">Observações</div>
                      <div class="kv-v whitespace-pre-wrap">{{ $v->observacoes ?? '—' }}</div>
                    </div>
                  </div>

                  @if($v->destino === 'INDIVIDUOS')
                    <div style="padding: 0 10px 10px 10px">
                      <div class="kv-k" style="margin-bottom:6px">Vínculos com Indivíduo(s)</div>
                      @php $links = $vinculosPorVisita[$v->id] ?? []; @endphp
                      @if(empty($links))
                        <div class="kv-grid"><div class="kv-k">—</div><div class="kv-v">Sem vínculos cadastrados.</div></div>
                      @else
                        <div class="overflow-x-auto">
                          <table class="vinc">
                            <thead>
                              <tr>
                                <th>Indivíduo</th>
                                <th>CadPen</th>
                                <th>Parentesco/Relacionamento</th>
                              </tr>
                            </thead>
                            <tbody>
                              @foreach($links as $lnk)
                                <tr>
                                  <td>{{ $lnk['nome'] ?? ('ID: '.$lnk['individuo_id']) }}</td>
                                  <td>{{ $lnk['cadpen'] ?? '—' }}</td>
                                  <td>{{ $lnk['parentesco'] ?? '—' }}</td>
                                </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </div>
                      @endif
                    </div>
                  @endif

                  <div class="text-xs text-gray-500" style="padding:6px 10px; background:#fafafa; border-top:1px solid #e5e7eb;">
                    Atualizado em {{ \Illuminate\Support\Carbon::parse($v->updated_at)->format('d/m/Y H:i') }}
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <div class="page-foot">
        <div><span class="muted">Gerado em:</span> {{ $geradoEmSafe }} <span class="muted">• Visitante:</span> {{ $visitante->nome_completo ?? '—' }}</div>
        <div class="page-no">Página <span class="cur"></span> de <span class="tot"></span></div>
      </div>
    </section>
  </div>

  {{-- Numeração "Página X de Y" --}}
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

  @endif
</x-app-layout>
