{{-- resources/views/sistema/usuariosedit.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">
      Gestão do Sistema • Usuários — {{ isset($user) && $user ? 'Editar' : 'Buscar' }}
    </h1>
  </x-slot>

  <div class="max-w-6xl mx-auto p-6 space-y-6">
    {{-- Alerts padronizados --}}
    @if (session('success') || session('ok'))
      <div class="rounded-md bg-green-100 text-green-800 px-4 py-2">{{ session('success') ?? session('ok') }}</div>
    @endif
    @if (session('warning') || session('info'))
      <div class="rounded-md bg-yellow-100 text-yellow-800 px-4 py-2">{{ session('warning') ?? session('info') }}</div>
    @endif
    @if (session('error'))
      <div class="rounded-md bg-red-100 text-red-800 px-4 py-2">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
      <div class="rounded-md bg-red-100 text-red-800 px-4 py-3">
        <div class="font-semibold mb-1">VERIFIQUE OS CAMPOS:</div>
        <ul class="list-disc list-inside text-sm">
          @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
      </div>
    @endif

    @if (!isset($user) || !$user)
      {{-- ===================== MODO BUSCA ===================== --}}
      @php $q = $q ?? []; @endphp

      <form method="GET" action="{{ route('sistema.usuarios.search') }}" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <label class="block">
            <span class="text-sm font-semibold">Nome</span>
            <input type="text" name="name" value="{{ $q['q_name'] ?? '' }}"
                   class="mt-1 w-full rounded-md border-gray-300" autocomplete="name">
          </label>

          <label class="block">
            <span class="text-sm font-semibold">E-mail</span>
            <input type="text" name="email" value="{{ $q['q_email'] ?? '' }}"
                   class="mt-1 w-full rounded-md border-gray-300"
                   autocapitalize="off" autocorrect="off" spellcheck="false">
          </label>

          <label class="block">
            <span class="text-sm font-semibold">Usuário</span>
            <input type="text" name="username" value="{{ $q['q_user'] ?? '' }}"
                   class="mt-1 w-full rounded-md border-gray-300"
                   oninput="this.value=this.value.toLowerCase()" autocapitalize="off" autocorrect="off" spellcheck="false">
          </label>

          <label class="block">
            <span class="text-sm font-semibold">Matrícula</span>
            <input type="text" name="matricula" value="{{ $q['q_matr'] ?? '' }}"
                   class="mt-1 w-full rounded-md border-gray-300"
                   inputmode="numeric" autocapitalize="off" autocorrect="off" spellcheck="false">
          </label>

          <label class="block">
            <span class="text-sm font-semibold">CPF</span>
            <input type="text" name="cpf" value="{{ $q['q_cpf'] ?? '' }}"
                   placeholder="000.000.000-00"
                   class="mt-1 w-full rounded-md border-gray-300"
                   inputmode="numeric" maxlength="14"
                   oninput="maskCPF(this)" autocapitalize="off" autocorrect="off" spellcheck="false">
          </label>

          <label class="block">
            <span class="text-sm font-semibold">Perfil (código)</span>
            <select name="perfil_code" class="mt-1 w-full rounded-md border-gray-300">
              <option value="">—</option>
              @foreach(($perfis ?? []) as $code => $name)
                <option value="{{ $code }}" @selected(($q['q_pcode'] ?? '') === $code)>{{ $code }}</option>
              @endforeach
            </select>
          </label>
        </div>

        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-600">
            Resultados por página
            <select name="pp" class="border rounded px-2 py-1">
              @foreach([10,15,20,30,50] as $n)
                <option value="{{ $n }}" @selected(($q['q_perpage'] ?? 15) == $n)>{{ $n }}</option>
              @endforeach
            </select>
          </div>
          <div class="flex gap-2">
            <a href="{{ route('sistema.usuarios.create') }}"
               class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Cadastrar Usuário</a>
            <button class="px-4 py-2 rounded bg-gray-900 text-white hover:bg-black">Buscar</button>
          </div>
        </div>
      </form>

      @if(($didSearch ?? false) && isset($resultados))
        <div class="rounded-lg border bg-white mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr class="text-left">
                <th class="p-3">Nome</th>
                <th class="p-3">Logins</th>
                <th class="p-3">Perfil</th>
                <th class="p-3">Status</th>
                <th class="p-3">Último login</th>
                <th class="p-3 text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              @forelse($resultados as $u)
                <tr class="border-t">
                  <td class="p-3">
                    <div class="font-medium">{{ $u->name }}</div>
                    <div class="text-xs text-gray-500">{{ $u->email }}</div>
                  </td>
                  <td class="p-3">
                    <div><span class="text-gray-500">user:</span> {{ $u->username ?? '—' }}</div>
                    <div><span class="text-gray-500">matr:</span> {{ $u->matricula ?? '—' }}</div>
                    <div><span class="text-gray-500">cpf:</span> {{ $u->cpf_masked ?? $u->cpf ?? '—' }}</div>
                  </td>
                  <td class="p-3 font-mono">
                    {{ $u->perfil_code ?? '—' }} <span class="text-gray-500">/</span> {{ $u->perfil ?? '—' }}
                  </td>
                  <td class="p-3">
                    @if($u->is_active)
                      <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 text-xs">ATIVO</span>
                    @else
                      <span class="px-2 py-0.5 rounded bg-red-100 text-red-800 text-xs">INATIVO</span>
                    @endif
                  </td>
                  <td class="p-3">
                    {{ $u->last_login_at ? \Carbon\Carbon::parse($u->last_login_at)->format('d/m/Y H:i') : '—' }}
                  </td>
                  <td class="p-3 text-right">
                    <a href="{{ route('sistema.usuarios.edit', $u) }}"
                       class="px-3 py-1 rounded bg-yellow-500 text-white hover:bg-yellow-600">Editar</a>

                    @if(auth()->check() && auth()->id() !== $u->id)
                      <form method="POST" action="{{ route('sistema.usuarios.destroy', $u) }}"
                            class="inline"
                            onsubmit="return confirm('Confirma excluir este usuário?');">
                        @csrf @method('DELETE')
                        <button class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700">Excluir</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="p-6 text-center text-gray-600">Nenhum usuário encontrado.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-2">{{ $resultados->links() }}</div>
      @endif

    @else
      {{-- ===================== MODO EDIÇÃO ===================== --}}
      <form method="POST" action="{{ route('sistema.usuarios.update', $user) }}" class="space-y-8 max-w-3xl">
        @csrf @method('PUT')

        @include('sistema.partials.usuariosform', [
          'usuario' => $user,
          'perfis'  => $perfis,
          'isEdit'  => true
        ])

        <div class="flex items-center justify-between gap-3">
          <a href="{{ route('sistema.usuarios.search') }}"
             class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">VOLTAR À BUSCA</a>
          <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">ATUALIZAR</button>
          </div>
        </div>
      </form>
    @endif
  </div>

  {{-- Utilitário local: máscara de CPF no filtro da busca --}}
  <script>
    function maskCPF(el){
      let v = (el.value || '').replace(/\D+/g,'').slice(0,11);
      let f = v;
      if (v.length > 9)       f = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*$/, '$1.$2.$3-$4');
      else if (v.length > 6)  f = v.replace(/^(\d{3})(\d{3})(\d+).*$/,        '$1.$2.$3');
      else if (v.length > 3)  f = v.replace(/^(\d{3})(\d+).*$/,               '$1.$2');
      el.value = f;
    }
  </script>
</x-app-layout>
