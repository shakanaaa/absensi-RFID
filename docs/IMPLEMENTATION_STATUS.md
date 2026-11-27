# Implementation Status — Sistem Absensi Sekolah RFID

Terakhir diperbarui: 26 November 2026 (gap phases master prompt — 101 test PASS)

## 0. Update 26 November 2026 — Gap Phases

Setelah audit business rules (Phase 0–9 roadmap awal selesai), dikerjakan gap
dari master execution prompt:

| Gap | Status |
|---|---|
| Phase 4 — kontrol sesi registrasi massal (pause/resume/skip/cancel, auto-next, progress) | ✅ Done |
| Phase 6 — device API: pairing, config, heartbeat, last_seen, rate limit | ✅ Done |
| Phase 9 — review anomali: filter kelas/terminal + halaman detail | ✅ Done |
| Phase 15 — dashboard per role + ekspor CSV laporan | ✅ Done |
| Phase 16 — modul insiden terminal (UI + policy + audit) | ✅ Done |
| Phase 7 — aplikasi terminal Tauri (scaffold `terminal/`) | ⚠️ Scaffold — frontend build OK; Rust belum dikompilasi; hardware belum diuji |
| Phase 17 — audit coverage + USER_GUIDE/TERMINAL_SETUP_GUIDE | ✅ Done |

Verifikasi: `php artisan test` → **101 test PASS**; `npm run build` → PASS;
`terminal/` `npm run build` → PASS.

Belum selesai/menunggu: keputusan BD-01,03,04,06–11 (lihat
`BUSINESS_DECISIONS.md`); pengujian reader fisik & provider WA resmi;
mode REG via API device (menunggu BD-08); approval koreksi 2 tahap (BD-11).

Sumber kebenaran: PRD v1.0 dan TAD v1.0 (diberikan pengguna dalam percakapan; belum disimpan sebagai file di repo — lihat bagian Dokumen).

## 0b. Update 26 November 2026 (penyelarasan dengan keputusan percakapan)

**Diubah:**
- Evaluator v1.2: scan keluar sebelum jam pulang tidak lagi otomatis keluar sementara.
  Hasilnya `keluar_sementara` (izin disetujui), `pulang_awal_berizin`, atau
  `pulang_awal_tanpa_izin` (fakta tetap dicatat, hanya ditandai). Jadwal bertipe
  `pulang_awal` menghasilkan `pulang_cepat_terjadwal`. Tap masuk setelah jam pulang
  menjadi `perlu_konfirmasi`.
- Tap yang datang belakangan menggantikan `alpa_menunggu` (mis. sinkronisasi offline).
  Izin yang disetujui tidak menimpa hadir/terlambat, tetapi menggantikan `alpa_menunggu`.
- Permohonan menunggu tidak dihitung belum ada keterangan. Bila ditolak, tanggal lampau
  kembali ke antrean.
- Jenis permohonan baru `izin_pulang_awal` dan `izin_keluar_sementara` (jam wajib).
- Cutoff harian `attendance:cutoff` (09:00 Asia/Jakarta, dapat diatur lewat env) menandai
  belum ada keterangan dan mengantrekan rekap ke admin dan wali kelas.
- UI tidak lagi menyebut "Alpa" sebelum dikonfirmasi admin. Nilai database tetap `alpa_menunggu`.
- Registrasi massal: pembacaan ulang kartu yang sama diabaikan (`duplicate_read`),
  dan ada Batalkan Pendaftaran Terakhir (`undone`, riwayat tidak dihapus).
- Debounce tap 3 detik dari `config/attendance.php`.
- Tes baru `MovementDecisionTest`. `DeviceApiTest` disesuaikan.

**Belum diverifikasi:** `php` dan `composer` tidak ada di lingkungan penyusunan, jadi
`php artisan test` dan `npm run build` belum dijalankan setelah perubahan ini.
Jalankan keduanya sebelum dipakai.

