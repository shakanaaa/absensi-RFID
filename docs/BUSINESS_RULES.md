# BUSINESS RULES — Sistem Absensi RFID

Dokumen ini adalah sumber kebenaran aturan bisnis. Setiap aturan memiliki ID, status
(**Confirmed** = disetujui & terimplementasi, **Proposed** = rekomendasi menunggu
persetujuan, **Needs Decision** = belum ada keputusan), dan acceptance criteria.

Status implementasi rinci ada di `BUSINESS_RULES_IMPLEMENTATION_MATRIX.md`.

---

## A. Identitas & Hak Akses

### AUTH-001 — Role-based access control
- **Modul**: Autentikasi & Otorisasi
- **Deskripsi**: 8 role: `admin`, `kepala_sekolah`, `wakil_kepala_sekolah`,
  `wali_kelas`, `petugas_piket`, `koordinator_kegiatan`, `orang_tua`, `siswa`.
  Otorisasi ditegakkan di backend (middleware `role:` + policies), bukan sekadar
  menyembunyikan tombol.
- **Kondisi pemicu**: setiap request ke route terproteksi.
- **Syarat**: `users.role` atau `role_assignments` efektif (cek `effective_from/to`).
- **Proses**: middleware `EnsureRole` menolak 403 sebelum controller; policy
  memeriksa scope (kelas/sekolah/anak).
- **Hasil**: akses sesuai matriks di `ROLE_PERMISSION_MATRIX.md`.
- **Pengecualian**: route `/api/v1/device/*` memakai token terminal, bukan sesi user.
- **Hak akses**: semua role.
- **Dampak data**: tidak ada.
- **Acceptance criteria**: role tanpa hak mendapat 403 di backend; terverifikasi test.
- **Test scenario**: `MasterDataTest`, `ScheduleTest::test_orang_tua_cannot_access_schedules`, dll.
- **Status**: Confirmed.

### AUTH-002 — Segregation of duties
- **Modul**: Otorisasi
- **Deskripsi**: Pembuat data tidak boleh menyetujui datanya sendiri
  (schedule version: `created_by ≠ approver`). Pengaju permohonan tidak boleh
  memutus permohonannya sendiri.
- **Kondisi pemicu**: approve schedule version; decide request.
- **Hasil**: 403 bila pelanggaran.
- **Acceptance criteria**: test `ScheduleTest` & `RequestAndAlpaTest`.
- **Status**: Confirmed.

### AUTH-003 — Registrasi publik = orang tua
- **Modul**: Autentikasi
- **Deskripsi**: Registrasi mandiri hanya menghasilkan akun `orang_tua`
  (perbaikan B-02). Akun staf/siswa dibuat admin via User Management.
- **Status**: Confirmed (ADR-008).

### AUTH-004 — Scope wali kelas
- **Modul**: Otorisasi
- **Deskripsi**: Wali kelas hanya mengakses siswa di kelas binaannya
  (`classes.homeroom_teacher_id` atau `role_assignments` scope `class`)
  dengan membership aktif (`to_date` null).
- **Status**: Confirmed.

### AUTH-005 — Scope orang tua
- **Modul**: Otorisasi
- **Deskripsi**: Orang tua hanya mengakses anak dengan `guardian_links.verified_at` terisi.
- **Status**: Confirmed.

---

## B. Registrasi & Kepemilikan Kartu RFID

### RFID-001 — Satu kartu aktif per siswa, satu siswa aktif per kartu
- **Modul**: Kartu RFID
- **Deskripsi**: Assignment aktif unik via `active_flag='ACTIVE'` + unique index
  (pola kompatibel MySQL & sqlite; PostgreSQL partial index tidak dipakai — ADR).
- **Kondisi**: assign kartu.
- **Hasil**: `ValidationException` bila melanggar; dicek dalam transaksi.
- **Status**: Confirmed.

### RFID-002 — Lifecycle kartu
- **Modul**: Kartu RFID
- **Deskripsi**: Status: `active, unassigned, suspended, lost, damaged, replaced,
  revoked, archived`. Hanya status assignable yang boleh dipakai.
- **Status**: Confirmed.

### RFID-003 — Unassign tidak menghapus riwayat
- **Modul**: Kartu RFID
- **Deskripsi**: Unassign menandai assignment lama tidak aktif; riwayat
  kepemilikan dipertahankan. Kartu hanya bisa dihapus bila tanpa riwayat.
