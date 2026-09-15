# Module 35 — Question Management (Admin/Editor)

**Nhóm:** Admin · **Ưu tiên:** Rất cao (chất lượng nội dung) · **Phụ thuộc:** Qbank (05), Media (37), RBAC (39), Audit (40), Search (25), Instructor portal (44) · **Trạng thái:** ✅

## 0. Tóm tắt module
CRUD & **workflow duyệt 3 lớp** câu hỏi: Content Creator soạn/sửa và **gán 1 giảng viên đúng môn học** → giảng viên đó duyệt chuyên môn (1 reject = fail ngay) → **2 reviewer** (role staff) gắn cờ xanh/vàng/đỏ → Admin/Super Admin publish hoặc trả về. Versioning, import (Excel/CSV/PDF), xử lý report, thống kê chất lượng.

| Route | Màn hình | Portal |
|-------|----------|--------|
| `/admin/questions` | Danh sách + filter + trạng thái | Admin |
| `/admin/questions/create` | Soạn câu hỏi | Admin |
| `/admin/questions/{id}/edit` | Chỉnh sửa (working copy) | Admin |
| `/admin/questions/{id}/compare` | So sánh working copy với bản đang xuất bản | Admin |
| `/admin/questions/import` | Import hàng loạt (Excel/CSV → draft) | Admin |
| `/admin/questions/export` | Export theo bộ lọc (Excel/CSV) | Admin |
| `/admin/questions/reports` | Xử lý báo lỗi câu hỏi | Admin |
| `/admin/questions/flags` | Review câu hỏi (hàng đợi reviewer gắn cờ) | Admin |
| `/teach/questions/reviews` | Hàng đợi giảng viên được gán | Teach |

## 1. Tổng quan
- **Mục đích:** Sản xuất & duy trì ngân hàng câu hỏi chất lượng qua kiểm soát 3 lớp (GV được gán → 2 reviewer gắn cờ → publish).
- **Đến từ:** Admin dashboard, report từ người dùng, hàng đợi `/teach`.
- **Đi sang:** Preview (như học viên), media, cross-link, Meilisearch (khi publish).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Question table** | Filter (status/hệ cơ quan-môn học-bài học/difficulty/report), search, sort | List | Table |
| **Question editor** | Rich editor stem, options, giải thích, references, lab values, media, bài học (≥1) + tags | Create/edit | Form nhiều section |
| **Preview pane** | Xem như học viên (study/exam) | Editor | Split |
| **Workflow bar** | Draft → Chờ GV → Chờ gắn cờ → Chờ publish → Published / Rejected / Private | Editor | — |
| **Metadata panel** | Creator, Instructor reviewer, Publisher, Created/Updated | Editor/Detail | Sidebar |
| **Analytics panel** | Attempts, correct rate, reports — đọc `stats_cache` | Detail | Tab |
| **Version history** | Sort `version_number`; instructor + publisher; snapshot read-only | Editor | Drawer |
| **Compare working copy** | Hai cột như `/teach` review: bản live vs bản đang sửa / gửi duyệt | Editor khi đã từng xuất bản | Page |
| **Clone action** | Nhân bản → câu mới `draft` | Detail/List | Button |
| **Import wizard** | Upload → map → validate → preview → commit | `/import` | Stepper |
| **Instructor review queue** | Danh sách `in_review` **gán cho mình** + approve/reject + lý do | `/teach/questions/reviews` | Table |
| **Reviewer flag queue** | Danh sách `in_flag_review` chưa có phiếu của mình + cờ xanh/vàng/đỏ + ghi chú tùy chọn; ẩn cờ peer và **không tiết lộ mình là người gắn cờ thứ mấy** | `/admin/questions/flags` | Table |
| **Reports queue** | Báo lỗi + xử lý | `/reports` | Table |
| **Duplicate check (per question)** | Nút trên editor → trang chi tiết kết quả ≥30% | Form → `/duplicates` | Detail page |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `QuestionEditor`(validate: đúng ≥1 đáp án, đủ giải thích), `OptionEditor`, `LessonPicker`(chọn ≥1 bài học; lọc tuỳ chọn theo hệ/môn), `InstructorPicker`(lọc GV giao môn với bài học), `TagPicker`, `WorkflowStatusBar`, `VersionHistory`(read-only snapshots), `CloneQuestionAction`, `ImportWizard`(map/validate), `InstructorReviewQueue`, `ReviewerFlagQueue`, `ReportQueue`, `QuestionPreview`, `DuplicateCheckPanel`(lexical fingerprint + % similarity trên form edit).

