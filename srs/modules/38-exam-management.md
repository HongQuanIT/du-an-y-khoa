# Module 38 — Exam Management (Admin/Instructor)

**Nhóm:** Admin · **Ưu tiên:** Cao · **Phụ thuộc:** Exams (23), Question Mgmt (35), RBAC (39) · **Trạng thái:** ✅

> 🔵 Tính năng **giao đề cho lớp/tổ chức** (Assign panel, `/assign`, role Instructor/Org Admin, org scope) thuộc **Phase 2** (Module Organization 32 đã hoãn). Phạm vi hiện tại: tạo & quản lý đề, cấu hình, chấm, xuất kết quả toàn hệ thống.

## 0. Tóm tắt module
Tạo & quản lý đề thi/kỳ thi: **cấu hình phân bổ câu theo topic** (admin không chọn từng câu thủ công), hệ thống **tự động lấy câu exam pool** (`status=private`, `exam_flag=true`), cấu hình thời gian/điểm chuẩn/lịch, publish + **access control** (mua/đăng ký mới được làm), chấm & xuất kết quả.

> **Exam question pool:** Câu dành riêng cho exam — học viên **không** làm trước qua Qbank. Chỉ lộ khi có quyền làm exam đã publish.

| Route | Màn hình |
|-------|----------|
| `/admin/exams` | Danh sách đề |
| `/admin/exams/create` | Tạo đề (wizard: info → topic config → generate → publish) |
| `/admin/exams/{id}/edit` | Chỉnh sửa + re-generate preview |
| `/admin/exams/{id}/results` | Kết quả & thống kê |

## 1. Tổng quan
- **Mục đích:** Xây dựng đề thi chuẩn hóa & tổ chức kỳ thi.
- **Đến từ:** Admin dashboard, Organization (instructor giao đề).
- **Đi sang:** Exam học viên (23), Reports.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Exam table** | Filter type/status/lịch | List | Table |
| **Create wizard** | Thông tin → **topic configuration** (topic + số câu) → **auto-generate preview** → cấu hình (time, pass, access) → publish | `/create` | Stepper |
| **Topic config table** | Add/remove/reorder topic (**tree picker**, chọn cha hoặc leaf); cột `topic_id`, `question_count`; tổng số câu | Wizard/Edit | Editable table |
| **Generate preview** | Hiển thị câu đã chọn (read-only); warning nếu thiếu pool | Sau config | — |
| **Blueprint preview** | Phân bổ theo topic + eligibility count | Wizard | Chart |
| **Assign panel** | Giao lớp/tổ chức + deadline | Edit | — |
| **Results dashboard** | Điểm, phân phối, item analysis | `/results` | Chart/table |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `ExamWizard`, `ExamTopicConfigTable`, `ExamGeneratePreview`, `BlueprintPreview`, `AssignPanel`, `ResultsDashboard`, `ItemAnalysis`.

## 4. Luồng người dùng
```
Admin → tạo exam → cấu hình topics (Internal 10, Surgery 10, …) → Generate
→ hệ thống auto-select từ exam pool → preview → validate đủ câu → publish
→ học viên chỉ thấy/làm khi có entitlement (mua/đăng ký/cấp quyền) — backend enforce
→ làm bài → results + item analysis.
```

## 5. Business Logic

### 5.1 Exam question pool (nguồn câu riêng)
- Câu exam: `status = private` **và** `exam_flag = true`.
- **Không** xuất hiện trong Qbank/browse học viên (module 05).
- Content editor tạo câu exam qua Question Management (35) với flag tương ứng.

### 5.2 Topic configuration & auto-selection (admin KHÔNG chọn từng câu)
Bảng `exam_topics`: `exam_id`, `topic_id`, `question_count`, `sort_order`.

**Thuật toán generate** (cho mỗi `exam_topic`):
1. Resolve `topic_id` → tập topic IDs (**bao gồm descendants** nếu chọn topic cha).
2. Filter `questions` thuộc một trong các topic IDs (qua `question_topics`).
2. Filter `status = private` AND `exam_flag = true`.
3. Sort `created_at DESC`.
4. Take `question_count` câu mới nhất.
5. **Không** lấy câu từ topic khác khi thiếu.
6. **Không** duplicate câu trong cùng exam (unique `question_id` across all topics).

