# Architecture Decision Records

## ADR-001 — Modular Monolith (mengikuti TAD)

Backend tetap satu codebase Laravel. Domain dipisah logical via model/service/action per modul (lihat AGENTS.md). Tidak ada microservices.

## ADR-002 — Database: MySQL, bukan PostgreSQL (deviasi sadar dari TAD)

**Konteks:** TAD merekomendasikan PostgreSQL 18 + Redis. Lingkungan pengguna XAMPP dengan MySQL/MariaDB sudah berjalan dan dipilih pengguna secara eksplisit.

**Keputusan:** MySQL sebagai DB utama untuk development dan target deployment awal. Queue/cache tetap `database` driver (bukan Redis) sampai ada alasan scaling.

**Konsekuensi:**
- Tidak ada partial unique index → constraint "satu aktif" kartu memakai kolom `active_flag` (berisi `'ACTIVE'` atau NULL; MySQL unique mengizinkan multi-NULL).
- `json` column didukung MySQL — aman.
- Transaksi & row locking didukung — aman untuk idempotency.
- Test suite jalan di SQLite in-memory; semua migrasi diverifikasi kompatibel SQLite dan MySQL.
- Jika nanti migrasi ke PostgreSQL diperlukan, tidak ada fitur yang mengunci vendor; cukup ganti `DB_CONNECTION` dan review index.

**Ditinjau:** bila deployment production memakai managed PostgreSQL, buat rencana migrasi terpisah.

## ADR-003 — UID RFID sebagai string

`uid_text` disimpan verbatim (string). Tidak pernah cast integer — leading zero dan case harus dipertahankan (PRD §17.2). Normalisasi hanya via `reader_profiles.pattern` yang teruji.

## ADR-004 — Tiga lapisan data (PRD §4.3)

- L1 fakta: `raw_rfid_events` — append-only, tidak ada update/delete via aplikasi.
- L2 evaluasi: `event_evaluations` — dapat dievaluasi ulang dengan `rule_version`.
- L3 keputusan: `request_decisions`, `early_dismissal_approvals`, `manual_adjustments`, `audit_logs` — append-only, keputusan baru membatalkan lewat record baru.

## ADR-005 — Tidak ada Alpa otomatis

Status `alpa` hanya dapat dibuat lewat konfirmasi admin setelah verifikasi wali kelas (PRD Opsi B). Sistem hanya menghasilkan `alpa_menunggu`.

## ADR-006 — Terminal = klien edge terpisah

Terminal RFID (Tauri 2 + Vue 3 + SQLite outbox sesuai TAD) adalah aplikasi terpisah di luar repo ini. Backend menyediakan `/api/v1/device/*` dengan device token Sanctum. Web portal tidak boleh mengklaim kemampuan offline-first.

## ADR-007 — Status memakai kode Indonesia

`attendance_status`: `belum_masuk`, `hadir`, `terlambat`, `izin`, `sakit`, `alpa_menunggu`, `alpa`, `dispensasi`, `perlu_pemeriksaan`, `tidak_berlaku`. `movement_state`: `belum_di_sekolah`, `di_sekolah`, `keluar_sementara`, `perlu_konfirmasi`, `sudah_pulang`. Konsisten dengan PRD §6.2–6.3.

## ADR-008 — Registrasi publik = akun orang tua

Route `register` bawaan Breeze tidak lagi dipakai staf/sekolah. Pendaftar publik mendapat `role='orang_tua'` dan `school_id=null`. Sekolah baru ditetapkan saat admin menautkan akun tersebut ke siswa lewat `guardian_links` (`claimSchool` di `GuardianLinkController`). Akun `siswa` dan staf hanya dibuat admin lewat manajemen pengguna. Alasan: pendaftar mandiri di sistem ini adalah wali; siswa tidak punya kredensial mandiri.