## 4. Luồng người dùng

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Lớp 0 — Content Creator (content_editor) trên /admin                   │
│  Soạn/sửa working copy · bắt buộc chọn 1 GV giao môn với bài học        │
│  KHÔNG tăng version                                                     │
│  draft / rejected  ──submit──►  in_review (chỉ GV được gán thấy)        │
│  (rút lại in_review / in_flag_review → draft nếu chưa xong pipeline)    │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────┐
│  Lớp 1 — 1 giảng viên được gán (instructor) trên /teach                 │
│  Duyệt chuyên môn · KHÔNG tăng version · KHÔNG publish                  │
│  in_review  ──approve──► in_flag_review                                 │
│  in_review  ──reject──► rejected ngay                                   │
│  «gửi duyệt lại» tăng cycle, xóa quyết định GV + 2 cờ reviewer          │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────┐
│  Lớp 1b — 2 reviewer (role reviewer) trên /admin — hàng đợi mở          │
│  Gắn cờ xanh / vàng / đỏ + ghi chú tùy chọn · KHÔNG tăng version        │
│  in_flag_review  ──cờ #1──► vẫn in_flag_review                          │
│  in_flag_review  ──cờ #2──► pending_publish                             │
│  Ẩn cờ peer trước khi mình gắn; creator không tự gắn cờ câu mình        │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────┐
│  Lớp 2 — Admin / Super Admin có `question.publish` trên /admin          │
│  Chỉ khi GV đã approve + đủ 2 cờ · KHÔNG sửa nội dung                   │
│  Xuất bản / trả về từ form `/admin/questions/{id}/edit` (lọc list theo  │
│  status `pending_publish`) — không còn trang hàng đợi riêng.            │
│  pending_publish  ──publish──►  published (+ question_versions)         │
│     (chặn nếu ≥1 cờ đỏ; cờ vàng = cảnh báo, vẫn cho publish)            │
│  pending_publish  ──trả về──►  rejected (lý do vận hành)                │
│  publisher ∉ {GV được gán, reviewer 1, reviewer 2}                      │
└─────────────────────────────────────────────────────────────────────────┘

Report từ user → queue → Creator sửa (working copy, không +version)
  → gửi lại lớp 1 → lớp 2 publish (version +1) → resolve report.

