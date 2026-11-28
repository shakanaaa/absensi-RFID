<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ConcentrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\RegistrationSessionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RfidCardController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

// Absensi harian & laporan — semua role staf.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
 | Master data — baca: admin & petugas piket. Tulis: admin saja
 | (kartu/registrasi: admin + petugas piket sebagai operator gerbang).
 */
Route::middleware(['auth', 'verified', 'role:admin,petugas_piket'])->prefix('master')->name('master.')->group(function () {
    Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
    Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
    Route::get('/concentrations', [ConcentrationController::class, 'index'])->name('concentrations.index');
    Route::get('/classes', [SchoolClassController::class, 'index'])->name('classes.index');
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');
    Route::get('/cards', [RfidCardController::class, 'index'])->name('cards.index');
    Route::get('/registrations', [RegistrationSessionController::class, 'index'])->name('registrations.index');
    Route::get('/registrations/create', [RegistrationSessionController::class, 'create'])->name('registrations.create');
    Route::get('/registrations/{session}', [RegistrationSessionController::class, 'show'])->whereNumber('session')->name('registrations.show');
});

// Tulis master data — admin saja.
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('master')->name('master.')->group(function () {
    Route::resource('academic-years', AcademicYearController::class)->except('index', 'show')->names('academic-years');
    Route::resource('grades', GradeController::class)->except('index', 'show')->names('grades');
    Route::resource('concentrations', ConcentrationController::class)->except('index', 'show')->names('concentrations');
    Route::resource('classes', SchoolClassController::class)->except('index', 'show')->parameters(['classes' => 'class'])->names('classes');
    Route::resource('students', StudentController::class)->except('index', 'show')->names('students');
});

// Kartu RFID & registrasi massal — admin + petugas piket (operator gerbang).
Route::middleware(['auth', 'verified', 'role:admin,petugas_piket'])->prefix('master')->name('master.')->group(function () {
    Route::post('/cards', [RfidCardController::class, 'store'])->name('cards.store');
    Route::patch('/cards/{card}', [RfidCardController::class, 'update'])->name('cards.update');
    Route::delete('/cards/{card}', [RfidCardController::class, 'destroy'])->name('cards.destroy');
    Route::post('/cards/{card}/assign', [RfidCardController::class, 'assign'])->name('cards.assign');
    Route::post('/assignments/{assignment}/unassign', [RfidCardController::class, 'unassign'])->name('assignments.unassign');

    Route::post('/registrations', [RegistrationSessionController::class, 'store'])->name('registrations.store');
    Route::post('/registrations/{session}/scan', [RegistrationSessionController::class, 'scan'])->name('registrations.scan');
    Route::post('/registrations/{session}/skip', [RegistrationSessionController::class, 'skip'])->name('registrations.skip');
    Route::post('/registrations/{session}/undo', [RegistrationSessionController::class, 'undoLast'])->name('registrations.undo');
    Route::post('/registrations/{session}/pause', [RegistrationSessionController::class, 'pause'])->name('registrations.pause');
    Route::post('/registrations/{session}/resume', [RegistrationSessionController::class, 'resume'])->name('registrations.resume');
    Route::post('/registrations/{session}/cancel', [RegistrationSessionController::class, 'cancel'])->name('registrations.cancel');
    Route::post('/registrations/{session}/finish', [RegistrationSessionController::class, 'finish'])->name('registrations.finish');
});

// Scan absensi via reader keyboard-wedge di browser — admin + petugas piket.
Route::middleware(['auth', 'verified', 'role:admin,petugas_piket'])->group(function () {
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::post('/scan', [ScanController::class, 'store'])->name('scan.store');
});

// Pengaturan & kenaikan kelas — admin saja.
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/holidays', [SettingsController::class, 'storeHoliday'])->name('settings.holidays.store');
    Route::delete('/settings/holidays/{holiday}', [SettingsController::class, 'destroyHoliday'])->name('settings.holidays.destroy');
    Route::post('/settings/holidays/generate', [SettingsController::class, 'generateHolidays'])->name('settings.holidays.generate');

    Route::get('/promotion', [PromotionController::class, 'index'])->name('promotion.index');
    Route::post('/promotion/prepare-year', [PromotionController::class, 'prepareYear'])->name('promotion.prepare-year');
    Route::post('/promotion/promote', [PromotionController::class, 'promote'])->name('promotion.promote');
});

Route::middleware(['auth', 'verified'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->middleware('role:admin')->name('index');
    Route::middleware('role:admin')->group(function () {
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::patch('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/auth.php';
