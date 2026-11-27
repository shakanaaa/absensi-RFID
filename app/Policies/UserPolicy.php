<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, User $model): bool
    {
        // Akun self-registered (school_id null) boleh dikelola admin sekolah manapun.
        return $user->hasRole(UserRole::Admin)
            && ($model->school_id === null || $model->school_id === $user->school_id);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->update($user, $model) && $user->id !== $model->id;
    }

    public function restore(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
