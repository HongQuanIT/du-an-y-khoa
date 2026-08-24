# Module 35 — Question Management (Admin/Editor)

**Nhóm:** Admin · **Ưu tiên:** Rất cao (chất lượng nội dung) · **Phụ thuộc:** Qbank (05), Media (37), RBAC (39), Audit (40), Search (25) · **Trạng thái:** ✅

## 0. Tóm tắt module
CRUD & workflow duyệt câu hỏi: soạn stem/đáp án/giải thích, gắn chủ đề/tag/media, versioning, import (Excel/CSV/PDF), xử lý report, thống kê chất lượng. Là công cụ chính của Content Editor.

| Route | Màn hình |
|-------|----------|
| `/admin/questions` | Danh sách + filter + trạng thái |
| `/admin/questions/create` | Soạn câu hỏi |
| `/admin/questions/{id}/edit` | Chỉnh sửa (versioning) |
| `/admin/questions/import` | Import hàng loạt |
| `/admin/questions/reports` | Xử lý báo lỗi câu hỏi |

## 1. Tổng quan
- **Mục đích:** Sản xuất & duy trì ngân hàng câu hỏi chất lượng.
- **Đến từ:** Admin dashboard, report từ người dùng.
- **Đi sang:** Preview (như học viên), media, cross-link.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Question table** | Filter (status/topic/difficulty/report), search, sort | List | Table |
| **Question editor** | Rich editor stem, options (đánh dấu đúng + giải thích từng đáp án), giải thích chung, references, lab values, media, topics/tags | Create/edit | Form nhiều section |
| **Preview pane** | Xem như học viên (study/exam) | Editor | Split |
| **Workflow bar** | Draft → In review → Published / Rejected / Private | Editor | — |
| **Metadata panel** | Creator, Reviewer, Created at, Updated at | Editor/Detail | Sidebar |
| **Analytics panel** | Attempts (study/exam), correct rate, reports — đọc `stats_cache` | Detail | Tab |
| **Version history** | Sort theo version_number; reviewer, thời gian duyệt; xem snapshot (read-only) | Editor | Drawer |
| **Clone action** | Nhân bản → câu mới `draft` | Detail/List | Button |
| **Import wizard** | Upload → map cột → validate → preview → commit | `/import` | Stepper |
| **Reports queue** | Danh sách báo lỗi + xử lý | `/reports` | Table |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `QuestionEditor`(validate: đúng ≥1 đáp án, đủ giải thích), `OptionEditor`, `TopicTreePicker`, `WorkflowStatusBar`, `VersionHistory`(read-only snapshots), `CloneQuestionAction`, `ImportWizard`(map/validate), `ReportQueue`, `QuestionPreview`.

## 4. Luồng người dùng
```
Editor tạo câu → soạn + gắn chủ đề/media → preview → gửi duyệt (in_review)
Reviewer duyệt → publish → đồng bộ search → hiển thị cho học viên
Report từ user → queue → sửa (working copy) → gửi duyệt lại → approve (tạo version mới) → resolve → notify reporter.
Clone: nhân bản câu hiện tại (hoặc từ snapshot version) → câu mới `draft`, không ảnh hưởng câu gốc.
Import: upload Excel → map cột → validate (báo dòng lỗi) → commit tạo draft hàng loạt.
```

## 5. Business Logic

### 5.1 Metadata & audit (bắt buộc hiển thị Admin UI)
Mỗi câu hỏi lưu và hiển thị rõ:
| Field | Ý nghĩa | UI |
|-------|---------|-----|
| `created_by` (creator_id) | Người tạo | Basic info |
| `reviewer_id` | Người kiểm duyệt (gán khi duyệt/từ chối) | Basic info |
| `created_at` | Thời gian tạo | Basic info |
| `updated_at` | Cập nhật gần nhất | Basic info |

