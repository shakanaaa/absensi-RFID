<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function view(User $user, Grade $grade): bool
    {
        return $grade->school_id === $user->school_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, Grade $grade): bool
    {
        return $this->create($user) && $grade->school_id === $user->school_id;
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $this->update($user, $grade);
    }

    public function restore(User $user, Grade $grade): bool
    {
        return $this->update($user, $grade);
    }

    public function forceDelete(User $user, Grade $grade): bool
    {
        return false;
    }
}