**Masih terbuka terhadap keputusan:** jadwal pulang cepat per kelas dan tingkat beserta
konflik, pengesahan massal, pembatalan, dan jalur darurat kepala sekolah. Pulang Belum
Tercatat dan penutupan hari (Final dan Final dengan Catatan). Kategori dan durasi maksimum
keluar sementara beserta peringatan terlambat kembali. Koreksi dua tingkat. Notifikasi orang
tua beserta preferensi. Bukti sakit, batas mundur, sanggahan. Hadir Manual dan batas bulanan.
Dispensasi dengan daftar peserta. Status kartu lengkap, penangguhan piket, Uji Reader.
Lima ukuran statistik terpisah dan status "belum final" di kalender.

## 1. Ringkasan Eksekutif

Phase 1–4 selesai: RBAC + master data + kartu RFID/registrasi massal + device API terminal (`/api/v1/device/*`) dengan ingest event idempotent dan attendance engine v1 (state machine masuk/keluar, hadir/terlambat, perlu_konfirmasi). Fondasi Laravel 13 + Vue 3/Inertia + Tailwind + Sanctum di MySQL (XAMPP). Phase 4 menambah: hari libur → `tidak_berlaku`, debounce scan 5 detik, proteksi status berizin, flag `time_quality=skewed` untuk clock melenceng >24 jam, dan alur review `perlu_konfirmasi` (`AttendanceReviewController` + halaman `Attendance/Review`, resolve menulis `manual_adjustments` + audit). Belum ada: workflow permohonan, editing jadwal, notifikasi, dashboard absensi.

## 2. Verifikasi (Phase 4)

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test` | PASS — 61 test, 173 assertion (6 test baru `AttendanceEdgeCaseTest`) |
| `npm run build` (Vite) | PASS — build produksi sukses |

## 2a. Verifikasi (Phase 3)

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test` | PASS — 55 test, 155 assertion (9 test baru `DeviceApiTest`) |
| `npm run build` (Vite) | PASS — build produksi sukses |