Clone: nhân bản → câu mới `draft`, lifecycle riêng.
Import: commit tạo hàng loạt `draft`.
```

## 5. Business Logic

### 5.1 Metadata & audit (bắt buộc hiển thị Admin UI)
| Field | Ý nghĩa | UI |
|-------|---------|-----|
| `created_by` | Content Creator tạo câu | Basic info |
| `assigned_instructor_id` | Giảng viên được editor gán (lọc theo môn học) | Picker / Basic info |
| `instructor_id` | GV lớp 1 đã quyết định (thường = assigned) | Basic info |
| `instructor_decision` / `instructor_note` | Approve / reject + ghi chú lớp 1 | Basic info |
| `reviewer_1_*` / `reviewer_2_*` | 2 slot cờ (id + flag green/yellow/red + note) | 2 cờ trắng/xanh/vàng/đỏ |
| `instructor_review_cycle` | Vòng biên tập; +1 mỗi lần submit / gửi lại (reset GV + 2 cờ) | — |
| `publisher_id` | Super Admin publish gần nhất (lớp 2) | Basic info |
| `rejection_reason` | Lý do từ chối (GV hoặc Super Admin) | Badge / alert |
| `created_at` / `updated_at` | Thời gian tạo / sửa working copy | Basic info |

> `reviewer_id` (legacy) → thay bằng `instructor_id` + `publisher_id`. Migration giữ alias đọc tạm nếu cần.

### 5.2 Versioning — **chỉ tăng khi Super Admin publish**
- Mọi chỉnh sửa của Content Creator trên **working copy** (`questions` + options…): **không** tạo / tăng version.
- Giảng viên approve/reject: **không** tạo version.
- **Chỉ khi Super Admin publish** (`pending_publish` → `published`):
  - `version_number` / `questions.version` **+1**
  - ghi `question_versions` snapshot JSON nội dung tại thời điểm publish
  - gán `publisher_id`, `published_at`; giữ `instructor_id` của lần duyệt lớp 1 tương ứng
- Version tăng dần (1, 2, 3…); mới nhất = `MAX(version_number)`.
- Session/review đang chạy dùng snapshot version lúc làm bài.
- **Không** rollback in-place — muốn bản cũ độc lập → **Clone** từ snapshot (§5.5).
- Admin UI version history: `version_number`, instructor, publisher, thời gian publish, xem snapshot read-only.

### 5.3 Workflow & trạng thái câu hỏi

| Status | Ý nghĩa | Ai chuyển tới | Hiển thị học viên (Qbank) |
|--------|---------|---------------|---------------------------|
| `draft` | Nháp / đang soạn; Creator sửa tự do | Tạo mới; rút lại từ `in_review` / `in_flag_review`; sau reject khi bắt đầu sửa | Không\* |
| `in_review` | Đã gửi, **chờ GV được gán** duyệt chuyên môn | Creator `submit` / gửi lại | Không\* |
| `in_flag_review` | GV đã approve, **chờ đủ 2 cờ reviewer** | Instructor approve | Không\* |
| `pending_publish` | Đủ 2 cờ + GV đã duyệt, **chờ Admin/SA** | Reviewer gắn cờ #2 | Không\* |
| `published` | Super Admin đã publish phiên bản | Super Admin `publish` | Có (theo gating) |
| `rejected` | Bị từ chối ở lớp 1 hoặc lớp 2; có `rejection_reason` | Instructor / Super Admin `reject` | Không\* |
| `private` | Pool exam (`exam_flag=true`) | Super Admin (sau đủ pipeline hoặc quy tắc exam riêng) | Không (Qbank) |
| `retired` | Ngừng dùng (giữ attempt) | Super Admin | Không |

\* **Ngoại lệ tái bản:** nếu câu đã từng publish (`published_version >= 1`), Qbank **vẫn phục vụ snapshot version đã publish** trong lúc working copy đi lại pipeline (`draft` / `in_review` / `in_flag_review` / `pending_publish` / `rejected`). Nội dung live **chỉ** đổi khi Super Admin publish lần mới (version +1). Câu chưa từng publish thì không lộ Qbank.

**Máy trạng thái (happy path + nhánh từ chối):**

```
                  submit              GV approve              đủ 2 cờ           publish
  draft ────────────────► in_review ────────────► in_flag_review ────► pending_publish ──► published
    ▲                       │                         │                     │
    │         GV reject     │    gửi duyệt lại        │                     │ trả về
    │◄──── rejected ◄───────┘    (reset cycle)        │                     │
    │         ▲                  ◄────────────────────┘                     │
    └─ Creator sửa (không +version) ────────────────────────────────────────┘