### 5.2 Versioning (mỗi lần **duyệt/approve** → version mới)
- Chỉnh sửa trong `draft` / `in_review`: cập nhật **working copy** trên `questions` (+ options…) — **không** tạo version.
- **Mỗi lần reviewer approve/publish** (hoặc duyệt lại sau chỉnh sửa) → tạo **một version mới**, **không overwrite** version cũ.
- `version_number` tăng dần (1, 2, 3…); version mới nhất = `MAX(version_number)`.
- Mỗi `question_versions` lưu: `version_number`, `reviewer_id` (người duyệt), `created_at`, **snapshot JSON** nội dung tại thời điểm duyệt.
- Session/review đang chạy dùng snapshot version lúc làm; sort/display version theo `version_number DESC`.
- **Không có re-generate / rollback in-place** trên câu hiện tại. Muốn bản sao độc lập → **Clone** (§5.5).
- Admin UI: version history (reviewer, thời gian duyệt, xem snapshot read-only).

### 5.3 Workflow & trạng thái câu hỏi
| Status | Ý nghĩa | Hiển thị học viên (Qbank) |
|--------|---------|---------------------------|
| `draft` | Mới tạo, chưa gửi duyệt | Không |
| `in_review` | Đã gửi, chờ reviewer | Không |
| `published` | Đã duyệt, public | Có (theo gating) |
| `rejected` | Bị từ chối; lưu `rejection_reason` | Không; UI hiển thị rõ Rejected |
| `private` | Chưa public; dùng cho exam (`exam_flag=true`) | Không (Qbank) |
| `retired` | Ngừng dùng (giữ lịch sử attempt) | Không |

Luồng: `draft` → gửi duyệt → `in_review` → reviewer **approve** → `published` **hoặc** **reject** → `rejected` (kèm lý do) → sửa → gửi lại `in_review` → approve lại → **version +1**.
Câu `private` + `exam_flag=true`: chỉ pool exam (module 38), không lộ Qbank.

**Quyền:** editor soạn/gửi duyệt; reviewer/admin publish/reject. Deny by default.

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

Tránh N+1: eager load creator/reviewer trên list; **không** join/count `question_attempts` trên list.

### 5.5 Clone (thay cho re-generate câu hỏi)
- **Không** có thao tác re-generate / áp dụng lại snapshot lên câu đang tồn tại.
- **Clone:** tạo `question` **mới** (`draft`), copy nội dung từ câu gốc hoặc từ snapshot version (`cloned_from_id`, `cloned_from_version` optional).
- Câu gốc và attempt/history giữ nguyên; câu clone có lifecycle riêng.

### 5.6 Topic (chủ đề phân cấp cha–con)
- `topics.parent_id` nullable → cây phân cấp (specialty → system → subtopic).
- Admin UI editor: chọn topic dạng **tree picker** (cha/con).
- Câu gắn ≥1 topic (khuyến nghị leaf); filter Qbank/exam pool: chọn topic **cha** → bao gồm câu thuộc **topic con** (descendants).

### 5.7 Khác
- **Validation nội dung:** đúng ≥1 (single: đúng 1), giải thích bắt buộc, chủ đề ≥1.
- **Import:** map cột, validate, dedup, preview trước commit; rollback batch.
- **Report handling:** open→reviewing→resolved/rejected; ảnh hưởng hiển thị (ẩn tạm nếu nghiêm trọng).
- **Retire** thay vì xóa cứng (giữ lịch sử attempt).
- **Đồng bộ Meilisearch** khi publish/retire (chỉ `published`; không index `private` exam pool).
- **Stats:** correct rate thực nghiệm → gợi ý câu quá dễ/khó/mơ hồ.

