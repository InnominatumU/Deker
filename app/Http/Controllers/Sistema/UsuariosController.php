<?php

namespace App\Http\Controllers\Sistema;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UsuariosController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','can:menu.sistema']);
    }

    /* ========= BUSCA + EDIT (sem {id}) =========
     * Abre a tela de EDIT com formulário de BUSCA embutido (modo "sem user")
     */
    public function search(Request $request)
    {
        $q_name     = trim((string)$request->get('name',''));
        $q_email    = trim(mb_strtolower((string)$request->get('email',''), 'UTF-8'));
        $q_user     = trim(mb_strtolower((string)$request->get('username',''), 'UTF-8'));
        $q_matr     = trim((string)$request->get('matricula',''));
        $q_cpf      = preg_replace('/\D+/', '', (string)$request->get('cpf',''));
        $q_pcode    = trim((string)$request->get('perfil_code',''));
        $q_perpage  = max(10, min(50, (int)$request->get('pp', 15)));

        $didSearch = ($q_name!=='' || $q_email!=='' || $q_user!=='' || $q_matr!=='' || $q_cpf!=='' || $q_pcode!=='');

        $resultados = null;
        if ($didSearch) {
            $resultados = User::query()
                ->when($q_name !== '',      fn($w) => $w->where('name','like','%'.$q_name.'%'))
                ->when($q_email !== '',     fn($w) => $w->whereRaw('LOWER(email) like ?', ['%'.$q_email.'%']))
                ->when($q_user !== '',      fn($w) => $w->whereRaw('LOWER(username) like ?', ['%'.$q_user.'%']))
                ->when($q_matr !== '',      fn($w) => $w->where('matricula','like','%'.$q_matr.'%'))
                ->when($q_cpf !== '',       fn($w) => $w->where('cpf','like','%'.$q_cpf.'%'))
                ->when($q_pcode !== '',     fn($w) => $w->where('perfil_code',$q_pcode))
                ->orderBy('name')
                ->paginate($q_perpage)
                ->appends($request->query());
        }

        $perfis = $this->perfilOptions();

        // Reaproveita a mesma view do EDIT (sem $user = modo BUSCA)
        return view('sistema.usuariosedit', [
            'user'       => null,
            'perfis'     => $perfis,
            'resultados' => $resultados,
            'didSearch'  => $didSearch,
            'q'          => compact('q_name','q_email','q_user','q_matr','q_cpf','q_pcode','q_perpage'),
        ]);
    }

    /* ========= CREATE ========= */
    public function create()
    {
        $perfis = $this->perfilOptions();
        return view('sistema.usuarioscreate', compact('perfis'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // Normalizações coerentes com a autenticação
        $data = $this->normalizeForPersist($data);

        // is_active default true na criação (se não vier do form)
        if (!array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        // Garante que perfil bate com o código (evita divergência)
        $perfis = $this->perfilOptions();
        if (!empty($data['perfil_code']) && isset($perfis[$data['perfil_code']])) {
            $data['perfil'] = $perfis[$data['perfil_code']];
        }

        $u = new User($data); // 'password' => 'hashed' no model fará o hash automático
        $u->save();

        return redirect()->route('sistema.usuarios.search')->with('success','Usuário criado com sucesso.');
    }

    /* ========= EDIT/UPDATE ========= */
    public function edit(User $usuario)
    {
        $perfis = $this->perfilOptions();
        return view('sistema.usuariosedit', ['user' => $usuario, 'perfis' => $perfis]);
    }

    public function update(Request $request, User $usuario)
    {
        $data = $this->validateData($request, $usuario->id);

        // Normalizações coerentes com a autenticação
        $data = $this->normalizeForPersist($data);

        // Senha opcional no update
        if (empty($data['password'])) {
            unset($data['password']); // mantém a senha atual
        }

        // is_active: se o campo não vier no request (checkbox desmarcado sem hidden),
        // preserva o valor atual do usuário:
        $data['is_active'] = $request->has('is_active')
            ? (bool)$request->boolean('is_active')
            : (bool)$usuario->is_active;

        // Garante que perfil bate com o código
        $perfis = $this->perfilOptions();
        if (!empty($data['perfil_code']) && isset($perfis[$data['perfil_code']])) {
            $data['perfil'] = $perfis[$data['perfil_code']];
        }

        $usuario->fill($data)->save();

        return redirect()->route('sistema.usuarios.edit', $usuario)->with('success','Usuário atualizado.');
    }

    /* ========= DESTROY ========= */
       private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $cpfDigits = preg_replace('/\D+/', '', (string) $request->input('cpf'));
        $unique = fn($col) => Rule::unique('users', $col)->ignore($ignoreId);

        $perfilCodes = array_keys($this->perfilOptions());

        $rules = [
            'name'        => ['required','string','max:200'],
            'email'       => ['required','string','email','max:190',$unique('email')],
            'matricula'   => ['nullable','string','max:40',$unique('matricula')],
            'cpf'         => ['nullable','string','size:11',$unique('cpf')], // apenas dígitos (11)
            'username'    => ['required','string','max:50',$unique('username')],
            'perfil_code' => ['required','string','max:3', Rule::in($perfilCodes)],
            'perfil'      => ['required','string','max:60'],
            'is_active'   => ['sometimes','boolean'],
            'password'    => [$ignoreId ? 'nullable' : 'required',
                PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
        ];

        $data = $request->validate($rules);

        // Ajusta CPF para só dígitos (ou null)
        $data['cpf'] = $cpfDigits ?: null;

        return $data;
    }

    /**
     * Normalizações coerentes com a estratégia de login:
     * - email/username: lowercase + trim
     * - cpf: só dígitos (já garantido em validateData)
     * - matricula: trim (mantém zeros)
     */
    private function normalizeForPersist(array $data): array
    {
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']), 'UTF-8');
        }
        if (isset($data['username']) && is_string($data['username'])) {
            $data['username'] = mb_strtolower(trim($data['username']), 'UTF-8');
        }
        if (isset($data['matricula']) && is_string($data['matricula'])) {
            $data['matricula'] = trim($data['matricula']);
            if ($data['matricula'] === '') $data['matricula'] = null;
        }
        if (isset($data['cpf']) && is_string($data['cpf'])) {
            $data['cpf'] = preg_replace('/\D+/', '', $data['cpf']) ?: null;
        }

        return $data;
    }

    private function perfilOptions(): array
    {
        return [
            // Administrativos
            'A1' => 'GESTOR_SISTEMA',
            'A2' => 'ADMIN_SISTEMA',      // Admin de Usuários/Unidades
            'A3' => 'ADMIN_TECNICO',
            'A4' => 'AUDITOR',
            // Gerenciais
            'G1' => 'GERENTE_GERAL',
            'G2' => 'GERENTE_UNIDADE',
            'G3' => 'GERENTE_SETORIAL',
            'G4' => 'COORDENADOR_SETORIAL',
            'G5' => 'COORDENADOR_NIS',
            'G6' => 'COORDENADOR',
            // Operacionais
            'O1' => 'OPERADOR_CADASTRO',
            'O2' => 'OPERADOR_SAUDE',
            'O3' => 'OPERADOR_TRABALHO',
            'O4' => 'OPERADOR_JURIDICO',
            // Suplementares
            'R1' => 'LEITURA_GERAL',
            'S1' => 'SUPORTE_LOCAL',
        ];
    }
}
