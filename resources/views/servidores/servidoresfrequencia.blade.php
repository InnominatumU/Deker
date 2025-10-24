{{-- resources/views/servidores/servidoresfrequencia.blade.php --}}
@php
    use Illuminate\Support\Facades\DB;

    $q = trim((string) request('q', ''));
    $encontrados = collect();

    if ($q !== '') {
        $qLike     = '%'.mb_strtoupper($q, 'UTF-8').'%';
        $cpfDigits = preg_replace('/\D+/', '', $q);

        $encontrados = DB::table('servidores')
            ->select('id','nome','matricula','cpf')
            ->where(function($w) use ($qLike, $cpfDigits) {
                $w->whereRaw('UPPER(nome) LIKE ?', [$qLike])
                  ->orWhereRaw('UPPER(matricula) LIKE ?', [$qLike]);
                if ($cpfDigits !== '') {
                    $w->orWhere('cpf', 'like', '%'.$cpfDigits.'%');
                }
            })
            ->orderBy('nome')
            ->limit(50)
            ->get();
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl uppercase">Servidores — Frequência</h2>
    </x-slot>

    {{-- Flash persistente --}}
    @if (session('success') || session('error') || $errors->any())
        <div data-flash class="mb-4 rounded border p-3
            {{ session('success') ? 'border-green-600 bg-green-50' : '' }}
            {{ session('error') ? 'border-red-600 bg-red-50' : '' }}
            {{ $errors->any() ? 'border-yellow-600 bg-yellow-50' : '' }}">
            @if (session('success')) <div class="font-semibold text-green-800">{{ session('success') }}</div> @endif
            @if (session('error'))   <div class="font-semibold text-red-800">{{ session('error') }}</div>   @endif
            @if ($errors->any())
                <div class="font-semibold text-yellow-800">Há pendências no formulário.</div>
                <ul class="mt-1 list-inside list-disc text-sm text-yellow-900">
                    @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- CARD: Localizar servidor para registrar frequência --}}
    <div class="rounded-xl bg-gray-800 p-4 shadow">
        <div class="rounded-lg bg-white p-4">
            <form method="GET" action="{{ route('servidores.frequencia.search') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3" autocomplete="off">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium">Localizar servidor (nome, matrícula ou CPF)</label>
                    <input name="q" value="{{ request('q') }}" class="mt-1 w-full rounded border p-2 uppercase" placeholder="EX.: JOÃO, 12345, 111.222.333-44" data-upcase>
                </div>
                <div class="md:col-span-1 flex items-end">
                    <button class="w-full rounded bg-blue-900 px-5 py-2 text-white shadow hover:opacity-95" type="submit">
                        Buscar
                    </button>
                </div>
            </form>

            @php $count = $encontrados->count(); @endphp

            @if($q !== '' && $count === 0)
                <p class="mt-3 text-sm text-gray-600">Nenhum servidor encontrado para “{{ $q }}”.</p>
            @endif

            @if($count >= 1)
                <div class="mt-4 overflow-x-auto">
                    <div class="mb-2 text-sm text-gray-600">Resultados da busca (clique em “Registrar”):</div>
                    <table class="min-w-full text-sm uppercase">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left">Nome</th>
                                <th class="px-3 py-2 text-left">Matrícula</th>
                                <th class="px-3 py-2 text-left">CPF</th>
                                <th class="px-3 py-2 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($encontrados as $r)
                                @php
                                    $cpfDigits = preg_replace('/\D+/', '', (string)($r->cpf ?? ''));
                                    $cpfFmt = $cpfDigits
                                        ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfDigits)
                                        : '—';
                                @endphp
                                <tr class="border-b">
                                    <td class="px-3 py-2">{{ $r->nome }}</td>
                                    <td class="px-3 py-2">{{ $r->matricula ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $cpfFmt }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex justify-end">
                                            <a href="{{ route('servidores.frequencia.show', $r->id) }}" class="rounded border px-3 py-1 hover:bg-gray-50">
                                                Registrar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Máscaras e uppercase --}}
    <script>
        const maskCPF = (v) => v
            .replace(/\D/g,'')
            .replace(/(\d{3})(\d)/,'$1.$2')
            .replace(/(\d{3})(\d)/,'$1.$2')
            .replace(/(\d{3})(\d{1,2})$/,'$1-$2')
            .slice(0,14);

        document.addEventListener('input', (e) => {
            if (e.target.matches('[data-mask="cpf"]')) {
                e.target.value = maskCPF(e.target.value);
            }
            if (e.target.matches('[data-upcase]')) {
                e.target.value = (e.target.value || '').toUpperCase();
            }
        }, { passive: true });
    </script>
</x-app-layout>
