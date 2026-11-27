<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationScanAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RegistrationSession::class, 'registration_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
