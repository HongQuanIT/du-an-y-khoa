# Module 05 — Question Bank (Qbank)

**Nhóm:** Core · **Ưu tiên:** Rất cao · **Phụ thuộc:** Question Session (06), Search (25), Subscription (28) · **Trạng thái:** ✅

## 0. Tóm tắt module
Trình duyệt & bộ lọc câu hỏi để tạo phiên luyện tập. Người dùng chọn tiêu chí (chủ đề, độ khó, trạng thái đã làm, tag) → tạo Session. Đây là điểm khởi đầu học tập chính.

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
| **Filter panel** | Chọn chủ đề, hệ cơ quan, độ khó, trạng thái (unseen/incorrect/correct/marked/omitted), tag, nguồn | Luôn | — | Desktop bên trái; mobile: bottom sheet |
| **Count preview** | Hiển thị số câu khớp filter realtime | Luôn | Cập nhật khi đổi filter | — |
| **Mode selector** | Study vs Exam | Luôn | — | Toggle |
| **Config số câu** | Số câu / thời gian (exam) | Luôn | Exam mode hiện time | — |
| **Question source tabs** | All / Custom / Weak / Marked | Luôn | — | Scroll ngang |
| **Question list (browse)** | Bảng/list câu kèm trạng thái, độ khó | `/qbank/browse` | Premium xem full | Table → card mobile |
| **Bulk actions** | Chọn nhiều → tạo session / mark | Browse | — | — |
| **Start button (floating)** | Tạo session | Luôn | Disabled nếu 0 câu | Sticky đáy mobile |
| **Saved filters** | Lưu bộ lọc yêu thích | Luôn | — | — |
| **Paywall overlay** | Câu Premium khóa | Free | Free tier | — |
| **Empty state** | Filter không ra câu nào | Khi 0 kết quả | — | — |
| **Loading** | Skeleton list + count | Đang tải | — | — |

## 3. Phân tích Component

### `FilterBuilder`
- **Props:** `topics[]`, `tags[]`, `currentUserStatusCounts`.
- **State:** `selectedTopics`, `difficulty`, `status`, `tags`, `count(loading)`.
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
 - Từ Weak Topics: /qbank?status=incorrect&topic=X (pre-filled)
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
- **Exclude:** câu retired/deleted không đưa vào session.
- **Saved filters:** lưu snapshot tiêu chí.
- **Adaptive option (Premium):** hệ thống tự chọn câu theo weak topics + spaced repetition.

## 6. Database
- Đọc: `questions`, `question_topics`, `topics`, `tags`, `question_status` (theo user).
- Tạo: `question_sessions` (khi start), `saved_filters(id,user_id,name,filters JSON)`.
- Search/filter phức tạp → Meilisearch (facet) + đếm.

## 7. API
| Method | URL | Payload/Query | Response | Quyền |
|--------|-----|---------------|----------|-------|
| GET | `/api/v1/questions` | filter, sort, page | list (không đáp án) | Auth (gated) |
| GET | `/api/v1/questions/count` | filter | `{count, byStatus}` | Auth |
| GET | `/api/v1/questions/facets` | — | topics/tags + counts | Auth |
| POST | `/api/v1/sessions` | `{mode, filters, count, time_limit?}` | session (chuyển sang module 06) | Auth (gated) |
| GET/POST/DELETE | `/api/v1/saved-filters` | — | CRUD | Owner |

Validation: filter hợp lệ, count ≤ max theo tier, mode/time hợp lệ.
Lỗi: `SUBSCRIPTION_REQUIRED` khi vượt free; `422` filter sai.

## 8. State Management
- **Client:** filter state (Alpine/Livewire), debounce count.
- **Server:** danh mục topic/tag cache Redis; count có thể cache ngắn theo (user, filter hash).
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
- Preview độ khó/tỉ lệ đúng cộng đồng cho từng chủ đề.
