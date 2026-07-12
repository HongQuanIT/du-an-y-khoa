# Module 03 — Dashboard (Student)

**Nhóm:** Core · **Ưu tiên:** Rất cao · **Phụ thuộc:** Analytics (19), Study Plan (04), Question Session (06), Weak Topics (20) · **Trạng thái:** ✅

## 0. Tóm tắt module
Trang chủ sau đăng nhập của người học: tổng quan tiến trình, gợi ý học tiếp (Continue Learning), số liệu nhanh, lối tắt tới mọi hoạt động. Đây là "trung tâm điều khiển" học tập.

| Route | Màn hình |
|-------|----------|
| `/dashboard` | Dashboard chính |
| `/dashboard?tab=overview\|activity\|goals` | Tab trong dashboard |

## 1. Tổng quan
- **Mục đích:** Cho người học biết "đang ở đâu, làm gì tiếp theo".
- **Khi dùng:** Ngay sau đăng nhập; điểm quay về mặc định.
- **Đến từ:** Login, logo header, menu.
- **Đi sang:** Question Session, Study Plan, Library, Analytics, Weak Topics, Exams.

## 2. Phân tích giao diện

| Thành phần | Chức năng | Hiển thị | Ẩn | Điều kiện | Responsive |
|-----------|-----------|----------|-----|-----------|-----------|
| **App Header** | Global search, notification bell, avatar menu, upgrade badge | Luôn | — | Upgrade badge ẩn nếu Premium | Mobile: search thu gọn |
| **Sidebar** | Menu chính (Dashboard, Qbank, Library, Flashcards, Analytics, Exams...) | Desktop luôn | Mobile mặc định ẩn | — | Mobile: drawer/bottom nav |
| **Welcome/Greeting** | Chào theo giờ + streak | Luôn | — | — | — |
| **Continue Learning card** | Nút tiếp tục session dang dở / gợi ý bắt đầu | Có session paused hoặc gợi ý | Nếu không có → CTA tạo mới | — | Full-width mobile |
| **Quick Stats widgets** | Câu đã làm, correct rate, streak, thời gian học | Luôn | — | Số liệu từ rollup | Grid → 2 cột mobile |
| **Progress chart** | Biểu đồ tiến trình theo thời gian (Chart.js) | Luôn | Empty nếu chưa có dữ liệu | — | Cuộn ngang mobile |
| **Weak Topics widget** | Top chủ đề yếu + CTA luyện | Có đủ dữ liệu | Ẩn nếu chưa đủ attempt | Premium: chi tiết hơn | Stack |
| **Study Plan widget** | Task hôm nay + tiến độ | Có study plan | CTA tạo plan nếu chưa có | — | — |
| **Recommendations** | Gợi ý câu/bài đọc/flashcard | Luôn | Empty state | AI/Premium nâng cao | Carousel mobile |
| **Recent activity** | Session/bài gần đây | Có hoạt động | Empty | — | List |
| **Upgrade banner** | Mời nâng cấp | Free | Ẩn Premium | — | — |
| **Skeleton loaders** | Khi tải widget | Đang tải | Sau tải | — | — |
| **Empty state tổng** | Người dùng mới chưa học gì | Khi rỗng | — | — | Onboarding CTA |

## 3. Phân tích Component

### `ContinueLearningCard`
- **Mục đích:** Giảm ma sát để tiếp tục học.
- **Props:** `pausedSession?`, `recommendedAction`.
- **State:** loading.
- **Events:** `onResume`, `onStartNew`.
- **Business:** Ưu tiên session `paused/active` gần nhất; nếu không có → gợi ý theo Study Plan → theo Weak Topics → mặc định random.
- **A11y/Loading/Empty/Error:** skeleton; empty → CTA; error → thử lại.

### `StatWidget`
- **Props:** `label`, `value`, `delta`, `icon`, `trend[]`, `premiumLocked`.
- **State:** hover tooltip.
- **Permission:** widget nâng cao mờ + upgrade nếu Free.
- **A11y:** số liệu có mô tả text.

