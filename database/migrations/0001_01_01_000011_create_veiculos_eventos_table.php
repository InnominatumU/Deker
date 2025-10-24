<?php
// database/migrations/0001_01_01_000011_create_veiculos_eventos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('veiculos_eventos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('veiculo_id')
                ->constrained('veiculos')
                ->cascadeOnDelete();

            // tipo básico para agrupar: USO, ABASTECIMENTO, DESLOCAMENTO, DOCUMENTO, MULTA, etc.
            $table->string('tipo', 30)->index();

            // data/hora do evento
            $table->dateTime('data_evento')->index();

            // carga útil flexível por tipo (conforme os formulários):
            // - USO: checklist, avarias_json, resp_id...
            // - ABASTECIMENTO: litros, valor_total, odometro_km...
            // - DESLOCAMENTO: finalidade, passageiros_json, escoltados_json, escolta_outros_tipo...
            // - DOCUMENTO: tipo, arquivo (path), observações...
            // - MULTA (aninhado em DOCUMENTO ou próprio tipo): número, órgão, local, prazo, valor, motorista etc.
            $table->json('payload_json')->nullable();

            // observação livre
            $table->text('observacoes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos_eventos');
    }
};
