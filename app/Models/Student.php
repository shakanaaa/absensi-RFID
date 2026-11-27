<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $guarded = [];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(StudentClassMembership::class);
    }

    /** Membership aktif: to_date null pada tahun ajaran aktif. */
    public function activeMembership()
    {
        return $this->hasOne(StudentClassMembership::class)->whereNull('to_date')->latestOfMany();
    }

    public function cardAssignments(): HasMany
    {
        return $this->hasMany(StudentCardAssignment::class);
    }

    public function activeCardAssignment()
    {
        return $this->hasOne(StudentCardAssignment::class)->where('status', 'active');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
