<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InteractsWithUserCreation;
use Illuminate\Console\Command;

class AdminCreate extends Command
{
    use InteractsWithUserCreation;

    protected $signature = 'admin:create
        {username : Username login (unik)}
        {--name= : Nama lengkap (default: username)}
        {--password= : Password (kosongkan untuk prompt tersembunyi)}
        {--school= : ID atau kode sekolah}';

    protected $description = 'Buat akun admin';

    public function handle(): int
    {
        return $this->createUserAccount('admin', $this->signature);
    }
}
