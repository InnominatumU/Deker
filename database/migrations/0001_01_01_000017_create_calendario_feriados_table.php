<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendario_feriados', function (Blueprint $table) {
            $table->id();
            $table->date('data'); // dia do evento
            $table->enum('tipo', [
                'PONTO_FACULTATIVO',
                'FERIADO_MUNICIPAL',
                'FERIADO_ESTADUAL',
                'FERIADO_FEDERAL',
            ]);
            $table->string('titulo', 120)->nullable(); // opcional (ex.: "Corpus Christi")
            $table->timestamps();

            $table->index('data');
            $table->unique(['data','tipo']); // permite múltiplos tipos no ano, mas evita duplicar o mesmo tipo no mesmo dia
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendario_feriados');
    }
};
