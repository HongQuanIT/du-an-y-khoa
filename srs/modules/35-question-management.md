# Module 35 — Question Management (Admin/Editor)

**Nhóm:** Admin · **Ưu tiên:** Rất cao (chất lượng nội dung) · **Phụ thuộc:** Qbank (05), Media (37), RBAC (39), Audit (40), Search (25), Instructor portal (44) · **Trạng thái:** ✅

## 0. Tóm tắt module
CRUD & **workflow duyệt 2 lớp** câu hỏi: Content Creator soạn/sửa → **2 giảng viên** duyệt chuyên môn (1 reject = fail ngay) → Super Admin publish phiên bản (không cần kiến thức y khoa). Versioning, import (Excel/CSV/PDF), xử lý report, thống kê chất lượng.

| Route | Màn hình | Portal |
|-------|----------|--------|
| `/admin/questions` | Danh sách + filter + trạng thái | Admin |
| `/admin/questions/create` | Soạn câu hỏi | Admin |
| `/admin/questions/{id}/edit` | Chỉnh sửa (working copy) | Admin |
| `/admin/questions/import` | Import hàng loạt (Excel/CSV → draft) | Admin |
| `/admin/questions/export` | Export theo bộ lọc (Excel/CSV) | Admin |
| `/admin/questions/reports` | Xử lý báo lỗi câu hỏi | Admin |
| `/admin/questions/pending-publish` | Hàng đợi Super Admin publish | Admin |
| `/teach/questions/reviews` | Hàng đợi giảng viên duyệt | Teach |

## 1. Tổng quan
- **Mục đích:** Sản xuất & duy trì ngân hàng câu hỏi chất lượng qua kiểm soát 2 lớp.
- **Đến từ:** Admin dashboard, report từ người dùng, hàng đợi `/teach`.
- **Đi sang:** Preview (như học viên), media, cross-link, Meilisearch (khi publish).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Question table** | Filter (status/hệ cơ quan-môn học-bài học/difficulty/report), search, sort | List | Table |
| **Question editor** | Rich editor stem, options, giải thích, references, lab values, media, bài học (≥1) + tags | Create/edit | Form nhiều section |
| **Preview pane** | Xem như học viên (study/exam) | Editor | Split |
| **Workflow bar** | Draft → Chờ GV → Chờ publish → Published / Rejected / Private | Editor | — |
| **Metadata panel** | Creator, Instructor reviewer, Publisher, Created/Updated | Editor/Detail | Sidebar |
| **Analytics panel** | Attempts, correct rate, reports — đọc `stats_cache` | Detail | Tab |
| **Version history** | Sort `version_number`; instructor + publisher; snapshot read-only | Editor | Drawer |
| **Clone action** | Nhân bản → câu mới `draft` | Detail/List | Button |
| **Import wizard** | Upload → map → validate → preview → commit | `/import` | Stepper |
| **Instructor review queue** | Danh sách `in_review` chưa có phiếu của mình + approve/reject + lý do; ẩn phiếu GV kia trước khi mình quyết | `/teach/questions/reviews` | Table |
| **Publish queue** | Danh sách `pending_publish` + publish/reject | `/admin/questions/pending-publish` | Table |
| **Reports queue** | Báo lỗi + xử lý | `/reports` | Table |
| **Duplicate check (per question)** | Nút trên editor → trang chi tiết kết quả ≥30% | Form → `/duplicates` | Detail page |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `QuestionEditor`(validate: đúng ≥1 đáp án, đủ giải thích), `OptionEditor`, `LessonPicker`(chọn ≥1 bài học; lọc tuỳ chọn theo hệ/môn), `TagPicker`, `WorkflowStatusBar`, `VersionHistory`(read-only snapshots), `CloneQuestionAction`, `ImportWizard`(map/validate), `InstructorReviewQueue`, `PublishQueue`, `ReportQueue`, `QuestionPreview`, `DuplicateCheckPanel`(lexical fingerprint + % similarity trên form edit).