- **Status**: Confirmed.

### RFID-004 — Registrasi massal per kelas
- **Modul**: Registrasi
- **Deskripsi**: Sesi registrasi menyimpan snapshot roster saat dibuka (audit-safe).
  Scan via keyboard-wedge JSON endpoint; setiap attempt dicatat append-only di
  `registration_scan_attempts`.
- **Outcome scan**: `assigned`, `assigned_new_card` (UID baru auto-register),
  `already_assigned`, `card_in_use`, `student_has_card`, `card_blocked`, `invalid_uid`.
- **Syarat**: UID ≤64 char; bila `reader_profiles.pattern` dipilih, UID harus cocok.
- **Status**: Confirmed.

### RFID-006 — Kartu non-aktif tidak menghasilkan kehadiran
- **Modul**: Kehadiran/Kartu
- **Deskripsi**: Scan UID kartu berstatus non-assignable
  (`suspended, lost, damaged, revoked, archived, replaced`) → outcome
  `inactive_card`, tanpa sesi/gerakan. Dibedakan dari `unknown_card` (UID tak dikenal).
- **Status**: Confirmed.

### RFID-005 — Pause/resume/skip registrasi massal
- **Modul**: Registrasi
- **Deskripsi**: PRD mensyaratkan antrean siswa tanpa kartu, auto-next setelah
  berhasil, pause/resume/skip/cancel, indikator progres.
- **Kondisi saat ini**: endpoint `scan` + `finish`; UI registrasi menampilkan
  roster & attempts; **pause/resume/skip eksplisit belum ada**.
- **Status**: Needs Decision — apakah auto-next & pause/resume diperlukan di iterasi ini
  (lihat BD-08).

---

## C. Aturan Kehadiran

### ATT-001 — Sesi absensi per (siswa, tanggal sekolah, schedule_key)
- **Modul**: Kehadiran
- **Deskripsi**: `attendance_sessions` unik per siswa+`school_date`+`schedule_key`.
  `school_date` dihitung di timezone sekolah.
- **Status**: Confirmed.

### ATT-002 — Status absen
- **Modul**: Kehadiran
- **Nilai**: `belum_masuk, hadir, terlambat, izin, sakit, alpa_menunggu, alpa,
  dispensasi, tidak_berlaku`.
- **Aturan**:
  - `hadir`/`terlambat` dari scan IN pertama (terlambat bila `event_time > start_time` jadwal approved).
  - `izin`/`sakit`/`dispensasi` dari permohonan approved.
  - `tidak_berlaku` untuk hari libur/tidak_berlaku.
  - `alpa_menunggu` → `alpa` hanya via verifikasi wali kelas + konfirmasi admin (lihat ALPA-001).
- **Status**: Confirmed.

### ATT-003 — Status "sedang berlangsung" tidak final
- **Modul**: Kehadiran
- **Deskripsi**: Siswa yang sudah `hadir`/`di_sekolah` belum diputus alpa —
  evaluasi alpa hanya untuk siswa tanpa aktivitas sama sekali (lihat ALPA-001).
  Tidak ada status OUT fiktif: tanpa tap OUT, `movement_state` tetap
  `di_sekolah`/`keluar_sementara`, tidak ada waktu pulang rekaan.
- **Status**: Confirmed.

### ATT-004 — Hari libur tidak menghasilkan Alpa
- **Modul**: Kehadiran/Kalender
- **Deskripsi**: `schedule_versions.type ∈ {holiday, libur, tidak_berlaku}` →
  sesi `tidak_berlaku`, tanpa transisi movement; generator alpa tetap bisa
  membuat `alpa_menunggu` untuk tanggal tsb **bila** dijalankan manual —
  scheduler harian memakai tanggal kemarin dan tidak mengecek hari libur.
- **Proses**: `SchoolDayService::isEffectiveSchoolDay` — tanggal dalam tahun
  ajaran aktif DAN tidak ada schedule approved non-school.
- **Status**: Confirmed (BD-05, 2026-11-25).

### ATT-005 — Perubahan jadwal tidak mengubah fakta historis
- **Modul**: Jadwal
- **Deskripsi**: Scan dievaluasi terhadap `schedule_versions` approved terbaru
  pada saat evaluasi. Versi jadwal baru tidak menulis ulang sesi historis;
  koreksi manual lewat review/manual_adjustments.
