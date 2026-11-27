# Desain: Simplifikasi Sistem Absensi RFID

Tanggal: 2026-11-26
Status: Draft — menunggu persetujuan

## Intent dan alasan

Aplikasi saat ini terlalu kompleks untuk kebutuhan sekolah (±30+ halaman, 8 role, jadwal per sesi, alur izin/dispensasi/alpa, insiden terminal, notifikasi otomatis). User meminta versi sederhana: hanya admin & guru piket, absensi murni dari tap RFID, laporan harian ke WhatsApp via link wa.me, kenaikan kelas sederhana, dan pengaturan kalender/jam.

## Keputusan user (confirmed)

- Absensi tetap via tap kartu RFID (bukan input manual).
- Laporan harian dikirim via link `wa.me` (bukan API provider).
- Modul dihapus **total**: routes, controller, halaman, service, dan tabel.
- Database **reset bersih** — data lama dibuang, seeder baru dibuat.

## Roles akhir

Hanya dua role:

| Role | Hak |
|---|---|
| `admin` | Semua: master data (siswa, kelas, grade, kartu, tahun ajaran, user), pengaturan, laporan, kenaikan kelas |
| `petugas_piket` | Melihat absensi hari ini, laporan harian, status kartu; tidak bisa ubah master data/pengaturan |

Dihapus: `kepala_sekolah`, `wakil_kepala_sekolah`, `wali_kelas`, `orang_tua`, `siswa`, `koordinator_kegiatan`, beserta tabel `role_assignments`, `guardian_links`, dan semua halaman terkait (guardians, users non-admin).

## Modul yang dihapus

- **Jadwal**: `academic_schedules`, `schedule_versions`, `school_activities`, `terms`; halaman Schedules/Terms; `SchoolDayService` ditulis ulang tanpa dependensi jadwal.
- **Insiden terminal**: `terminal_incidents` + halaman/controller.
- **Izin/dispensasi/alpa**: `requests`, `request_decisions`, `early_dismissal_approvals`, `manual_adjustments`; halaman Requests, Alpa, Attendance Review.
- **Notifikasi otomatis**: `notification_outbox`, `NotificationService`, outbox worker — diganti tombol wa.me.
- **Evaluasi/movement kompleks**: `event_evaluations`, `movement_transactions` — event mentah tetap disimpan di `raw_rfid_events` sebagai audit, tapi tidak ada workflow review.
- **Kalender absensi 3-view** diganti halaman Pengaturan + kalender libur sederhana.

## Yang dipertahankan

- `schools`, `users` (disederhanakan), `academic_years`, `grades`, `school_classes`, `students`, `student_class_memberships`.
- `rfid_cards`, `student_card_assignments`, `terminals`, `terminal_tokens`, `device_pairing_codes`, `reader_profiles`, `registration_sessions`, `registration_scan_attempts` (registrasi kartu massal tetap, karena dibutuhkan untuk assign kartu).
- `raw_rfid_events` — tetap menyimpan semua tap sebagai catatan mentah.

## Model absensi baru

Tabel `attendance_sessions` disederhanakan menjadi satu baris per siswa per tanggal:

```
student_id, school_id, school_date,
check_in_at (nullable), attendance_status,
check_out_at (nullable), notes, timestamps
UNIQUE(student_id, school_date)
```

`attendance_status` hanya: `hadir`, `terlambat`, `tidak_hadir`.

Aturan evaluasi (disederhanakan dari AttendanceEvaluator):

- Tap IN pada hari efektif → `check_in_at` terisi; `hadir` jika `check_in_at <= jam_masuk`, `terlambat` jika lewat.
- Tap OUT → `check_out_at` terisi (jam pulang aktual). Tap OUT tanpa IN di hari yang sama dicatat di raw event tapi tidak mengubah status.
- Tidak ada tap IN sampai akhir hari efektif → `tidak_hadir` (ditentukan lazy: status dihitung "belum_masuk" selama hari berjalan, menjadi `tidak_hadir` setelah jam_pulang lewat / saat laporan dibuka keesokan hari).
- Hari libur/non-efektif → tidak ada sesi; tidak menghasilkan `tidak_hadir`.
- Hari efektif = tanggal dalam rentang tahun ajaran aktif, bukan Sabtu/Minggu, dan tidak ada di `school_holidays`.

Kolom `schedule_key`, `schedule_version_id`, `movement_state`, `close_state`, `close_snapshot`, `closed_at`, `closed_by`, dan kolom verifikasi alpa dihapus.

## Pengaturan (halaman baru `/settings`)

Tabel `school_settings` (1 baris per sekolah):

- `check_in_time` (jam masuk, default 07:00) — batas status `terlambat`.
- `check_out_time` (jam pulang, default 15:00) — acuan "pulang awal" di laporan (informational).
- `whatsapp_number` — nomor tujuan laporan (format internasional tanpa +, mis. 62812xxx).
- `report_send_time` — opsional, jam target kirim laporan (default 15:30).