```

- List admin: cột **Trạng thái** = xuất bản. Cột **Bản gửi duyệt** = giai đoạn pipeline + GV được gán + 2 cờ reviewer (trắng / xanh / vàng / đỏ).
- Submit bắt buộc `assigned_instructor_id` và GV đó giao ≥1 môn với bài học của câu (`instructor_subject`).
- 1 GV reject = fail ngay — không sang reviewer.
- Từ `in_review` / `in_flag_review`: Creator được sửa; gửi duyệt lại tăng cycle và xóa quyết định GV + 2 cờ. Withdraw → `draft` cũng xóa phiếu hiện tại (giữ GV đã gán).
- `/admin/questions/flags` (**Review câu hỏi**) ẩn cờ reviewer kia trước khi mình gắn (tránh neo theo) và không cho biết mình là người gắn cờ thứ mấy.
- ≥1 cờ đỏ: Admin **không** publish — chỉ trả về biên tập. Cờ vàng: cảnh báo, vẫn cho publish.
- Từ `pending_publish`: không cho Creator sửa trực tiếp — Admin trả về (`rejected`) vì lý do vận hành.
- `retired` / `private`: chỉ Admin/SA; không đi ngược về Creator trừ clone.
- Legacy: câu `pending_publish` đã đủ 2 accept GV (trước khi tách reviewer) vẫn xuất bản được.

**Quyền (deny by default):**

| Actor | Role | Được làm |
|-------|------|----------|
| Content Creator | `content_editor` | CRUD working copy; chọn GV; `submit`; withdraw; clone; import draft; xử lý report (sửa) |
| Giảng viên | `instructor` | 1 phiếu `approve` / `reject` trên `/teach` **chỉ câu gán cho mình**; không publish, không +version |
| Reviewer | `reviewer` | Gắn 1 cờ/vòng trên `/admin/questions/flags`; không soạn, không publish |
| Admin | `admin` | Xem + **publish / private / retire / xoá**; gán môn học cho GV; **không** soạn / không duyệt thay GV / không gắn cờ |
| Super Admin | `super_admin` | Oversight + cùng quyền trạng thái/xoá như Admin; **không** soạn/sửa/review/flag |

Permissions: `question.create|update|delete|submit` (`content_editor`), `question.review` (`instructor`), `question.flag` (`reviewer`, không kèm `question.view` — hàng đợi `/admin/questions/flags` thôi), `question.publish` (`admin` + `super_admin`).

### 5.4 Question analytics (Admin — **rollup job**, không COUNT realtime trên list)
- **Danh sách câu hỏi:** chỉ đọc `stats_cache` JSON trên `questions` — **không** aggregate trực tiếp từ `question_attempts`.
- **Job rollup định kỳ** (`SyncQuestionStatsJob`): quét `question_attempts` + `question_sessions.mode` + `question_reports` → ghi `stats_cache`.
- Trigger bổ sung: sau batch finish session, sau resolve report (debounce/queue).
- **Detail:** hiển thị từ `stats_cache`; nút "Refresh stats" enqueue job nếu cần cập nhật gần realtime.

| Metric trong `stats_cache` | Ghi chú |
|----------------------------|---------|
| `total_attempts`, `study_mode_attempts`, `exam_mode_attempts` | |
| `correct_attempts`, `incorrect_attempts`, `correct_rate` | không chia 0 |
| `average_score` | nếu có scoring |
| `total_reports` | breakdown theo `reason` optional |
| `stats_updated_at` | thời điểm rollup gần nhất |

Tránh N+1: eager load creator / instructor / publisher trên list; **không** join/count `question_attempts` trên list.

### 5.5 Clone (thay cho re-generate câu hỏi)
- **Không** có thao tác re-generate / áp dụng lại snapshot lên câu đang tồn tại.
- **Clone:** tạo `question` **mới** (`draft`), copy nội dung từ câu gốc hoặc từ snapshot version (`cloned_from_id`, `cloned_from_version` optional).
- Câu gốc và attempt/history giữ nguyên; câu clone có lifecycle riêng (phải đi lại 2 lớp duyệt).

### 5.6 Phân loại nội dung (Hệ cơ quan ∥ Môn học → Bài học)
- Phân loại: `organ_systems` và `subjects` **độc lập**; bài học gắn qua `lesson_organ_system`, `lesson_subject` (0 hoặc nhiều mỗi trục), thay cho cây `topics` cũ.
- Admin UI editor: chọn **Bài học** (lọc tuỳ chọn theo hệ cơ quan và/hoặc môn học — không cascade cha–con).
- Câu gắn **≥1 Bài học** (`question_lesson`); các bài ngang hàng, không phân biệt primary. Filter Qbank/exam pool: chọn Hệ cơ quan và/hoặc Môn học → gồm câu thuộc bài học gắn trực tiếp.

### 5.7 Kiểm tra trùng lặp (lexical — phase 1)
- **Mục đích:** trên form edit một câu, mở **trang chi tiết** để quét ngân hàng xem câu nào trùng / gần trùng. **Không** chặn workflow cứng (chỉ cảnh báo).
- **Chuẩn hóa:** HTML→plain, lowercase, bỏ dấu (VN/EN), collapse whitespace; options sort theo nội dung để fingerprint ổn định khi đổi thứ tự hiển thị.
- **Exact:** `content_fingerprint` = SHA-256(stem_norm + options_sorted + correct flags) trên `questions`.
- **Near-dup scoring:** % = stem ~70% + options bag ~30% (Jaccard token / similar_text); chỉ lưu / hiển thị cặp **≥30%**.
- **Mức độ (`DuplicateSeverity`):** Exact 100% · VeryHigh ≥90% · High ≥75% · Medium ≥60% · Low ≥30%.
- **UI:** nút “Kiểm tra trùng lặp” trên form → `GET /admin/questions/{id}/duplicates` (KPI + bảng chi tiết + stem/options câu gốc); **Quét lại** = `POST .../check-duplicates`.
- **Job phụ:** `RefreshQuestionSimilarityJob` sau save. Import dedup (§5.8) tái sử dụng cùng scorer sau.

### 5.7b Đáp án có thể đảo thứ tự
- Editor: chữ A/B/C chỉ là preview theo vị trí form (`order` / denormalized `label`).
- **Không** viết “đáp án A/B…” trong stem/giải thích như nghĩa gắn cứng — mô tả theo nội dung lựa chọn.
- Runtime (QBank session + live classroom): đảo theo seed; chấm/`selected_option_ids` / reveal dùng `option.id`.
- Version snapshot (`question_versions.snapshot.options[]`) **bắt buộc** có `id` để overlay published vẫn chấm được.

### 5.8 Khác
- **Validation nội dung:** đúng ≥1 (single: đúng 1), giải thích bắt buộc, Bài học ≥1 — bắt buộc trước `submit` và trước `publish`.
- **Import:** map cột, validate, dedup, preview trước commit; rollback batch; sau import vẫn `draft` → Creator submit từng câu / hàng loạt vào lớp 1.
- **Report handling:** open→reviewing→resolved/rejected; ảnh hưởng hiển thị (ẩn tạm nếu nghiêm trọng).
- **Retire** thay vì xóa cứng (giữ lịch sử attempt) — chỉ Super Admin.
- **Đồng bộ Meilisearch** khi publish/retire (chỉ bản live `published`; không index `private` exam pool).
- **Stats:** correct rate thực nghiệm → gợi ý câu quá dễ/khó/mơ hồ.

## 6. Database
- `questions`:
  - `status` enum §5.3 (`draft` / `in_review` / `in_flag_review` / `pending_publish` / `published` / `rejected` / `private` / `retired`)
  - `version` INT (denormalized = version đã publish gần nhất; 0 nếu chưa từng publish)
  - `published_version` INT null (trùng `version` khi đang live; dùng Qbank đọc snapshot)
  - `assigned_instructor_id` FK null, `instructor_id` FK null (GV đã quyết), `instructor_decision` / `instructor_note` / `instructor_reviewed_at`
  - `instructor_review_cycle` UINT default 0; `reviewer_1_id` / `reviewer_1_flag` / `reviewer_1_note`; `reviewer_2_*` (denormalize 2 cờ)
  - `instructor_1_*` / `instructor_2_*` (legacy 2 slot GV — giữ để xuất bản câu cũ)
  - `rejection_reason` TEXT null, `rejected_by_role` ENUM(`instructor`,`super_admin`) null
  - `exam_flag`, `cloned_from_id`, `cloned_from_version`, `created_by`, `updated_by`, timestamps
  - `content_fingerprint` CHAR(64) null + index; `similarity_checked_at` timestamp null
- `question_similarity_matches`: `question_id_low`, `question_id_high` (UUID, low < high), `score`, `severity`, `signals` JSON, `detected_at`; unique cặp
- `question_options`, `question_lesson` (`question_id` uuid, `lesson_id`), `question_tags`, `question_reports`
- `question_versions`: `question_id`, `version_number`, `instructor_id` FK, `publisher_id` FK, `snapshot` JSON, `created_at`; unique `(question_id, version_number)`; **chỉ tạo khi Super Admin publish**
- `question_instructor_reviews`: `question_id`, `review_cycle`, `instructor_id`, `decision` (approved/rejected), `note`, `content_fingerprint`, `reviewed_at`; unique `(question_id, review_cycle, instructor_id)` — tối đa 1 phiếu GV/vòng
- `question_reviewer_flags`: `question_id`, `review_cycle`, `reviewer_id`, `flag` (green/yellow/red), `note` null, `content_fingerprint`, `reviewed_at`; unique `(question_id, review_cycle, reviewer_id)`
- `instructor_subject`: `user_id` + `subject_id` (unique) — môn học giảng viên được gán
- `question_review_requests` (optional / giữ): theo dõi yêu cầu submit lớp 1; status pending/approved/rejected; **không** thay thế `questions.status`
- `organ_systems`, `subjects`, `lessons` (mỗi bảng: `id, name, slug UK, description null, status, sort_order`); pivot `lesson_organ_system`, `lesson_subject`
- `question_import_batches(id, uploaded_by, original_filename, disk_path, format, status, source_headers, column_map, stats, error_report_path, committed_at)`
- `questions.import_batch_id` FK null — gắn câu tạo từ lô import (luôn `draft`)
- `stats_cache` JSON + `stats_updated_at` trên `questions`

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/questions` | filter | list | `question.view` |
| POST | `/api/v1/admin/questions` | question payload | draft | `question.create` |
| PUT | `/api/v1/admin/questions/{id}` | fields | working copy (không +version) | `question.update` |
| POST | `/api/v1/admin/questions/{id}/submit` | — | `in_review` | `question.submit` |
| POST | `/api/v1/admin/questions/{id}/withdraw` | — | `draft` | `question.submit` |
| POST | `/api/v1/teach/questions/{id}/approve` | `{note?}` | `in_flag_review` | `question.review` |
| POST | `/api/v1/teach/questions/{id}/reject` | `{reason}` | `rejected` | `question.review` |
| POST | `/admin/questions/{id}/flags` | `{flag, note?}` | `in_flag_review` hoặc `pending_publish` | `question.flag` |
| POST | `/api/v1/admin/questions/{id}/publish` | — | `published` (+ version) | `question.publish` (super_admin) |
| POST | `/api/v1/admin/questions/{id}/reject-publish` | `{reason}` | `rejected` | `question.publish` |
| POST | `/api/v1/admin/questions/{id}/clone` | `{from_version?}` | draft (câu mới) | `question.create` |
| POST | `/api/v1/admin/questions/{id}/retire` | — | retired | `question.retire` |
| POST | `/admin/questions/import` | file → map → commit | batch draft | `question.create` |
| GET | `/admin/questions/import/template` | `format=xlsx\|csv` | file | `question.create` |
| GET | `/admin/questions/export` | filter + `format` | file | `question.view` |
| GET/POST | `/api/v1/admin/questions/reports` | — | queue/resolve | `question.update` |
| POST | `/admin/questions/{id}/check-duplicates` | — | refresh pairs + redirect detail | `question.view` |
| GET | `/admin/questions/{id}/duplicates` | — | trang chi tiết kết quả ≥30% | `question.view` |

