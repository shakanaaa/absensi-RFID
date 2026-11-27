<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Concentration;
use App\Models\User;

class ConcentrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function view(User $user, Concentration $concentration): bool
    {
        return $concentration->school_id === $user->school_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, Concentration $concentration): bool
    {
        return $this->create($user) && $concentration->school_id === $user->school_id;
    }

    public function delete(User $user, Concentration $concentration): bool
    {
        return $this->update($user, $concentration);
    }

    public function restore(User $user, Concentration $concentration): bool
    {
        return $this->update($user, $concentration);
    }

    public function forceDelete(User $user, Concentration $concentration): bool
    {
        return false;
    }
}
