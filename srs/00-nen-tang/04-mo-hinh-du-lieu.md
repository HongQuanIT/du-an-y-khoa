# 04 — Mô hình dữ liệu (Data Model)

> Danh mục Entity dùng chung cho toàn hệ thống. Mỗi module tham chiếu về đây thay vì định nghĩa lại. Quy ước chung áp dụng cho **mọi bảng** ở mục 1.

## 1. Quy ước chung cho mọi bảng

- **Khóa chính**: `id` — `BIGINT UNSIGNED AUTO_INCREMENT` (nội bộ); thêm `uuid`/`public_id` (`CHAR(26)` ULID) cho tài nguyên lộ ra ngoài/URL.
- **Timestamp**: `created_at`, `updated_at` (`TIMESTAMP`).
- **Soft delete**: `deleted_at NULLABLE` cho hầu hết bảng nghiệp vụ (câu hỏi, bài viết, user, media, note...). Bảng log/tracking **không** soft delete (chỉ insert).
- **Audit tối thiểu**: `created_by`, `updated_by` (FK `users.id`, nullable) cho bảng nội dung.
- **Charset**: `utf8mb4`, collation `utf8mb4_unicode_ci`.
- **Enum**: lưu dạng `VARCHAR` + check ở tầng app (hoặc `ENUM` khi ổn định) — liệt kê giá trị ngay dưới field.
- **Index**: khai báo rõ ở từng entity; FK có index; cột lọc/sort thường xuyên có index; tránh over-index bảng ghi lớn.
- **Tiền tệ**: lưu số nguyên nhỏ nhất (vd `amount_cents INT`) + `currency CHAR(3)`.

## 2. Nhóm Identity & Access

### User
| Field | Type | Ghi chú |
|-------|------|---------|
| id | BIGINT PK | |
| uuid | CHAR(26) | public id |
| name | VARCHAR(150) | |
| email | VARCHAR(190) UNIQUE | |
| email_verified_at | TIMESTAMP null | |
| password | VARCHAR(255) null | null nếu chỉ OAuth |
| role | VARCHAR(30) | enum: guest*/student/instructor/content_editor/org_admin/admin/super_admin |
| status | VARCHAR(20) | enum: active/pending/suspended/banned |
| avatar_media_id | FK Media null | |
| locale | VARCHAR(5) | vi/en |
| timezone | VARCHAR(40) | |
| organization_id | FK Organization null | 🔵 Phase 2 (Organization hoãn) — để null, chưa dùng |
| last_login_at | TIMESTAMP null | |
| streak_count | INT default 0 | |
| exam_target_date | DATE null | dùng cho Study Plan |
| meta | JSON | onboarding flags, prefs |
| timestamps, soft delete | | |

Index: `email`, `role`, `organization_id`, `status`.
Quan hệ: hasMany Attempt/Session/Note/Bookmark/Highlight/Flashcard; hasOne Subscription; hasMany OAuthAccount, Device; hasMany Classroom (as host) / ClassroomMember (Module 44). *(🔵 Phase 2: belongsTo Organization.)*

### OAuthAccount
`id, user_id FK, provider(google/facebook/apple), provider_user_id, access_token(enc), refresh_token(enc), expires_at, timestamps`. Unique `(provider, provider_user_id)`.

### Device / Session (auth)
`id, user_id FK, device_name, ip, user_agent, last_active_at, revoked_at, token_hash, timestamps`.

### Role / Permission / RoleUser / PermissionRole
Chuẩn RBAC: `roles(id,name,slug)`, `permissions(id,name,slug)`, `permission_role`, `role_user` (nếu multi-role). Xem `03-phan-quyen-rbac.md`.

### 🔵 Organization *(Phase 2 — hoãn, chưa tạo bảng ở giai đoạn hiện tại)*
`id, uuid, name, type(university/hospital/company), seats_total, seats_used, billing_email, status, meta JSON, timestamps, soft delete`.

### 🔵 OrganizationMember *(Phase 2 — hoãn)*
`id, organization_id FK, user_id FK, org_role(member/instructor/org_admin), invited_by, joined_at, status(invited/active/removed), timestamps`. Unique `(organization_id, user_id)`.

> 🔵 **Ghi chú:** Nhóm bảng tổ chức (`organizations`, `organization_members`, và `classes`, `assignments`, `assignment_submissions` ở Module 32) **chưa đưa vào build hiện tại**. Cột `users.organization_id` để **nullable** và tạm không dùng cho tới Phase 2.
>
> **Classroom cộng đồng (Module 44)** dùng bảng `classrooms` / `classroom_members` / `live_sessions`… — **không** đụng tên `classes` của Organization. Xem mục 8 bên dưới.