### `ProgressChart` (Chart.js)
- **Props:** `series`, `range(7d/30d/all)`.
- **State:** selected range.
- **Events:** `onRangeChange`.
- **Loading:** skeleton; **Empty:** "Bắt đầu học để thấy tiến trình"; lazy-load Chart.js.

### `RecommendationList`, `WeakTopicMini`, `StudyPlanTodayCard`, `StreakBadge`.

## 4. Luồng người dùng
```
Login → /dashboard
 → Continue Learning: Resume session → Question Session (06)
 → Study Plan task hôm nay → bắt đầu → Session
 → Weak Topics widget → luyện chủ đề yếu → Session filtered
 → Recommendation → mở Library/flashcard
Người mới: empty state → gợi ý làm quiz chẩn đoán (self-assessment) → tạo Study Plan.
```

## 5. Business Logic
- **Continue Learning priority:** paused session > study plan task hôm nay > weak topic > default.
- **Số liệu** đọc từ `DailyStat`/`TopicMastery` (rollup), không query attempt trực tiếp.
- **Recommendation logic:** kết hợp weak topics, câu chưa làm, spaced repetition due, nội dung high-yield; Premium dùng AI/adaptive.
- **Gating Premium:** widget nâng cao (heatmap preview, peer compare) khóa với Free.
- **Streak logic:** cập nhật khi đạt daily goal; hiển thị cảnh báo sắp mất streak.

## 6. Database
- Đọc: `daily_stats`, `topic_mastery`, `question_sessions` (paused), `study_plans`+`study_plan_tasks`, `flashcard_reviews` (due), `notifications`.
- Không tạo entity mới; dùng cache tổng hợp.

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/dashboard` | tổng hợp widget (stats, continue, recommendations, plan today) | Auth |
| GET | `/api/v1/dashboard/progress?range=30d` | series biểu đồ | Auth |
| GET | `/api/v1/dashboard/recommendations` | gợi ý | Auth |

Response gộp để giảm round-trip; cache Redis theo user + TTL ngắn.

## 8. State Management
- **Server state:** đọc cache rollup (Redis), refresh nền.
- **Client:** range chart, tab (Alpine).
- **Optimistic:** không (chủ yếu đọc).
- **Realtime:** cập nhật streak/notification qua Reverb (tùy chọn).
- **Caching:** dashboard payload cache 1–5 phút/user, invalidations khi có session finish.

## 9. Phân quyền
- Student/Premium: xem; nội dung nâng cao gated theo entitlement. Instructor/Admin có dashboard riêng (module 33) — không dùng dashboard học viên trừ khi ở chế độ học.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| User mới, chưa dữ liệu | Empty state + onboarding CTA |
| Subscription vừa hết hạn | Widget Premium chuyển mờ + upgrade |
| Cache stale | Refresh nền, hiển thị "cập nhật lúc..." |
| API widget lỗi từng phần | Widget lỗi cục bộ, phần khác vẫn hiển thị |
| Refresh liên tục | Cache chống tải nặng |

## 11. Tracking
`dashboard_open`, `continue_learning_click`, `widget_click`, `recommendation_click`, `progress_range_change`, `upgrade_click`, `streak_view`.

## 12. Responsive
- Desktop: sidebar cố định + grid nhiều cột. Tablet: sidebar thu gọn (icon), grid 2 cột. Mobile: bottom nav, widget stack 1 cột, carousel cho recommendation.

## 13. Security
- Chỉ trả dữ liệu của chính user (scope owner); không lộ số liệu người khác trừ leaderboard ẩn danh; cache theo user tránh rò rỉ chéo.

## 14. Performance
- Đọc rollup cache; gộp API; lazy-load Chart.js; skeleton tránh CLS; prefetch route Qbank.

## 15. Đề xuất cải tiến
- Dashboard tùy biến (kéo-thả widget).
- "Daily brief" AI tóm tắt nên học gì hôm nay.
- Mục tiêu tuần + gamification (badge, level).
- So sánh tiến độ với mục tiêu ngày thi (dự báo "đủ/thiếu").
