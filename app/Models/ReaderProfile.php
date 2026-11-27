<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReaderProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tested_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }
}