- **Catatan**: event yang dievaluasi *setelah* jadwal berubah memakai jadwal baru
  (wajar untuk sync offline); event lama tidak dievaluasi ulang.
- **Status**: Confirmed.

### ATT-006 — Clock skew
- **Modul**: Kehadiran
- **Deskripsi**: `event_time` melenceng >24 jam dari `received_at` →
  `raw_rfid_events.time_quality='skewed'`; event tetap dievaluasi
  (waktu terminal = source of truth).
- **Status**: Confirmed.

---

## D. Pergerakan Siswa (Movement)

State machine didefinisikan di `AttendanceEvaluator::transition()` (rule_version v1.1).
Matriks lengkap: `STATE_TRANSITION_MATRIX.md`.

### MOV-001 — Transisi movement
- **Modul**: Pergerakan
- **Nilai `movement_state`**: `belum_di_sekolah, di_sekolah, keluar_sementara,
  sudah_pulang, perlu_konfirmasi`.
- **Ringkasan**:
  - IN dari `belum_di_sekolah` → `di_sekolah` (+status hadir/terlambat).
  - IN dari `keluar_sementara` → `di_sekolah` (`kembali`).
  - OUT dari `di_sekolah` → `keluar_sementara` bila sebelum `end_time`,
    `sudah_pulang` bila sesudah/pada `end_time`.
  - OUT dari `belum_di_sekolah` / IN dari `sudah_pulang` → `perlu_konfirmasi`.
  - Scan berulang state sama → outcome idempotent (`sudah_di_sekolah`, dll), tanpa movement baru.
- **Status**: Confirmed.

### MOV-002 — Debounce
- **Modul**: Pergerakan
- **Deskripsi**: Scan dalam 5 detik dari gerakan terakhir (berdasar `event_time`,
  aman untuk sync offline) → `ignored_duplicate_scan`, tanpa movement.
- **Status**: Confirmed.

### MOV-003 — Status absen berizin dilindungi
- **Modul**: Pergerakan
- **Deskripsi**: Bila `attendance_status ∈ PROTECTED` (`izin, sakit,
  alpa_menunggu, alpa, dispensasi, tidak_berlaku`), scan tetap mencatat
  movement tetapi TIDAK menimpa status absen.
- **Status**: Confirmed.

### MOV-004 — `perlu_konfirmasi` dan resolusi
- **Modul**: Pergerakan
- **Deskripsi**: Sesi `perlu_konfirmasi` muncul di halaman review; admin/petugas
  piket me-resolve ke state/status aktual dengan alasan wajib → menulis
  `manual_adjustments` (source `review`) + audit. Scan mentah tidak diubah.
- **Status**: Confirmed.

### MOV-005 — Event di terminal berbeda
- **Modul**: Pergerakan
- **Deskripsi**: Tidak ada aturan lokasi; transisi ditentukan `terminal.type`
  (`in`/`out`), bukan identitas terminal. Tap IN di terminal OUT tetap diperlakukan
  sebagai scan `in` sesuai `type` terminal.
- **Status**: Confirmed (perlu review — lihat BD-09).

---

## E. Izin & Persetujuan

### LEAVE-001 — Jenis permohonan
- **Modul**: Permohonan
- **Nilai `requests.type`**: `izin, sakit, dispensasi, lainnya`.
  `dispensasi` = dispensasi kegiatan sekolah; tidak disamakan dengan izin pribadi.
- **Status**: Confirmed.

### LEAVE-002 — Pengaju & scope
- **Modul**: Permohonan
- **Deskripsi**: Draft → submit → approve/reject. Pengaju: orang tua (anak
  verified), wali kelas (kelas binaan), staf (semua siswa). `lainnya` tidak
  mengubah status absen.
- **Status**: Confirmed.

### LEAVE-003 — Pemutus
- **Modul**: Permohonan
- **Deskripsi**: Admin, kepala sekolah, wakil kepala sekolah (semua jenis);
  wali kelas untuk siswa kelas binaannya. Petugas piket TIDAK bisa memutus.
  Pengaju tidak bisa memutus sendiri. Keputusan append-only di `request_decisions`.
- **Status**: Confirmed.
- **Catatan**: PRD menyebut "petugas piket tidak otomatis berwenang menyetujui
  seluruh jenis" — saat ini petugas tidak berwenang sama sekali; apakah perlu
  subset (mis. dispensasi) → BD-01.