## 4. Luồng người dùng

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Lớp 0 — Content Creator (content_editor) trên /admin                   │
│  Soạn/sửa working copy thoải mái · KHÔNG tăng version                   │
│  draft / rejected  ──submit──►  in_review                               │
│  (gán 1 GV đúng môn; rút lại in_review → draft nếu chưa duyệt)          │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────┐
│  Lớp 1a — 1 giảng viên được gán (instructor) trên /teach                │
│  Duyệt chuyên môn · KHÔNG tăng version · KHÔNG publish lên Qbank        │
│  in_review  ──accept──► in_flag_review                                  │
│  in_review  ──reject──► rejected ngay                                   │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────┐
│  Lớp 1b — 2 reviewer (`question.flag`) trên /admin/questions/flags      │
│  Gắn cờ xanh/đỏ (đỏ bắt buộc ghi chú) · KHÔNG tăng version               │
│  in_flag_review ──2 xanh──► pending_publish (sẵn XB)                    │
│  in_flag_review ──≥1 đỏ──► pending_publish (fail-fast; Admin phải trả)  │
│  Có cờ đỏ → Admin không được publish                                     │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────────┐
│  Lớp 2 — Admin có `question.publish` trên /admin                        │
│  Chỉ publish khi GV đã duyệt + đủ 2 cờ xanh · KHÔNG sửa nội dung        │
│  pending_publish  ──publish──►  published (+ question_versions)         │
│  pending_publish  ──trả về──►  rejected (vận hành hoặc có cờ đỏ)        │
│  Publisher ∉ {GV được gán, 2 reviewer đã gắn cờ}                        │
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
| `instructor_id` | Giảng viên lớp 1 quyết định gần nhất | Basic info |
| `instructor_1_*` / `instructor_2_*` | Legacy 2 slot GV (id + decision) | — |
| `reviewer_1_*` / `reviewer_2_*` | 2 slot cờ reviewer (id + green/red + note) | 2 cờ trắng/xanh/đỏ |
| `instructor_review_cycle` | Vòng duyệt; +1 mỗi lần submit / gửi lại | Timeline / list |
| `pipeline_reject_count` | Số lần trả về (GV + Admin) trong pipeline chưa XB; reset khi publish | List «Vòng N · X lần trả về» |
| `publisher_id` | Super Admin publish gần nhất (lớp 2) | Basic info |
| `rejection_reason` | Lý do từ chối (GV hoặc Super Admin) | Badge / alert |
| `created_at` / `updated_at` | Thời gian tạo / sửa working copy | Basic info |

> `reviewer_id` (legacy) → thay bằng `instructor_id` + `publisher_id`. Migration giữ alias đọc tạm nếu cần.

### 5.1b Timeline duyệt (Admin)
- Nguồn: `question_instructor_reviews` + `question_reviewer_flags` + `question_workflow_events` (`submit` / `admin_reject` / `publish`).
- UI form: partial «Lịch sử duyệt» nhóm theo `review_cycle` — ai duyệt, ai gắn cờ, ai trả về, ghi chú, thời gian.
- Khi publish: ghi `snapshot.review_pipeline` (cycle, reject count, instructor, 2 cờ, publisher) vào `question_versions`.
- List admin: nhãn `pipelineProgressLabel()` = «Vòng N · X lần trả về» **chỉ khi đang trong pipeline** (chờ GV / chờ cờ / chờ XB / từ chối). Nháp, đã xuất bản, private, retire → không hiện «Vòng N». Cột bản gửi: nháp → «Bản nháp».
- Timeline «Lịch sử duyệt»: nhóm theo **phiên bản** — «Bản hiện tại» (trạng thái working copy + các vòng sau XB gần nhất) và từng **Phiên bản N đã xuất bản** (các vòng duyệt dẫn tới XB đó). Mỗi segment mở/đóng; trong segment là các vòng + QA.

### 5.1c QA chất lượng duyệt (Admin)
- Outcome trên `question_reviewer_flags` / `question_instructor_reviews` (theo từng **vòng** `review_cycle` + actor):
  - Reviewer: `pending|confirmed|false_positive|inconclusive` — `confirmed` = gắn đúng, `false_positive` = **gắn sai** (đỏ oan **hoặc** xanh sai).
  - GV: `pending|confirmed|miss|over_reject|inconclusive` — Admin chỉ thấy **Duyệt đúng / Duyệt sai**; `miss` (approve sai) và `over_reject` (reject sai) đều là duyệt sai (cùng nhãn).
- **Đánh dấu ngay tại vòng (khuyến nghị UX):** trên «Lịch sử duyệt», mỗi entry cờ/duyệt có badge outcome + Admin (permission **`question.adjudicate`**) chỉnh outcome thủ công khi phát hiện sai — ghi `outcome_source=admin`, `outcome_by`, `outcome_at`, `outcome_note`.
- **Cascade theo vòng khi Admin adjudicate:**
  - Xác nhận cờ đỏ đúng → set red flags vòng đó `confirmed` + approve GV cùng vòng → `miss` (hiển thị **Duyệt sai**).
  - Đánh cờ đỏ gắn sai → red flags `false_positive`; không quy lỗi GV.
  - Đánh cờ xanh gắn sai (nên đỏ) → green flags `false_positive`; nếu cùng vòng có approve GV → `miss`.
  - Đánh GV duyệt sai khi reject → lưu `over_reject` (UI: **Duyệt sai**).
