<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolHoliday extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