## 3. Nhóm Nội dung câu hỏi

### Question
| Field | Type | Ghi chú |
|-------|------|---------|
| id, uuid | | |
| stem | LONGTEXT | vignette/đề bài (HTML/Markdown an toàn) |
| lead_in | TEXT | câu hỏi dẫn ("Chẩn đoán phù hợp nhất?") |
| type | VARCHAR | single_best/multi/matching |
| difficulty | VARCHAR | easy/medium/hard (hoặc 1–5) |
| status | VARCHAR | draft / in_review / pending_publish / published / rejected / private / retired |
| exam_flag | BOOL default false | `true` = câu dành cho exam pool (kèm `private`) |
| is_free | BOOL | dùng cho preview free tier |
| explanation | LONGTEXT | giải thích tổng |
| references | JSON | nguồn (guideline, sách) |
| lab_values | JSON | chỉ số tham chiếu kèm câu |
| media_ids | JSON | ảnh/video đính kèm |
| version | INT | version đã publish gần nhất (0 nếu chưa từng); chi tiết ở `question_versions` |
| published_version | INT null | version đang phục vụ Qbank (snapshot); null = chưa live |
| instructor_id | FK null | giảng viên duyệt/từ chối lớp 1 gần nhất |
| publisher_id | FK null | Super Admin publish gần nhất (lớp 2) |
| rejection_reason | TEXT null | khi status = rejected |
| rejected_by_role | VARCHAR null | `instructor` \| `super_admin` |
| stats_cache | JSON | attempts, correct_rate, reports… — **rollup job**; list admin chỉ đọc field này |
| stats_updated_at | TIMESTAMP null | lần rollup gần nhất |
| cloned_from_id | FK null | câu nguồn khi clone |
| cloned_from_version | INT null | version snapshot nguồn (optional) |
| created_by, updated_by | | creator_id / editor gần nhất |
| timestamps, soft delete | | |

Index: `status`, `exam_flag`, `(status, exam_flag, created_at)`, `difficulty`, `is_free`. Full-text → Meilisearch (chỉ `published`).

### QuestionVersion
`id, question_id FK, version_number INT, instructor_id FK, publisher_id FK, snapshot JSON, created_at`. Unique `(question_id, version_number)`. **Chỉ tạo khi Super Admin publish** (`pending_publish` → `published`) — không tạo khi Creator sửa working copy hay khi giảng viên approve/reject. Xem Module 35 §5.2–5.3.

### QuestionOption
`id, question_id FK, label(A/B/...), content TEXT, is_correct BOOL, explanation TEXT (vì sao đúng/sai), order INT, timestamps`.

### Topic (chuyên ngành/chủ đề — **phân cấp cha–con**)
`id, parent_id FK null, name, slug, type(specialty/system/subtopic), order, icon, depth INT null, timestamps`.
- Cây không giới hạn độ sâu (khuyến nghị 2–3 cấp: chuyên ngành → hệ → subtopic).
- Index: `parent_id`, `(parent_id, order)`.
- Filter Qbank/exam: chọn topic **cha** → bao gồm mọi **topic con** (descendants).
- Ví dụ: Nội → Tiêu hóa → Viêm gan.

### QuestionTopic (pivot)
`question_id, topic_id, is_primary BOOL`.

### Tag
`id, name, slug, type(keyword/high_yield/...)`. Pivot `question_tag`.

### QuestionReport (báo lỗi câu hỏi)
`id, question_id, user_id, reason(enum), detail TEXT, status(open/reviewing/resolved/rejected), resolved_by, resolution TEXT, timestamps`.

## 4. Nhóm Học tập (Learning activity)

### QuestionSession
| Field | Type | Ghi chú |
|-------|------|---------|
| id, uuid | | |
| user_id FK | | |
| mode | VARCHAR | study/exam |
| status | VARCHAR | active/paused/completed/expired/abandoned |
| source | VARCHAR | custom/weak_topics/study_plan/exam/self_assessment |
| filters | JSON | snapshot filter tạo session |
| question_ids | JSON | thứ tự câu |
| total | INT | số câu |
| answered_count | INT | |
| correct_count | INT | |
| time_limit_seconds | INT null | exam mode |
| started_at, finished_at | TIMESTAMP null | |
| paused_state | JSON | vị trí câu, timer còn lại |
| exam_id FK null | | nếu là exam/self-assessment |
| timestamps, soft delete | | |

