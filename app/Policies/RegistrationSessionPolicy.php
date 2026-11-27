<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RegistrationSession;
use App\Models\User;

class RegistrationSessionPolicy
{
    /** Petugas piket ikut mengoperasikan sesi registrasi di gerbang. */
    private function operators(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function viewAny(User $user): bool
    {
        return $this->operators($user);
    }

    public function view(User $user, RegistrationSession $session): bool
    {
        return $session->school_id === $user->school_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->operators($user);
    }

    public function update(User $user, RegistrationSession $session): bool
    {
        return $session->school_id === $user->school_id && $this->operators($user);
    }

    public function delete(User $user, RegistrationSession $session): bool
    {
        return false;
    }

    public function restore(User $user, RegistrationSession $session): bool
    {
        return false;
    }

    public function forceDelete(User $user, RegistrationSession $session): bool
    {
        return false;
    }
}
