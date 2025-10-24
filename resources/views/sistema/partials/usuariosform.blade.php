{{-- resources/views/sistema/partials/usuariosform.blade.php --}}
@php
  /** @var \App\Models\User|null $usuario */
  $usuario = $usuario ?? null;

  // Helper de valor com fallback ao $usuario
  $v = function (string $k, $default = '') use ($usuario) {
      $from = $usuario?->{$k} ?? null;
      return old($k, $from ?? $default);
  };

  // Flag edição (para tornar a senha opcional)
  $isEdit = isset($isEdit) && $isEdit;

  // Opções de perfil (code => nome). Preferir receber do controller.
  $perfis = $perfis ?? [
      'A1' => 'GESTOR_SISTEMA',
      'A2' => 'ADMIN_SISTEMA',
      'A3' => 'ADMIN_TECNICO',
      'A4' => 'AUDITOR',
      'G1' => 'GERENTE_GERAL',
      'G2' => 'GERENTE_UNIDADE',
      'G3' => 'GERENTE_SETORIAL',
      'G4' => 'COORDENADOR_SETORIAL',
      'G5' => 'COORDENADOR_NIS',
      'G6' => 'COORDENADOR',
      'O1' => 'OPERADOR_CADASTRO',
      'O2' => 'OPERADOR_SAUDE',
      'O3' => 'OPERADOR_TRABALHO',
      'O4' => 'OPERADOR_JURIDICO',
      'R1' => 'LEITURA_GERAL',
      'S1' => 'SUPORTE_LOCAL',
  ];

  // Função helper para exibir "A1 - Gestor do Sistema"
  $labelPerfil = function (string $code, string $name): string {
      $bonito = ucwords(mb_strtolower(str_replace('_',' ', $name), 'UTF-8'));
      return $code.' - '.$bonito;
  };
@endphp

<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Identificação do Usuário</h2>

  <label class="block">
    <span class="text-sm font-semibold">Nome completo *</span>
    <input type="text" name="name" value="{{ $v('name') }}"
           class="mt-1 w-full rounded-md border-gray-300" required>
    @error('name') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <label class="block">
    <span class="text-sm font-semibold">E-mail *</span>
    <input type="email" name="email" value="{{ $v('email') }}"
           class="mt-1 w-full rounded-md border-gray-300" required>
    @error('email') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  <div class="grid md:grid-cols-3 gap-4">
    <label class="block">
      <span class="text-sm font-semibold">Matrícula</span>
      <input type="text" name="matricula" value="{{ $v('matricula') }}"
             class="mt-1 w-full rounded-md border-gray-300">
      @error('matricula') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
    </label>

    <label class="block">
      <span class="text-sm font-semibold">CPF</span>
      <input type="text" name="cpf" value="{{ old('cpf', $usuario?->cpf_masked ?? $usuario?->cpf) }}"
             inputmode="numeric" maxlength="14" placeholder="000.000.000-00"
             class="mt-1 w-full rounded-md border-gray-300" oninput="maskCPF(this)">
      @error('cpf') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
    </label>

    <label class="block">
      <span class="text-sm font-semibold">Nome de usuário *</span>
      <input type="text" name="username" value="{{ $v('username') }}"
             class="mt-1 w-full rounded-md border-gray-300" oninput="this.value=this.value.toLowerCase()" required>
      @error('username') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
    </label>
  </div>
</div>

<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Perfil e Status</h2>

  {{-- Dropdown único: "A1 - Gestor do Sistema" (envia perfil_code e, via hidden, perfil) --}}
  <label class="block">
    <span class="text-sm font-semibold">Perfil (código - nome) *</span>
    <select name="perfil_code" id="perfil_code" class="mt-1 w-full rounded-md border-gray-300" required>
      <option value="">— Selecione —</option>
      @foreach($perfis as $code => $name)
        @php
          $selected = ($v('perfil_code') === $code) || ($v('perfil') === $name);
        @endphp
        <option value="{{ $code }}"
                data-perfil="{{ $name }}"
                @selected($selected)>
          {{ $labelPerfil($code, $name) }}
        </option>
      @endforeach
    </select>
    @error('perfil_code') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>

  {{-- Campo oculto para enviar o nome do perfil esperado pelo controller --}}
  <input type="hidden" name="perfil" id="perfil_hidden" value="{{ $v('perfil') }}">
  @error('perfil') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror

  <label class="inline-flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300"
           {{ old('is_active', ($usuario->is_active ?? true)) ? 'checked' : '' }}>
    <span class="text-sm font-semibold">Ativo</span>
  </label>
</div>

<div class="border rounded-lg p-4 bg-white space-y-4">
  <h2 class="text-sm font-semibold text-gray-700">Senha</h2>

  <label class="block">
    <span class="text-sm font-semibold">{{ $isEdit ? 'Nova senha (opcional)' : 'Senha *' }}</span>
    <input type="password" name="password" {{ $isEdit ? '' : 'required' }}
           class="mt-1 w-full rounded-md border-gray-300"
           placeholder="{{ $isEdit ? 'Deixe em branco para manter' : '' }}">
    <p class="text-xs text-gray-500 mt-1">Mín. 8 com maiúsculas, minúsculas, números e símbolos.</p>
    @error('password') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
  </label>
</div>

{{-- JS utilitários --}}
<script>
  // Máscara CPF
  function maskCPF(el){
    let v = (el.value || '').replace(/\D+/g,'').slice(0,11);
    let f = v;
    if (v.length > 9)       f = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*$/, '$1.$2.$3-$4');
    else if (v.length > 6)  f = v.replace(/^(\d{3})(\d{3})(\d+).*$/, '$1.$2.$3');
    else if (v.length > 3)  f = v.replace(/^(\d{3})(\d+).*$/, '$1.$2');
    el.value = f;
  }

  // Mantém o input hidden "perfil" sincronizado com o select "perfil_code"
  (function(){
    const sel = document.getElementById('perfil_code');
    const hid = document.getElementById('perfil_hidden');

    function syncPerfilHidden(){
      const opt = sel?.selectedOptions?.[0];
      if (!opt) return;
      const perfilName = opt.getAttribute('data-perfil') || '';
      if (hid) hid.value = perfilName;
    }

    sel?.addEventListener('change', syncPerfilHidden);
    // Inicializa na carga (útil em edição/validação com erro)
    syncPerfilHidden();
  })();
</script>
