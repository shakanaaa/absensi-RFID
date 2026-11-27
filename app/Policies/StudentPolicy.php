<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function view(User $user, Student $student): bool
    {
        return $student->school_id === $user->school_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, Student $student): bool
    {
        return $this->create($user) && $student->school_id === $user->school_id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->update($user, $student);
    }

    public function restore(User $user, Student $student): bool
    {
        return $this->update($user, $student);
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return false;
    }
}
