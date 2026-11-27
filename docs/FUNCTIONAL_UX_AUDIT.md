# FUNCTIONAL UX AUDIT — Sistem Absensi RFID

Tanggal: 26 November 2026. Lingkup: alur kerja per modul & per role.
Batasan: tanpa ubah tampilan/design system, tanpa ubah struktur DB,
tanpa melemahkan business rules.

## A. Temuan per modul

### A1. Master Data
- **Siswa**: form mewajibkan `class_id`, `academic_year_id`, `from_date`
  manual — padahal 90% kasus = tahun ajaran aktif + tanggal hari ini.
  Kelas sudah memuat `academic_year_id` → input tahun ajaran duplikatif.
- **Orang tua/wali**: ada 2 tempat menautkan (halaman Guardians khusus dan
  di detail Siswa untuk admin). Tidak berbahaya, tapi form guardian
  terpisah menambah langkah.
- **Kelas**: CRUD standar, sudah baik.

### A2. RFID & Registrasi
- Registrasi massal sudah baik: pilih kelas → antrean otomatis → scan →
  auto-next, pause/skip/cancel, progress.
- Sisa gap: tidak ada **ringkasan hasil** setelah sesi selesai (hanya
  angka progress; tidak ada rekap outcome per kategori).

### A3. Terminal
- Kelola token/pairing sudah sederhana. Status koneksi terlihat via
  `last_seen_at` di tabel — tapi tidak ada indikator "offline" eksplisit
  (harus dihitung manual dari timestamp).
- Insiden terminal: modul baru, alur buka→resolve sudah satu halaman.

### A4. Absensi harian
- Fully otomatis via device API — sudah sesuai target (tidak ada langkah
  manual untuk tap normal).

### A5. Review anomali
- Satu daftar + filter tanggal/kelas/terminal + halaman detail + resolve
  dengan alasan wajib — sudah sesuai target. Tidak ada yang perlu diubah.

### A6. Permohonan izin — **temuan utama**
- Alur sekarang: buat draft (form) → buka detail → klik "Ajukan" →
  menunggu diputuskan. **3 halaman untuk satu permohonan sederhana.**
- **Tidak ada edit draft** — Phase 10 mensyaratkan "edit draft" tetapi
  route `update` tidak ada; satu kesalahan ketik = hapus & buat ulang.
- Index hanya filter status — tidak ada filter jenis/cari siswa.
- `period_end` opsional tapi tidak ada default; ortu sering tidak tahu
  format rentang.

### A7. Alpa
- Daftar datar per siswa; tidak ada pengelompokan kelas, tidak ada
  ringkasan (total/terverifikasi/belum), filter tanggal default kosong
  (harus pilih manual), form generate juga minta tanggal manual padahal
  99% kasus = hari ini.
- Alur Opsi B sudah benar (verify wali → confirm admin) — dipertahankan.

### A8. Jadwal & pulang cepat
- Draft→approve sudah benar. Form versi jadwal perlu dicek apakah default
  tanggal = hari ini (perlu verifikasi saat implementasi).

### A9. Kalender
- Read-only, data dari sumber utama — sudah sesuai target.

### A10. Notifikasi
- Outbox + idempotency + retry sudah benar. Trigger dibatasi keputusan
  (approved/rejected/alpa) — tidak ada spam. Tidak ada yang perlu diubah.

### A11. Dashboard & laporan
- Dashboard role-based baru selesai; angka bisa ditelusuri via tautan.
- Laporan: filter dari/sampai/kelas diisi manual tiap kali — bisa default
  bulan berjalan (sudah default `startOfMonth`→hari ini, OK).
  Tidak ada preset periode (harian/mingguan/bulanan) — 1 klik penghematan.

## B. Deteksi fitur redundan

| # | Temuan | Kategori |
|---|---|---|
| R1 | Permohonan draft→submit→detail = 3 halaman untuk aksi rutin | MERGE WORKFLOW |
| R2 | Tidak ada edit draft → hapus+buat ulang | SIMPLIFY |
| R3 | Index permohonan tanpa filter jenis/pencarian | SIMPLIFY |
| R4 | Form siswa: tahun ajaran & from_date manual padahal deterministik | AUTOMATE (smart default) |
| R5 | Alpa: tanpa default tanggal, tanpa ringkasan, tanpa filter kelas | SIMPLIFY |
| R6 | Laporan tanpa preset periode cepat | SIMPLIFY |
| R7 | Terminal: status online/offline harus dibaca dari timestamp | AUTOMATE (badge) |
| R8 | Ringkasan hasil sesi registrasi tidak ada | SIMPLIFY |

## C. Yang TIDAK diubah (sengaja)

- Approval permohonan & jadwal (business rule).
- Verifikasi→konfirmasi Alpa Opsi B.
- Review anomali (sudah optimal).
- Kalender (read-only by design).
- Struktur DB, tampilan, design system — sesuai batasan.
