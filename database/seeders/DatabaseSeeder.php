<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\ReaderProfile;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentClassMembership;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $school = School::create([
            'name' => 'SMA Demo Absensi',
            'code' => 'SMA-DEMO',
            'timezone' => 'Asia/Jakarta',
        ]);

        SchoolSetting::create([
            'school_id' => $school->id,
            'check_in_time' => '07:00:00',
            'check_out_time' => '15:00:00',
            'whatsapp_number' => null,
            'weekly_off_days' => [6, 7],
        ]);

        User::create([
            'name' => 'Admin Sekolah',
            'email' => 'admin@absensi.test',
            'password' => Hash::make('password'),
            'school_id' => $school->id,
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Guru Piket',
            'email' => 'guru.piket@absensi.test',
            'password' => Hash::make('password'),
            'school_id' => $school->id,
            'role' => 'petugas_piket',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $year = AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2026/2027',
            'start_date' => '2026-07-10',
            'end_date' => '2027-06-09',
            'status' => 'active',
        ]);

        $grades = [];
        foreach ([10 => 'X', 11 => 'XI', 12 => 'XII'] as $level => $name) {
            $grades[$level] = Grade::create([
                'school_id' => $school->id,
                'name' => $name,
                'level' => $level,
            ]);
        }

        $concentrations = [];
        foreach (['IPA', 'IPS', 'Bahasa'] as $name) {
            $concentrations[$name] = \App\Models\Concentration::create([
                'school_id' => $school->id,
                'name' => $name,
            ]);
        }

        // Kelas per tingkat × konsentrasi: X IPA 1, X IPS 1, dst.
        $classes = [];
        foreach ([10, 11, 12] as $level) {
            foreach ($concentrations as $concName => $conc) {
                $classes[] = SchoolClass::create([
                    'school_id' => $school->id,
                    'grade_id' => $grades[$level]->id,
                    'concentration_id' => $conc->id,
                    'code' => $grades[$level]->name.'-'.$concName.'-1',
                    'name' => $grades[$level]->name.' '.$concName.' 1',
                    'status' => 'active',
                ]);
            }
        }

        ReaderProfile::create([
            'name' => 'Reader USB Keyboard Emulation',
            'device_model' => null,
            'pattern' => '^[0-9A-Fa-f]{8,20}$',
            'uid_length' => null,
            'suffix' => "\r",
            'version' => '1.0',
            'tested_at' => null,
            'is_active' => true,
        ]);

        // 5 siswa demo di kelas X-1.
        $class = $classes[0];
        foreach (range(1, 5) as $i) {
            $student = Student::create([
                'school_id' => $school->id,
                'name' => "Siswa Demo {$i}",
                'status' => 'active',
            ]);

            StudentClassMembership::create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'academic_year_id' => $year->id,
                'from_date' => '2026-07-13',
                'attendance_number' => $i,
            ]);
        }
    }
}
