# BUSINESS RULES IMPLEMENTATION MATRIX

Pemetaan aturan bisnis → implementasi. Status:
**Not Implemented / Partially Implemented / Implemented / Tested / Needs Decision**.

| Rule ID | Deskripsi | Database | Backend | Frontend | Test | Status | Catatan |
|---|---|---|---|---|---|---|---|
| AUTH-001 | RBAC 8 role, backend enforcement | users.role, role_assignments | EnsureRole, policies | nav role-aware | MasterDataTest, ScheduleTest | Tested | — |
| AUTH-002 | SoD: creator≠approver, requester≠decider | — | ScheduleController, RequestPolicy | tombol disembunyikan | ScheduleTest, RequestAndAlpaTest | Tested | — |
| AUTH-003 | Registrasi publik = orang_tua | users.role | RegisteredUserController | — | (belum ada test khusus) | Implemented | ADR-008 |
| AUTH-004 | Scope wali kelas | classes.homoroom_teacher_id, role_assignments | User::waliKelasClassIds, policies | — | RequestAndAlpaTest, CalendarTest | Tested | — |
| AUTH-005 | Scope ortu verified | guardian_links.verified_at | RequestPolicy, CalendarController | — | RequestAndAlpaTest, CalendarTest | Tested | — |
| RFID-001 | 1 kartu aktif/siswa, 1 siswa/kartu | student_card_assignments.active_flag | CardService::assign | Cards/Index | CardManagementTest | Tested | — |
| RFID-002 | Lifecycle status kartu | rfid_cards.status | CardService | Cards/Index | CardManagementTest | Tested | — |
| RFID-003 | Unassign simpan riwayat | assignments | CardService::unassign | Cards/Index | CardManagementTest | Tested | — |
| RFID-004 | Registrasi massal + outcomes | registration_sessions, scan_attempts | RegistrationSessionController, CardService::processScan | Registrations/* | CardManagementTest | Tested | — |
| RFID-006 | Kartu non-aktif → inactive_card | rfid_cards.status | AttendanceEvaluator::resolveStudent | — | AttendanceEdgeCaseTest | Tested | — |
| RFID-005 | Pause/resume/skip/auto-next | — | belum ada | — | — | Needs Decision | BD-08 |
| ATT-001 | Sesi per siswa+tanggal+schedule_key | attendance_sessions unique | AttendanceEvaluator | — | DeviceApiTest | Tested | — |
| ATT-002 | Status absen & derivasi | attendance_sessions | AttendanceEvaluator, applyToAttendance | Review, Alpa, Calendar | multiple | Tested | — |
| ATT-003 | Status berjalan tidak final; no OUT fiktif | — | evaluator tidak menulis waktu rekaan | — | implisit | Implemented | — |
| ATT-004 | Libur ≠ alpa; generator skip libur | schedule_versions, academic_years | SchoolDayService + AlpaService | — | RequestAndAlpaTest | Tested | BD-05 confirmed |
| ATT-005 | Perubahan jadwal tak ubah historis | schedule_versions berversi | evaluator baca approved terbaru | Schedules/Index | ScheduleTest | Tested | event lama tak dievaluasi ulang |
| ATT-006 | Clock skew flag | raw_rfid_events.time_quality | DeviceEventController | — | AttendanceEdgeCaseTest | Tested | — |
| MOV-001 | State machine movement | movement_transactions | AttendanceEvaluator::transition | Review | DeviceApiTest, EdgeCase | Tested | v1.1 |
| MOV-002 | Debounce 5s | — | evaluator | — | AttendanceEdgeCaseTest | Tested | — |
| MOV-003 | Status berizin dilindungi | — | PROTECTED_STATUSES | — | AttendanceEdgeCaseTest | Tested | — |
| MOV-004 | Review perlu_konfirmasi | manual_adjustments | AttendanceReviewController | Attendance/Review | AttendanceEdgeCaseTest | Tested | — |
| MOV-005 | Terminal type menentukan arah | terminals.type | evaluator | — | implisit | Implemented | BD-09 open |
| LEAVE-001 | Jenis permohonan | requests.type | validation enum | Requests/Form | RequestAndAlpaTest | Tested | — |
| LEAVE-002 | Pengaju & scope | requests | RequestPolicy::createFor | Requests/* | RequestAndAlpaTest | Tested | — |
| LEAVE-003 | Pemutus & SoD | request_decisions | RequestPolicy::decide | Requests/Show | RequestAndAlpaTest | Tested | BD-01 open |
| LEAVE-004 | Terapkan status ke sesi | attendance_sessions | applyToAttendance + SchoolDayService | — | RequestAndAlpaTest | Tested | skip hari non-efektif |
| LEAVE-005 | Lampiran bukti | requests.attachment_path | kolom ada, upload belum | — | — | Needs Decision | BD-06 |
| LEAVE-006 | Batas waktu/darurat | — | belum ada | — | — | Needs Decision | BD-02 |
| LEAVE-007 | Sanggahan ortu | — | belum ada | — | — | Needs Decision | BD-07 |
| EARLY-001 | pulang_awal + approval record | early_dismissal_approvals | ScheduleController::approve | Schedules/Index | ScheduleTest | Tested | — |
| EARLY-002 | Cakupan sekolah/tingkat/kelas | academic_schedules.scope_* | hanya 'school' | — | — | Needs Decision | BD-10 |
| EARLY-003 | Alur draft→approve, supersede | schedule_versions | ScheduleController | Schedules/Index | ScheduleTest | Tested | — |
| EARLY-004 | Pengumuman perubahan jadwal | notification_outbox | trigger belum ada | — | — | Needs Decision | BD-03 |
| CAL-001 | Kalender 3 view | — | CalendarController | Calendar/Index | CalendarTest | Tested | — |
| CAL-002 | Prioritas jadwal konsisten | schedule_versions | scheduleFor (version_no desc) | — | ScheduleTest | Tested | — |
| CAL-003 | Izin multi-hari hari efektif | — | applyToAttendance + SchoolDayService | — | RequestAndAlpaTest | Tested | BD-05 confirmed |
| NOTIF-001 | Outbox idempotent + retry | notification_outbox | NotificationService, notifications:send | — | NotificationTest | Tested | — |
| NOTIF-002 | Trigger request & alpa | notification_outbox | controllers | — | NotificationTest | Tested | — |
| NOTIF-003 | Cakupan trigger PRD (~9) | — | baru 2 trigger | — | — | Needs Decision | BD-03 |
| NOTIF-004 | WA provider | — | MockWhatsAppProvider | — | NotificationTest | Implemented | mock by design |
| AUDIT-001 | Audit log append-only | audit_logs | AuditLogger | — | dicek di banyak test | Tested | — |
| AUDIT-002 | Koreksi + manual_adjustments | manual_adjustments | AttendanceReviewController | Attendance/Review | AttendanceEdgeCaseTest | Partially Implemented | approval 2 tahap belum — BD-11 |
| AUDIT-003 | Event mentah abadi + idempotent | raw_rfid_events, event_evaluations | DeviceEventController | — | DeviceApiTest | Tested | — |
| TERM-001 | Auth terminal Bearer | terminal_tokens | AuthenticateTerminal | Master/Terminals | DeviceApiTest | Tested | — |
| TERM-002 | Ingest idempotent batch | raw_rfid_events.event_id unique | DeviceEventController | — | DeviceApiTest | Tested | — |
| TERM-003 | Offline/terlambat/skew | time_quality, sync_status | evaluator + ingest | — | AttendanceEdgeCaseTest | Partially Implemented | sync_status belum dipakai alur offline |
| TERM-004 | Insiden terminal | terminal_incidents | tabel ada, tanpa UI | — | — | Not Implemented | — |
| REPORT-001 | Dashboard & rekap | attendance_sessions | DashboardController, ReportController | Dashboard, Reports/Index | ReportTest | Partially Implemented | ekspor belum ada |
| REPORT-002 | Definisi % kehadiran | — | ReportController (BD-12) | Reports/Index | ReportTest | Tested | formula confirmed |

## Update 26 November 2026 (penyelarasan keputusan)

| Rule ID | Deskripsi | Status | Catatan |
|---|---|---|---|
| MOV-006 | Keluar sebelum jam pulang: berizin, tanpa izin ditandai, keluar sementara hanya dengan izin | Implemented (tes belum dijalankan) | `AttendanceEvaluator::earlyExit`, `MovementDecisionTest` |
| MOV-007 | Tap masuk setelah jam pulang berlaku menjadi perlu_konfirmasi | Implemented (tes belum dijalankan) | — |
| ATT-007 | Tap belakangan menggantikan belum ada keterangan; izin tidak menimpa tap | Implemented (tes belum dijalankan) | — |
| ATT-008 | Cutoff harian dan rekap staf | Implemented (tes belum dijalankan) | `attendance:cutoff`, `DailyRecap` |
| LEAVE-008 | Izin pulang awal dan keluar sementara | Implemented (tes belum dijalankan) | jam wajib |
| RFID-007 | Pembacaan ulang diabaikan dan batalkan pendaftaran terakhir | Implemented (tes belum dijalankan) | — |
| EARLY-002 | Cakupan kelas dan tingkat, konflik eksplisit | Not Implemented | BD-10 |

## Update 26 November 2026

Fitur gap-phase yang ditambahkan (bukan aturan bisnis baru, melengkapi
implementasi aturan existing):

- **Registrasi massal** (RFID-005/dst): pause/resume/skip/cancel + auto-next
  + progress — `RegistrationSessionController`, `Registrations/Show.vue`.
- **Device API** (TERM-004): pairing code → token, `/config`, `/heartbeat`,
  rate limit — `DeviceApiController`, `DevicePairingCode`, middleware `terminal`.
- **Review anomali** (AUDIT/I): filter kelas+terminal, halaman detail
  pergerakan+event mentah+koreksi — `AttendanceReviewController@show`,
  `Attendance/ReviewShow.vue`.
- **Dashboard/laporan** (REPORT-*): roleData per role + ekspor CSV
  (`ReportController@export`, formula BD-12 identik dengan index).
- **Insiden terminal** (TERM-*): `TerminalIncidentController` + policy +
  UI `/master/incidents`.
- **Terminal desktop**: scaffold `terminal/` (Tauri 2) — lihat
  `docs/TERMINAL_SETUP_GUIDE.md`.
