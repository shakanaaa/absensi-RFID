<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalToken extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }
}
