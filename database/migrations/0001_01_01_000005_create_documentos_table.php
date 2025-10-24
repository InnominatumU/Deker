<?php
// database/migrations/0001_01_01_000005_create_documentos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('documentos')) {
            Schema::create('documentos', function (Blueprint $table) {
                $table->id();

                // relação 1:1 com dados_pessoais
                $table->foreignId('dados_pessoais_id')
                    ->constrained('dados_pessoais')
                    ->cascadeOnDelete()
                    ->unique(); // 1 registro de documentos por pessoa

                // Campos de documentos
                $table->char('cpf', 11)->nullable()->unique('documentos_cpf_unique');

                $table->string('rg_numero', 30)->nullable();
                $table->string('rg_orgao_emissor', 30)->nullable();
                $table->char('rg_uf', 2)->nullable();
                $table->index(['rg_numero','rg_orgao_emissor','rg_uf'], 'documentos_rg_comp');

                $table->string('prontuario', 120)->nullable();
                $table->json('outros_documentos')->nullable();

                // Observações específicas desta seção
                $table->text('observacoes')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