Validation nghiêm; `409` optimistic lock trên working copy; audit mọi mutate workflow.

## 8. State Management
- Autosave draft/working copy; optimistic lock; import async + progress; search sync async khi publish/retire.
- UI workflow bar chỉ hiện action đúng role (Creator không thấy Publish; Instructor không thấy Publish; SA không thay nút Approve của GV trừ override có audit).

## 9. Phân quyền
- **Content Editor:** CRUD working copy, chọn GV theo môn, submit/withdraw, clone, import draft. Không approve GV, không gắn cờ, không publish.
- **Instructor:** hàng đợi `/teach` chỉ câu gán cho mình; approve → `in_flag_review` / reject. Không sửa nội dung, không publish.
- **Reviewer:** hàng đợi `/admin/questions/flags`; gắn 1 cờ/vòng. Không soạn, không publish.
- **Admin / Super Admin:** oversight; publish / private / retire / xoá; gán môn cho GV; **không** soạn / review / flag.
- Publish cần đã qua lớp GV + đủ 2 cờ, không có cờ đỏ, publisher khác GV và 2 reviewer. Xem RBAC (`03-phan-quyen-rbac.md`).

## 10. Edge Cases
- Publish → snapshot version mới; session đang chạy giữ version cũ.
- Sửa working copy (kể cả sau reject) **không** +version cho đến khi SA publish.
- Câu đã live: learner luôn đọc snapshot `published_version` cho đến publish lần sau.
- Concurrent edit → 409; import lớn → chunk + queue; media chưa ready → chặn submit/publish.
- Instructor reject và SA reject đều về `rejected` nhưng `rejected_by_role` phân biệt để UI hướng dẫn Creator.
- Super Admin **không** bỏ qua lớp GV trên happy path; override khẩn cấp (nếu có) phải ghi audit + lý do.

