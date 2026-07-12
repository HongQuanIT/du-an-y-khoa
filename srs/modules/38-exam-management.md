# Module 38 — Exam Management (Admin/Instructor)

**Nhóm:** Admin · **Ưu tiên:** Cao · **Phụ thuộc:** Exams (23), Question Mgmt (35), Organization (32), RBAC (39) · **Trạng thái:** ✅

## 0. Tóm tắt module
Tạo & quản lý đề thi/kỳ thi: chọn câu (thủ công hoặc rule ngẫu nhiên theo tiêu chí), cấu hình thời gian/điểm chuẩn/lịch, giao cho lớp/tổ chức, theo dõi & chấm, xuất kết quả.

| Route | Màn hình |
|-------|----------|
| `/admin/exams` | Danh sách đề |
| `/admin/exams/create` | Tạo đề (wizard) |
| `/admin/exams/{id}/edit` | Chỉnh sửa |
| `/admin/exams/{id}/results` | Kết quả & thống kê |

## 1. Tổng quan
- **Mục đích:** Xây dựng đề thi chuẩn hóa & tổ chức kỳ thi.
- **Đến từ:** Admin dashboard, Organization (instructor giao đề).
- **Đi sang:** Exam học viên (23), Reports.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Exam table** | Filter type/status/lịch | List | Table |
| **Create wizard** | Thông tin → chọn câu (thủ công/rule) → cấu hình (time, pass, số lần) → lịch → xuất bản | `/create` | Stepper |
| **Question picker** | Tìm & chọn câu / định nghĩa rule (chủ đề, độ khó, số câu) | Wizard | Split |
| **Blueprint preview** | Phân bổ câu theo chủ đề/độ khó | Wizard | Chart |
| **Assign panel** | Giao lớp/tổ chức + deadline | Edit | — |
| **Results dashboard** | Điểm, phân phối, item analysis | `/results` | Chart/table |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `ExamWizard`, `QuestionPicker`(manual/rule), `BlueprintPreview`, `AssignPanel`, `ResultsDashboard`, `ItemAnalysis`.

## 4. Luồng người dùng
```
Admin → tạo đề mock → rule: 100 câu (mọi chủ đề, độ khó cân bằng) → time 120' → publish
Instructor → tạo đề lớp → giao cho lớp A + deadline → học viên làm → xem kết quả + item analysis.
```

## 5. Business Logic
- **Rule-based selection:** blueprint (số câu theo chủ đề/độ khó); random hóa mỗi attempt hoặc cố định.
- **Snapshot đề** khi học viên bắt đầu (công bằng).
- **Cấu hình:** time, pass_score, số lần làm, availability window, hiển thị đáp án sau nộp hay không.
- **Assign** tới lớp/tổ chức (32) → tạo notification + task.
- **Item analysis:** độ khó, độ phân biệt → cải thiện ngân hàng.
- **Gating:** đề Premium/tổ chức theo quyền.

## 6. Database
- `exams` (mục 8 data model, rule JSON/question_ids), `exam_attempts`, liên kết `assignments` (module 32).

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/exams` | filter | list | `exam.manage` |
| POST | `/api/v1/admin/exams` | exam payload/rule | draft | `exam.manage` |
| PUT | `/api/v1/admin/exams/{id}` | fields | exam | `exam.manage` |
| POST | `/api/v1/admin/exams/{id}/publish` | — | published | `exam.manage` |
| POST | `/api/v1/admin/exams/{id}/assign` | `{class_ids, due}` | ok | Instructor/Org Admin |
| GET | `/api/v1/admin/exams/{id}/results` | — | thống kê + item analysis | `exam.manage` |

## 8. State Management
- Wizard state; rule preview server tính count; results rollup cache; assign qua notification queue.

## 9. Phân quyền
- Admin (`exam.manage`), Instructor/Org Admin (đề lớp, giao bài trong org). Xem RBAC.

## 10. Edge Cases
- Rule ra thiếu câu → cảnh báo; sửa đề đang có attempt → snapshot bảo vệ attempt đang chạy; đề hết hạn → chặn start; concurrent edit → 409; publish khi câu chưa đủ → validate.

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
