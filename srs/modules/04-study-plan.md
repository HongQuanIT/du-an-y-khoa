# Module 04 — Study Plan

**Nhóm:** Core · **Ưu tiên:** Cao · **Phụ thuộc:** Dashboard (03), Question Session (06), Weak Topics (20), Analytics (19) · **Trạng thái:** ✅

## 0. Tóm tắt module
Lộ trình học cá nhân hóa theo ngày thi mục tiêu: chia khối lượng câu hỏi/bài đọc/flashcard theo ngày, thích ứng theo tiến độ và điểm yếu.

| Route | Màn hình |
|-------|----------|
| `/study-plan` | Tổng quan kế hoạch |
| `/study-plan/create` | Wizard tạo kế hoạch |
| `/study-plan/{id}` | Chi tiết + lịch |
| `/study-plan/{id}/edit` | Chỉnh sửa |

## 1. Tổng quan
- **Mục đích:** Biến mục tiêu thi thành nhiệm vụ hằng ngày khả thi.
- **Đến từ:** Dashboard widget, onboarding, nhắc nhở.
- **Đi sang:** Question Session (task câu hỏi), Library (task đọc), Flashcards (task ôn).

## 2. Phân tích giao diện

| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Create wizard (stepper)** | Chọn kỳ thi → ngày thi → phạm vi chủ đề → cường độ/ngày → xem trước | Khi tạo | Ngang → dọc |
| **Calendar view** | Lịch task theo ngày (Calendar) | Chi tiết plan | Tháng → agenda list mobile |
| **Today panel** | Task hôm nay + progress | Luôn | Full-width |
| **Progress bar tổng** | % hoàn thành đến ngày thi | Luôn | — |
| **Task list** | Danh sách nhiệm vụ (checkbox) | Luôn | List |
| **Adaptive banner** | Thông báo điều chỉnh kế hoạch | Khi hệ thống re-plan | — |
| **Empty state** | Chưa có plan → CTA tạo | Khi rỗng | — |
| **Reschedule modal** | Dời task khi lỡ | Khi lỡ task | Bottom sheet mobile |
| **Loading/Error** | Skeleton, retry | Theo trạng thái | — |

## 3. Phân tích Component

### `StudyPlanWizard`
- **Props:** `exams[]`, `topics[]`, `defaults`.
- **State:** `step`, `formData(examTargetDate, dailyGoal, scope, strategy)`.
- **Events:** `onNext/onBack/onGenerate`.
- **Validation:** ngày thi ở tương lai; daily goal > 0; phạm vi ≥ 1 chủ đề.
- **Business:** tính tổng câu cần làm / số ngày → daily goal đề xuất.
- **A11y:** stepper, focus quản lý.

### `PlanCalendar`
- **Props:** `tasks[]`, `range`.
- **Events:** `onTaskClick`, `onReschedule(dragDrop)`.
- **State:** selected date.
- **Empty:** ngày trống → "Nghỉ / thêm task".

### `TaskItem`
- **Props:** `task(type, target, done, status)`.
- **Events:** `onStart`, `onComplete`, `onSkip`.
- **State:** progress.
- **Business:** click → điều hướng đúng module theo `type`.

### `AdaptiveNotice`, `RescheduleModal`, `PlanProgressRing`.

## 4. Luồng người dùng
```
Dashboard/Onboarding → /study-plan/create (wizard)
 → chọn kỳ thi, ngày thi, phạm vi, cường độ → generate
 → /study-plan/{id}: xem lịch + task hôm nay
 → bắt đầu task câu hỏi → Session → hoàn thành → task tự đánh dấu done
 → lỡ ngày → hệ thống re-balance khối lượng còn lại (adaptive)
Ngoại lệ:
 - Ngày thi quá gần khối lượng lớn → cảnh báo "cường độ cao / dời ngày".
 - Hoàn thành sớm → gợi ý nâng mục tiêu hoặc ôn tập.
```

## 5. Business Logic
- **Sinh kế hoạch:** `total_needed / days_until_exam` → daily goal; phân bổ theo trọng số chủ đề (ưu tiên weak topics + high-yield).
- **Adaptive re-plan:** hằng ngày (scheduler) tính lại: cộng dồn task lỡ, giảm/tăng theo tốc độ thực tế, ưu tiên chủ đề đang yếu.
- **Strategy:** `fixed` (chia đều) vs `adaptive` (điều chỉnh liên tục).
- **Task types:** questions / read (article) / flashcards (due) / review (câu sai).
- **Hoàn thành task** khi đạt target (vd làm đủ N câu chủ đề X).
- **Premium:** adaptive nâng cao + dự báo "đạt mục tiêu"; Free chỉ fixed cơ bản.
- **Continue Learning** (Dashboard) đọc task hôm nay từ đây.

## 6. Database
- `study_plans`, `study_plan_tasks` (xem `04-mo-hinh-du-lieu.md` mục 7).
- Đọc `topic_mastery`, `flashcard_reviews` (due), `question_status`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| POST | `/api/v1/study-plan` | `{exam, target_date, scope[], daily_goal, strategy}` | plan + tasks | Auth |
| GET | `/api/v1/study-plan/{id}` | — | plan + calendar tasks | Owner |
| GET | `/api/v1/study-plan/{id}/today` | — | task hôm nay | Owner |
| PATCH | `/api/v1/study-plan/{id}` | fields | plan | Owner |
| POST | `/api/v1/study-plan/{id}/tasks/{taskId}/complete` | — | task | Owner |
| POST | `/api/v1/study-plan/{id}/reschedule` | `{taskId,date}` | task | Owner |
| DELETE | `/api/v1/study-plan/{id}` | — | 204 | Owner |

Validation: ngày tương lai, scope hợp lệ, quyền owner.

## 8. State Management
- **Server:** plan/tasks trong DB; re-plan qua scheduler job.
- **Client:** wizard state, calendar view.
- **Optimistic:** đánh dấu task done optimistic, rollback nếu lỗi.
- **Caching:** "today" cache ngắn; invalidations khi complete task.

## 9. Phân quyền
- Owner (Student/Premium). Instructor có thể gán plan mẫu cho lớp (module 32). Free giới hạn 1 plan; Premium nhiều plan + adaptive.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Đổi ngày thi | Re-generate hoặc re-balance |
| Bỏ lỡ nhiều ngày | Adaptive dồn + cảnh báo quá tải |
| Subscription hết hạn | Adaptive → fixed; plan vẫn xem được |
| Xóa chủ đề khỏi scope | Re-balance task còn lại |
| Concurrent complete task | Idempotent, `409` nếu xung đột |
| Timeout generate | Sinh nền qua queue + thông báo khi xong |

## 11. Tracking
`study_plan_create`, `study_plan_view`, `study_plan_task_start`, `study_plan_task_complete`, `study_plan_reschedule`, `study_plan_replan(auto)`, `study_plan_delete`.

## 12. Responsive
- Desktop: calendar tháng + panel bên. Tablet: calendar tuần. Mobile: agenda list theo ngày, task hôm nay nổi bật, swipe complete.

## 13. Security
- Scope owner; validate ngày; chống thao tác task của người khác (IDOR).

## 14. Performance
- Sinh/re-plan qua queue; task hôm nay cache; calendar phân trang theo tháng.

## 15. Đề xuất cải tiến
- Dự báo AI "khả năng đạt mục tiêu" + đề xuất điều chỉnh.
- Đồng bộ Google Calendar / nhắc lịch.
- Chế độ "cram" trước thi (dồn high-yield).
- Nhắc nhở thông minh theo thói quen giờ học.