Index: `user_id, status`, `mode`, `exam_id`.

### QuestionAttempt
| Field | Type | Ghi chú |
|-------|------|---------|
| id | BIGINT PK | |
| session_id FK | | |
| user_id FK | | denormalized để query nhanh |
| question_id FK | | |
| selected_option_ids | JSON | |
| is_correct | BOOL null | null nếu skip |
| used_hint | BOOL | |
| time_spent_seconds | INT | |
| confidence | VARCHAR null | guess/unsure/sure |
| flagged | BOOL | đánh dấu để review |
| answered_at | TIMESTAMP | |
| timestamps | | không soft delete |

Index: `(user_id, question_id)`, `session_id`, `answered_at`. Bảng lớn → cân nhắc partition theo tháng.

### QuestionStatus (trạng thái câu theo user — cache trạng thái mới nhất)
`id, user_id, question_id, status(unseen/incorrect/correct/omitted/marked), attempts_count, last_attempt_at, last_correct_at`. Unique `(user_id, question_id)`. Dùng cho filter "chưa làm / làm sai / làm đúng".

## 5. Nhóm Cá nhân hóa

### Note
`id, user_id, notable_type, notable_id (polymorphic: question/article/...), body TEXT/JSON(rich), color, timestamps, soft delete`. Index `(user_id, notable_type, notable_id)`.

### Bookmark
`id, user_id, bookmarkable_type, bookmarkable_id, folder_id null, timestamps`. Unique `(user_id, type, id)`.

### BookmarkFolder
`id, user_id, name, color, order, timestamps`.

### Highlight
`id, user_id, highlightable_type, highlightable_id, anchor JSON(vị trí/selector), text_snapshot TEXT, color, note TEXT null, timestamps, soft delete`.

### Flashcard
`id, user_id, deck_id null, front TEXT, back TEXT, source_type/source_id null (câu/bài liên kết), tags JSON, timestamps, soft delete`.

### FlashcardReview (SRS)
`id, flashcard_id, user_id, ease_factor, interval_days, repetitions, due_at, last_grade(again/hard/good/easy), last_reviewed_at, timestamps`. Index `(user_id, due_at)`.

### FlashcardDeck
`id, user_id, name, is_shared, timestamps`.

## 6. Nhóm Thư viện nội dung

### Article (Library / Disease / Procedure base)
| Field | Type | Ghi chú |
|-------|------|---------|
| id, uuid, slug | | |
| type | VARCHAR | disease/topic/procedure/general |
| title | VARCHAR | |
| summary | TEXT | |
| body | LONGTEXT | rich content, mục lục, cross-links |
| status | VARCHAR | draft/in_review/published/retired |
| is_free | BOOL | |
| toc | JSON | table of contents |
| references | JSON | |
| version | INT | |
| created_by/updated_by | | |
| timestamps, soft delete | | |

### Drug
`id, uuid, name, generic_name, brand_names JSON, drug_class, indications TEXT, contraindications TEXT, dosing JSON, side_effects TEXT, interactions JSON, references JSON, status, is_free, timestamps, soft delete`.

### Procedure
Có thể dùng `Article(type=procedure)` + field mở rộng: `steps JSON, indications, complications, media_ids JSON`.

### Media
| Field | Type | Ghi chú |
|-------|------|---------|
| id, uuid | | |
| type | VARCHAR | image/video/audio/document |
| disk | VARCHAR | s3/r2 |
| path | VARCHAR | |
| mime, size, width, height, duration | | |
| variants | JSON | thumbnail/webp/hls |
| alt, caption, credit | | y khoa cần credit |
| is_premium | BOOL | signed url |
| status | VARCHAR | processing/ready/failed |
| created_by | | |
| timestamps, soft delete | | |

### ContentLink (liên kết chéo)
`id, source_type, source_id, target_type, target_id, relation(mentions/see_also/treats/caused_by), timestamps`. Cho cross-linking Question ↔ Article ↔ Drug ↔ Media.

## 7. Nhóm Study Plan & Analytics

### StudyPlan
`id, user_id, name, exam_target_date, daily_goal_questions, daily_goal_minutes, topic_scope JSON, strategy(adaptive/fixed), status(active/completed/paused), progress_cache JSON, timestamps`.

