<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $guarded = [];

    protected $table = 'classes';

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function concentration(): BelongsTo
    {
        return $this->belongsTo(Concentration::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(StudentClassMembership::class, 'class_id');
    }
}
