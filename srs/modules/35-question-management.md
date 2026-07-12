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
| **Workflow bar** | Draft → In review → Published → Retired | Editor | — |
| **Version history** | So sánh & rollback | Editor | Drawer |
| **Import wizard** | Upload → map cột → validate → preview → commit | `/import` | Stepper |
| **Reports queue** | Danh sách báo lỗi + xử lý | `/reports` | Table |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `QuestionEditor`(validate: đúng ≥1 đáp án, đủ giải thích), `OptionEditor`, `WorkflowStatusBar`, `VersionHistory`(diff), `ImportWizard`(map/validate), `ReportQueue`, `QuestionPreview`.

## 4. Luồng người dùng
```
Editor tạo câu → soạn + gắn chủ đề/media → preview → gửi duyệt (in_review)
Reviewer duyệt → publish → đồng bộ search → hiển thị cho học viên
Report từ user → queue → sửa (tạo version) → resolve → notify reporter.
Import: upload Excel → map cột → validate (báo dòng lỗi) → commit tạo draft hàng loạt.
```

## 5. Business Logic
- **Workflow & quyền:** editor soạn/gửi duyệt; reviewer/admin publish. Deny by default.
- **Versioning:** mỗi publish tạo version; review/session cũ dùng snapshot version.
- **Validation nội dung:** đúng ≥1 (single: đúng 1), giải thích bắt buộc, chủ đề ≥1.
- **Import:** map cột, validate, dedup, preview trước commit; rollback batch.
- **Report handling:** trạng thái open→reviewing→resolved/rejected; ảnh hưởng hiển thị (ẩn tạm nếu nghiêm trọng).
- **Retire** thay vì xóa cứng (giữ lịch sử attempt).
- **Đồng bộ Meilisearch** khi publish/retire (queue).
- **Stats:** correct rate thực nghiệm → gợi ý câu quá dễ/khó/mơ hồ.

## 6. Database
- `questions`, `question_options`, `question_topics`, `question_tag`, `question_reports`, `question_versions(question_id, version, snapshot JSON, created_by, created_at)`, `import_batches(id, file, status, stats)`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/questions` | filter | list | `question.view` |
| POST | `/api/v1/admin/questions` | question payload | draft | `question.create` |
| PUT | `/api/v1/admin/questions/{id}` | fields (+version) | question | `question.update` |
| POST | `/api/v1/admin/questions/{id}/publish` | — | published | `question.publish` |
| POST | `/api/v1/admin/questions/{id}/retire` | — | retired | `question.publish` |
| POST | `/api/v1/admin/questions/import` | file/map | batch | `question.create` |
| GET/POST | `/api/v1/admin/questions/reports` | — | queue/resolve | `question.update` |

Validation nghiêm; `409` version conflict; audit mọi mutate.

## 8. State Management
- Autosave draft; version optimistic lock; import async (queue) + progress; search sync async.

## 9. Phân quyền
- Content Editor: CRUD draft, gửi duyệt. Admin/Reviewer: publish/retire. Super Admin: full. Xem RBAC.

## 10. Edge Cases
- Publish câu đang có session → dùng snapshot cũ cho session đang chạy; concurrent edit → conflict 409; import file lớn → chunk + queue; ảnh media chưa ready → chặn publish; đáp án đúng thiếu → validate.

## 11. Tracking (audit + product)
`question_create`, `question_update`, `question_publish`, `question_retire`, `question_import`, `report_resolve`, `question_preview`.

## 12. Responsive
- Desktop tối ưu (editor phức tạp); mobile hạn chế (xem/duyệt nhanh, xử lý report).

## 13. Security
- RBAC nghiêm; sanitize rich content (XSS trong stem/explanation); audit; kiểm soát import (mime, size, injection CSV); không để editor tự publish nếu chính sách yêu cầu duyệt.

## 14. Performance
- Import/sync qua queue; server pagination; version snapshot tránh query nặng; preview cache.

## 15. Đề xuất cải tiến
- AI hỗ trợ soạn (gợi ý distractor, kiểm tra chất lượng, phát hiện trùng); psychometrics (độ phân biệt, IRT); quy trình duyệt nhiều cấp; gắn nguồn tự động; phát hiện câu mơ hồ theo dữ liệu.
