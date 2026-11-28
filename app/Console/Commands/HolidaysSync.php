<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\School;
use App\Services\HolidayService;
use Illuminate\Console\Command;

/**
 * Sinkronkan libur nasional ke school_holidays untuk semua sekolah,
 * mencakup seluruh rentang tahun ajaran masing-masing. Idempotent —
 * aman dijalankan berulang (dipakai scheduler harian).
 */
class HolidaysSync extends Command
{
    protected $signature = 'holidays:sync {--school= : ID sekolah (default: semua)}';

    protected $description = 'Sinkronkan libur nasional Indonesia ke kalender sekolah';

    public function handle(HolidayService $holidays): int
    {
        $schools = School::query()
            ->when($this->option('school'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($schools->isEmpty()) {
            $this->warn('Tidak ada sekolah ditemukan.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($schools as $school) {
            $created = 0;

            $years = AcademicYear::where('school_id', $school->id)->get(['start_date', 'end_date']);

            foreach ($years as $year) {
                $created += $holidays->syncForSchoolRange(
                    $school->id,
                    $year->start_date->toDateString(),
                    $year->end_date->toDateString(),
                );
            }

            // Selalu cakup tahun berjalan & tahun depan meski belum ada tahun ajaran.
            $today = now();
            $created += $holidays->syncForSchool($school->id, (int) $today->format('Y'));
            $created += $holidays->syncForSchool($school->id, (int) $today->format('Y') + 1);

            $total += $created;
            $this->info("{$school->name}: {$created} libur nasional ditambahkan/diubah.");
        }

        $this->info("Selesai. Total {$total} tanggal libur diproses.");

        return self::SUCCESS;
    }
}
