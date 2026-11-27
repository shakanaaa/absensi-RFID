<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case PetugasPiket = 'petugas_piket';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::PetugasPiket => 'Petugas Piket',
        };
    }
}
