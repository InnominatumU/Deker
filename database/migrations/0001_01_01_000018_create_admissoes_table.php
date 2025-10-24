<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissoes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Identificadores básicos
            $table->unsignedBigInteger('unidade_id');               // unidade onde o vínculo foi criado
            $table->unsignedBigInteger('destino_unidade_id')->nullable(); // para transferência/trânsito
            $table->unsignedBigInteger('dados_pessoais_id')->nullable();  // opcional: relação com dados_pessoais
            $table->string('cadpen', 50)->index();                  // ex.: 2025-000123

            // Controle de estado do vínculo
            // tipo do lançamento efetuado nesta linha
            $table->enum('tipo', ['ADMISSAO','TRANSFERENCIA','TRANSITO','DESLIGAMENTO'])->index();
            // se o vínculo ainda está ativo nesta unidade
            $table->boolean('ativo')->default(true)->index();
            // status operacional (ex.: deslocamento entre unidades)
            $table->enum('status', ['ATIVO','EM_DESLOCAMENTO','ENCERRADO'])->default('ATIVO')->index();

            // Dados da admissão/origem
            $table->string('origem', 80)->nullable();               // PC, PM, PF, PRF, UNIDADE PRISIONAL, OUTROS
            $table->string('origem_complemento', 160)->nullable();  // ex.: "12ª DP DE URUAÇU"
            $table->char('uf_origem', 2)->nullable();

            // Motivo de admissão (quando tipo=ADMISSAO)
            $table->string('motivo', 60)->nullable();               // PRISAO_EM_FLAGRANTE, etc.
            $table->string('motivo_descricao', 255)->nullable();

            // Enquadramentos (lista construída no front)
            $table->json('enquadramentos_json')->nullable();

            // Marcação temporal da admissão
            $table->timestamp('admissao_at')->nullable()->index();

            // Desligamento (quando aplicável)
            $table->string('desligamento_tipo', 40)->nullable();        // ALVARA, RELAXAMENTO_DE_PRISAO, TRANSITO, TRANSFERENCIA, OUTROS
            $table->string('desligamento_observacao', 500)->nullable();
            $table->timestamp('desligamento_at')->nullable()->index();

            $table->timestamps();

            // Índices auxiliares
            $table->index(['cadpen','ativo']);
            $table->index(['unidade_id']);
            $table->index(['destino_unidade_id']);
            $table->index(['tipo','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissoes');
    }
};
