# ROLE PERMISSION MATRIX

Otorisasi ditegakkan di **backend** (middleware `role:` + policies + controller
checks), bukan hanya UI. Matriks ini mencerminkan implementasi aktual.

Role: `admin`, `kepala` (kepala_sekolah), `wakil`, `petugas` (petugas_piket),
`koordinator`, `wali`, `ortu` (orang_tua), `siswa`.

Legenda: ✅ diizinkan · ❌ ditolak (403) · 🔶 scope terbatas.

## Master Data

| Modul | admin | kepala | wakil | petugas | koordinator | wali | ortu | siswa |
|---|---|---|---|---|---|---|---|---|
| Tahun ajaran / semester / tingkat / kelas (baca) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Siswa (daftar) | ✅ | ✅ | ✅ | ✅ | ✅ | 🔶 kelas binaan | 🔶 anak verified | ❌ (kosong) |
| Siswa (detail) | ✅ | ✅ | ✅ | ✅ | ✅ | 🔶 kelas binaan | 🔶 anak verified | ❌ |
| Guardlinks (baca) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Semua master data (tulis)** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

## Kartu & Registrasi

| Modul | admin | kepala | wakil | petugas | koordinator | wali | ortu | siswa |
|---|---|---|---|---|---|---|---|---|
| Kartu (baca) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Kartu register/assign/status/delete | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Registrasi massal (buat/scan/finish) | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Terminal kelola + token | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |

## Kehadiran & Jadwal

| Modul | admin | kepala | wakil | petugas | koordinator | wali | ortu | siswa |
|---|---|---|---|---|---|---|---|---|
| Review `perlu_konfirmasi` (baca) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Resolve `perlu_konfirmasi` | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Jadwal (baca) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Jadwal buat versi (draft) | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Jadwal approve/cancel | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Alpa index | ✅ | ✅ | ✅ | ✅ | ❌ | 🔶 kelas binaan | ❌ | ❌ |
| Alpa verify | ✅ | ❌ | ❌ | ✅ | ❌ | 🔶 kelas binaan | ❌ | ❌ |
| Alpa confirm → `alpa` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Alpa generate | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |

## Permohonan (requests)

| Modul | admin | kepala | wakil | petugas | koordinator | wali | ortu | siswa |
|---|---|---|---|---|---|---|---|---|
| Buat permohonan | ✅ semua siswa | ✅ | ✅ | ✅ | ✅ | 🔶 kelas binaan | 🔶 anak verified | ❌ |
| Lihat daftar | ✅ | ✅ | ✅ | ✅ | ✅ | 🔶 kelas binaan | 🔶 miliknya | ❌ |
| Submit / hapus draft | pengaju / staf | — | — | — | — | — | — | — |
| Decide (setujui/tolak) | ✅ | ✅ | ✅ | ❌ | ❌ | 🔶 kelas binaan | ❌ | ❌ |

Catatan: pengaju tidak boleh memutus permohonannya sendiri (semua role).

## Kalender

| Modul | admin | kepala | wakil | petugas | koordinator | wali | ortu | siswa |
|---|---|---|---|---|---|---|---|---|
| Kalender (3 view) | ✅ semua siswa | ✅ | ✅ | ✅ | ✅ | 🔶 kelas binaan | 🔶 anak verified | ❌ kosong |

## Pengguna

| Modul | admin | kepala | wakil | petugas | koordinator | wali | ortu | siswa |
|---|---|---|---|---|---|---|---|---|
| Daftar pengguna | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Buat/ubah/hapus user & role | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

## API Perangkat

| Endpoint | Auth |
|---|---|
| `POST /api/v1/device/events` | Bearer terminal token |
| `GET /api/v1/device/me` | Bearer terminal token |

## Prinsip yang ditegakkan backend

1. **Tulis master data = admin only**; baca = staf.
2. **Operasional lapangan** (kartu, registrasi, terminal, resolve, generate alpa)
   = admin + petugas piket.
3. **Persetujuan** (jadwal, permohonan, alpa final) = pimpinan (kepala/wakil)
   dan/atau admin; **bukan** petugas piket untuk keputusan final.
4. **Segregation of duties**: pembuat ≠ approver; pengaju ≠ pemutus.
5. **Scope**: wali kelas → kelas binaan; ortu → anak verified; siswa → belum
   ada tautan (kosong).
6. **Multi-role**: `hasRole` memeriksa `users.role` DAN `role_assignments`
   efektif — user bisa punya role tambahan ber-scope.
