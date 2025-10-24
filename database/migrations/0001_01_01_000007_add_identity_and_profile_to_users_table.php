<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Atenção: o método after() é específico de MySQL/MariaDB. Em outros SGBDs ele pode ser ignorado.

            if (!Schema::hasColumn('users','matricula')) {
                $table->string('matricula', 40)->nullable()->unique()->after('email');
            }

            if (!Schema::hasColumn('users','cpf')) {
                // CPF só dígitos, 11 chars
                $table->string('cpf', 11)->nullable()->unique()->after('matricula');
            }

            if (!Schema::hasColumn('users','username')) {
                $table->string('username', 50)->nullable()->unique()->after('cpf');
            }

            if (!Schema::hasColumn('users','perfil_code')) {
                $table->string('perfil_code', 3)->nullable()->index()->after('username');
            }

            if (!Schema::hasColumn('users','perfil')) {
                $table->string('perfil', 60)->nullable()->index()->after('perfil_code');
            }

            if (!Schema::hasColumn('users','is_active')) {
                $table->boolean('is_active')->default(true)->index()->after('perfil');
            }

            if (!Schema::hasColumn('users','last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Dropa só o que existe (evita erros em ambientes onde algo já foi removido)
            foreach (['matricula','cpf','username','perfil_code','perfil','is_active','last_login_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
