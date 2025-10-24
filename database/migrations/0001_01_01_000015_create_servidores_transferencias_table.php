<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('servidores_transferencias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('servidor_id')
                ->constrained('servidores')
                ->cascadeOnDelete();

            // origem pode ser nula (ex.: primeiro vínculo registrado)
            $table->foreignId('unidade_origem_id')
                ->nullable()
                ->constrained('unidades')
                ->nullOnDelete();

            $table->foreignId('unidade_destino_id')
                ->constrained('unidades')
                ->cascadeOnDelete();

            $table->date('data_transferencia');
            $table->string('observacoes', 1000)->nullable();

            $table->timestamps();

            $table->index(['servidor_id', 'data_transferencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servidores_transferencias');
    }
};
