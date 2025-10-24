<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();

            // Identificação
            $table->string('nome', 150)->unique();
            $table->string('sigla', 30)->nullable();

            // Endereço
            $table->string('end_logradouro', 120)->nullable();
            $table->string('end_numero', 10)->nullable();
            $table->string('end_complemento', 60)->nullable();
            $table->string('end_bairro', 80)->nullable();
            $table->string('end_municipio', 80)->nullable();
            $table->string('end_uf', 2)->nullable();
            $table->string('end_cep', 12)->nullable();

            // Classificação
            // porte: PEQUENO | MÉDIO | GRANDE (armazenado em caixa alta no controller)
            $table->string('porte', 10);
            // perfil: conforme dropdown (inclui PPP, CDP, CPP, etc.) — armazenado em caixa alta
            $table->string('perfil', 60);
            // quando perfil = OUTROS, guarda o texto digitado (opcional)
            $table->string('perfil_outro', 120)->nullable();

            // Capacidade (nº de vagas)
            $table->unsignedInteger('capacidade_vagas')->nullable();

            // Mapeamento físico (novo modelo)
            // Lista de itens gerados pelo form: [{ "tipo":"BLOCO", "valor":"1", "valor_extenso":"" }, ...]
            $table->json('mapeamento_json')->nullable();

            // Observações e autoria
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
