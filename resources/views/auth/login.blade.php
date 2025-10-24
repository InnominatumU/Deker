{{-- resources/views/auth/login.blade.php --}}
<x-guest-layout>
    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-100 text-green-800 px-4 py-2">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-100 text-red-800 px-4 py-3">
            <div class="font-semibold mb-1">Não foi possível entrar:</div>
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                E-mail, Matrícula, CPF ou Usuário
            </label>
            <input
                type="text"
                name="login"
                value="{{ old('login') }}"
                autocomplete="username"
                autofocus
                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                placeholder="ex.: usuario@org.br • 123456 • 000.000.000-00 • joao.silva"
            >
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">
                Senha
            </label>
            <input
                type="password"
                name="password"
                autocomplete="current-password"
                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900"
            >
        </div>

        <div class="flex items-center justify-between">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="remember" class="rounded border-gray-300 dark:border-gray-700">
                <span class="text-sm">Lembrar-me</span>
            </label>

            {{-- Se quiser disponibilizar recuperação de senha, descomente a rota e o link: --}}
            {{-- <a href="{{ route('password.request') }}" class="text-sm text-blue-700 hover:underline">
                Esqueci minha senha
            </a> --}}
        </div>

        <div class="pt-2">
            <button class="w-full px-4 py-2 rounded bg-gray-900 text-white hover:bg-black">
                Entrar
            </button>
        </div>
    </form>
</x-guest-layout>
