<?php

namespace App\Models\Frota;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VeiculoEvento extends Model
{
    use HasFactory;

    protected $table = 'veiculos_eventos';

    protected $fillable = [
        'veiculo_id',
        'tipo',          // USO, MANUTENCAO, ABASTECIMENTO, SINISTRO, DOCUMENTO, MULTA, TRANSFERENCIA, ALOCACAO
        'data_evento',
        'hodometro',
        'descricao',
        'custo',
        'extras_json',
    ];

    protected $casts = [
        'veiculo_id'  => 'integer',
        'data_evento' => 'datetime',
        'hodometro'   => 'integer',
        'custo'       => 'decimal:2',
        'extras_json' => 'array',
    ];

    public const TIPOS = [
        'USO'            => 'USO',
        'MANUTENCAO'     => 'MANUTENÇÃO',
        'ABASTECIMENTO'  => 'ABASTECIMENTO',
        'SINISTRO'       => 'SINISTRO',
        'DOCUMENTO'      => 'DOCUMENTO',
        'MULTA'          => 'MULTA',
        'TRANSFERENCIA'  => 'TRANSFERÊNCIA',
        'ALOCACAO'       => 'ALOCAÇÃO',
    ];

    public function veiculo()
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }
}
