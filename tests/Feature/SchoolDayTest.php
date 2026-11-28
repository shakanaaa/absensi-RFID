<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolHoliday;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\SchoolDayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolDayTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolDayService $days;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SMK Uji', 'code' => 'SMK-UJI', 'timezone' => 'Asia/Jakarta']);
        AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026/2027',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active',
        ]);
        $this->days = new SchoolDayService;
    }

    private function setOffDays(?array $days): void
    {
        SchoolSetting::updateOrCreate(
            ['school_id' => $this->school->id],
            ['check_in_time' => '07:00:00', 'check_out_time' => '15:00:00', 'weekly_off_days' => $days]
        );
    }

    private function dates(): array
    {
        $sat = Carbon::parse('2026-07-01')->next(Carbon::SATURDAY);

        return [$sat->toDateString(), $sat->copy()->addDay()->toDateString(), $sat->copy()->addDays(2)->toDateString()];
    }

    public function test_default_weekend_is_not_school_day(): void
    {
        [$sat, $sun, $mon] = $this->dates();

        $this->assertFalse($this->days->isEffectiveSchoolDay($this->school->id, $sat));
        $this->assertFalse($this->days->isEffectiveSchoolDay($this->school->id, $sun));
        $this->assertTrue($this->days->isEffectiveSchoolDay($this->school->id, $mon));
    }

    public function test_custom_weekly_off_days(): void
    {
        // Hanya Minggu libur → Sabtu jadi hari sekolah.
        $this->setOffDays([7]);
        [$sat, $sun, $mon] = $this->dates();

        $this->assertTrue($this->days->isEffectiveSchoolDay($this->school->id, $sat));
        $this->assertFalse($this->days->isEffectiveSchoolDay($this->school->id, $sun));
        $this->assertTrue($this->days->isEffectiveSchoolDay($this->school->id, $mon));
    }

    public function test_empty_off_days_makes_every_weekday_school_day(): void
    {
        $this->setOffDays([]);
        [$sat, $sun] = $this->dates();

        $this->assertTrue($this->days->isEffectiveSchoolDay($this->school->id, $sat));
        $this->assertTrue($this->days->isEffectiveSchoolDay($this->school->id, $sun));
    }

    public function test_holiday_still_overrides_school_day(): void
    {
        $this->setOffDays([7]);
        [$sat] = $this->dates();
        SchoolHoliday::create(['school_id' => $this->school->id, 'holiday_date' => $sat, 'name' => 'Libur Khusus']);

        $this->assertFalse($this->days->isEffectiveSchoolDay($this->school->id, $sat));
    }

    public function test_admin_can_update_weekly_off_days(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'username' => 'admin-smk', 'email' => 'admin@smk.test',
            'password' => Hash::make('password'),
            'school_id' => $this->school->id, 'role' => 'admin', 'status' => 'active',
        ]);

        $this->actingAs($admin)->put(route('settings.update'), [
            'check_in_time' => '07:00',
            'check_out_time' => '15:00',
            'weekly_off_days' => [6, 7],
        ])->assertRedirect();

        $this->assertSame([6, 7], SchoolSetting::where('school_id', $this->school->id)->first()->weekly_off_days);
    }

    public function test_petugas_piket_cannot_update_settings(): void
    {
        $piket = User::create([
            'name' => 'Piket', 'username' => 'piket-smk', 'email' => 'piket@smk.test',
            'password' => Hash::make('password'),
            'school_id' => $this->school->id, 'role' => 'petugas_piket', 'status' => 'active',
        ]);

        $this->actingAs($piket)->put(route('settings.update'), [
            'check_in_time' => '07:00',
            'check_out_time' => '15:00',
            'weekly_off_days' => [6],
        ])->assertForbidden();
    }
}