## Kalender & libur nasional

Tabel `school_holidays` (school_id, date, name). Halaman Pengaturan punya:

- Daftar libur (tambah/hapus manual).
- Tombol **"Generate Libur Nasional"**: memilih tahun → mengisi daftar libur nasional Indonesia untuk tahun itu (tanggal tetap + libur keagamaan yang bisa diprediksi; tanggal yang bergeser seperti Idul Fitri ditandai "perkiraan" atau pakai tanggal resmi terbaru yang tersedia). Daftar dikelola sebagai konstanta per tahun di kode, diperbarui manual bila diperlukan.

## Laporan harian + WhatsApp

Halaman `/reports`:

- Filter tanggal + kelas.
- Rekap: total hadir / terlambat / tidak hadir + daftar siswa dengan jam masuk/pulang.
- Tombol **"Kirim ke WhatsApp"** → `https://wa.me/<whatsapp_number>?text=<urlencoded laporan>` — laporan diformat teks sederhana (nama sekolah, tanggal, per kelas: H/T/Tidak, daftar nama terlambat & tidak hadir).
- Jika `whatsapp_number` kosong → tombol disabled dengan pesan pengaturan.

## Kenaikan kelas

Halaman `/promotion` (admin only):

- Daftar kelas aktif dikelompokkan per grade.
- Aksi **"Naik Kelas Semua"**: untuk tiap siswa aktif, pindahkan ke kelas berikutnya (grade berikutnya, posisi kelas yang sama — X-1 → XI-1). Siswa kelas tertinggi (XII) → status `lulus`, keluar dari roster aktif.
- Aksi per kelas: pilih kelas sumber & kelas tujuan → pindahkan anggotanya.
- Membutuhkan tahun ajaran baru: wizard sederhana — buat tahun ajaran baru (copy struktur grade) lalu promosikan. Implementasi: halaman menampilkan tahun ajaran aktif; promosi membuat membership baru di tahun ajaran target.

Sederhana = tanpa alur draft/approval; konfirmasi dialog saja.

## Halaman akhir (frontend)

- `Dashboard` — ringkasan hari ini: total tap, hadir/terlambat, yang belum tap.
- `Attendance/Index` — absensi harian: pilih tanggal, filter kelas, tabel siswa × status × jam masuk × jam pulang; feed tap terakhir.
- `Reports/Index` — laporan + tombol wa.me.
- `Promotion/Index` — kenaikan kelas.
- `Settings/Index` — jam, nomor WA, kalender libur + generate.
- `Master/*` — Students, Classes, Grades, Cards (+registrasi), AcademicYears, Users (admin).
- Dihapus: Requests, Alpa, Attendance/Review, Calendar, Incidents, Schedules, Terms, Guardians.

## Pendekatan implementasi

**A. Rewrite migrasi (dipilih).** Karena DB di-reset, file migrasi lama diganti set migrasi bersih (`0001` core, `0002` rfid/registrasi, `0003` absensi, `0004` settings/libur). `migrate:fresh` + seeder baru. Repo tetap bersih untuk jangka panjang.

B. Migrasi inkremental drop tabel — lebih aman secara git history, tapi menyisakan migrasi "tambal" yang membingungkan. Ditolak.

## Batasan & non-goal

- Tidak ada input manual absensi, tidak ada koreksi kehadiran, tidak ada izin jenis apapun.
- Tidak ada notifikasi otomatis — hanya link wa.me.
- Tidak ada multi-sekolah kompleks (tabel schools tetap, tapi UI mengasumsikan 1 sekolah).
- Event RFID mentah tidak pernah dihapus.
- Jam perangkat terminal tetap dipercaya apa adanya (sinkronisasi offline tetap via endpoint device yang ada, disederhanakan: tanpa evaluation queue).

## Test scenario minimal

- Tap IN sebelum/sesudah jam masuk → hadir/terlambat.
- Tap IN di hari libur → tidak ada sesi.
- Siswa tanpa tap sampai lewat jam pulang → tidak_hadir di laporan.
- Tap OUT mengisi check_out_at; OUT tanpa IN tidak mengubah status.
- UID kartu tidak dikenal/nonaktif → event tersimpan, tidak ada sesi.
- Generate libur nasional mengisi tanggal benar; hari efektif mengecualikan Sabtu/Minggu/libur.
- Laporan wa.me berisi teks ter-encode benar; tombol disabled bila nomor kosong.
- Kenaikan kelas: X→XI, XII→lulus; membership lama nonaktif.
- petugas_piket tidak bisa akses master data/settings/promotion (403).

## Yang masih open (minor, keputusan saya sebagai builder)

- Toleransi keterlambatan: tidak ada — tepat jam masuk = hadir, lewat 1 menit = terlambat.
- Status "pulang awal": tidak dijadikan status; hanya kolom `check_out_at` + ditandai di laporan bila < jam_pulang.
- Nama file migrasi baru mengikuti timestamp baru.