## 11. Tracking (audit + product)
`question_create`, `question_update`, `question_submit`, `question_withdraw`, `question_instructor_approve`, `question_instructor_reject`, `question_reviewer_flagged`, `question_publish`, `question_reject_publish`, `question_retire`, `question_import`, `question_export`, `report_resolve`, `question_preview`.

## 12. Responsive
- Desktop tối ưu (editor phức tạp); `/teach` review queue dùng được trên tablet; mobile hạn chế (duyệt nhanh, xử lý report).

## 13. Security
- RBAC nghiêm theo 3 lớp; sanitize rich content (XSS); audit; kiểm soát import; **Creator không tự publish**; **Instructor không tự publish**; **Reviewer không tự publish**; portal tách `/admin` vs `/teach`.

## 14. Performance
- Import/sync qua queue; server pagination; version snapshot tránh query nặng; preview cache.
- **List analytics:** chỉ `stats_cache`; rollup job xử lý `question_attempts` nặng — không COUNT trên list.
- Hàng đợi GV / SA filter theo `status` + index `(status, updated_at)`.
- **Duplicate check:** trang chi tiết per-question (`/duplicates`); lưu/hiển thị cặp ≥30%; candidate theo fingerprint + stem bucket.

## 15. Đề xuất cải tiến
- AI hỗ trợ soạn; psychometrics; so sánh diff working copy vs version live; SLA nhắc duyệt; bulk-assign GV sau import.

## 16. Phạm vi triển khai (Phase 2a MVP → mở rộng)

**Đang ưu tiên sau Admin Phase 0–1.** Các hạng mục User CSKH nâng cao (impersonate, override Premium, bulk, CSV) **hoãn** — xem module 34 §16.

| Đợt | Phạm vi |
|-----|---------|
| **2a MVP** | Status `pending_publish`; Creator submit; Instructor approve/reject trên `/teach`; Super Admin publish (+version); RBAC tách `question.review` / `question.publish`; metadata instructor/publisher |
| **2b** | Report queue, preview học viên, version compare, rejection UI 2 nguồn, withdraw |
| **2c** | Import/export Excel+CSV (luôn `draft`, không publish); media picker (37); `SyncQuestionStatsJob`; gán GV theo Môn học |
