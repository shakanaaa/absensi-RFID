<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function view(User $user, AcademicYear $academicYear): bool
    {
        return $academicYear->school_id === $user->school_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $this->create($user) && $academicYear->school_id === $user->school_id;
    }

    public function delete(User $user, AcademicYear $academicYear): bool
    {
        return $this->update($user, $academicYear);
    }

    public function restore(User $user, AcademicYear $academicYear): bool
    {
        return $this->update($user, $academicYear);
    }

    public function forceDelete(User $user, AcademicYear $academicYear): bool
    {
        return false;
    }
}
