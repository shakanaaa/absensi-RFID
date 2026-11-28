<?php

namespace App\Services;

use App\Models\SchoolHoliday;
use Illuminate\Support\Carbon;

/**
 * Generator libur nasional Indonesia.
 *
 * Yang selalu bisa dihitung untuk tahun berapa pun:
 *  - tanggal tetap (1 Jan, 1 Mei, 1 Jun, 17 Agu, 25 Des)
 *  - libur Paskah (Wafat Isa Almasih = Paskah-2, Hari Paskah,
 *    Kenaikan Isa Almasih = Paskah+39)
 *
 * Libur keagamaan/hari besar (Imlek, Nyepi, Idul Fitri, Idul Adha,
 * 1 Muharam, Maulid, Waisak, Isra Mikraj, cuti bersama) mengikuti
 * tanggal SKB 3 Menteri untuk tahun yang tersedia di TAHUNAN.
 * Tahun di luar tabulasi hanya mendapat tanggal tetap + Paskah;
 * admin tetap bisa menambahkan manual lewat halaman Pengaturan.
 */
class HolidayService
{
    /**
     * Tanggal libur keagamaan per tahun (YYYY-MM-DD => nama).
     * Sumber: SKB 3 Menteri tentang Libur Nasional & Cuti Bersama.
     */
    private const TAHUNAN = [
        2025 => [
            '2025-01-27' => 'Isra Mikraj Nabi Muhammad SAW',
            '2025-01-29' => 'Tahun Baru Imlek 2576 Kongzili',
            '2025-03-28' => 'Idul Fitri 1446 H',
            '2025-03-29' => 'Idul Fitri 1446 H',
            '2025-03-31' => 'Hari Paskah',
            '2025-04-18' => 'Wafat Isa Almasih',
            '2025-04-20' => 'Kenaikan Isa Almasih',
            '2025-04-20' => 'Hari Paskah (Kenaikan)',
            '2025-05-12' => 'Hari Raya Waisak 2569 BE',
            '2025-05-29' => 'Cuti Bersama Idul Fitri 1446 H',
            '2025-06-01' => 'Hari Lahir Pancasila',
            '2025-06-06' => 'Idul Adha 1446 H',
            '2025-06-09' => 'Cuti Bersama Idul Adha 1446 H',
            '2025-06-27' => 'Tahun Baru Islam 1447 H',
            '2025-08-17' => 'Hari Kemerdekaan RI',
            '2025-09-05' => 'Maulid Nabi Muhammad SAW',
            '2025-12-25' => 'Hari Raya Natal',
            '2025-12-26' => 'Cuti Bersama Natal',
        ],
        2026 => [
            '2026-01-01' => 'Tahun Baru 2026 M',
            '2026-01-16' => 'Isra Mikraj Nabi Muhammad SAW',
            '2026-02-17' => 'Tahun Baru Imlek 2577 Kongzili',
            '2026-03-19' => 'Idul Fitri 1447 H',
            '2026-03-20' => 'Idul Fitri 1447 H',
            '2026-03-23' => 'Cuti Bersama Idul Fitri 1447 H',
            '2026-04-03' => 'Wafat Isa Almasih',
            '2026-04-05' => 'Kenaikan Isa Almasih',
            '2026-05-01' => 'Hari Buruh Internasional',
            '2026-05-14' => 'Kenaikan Isa Almasih (Cuti Bersama)',
            '2026-05-27' => 'Idul Adha 1447 H',
            '2026-05-28' => 'Cuti Bersama Idul Adha 1447 H',
            '2026-05-31' => 'Hari Raya Waisak 2570 BE',
            '2026-06-01' => 'Hari Lahir Pancasila',
            '2026-06-16' => 'Tahun Baru Islam 1448 H',
            '2026-08-17' => 'Hari Kemerdekaan RI',
            '2026-08-25' => 'Maulid Nabi Muhammad SAW',
            '2026-12-25' => 'Hari Raya Natal',
        ],
        2027 => [
            '2027-01-01' => 'Tahun Baru 2027 M',
            '2027-01-05' => 'Isra Mikraj Nabi Muhammad SAW 1448 H',
            '2027-02-05' => 'Cuti Bersama Tahun Baru Imlek 2578 Kongzili',
            '2027-02-06' => 'Tahun Baru Imlek 2578 Kongzili',
            '2027-03-08' => 'Hari Suci Nyepi (Tahun Baru Saka 1949)',
            '2027-03-09' => 'Cuti Bersama Idulfitri 1448 H',
            '2027-03-10' => 'Idulfitri 1448 H',
            '2027-03-11' => 'Idulfitri 1448 H',
            '2027-03-12' => 'Cuti Bersama Idulfitri 1448 H',
            '2027-03-15' => 'Cuti Bersama Idulfitri 1448 H',
            '2027-03-25' => 'Cuti Bersama Wafat Isa Almasih',
            '2027-03-26' => 'Wafat Isa Almasih',
            '2027-03-28' => 'Hari Paskah',
            '2027-05-06' => 'Kenaikan Isa Almasih',
            '2027-05-17' => 'Iduladha 1448 H',
            '2027-05-18' => 'Cuti Bersama Iduladha 1448 H',
            '2027-05-19' => 'Cuti Bersama Hari Raya Waisak',
            '2027-05-20' => 'Hari Raya Waisak 2571 BE',
            '2027-06-06' => 'Tahun Baru Islam 1449 H',
            '2027-08-15' => 'Maulid Nabi Muhammad SAW',
            '2027-08-17' => 'Hari Kemerdekaan RI',
            '2027-12-24' => 'Cuti Bersama Hari Raya Natal',
            '2027-12-25' => 'Hari Raya Natal',
            '2027-12-26' => 'Isra Mikraj Nabi Muhammad SAW 1449 H',
        ],
    ];

