<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawRfidEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'received_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
