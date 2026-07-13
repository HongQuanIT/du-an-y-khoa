# Module 23 — Exams (Mô phỏng thi)

**Nhóm:** Exam · **Ưu tiên:** Cao · **Phụ thuộc:** Session (06), Review (07), Exam Management (38), Performance (21) · **Trạng thái:** ✅

## 0. Tóm tắt module
Mô phỏng kỳ thi thật: đề cố định/ngẫu nhiên theo rule, giới hạn thời gian, giao diện giống phòng thi, chấm & xếp hạng sau nộp. Dùng lại Session engine ở "exam mode" nhưng có lớp Exam (đề mẫu, lịch, điểm chuẩn, percentile).

> 🔵 Các phần gắn **tổ chức** (đề tổ chức, giám sát/proctoring theo org) thuộc **Phase 2** (Module Organization 32 đã hoãn).

| Route | Màn hình |
|-------|----------|
| `/exams` | Danh sách đề thi |
| `/exams/{id}` | Chi tiết đề (intro + bắt đầu) |
| `/exams/{id}/take` | Phòng thi (session exam) |
| `/exams/{id}/result/{attemptId}` | Kết quả + review |

## 1. Tổng quan
- **Mục đích:** Đánh giá năng lực trong điều kiện thi thật.
- **Đến từ:** Sidebar, Dashboard, Study Plan, Org (giao đề).
- **Đi sang:** Review (07), Performance (21), leaderboard.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Exam list** | Đề available + trạng thái (mới/đã làm) + độ khó | `/exams` | Grid/list |
| **Exam intro** | Mô tả, số câu, thời gian, điều kiện, nút "Bắt đầu" | `/exams/{id}` | — |
| **Pre-exam checklist** | Xác nhận sẵn sàng, kiểm tra kết nối | Trước khi vào | Modal |
| **Exam room** | Player (module 06 exam mode) + timer nổi bật + navigator | `/take` | Full-screen |
| **Submit modal** | Xác nhận nộp (còn câu chưa làm) | Khi nộp/hết giờ | — |
| **Result page** | Điểm, pass/fail, percentile, breakdown, review | `/result` | Chart |
| **Leaderboard** | Xếp hạng (nếu bật) | Result | Ẩn danh |
| **Paywall/Empty/Loading/Error** | Đề Premium; chưa có đề | Theo trạng thái | — |

## 3. Phân tích Component
- Tái dùng `QuestionPlayer` (exam mode) + `ExamIntro`, `PreExamChecklist`, `ExamTimer(prominent)`, `SubmitConfirmModal`, `ExamResult`, `Leaderboard`.
- `ExamTimer`: authoritative server; cảnh báo mốc; auto-submit khi hết.

## 4. Luồng người dùng
```
/exams → chọn đề → intro → checklist → /take (timer chạy) → làm bài (không giải thích)
 → nộp/hết giờ → chấm → result (điểm, percentile) → review từng câu → Performance cập nhật
Ngoại lệ: mất mạng → resume trong thời gian còn lại; hết giờ khi offline → server auto-submit.
```

## 5. Business Logic
- **Đề:** `question_ids` cố định hoặc `rule` (random theo tiêu chí) — snapshot khi bắt đầu để công bằng.
- **Timer server-side**; auto-submit; chống mở lại sau nộp.
- **Chấm:** như Session; tính `score`, `pass` theo `pass_score`.
- **Percentile:** so các attempt của đề (cohort).
- **Số lần làm:** giới hạn/không theo cấu hình đề.
- **Availability window:** `available_from/to` (đề tổ chức).
- **Gating:** đề Premium/đề tổ chức theo quyền.
- **Anti-cheat:** không lộ đáp án; phát hiện rời tab (tùy chọn); 1 attempt/đề đang mở.

## 6. Database
- `exams`, `exam_attempts`, dùng `question_sessions`(mode=exam, exam_id) + `question_attempts`. (mục 8 data model)

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/exams` | filter | list | Auth (gated) |
| GET | `/api/v1/exams/{id}` | — | intro | Auth |
| POST | `/api/v1/exams/{id}/start` | Idempotency-Key | exam_attempt + session | Auth (gated) |
| POST | `/api/v1/exams/{id}/submit` | — | result | Owner attempt |
| GET | `/api/v1/exams/{id}/result/{attemptId}` | — | điểm + review | Owner |
| GET | `/api/v1/exams/{id}/leaderboard` | — | ẩn danh | Auth |

## 8. State Management
- Session engine (module 06); timer server; result cache bất biến; leaderboard cache; realtime (org giám sát exam qua Reverb tùy chọn).

## 9. Phân quyền
- Student/Premium theo gating đề. Instructor/Org tạo & giao đề (38/32), xem kết quả học viên. Admin quản lý.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Mất mạng giữa thi | Autosave, resume trong thời gian còn lại; server auto-submit khi hết |
| Refresh/đóng tab | Khôi phục phòng thi; timer tiếp tục theo server |
| Hết giờ | Auto-submit + chấm phần đã làm |
| Bắt đầu 2 lần | Idempotency → 1 attempt |
| Đề hết hạn availability | Chặn bắt đầu |
| Subscription hết giữa thi | Cho hoàn thành attempt hiện tại |

## 11. Tracking
`exam_open`, `exam_start`, `question_answer(exam)`, `exam_submit`, `exam_finish`, `exam_result_view`, `exam_review_open`, `leaderboard_view`.

## 12. Responsive
- Desktop: phòng thi rộng + navigator + timer. Mobile: full-screen, timer header, navigator drawer, cảnh báo xoay ngang cho câu nhiều dữ kiện.

## 13. Security
- Timer & chấm server; không lộ đáp án; snapshot đề; chống nhiều thiết bị cùng attempt; audit start/submit; đề tổ chức bảo mật.

## 14. Performance
- Prefetch câu; snapshot đề tránh query; result cache; leaderboard cache/rollup; chịu tải cao điểm (mùa thi) → queue chấm nếu cần.

## 15. Đề xuất cải tiến
- Chế độ thi giống chuẩn thật (block timing, nghỉ giữa block); proctoring nhẹ (webcam tùy chọn org); phân tích timing per câu; đề adaptive (CAT).
