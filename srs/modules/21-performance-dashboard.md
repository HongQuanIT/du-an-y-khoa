# Module 21 — Performance Dashboard

**Nhóm:** Personal · **Ưu tiên:** Trung bình · **Phụ thuộc:** Analytics (19), Exams (23), Weak Topics (20) · **Trạng thái:** ✅

## 0. Tóm tắt module
Bảng điều khiển hiệu suất chuyên sâu hướng "sẵn sàng thi": điểm dự báo, mức độ sẵn sàng theo chủ đề, so sánh mục tiêu, lịch sử điểm exam. Khác Analytics (19) ở chỗ tập trung "readiness for exam".

| Route | Màn hình |
|-------|----------|
| `/performance` | Bảng hiệu suất |

## 1. Tổng quan
- **Mục đích:** Trả lời "Tôi đã sẵn sàng thi chưa?".
- **Đến từ:** Dashboard, Analytics, sau Exam.
- **Đi sang:** Weak Topics, Study Plan (điều chỉnh), Exams.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Readiness gauge** | Chỉ số sẵn sàng tổng (0–100) | Luôn | Gauge |
| **Predicted score card** | Điểm dự báo + khoảng tin cậy | Premium | — |
| **Topic readiness grid** | Mức sẵn sàng từng chủ đề (màu) | Luôn | Grid |
| **Exam score history** | Line chart điểm qua các exam | Luôn | Chart |
| **Goal vs actual** | So mục tiêu ngày thi | Luôn | — |
| **Recommendations** | Hành động ưu tiên | Luôn | List |
| **Empty/Loading/Error/Paywall** | Chưa đủ dữ liệu | Theo trạng thái | — |

## 3. Phân tích Component
- `ReadinessGauge`, `PredictedScoreCard`(Premium), `TopicReadinessGrid`, `ExamScoreTrend`, `GoalProgress`, `ActionList`.

## 4. Luồng người dùng
```
/performance → readiness 62/100 → xem chủ đề đỏ → hành động đề xuất → luyện/đọc
Sau exam mới → điểm cập nhật → dự báo điều chỉnh.
```

## 5. Business Logic
- **Readiness score:** hàm tổng hợp mastery các chủ đề (trọng số high-yield) + độ phủ (đã làm bao nhiêu %) + xu hướng + kết quả exam gần đây.
- **Predicted score:** mô hình hồi quy/heuristic từ correct rate + độ khó + phủ; khoảng tin cậy.
- **Topic readiness:** mastery + coverage per topic.
- **Gating:** predicted score + dự báo = Premium.

## 6. Database
- Đọc `topic_mastery`, `daily_stats`, `exam_attempts`, `question_status` (coverage).

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/performance` | readiness + topic grid + goal | Auth |
| GET | `/api/v1/performance/predicted` | điểm dự báo | Premium |
| GET | `/api/v1/performance/exam-history` | lịch sử điểm | Auth |

## 8. State Management
- Rollup cache; recompute sau exam/finish. Client tab/range. Không realtime.

## 9. Phân quyền
- Student cơ bản; Premium: predicted/dự báo. Instructor: readiness tổng hợp lớp.

## 10. Edge Cases
- Chưa có exam → ẩn history; coverage thấp → cảnh báo "dự báo kém tin cậy"; subscription hết → ẩn predicted.

## 11. Tracking
`performance_open`, `readiness_view`, `predicted_view`, `performance_action_click`.

## 12. Responsive
- Desktop: gauge + grid + chart. Mobile: gauge trên, grid cuộn, chart full.

## 13. Security
- Scope owner; dự báo không dùng dữ liệu định danh người khác.

## 14. Performance
- Cache rollup; downsample chart; recompute async.

## 15. Đề xuất cải tiến
- Mô phỏng "nếu học thêm X giờ → readiness tăng bao nhiêu"; cảnh báo sớm nguy cơ trượt; tích hợp lịch nhắc.