- **Tự động (giữ):** Admin trả về khi có cờ đỏ chọn Cờ đỏ đúng / Cờ đỏ gắn sai; khi publish heuristic fingerprint adjudicate pending đỏ + reject GV; approve không bị tranh chấp → `confirmed`.
- Báo cáo `content.review-qa`: KPI + bảng Reviewer (tổng/đỏ/xanh/gắn sai) + bảng GV (tổng/approve/reject/duyệt sai). Permission: `report.view` + `question.view`.
- **UI:** «Lịch sử duyệt» — Admin chọn **Duyệt đúng / Duyệt sai** (hoặc Gắn đúng / Gắn sai) rồi Lưu QA; ghi đúng `review_cycle` + actor. Route `POST …/review-outcomes` yêu cầu `question.adjudicate` (mặc định `admin` + `super_admin`; **không** cấp cho `content_editor`).

### 5.2 Versioning — **chỉ tăng khi Super Admin publish**
- Mọi chỉnh sửa của Content Creator trên **working copy** (`questions` + options…): **không** tạo / tăng version.
- Giảng viên approve/reject: **không** tạo version.
- **Chỉ khi Super Admin publish** (`pending_publish` → `published`):
  - `version_number` / `questions.version` **+1**
  - ghi `question_versions` snapshot JSON nội dung tại thời điểm publish
  - gán `publisher_id`, `published_at`; giữ `instructor_id` của lần duyệt lớp 1 tương ứng
- Version tăng dần (1, 2, 3…); mới nhất = `MAX(version_number)`.
- Session/review đang chạy dùng snapshot version lúc làm bài.
- **Khôi phục phiên bản (Editor):** áp snapshot vào **working copy** + về `draft`; **không** +version, **không** tạo `question_versions`. QBank vẫn phục vụ `published_version` đến khi Admin publish lại. Muốn bản cũ thành câu độc lập → **Clone** (§5.5).
- Admin UI version history: `version_number`, instructor, publisher, thời gian publish, xem snapshot read-only.

### 5.3 Workflow & trạng thái câu hỏi

| Status | Ý nghĩa | Ai chuyển tới | Hiển thị học viên (Qbank) |
|--------|---------|---------------|---------------------------|
| `draft` | Nháp / đang soạn; Creator sửa tự do | Tạo mới; rút lại từ `in_review` / `in_flag_review`; sau reject | Không\* |
| `in_review` | Đã gửi, **chờ GV được gán** (lớp 1a) | Creator `submit` / gửi lại | Không\* |
| `in_flag_review` | GV đã duyệt, **chờ 2 reviewer gắn cờ** (lớp 1b) | Instructor accept | Không\* |
| `pending_publish` | Đủ 2 cờ xanh **hoặc** ≥1 cờ đỏ (chờ Admin) | Reviewer flag #2 hoặc cờ đỏ fail-fast | Không\* |
| `published` | Admin đã publish phiên bản | Admin `publish` | Có (theo gating) |
| `rejected` | Bị từ chối ở lớp 1a hoặc lớp 2; có `rejection_reason` | Instructor / Admin `reject` | Không\* |
| `private` | Ẩn khỏi ngân hàng câu hỏi (không hiện QBank / không lấy vào bài thi mới) | Admin | Không (Qbank) |
| `retired` | Ngừng dùng (giữ attempt) | Admin | Không |

\* **Ngoại lệ tái bản:** nếu câu đã từng publish (`published_version >= 1`), Qbank **vẫn phục vụ snapshot version đã publish** trong lúc working copy đi lại pipeline (`draft` / `in_review` / `in_flag_review` / `pending_publish` / `rejected`). Nội dung live **chỉ** đổi khi Admin publish lần mới (version +1). Câu chưa từng publish thì không lộ Qbank.

**Máy trạng thái (happy path + nhánh từ chối):**

```
                  submit       accept(GV)        2 xanh | ≥1 đỏ          publish
   draft ─────────────► in_review ──► in_flag_review ──► pending_publish ──► published
    ▲                    │                                  │
    │      reject(GV)    │                                  │ trả về (vận hành / cờ đỏ)
    │◄── rejected ◄──────┘                                  ▼
    │◄─────────────────────────────── rejected ◄────────────┘
    └─ Creator sửa (không +version) ─┘
```

