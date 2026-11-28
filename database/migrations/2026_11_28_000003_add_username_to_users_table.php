<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
        });

        // Backfill: username lama = bagian depan email; email tetap boleh null.
        foreach (DB::table('users')->whereNull('username')->get() as $user) {
            $base = \Illuminate\Support\Str::before((string) $user->email, '@') ?: 'user'.$user->id;
            $username = $base;
            $i = 1;
            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $base.$i++;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
