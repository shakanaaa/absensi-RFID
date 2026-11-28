<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private User $admin;
    private User $piket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SMA Uji', 'code' => 'SMA-UJI', 'timezone' => 'Asia/Jakarta']);
        $this->admin = $this->user('admin@uji.test', 'admin');
        $this->piket = $this->user('piket@uji.test', 'petugas_piket');
    }

    private function user(string $email, string $role): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'username' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
            'school_id' => $this->school->id,
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_admin_can_create_academic_year(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.academic-years.store'), [
                'name' => '2027/2028',
                'start_date' => '2027-07-01',
                'end_date' => '2028-06-01',
                'status' => 'draft',
            ])
            ->assertRedirect(route('master.academic-years.index'));

        $this->assertDatabaseHas('academic_years', ['name' => '2027/2028', 'school_id' => $this->school->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academic_year.created']);
    }

    public function test_piket_can_read_but_not_write_master_data(): void
    {
        $this->actingAs($this->piket)->get(route('master.academic-years.index'))->assertOk();
        $this->actingAs($this->piket)->get(route('master.students.index'))->assertOk();
        $this->actingAs($this->piket)->get(route('master.classes.index'))->assertOk();

        $this->actingAs($this->piket)
            ->post(route('master.academic-years.store'), [
                'name' => '2028/2029', 'start_date' => '2028-07-01',
                'end_date' => '2029-06-01', 'status' => 'draft',
            ])
            ->assertForbidden();

        $this->actingAs($this->piket)->get(route('master.academic-years.create'))->assertForbidden();
        $this->actingAs($this->piket)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->piket)->get(route('promotion.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('master.students.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_only_two_roles_exist(): void
    {
        $this->assertEquals(['admin', 'petugas_piket'], array_column(\App\Enums\UserRole::cases(), 'value'));
    }
}
