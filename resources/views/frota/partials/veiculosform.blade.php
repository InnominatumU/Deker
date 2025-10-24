{{-- resources/views/frota/partials/veiculosform.blade.php --}}
@php
    // Evita "variável não definida"
    /** @var \stdClass|array|null $veiculo */
    $VEICULO_SAFE = isset($veiculo) ? $veiculo : null;

    // Helper: old() -> $veiculo -> default
    $val = function (string $key, $default = '') use ($VEICULO_SAFE) {
        return old($key, (string) data_get($VEICULO_SAFE, $key, $default));
    };

    // Helper simples para selected
    $is = function ($a, $b) {
        return (string)$a === (string)$b ? 'selected' : '';
    };
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 uppercase">
    {{-- Identificação --}}
    <div>
        <label class="block text-sm font-medium">Placa *</label>
        <input name="placa"
               value="{{ strtoupper($val('placa')) }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               maxlength="10" required
               placeholder="ABC1D23"
               inputmode="latin-prose" autocomplete="off">
        @error('placa')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">RENAVAM</label>
        <input name="renavam"
               value="{{ strtoupper($val('renavam')) }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               maxlength="20" placeholder="OPCIONAL"
               inputmode="numeric" autocomplete="off">
        @error('renavam')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Chassi</label>
        {{-- ATENÇÃO: migration define chassi(25). Controller valida 30. Vamos alinhar o Controller depois. --}}
        <input name="chassi"
               value="{{ strtoupper($val('chassi')) }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               maxlength="25" placeholder="OPCIONAL">
        @error('chassi')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Especificações --}}
    <div>
        <label class="block text-sm font-medium">Marca</label>
        <input name="marca"
               value="{{ strtoupper($val('marca')) }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               maxlength="60" placeholder="FIAT, VW, GM...">
        @error('marca')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Modelo</label>
        <input name="modelo"
               value="{{ strtoupper($val('modelo')) }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               maxlength="80" placeholder="UNO, GOL, ONIX...">
        @error('modelo')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Tipo</label>
        <input name="tipo"
               value="{{ strtoupper($val('tipo')) }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               maxlength="40" placeholder="CARRO, CAMINHÃO, VAN...">
        @error('tipo')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Ano Fabricação</label>
        <input type="number" name="ano_fabricacao"
               value="{{ $val('ano_fabricacao') }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               min="1900" max="2100" inputmode="numeric" placeholder="AAAA">
        @error('ano_fabricacao')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Ano Modelo</label>
        <input type="number" name="ano_modelo"
               value="{{ $val('ano_modelo') }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               min="1900" max="2100" inputmode="numeric" placeholder="AAAA">
        @error('ano_modelo')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Cor</label>
        <input name="cor"
               value="{{ strtoupper($val('cor')) }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               maxlength="30" placeholder="BRANCO, PRETO, PRATA...">
        @error('cor')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Campos presentes na migration de veículos (opcionais na UI) --}}
    <div>
        <label class="block text-sm font-medium">Categoria</label>
        <input name="categoria"
               value="{{ strtoupper($val('categoria')) }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               maxlength="30" placeholder="PASSEIO, UTILITÁRIO, LEVE...">
        {{-- Campo não está no validateVeiculo ainda. Vamos alinhar no Controller se necessário. --}}
    </div>

    <div>
        <label class="block text-sm font-medium">Combustível</label>
        <select name="tipo_combustivel" class="mt-1 w-full rounded border p-2 uppercase">
            @php $comb = strtoupper($val('tipo_combustivel')); @endphp
            <option value="" {{ $is($comb,'') }}>-SELECIONE-</option>
            @foreach (['GASOLINA','ETANOL','DIESEL','FLEX','GNV','ELETRICO','HIBRIDO'] as $opt)
                <option value="{{ $opt }}" {{ $is($comb,$opt) }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium">Capacidade do tanque (L)</label>
        <input type="number" name="capacidade_tanque"
               value="{{ $val('capacidade_tanque') }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               min="0" max="999" inputmode="numeric" placeholder="ex.: 45">
    </div>

    {{-- Classificação / Status --}}
    <div>
        <label class="block text-sm font-medium">Propriedade *</label>
        {{-- ATENÇÃO: este campo é exigido no Controller, mas NÃO existe na migration atual.
             Próximo passo: ou adicionamos a coluna na migration, ou retiramos do Controller. --}}
        <select name="propriedade" class="mt-1 w-full rounded border p-2 uppercase" required>
            @php $prop = strtoupper($val('propriedade')); @endphp
            <option value="" {{ $is($prop,'') }}>-SELECIONE-</option>
            @foreach (['PROPRIA','ALUGADA','TERCEIRIZADA'] as $opt)
                <option value="{{ $opt }}" {{ $is($prop,$opt) }}>{{ $opt }}</option>
            @endforeach
        </select>
        @error('propriedade')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Status *</label>
        {{-- Controller usa DISPONIVEL | MANUTENCAO | INATIVO.
             Na migration o default é "ATIVO". Vamos alinhar no Controller em seguida. --}}
        <select name="status" class="mt-1 w-full rounded border p-2 uppercase" required>
            @php $st = strtoupper($val('status','DISPONIVEL')); @endphp
            <option value="" {{ $is($st,'') }}>-SELECIONE-</option>
            @foreach (['DISPONIVEL','MANUTENCAO','INATIVO'] as $opt)
                <option value="{{ $opt }}" {{ $is($st,$opt) }}>{{ $opt }}</option>
            @endforeach
        </select>
        @error('status')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium">Odômetro (km)</label>
        {{-- Mapeia para hodometro_atual no insert/update --}}
        <input type="number" name="odometro_km"
               value="{{ $val('odometro_km') }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               min="0" max="2000000" inputmode="numeric" placeholder="0">
        @error('odometro_km')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Vínculo opcional com unidade (migration tem unidade_id) --}}
    <div>
        <label class="block text-sm font-medium">Unidade (ID)</label>
        {{-- Simples numérico por enquanto; se a tela receber $unidades, trocamos por <select>. --}}
        <input type="number" name="unidade_id"
               value="{{ $val('unidade_id') }}"
               class="mt-1 w-full rounded border p-2 uppercase"
               min="1" inputmode="numeric" placeholder="OPCIONAL">
    </div>

    {{-- Observações --}}
    <div class="md:col-span-3">
        <label class="block text-sm font-medium">Observações</label>
        <textarea name="observacoes" rows="3"
                  class="mt-1 w-full rounded border p-2 uppercase"
                  placeholder="INFORMAÇÕES GERAIS, ACESSÓRIOS, CONDIÇÕES...">{{ strtoupper($val('observacoes')) }}</textarea>
        @error('observacoes')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
</div>
