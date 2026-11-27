<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePairingCode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
