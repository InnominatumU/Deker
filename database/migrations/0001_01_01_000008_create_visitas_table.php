<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Visitantes (pessoa que visita)
        Schema::create('visitantes', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo', 150);
            $table->string('cpf', 20)->nullable()->unique();  // pode não ter
            $table->string('rg', 30)->nullable();
            $table->string('oab', 30)->nullable();            // exigido só para visita jurídica
            $table->timestamps();
        });

        // 2) Visitas (o evento da visita)
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitante_id')->constrained('visitantes')->cascadeOnDelete();

            // Tipo de visita
            $table->string('tipo', 30); // SOCIAL | ASSISTIDA | JURIDICA | RELIGIOSA | AUTORIDADES | OUTRAS

            // Destino da visita (indivíduos ou unidade)
            $table->string('destino', 20)->default('INDIVIDUOS'); // INDIVIDUOS | UNIDADE

            // ⚠️ FK condicional para evitar quebra se 'unidades' não existir ainda
            $table->unsignedBigInteger('unidade_id')->nullable()->index();

            // Campos condicionais por tipo
            $table->string('religiao', 60)->nullable();           // RELIGIOSA
            $table->string('autoridade_cargo', 80)->nullable();   // AUTORIDADES
            $table->string('autoridade_orgao', 120)->nullable();  // AUTORIDADES
            $table->text('descricao_outros')->nullable();         // OUTRAS

            // Observações gerais (opcional)
            $table->text('observacoes')->nullable();

            // (opcional) Quem cadastrou
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();
        });

        // Tenta aplicar a FK para 'unidades' se a tabela já existir
        if (Schema::hasTable('unidades')) {
            Schema::table('visitas', function (Blueprint $table) {
                $table->foreign('unidade_id')
                      ->references('id')
                      ->on('unidades')
                      ->nullOnDelete();
            });
        }

        // 3) Pivot visita <-> indivíduos
        Schema::create('visita_individuo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visita_id')->constrained('visitas')->cascadeOnDelete();
            $table->unsignedBigInteger('individuo_id'); // tabela pode variar; FK condicional abaixo
            $table->string('parentesco', 40)->nullable(); // para Social/Assistida (etc.)
            $table->timestamps();

            $table->index(['visita_id', 'individuo_id']);

            // FK suave (se existir tabela individuos)
            if (Schema::hasTable('individuos')) {
                $table->foreign('individuo_id')
                      ->references('id')
                      ->on('individuos')
                      ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visita_individuo');
        // Remover a FK condicional de 'unidade_id' se existir, antes de dropar 'visitas'
        if (Schema::hasTable('visitas')) {
            Schema::table('visitas', function (Blueprint $table) {
                try { $table->dropForeign(['unidade_id']); } catch (\Throwable $e) {}
            });
        }
        Schema::dropIfExists('visitas');
        Schema::dropIfExists('visitantes');
    }
};
