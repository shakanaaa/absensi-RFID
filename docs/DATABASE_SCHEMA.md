# Database Schema — Sistem Absensi RFID

Semua tabel memakai `id` bigint auto-increment kecuali dinyatakan lain. UID disimpan sebagai string. Kolom `created_at`/`updated_at` standar kecuali `audit_logs` (hanya `created_at`).

## Struktur Sekolah

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `schools` | name, code (unique), timezone, settings(json) | settings: jam sekolah, cutoff, debounce, retensi |
| `academic_years` | school_id, name, start_date, end_date, status | status: draft/active/closed |
| `terms` | academic_year_id, name, sequence, start/end_date, status | semester |
| `grades` | school_id, name, level | unik (school_id, name) |
| `classes` | school_id, academic_year_id, grade_id, code, name, homeroom_teacher_id, status | unik (school_id, academic_year_id, code) |

## Siswa & Wali

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `students` | school_id, student_no, name, gender, birth_date, status | unik (school_id, student_no) |
| `student_class_memberships` | student_id, class_id, academic_year_id, from_date, to_date, attendance_number | histori kelas bertanggal |
| `guardian_links` | user_id, student_id, relation, is_primary, verified_at, valid_from/to | unik (user_id, student_id) |
| `users` (+kolom) | school_id, role, phone, status | role: admin/kepala_sekolah/wakil_kepala_sekolah/wali_kelas/piket/orang_tua/siswa/koordinator_kegiatan |
| `role_assignments` | user_id, role, scope_type, scope_id, effective_from/to | role bertanggal berscope |

## RFID & Perangkat

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `reader_profiles` | name, device_model, pattern, uid_length, suffix, version, tested_at, is_active | profil format UID teruji |
| `rfid_cards` | uid_text (unique), card_type, status, issued_at | status: active/suspended/lost/damaged/replaced/revoked/archived |
| `student_card_assignments` | rfid_card_id, student_id, assigned_by, assigned_at, unassigned_at, status, active_flag, reason | unik (card_id, active_flag) & (student_id, active_flag); active_flag='ACTIVE' saat aktif, NULL selainnya |
| `terminals` | school_id, terminal_code, type(in/out/registration/spare), location, reader_profile_id, status | unik (school_id, terminal_code) |
| `terminal_tokens` | terminal_id, token_hash (unique), label, last_used_at, revoked_at | token disimpan hashed |

## Absensi & Pergerakan

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `raw_rfid_events` | event_id char(36) unique, terminal_id, student_id?, uid_text, event_type, event_time, timezone_offset, received_at, sequence_number, sync_status, time_quality, evaluation_status, payload(json) | APPEND-ONLY; index (terminal_id, sequence_number), event_time, uid_text |
| `event_evaluations` | raw_event_id, evaluated_at, outcome, reason_code, rule_version, reviewed_by, payload | evaluasi bisa berulang (rule_version) |
| `attendance_sessions` | student_id, school_id, school_date, schedule_key, schedule_version_id?, attendance_status, movement_state, close_state, close_snapshot, closed_at, closed_by | unik (student_id, school_date, schedule_key) |
| `movement_transactions` | attendance_session_id, student_id, type, occurred_at, raw_event_id?, event_evaluation_id?, outcome, details(json) | type: in/out/return/temp_exit/dll |

## Jadwal & Pulang Cepat

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `academic_schedules` | school_id, name, scope_type(school/grade/class/student), scope_id | induk jadwal |
| `schedule_versions` | academic_schedule_id, school_id, school_date, start/end_time, type, version_no, status, reason, created_by, approved_by, approved_at, effective_from, cancelled_at | immutable setelah aktif; status: draft/pending_approval/active/superseded/cancelled/emergency_active/ratified/not_ratified/escalated |
| `early_dismissal_approvals` | schedule_version_id, approver_id, decision, reason, decided_at | approval pengesahan darurat |

## Permohonan & Pendaftaran Massal

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `requests` | school_id, student_id, requester_user_id, type, status, period_start/end, start/end_time, reason, attachment_path, submitted_at | type: izin/sakit/pulang_awal/keluar_sementara/dispensasi/sanggahan; status: draft/submitted/waiting_review/needs_revision/approved/rejected/cancelled/expired |
| `request_decisions` | request_id, decided_by, decision, reason, decided_at | append-only |
| `registration_sessions` | school_id, class_id, operator_user_id, reader_profile_id, status, snapshot(json), started_at, finished_at | status: running/paused/completed/cancelled |
| `registration_scan_attempts` | registration_session_id, uid_text?, student_id?, outcome, detail(json), attempted_at | outcome: accepted/invalid_format/duplicate/uid_already_used/dll |

## Kegiatan, Insiden, Penyesuaian

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `school_activities` | school_id, name, activity_date, start/end_time, location, coordinator_user_id, status | dispensasi kegiatan resmi |
| `activity_participants` | school_activity_id, student_id, confirmed, confirmed_at | unik (activity, student) |
| `terminal_incidents` | terminal_id, school_id, category, severity, description, started_at, ended_at, opened_by, status | insiden perangkat |
| `manual_adjustments` | school_id, target_type, target_id, requested_by, approved_by?, reason, source, effective_time, before_ref, after_ref | koreksi; tidak mengubah raw event |

## Notifikasi & Audit

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `notification_outbox` | school_id, idempotency_key (unique), trigger_event, recipient_user_id?, recipient_contact?, channel, template_key, payload(json), status, scheduled_at, sent_at, provider_message_id, attempts, last_error | status: queued/sending/sent_to_provider/delivered/read/failed_retryable/failed_final/cancelled |
| `audit_logs` | school_id?, actor_user_id?, actor_type, action, entity_type, entity_id, before_ref, after_ref, reason, request_id, source, created_at | APPEND-ONLY (tanpa updated_at) |

## Constraint Penting (PRD §17.1)

- `UNIQUE(raw_rfid_events.event_id)` — idempotency ingest.
- Satu assignment aktif per kartu dan per siswa — via `active_flag`.
- `UNIQUE(student_id, school_date, schedule_key)` — satu sesi per konteks.
- `UNIQUE(school_id, terminal_code)` — identitas terminal.
- `UNIQUE(idempotency_key)` — notifikasi.
- UID disimpan verbatim; masking hanya di layer presentasi.
