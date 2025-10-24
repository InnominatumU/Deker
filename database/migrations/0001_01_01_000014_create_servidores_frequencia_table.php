<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cria somente se ainda não existir (útil em ambientes já parcialmente criados)
        if (! Schema::hasTable('servidores_frequencia')) {
            Schema::create('servidores_frequencia', function (Blueprint $table) {
                $table->id();

                $table->foreignId('servidor_id')
                    ->constrained('servidores')
                    ->cascadeOnDelete();

                // Data-base do lançamento
                $table->date('data');

                // Podem ser nulos para registros de ocorrência sem horas
                $table->time('hora_entrada')->nullable();
                $table->time('hora_saida')->nullable();

                // Total de horas do segmento (opcional; pode ser calculado no controller/service)
                // 8,2 dá folga para 999,99h, bem acima do necessário para segmentos diários
                $table->decimal('horas', 8, 2)->default(0);

                // Tipos exibidos/consumidos pela interface
                $table->enum('tipo', ['NORMAL', 'FOLGA', 'LICENCA', 'FERIAS', 'ATESTADO'])->default('NORMAL');

                $table->string('observacoes', 1000)->nullable();

                $table->timestamps();

                // Índices para relatórios e paginação
                $table->index(['servidor_id', 'data']);
                $table->index('tipo');

                // Evita duplicar o MESMO segmento (mesma data + mesma janela de horas)
                $table->unique(
                    ['servidor_id', 'data', 'hora_entrada', 'hora_saida'],
                    'uniq_servidor_data_janela'
                );
            });
        } else {
            // Se a tabela já existir, garantimos colunas/índices mínimos usados pelo sistema
            Schema::table('servidores_frequencia', function (Blueprint $table) {
                // Colunas essenciais (adiciona se não existir)
                if (! Schema::hasColumn('servidores_frequencia', 'servidor_id')) {
                    $table->foreignId('servidor_id')
                        ->after('id')
                        ->constrained('servidores')
                        ->cascadeOnDelete();
                }
                if (! Schema::hasColumn('servidores_frequencia', 'data')) {
                    $table->date('data')->after('servidor_id');
                }
                if (! Schema::hasColumn('servidores_frequencia', 'hora_entrada')) {
                    $table->time('hora_entrada')->nullable()->after('data');
                }
                if (! Schema::hasColumn('servidores_frequencia', 'hora_saida')) {
                    $table->time('hora_saida')->nullable()->after('hora_entrada');
                }
                if (! Schema::hasColumn('servidores_frequencia', 'horas')) {
                    $table->decimal('horas', 8, 2)->default(0)->after('hora_saida');
                }
                if (! Schema::hasColumn('servidores_frequencia', 'tipo')) {
                    $table->enum('tipo', ['NORMAL', 'FOLGA', 'LICENCA', 'FERIAS', 'ATESTADO'])
                          ->default('NORMAL')
                          ->after('horas');
                }
                if (! Schema::hasColumn('servidores_frequencia', 'observacoes')) {
                    $table->string('observacoes', 1000)->nullable()->after('tipo');
                }

                // Índices
                // (alguns provedores não expõem API para "hasIndex", então tentamos criar com try/catch no artisan, mas aqui só declaramos)
                $table->index(['servidor_id', 'data']);
                $table->index('tipo');

                // Unique do segmento
                try {
                    $table->unique(
                        ['servidor_id', 'data', 'hora_entrada', 'hora_saida'],
                        'uniq_servidor_data_janela'
                    );
                } catch (\Throwable $e) {
                    // Se já existir, ignoramos silenciosamente
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('servidores_frequencia');
    }
};
