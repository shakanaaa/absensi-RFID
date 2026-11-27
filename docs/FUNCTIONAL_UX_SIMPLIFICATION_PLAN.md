# FUNCTIONAL UX SIMPLIFICATION PLAN

Setiap item: alur lama → alur baru, langkah hilang/otomatis, rule yang
dilindungi, risiko, acceptance criteria. Implementasi dibatasi pada item
berisiko rendah; item yang butuh keputusan bisnis tidak diimplementasi.

## P1 — Permohonan: "Buat & Ajukan" satu langkah (MERGE WORKFLOW)

- **Lama**: isi form → simpan draft → buka detail → klik Ajukan (3 layar).
- **Baru**: dua tombol di form — "Buat Draft" (lama) dan "Buat & Ajukan"
  (langsung `status=submitted`, `submitted_at` terisi, audit
  `request.submitted`).
- **Dihilangkan**: 1 layar + 1 klik untuk kasus umum.
- **Otomatis**: penetapan status & timestamp submit.
- **Tetap wajib**: validasi sama, audit `request.created` + `submitted`,
  notifikasi keputusan tetap setelah approve/reject.
- **Risiko**: rendah — jalur draft tidak dihapus.
- **AC**: POST requests dengan flag `submit=1` menghasilkan permohonan
  submitted + submitted_at + audit; tanpa flag tetap draft.

## P2 — Edit draft permohonan (SIMPLIFY)

- **Lama**: tidak bisa edit; harus hapus draft lalu input ulang.
- **Baru**: `PUT /requests/{id}` hanya untuk `status=draft` dan pemohon;
  halaman edit memakai Form.vue yang sama (prefill).
- **Dihilangkan**: input ulang seluruh field.
- **Tetap wajib**: policy `update` = draft + requester; audit
  `request.updated` dengan diff sebelum/sesudah.
- **Risiko**: rendah.
- **AC**: draft dapat diedit; non-draft → 403; non-pemohon → 403.

## P3 — Index permohonan: filter jenis + cari siswa (SIMPLIFY)

- **Lama**: filter status saja.
- **Baru**: tambah filter `type` dan `q` (nama / student_no, like).
- **Dihilangkan**: scroll daftar panjang untuk cari satu siswa.
- **Risiko**: rendah.
- **AC**: filter bekerja; scope sekolah tetap terjaga.

## P4 — Smart defaults form siswa (AUTOMATE)

- **Lama**: user memilih tahun ajaran & from_date manual.
- **Baru**: saat create, default `academic_year_id` = tahun ajaran aktif,
  `from_date` = hari ini; `class_id` yang dipilih sudah membawa tahun
  ajaran (opsi kelas tetap menampilkan tahun untuk kejelasan).
- **Dihilangkan**: 2 input manual per siswa baru.
- **Tetap wajib**: `class_id` tetap wajib; validasi overlap membership
  tidak berubah.
- **Risiko**: rendah — user tetap bisa mengubah sebelum simpan.
- **AC**: form create menampilkan default; store tetap memvalidasi.

## P5 — Alpa: default tanggal + filter kelas + ringkasan (SIMPLIFY)

- **Lama**: filter tanggal kosong (semua tanggal), tanpa ringkasan,
  tanpa filter kelas; form generate minta tanggal.
- **Baru**: index default `date` = hari ini; tambah `class_id` filter;
  ringkasan header (total / terverifikasi / belum); form generate
  default tanggal hari ini.
- **Dihilangkan**: memilih tanggal manual tiap kunjungan; menebak
  mana yang sudah diverifikasi.
- **Tetap wajib**: alur verify→confirm tidak berubah; scope wali kelas
  tetap dibatasi kelas binaan.
- **Risiko**: rendah-sedang (perlu eager-load kelas siswa untuk filter —
  sudah ada relasi memberships).
- **AC**: default hari ini; filter kelas mempersempit hasil; ringkasan
  akurat terhadap filter aktif.

## P6 — Preset periode laporan (SIMPLIFY)

- **Lama**: isi dari/sampai manual (default bulan berjalan sudah ada).
- **Baru**: tombol preset Hari ini / 7 hari / Bulan ini yang mengisi
  filter — hanya frontend, query param sama.
- **Risiko**: sangat rendah.
- **AC**: preset mengisi date filter dengan benar (dihitung client-side).

## P7 — Badge status terminal online/offline (AUTOMATE)

- **Lama**: user menafsirkan `last_seen_at` manual.
- **Baru**: badge "Online" jika last_seen < 5 menit (ambang sinkron
  heartbeat 60 dtk + toleransi), "Offline" lainnya — dihitung di
  controller sebagai boolean per terminal (bukan ubah tampilan besar;
  hanya label status di kolom yang sudah ada).
- **Risiko**: rendah.
- **AC**: terminal dengan heartbeat segar = online; tanpa heartbeat =
  offline/unknown.

## P8 — Ringkasan hasil sesi registrasi (SIMPLIFY)

- **Lama**: setelah finish hanya angka progress.
- **Baru**: `finish` response + flash menampilkan breakdown outcome
  (assigned/already_owned/skipped/failed) dari scan_attempts sesi tsb.
- **Risiko**: rendah (query agregat append-only table).
- **AC**: ringkasan cocok dengan scan_attempts; tidak mengubah outcome.

## Tidak diimplementasi (butuh keputusan bisnis / di luar aman)

- Notifikasi tambahan (kebijakan penerima belum final — BD).
- Bulk verify alpa (perubahan semantik approval).
- Auto-generate alpa harian via scheduler (perlu keputusan BD).
- Perubahan scope role mana pun.
