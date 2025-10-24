{{-- resources/views/menus/inicio/index.blade.php --}}
<x-app-layout>

  @php
    \Carbon\Carbon::setLocale('pt_BR');
    $agora = \Illuminate\Support\Carbon::now('America/Sao_Paulo');
    $usuario = auth()->user();
    $perfil = $usuario->email === 'gestor@deker.local' ? 'Gestor do Sistema' : 'Usuário';
    $unidadeAtual = 'Unidade não definida'; // troca depois quando tivermos vínculo/unidade
  @endphp

  <!-- Logo DEKER no início -->
  <div class="w-full flex justify-center mb-6">
    <img src="{{ asset('images/deker.png') }}"
         alt="DEKER"
         class="h-56 w-auto drop-shadow-lg">
  </div>

  <div class="p-6 space-y-4">
    <div class="rounded-lg bg-white shadow p-6">
      <h2 class="text-lg font-semibold">Bem-vindo(a), {{ $usuario->name ?? $usuario->email }}!</h2>
      <p class="text-gray-600 mt-1">
        Perfil: <span class="font-medium">{{ $perfil }}</span>
      </p>

      <div class="mt-4 grid sm:grid-cols-3 gap-4 text-sm">
        <!-- Hora -->
        <div class="p-3 rounded bg-gray-50">
          <div class="text-gray-500">Hora</div>
          <div class="font-medium" id="relogio"></div>
        </div>

        <!-- Data -->
        <div class="p-3 rounded bg-gray-50">
          <div class="text-gray-500">Data</div>
          <div class="font-medium">{{ $agora->translatedFormat('d \\d\\e F \\d\\e Y') }}</div>
        </div>

        <!-- Unidade -->
        <div class="p-3 rounded bg-gray-50">
          <div class="text-gray-500">Local (Unidade do Usuário)</div>
          <div class="font-medium">{{ $unidadeAtual }}</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function atualizarRelogio() {
        const agora = new Date();
        const horas = agora.getHours().toString().padStart(2, '0');
        const minutos = agora.getMinutes().toString().padStart(2, '0');
        const segundos = agora.getSeconds().toString().padStart(2, '0');
        document.getElementById('relogio').innerText = `${horas}:${minutos}:${segundos}`;
    }
    setInterval(atualizarRelogio, 1000);
    atualizarRelogio();
  </script>
</x-app-layout>