- List admin: cột **Trạng thái** = xuất bản (đã XB / riêng tư / ngừng dùng). Cột **Bản gửi duyệt** = editor đã gửi bản cập nhật chưa + 2 cờ reviewer (trắng chờ / xanh đạt / đỏ không đạt).
- Reviewer chỉ **xanh / đỏ**; cờ đỏ **bắt buộc ghi chú**. ≥1 đỏ → fail-fast vào `pending_publish`, Admin **không publish**, phải trả editor.
- Một phiếu reject GV = fail ngay. Admin không phá thế cờ y khoa (không publish khi có đỏ).
- Từ `in_review` (GV chưa approve/reject): Creator **không sửa** nội dung nhưng **được withdraw** → `draft` rồi chỉnh và gửi lại. Withdraw xóa slot duyệt hiện tại.
- Từ `in_flag_review` trở đi (đã qua GV): Creator **không sửa** và **không rút nháp** — chờ reviewer / Admin.
- `/teach` ẩn phiếu của GV kia trước khi mình quyết định (tránh neo theo).
- Từ `pending_publish`: **không** cho Creator sửa trực tiếp — Admin trả về (`rejected`) vì lý do vận hành hoặc vì cờ đỏ.
- `retired` / `private`: chỉ Super Admin; không đi ngược về Creator trừ clone.

**Quyền (deny by default):**

| Actor | Role | Được làm |
|-------|------|----------|
| Content Creator | `content_editor` | CRUD working copy; `submit`; withdraw; clone; import draft; xử lý report (sửa) |
| Giảng viên | `instructor` | 1 phiếu `approve` / `reject` trên `/teach` (**không** publish, **không** +version); `pending_publish` chỉ khi đủ 2 accept khác người; 1 reject = fail ngay; xem lại đã duyệt / đã từ chối |
| Admin | `admin` | Xem + **publish / private / retire / xoá** (`question.publish`, `question.delete`); **đánh dấu QA duyệt** (`question.adjudicate`); **không** `question.create` / `question.update` / không duyệt thay GV |
| Super Admin | `super_admin` | Oversight + cùng quyền trạng thái/xoá/QA như Admin; **không** soạn/sửa nội dung (tránh xung đột biên tập); publish vẫn cần đã qua lớp GV + khác người duyệt |

Permissions: `question.create|update|delete|submit` (create/update chỉ `content_editor`), `question.review` (instructor), `question.publish` + `question.adjudicate` (`admin` + `super_admin`).

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
- Câu gắn **≥1 Bài học** (`question_lesson`); các bài ngang hàng, không phân biệt primary. Filter Qbank/bài thi: chọn Hệ cơ quan và/hoặc Môn học → gồm câu thuộc bài học gắn trực tiếp.

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
- **Đồng bộ Meilisearch** khi publish/retire (chỉ bản live trong ngân hàng; không index `private`/`retired`).
- **Stats:** correct rate thực nghiệm → gợi ý câu quá dễ/khó/mơ hồ.

## 6. Database
- `questions`:
  - `status` enum §5.3 (`draft` / `in_review` / `pending_publish` / `published` / `rejected` / `private` / `retired`)
  - `version` INT (denormalized = version đã publish gần nhất; 0 nếu chưa từng publish)
  - `published_version` INT null (trùng `version` khi đang live; dùng Qbank đọc snapshot)
  - `instructor_id` FK null (GV quyết định gần nhất), `publisher_id` FK null
  - `instructor_review_cycle` UINT default 0; `pipeline_reject_count` UINT default 0 (reset khi publish)
  - `instructor_1_id` / `instructor_1_decision`; `instructor_2_id` / `instructor_2_decision` (legacy)
  - `rejection_reason` TEXT null, `rejected_by_role` ENUM(`instructor`,`super_admin`) null
  - `is_priority` (Câu ưu tiên — chữa đề livestream), `cloned_from_id`, `cloned_from_version`, `created_by`, `updated_by`, timestamps
  - `content_fingerprint` CHAR(64) null + index; `similarity_checked_at` timestamp null
