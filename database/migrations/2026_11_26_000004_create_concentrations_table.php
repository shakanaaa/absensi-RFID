<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concentrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('concentration_id')
                ->after('grade_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('concentration_id');
        });

        Schema::dropIfExists('concentrations');
    }
};
