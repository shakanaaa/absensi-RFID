<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceSession extends Model
{
    public const STATUS_BELUM_MASUK = 'belum_masuk';
    public const STATUS_HADIR = 'hadir';
    public const STATUS_TERLAMBAT = 'terlambat';
    public const STATUS_TIDAK_HADIR = 'tidak_hadir';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'school_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Label tampilan untuk status absensi. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_HADIR => 'Hadir',
            self::STATUS_TERLAMBAT => 'Terlambat',
            self::STATUS_TIDAK_HADIR => 'Tidak Hadir',
            default => 'Belum Masuk',
        };
    }
}
