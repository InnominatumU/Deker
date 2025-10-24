<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identificacoes_visuais', function (Blueprint $table) {
            $table->id();

            // relação 1:1 com dados_pessoais
            $table->foreignId('dados_pessoais_id')
                  ->constrained('dados_pessoais')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete()
                  ->unique();

            // chaves usuais
            $table->string('cadpen', 50)->unique();
            $table->string('etnia', 50)->nullable();

            // lista mestre usada no form (ex.: { local, tipo, descricao })
            $table->json('sinais_compostos')->nullable();

            // biometria: array com 10 dedos [{ dedo, imagem, status }]
            $table->json('biometria_json')->nullable();

            // URLs das fotos 3x4
            $table->string('foto_le_url', 2048)->nullable();
            $table->string('foto_frontal_url', 2048)->nullable();
            $table->string('foto_ld_url', 2048)->nullable();

            // observações
            $table->text('observacoes')->nullable();

            $table->timestamps();

            // índices auxiliares (obs.: unique em cadpen já cria índice)
            $table->index('cadpen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identificacoes_visuais');
    }
};
