<?php
// app/Models/Frota/Veiculo.php

namespace App\Models\Frota;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Veiculo extends Model
{
    use HasFactory;

    protected $table = 'veiculos';

    protected $fillable = [
        'placa',
        'renavam',
        'chassi',
        'marca',
        'modelo',
        'tipo',
        'ano_fabricacao',
        'ano_modelo',
        'cor',
        'propriedade',      // PROPRIA | ALUGADA | TERCEIRIZADA
        'status',           // DISPONIVEL | MANUTENCAO | INATIVO
        'hodometro_atual',
        'observacoes',
        'created_by',
    ];

    protected $casts = [
        'ano_fabricacao' => 'integer',
        'ano_modelo'     => 'integer',
        'hodometro_atual'=> 'integer',
        'created_by'     => 'integer',
    ];

    // Se no futuro criarmos models para tabelas operacionais,
    // estes relacionamentos ficam prontos:
    // public function deslocamentos()
    // {
    //     return $this->hasMany(Deslocamento::class, 'placa', 'placa');
    // }
    // public function abastecimentos()
    // {
    //     return $this->hasMany(Abastecimento::class, 'placa', 'placa');
    // }
    // public function documentos()
    // {
    //     return $this->hasMany(VeiculoDocumento::class, 'placa', 'placa');
    // }
}