## 2a. Verifikasi (Phase 2)

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test` | PASS — 46 test, 122 assertion (12 test baru `CardManagementTest`) |
| `npm run build` (Vite) | PASS — build produksi sukses |

## 2a. Verifikasi (Phase 1)

| Pemeriksaan | Hasil |
|---|---|
| `php artisan migrate:fresh --seed` | PASS — 15 migrasi, seed admin + wali kelas + orang tua demo |
| `php artisan test` | PASS — 34 test, 93 assertion (9 test baru `MasterDataTest` untuk RBAC & master data) |
| `npm run build` (Vite) | PASS — build produksi sukses |
| `php artisan serve` + curl `/login`, `/master/students` | PASS — 200 / 302 (guest redirect ke login) |

## 2a. Phase 0 — Verifikasi Fondasi (historis)

| Pemeriksaan | Hasil |
|---|---|
| `php artisan migrate:fresh --seed` | PASS — 14 migrasi, seed admin + data demo |
| `php artisan test` | PASS — 25 test, 61 assertion (semua bawaan Breeze/laravel) |
| `npm run build` (Vite) | PASS — build produksi sukses |
| `php artisan serve` + curl `/`, `/login` | PASS — HTTP 200 |

## 3. Yang Sudah Tersedia

### Infrastruktur
- Autentikasi web lengkap (login, register, logout, reset password, verifikasi email, konfirmasi password) — Breeze.
- Sanctum terinstall; `api.php` hanya berisi `GET /api/user`.
- Session/cache/queue driver: `database` (bukan Redis — lihat ADR-002).
- Health check `/up`.

### Skema Database (15 migrasi domain, semua PASS di MySQL)
- Struktur sekolah: `schools`, `academic_years`, `terms`, `grades`, `classes`.
- Siswa: `students`, `student_class_memberships` (keanggotaan bertanggal + nomor absen), `guardian_links` (relasi terverifikasi, multi-anak).
- User/RBAC: kolom `school_id`, `role`, `phone`, `status` di `users`; `role_assignments` untuk role bertanggal berscope.
- RFID: `reader_profiles`, `rfid_cards` (UID string unik), `student_card_assignments` (satu aktif per kartu & per siswa via `active_flag`), `terminals`, `terminal_tokens`.
- Absensi: `raw_rfid_events` (append-only, `event_id` UUID unik, event_time vs received_at terpisah), `event_evaluations`, `attendance_sessions` (unik per siswa/tanggal/schedule_key), `movement_transactions`.
- Jadwal: `academic_schedules`, `schedule_versions` (versioning + approval), `early_dismissal_approvals`.
- Permohonan: `requests`, `request_decisions`; pendaftaran massal: `registration_sessions`, `registration_scan_attempts`.
- Operasional: `school_activities`, `activity_participants`, `terminal_incidents`, `manual_adjustments`.
- Notifikasi & audit: `notification_outbox` (idempotency_key unik), `audit_logs` (append-only, tanpa updated_at).

### Model
- 32 model Eloquent dengan relasi dan casts tanggal/JSON untuk entitas inti.

### Seeder
- 1 sekolah demo, 1 admin (`admin@absensi.test` / `password`), tahun ajaran 2026/2027, 1 grade, 1 kelas (X IPA 1), 5 siswa, 1 reader profile.

### Phase 1 — RBAC & Master Data
- Enum `App\Enums\UserRole` (8 role), middleware `role:` (alias `role`), method `User::hasRole/isAdmin/waliKelasClassIds`.
- 6 policy: Student, SchoolClass, AcademicYear, Grade, GuardianLink, User — tenant isolation per `school_id`, wali kelas scoped ke kelas binaan (homeroom + `role_assignments` scope `class`), orang tua scoped ke anak tertaut terverifikasi.
- Controller + form request + route untuk: academic-years, terms, grades, classes, students (dengan membership efektif), guardian-links, users.
- `App\Services\AuditLogger` menulis `audit_logs` untuk create/update/delete master data, perubahan role user, suspend user.
- UI Vue di `resources/js/Pages/Master/` (index + form per resource, halaman detail siswa: riwayat kelas, kartu RFID, wali) + sidebar `AuthenticatedLayout` dengan nav role-aware + `SideNavLink`.
- `HandleInertiaRequests` share `auth.user.role/role_label/school` dan `flash`.
- Registrasi publik kini membuat akun `orang_tua` (bukan `student`); `school_id` diisi otomatis saat admin menautkan wali ke siswa (ADR-008).

### Phase 2 — Kartu RFID & Registrasi Massal
- `App\Services\CardService`: assign/unassign dengan transaction + `active_flag` uniqueness, status guard, audit.
- `RfidCardController`: index (filter status/UID, scoped ke kartu sekolah), store, update status, assign, unassign, destroy (hanya tanpa histori).
- `RegistrationSessionController`: sesi per kelas dengan snapshot roster, scan endpoint JSON (keyboard-wedge), finish; setiap percobaan scan tercatat di `registration_scan_attempts` (outcome: `assigned`, `assigned_new_card`, `already_assigned`, `card_in_use`, `student_has_card`, `card_blocked`, `invalid_uid`).
- Kebijakan: tulis kartu & operasikan sesi = `admin` + `petugas_piket`; baca = staf + petugas. Validasi UID opsional via `reader_profiles.pattern`.
- UI: `Master/Cards/Index` (tabel + assign modal + ganti status), `Master/Registrations/{Index,Create,Show}` (roster + konsol scan + riwayat percobaan).
- Policy baru: `RfidCardPolicy`, `RegistrationSessionPolicy`.

### Phase 3 — Device API & Attendance Engine v1
- Middleware `terminal` (`AuthenticateTerminal`): Bearer token → `token_hash` SHA-256, tolak token revoked / terminal nonaktif; `last_used_at` di-update.
- `TerminalController` + `TerminalPolicy` + halaman `Master/Terminals`: CRUD terminal (arah in/out), terbitkan token (plaintext tampil sekali via flash), cabut token.
- `POST /api/v1/device/events` (batch ≤500): idempotent via `event_id` unik — duplicate → `status: duplicate` tanpa evaluasi ulang; `GET /api/v1/device/me` untuk handshake.
- `App\Services\AttendanceEvaluator` (rule_version `v1`): resolusi siswa via kartu aktif → `attendance_sessions` per (siswa, tanggal sekolah, schedule_key) → transisi `movement_state` + `attendance_status` (`hadir`/`terlambat` vs `schedule_versions` approved) → `event_evaluations` + `movement_transactions` + `evaluation_status`. Unknown UID → `unknown_card` tanpa sesi.
- Keputusan disederhanakan untuk v1 (didokumentasikan di docblock service): out-scan sebelum `end_time` jadwal → `keluar_sementara`, sesudahnya → `sudah_pulang`; out tanpa masuk / in setelah pulang → `perlu_konfirmasi`. Edge case lengkap = Phase 4.

## 4. Fondasi Saja (belum jadi fitur)

- `role_assignments` effective-dating sudah dipakai untuk scope wali kelas, tetapi belum ada UI pengelolaannya.
- `notification_outbox` ada tabelnya, tetapi **tidak ada job worker/dispather**.
- `terminal_tokens` ada tabelnya, tetapi **belum ada endpoint pairing/auth perangkat**.
- `audit_logs` sudah ditulis untuk aksi master data & user; modul bisnis lain belum.
- `reader_profiles` dipakai validasi UID saat registrasi; `terminals` + token sudah punya UI (`Master/Terminals`) — tetapi terminal Tauri client belum ada (Phase 3 API saja).
- Semua state machine PRD (movement, request, schedule, registration) **belum diimplementasikan**.

## 5. Belum Dibuat Sama Sekali

Modul bisnis Phase 4–9: penyempurnaan state machine & edge cases, request/approval workflow + Alpa (Opsi B), jadwal & early dismissal, kalender, notifikasi WhatsApp, dashboard/laporan, terminal Tauri.

## 6. Bug & Masalah yang Ditemukan

| # | Masalah | Severity | Status |
|---|---|---|---|
| B-01 | `.env.example` masih `DB_CONNECTION=sqlite` padahal proyek memakai MySQL | Rendah | DIPERBAIKI di Phase 0 |
| B-02 | Registrasi publik Breeze membuat user dengan `role='student'` (default kolom) — akun orang tua yang daftar sendiri akan salah role | Sedang | DIPERBAIKI di Phase 1 — registrasi publik → `orang_tua`, `school_id` diisi saat tertaut (ADR-008) |
| B-03 | `attendance_sessions.schedule_version_id` adalah kolom polos tanpa FK (schedule dibuat di migrasi setelahnya) | Rendah | By design sementara; evaluasi FK di Phase 3 |
| B-04 | `phpunit.xml` memakai SQLite in-memory sementara dev MySQL — test tidak menguji perilaku MySQL-specific (partial index tidak dipakai; kita tidak memakainya) | Rendah | Dicatat; constraint kita kompatibel dua-duanya |
| B-05 | File `database/database.sqlite` sisa instalasi default masih ada (di-gitignore) | Rendah | Tidak berbahaya; biarkan |

## 7. Ketidaksesuaian PRD ↔ Repository

- **DB**: PRD/TAD menyebut PostgreSQL 18 + Redis; repo memakai MySQL + database queue/cache. Lihat `ARCHITECTURE_DECISIONS.md` ADR-002 — keputusan sadar, bukan bug.
- **Terminal**: TAD merencanakan app desktop Tauri 2 terpisah. Belum ada; Phase 3 menyiapkan API device, terminal client menyusul.
- **Dokumen PRD/TAD**: belum ada file di repo. Diminta penempatan di `docs/` atau akan disalin dari lampiran.

## 8. Ketidaksesuaian TAD ↔ Repository

- Queue/cache `database` vs Redis (ADR-002).
- `php artisan install:api` sudah jalan; Sanctum `HasApiTokens` sudah ditambahkan ke User.
- CLAUDE.md meminta instalasi Laravel Boost (`composer require laravel/boost --dev` + `boost:install`) — opsional, ditunda; tidak dibutuhkan untuk roadmap.

## 9. Prioritas Pengerjaan (selaras roadmap master prompt)

1. ~~**Phase 1**~~ — SELESAI: RBAC + master data + audit + B-02.
2. ~~**Phase 2**~~ — SELESAI: manajemen kartu + registrasi massal (web scan). Catatan: capture UID via keyboard-wedge bekerja di browser; PoC hardware tetap disarankan sebelum go-live (lihat §10).
3. ~~**Phase 3**~~ — SELESAI: device API + ingest idempotent + attendance engine v1.
4. ~~**Phase 4**~~ — SELESAI: edge cases movement (libur, debounce, proteksi status, clock skew) + review `perlu_konfirmasi`.
5. ~~**Phase 5**~~ — SELESAI: request/approval (izin/sakit/dispensasi) + Alpa Opsi B (generate → verifikasi wali kelas → konfirmasi admin).
4. **Phase 4** — Lengkapi transisi movement & edge cases.
6. ~~**Phase 5**~~ — SELESAI.
7. ~~**Phase 6**~~ — SELESAI: jadwal sekolah berversi (draft→approve, supersede per tanggal) + pulang awal (`early_dismissal_approvals`).
8. ~~**Phase 7**~~ — SELESAI: kalender absensi 3 view (bulan/minggu/hari) dengan scoping per role.
9. ~~**Phase 8**~~ — SELESAI: notification outbox + `notifications:send` (tiap menit) + WA provider mock; trigger: keputusan permohonan & konfirmasi alpa.
10. ~~**Phase 9**~~ — SELESAI: dashboard ringkasan + rekap kehadiran (formula BD-12 confirmed). Audit business rules lengkap (5 dokumen + 12 keputusan).
6. **Phase 6** — Schedule & early dismissal.
7. **Phase 7** — Kalender (3 view).
8. **Phase 8** — Notification outbox + worker + provider WA adapter (mock dulu).
9. **Phase 9** — Dashboard & reporting.

## 10. Risiko Implementasi

- **Reader keyboard-emulation**: capture UID di browser tidak bisa membedakan scan vs ketikan — butuh fokus input ketat + validasi format + kiosk mode (PRD sudah mensyaratkan). PoC hardware wajib sebelum go-live.
- **MySQL vs PostgreSQL**: tidak ada partial unique index; constraint kita sudah dirancang kompatibel (active_flag pattern). Perlu uji konkurensi update kartu.
- **Registrasi massal butuh server online** (TAD §7.3) — tidak ada mode offline untuk REG.
- **Belum ada job queue worker** yang berjalan; notifikasi Phase 8 perlu `php artisan queue:work` atau scheduler.
- **Timezone**: `schools.timezone` ada; semua evaluasi waktu harus konsisten Asia/Jakarta default.

## 11. Dokumen

- `docs/IMPLEMENTATION_STATUS.md` — dokumen ini.
- `docs/ARCHITECTURE_DECISIONS.md` — ADR termasuk keputusan MySQL.
- `docs/DATABASE_SCHEMA.md` — ringkasan skema.
- `docs/API_DOCUMENTATION.md`, `docs/TESTING_GUIDE.md`, `docs/DEPLOYMENT_GUIDE.md`, `docs/CHANGELOG.md` — stub, diisi per fase.
- PRD v1.0 & TAD v1.0: sumber kebenaran dari lampiran percakapan; belum tersimpan di repo (dapat ditambahkan ke `docs/`).
