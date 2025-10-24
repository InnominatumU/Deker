<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Campos liberados para atribuição em massa.
     *
     * OBS: adicionamos os identificadores extras e perfil.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'matricula',
        'cpf',
        'username',
        'perfil_code',
        'perfil',
        'is_active',
        'last_login_at',
    ];

    /**
     * Campos ocultos na serialização.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Atributos adicionados automaticamente ao serializar (JSON/array).
     * Não afeta as views Blade.
     */
    protected $appends = [
        'cpf_masked',
    ];

    /**
     * Casts de atributos.
     *
     * Importante: 'password' => 'hashed' já faz o hash automaticamente.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'last_login_at'     => 'datetime',
        ];
    }

    /* =========================================================
     |  Normalizadores / Mutators
     |=========================================================*/

    public function setEmailAttribute($v): void
    {
        $this->attributes['email'] = $v ? mb_strtolower(trim($v), 'UTF-8') : null;
    }

    public function setUsernameAttribute($v): void
    {
        $this->attributes['username'] = $v ? mb_strtolower(trim($v), 'UTF-8') : null;
    }

    public function setMatriculaAttribute($v): void
    {
        $this->attributes['matricula'] = $v ? trim($v) : null;
    }

    public function setPerfilAttribute($v): void
    {
        $this->attributes['perfil'] = $v ? mb_strtoupper(trim($v), 'UTF-8') : null;
    }

    public function setPerfilCodeAttribute($v): void
    {
        $this->attributes['perfil_code'] = $v ? mb_strtoupper(trim($v), 'UTF-8') : null;
    }

    /**
     * CPF: armazena somente dígitos (11).
     */
    public function setCpfAttribute($v): void
    {
        if ($v === null) {
            $this->attributes['cpf'] = null;
            return;
        }
        $d = preg_replace('/\D+/', '', (string) $v);
        $this->attributes['cpf'] = $d !== '' ? $d : null;
    }

    /* =========================================================
     |  Accessors úteis
     |=========================================================*/

    /**
     * Exibe CPF mascarado (000.000.000-00).
     */
    public function getCpfMaskedAttribute(): ?string
    {
        $c = $this->cpf;
        if (!$c || strlen($c) !== 11) return $this->cpf;
        return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9, 2);
    }

    /* =========================================================
     |  Scopes auxiliares (opcionais)
     |=========================================================*/

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Filtra por um ou mais códigos de perfil (ex.: ['A1','A2']).
     */
    public function scopeByPerfil($query, $codes)
    {
        $codes = is_array($codes) ? $codes : [$codes];
        return $query->whereIn('perfil_code', array_filter($codes));
    }
}