### StudyPlanTask
`id, study_plan_id, date, type(questions/read/flashcards/review), target INT, done INT, status(pending/done/skipped), ref JSON, timestamps`.

### TopicMastery (Weak Topics / Heatmap nguồn)
`id, user_id, topic_id, attempts, correct, correct_rate DECIMAL, mastery_level(0-5), last_activity_at, trend JSON, updated_at`. Unique `(user_id, topic_id)`.

### DailyStat (analytics rollup)
`id, user_id, date, questions_answered, correct, minutes, sessions, avg_time, streak_flag, timestamps`. Unique `(user_id, date)`.

## 8. Nhóm Classroom / Live Review (Module 44)

> B2C cộng đồng — **không** dùng tên `classes` (dành Module 32 Organization Phase 2).

### Classroom
| Field | Type | Ghi chú |
|-------|------|---------|
| id, uuid | | |
| title | VARCHAR(200) | |
| description | TEXT null | |
| host_user_id | FK User | người tạo / host chính |
| purpose | VARCHAR(30) | enum: `community_review` / `feedback_review` / `exam_review` — xem Module 44 §16 |
| visibility | VARCHAR(20) | enum: public / unlisted / invite_only |
| join_code | VARCHAR(16) null | mã tham gia; unique khi có |
| status | VARCHAR(20) | enum: draft / **pending_approval** / active / archived |
| max_members | INT null | |
| cover_media_id | FK Media null | |
| meta | JSON | |
| timestamps, soft delete | | |

Index: `host_user_id`, `visibility`, `status`, `join_code`.

### ClassroomMember
`id, classroom_id FK, user_id FK, role_in_class(host/cohost/member), status(invited/active/left/banned), joined_at, timestamps`. Unique `(classroom_id, user_id)`.

### LiveSession
`id, uuid, classroom_id FK, title, scheduled_at, started_at null, ended_at null, status(scheduled/live/ended/cancelled), livekit_room_name, linked_exam_id FK null, question_set JSON null, timestamps, soft delete`. Index `(classroom_id, status)`, `scheduled_at`.

### LiveSessionMessage
`id, live_session_id FK, user_id FK, body TEXT, type(chat/question/system), is_hidden BOOL default false, created_at`. Index `(live_session_id, created_at)`. Insert-heavy; không soft-delete nghiệp vụ (ẩn bằng `is_hidden`).

### LiveRecording
`id, live_session_id FK, media_id FK null, duration_seconds INT null, status(processing/ready/failed), egress_id VARCHAR null, timestamps`. Quan hệ 1–n với session (retry egress).

## 9. Nhóm Thi cử

### Exam (đề mẫu/kỳ thi)
`id, uuid, title, type(mock/self_assessment/org_exam), description, duration_minutes, pass_score, available_from/to, access_type, is_premium, status(draft/published/archived), created_by, timestamps, soft delete`.

### ExamTopic (phân bổ câu theo chủ đề — admin config)
`id, exam_id FK, topic_id FK, question_count INT, sort_order INT`. Unique `(exam_id, topic_id)`.

### ExamQuestion (câu đã generate — snapshot cố định)
`id, exam_id FK, question_id FK, topic_id FK, sort_order INT`. Unique `(exam_id, question_id)`.

### ExamAttempt
`id, uuid, exam_id, user_id, session_id FK, score, percentile, status(scheduled/in_progress/submitted/graded), started_at, submitted_at, timestamps`.

## 10. Nhóm Thương mại

### Plan (gói)
`id, name, slug, price_cents, currency, interval(month/year/lifetime), features JSON, is_active, trial_days, timestamps`.

### Subscription
`id, user_id/organization_id, plan_id, status(trialing/active/past_due/canceled/expired), current_period_start/end, cancel_at, provider, provider_sub_id, seats INT null, timestamps`.

### Invoice / Payment
`invoices(id, subscription_id, amount_cents, currency, status(paid/open/void/refunded), issued_at, paid_at, pdf_media_id, provider_invoice_id)`;
`payments(id, invoice_id, amount_cents, method, status, provider_payment_id, paid_at)`.

### Coupon
`id, code, type(percent/fixed), value, max_redemptions, redeemed_count, valid_from/to, active`.

### Partner (Module 46 — CTV / chia sẻ doanh thu)
`partners(id, user_id UNIQUE, display_name, default_commission_rate_bps INT, status(active/suspended), timestamps)`.

