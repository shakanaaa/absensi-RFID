<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InteractsWithUserCreation;
use Illuminate\Console\Command;

class PiketCreate extends Command
{
    use InteractsWithUserCreation;

    protected $signature = 'piket:create
        {username : Username login (unik)}
        {--name= : Nama lengkap (default: username)}
        {--password= : Password (kosongkan untuk prompt tersembunyi)}
        {--school= : ID atau kode sekolah}';

    protected $description = 'Buat akun petugas piket';

    public function handle(): int
    {
        return $this->createUserAccount('petugas_piket', $this->signature);
    }
}