    /**
     * Daftar libur untuk satu tahun: [ 'Y-m-d' => 'nama', ... ].
     * Libur tetap & Paskah selalu dihitung; tanggal tabulasi menimpa
     * nama default untuk tanggal yang sama.
     */
    public function holidaysForYear(int $year): array
    {
        $holidays = [];

        // Tanggal tetap.
        foreach ([
            '01-01' => 'Tahun Baru',
            '05-01' => 'Hari Buruh Internasional',
            '06-01' => 'Hari Lahir Pancasila',
            '08-17' => 'Hari Kemerdekaan RI',
            '12-25' => 'Hari Raya Natal',
        ] as $md => $name) {
            $holidays[sprintf('%04d-%s', $year, $md)] = $name;
        }

        // Berbasis Paskah (algoritma Meeus/Jones/Butcher).
        $easter = $this->easter($year);
        $holidays[$easter->copy()->subDays(2)->toDateString()] = 'Wafat Isa Almasih';
        $holidays[$easter->copy()->toDateString()] = 'Hari Paskah';
        $holidays[$easter->copy()->addDays(39)->toDateString()] = 'Kenaikan Isa Almasih';

        // Tabulasi SKB untuk tahun yang tersedia.
        foreach (self::TAHUNAN[$year] ?? [] as $date => $name) {
            $holidays[$date] = $name;
        }

        ksort($holidays);

        return $holidays;
    }

    /**
     * Sinkronkan libur nasional satu tahun ke school_holidays satu sekolah.
     * Idempotent — updateOrCreate per tanggal, tidak menghapus libur manual.
     * Mengembalikan jumlah tanggal baru yang dibuat.
     */
    public function syncForSchool(int $schoolId, int $year): int
    {
        $created = 0;

        foreach ($this->holidaysForYear($year) as $date => $name) {
            $created += SchoolHoliday::updateOrCreate(
                ['school_id' => $schoolId, 'holiday_date' => $date],
                ['name' => $name]
            )->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * Sinkronkan libur nasional untuk semua tahun Masehi yang dicakup
     * sebuah rentang tanggal (mis. start_date s.d. end_date tahun ajaran).
     * Dipakai otomatis saat tahun ajaran baru dibuat dan oleh command harian.
     */
    public function syncForSchoolRange(int $schoolId, string $startDate, string $endDate): int
    {
        $total = 0;
        for ($y = (int) substr($startDate, 0, 4); $y <= (int) substr($endDate, 0, 4); $y++) {
            $total += $this->syncForSchool($schoolId, $y);
        }

        return $total;
    }

    /** Tanggal Paskah (Minggu) untuk tahun Masehi. */
    private function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }
}
