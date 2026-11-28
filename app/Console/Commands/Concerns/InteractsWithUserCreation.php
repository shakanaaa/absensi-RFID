<?php

namespace App\Console\Commands\Concerns;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

trait InteractsWithUserCreation
{
    private function createUserAccount(string $role, string $signatureHelp): int
    {
        $username = Str::lower(trim($this->argument('username')));

        if (! preg_match('/^[a-z0-9_.-]{3,50}$/', $username)) {
            $this->error('Username 3-50 karakter: huruf kecil, angka, titik, strip, underscore.');

            return Command::FAILURE;
        }

        if (User::where('username', $username)->exists()) {
            $this->error("Username '{$username}' sudah dipakai.");

            return Command::FAILURE;
        }

        $schools = School::orderBy('name')->get(['id', 'name', 'code']);
        if ($schools->isEmpty()) {
            $this->error('Belum ada sekolah. Jalankan migrate:fresh --seed dulu.');

            return Command::FAILURE;
        }

        $name = $this->option('name') ?: \Laravel\Prompts\text('Nama lengkap', default: ucfirst($username), required: true);
        $plainPassword = $this->option('password') ?: \Laravel\Prompts\password('Password', required: true);

        $schoolOpt = $this->option('school');
        if ($schoolOpt) {
            $school = $schools->firstWhere('id', (int) $schoolOpt) ?? $schools->firstWhere('code', $schoolOpt);
        } elseif ($schools->count() === 1) {
            $school = $schools->first();
        } else {
            $school = $schools->firstWhere('id', \Laravel\Prompts\select('Sekolah', $schools->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->code})"])->all()));
        }

        if (! $school) {
            $this->error('Sekolah tidak ditemukan.');

            return Command::FAILURE;
        }

        User::create([
            'name' => $name,
            'username' => $username,
            'password' => Hash::make($plainPassword),
            'school_id' => $school->id,
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->info("Akun dibuat: {$username} ({$role}) @ {$school->name}");

        return Command::SUCCESS;
    }
}
