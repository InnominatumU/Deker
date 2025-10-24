<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('dados_pessoais')) {
            Schema::create('dados_pessoais', function (Blueprint $table) {
                $table->id();

                // Identificadores
                $table->string('cadpen', 50)->unique();                 // unique já cria índice
                $table->unsignedBigInteger('cadpen_number')->unique();

                // Fluxo do wizard / rascunho
                $table->string('wizard_stage', 20)->default('basicas')->index(); // basicas|documentos|identificacao|concluido
                $table->boolean('is_draft')->default(true)->index();

                // Dados principais
                $table->string('nome_completo', 200)->index();
                $table->string('nome_social', 200)->nullable();
                $table->string('alcunha', 200)->nullable();
                $table->string('genero_sexo', 30)->nullable();
                $table->date('data_nascimento')->nullable();

                // Filiação
                $table->string('mae', 200)->index();
                $table->string('pai', 200)->nullable();

                // Nacionalidade & Naturalidade
                $table->string('nacionalidade', 60)->nullable();
                $table->string('naturalidade_uf', 2)->nullable()->index();
                $table->string('naturalidade_municipio', 120)->nullable()->index();

                // Estado civil, escolaridade, profissão
                $table->string('estado_civil', 40)->nullable();
                $table->string('escolaridade_nivel', 60)->nullable();
                $table->string('escolaridade_situacao', 60)->nullable();
                $table->string('profissao', 120)->nullable();

                // Endereço & Contatos
                $table->string('end_logradouro', 200)->nullable();
                $table->string('end_numero', 20)->nullable();
                $table->string('end_complemento', 120)->nullable();
                $table->string('end_bairro', 120)->nullable();
                $table->string('end_municipio', 120)->nullable()->index();
                $table->string('end_uf', 2)->nullable()->index();
                $table->string('end_cep', 15)->nullable()->index();

                $table->string('telefone_principal', 30)->nullable();
                $table->json('telefones_adicionais')->nullable();
                $table->string('email', 190)->nullable()->index();

                // Óbito
                $table->boolean('obito')->default(false)->index();
                $table->date('data_obito')->nullable();

                // Auditoria (liga com users)
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                // Timestamps
                $table->timestamps();

                // Cancelamento
                $table->dateTime('canceled_at')->nullable()->index();
                $table->foreignId('canceled_by')->nullable()->constrained('users')->nullOnDelete();

                $table->text('observacoes')->nullable();

                // Índice composto útil
                $table->index(['nome_completo', 'mae']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dados_pessoais');
    }
};
