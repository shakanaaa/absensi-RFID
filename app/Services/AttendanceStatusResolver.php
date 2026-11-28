<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\School;
use Illuminate\Support\Carbon;

/**
 * Menentukan status efektif untuk siswa yang belum check-in (tidak ada
 * sesi atau status 'belum_masuk') pada tanggal yang sedang ditampilkan.
 *
 * Aturan (hari sekolah efektif):
 *  - tanggal di masa depan          → belum_masuk
 *  - hari ini sebelum batas terlambat → belum_masuk
 *  - hari ini setelah batas terlambat, sebelum jam pulang → terlambat
 *  - hari ini setelah jam pulang / tanggal lampau → tidak_hadir
 *
 * Siswa yang sudah check-in tidak pernah diubah di sini — status
 * hadir/terlambat mereka ditentukan saat tap.
 */
class AttendanceStatusResolver
{
    public function resolve(School $school, string $date, ?string $storedStatus): string
    {
        if ($storedStatus !== null && $storedStatus !== AttendanceSession::STATUS_BELUM_MASUK) {
            return $storedStatus;
        }

        $settings = $school->settings;
        $now = Carbon::now($school->timezone ?: config('app.timezone'));
        $today = $now->toDateString();

        if ($date > $today) {
            return AttendanceSession::STATUS_BELUM_MASUK;
        }

        if ($date < $today) {
            return AttendanceSession::STATUS_TIDAK_HADIR;
        }

        $nowTime = $now->format('H:i:s');

        $checkout = $settings?->check_out_time?->format('H:i:s');
        if ($checkout !== null && $nowTime > $checkout) {
            return AttendanceSession::STATUS_TIDAK_HADIR;
        }

        $deadline = $settings?->late_deadline_time?->format('H:i:s');
        if ($deadline !== null && $nowTime > $deadline) {
            return AttendanceSession::STATUS_TERLAMBAT;
        }

        return AttendanceSession::STATUS_BELUM_MASUK;
    }
}
