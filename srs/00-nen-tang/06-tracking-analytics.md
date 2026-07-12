# 06 — Tracking & Analytics

> Quy ước sự kiện (event) và pipeline phân tích dùng chung. Mỗi module liệt kê thêm event riêng, tham chiếu format tại đây.

## 1. Nguyên tắc

- **Track mọi thao tác có ý nghĩa** (theo yêu cầu SRS). Event chuẩn hóa tên, có schema property.
- Ghi bất đồng bộ qua queue để không chặn UX.
- Phân biệt: **Product analytics** (hành vi) vs **Learning analytics** (kết quả học) vs **System metrics** (kỹ thuật).
- Tôn trọng quyền riêng tư: cho phép opt-out analytics không thiết yếu; ẩn danh IP nếu cần.

## 2. Cấu trúc event chuẩn

```json
{
  "name": "question_answer",
  "user_id": "01H...",            // null nếu guest
  "session_uuid": "web-...",       // phiên trình duyệt
  "occurred_at": "2026-07-11T11:22:33Z",
  "context": {
    "url": "/qbank/session/123",
    "referrer": "/qbank",
    "device": "desktop",
    "app_version": "1.4.0",
    "locale": "vi"
  },
  "properties": {
    "question_id": "01H...",
    "session_id": "123",
    "is_correct": true,
    "time_spent_seconds": 42,
    "used_hint": false
  }
}
```

Lưu vào `tracking_events` (insert-only) + có thể forward tới kho phân tích ngoài.

## 3. Danh mục event chuẩn (naming: `object_action`, snake_case)

### Auth & Account
`signup_start`, `signup_success`, `login_success`, `login_failed`, `logout`, `oauth_connect`, `password_reset_request`, `email_verify_success`.

### Discovery & Search
`search`, `search_result_click`, `global_search_open`, `filter_apply`, `sort_apply`.

### Question / Session
`qbank_open`, `session_create`, `session_start`, `question_open`, `question_answer`, `question_skip`, `hint_use`, `question_flag`, `session_pause`, `session_resume`, `session_finish`, `review_open`.

### Content / Library
`library_open`, `article_read`, `article_scroll_complete`, `drug_open`, `procedure_open`, `image_view`, `image_zoom`, `video_play`, `video_complete`, `crosslink_click`.

### Personalization
`bookmark_add`, `bookmark_remove`, `highlight_create`, `highlight_delete`, `note_create`, `note_update`, `flashcard_create`, `flashcard_review`, `deck_create`.

### Study Plan & Analytics
`study_plan_create`, `study_plan_task_complete`, `weak_topic_open`, `weak_topic_practice`, `analytics_open`, `heatmap_open`.

### Exam
`exam_open`, `exam_start`, `exam_submit`, `exam_finish`, `self_assessment_start`, `self_assessment_finish`.

### Monetization
`paywall_view`, `upgrade_click`, `checkout_start`, `subscription_upgrade`, `subscription_cancel`, `payment_success`, `payment_failed`, `coupon_apply`.

### AI
`ai_open`, `ai_prompt`, `ai_response`, `ai_feedback` (thumbs up/down).

### Notification & Engagement
`notification_receive`, `notification_open`, `streak_continue`, `streak_break`, `reminder_click`.

## 4. Bảng thuộc tính bắt buộc theo nhóm

| Nhóm event | Property bắt buộc |
|-----------|-------------------|
| question_* | question_id, session_id |
| session_* | session_id, mode, source |
| answer | question_id, is_correct, time_spent_seconds, used_hint |
| search | query, results_count |
| content | content_type, content_id |
| monetization | plan_id (nếu có), source (nơi bấm) |
| ai_* | thread_id, tokens (nếu đo) |

## 5. Pipeline phân tích

```
Client/Server → dispatch TrackEventJob (queue) → tracking_events (MySQL, partition)
     → job rollup định kỳ (Scheduler):
         • DailyStat (per user/day)
         • TopicMastery (weak topics)
         • stats_cache trên Question (correct rate)
         • aggregates cho Dashboard/Reports (Redis cache)
     → (tùy chọn) sink ra kho phân tích (BigQuery/ClickHouse) cho BI
```

## 6. Learning analytics — công thức lõi

- **Correct rate (topic)** = `correct / attempts` trên các attempt hợp lệ (không tính skip).
- **Mastery level (0–5)**: hàm theo correct rate gần đây + số lượng attempt + độ khó câu (câu khó đúng → cộng nhiều hơn). Có decay theo thời gian không hoạt động.
- **Weak topic**: topic có mastery thấp và/hoặc correct rate < ngưỡng và đủ số attempt tối thiểu.
- **Percentile / peer compare**: so với phân phối của cohort (cùng exam target / cùng org).
- **Streak**: số ngày liên tiếp đạt daily goal.

Chi tiết dùng ở module 19–22, 04-Study Plan.

## 7. Dashboard & Reports

- Dashboard người học đọc từ rollup cache (không query attempt trực tiếp).
- Admin Reports (module 41) query read-replica + cache; export qua queue.

## 8. Quản trị chất lượng dữ liệu

- Versioning schema event (`schema_version` trong properties khi thay đổi).
- Validation event ở tầng nhận (loại bỏ event dị dạng, log để giám sát).
- Giám sát volume/anomaly (đột biến `login_failed`, `payment_failed`).
