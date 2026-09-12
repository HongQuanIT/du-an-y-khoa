# Module 05 — Question Bank (Qbank)

**Nhóm:** Core · **Ưu tiên:** Rất cao · **Phụ thuộc:** Question Session (06), Search (25), Subscription (28) · **Trạng thái:** ✅

## 0. Tóm tắt module
Trình duyệt & bộ lọc câu hỏi để tạo phiên luyện tập. Người dùng chọn tiêu chí (Hệ cơ quan → Môn học → Bài học, độ khó, trạng thái đã làm, tag) → tạo Session. Đây là điểm khởi đầu học tập chính.

| Route | Màn hình |
|-------|----------|
| `/qbank` | Trình tạo session (filter builder) |
| `/qbank/browse` | Duyệt danh sách câu hỏi |
| `/qbank/sessions` | Lịch sử session |

## 1. Tổng quan
- **Mục đích:** Cho phép cấu hình chính xác bộ câu hỏi muốn luyện.
- **Đến từ:** Dashboard, sidebar, Weak Topics (pre-filled), Study Plan.
- **Đi sang:** Question Session (06) khi bấm "Bắt đầu".

## 2. Phân tích giao diện

| Thành phần | Chức năng | Hiển thị/Ẩn | Điều kiện | Responsive |
|-----------|-----------|-------------|-----------|-----------|
| **Session type selector** | Chọn **Tuỳ chỉnh** vs **Thích ứng** | Luôn | — | 2 thẻ ngang; mobile xếp dọc |
| **Filter panel (tuỳ chỉnh)** | Chọn **Kỳ thi (ma trận) → Hệ/Môn/Bài học**, độ khó, trạng thái, câu đã lưu | Khi loại = tuỳ chỉnh | Không chọn kỳ thi → toàn bộ NHCH; chọn kỳ thi → chỉ danh mục trong ma trận kỳ đó | Desktop bên trái; mobile: bottom sheet |
| **Exam picker (thích ứng)** | Chỉ chọn đề thi theo ma trận; hệ thống chọn câu theo sức học / điểm yếu | Khi loại = thích ứng | Ẩn độ khó, trạng thái, bộ lọc chủ đề thủ công | Desktop trái; mobile full width |
| **Count preview** | Hiển thị số câu khớp filter realtime | Luôn | Adaptive: cần đã chọn đề | — |
| **Mode selector** | Study vs Exam | Luôn | — | Toggle |
| **Config số câu** | Số câu / thời gian (exam) | Luôn | Exam mode hiện time | — |
| **Start button (floating)** | Tạo session | Luôn | Disabled nếu 0 câu (hoặc adaptive chưa chọn đề) | Sticky đáy mobile |
| **Saved filters** | Lưu bộ lọc yêu thích | Luôn | — | — |
| **Paywall overlay** | Câu Premium khóa | Free | Free tier | — |
| **Empty state** | Filter không ra câu nào | Khi 0 kết quả | — | — |
| **Loading** | Skeleton list + count | Đang tải | — | — |

## 3. Phân tích Component

### `FilterBuilder`
- **Props:** `organSystems[]`, `subjects[]`, `lessons[]`, `tags[]`, `currentUserStatusCounts`.
- **State:** `selectedLessons`, `selectedSubjects`, `selectedOrganSystems`, `difficulty`, `status`, `tags`, `count(loading)`.
- **Events:** `onChange` (debounce → gọi count), `onReset`, `onSaveFilter`.
- **Validation:** ít nhất 1 tiêu chí; số câu ≤ giới hạn (Free thấp hơn).
- **Permission:** trạng thái/nguồn nâng cao (weak) Premium.
- **A11y:** nhóm checkbox có legend; count qua `aria-live`.
- **Loading/Empty:** count spinner; empty → gợi ý nới filter.

### `ModeSelector`
- **Props:** `mode`, `examConfig`.
- **Events:** `onModeChange`, `onConfigChange`.
- **Business:** Exam mode bật time limit + ẩn giải thích trong session.

### `QuestionListRow`
- **Props:** `question(id, preview, difficulty, status, isPremium)`.
- **State:** selected.
- **Permission:** premium mờ; không lộ đáp án.
- **A11y:** status badge có text.

### `SavedFilterChips`, `CountPreviewBadge`, `StartSessionButton`.

## 4. Luồng người dùng
```
/qbank → chọn filter → xem count realtime → chọn mode (Study/Exam) → cấu hình số câu/time
 → "Bắt đầu" → tạo QuestionSession → Question Session (06)
Nhánh:
 - Từ Weak Topics: /qbank?status=incorrect&lesson=X (pre-filled)
 - Từ Study Plan task: filter theo task
 - Browse: chọn thủ công câu → tạo session
Ngoại lệ:
 - 0 câu khớp → empty, gợi ý bỏ bớt filter.
 - Free vượt quota câu → paywall.
 - Chọn câu đã retired → loại khỏi session, thông báo.
```