### LEAVE-004 — Penerapan ke absen
- **Modul**: Permohonan
- **Deskripsi**: Approval `izin/sakit/dispensasi` menulis status tsb ke semua
  `attendance_sessions` tanggal dalam `period_start..period_end` (membuat sesi
  `belum_masuk` bila belum ada), tanpa menimpa status final (`alpa`, `tidak_berlaku`, dll).
- **Proses**: `applyToAttendance` melewati tanggal non-efektif via
  `SchoolDayService` (BD-05 confirmed).
- **Status**: Confirmed.

### LEAVE-005 — Lampiran & bukti
- **Modul**: Permohonan
- **Deskripsi**: `requests.attachment_path` ada di skema; **UI upload belum ada**.
- **Status**: Needs Decision (BD-06) — apakah lampiran wajib untuk sakit/dll.

### LEAVE-006 — Batas waktu & darurat
- **Modul**: Permohonan
- **Deskripsi**: Belum ada aturan batas waktu pengajuan / jalur darurat.
- **Status**: Needs Decision (BD-02).

### LEAVE-007 — Sanggahan orang tua
- **Modul**: Permohonan
- **Deskripsi**: Tidak ada mekanisme sanggahan; orang tua hanya bisa membuat
  permohonan baru.
- **Status**: Needs Decision (BD-07).

---

## F. Pulang Cepat & Perubahan Jadwal

### EARLY-001 — `pulang_awal` sebagai versi jadwal resmi
- **Modul**: Jadwal
- **Deskripsi**: Perubahan jam pulang dicatat sebagai `schedule_versions.type=
  'pulang_awal'` dengan `end_time` baru; saat disetujui dicatat di
  `early_dismissal_approvals`. Bukan izin massal.
- **Status**: Confirmed.

### EARLY-002 — Cakupan jadwal
- **Modul**: Jadwal
- **Deskripsi**: Saat ini `academic_schedules.scope_type` selalu `school`.
  Cakupan tingkat/kelas belum diimplementasikan.
- **Status**: Needs Decision (BD-10).

### EARLY-003 — Alur persetujuan jadwal
- **Modul**: Jadwal
- **Deskripsi**: Admin/petugas buat jadwal & versi (draft) → kepala sekolah/
  wakil/admin approve. Pembuat ≠ approver. Approve versi baru membatalkan
  approved lain untuk tanggal sama. Cancel tersedia.
- **Status**: Confirmed.

### EARLY-004 — Pengumuman perubahan jadwal
- **Modul**: Notifikasi
- **Deskripsi**: Belum ada notifikasi ke orang tua saat jadwal disetujui.
- **Status**: Needs Decision (BD-03).

---

## G. Kalender & Hari Efektif

### CAL-001 — Kalender 3 view
- **Modul**: Kalender
- **Deskripsi**: `/calendar` view bulan/minggu/hari; per hari: jumlah status
  absen (scope role), tipe jadwal, kegiatan sekolah. Detail hari = per siswa.
- **Status**: Confirmed.

### CAL-002 — Prioritas jadwal
- **Modul**: Kalender/Kehadiran
- **Deskripsi**: Evaluator memilih `schedule_versions` approved dengan
  `version_no` tertinggi per tanggal; non-school types menang atas `normal`.
  Karena approve baru meng-cancel approved lama, tidak ada ambiguitas.
- **Status**: Confirmed.

### CAL-003 — Izin multi-hari hanya pada hari efektif
- **Modul**: Permohonan
- **Deskripsi**: Izin multi-hari hanya berlaku pada hari sekolah efektif;
  hari libur/non-efektif dilewati (BD-05 confirmed).
- **Status**: Confirmed.

---

## H. Notifikasi

### NOTIF-001 — Outbox pattern
- **Modul**: Notifikasi
- **Deskripsi**: Semua notifikasi ditulis ke `notification_outbox` idempotent
  (`idempotency_key` unik); `notifications:send` tiap menit memproses antrean;
  retry maks 3 attempts → `failed`. Status `sent` hanya setelah provider
  mengembalikan message id.
- **Status**: Confirmed.

### NOTIF-002 — Trigger saat ini
- **Modul**: Notifikasi
- **Trigger**: `request.approved`, `request.rejected` (ke pengaju),
  `alpa.confirmed` (ke wali verified). Hanya bila `users.phone` terisi.
- **Status**: Confirmed.

