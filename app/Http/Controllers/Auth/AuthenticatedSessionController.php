<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Exibe a view de login.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Processa a autenticação com múltiplos identificadores:
     * e-mail OU matrícula OU CPF (só dígitos) OU username.
     */
    public function store(Request $request): RedirectResponse
    {
        // Aceita "login" (preferido) ou "email" (compat com Breeze/Fortify)
        $rawLogin = (string) ($request->input('login', $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $remember = (bool) $request->boolean('remember');

        // Validação básica (não aplicamos regra de senha forte aqui)
        $rules = [
            'password' => ['required', 'string'],
        ];
        if ($request->has('login')) {
            $rules['login'] = ['required', 'string'];
        } else {
            $rules['email'] = ['required', 'string'];
        }
        $request->validate($rules);

        // Detecta coluna e normaliza valor
        $column = $this->detectLoginColumn($rawLogin);
        $value  = $this->normalizeLoginValue($column, $rawLogin);

        // Busca usuário pelo identificador informado
        /** @var \App\Models\User|null $user */
        $user = User::query()->where($column, $value)->first();

        // Mensagem única para evitar "vazar" qual campo errou
        $invalidMsg = __('As credenciais informadas são inválidas.');

        if (!$user) {
            throw ValidationException::withMessages(['login' => $invalidMsg]);
        }

        // Bloqueia usuário inativo
        if (empty($user->is_active)) {
            throw ValidationException::withMessages([
                'login' => __('As credenciais informadas são inválidas ou o usuário está inativo.'),
            ]);
        }

        // Tenta autenticar exigindo is_active=1
        if (! Auth::attempt([$column => $value, 'password' => $password, 'is_active' => 1], $remember)) {
            throw ValidationException::withMessages(['login' => $invalidMsg]);
        }

        // Proteção contra fixation + trilha de último login
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        // Redireciona para a página inicial
        return redirect()->intended(route('inicio'));
    }

    /**
     * Finaliza a sessão autenticada.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Decide qual coluna usar para autenticar a partir do valor digitado.
     * - Contém "@": email
     * - 11 dígitos após limpar: cpf
     * - Somente dígitos (mas !=11): matrícula
     * - Caso contrário: username
     */
    private function detectLoginColumn(string $login): string
    {
        $login = trim($login);
        if ($login === '') return 'email';

        if (str_contains($login, '@')) {
            return 'email';
        }

        $digits = preg_replace('/\D+/', '', $login);
        if (strlen($digits) === 11) {
            return 'cpf';
        }

        if (preg_match('/^\d+$/', $login)) {
            return 'matricula';
        }

        return 'username';
    }

    /**
     * Normaliza o valor de login conforme a coluna usada.
     * - email / username: lowercase + trim
     * - cpf: apenas dígitos (11)
     * - matricula: trim (mantém zeros à esquerda)
     */
    private function normalizeLoginValue(string $column, string $raw): string
    {
        $raw = trim($raw);

        return match ($column) {
            'email', 'username' => mb_strtolower($raw, 'UTF-8'),
            'cpf'               => preg_replace('/\D+/', '', $raw) ?? '',
            'matricula'         => $raw,
            default             => $raw,
        };
    }
}
