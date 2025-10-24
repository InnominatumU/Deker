<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('servidores', function (Blueprint $table) {
            $table->id();

            // Identificação
            $table->string('nome', 150);
            $table->string('cpf', 14)->unique();        // guarda somente dígitos no controller
            $table->string('matricula', 40)->unique();

            // Unidade atual (opcional; seu controller já checa com Schema::hasColumn)
            $table->foreignId('unidade_id')
                ->nullable()
                ->constrained('unidades')
                ->nullOnDelete();

            // Dados funcionais consolidados (de "Configurar Carga Horária" ou fallback do form)
            $table->string('cargo_funcao', 120);        // ex.: "AGENTE / CHEFE DE EQUIPE"
            $table->unsignedTinyInteger('carga_horaria'); // horas/semana (1..80)
            $table->enum('plantao', ['SIM', 'NAO'])->default('NAO');

            // Situação
            $table->boolean('ativo')->default(true);
            $table->string('motivo_inatividade', 1000)->nullable();

            // Observações gerais (até 1000 chars no seu validate; aqui uso text para folga)
            $table->text('observacoes')->nullable();

            $table->timestamps();

            // Índices auxiliares para busca
            $table->index(['nome']);
            $table->index(['matricula']);
            $table->index(['cpf']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servidores');
    }
};
