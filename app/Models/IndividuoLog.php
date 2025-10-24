<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndividuoLog extends Model
{
    use HasFactory;

    protected $table = 'individuos_logs';

    protected $fillable = [
        'individuo_id',
        'user_id',
        'section',
        'action',
        'changes',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];
}
