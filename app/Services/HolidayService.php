<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Generator libur nasional Indonesia.
 *
 * Yang selalu bisa dihitung untuk tahun berapa pun:
 *  - tanggal tetap (1 Jan, 1 Mei, 1 Jun, 17 Agu, 25 Des)
 *  - libur berbasis Paskah (Wafat Isa Almasih, Kenaikan Isa Almasih)
 *
 * Libur keagamaan/hari besar (Imlek, Nyepi, Idul Fitri, Idul Adha,
 * 1 Muharam, Maulid, Waisak, Isra Mikraj) mengikuti tanggal SKB 3 Menteri
 * untuk tahun yang tersedia di TAHUNAN di bawah. Tahun lain → kosong,
 * admin menambahkan manual lewat halaman Pengaturan.
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
        $holidays[$easter->copy()->addDay()->toDateString()] = 'Kenaikan Isa Almasih';

        // Tabulasi SKB untuk tahun yang tersedia.
        foreach (self::TAHUNAN[$year] ?? [] as $date => $name) {
            $holidays[$date] = $name;
        }

        ksort($holidays);

        return $holidays;
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
