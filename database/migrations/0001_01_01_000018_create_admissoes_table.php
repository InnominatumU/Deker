<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admissoes');

        Schema::create('admissoes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Identificadores básicos
            $table->unsignedBigInteger('unidade_id');                     // unidade onde o registro é lançado
            $table->unsignedBigInteger('destino_unidade_id')->nullable(); // destino p/ transferência/trânsito
            $table->unsignedBigInteger('dados_pessoais_id')->nullable();  // opcional
            $table->string('cadpen', 50)->index();

            // Controle de estado
            $table->enum('tipo', ['ADMISSAO','TRANSFERENCIA','TRANSITO','DESLIGAMENTO'])->index();
            $table->boolean('ativo')->default(true)->index(); // somente ADMISSAO ativa
            $table->enum('status', ['ATIVO','EM_DESLOCAMENTO','ENCERRADO'])->default('ATIVO')->index();

            // ADMISSÃO — origem como UNIDADE (dropdown)
            $table->unsignedBigInteger('origem_unidade_id')->nullable()->index();

            // (campos textuais antigos podem ser mantidos caso úteis; aqui mantive como opcionais)
            $table->string('origem', 80)->nullable();
            $table->string('origem_complemento', 160)->nullable();
            $table->char('uf_origem', 2)->nullable();

            // Motivo (apenas para ADMISSAO)
            $table->string('motivo', 60)->nullable();
            $table->string('motivo_descricao', 255)->nullable();

            // Enquadramentos (apenas para ADMISSAO)
            $table->json('enquadramentos_json')->nullable();

            // Marcação temporal da admissão
            $table->timestamp('admissao_at')->nullable()->index();

            // ------------------------ TRANSFERÊNCIA ------------------------
            $table->string('transferencia_motivo', 120)->nullable();
            $table->timestamp('transferencia_inicio_at')->nullable()->index();
            $table->timestamp('transferencia_aceite_at')->nullable()->index();
            $table->unsignedInteger('transferencia_duracao_min')->nullable();

            // -------------------------- TRÂNSITO ---------------------------
            $table->string('transito_motivo', 120)->nullable();
            $table->timestamp('transito_inicio_at')->nullable()->index();
            $table->timestamp('transito_aceite_at')->nullable()->index();
            $table->date('transito_prev_retorno_em')->nullable()->index();
            $table->timestamp('transito_retorno_inicio_at')->nullable()->index();
            $table->timestamp('transito_retorno_conclusao_at')->nullable()->index();
            $table->unsignedInteger('transito_ida_duracao_min')->nullable();
            $table->unsignedInteger('transito_retorno_duracao_min')->nullable();

            // ------------------------ DESLIGAMENTO -------------------------
            $table->string('desligamento_tipo', 40)->nullable();
            $table->unsignedBigInteger('desligamento_destino_unidade_id')->nullable();
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
