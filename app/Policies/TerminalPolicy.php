<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Terminal;
use App\Models\User;

class TerminalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function view(User $user, Terminal $terminal): bool
    {
        return $terminal->school_id === $user->school_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function update(User $user, Terminal $terminal): bool
    {
        return $terminal->school_id === $user->school_id && $this->create($user);
    }

    public function delete(User $user, Terminal $terminal): bool
    {
        return false;
    }

    public function restore(User $user, Terminal $terminal): bool
    {
        return false;
    }

    public function forceDelete(User $user, Terminal $terminal): bool
    {
        return false;
    }
}
