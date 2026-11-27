<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function readerProfile(): BelongsTo
    {
        return $this->belongsTo(ReaderProfile::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(TerminalToken::class);
    }

    public function rawEvents(): HasMany
    {
        return $this->hasMany(RawRfidEvent::class);
    }
}