`partner_invite_codes(id, partner_id FK, code UNIQUE, label, starts_at null, expires_at null, max_uses null, use_count, commission_rate_bps null, is_active, timestamps)`. Index `code`.

`partner_attributions(id, partner_id FK, invite_code_id FK, referred_user_id UNIQUE, attributed_at, source(link/code_field), timestamps)`.

`partner_commissions(id, partner_id FK, attribution_id FK, payment_id UNIQUE → billing_payments, referred_user_id, gross_cents, rate_bps, commission_cents, status(pending/approved/paid/void), payout_id null, timestamps)`. Index `(partner_id, status)`.

`partner_payouts(id, partner_id FK, period_from, period_to, amount_cents, status(draft/approved/paid/cancelled), paid_at null, note null, created_by FK users, timestamps)`.

## 11. Nhóm Hệ thống

### Notification
`id, user_id, type, title, body, data JSON, channel(in_app/email/push), read_at null, action_url, timestamps`. Index `(user_id, read_at)`.

### TrackingEvent
`id, user_id null, session_uuid, name, properties JSON, url, ip, user_agent, occurred_at, timestamps(insert-only)`. Bảng lớn → partition theo tháng, cân nhắc sink ra kho phân tích. Xem `06-tracking-analytics.md`.

### AuditLog
`id, actor_id, action, auditable_type, auditable_id, before JSON, after JSON, ip, user_agent, created_at`. Insert-only. Xem module 40.

### FeatureFlag
`id, key, description, enabled BOOL, rules JSON(role/org/percentage), timestamps`.

### Setting
`id, group, key, value JSON, is_public, timestamps` — cấu hình hệ thống.

### AiThread (Module 08 — AI Tutor)
`id, uuid, user_id FK, context_type, context_id, session_id FK null, title, preset VARCHAR null, created_at, updated_at`. Index `(user_id, updated_at)`, `(context_type, context_id)`.

### AiMessage
`id, uuid, thread_id FK, role(user/assistant/system), content LONGTEXT, preset VARCHAR null, tokens_in INT null, tokens_out INT null, citations JSON null, feedback VARCHAR null (up/down), created_at`. Index `(thread_id, id)`.

### AiUsage
`user_id FK, date DATE, count INT`. PK `(user_id, date)` — counter quota ngày (Redis là nguồn đếm realtime; bảng này để đối soát).

## 12. Sơ đồ quan hệ (ERD rút gọn)

```
User ─┬─< QuestionSession ─< QuestionAttempt >─ Question ─< QuestionOption
      │                                   │           ├─< QuestionTopic >─ Topic
      ├─< QuestionStatus >─ Question       │           └─< question_tag >─ Tag
      ├─< Note (poly)                      └─ (flagged/report) QuestionReport
      ├─< Bookmark (poly) ─ BookmarkFolder
      ├─< Highlight (poly)
      ├─< Flashcard ─< FlashcardReview ; Flashcard ─ FlashcardDeck
      ├─< StudyPlan ─< StudyPlanTask
      ├─< TopicMastery >─ Topic
      ├─< DailyStat
      ├─< ExamAttempt >─ Exam
      ├─ Subscription ─ Plan ; Subscription ─< Invoice ─< Payment
      ├─< Notification
      ├─< AiThread ─< AiMessage
      ├─ host Classroom ─< ClassroomMember >─ User
      │         └─< LiveSession ─< LiveSessionMessage
      │                      └─< LiveRecording >─ Media (HLS)
      # 🔵 (Phase 2) belongsTo Organization ─< OrganizationMember

Article/Drug/Procedure/Media ─< ContentLink >─ (poly bất kỳ)
```

## 13. Chỉ mục & hiệu năng dữ liệu (điểm nóng)

| Bảng | Vấn đề | Giải pháp |
|------|--------|-----------|
| question_attempts | ghi rất nhiều, query analytics | index `(user_id, question_id)`, partition theo tháng, rollup → DailyStat/TopicMastery + **`stats_cache` trên questions** (`SyncQuestionStatsJob`); admin list **không** COUNT trực tiếp |
| tracking_events | ghi cực lớn | insert-only, partition, batch insert qua queue, TTL/archival |
| live_session_messages | chat đồng thời cao khi live | index `(live_session_id, created_at)`, rate-limit, paginate |
| questions/articles | tìm kiếm full-text | đồng bộ sang Meilisearch |
| dashboard stats | đọc nặng lặp lại | cache Redis + rollup định kỳ |
