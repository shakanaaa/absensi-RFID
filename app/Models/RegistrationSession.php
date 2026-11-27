<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function readerProfile(): BelongsTo
    {
        return $this->belongsTo(ReaderProfile::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(RegistrationScanAttempt::class);
    }
}
