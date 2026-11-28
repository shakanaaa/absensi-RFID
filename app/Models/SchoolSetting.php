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
            'late_deadline_time' => 'datetime:H:i:s',
            'report_send_time' => 'datetime:H:i:s',
            'weekly_off_days' => 'array',
            'wa_report_options' => 'array',
        ];
    }

    /** Bagian laporan WA yang aktif; null → bawaan (counts + terlambat + tidak_hadir). */
    public function waReportSections(): array
    {
        $defaults = [
            'counts' => true,
            'hadir' => false,
            'terlambat' => true,
            'tidak_hadir' => true,
            'belum_masuk' => false,
        ];

        $saved = $this->wa_report_options;

        return is_array($saved) ? array_merge($defaults, array_intersect_key($saved, $defaults)) : $defaults;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