## 5. Business Logic
- **Count realtime:** query đếm theo filter + trạng thái user (join `question_status`).
- **Trạng thái câu theo user:** unseen/incorrect/correct/omitted/marked (từ `question_status`).
- **Gating:** Free chỉ `is_free` + quota/ngày; Premium full. Server enforce.
- **Random/ordering:** shuffle hoặc theo độ khó tăng dần (tùy chọn).
- **Exclude:** câu chưa từng live hoặc đã `retired`/`private` (kể cả `exam_flag`). Include khi có `published_version` (snapshot live) — kể cả lúc working copy đang `draft`/`in_review`/`pending_publish`/`rejected` để tái bản; learner **không** đọc working copy chưa publish. Gating tier vẫn áp dụng.
- **Taxonomy filter:** chọn Hệ cơ quan hoặc Môn học → đếm/lọc gồm mọi Bài học con (suy qua `subject_organ_system` / `lesson_subject`). Câu hỏi gắn ≥1 Bài học (các bài ngang hàng).
- **Saved filters:** lưu snapshot tiêu chí.
- **Adaptive option:** phiên luyện thích ứng chỉ yêu cầu chọn đề thi (blueprint/ma trận); server bỏ độ khó/trạng thái; chọn câu theo ma trận + chủ đề yếu / câu sai / chưa làm.

## 6. Database
- Đọc: `questions`, `question_lesson`, `lessons`, `subjects`, `organ_systems`, `lesson_subject`, `subject_organ_system`, `tags`, `question_tags`, `question_status` (theo user).
- Tạo: `question_sessions` (khi start), `saved_filters(id,user_id,name,filters JSON)`.
- Search/filter phức tạp → Meilisearch (facet) + đếm.

## 7. API
| Method | URL | Payload/Query | Response | Quyền |
|--------|-----|---------------|----------|-------|
| GET | `/api/v1/questions` | filter, sort, page | list (không đáp án) | Auth (gated) |
| GET | `/api/v1/questions/count` | filter | `{count, byStatus}` | Auth |
| GET | `/api/v1/questions/facets` | — | organ_systems/subjects/lessons/tags + counts | Auth |
| POST | `/api/v1/sessions` | `{mode, filters, count, time_limit?}` | session (chuyển sang module 06) | Auth (gated) |
| GET/POST/DELETE | `/api/v1/saved-filters` | — | CRUD | Owner |

Validation: filter hợp lệ, count ≤ max theo tier, mode/time hợp lệ.
Lỗi: `SUBSCRIPTION_REQUIRED` khi vượt free; `422` filter sai.

## 8. State Management
- **Client:** filter state (Alpine/Livewire), debounce count.
- **Server:** danh mục taxonomy (hệ cơ quan/môn học/bài học) + tag cache Redis; count có thể cache ngắn theo (user, filter hash).
- **Pagination:** browse dùng cursor/infinite scroll.
- **Optimistic:** mark câu.
- **Realtime:** không.

## 9. Phân quyền
- Student: free subset. Premium: full. Instructor/Editor: xem tất cả (kể cả draft nếu editor). Xem `03-phan-quyen-rbac.md`.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Filter 0 kết quả | Empty + gợi ý |
| Vượt quota Free | Paywall |
| Câu bị retired sau khi chọn | Loại khỏi session + thông báo |
| Duplicate start (double click) | Idempotency-Key → 1 session |
| Refresh giữa cấu hình | Giữ filter (localStorage) |
| Timeout count | Hiển thị "~" + thử lại |
| Concurrent thay đổi bộ câu (admin publish) | Count có thể lệch nhẹ; snapshot khi tạo session |

## 11. Tracking
`qbank_open`, `filter_apply`, `count_preview`, `mode_change`, `saved_filter_create`, `session_create`, `paywall_view`, `browse_open`.

## 12. Responsive
- Desktop: filter trái + preview phải. Tablet: filter collapsible. Mobile: filter bottom sheet, count + Start sticky đáy.

## 13. Security
- Không trả `is_correct`/đáp án ở list; enforce entitlement server-side; scope theo user cho status; chống lộ câu Premium.

## 14. Performance
- Meilisearch facet + cache count; danh mục cache; infinite scroll browse; index `question_status(user_id,status)`.

## 15. Đề xuất cải tiến
- "Smart session" 1 chạm (AI chọn câu tối ưu hôm nay).
- Ước lượng thời gian hoàn thành session.
- Lưu & chia sẻ bộ lọc trong lớp học.
- Preview độ khó/tỉ lệ đúng cộng đồng cho từng Bài học.
