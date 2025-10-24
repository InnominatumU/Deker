<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Individuo extends Model
{
    use HasFactory;

    protected $table = 'individuos';

    protected $fillable = [
        'cadpen',
        'cadpen_number',
        'wizard_stage',
        'is_draft',
        'nome_completo',
        'nome_social',
        'genero_sexo',
        'data_nascimento',
        'mae',
        'pai',
        'nacionalidade',
        'naturalidade_uf',
        'naturalidade_municipio',
        'estado_civil',
        'escolaridade_nivel',
        'escolaridade_situacao',
        'profissao',
        'end_logradouro',
        'end_numero',
        'end_complemento',
        'end_bairro',
        'end_municipio',
        'end_uf',
        'end_cep',
        'telefone_principal',
        'telefones_adicionais',
        'email',
        'obito',
        'data_obito',
        'observacoes',
        'created_by',
        'updated_by',
        'canceled_at',
        'canceled_by',
    ];

    protected $casts = [
        'is_draft'             => 'boolean',
        'data_nascimento'      => 'date',
        'obito'                => 'boolean',
        'data_obito'           => 'date',
        'telefones_adicionais' => 'array',
        'canceled_at'          => 'datetime',
    ];
}
