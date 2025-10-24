{{-- resources/views/gestao/index.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h1 class="font-bold text-xl">GESTÃO DE INDIVÍDUOS</h1>
  </x-slot>

  <div class="p-6 space-y-8">
    <p class="text-gray-700">Escolha uma opção abaixo.</p>

    {{-- NOVO CADASTRO --}}
    <section aria-label="Novo cadastro" class="space-y-3">
      <h2 class="font-semibold text-lg">Novo cadastro</h2>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {{-- Dados Pessoais (CREATE) --}}
        <a href="{{ route('gestao.dados.create') }}"
           class="block rounded border p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
           aria-label="Abrir formulário de Dados Pessoais">
          <h3 class="font-semibold">DADOS PESSOAIS</h3>
          <p class="text-sm text-gray-600">Novo cadastro</p>
        </a>

        {{-- Documentos (CREATE) --}}
        <a href="{{ route('gestao.documentos.create') }}"
           class="block rounded border p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
           aria-label="Abrir formulário de Documentos">
          <h3 class="font-semibold">DOCUMENTOS</h3>
          <p class="text-sm text-gray-600">Vincular documentos</p>
        </a>

        {{-- Identificação Visual (CREATE) --}}
        <a href="{{ route('gestao.identificacao.visual.create') }}"
           class="block rounded border p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
           aria-label="Abrir formulário de Identificação Visual">
          <h3 class="font-semibold">IDENTIFICAÇÃO VISUAL</h3>
          <p class="text-sm text-gray-600">Características visuais</p>
        </a>
      </div>
    </section>

    {{-- BUSCAR / EDITAR / IMPRIMIR --}}
    <section aria-label="Buscar, editar e imprimir" class="space-y-3">
      <h2 class="font-semibold text-lg">Buscar / Editar / Imprimir</h2>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Buscar Dados Pessoais --}}
        <a href="{{ route('gestao.dados.search') }}"
           class="block rounded border p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
           aria-label="Buscar/editar Dados Pessoais">
          <h3 class="font-semibold">BUSCAR — DADOS PESSOAIS</h3>
          <p class="text-sm text-gray-600">Localizar e editar cadastros</p>
        </a>

        {{-- Buscar Documentos --}}
        <a href="{{ route('gestao.documentos.search') }}"
           class="block rounded border p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
           aria-label="Buscar/editar Documentos">
          <h3 class="font-semibold">BUSCAR — DOCUMENTOS</h3>
          <p class="text-sm text-gray-600">Localizar por pessoa/CadPen</p>
        </a>

        {{-- Buscar Identificação Visual --}}
        <a href="{{ route('gestao.identificacao.visual.search') }}"
           class="block rounded border p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
           aria-label="Buscar/editar Identificação Visual">
          <h3 class="font-semibold">BUSCAR — IDENTIFICAÇÃO VISUAL</h3>
          <p class="text-sm text-gray-600">Localizar por pessoa/CadPen</p>
        </a>

        {{-- Ficha do Indivíduo: vai para /gestao/ficha (redireciona à busca) --}}
        <a href="{{ route('gestao.ficha.index') }}"
           class="block rounded border p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
           aria-label="Ficha do Indivíduo">
          <h3 class="font-semibold">FICHA DO INDIVÍDUO</h3>
          <p class="text-sm text-gray-600">Escolha a pessoa e gere a ficha</p>
        </a>
      </div>
    </section>
  </div>
</x-app-layout>
