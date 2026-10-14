<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($qq) => $qq->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($qq) => $qq->where('name', $request->string('role'))))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => \App\Support\Roles::LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(array_keys(\App\Support\Roles::LABELS))],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        $password = $data['password'] ?? Str::password(10);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $user->assignRole($data['role']);

        $this->audit->log('user.created', $user, [], ['name' => $user->name, 'role' => $data['role']]);

        return back()
            ->with('success', "Compte créé pour {$user->name}.")
            ->with('generated_password', $data['password'] ? null : $password);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(array_keys(\App\Support\Roles::LABELS))],
        ]);

        $old = $user->only(['name', 'email', 'phone', 'job_title']);
        $user->update($data);

        $user->syncRoles([$data['role']]);

        $this->audit->log('user.updated', $user, $old, $data);

        return back()->with('success', 'Compte mis à jour.');
    }

    /** Réinitialise le mot de passe (la direction communique le nouveau). */
    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        $password = $data['password'] ?? Str::password(10);

        $user->update(['password' => Hash::make($password)]);

        $this->audit->log('user.password_reset', $user, [], ['by' => $request->user()->id]);

        return back()
            ->with('success', "Mot de passe réinitialisé pour {$user->name}.")
            ->with('generated_password', $password);
    }

    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->log('user.toggled_active', $user, [], ['is_active' => $user->is_active]);

        return back()->with('success', $user->is_active ? 'Compte réactivé.' : 'Compte désactivé.');
    }
}
