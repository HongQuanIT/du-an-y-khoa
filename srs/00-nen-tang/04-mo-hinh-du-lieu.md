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
| organization_id | FK Organization null | |
| last_login_at | TIMESTAMP null | |
| streak_count | INT default 0 | |
| exam_target_date | DATE null | dùng cho Study Plan |
| meta | JSON | onboarding flags, prefs |
| timestamps, soft delete | | |

Index: `email`, `role`, `organization_id`, `status`.
Quan hệ: hasMany Attempt/Session/Note/Bookmark/Highlight/Flashcard; belongsTo Organization; hasOne Subscription; hasMany OAuthAccount, Device.

### OAuthAccount
`id, user_id FK, provider(google/facebook/apple), provider_user_id, access_token(enc), refresh_token(enc), expires_at, timestamps`. Unique `(provider, provider_user_id)`.

### Device / Session (auth)
`id, user_id FK, device_name, ip, user_agent, last_active_at, revoked_at, token_hash, timestamps`.

### Role / Permission / RoleUser / PermissionRole
Chuẩn RBAC: `roles(id,name,slug)`, `permissions(id,name,slug)`, `permission_role`, `role_user` (nếu multi-role). Xem `03-phan-quyen-rbac.md`.

### Organization
`id, uuid, name, type(university/hospital/company), seats_total, seats_used, billing_email, status, meta JSON, timestamps, soft delete`.

### OrganizationMember
`id, organization_id FK, user_id FK, org_role(member/instructor/org_admin), invited_by, joined_at, status(invited/active/removed), timestamps`. Unique `(organization_id, user_id)`.

## 3. Nhóm Nội dung câu hỏi

### Question
| Field | Type | Ghi chú |
|-------|------|---------|
| id, uuid | | |
| stem | LONGTEXT | vignette/đề bài (HTML/Markdown an toàn) |
| lead_in | TEXT | câu hỏi dẫn ("Chẩn đoán phù hợp nhất?") |
| type | VARCHAR | single_best/multi/matching |
| difficulty | VARCHAR | easy/medium/hard (hoặc 1–5) |
| status | VARCHAR | draft/in_review/published/retired |
| is_free | BOOL | dùng cho preview free tier |
| explanation | LONGTEXT | giải thích tổng |
| references | JSON | nguồn (guideline, sách) |
| lab_values | JSON | chỉ số tham chiếu kèm câu |
| media_ids | JSON | ảnh/video đính kèm |
| version | INT | quản lý phiên bản |
| stats_cache | JSON | tỉ lệ đúng, độ khó thực nghiệm |
| created_by, updated_by | | |
| timestamps, soft delete | | |

Index: `status`, `difficulty`, `is_free`. Full-text → Meilisearch.

### QuestionOption
`id, question_id FK, label(A/B/...), content TEXT, is_correct BOOL, explanation TEXT (vì sao đúng/sai), order INT, timestamps`.

### Topic (chuyên ngành/chủ đề — phân cấp)
`id, parent_id FK null, name, slug, type(specialty/system/subtopic), order, icon, timestamps`. Ví dụ 18 chuyên ngành: Nội, Ngoại, Sản, Nhi, Dược...

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

## 8. Nhóm Thi cử

### Exam (đề mẫu/kỳ thi)
`id, uuid, title, type(mock/self_assessment/org_exam), description, question_ids JSON hoặc rule JSON, duration_minutes, pass_score, available_from/to, is_premium, status, created_by, timestamps, soft delete`.

### ExamAttempt
`id, uuid, exam_id, user_id, session_id FK, score, percentile, status(scheduled/in_progress/submitted/graded), started_at, submitted_at, timestamps`.

## 9. Nhóm Thương mại

### Plan (gói)
`id, name, slug, price_cents, currency, interval(month/year/lifetime), features JSON, is_active, trial_days, timestamps`.

### Subscription
`id, user_id/organization_id, plan_id, status(trialing/active/past_due/canceled/expired), current_period_start/end, cancel_at, provider, provider_sub_id, seats INT null, timestamps`.

### Invoice / Payment
`invoices(id, subscription_id, amount_cents, currency, status(paid/open/void/refunded), issued_at, paid_at, pdf_media_id, provider_invoice_id)`;
`payments(id, invoice_id, amount_cents, method, status, provider_payment_id, paid_at)`.

### Coupon
`id, code, type(percent/fixed), value, max_redemptions, redeemed_count, valid_from/to, active`.

## 10. Nhóm Hệ thống

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

## 11. Sơ đồ quan hệ (ERD rút gọn)

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
      └─ belongsTo Organization ─< OrganizationMember

Article/Drug/Procedure/Media ─< ContentLink >─ (poly bất kỳ)
```

## 12. Chỉ mục & hiệu năng dữ liệu (điểm nóng)

| Bảng | Vấn đề | Giải pháp |
|------|--------|-----------|
| question_attempts | ghi rất nhiều, query analytics | index `(user_id, question_id)`, partition theo tháng, rollup sang DailyStat/TopicMastery qua job |
| tracking_events | ghi cực lớn | insert-only, partition, batch insert qua queue, TTL/archival |
| questions/articles | tìm kiếm full-text | đồng bộ sang Meilisearch |
| dashboard stats | đọc nặng lặp lại | cache Redis + rollup định kỳ |