- `question_similarity_matches`: `question_id_low`, `question_id_high` (UUID, low < high), `score`, `severity`, `signals` JSON, `detected_at`; unique cặp
- `question_options`, `question_lesson` (`question_id` uuid, `lesson_id`), `question_tags`, `question_reports`
- `question_versions`: `question_id`, `version_number`, `snapshot` JSON (kèm `review_pipeline` khi publish), `created_by`, `event`, `created_at`; unique `(question_id, version_number)`; **chỉ tạo khi Super Admin publish**
- `question_instructor_reviews`: `question_id`, `review_cycle`, `instructor_id`, `decision` (approved/rejected), `note`, `content_fingerprint`, `reviewed_at`; unique `(question_id, review_cycle, instructor_id)`
- `question_reviewer_flags`: `question_id`, `review_cycle`, `reviewer_id`, `flag` (green/red), `note`, `content_fingerprint`, `reviewed_at`
- `question_workflow_events`: `question_id`, `review_cycle`, `event_type` (submit/admin_reject/publish), `actor_id`, `note`, `meta` JSON, `occurred_at`
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
| POST | `/api/v1/teach/questions/{id}/approve` | `{note?}` | `in_review` (1/2) hoặc `pending_publish` (2/2) | `question.review` |
| POST | `/api/v1/teach/questions/{id}/reject` | `{reason}` | `rejected` | `question.review` |
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
- **Content Editor:** CRUD working copy, submit/withdraw, clone, import draft. Không approve GV, không publish.
- **Instructor:** hàng đợi duyệt `/teach`; approve → `pending_publish` / reject. Không sửa nội dung (read-only + ghi chú), không publish.
- **Admin / Super Admin:** oversight list; publish / private / retire / xoá; **không** `question.create` / `question.update` (chỉ Content Editor soạn nội dung).
- **Super Admin:** publish vẫn cần đã qua lớp GV + khác người duyệt. Xem RBAC (`03-phan-quyen-rbac.md`).

## 10. Edge Cases
- Publish → snapshot version mới; session đang chạy giữ version cũ.
- Sửa working copy (kể cả sau reject) **không** +version cho đến khi SA publish.
- Câu đã live: learner luôn đọc snapshot `published_version` cho đến publish lần sau.
- Concurrent edit → 409; import lớn → chunk + queue; media chưa ready → chặn submit/publish.
- Instructor reject và SA reject đều về `rejected` nhưng `rejected_by_role` phân biệt để UI hướng dẫn Creator.
- Super Admin **không** bỏ qua lớp GV trên happy path; override khẩn cấp (nếu có) phải ghi audit + lý do.

## 11. Tracking (audit + product)
`question_create`, `question_update`, `question_submit`, `question_withdraw`, `question_instructor_approve`, `question_instructor_reject`, `question_publish`, `question_reject_publish`, `question_retire`, `question_import`, `question_export`, `report_resolve`, `question_preview`.

## 12. Responsive
- Desktop tối ưu (editor phức tạp); `/teach` review queue dùng được trên tablet; mobile hạn chế (duyệt nhanh, xử lý report).

## 13. Security
- RBAC nghiêm theo 2 lớp; sanitize rich content (XSS); audit; kiểm soát import; **Creator không tự publish**; **Instructor không tự publish**; portal tách `/admin` vs `/teach`.

## 14. Performance
- Import/sync qua queue; server pagination; version snapshot tránh query nặng; preview cache.
- **List analytics:** chỉ `stats_cache`; rollup job xử lý `question_attempts` nặng — không COUNT trên list.
- Hàng đợi GV / SA filter theo `status` + index `(status, updated_at)`.
- **Duplicate check:** trang chi tiết per-question (`/duplicates`); lưu/hiển thị cặp ≥30%; candidate theo fingerprint + stem bucket.

## 15. Đề xuất cải tiến
- AI hỗ trợ soạn; psychometrics; gán giảng viên theo Môn học/chuyên khoa; so sánh diff working copy vs version live; SLA nhắc duyệt.

## 16. Phạm vi triển khai (Phase 2a MVP → mở rộng)

**Đang ưu tiên sau Admin Phase 0–1.** Các hạng mục User CSKH nâng cao (impersonate, override Premium, bulk, CSV) **hoãn** — xem module 34 §16.

| Đợt | Phạm vi |
|-----|---------|
| **2a MVP** | Status `pending_publish`; Creator submit; Instructor approve/reject trên `/teach`; Super Admin publish (+version); RBAC tách `question.review` / `question.publish`; metadata instructor/publisher |
| **2b** | Report queue, preview học viên, version compare, rejection UI 2 nguồn, withdraw |
| **2c** | Import/export Excel+CSV (luôn `draft`, không publish); media picker (37); `SyncQuestionStatsJob`; gán GV theo Môn học |