**Validation trước publish/generate:**
- Mỗi topic phải đủ eligible questions; nếu thiếu → **ERROR** rõ topic + số thiếu (vd: "Surgery requires 5 but only 3 available. Missing: 2").
- Không generate exam incomplete.
- Hiển thị eligible count realtime khi admin chỉnh `question_count`.

Kết quả generate lưu `exam_questions(exam_id, question_id, topic_id, sort_order)` — snapshot cố định cho exam đã publish; re-generate chỉ khi exam còn `draft`.

### 5.3 Publish & access control
1. Exam tạo → generate questions → admin hoàn thiện → publish.
2. Học viên Free/Premium **không** truy cập/làm trước khi có quyền (mua exam, subscription, grant).
3. **Backend** kiểm tra authorization mọi endpoint (`GET intro`, `POST start`, `POST submit`) — không chỉ ẩn UI.
4. Exam đã publish nhưng chưa mở bán → status/availability chặn start.

### 5.4 Khác
- **Snapshot đề** khi học viên bắt đầu attempt (công bằng, version câu tại thời điểm generate).
- **Cấu hình:** time, pass_score, số lần làm, `available_from/to`, hiển thị đáp án sau nộp.
- **Item analysis:** độ khó, discrimination → cải thiện ngân hàng.
- **Gating:** đề Premium/theo gói theo quyền (28/29).

## 6. Database
- `exams`: `id, uuid, title, description, type, duration_minutes, pass_score, status(draft/published/archived), access_type(free/premium/purchase/grant), pricing refs, available_from/to, is_premium, created_by, timestamps, soft delete`.
- `exam_topics`: `exam_id FK`, `topic_id FK`, `question_count INT`, `sort_order INT`; unique `(exam_id, topic_id)`.
- `exam_questions`: `exam_id FK`, `question_id FK`, `topic_id FK`, `sort_order INT`; unique `(exam_id, question_id)`.
- `exam_attempts`, liên kết `question_sessions` (mode=exam).
- Index: `questions(status, exam_flag, created_at)` cho pool query; `exam_topics(exam_id)`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/exams` | filter | list | `exam.manage` |
| POST | `/api/v1/admin/exams` | exam + exam_topics[] | draft | `exam.manage` |
| PUT | `/api/v1/admin/exams/{id}` | fields + exam_topics | exam | `exam.manage` |
| POST | `/api/v1/admin/exams/{id}/generate` | — | preview + validation errors | `exam.manage` |
| GET | `/api/v1/admin/exams/{id}/eligibility` | — | per-topic eligible counts | `exam.manage` |
| POST | `/api/v1/admin/exams/{id}/publish` | — | published (422 nếu thiếu câu) | `exam.manage` |
| GET | `/api/v1/admin/exams/{id}/results` | — | thống kê + item analysis | `exam.manage` |

## 8. State Management
- Wizard state; rule preview server tính count; results rollup cache; assign qua notification queue.

## 9. Phân quyền
- Admin (`exam.manage`), Instructor/Org Admin (đề lớp, giao bài trong org). Xem RBAC.

## 10. Edge Cases
- Topic thiếu eligible questions → **block generate/publish** + message per-topic (không fallback topic khác).
- Duplicate question across topics → validate trước khi lưu `exam_questions`.
- Sửa đề đã có attempt → không re-generate; snapshot bảo vệ attempt đang chạy.
- Đề hết hạn availability → chặn start.
- Concurrent edit exam config → 409.
- Publish khi pool thay đổi sau generate → yêu cầu re-generate hoặc validate lại eligibility.

## 11. Tracking (audit + product)
`exam_create`, `exam_publish`, `exam_assign`, `exam_results_view`, `item_analysis_view`.

## 12. Responsive
- Desktop tối ưu; mobile chủ yếu xem kết quả.

## 13. Security
- RBAC; snapshot đề; không lộ đáp án; audit; org scope cho instructor; bảo mật đề trước giờ thi (availability).

## 14. Performance
- Rule count server; snapshot; results rollup + item analysis async; chịu tải cao điểm.

## 15. Đề xuất cải tiến
- Đề adaptive (CAT); ngân hàng blueprint tái dùng; lịch thi đồng loạt + phòng thi ảo; proctoring; phân tích psychometric sâu.
