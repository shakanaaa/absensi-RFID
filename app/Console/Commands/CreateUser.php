<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Prompts\password;
use Laravel\Prompts\select;
use Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'user:create
        {--name= : Nama lengkap}
        {--username= : Username login (unik)}
        {--password= : Password (kosongkan untuk prompt tersembunyi)}
        {--role= : Role: admin atau petugas_piket}
        {--school= : ID atau kode sekolah}';

    protected $description = 'Buat akun pengguna (admin / petugas piket)';

    public function handle(): int
    {
        $schools = School::orderBy('name')->get(['id', 'name', 'code']);
        if ($schools->isEmpty()) {
            $this->error('Belum ada sekolah. Jalankan migrate:fresh --seed dulu.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: text('Nama lengkap', required: true);
        $username = $this->option('username') ?: text('Username', required: true, validate: fn ($v) => $this->validateUsername($v));
        $plainPassword = $this->option('password') ?: password('Password', required: true);

        $role = $this->option('role') ?: select('Role', [
            'admin' => 'Admin',
            'petugas_piket' => 'Petugas Piket',
        ]);
        if (! in_array($role, ['admin', 'petugas_piket'], true)) {
            $this->error('Role harus admin atau petugas_piket.');

            return self::FAILURE;
        }

        $schoolOpt = $this->option('school');
        if ($schoolOpt) {
            $school = $schools->firstWhere('id', (int) $schoolOpt) ?? $schools->firstWhere('code', $schoolOpt);
        } elseif ($schools->count() === 1) {
            $school = $schools->first();
        } else {
            $school = $schools->firstWhere('id', select('Sekolah', $schools->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->code})"])->all()));
        }

        if (! $school) {
            $this->error('Sekolah tidak ditemukan.');

            return self::FAILURE;
        }

        if (User::where('username', $username)->exists()) {
            $this->error("Username '{$username}' sudah dipakai.");

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'username' => Str::lower($username),
            'password' => Hash::make($plainPassword),
            'school_id' => $school->id,
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->info("Akun dibuat: {$username} ({$role}) @ {$school->name}");

        return self::SUCCESS;
    }

    private function validateUsername(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        if ($value === '' || ! preg_match('/^[a-z0-9_.-]{3,50}$/', $value)) {
            return 'Username 3-50 karakter: huruf kecil, angka, titik, strip, underscore.';
        }

        return null;
    }
}
