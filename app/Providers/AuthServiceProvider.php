<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    public function boot(): void
    {
        /**
         * Passe-livre:
         * - A1 (GESTOR_SISTEMA) SEMPRE tem acesso a tudo
         * - Em ambiente local/dev (ou APP_DEBUG=true), e-mails listados em DEKER_DEV_MENU_EMAILS também têm passe-livre
         */
        Gate::before(function ($user, $ability) {
            // Admin pleno
            $pName = mb_strtoupper((string)($user->perfil ?? ''), 'UTF-8');
            $pCode = mb_strtoupper((string)($user->perfil_code ?? ''), 'UTF-8');
            if ($pCode === 'A1' || $pName === 'GESTOR_SISTEMA') {
                return true;
            }

            // Bypass de desenvolvimento
            if (config('app.debug') || app()->environment(['local','development'])) {
                $csv     = (string) env('DEKER_DEV_MENU_EMAILS', 'gestor@deker.local,admin@deker.local');
                $allowed = array_map(fn($s) => strtolower(trim($s)), array_filter(explode(',', $csv)));
                if (in_array(strtolower($user->email ?? ''), $allowed, true)) {
                    return true;
                }
            }

            return null; // segue para as regras abaixo
        });

        // Regras de menu por perfil (nome OU código)
        $map = [
            'menu.sistema'      => ['GESTOR_SISTEMA','ADMIN_SISTEMA','A1','A2'],
            'menu.individuos'   => ['GESTOR_SISTEMA','ADMIN_SISTEMA','COORDENADOR','COORDENADOR_SETORIAL','COORDENADOR_NIS','GERENTE_SETORIAL','G3','G4','G5','G6','O1','O2','O3','O4'],
            'menu.relatorios'   => ['GESTOR_SISTEMA','ADMIN_SISTEMA','AUDITOR','GERENTE_GERAL','GERENTE_UNIDADE','GERENTE_SETORIAL','A4','G1','G2','G3'],
            'menu.atendimentos' => ['GESTOR_SISTEMA','ADMIN_SISTEMA','ATENDENTE','ASSISTENTE_SOCIAL','COORDENADOR','A1','A2','G6','O2'],
            'menu.ciclo'        => ['GESTOR_SISTEMA','ADMIN_SISTEMA','G2','G3','G4','G5','G6'],
            'menu.sobre'        => ['*'], // liberado a todos autenticados
            'menu.inicio'       => ['*'], // liberado a todos autenticados
        ];

        foreach ($map as $ability => $perfis) {
            Gate::define($ability, function ($user) use ($perfis) {
                if (in_array('*', $perfis, true)) {
                    return true;
                }
                $pName = mb_strtoupper((string)($user->perfil ?? ''), 'UTF-8');
                $pCode = mb_strtoupper((string)($user->perfil_code ?? ''), 'UTF-8');
                return in_array($pName, $perfis, true) || in_array($pCode, $perfis, true);
            });
        }

        // Compat legado (se for usado em algum lugar)
        Gate::define('sistema.manage', fn($u) =>
            in_array(mb_strtoupper((string)($u->perfil ?? ''), 'UTF-8'), ['GESTOR_SISTEMA','ADMIN_SISTEMA'], true)
            || in_array(mb_strtoupper((string)($u->perfil_code ?? ''), 'UTF-8'), ['A1','A2'], true)
        );
    }
}
