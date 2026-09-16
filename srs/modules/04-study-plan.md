# Module 04 — Study Plan

**Nhóm:** Core · **Ưu tiên:** Cao · **Phụ thuộc:** Dashboard (03), Question Session (06), Weak Topics (20), Analytics (19) · **Trạng thái:** ✅

## 0. Tóm tắt module
Lộ trình học cá nhân hóa theo ngày thi mục tiêu: chia khối lượng câu hỏi/bài đọc/flashcard theo ngày, thích ứng theo tiến độ và điểm yếu.

| Route | Màn hình |
|-------|----------|
| `/study-plan` | Tổng quan kế hoạch |
| `/study-plan/create` | Wizard tạo kế hoạch |
| `/study-plan/{id}` | Chi tiết + lịch |

## 1. Tổng quan
- **Mục đích:** Biến mục tiêu thi thành nhiệm vụ hằng ngày khả thi.
- **Đến từ:** Dashboard widget, onboarding, nhắc nhở.
- **Đi sang:** Question Session (task câu hỏi), Library (task đọc), Flashcards (task ôn).

## 2. Phân tích giao diện

| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Create wizard (stepper)** | Chọn kỳ thi → ngày thi → phạm vi (Hệ cơ quan/Môn học/Bài học) → cường độ/ngày → xem trước | Khi tạo | Ngang → dọc |
| **Calendar view** | Lịch task theo ngày (Calendar) | Chi tiết plan | Tháng → agenda list mobile |
| **Today panel** | Task hôm nay + progress | Luôn | Full-width |
| **Progress bar tổng** | % hoàn thành đến ngày thi | Luôn | — |
| **Task list** | Danh sách nhiệm vụ (checkbox) | Luôn | List |
| **Empty state** | Chưa có plan → CTA tạo | Khi rỗng | — |
| **Loading/Error** | Skeleton, retry | Theo trạng thái | — |

## 3. Phân tích Component

### `StudyPlanWizard`
- **Props:** `organSystems[]`, `subjects[]`, `lessons[]`, `defaults`.
- **State:** `step`, `formData(examTargetDate, dailyGoal, scope, strategy)`.
- **Events:** `onNext/onBack/onGenerate`.
- **Validation:** ngày thi ở tương lai; daily goal > 0; phạm vi ≥ 1 Bài học (hoặc Môn học/Hệ cơ quan).
- **Business:** tính tổng câu cần làm / số ngày → daily goal đề xuất.
- **A11y:** stepper, focus quản lý.

### `PlanCalendar`
- **Props:** `tasks[]`, `range`.
- **Events:** `onTaskClick`.
- **State:** selected date.
- **Empty:** ngày trống → "Nghỉ / thêm task".

### `TaskItem`
- **Props:** `task(type, target, done, status)`.
- **Events:** `onStart`, `onComplete`, `onSkip`.
- **State:** progress.
- **Business:** click → điều hướng đúng module theo `type`.

### `PlanProgressRing`.

## 4. Luồng người dùng
```
Dashboard/Onboarding → /study-plan/create (wizard)
 → chọn kỳ thi, ngày thi, phạm vi, cường độ → generate
 → /study-plan/{id}: xem lịch + task hôm nay
 → bắt đầu task câu hỏi → Session → hoàn thành → task tự đánh dấu done
Ngoại lệ:
 - Ngày thi quá gần khối lượng lớn → cảnh báo cường độ cao.
 - Hoàn thành sớm → gợi ý nâng mục tiêu hoặc ôn tập.
```

## 5. Business Logic
- **Sinh kế hoạch:** `total_needed / days_until_exam` → daily goal; phân bổ theo trọng số Bài học/Môn học (ưu tiên Bài học yếu + high-yield).
- **Kế hoạch bất biến sau khi tạo:** học viên không thể sửa, xoá, dời task hoặc lập lại kế hoạch; tạo kế hoạch mới sẽ thay thế kế hoạch đang hoạt động.
- **Strategy:** `fixed` (chia đều) hoặc `adaptive` (ưu tiên phân bổ theo nội dung yếu lúc tạo).
- **Task types:** questions / read (article) / flashcards (due) / review (câu sai).
- **Hoàn thành task** khi đạt target (vd làm đủ N câu Bài học X).
- **Premium:** adaptive nâng cao + dự báo "đạt mục tiêu"; Free chỉ fixed cơ bản.
- **Continue Learning** (Dashboard) đọc task hôm nay từ đây.

## 6. Database
- `study_plans`, `study_plan_tasks` (xem `04-mo-hinh-du-lieu.md` mục 7).
- Đọc `topic_mastery` (rollup theo `lesson_id`), `flashcard_reviews` (due), `question_status`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/study-plans` | — | danh sách kế hoạch | Owner |
| POST | `/api/v1/study-plans` | `{exam, target_date, scope[], daily_goal, strategy}` | plan + tasks | Auth |
| GET | `/api/v1/study-plans/{id}` | — | plan | Owner |
| GET | `/api/v1/study-plans/{id}/tasks` | `date?` | task theo ngày | Owner |
| POST | `/api/v1/study-plans/{id}/tasks/{taskId}/start` | — | task + session | Owner |
| POST | `/api/v1/study-plans/{id}/tasks/{taskId}/skip` | — | task | Owner |

Validation: ngày tương lai, scope hợp lệ, quyền owner.

## 8. State Management
- **Server:** plan/tasks trong DB; lịch được giữ nguyên sau khi tạo.
- **Client:** wizard state, calendar view.
- **Optimistic:** đánh dấu task done optimistic, rollback nếu lỗi.
- **Caching:** "today" cache ngắn; invalidations khi hoàn thành hoặc bỏ qua task.

## 9. Phân quyền
- Owner (Student/Premium). Quyền chỉ gồm tạo, xem kế hoạch và thực hiện task; không có sửa, xoá, dời lịch hay lập lại kế hoạch.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Đổi ngày thi | Tạo kế hoạch mới |
| Bỏ lỡ nhiều ngày | Task cũ vẫn được hiển thị để học viên tự xử lý |
| Subscription hết hạn | Plan vẫn xem và thực hiện được |
| Xóa Bài học khỏi scope | Kế hoạch đã tạo không thay đổi |
| Concurrent complete task | Idempotent, `409` nếu xung đột |
| Timeout generate | Sinh nền qua queue + thông báo khi xong |

## 11. Tracking
`study_plan_create`, `study_plan_view`, `study_plan_task_start`, `study_plan_task_complete`.

## 12. Responsive
- Desktop: calendar tháng + panel bên. Tablet: calendar tuần. Mobile: agenda list theo ngày, task hôm nay nổi bật, swipe complete.

## 13. Security
- Scope owner; validate ngày; chống thao tác task của người khác (IDOR).

## 14. Performance
- Sinh kế hoạch qua queue; task hôm nay cache; calendar phân trang theo tháng.

## 15. Đề xuất cải tiến
- Dự báo AI "khả năng đạt mục tiêu" + đề xuất điều chỉnh.
- Đồng bộ Google Calendar / nhắc lịch.
- Chế độ "cram" trước thi (dồn high-yield).
- Nhắc nhở thông minh theo thói quen giờ học.
