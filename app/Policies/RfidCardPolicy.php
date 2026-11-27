<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RfidCard;
use App\Models\User;

class RfidCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function view(User $user, RfidCard $card): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::PetugasPiket);
    }

    public function update(User $user, RfidCard $card): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, RfidCard $card): bool
    {
        // Kartu dengan histori tidak dihapus — cukup nonaktifkan.
        return $this->create($user) && ! $card->assignments()->exists();
    }

    public function restore(User $user, RfidCard $card): bool
    {
        return $this->create($user);
    }

    public function forceDelete(User $user, RfidCard $card): bool
    {
        return false;
    }
}
