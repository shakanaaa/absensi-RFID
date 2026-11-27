<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    protected $guarded = [];

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function activeAcademicYear(): HasOne
    {
        return $this->hasOne(AcademicYear::class)->where('status', 'active')->latestOfMany();
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(SchoolSetting::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(SchoolHoliday::class);
    }
}
