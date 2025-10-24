<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pool de números do CadPen
        Schema::create('cadpen_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('number')->unique()->index();   // 1,2,3...
            $table->string('status', 20)->default('available')->index(); // available|reserved|assigned
            $table->timestamps();

            $table->index(['status', 'number']); // ajuda a pegar o menor disponível
        });

        // Reservas do CadPen (1:1 com um número do pool)
        Schema::create('cadpen_reservas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cadpen_number_id')->unique(); // cada número só pode ter 1 reserva ativa/histórica
            $table->string('cadpen', 50)->unique();                    // ex.: CADPEN-2025-000123
            $table->string('status', 20)->default('reservado')->index(); // reservado|consumido|cancelado|expirado
            $table->dateTime('expires_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('cadpen_number_id')->references('id')->on('cadpen_numbers')->cascadeOnDelete();
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadpen_reservas');
        Schema::dropIfExists('cadpen_numbers');
    }
};
