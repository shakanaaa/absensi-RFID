# FEATURE WORKFLOW MAPPING

Peta alur per fitur: aktor → langkah → sistem → output. Status:
`[OK]` sudah sesuai target, `[S]` disederhanakan sesi ini, `[G]` gap
menunggu keputusan bisnis.

## 1. Absensi harian (core) — [OK]
Siswa tap → terminal → `POST /api/v1/events` (auth terminal, throttle)
→ validasi kartu → evaluasi jadwal/pergerakan → attendance_sessions +
movements + event outcome → dashboard. Tidak ada langkah manual.

## 2. Registrasi kartu massal — [S]
Petugas → Master > Registrasi > pilih kelas → sistem antre siswa tanpa
kartu aktif → scan UID → validasi (duplikat/inactive/sudah punya) →
assignment + auto-next → pause/resume/skip/cancel → **[S]** ringkasan
outcome saat finish. Kartu baru/lepas tetap via Master > Kartu.

## 3. Permohonan izin — [S]
Orang tua/wali → Requests > Buat → pilih anak+jenis+tanggal+alasan →
**[S]** "Buat & Ajukan" (langsung submitted) atau "Buat Draft" →
detail → **[S]** edit draft bila perlu → submit → pengampu
(BD-03 scope) decide → status final → apply ke attendance (BD-05:
hari efektif saja) → notifikasi outbox.

## 4. Alpa (Opsi B) — [S]
Sistem/Petugas/Admin → generate tanggal (default hari ini) →
`alpa_menunggu` → **[S]** daftar dikelompokkan filter kelas + ringkasan
→ Wali Kelas verify (scope kelas binaan) → Admin confirm → final +
notifikasi wali. Tidak pernah auto-final.

## 5. Jadwal & pulang cepat — [OK]
Pembuat draft versi → approve → resolver jadwal efektif per tanggal
(scope: sekolah/tingkat/kelas) → dipakai evaluasi tap. Pulang cepat =
perubahan jadwal resmi. Histori versi tidak ditimpa.

## 6. Kalender — [OK]
Read-only agregasi: jadwal, sesi kehadiran, permohonan approved,
keputusan jadwal, hari libur. Akses mengikuti scope role. Detail
mengarah ke sumber.

## 7. Review anomali — [OK]
Satu daftar `needs_review` → filter tanggal/kelas/terminal → detail
(kronologi + event mentah + manual_adjustments) → resolve wajib alasan
→ audit. Tidak ada auto-resolve.

## 8. Terminal & insiden — [S]
Admin pairing (kode 15 mnt sekali pakai → token) → Tauri app simpan
event lokal SQLite → sync 15 dtk + heartbeat 60 dtk → **[S]** badge
online/offline → insiden buka→resolve di `/master/incidents` → audit.

## 9. Dashboard & laporan — [S]
Dashboard per role (otomatis, angka tertaut ke sumber). Laporan filter
dari/sampai/kelas + **[S]** preset periode → rekap + **[S]** ekspor CSV
BOM (sudah ada). Rumus BD-12: (Hadir+Terlambat)/HariEfektif.

## 10. Notifikasi — [OK]
Trigger: keputusan permohonan, alpa confirmed, dst → outbox idempotent
→ retry → histori. Penerima = wali terverifikasi terkait. Tidak ada
notifikasi untuk event internal non-relevan.

## 11. Master data — [S]
Tahun ajaran/semester/tingkat/kelas CRUD → siswa (form dengan **[S]**
default tahun aktif + tanggal hari ini) → membership auto-tutup di
`syncMembership` → guardian link (admin via detail siswa / form
khusus) → user management + role.

## Matriks singkat

| Role | Alur utama | Halaman kunci |
|---|---|---|
| Admin | setup, master, RFID, terminal, jadwal, konfirmasi alpa, laporan | semua /master/* |
| Kepala/Wakil | ringkasan, approve sesuai scope, laporan | Dashboard, Requests, Schedules |
| Petugas Piket | monitoring, registrasi kartu, anomali, insiden | Review, Registrations, Incidents |
| Wali Kelas | kehadiran kelas, verify alpa, permohonan siswa | Dashboard, Alpa, Requests |
| Koordinator | kegiatan & dispensasi | Requests (scope), Calendar |
| Orang Tua | info anak, ajukan izin, status | Dashboard, Requests, Calendar |
| Siswa | riwayat & status sendiri | Dashboard, Calendar |