### NOTIF-003 — Cakupan notifikasi PRD
- **Modul**: Notifikasi
- **Deskripsi**: PRD mensyaratkan notifikasi untuk tap IN/OUT, tidak ada
  kehadiran, terlambat, izin diajukan, perubahan jadwal, koreksi data.
  **Baru 2 dari ~9 trigger terimplementasi.**
- **Status**: Needs Decision (BD-03) — template, opt-out, dan mana yang prioritas.

### NOTIF-004 — Provider
- **Modul**: Notifikasi
- **Deskripsi**: `WhatsAppProvider` contract + `MockWhatsAppProvider`.
  Provider produksi belum dipilih.
- **Status**: Confirmed (mock disengaja sampai provider diputuskan).

---

## I. Koreksi & Audit

### AUDIT-001 — Append-only audit log
- **Modul**: Audit
- **Deskripsi**: `audit_logs` mencatat actor, action, entity, before/after ref,
  reason, request_id, source, created_at. Tidak ada update/delete.
- **Status**: Confirmed.

### AUDIT-002 — Koreksi kehadiran
- **Modul**: Kehadiran
- **Deskripsi**: Resolusi `perlu_konfirmasi` menulis `manual_adjustments`
  (source `review`) + audit; event mentah `raw_rfid_events` tidak pernah diubah.
  `manual_adjustments` punya `requested_by`/`approved_by` — alur approval
  dua tahap belum diimplementasikan.
- **Status**: Partially Confirmed (lihat BD-11).

### AUDIT-003 — Event mentah abadi
- **Modul**: Kehadiran
- **Deskripsi**: `raw_rfid_events` append-only; `event_evaluations` mencatat
  outcome + rule_version per evaluasi. Event duplikat di-ack tanpa re-evaluasi
  (idempotent by `event_id`).
- **Status**: Confirmed.

---

## J. Terminal & Sinkronisasi

### TERM-001 — Auth terminal
- **Modul**: Terminal
- **Deskripsi**: Bearer token → SHA-256 hash lookup; token revoked & terminal
  non-aktif ditolak; `last_used_at` diupdate.
- **Status**: Confirmed.

### TERM-002 — Ingest idempotent
- **Modul**: Terminal
- **Deskripsi**: `POST /api/v1/device/events` batch ≤500; `event_id` unik →
  duplikat di-ack `duplicate` tanpa evaluasi ulang (race-safe via constraint).
- **Status**: Confirmed.

### TERM-003 — Offline & event terlambat
- **Modul**: Terminal
- **Deskripsi**: `event_time` = waktu terminal (source of truth); event sync
  terlambat tetap dievaluasi terhadap jadwal tanggal `event_time`.
  `time_quality=skewed` bila drift >24 jam. Event belum tersinkronisasi
  (`sync_status`) tidak dianggap final — tapi tidak ada status "pending" di
  evaluasi; event yang masuk langsung dievaluasi.
- **Status**: Confirmed — catatan: `sync_status` kolom ada, belum dipakai alur offline.

### TERM-004 — Insiden terminal
- **Modul**: Terminal
- **Deskripsi**: `terminal_incidents` ada di skema; **UI/manajemen belum ada**.
- **Status**: Not Implemented.

---

## K. Laporan & Perhitungan

### REPORT-001 — Dashboard & rekap
- **Modul**: Laporan
- **Deskripsi**: Dashboard menampilkan ringkasan kehadiran hari ini + antrean
  (perlu konfirmasi, alpa menunggu, permohonan diajukan). Halaman Rekap:
  filter periode+kelas, rekap per kelas & per siswa dengan formula BD-12.
  Ekspor data belum ada.
- **Status**: Partially Implemented (ekspor pending).

### REPORT-002 — Definisi persentase kehadiran
- **Modul**: Laporan
- **Deskripsi**: **Confirmed (BD-12)**: `(hadir + terlambat) / hari_efektif × 100`.
  Izin/sakit/alpa/dispensasi/tidak_berlaku/belum_masuk kategori terpisah,
  tidak dihitung ganda. Lihat `ReportController`.
- **Status**: Confirmed.

---

## Ringkasan Status

| Status | Jumlah aturan |
|---|---|
| Confirmed | ~30 |
| Partially Confirmed | 2 (LEAVE-004, AUDIT-002) |
| Needs Decision | 8 (RFID-005, ATT-004, LEAVE-004/005/006/007, CAL-003, NOTIF-003, EARLY-002/004, REPORT-002) |
| Not Implemented | 2 (TERM-004, REPORT-001) |