## 6. Database
- `questions`: `reviewer_id FK null`, `rejection_reason TEXT null`, `exam_flag BOOL default false`, `cloned_from_id FK null`, `cloned_from_version INT null`; `status` enum (§5.3); `created_by`, `updated_by`, timestamps.
- `question_options`, `question_topics`, `question_tag`, `question_reports`.
- `question_versions`: `question_id`, `version_number INT`, `reviewer_id FK`, `snapshot JSON`, `created_at`; unique `(question_id, version_number)`; tạo khi **approve/publish**.
- `topics`: `parent_id FK null` — cây phân cấp; index `parent_id`.
- `import_batches(id, file, status, stats)`.
- `stats_cache JSON` + `stats_updated_at` trên `questions` — **nguồn đọc duy nhất** cho list analytics; cập nhật bởi rollup job.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/questions` | filter | list | `question.view` |
| POST | `/api/v1/admin/questions` | question payload | draft | `question.create` |
| PUT | `/api/v1/admin/questions/{id}` | fields (+version) | question | `question.update` |
| POST | `/api/v1/admin/questions/{id}/publish` | — | published (+ tạo version) | `question.publish` |
| POST | `/api/v1/admin/questions/{id}/reject` | `{reason}` | rejected | `question.publish` |
| POST | `/api/v1/admin/questions/{id}/clone` | `{from_version?}` | draft (câu mới) | `question.create` |
| POST | `/api/v1/admin/questions/{id}/retire` | — | retired | `question.publish` |
| POST | `/api/v1/admin/questions/import` | file/map | batch | `question.create` |
| GET/POST | `/api/v1/admin/questions/reports` | — | queue/resolve | `question.update` |

Validation nghiêm; `409` version conflict; audit mọi mutate.

## 8. State Management
- Autosave draft; version optimistic lock; import async (queue) + progress; search sync async.

## 9. Phân quyền
- Content Editor: CRUD draft, gửi duyệt. Admin/Reviewer: publish/retire. Super Admin: full. Xem RBAC.

## 10. Edge Cases
- Publish/approve → snapshot version mới; session đang chạy giữ version cũ.
- Chỉnh sửa trong `in_review` không tạo version cho đến khi approve.
- Không rollback in-place — muốn nội dung cũ → **clone** từ version snapshot.
- Concurrent edit → conflict 409; import file lớn → chunk + queue; ảnh media chưa ready → chặn publish.

## 11. Tracking (audit + product)
`question_create`, `question_update`, `question_publish`, `question_retire`, `question_import`, `report_resolve`, `question_preview`.

## 12. Responsive
- Desktop tối ưu (editor phức tạp); mobile hạn chế (xem/duyệt nhanh, xử lý report).

## 13. Security
- RBAC nghiêm; sanitize rich content (XSS trong stem/explanation); audit; kiểm soát import (mime, size, injection CSV); không để editor tự publish nếu chính sách yêu cầu duyệt.

## 14. Performance
- Import/sync qua queue; server pagination; version snapshot tránh query nặng; preview cache.
- **List analytics:** chỉ `stats_cache`; rollup job xử lý `question_attempts` nặng — không COUNT trên list.

## 15. Đề xuất cải tiến
- AI hỗ trợ soạn (gợi ý distractor, kiểm tra chất lượng, phát hiện trùng); psychometrics (độ phân biệt, IRT); quy trình duyệt nhiều cấp; gắn nguồn tự động; phát hiện câu mơ hồ theo dữ liệu.

## 16. Phạm vi triển khai (Phase 2a MVP → mở rộng)

**Đang ưu tiên sau Admin Phase 0–1.** Các hạng mục User CSKH nâng cao (impersonate, override Premium, bulk, CSV) **hoãn** — xem module 34 §16.

| Đợt | Phạm vi |
|-----|---------|
| **2a MVP** | List + filter (đọc `stats_cache`); create/edit; metadata; workflow status §5.3; **version khi approve**; clone; topic tree picker; RBAC + audit |
| **2b** | Report queue, preview học viên, version compare read-only, rejection reason UI |
| **2c** | Import batch, media picker (37), `SyncQuestionStatsJob` rollup, psychometrics gợi ý |
