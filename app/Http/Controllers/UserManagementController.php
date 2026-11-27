<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::where('school_id', $request->user()->school_id)
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Master/Users/Index', [
            'users' => $users,
            'roles' => collect(UserRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
            'filters' => $request->only('role'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Master/Users/Form', [
            'account' => null,
            'roles' => collect(UserRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->safe()->except('password', 'password_confirmation');

        $user = User::create($data + [
            'school_id' => $request->user()->school_id,
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
        ]);

        AuditLogger::log('user.created', User::class, $user, after: $user->only(['name', 'email', 'role', 'status']));

        return redirect()->route('users.index')->with('success', 'Akun dibuat.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Master/Users/Form', [
            'account' => $user,
            'roles' => collect(UserRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        // Admin tidak boleh mengubah role/status akunnya sendiri (anti lockout).
        if ($user->id === $request->user()->id
            && ($request->input('role') !== $user->role || $request->input('status') !== $user->status)) {
            return back()->with('error', 'Role/status akun sendiri tidak bisa diubah di sini.');
        }

        $before = $user->only(['name', 'role', 'status', 'phone']);
        $user->update($request->validated());

        // Perubahan role wajib teraudit (PRD §5.2).
        if ($before['role'] !== $user->role) {
            AuditLogger::log('user.role_changed', User::class, $user, before: ['role' => $before['role']], after: ['role' => $user->role]);
        }

        AuditLogger::log('user.updated', User::class, $user, before: $before, after: $user->only(['name', 'role', 'status', 'phone']));

        return redirect()->route('users.index')->with('success', 'Akun diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->update(['status' => 'suspended']);

        AuditLogger::log('user.suspended', User::class, $user);

        return redirect()->route('users.index')->with('success', 'Akun ditangguhkan.');
    }
}
