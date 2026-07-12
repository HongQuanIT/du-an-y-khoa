# Module 24 — Self Assessment

**Nhóm:** Exam · **Ưu tiên:** Trung bình · **Phụ thuộc:** Exams (23), Performance (21), Study Plan (04) · **Trạng thái:** ✅

## 0. Tóm tắt module
Bài tự đánh giá năng lực (chẩn đoán đầu vào / kiểm tra định kỳ) để xác định trình độ, tạo baseline cho Study Plan & dự báo. Là dạng đặc biệt của Exam (`type=self_assessment`) nhấn mạnh phân tích chẩn đoán hơn là điểm số.

| Route | Màn hình |
|-------|----------|
| `/self-assessment` | Danh sách/khởi động |
| `/self-assessment/{id}/take` | Làm bài |
| `/self-assessment/{id}/result` | Kết quả chẩn đoán |

## 1. Tổng quan
- **Mục đích:** Xác định điểm mạnh/yếu ban đầu, hiệu chỉnh lộ trình.
- **Đến từ:** Onboarding, Dashboard (định kỳ), Study Plan.
- **Đi sang:** Study Plan (tạo/điều chỉnh), Weak Topics, Performance.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Intro** | Giải thích mục đích (không áp lực điểm) | `/self-assessment` | — |
| **Assessment player** | Làm bài (exam-like, không giải thích) | `/take` | Full |
| **Diagnostic result** | Bản đồ năng lực theo chủ đề + đề xuất lộ trình | `/result` | Chart/radar |
| **CTA** | "Tạo Study Plan từ kết quả" | Result | Sticky |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- Tái dùng `QuestionPlayer` (exam mode) + `DiagnosticResult` (radar chart năng lực), `PlanFromResultButton`.

## 4. Luồng người dùng
```
Onboarding → self-assessment ngắn → result: bản đồ năng lực → "Tạo Study Plan" → plan cá nhân hóa
Định kỳ (vd mỗi 2 tuần) → làm lại → so tiến bộ → điều chỉnh plan.
```

## 5. Business Logic
- **Chọn câu đại diện** phủ nhiều chủ đề (balanced) để chẩn đoán nhanh.
- **Kết quả → baseline mastery** ghi vào `topic_mastery` (weight khởi tạo).
- **Sinh Study Plan** từ kết quả (ưu tiên chủ đề yếu).
- **So sánh theo thời gian** (các lần assessment).
- Ít nhấn mạnh pass/fail; nhấn phân tích.

## 6. Database
- `exams(type=self_assessment)`, `exam_attempts`; ghi baseline `topic_mastery`.

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/self-assessment` | list/khởi động | Auth |
| POST | `/api/v1/self-assessment/{id}/start` | attempt+session | Auth |
| POST | `/api/v1/self-assessment/{id}/submit` | diagnostic result | Owner |
| POST | `/api/v1/self-assessment/{id}/to-study-plan` | study plan | Owner |

## 8. State Management
- Session engine; result cache; ghi baseline async; không realtime.

## 9. Phân quyền
- Mọi Student (kể cả Free — vì là công cụ dẫn dắt). Premium: assessment sâu hơn + dự báo.

## 10. Edge Cases
- Bỏ dở → lưu, cho tiếp tục; làm lại nhiều lần → lưu lịch sử để so sánh; dữ liệu ít → chẩn đoán "sơ bộ".

## 11. Tracking
`self_assessment_start`, `self_assessment_finish`, `diagnostic_result_view`, `plan_from_assessment`.

## 12. Responsive
- Như Exam; result radar chart mobile cuộn.

## 13. Security
- Như Exam (server chấm, không lộ đáp án); scope owner.

## 14. Performance
- Bài ngắn; result cache; baseline ghi async.

## 15. Đề xuất cải tiến
- Adaptive diagnostic (CAT) rút ngắn số câu; so sánh tiến bộ theo thời gian trực quan; gợi ý mục tiêu thực tế theo baseline.
