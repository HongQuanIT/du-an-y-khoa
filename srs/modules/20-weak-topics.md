# Module 20 — Weak Topics

**Nhóm:** Personal · **Ưu tiên:** Cao · **Phụ thuộc:** Analytics (19), Qbank (05), Study Plan (04) · **Trạng thái:** ✅

## 0. Tóm tắt module
Phát hiện & liệt kê chủ đề yếu, ưu tiên ôn tập, tạo session luyện đúng điểm yếu bằng 1 chạm. Là "động cơ cá nhân hóa" của nền tảng.

| Route | Màn hình |
|-------|----------|
| `/weak-topics` | Danh sách chủ đề yếu |
| Widget | Dashboard |

## 1. Tổng quan
- **Mục đích:** Tập trung nỗ lực vào nơi hiệu quả nhất.
- **Đến từ:** Dashboard widget, Analytics, sau Review.
- **Đi sang:** Qbank/Session (pre-filtered), Library (đọc bù), Flashcards.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Weak topic list** | Chủ đề + correct rate + mastery + trend + số câu chưa làm | `/weak-topics` | List/table |
| **Priority sort** | Sắp theo mức yếu/độ high-yield | Luôn | — |
| **Practice CTA** | "Luyện ngay" tạo session | Mỗi item | Sticky mobile |
| **Filter** | Theo chuyên ngành/hệ | Luôn | Chips |
| **Recommended reading** | Bài Library cho chủ đề | Item expand | — |
| **Empty/Loading/Error/Paywall** | Chưa đủ dữ liệu; skeleton | Theo trạng thái | — |

## 3. Phân tích Component
- `WeakTopicRow` (props topic{rate,mastery,trend,unseen}; events onPractice/onRead), `PrioritySorter`, `TopicTrendMini`.

## 4. Luồng người dùng
```
Dashboard widget "3 chủ đề yếu" → /weak-topics → "Luyện ngay" Dược → Session lọc incorrect+unseen(Dược)
 → Review → mastery cập nhật → chủ đề rời khỏi danh sách khi đạt ngưỡng.
```

## 5. Business Logic
- **Xác định weak:** `topic_mastery` với `correct_rate < ngưỡng` và/hoặc `mastery_level` thấp, cần `attempts ≥ min` (tránh nhiễu).
- **Priority score:** kết hợp mức yếu × high-yield × khối lượng còn lại × gần ngày thi.
- **Practice preset:** tạo session filter `topic=X, status in (incorrect,omitted,unseen)`.
- **Decay:** không hoạt động lâu → coi là "cần ôn lại".
- **Gating:** Free xem top N; Premium đầy đủ + đề xuất reading/AI.

## 6. Database
- Đọc `topic_mastery` (cập nhật qua job sau mỗi finish), `question_status`, `content_links` (reading).

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/weak-topics?filter=` | list + priority | Auth |
| POST | `/api/v1/weak-topics/{topicId}/practice` | session | Auth (gated) |
| GET | `/api/v1/weak-topics/{topicId}/reading` | bài đề xuất | Auth |

## 8. State Management
- Server: mastery rollup cache; recompute job. Client: filter/sort. Không realtime.

## 9. Phân quyền
- Student top N; Premium full + reading/AI. Instructor xem weak topics tổng hợp lớp.

## 10. Edge Cases
- Chưa đủ attempt → "làm thêm để phát hiện điểm yếu"; tất cả tốt → empty tích cực; chủ đề bị gỡ → ẩn; subscription hết → giới hạn top N.

## 11. Tracking
`weak_topic_open`, `weak_topic_practice`, `weak_topic_read`, `weak_topic_filter`.

## 12. Responsive
- Desktop: table + trend. Mobile: card, CTA sticky, expand để xem reading.

## 13. Security
- Scope owner; đề xuất không lộ dữ liệu người khác.

## 14. Performance
- Đọc rollup cache; recompute async; index `(user_id, mastery_level)`.

## 15. Đề xuất cải tiến
- "Fix my weakness" 1 chạm tạo lộ trình mini; AI giải thích tại sao yếu + kế hoạch; nhắc nhở định kỳ; so sánh điểm yếu với cohort.
