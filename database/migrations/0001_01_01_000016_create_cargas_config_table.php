<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelas criadas neste arquivo:
     * - cargas_horarias: catálogo de tipos semanais e plantões (sem view)
     * - cargos: catálogo de cargos
     * - funcoes: catálogo de funções
     *
     * Observações:
     * - Todas possuem unidade_id opcional (para catálogos por unidade). Se não usar por unidade, deixe null.
     * - Unicidades são por (unidade_id, nome).
     */

    public function up(): void
    {
        // ---------- 1) CARGAS / PLANTÕES ----------
        // Catálogo único para "carga horária semanal" e "plantões"
        // Ex.: tipo_semana="40H SEMANAL", nome_plantao="DIARISTA", carga_horaria_plantao=8, hora_entrada="07:00", intervalo_minutos=60
        Schema::create('cargas_horarias', function (Blueprint $table) {
            $table->id();

            // Opcional: catálogo por unidade
            if (Schema::hasTable('unidades')) {
                $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('unidade_id')->nullable()->index();
            }

            // Tipo semanal (ex.: "40H SEMANAL", "30H SEMANAL", "20H SEMANAL")
            $table->string('tipo_semana', 60)->nullable()->index();

            // Nome do plantão (ex.: "24X72", "12X36", "DIARISTA", "NOTURNO")
            $table->string('nome_plantao', 60)->nullable()->index();

            // Duração total do plantão, em horas inteiras (0..168). Para diarista, usar 8, por exemplo.
            $table->unsignedSmallInteger('carga_horaria_plantao')->nullable();

            // Hora padrão de entrada (opcional)
            $table->time('hora_entrada')->nullable();

            // Intervalo padrão do plantão (minutos). Ex.: 60 para diarista com 1h de almoço.
            $table->unsignedSmallInteger('intervalo_minutos')->default(0);

            // Ativo/inativo para manter histórico sem excluir
            $table->boolean('ativo')->default(true)->index();

            $table->timestamps();

            // Evita duplicidades por unidade: mesmo "tipo_semana" + "nome_plantao"
            $table->unique(['unidade_id', 'tipo_semana', 'nome_plantao'], 'uq_cargas_unidade_tipo_plantao');
        });

        // ---------- 2) CARGOS ----------
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();

            if (Schema::hasTable('unidades')) {
                $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('unidade_id')->nullable()->index();
            }

            $table->string('nome', 120);
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();

            $table->unique(['unidade_id', 'nome'], 'uq_cargos_unidade_nome');
        });

        // ---------- 3) FUNÇÕES ----------
        Schema::create('funcoes', function (Blueprint $table) {
            $table->id();

            if (Schema::hasTable('unidades')) {
                $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('unidade_id')->nullable()->index();
            }

            $table->string('nome', 120);
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();

            $table->unique(['unidade_id', 'nome'], 'uq_funcoes_unidade_nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcoes');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('cargas_horarias');
    }
};
