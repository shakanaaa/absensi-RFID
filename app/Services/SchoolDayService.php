<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolHoliday;
use App\Models\SchoolSetting;
use Illuminate\Support\Carbon;

/**
 * Hari sekolah efektif = hari bukan libur mingguan (school_settings.
 * weekly_off_days, default Sabtu–Minggu), dalam rentang tahun ajaran
 * aktif, dan bukan hari libur (school_holidays).
 */
class SchoolDayService
{
    public const DEFAULT_OFF_DAYS = [6, 7]; // Sabtu, Minggu (ISO-8601)

    public function isEffectiveSchoolDay(int $schoolId, string $date): bool
    {
        $day = Carbon::parse($date);

        $offDays = SchoolSetting::where('school_id', $schoolId)->first()?->weekly_off_days
            ?? self::DEFAULT_OFF_DAYS;

        if (in_array($day->dayOfWeekIso, $offDays, true)) {
            return false;
        }

        $inYear = AcademicYear::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if (! $inYear) {
            return false;
        }

        return ! SchoolHoliday::where('school_id', $schoolId)
            ->whereDate('holiday_date', $date)
            ->exists();
    }

    /** Jumlah hari efektif inklusif dalam rentang (batas 400 iterasi). */
    public function effectiveDaysInRange(int $schoolId, Carbon $start, Carbon $end): int
    {
        $count = 0;
        for ($d = $start->copy()->startOfDay(); $d->lte($end) && $count <= 400; $d->addDay()) {
            if ($this->isEffectiveSchoolDay($schoolId, $d->toDateString())) {
                $count++;
            }
        }

        return $count;
    }
}
