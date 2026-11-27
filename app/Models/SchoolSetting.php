<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'check_in_time' => 'datetime:H:i:s',
            'check_out_time' => 'datetime:H:i:s',
            'report_send_time' => 'datetime:H:i:s',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
