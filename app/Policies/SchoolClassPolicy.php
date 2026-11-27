<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function view(User $user, SchoolClass $class): bool
    {
        return $class->school_id === $user->school_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $this->create($user) && $class->school_id === $user->school_id;
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $this->update($user, $class);
    }

    public function restore(User $user, SchoolClass $class): bool
    {
        return $this->update($user, $class);
    }

    public function forceDelete(User $user, SchoolClass $class): bool
    {
        return false;
    }
}
