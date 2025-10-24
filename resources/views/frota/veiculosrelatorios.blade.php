{{-- resources/views/frota/veiculosrelatorios.blade.php --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">FROTA — RELATÓRIOS</h2></x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto bg-white dark:bg-gray-900 p-6 rounded-xl shadow">
            <form method="GET" action="{{ route('frota.veiculos.relatorios.run') }}" class="uppercase" id="frm-relatorios">
                {{-- Filtros principais --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Data Início</label>
                        <input type="date" name="inicio" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Data Fim</label>
                        <input type="date" name="fim" class="mt-1 w-full rounded border p-2 uppercase">
                    </div>
                    <div class="flex items-end">
                        <div class="w-full">
                            <label class="block text-sm font-medium">Selecionar Tudo</label>
                            <button type="button" id="btn-select-all" class="mt-1 w-full rounded border p-2">MARCAR/DESMARCAR</button>
                        </div>
                    </div>
                </div>

                {{-- Tipos de relatório: agora em CHECKBOXES (multiseleção) --}}
                <div class="mt-6">
                    <label class="block text-sm font-medium mb-2">Tipos de Relatório</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="tipos[]" value="USO" class="rounded border">
                            <span>USO / DIÁRIAS</span>
                        </label>

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="tipos[]" value="ABAST" class="rounded border">
                            <span>ABASTECIMENTOS</span>
                        </label>

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="tipos[]" value="DESLOC" class="rounded border">
                            <span>DESLOCAMENTOS</span>
                        </label>

                        {{-- Manutenções REMOVIDO daqui (foi para Deslocamentos) --}}

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="tipos[]" value="DOCS" class="rounded border">
                            <span>DOCUMENTOS</span>
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 normal-case">Selecione um ou mais tipos para gerar um relatório combinado.</p>
                </div>

                <div class="mt-6 flex gap-3">
                    <button class="px-4 py-2 rounded bg-blue-900 text-white">Gerar</button>
                    <button type="reset" class="px-4 py-2 rounded border">Limpar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function(){
        "use strict";
        const form = document.getElementById('frm-relatorios');
        const btnAll = document.getElementById('btn-select-all');

        const getChecks = () => Array.from(form.querySelectorAll('input[type="checkbox"][name="tipos[]"]'));

        // Botão marcar/desmarcar todos
        btnAll.addEventListener('click', () => {
            const cks = getChecks();
            const anyUnchecked = cks.some(c => !c.checked);
            cks.forEach(c => c.checked = anyUnchecked);
        });

        // Validação: exigir ao menos 1 tipo
        form.addEventListener('submit', (e) => {
            const cks = getChecks();
            if (!cks.some(c => c.checked)) {
                e.preventDefault();
                alert('Selecione ao menos um TIPO DE RELATÓRIO.');
            }
        });
    })();
    </script>
</x-app-layout>
