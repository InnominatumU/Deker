<?php
// database/migrations/0001_01_01_000010_create_veiculos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table) {
            $table->id();

            // Identificação principal
            $table->string('placa', 10)->unique();
            $table->string('renavam', 20)->nullable()->unique();
            // manter 25 para casar com validação do Controller (max:25)
            $table->string('chassi', 25)->nullable()->unique();

            // Descrição
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->unsignedSmallInteger('ano_fabricacao')->nullable();
            $table->unsignedSmallInteger('ano_modelo')->nullable();
            $table->string('cor', 30)->nullable();

            // Alinhado ao form/controller
            // (antes havia 'categoria'; padronizamos como 'tipo' para casar com o POST)
            $table->string('tipo', 30)->nullable();              // ex.: CARRO, MOTO, CAMINHAO
            $table->string('propriedade', 20)->nullable();       // PROPRIA | ALUGADA | TERCEIRIZADA

            // Operação
            $table->string('tipo_combustivel', 20)->nullable();
            $table->unsignedSmallInteger('capacidade_tanque')->nullable(); // litros
            $table->unsignedInteger('hodometro_atual')->nullable();        // km

            // Vínculo (opcional) com unidades
            $table->foreignId('unidade_id')->nullable()
                  ->constrained('unidades')->nullOnDelete();

            // Status operacional — casar com o form (ex.: DISPONIVEL)
            // Mantemos string livre; default DISPONIVEL.
            $table->string('status', 20)->default('DISPONIVEL');

            // JSON livre para documentos diversos (mantido para futuro)
            $table->json('documentos_json')->nullable();

            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();

            // Índices úteis
            $table->index(['modelo']);
            $table->index(['status']);
            $table->index(['tipo']);
            $table->index(['propriedade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos');
    }
};
