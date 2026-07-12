# Module 06 — Question Session (Player)

**Nhóm:** Core · **Ưu tiên:** Rất cao (trái tim sản phẩm) · **Phụ thuộc:** Qbank (05), Review (07), Notes/Bookmark/Highlight (15–17), AI (08) · **Trạng thái:** ✅

## 0. Tóm tắt module
Giao diện làm bài: hiển thị từng câu hỏi, nhận đáp án, chấm điểm, hỗ trợ Study/Exam mode. Nơi người dùng dành nhiều thời gian nhất → UX phải mượt, chống mất dữ liệu, tương tác phong phú (bookmark/note/highlight/flag/hint/AI/lab values).

| Route | Màn hình |
|-------|----------|
| `/qbank/session/{id}` | Màn làm bài (1 câu/màn) |
| `/qbank/session/{id}/summary` | Tổng kết sau nộp (exam) |

## 1. Tổng quan
- **Mục đích:** Trả lời câu hỏi và (Study) học từ giải thích ngay.
- **Đến từ:** Qbank start, Dashboard Continue, Study Plan task, Weak Topics.
- **Đi sang:** Question Review (07), Summary, Dashboard.

## 2. Phân tích giao diện

| Thành phần | Chức năng | Hiển thị | Ẩn | Điều kiện | Responsive |
|-----------|-----------|----------|-----|-----------|-----------|
| **Session header** | Tiến trình (câu 5/40), timer (exam), nút thoát/tạm dừng | Luôn | Timer ẩn ở study | Exam có timer | Mobile: gọn |
| **Progress bar** | % hoàn thành / bản đồ câu | Luôn | — | — | Map → drawer mobile |
| **Question stem** | Đề bài (vignette), lab values, media | Luôn | — | — | Ảnh zoom |
| **Answer options** | Radio/checkbox A–E | Luôn | — | Single/multi theo type | Nút lớn mobile |
| **Submit/Next button** | Nộp đáp án / câu tiếp | Luôn | — | Study: submit từng câu; Exam: next | Sticky đáy |
| **Explanation panel** | Giải thích + vì sao đúng/sai | Study: sau submit | Exam: ẩn tới khi nộp cả bài | — | Accordion mobile |
| **Toolbar câu** | Bookmark, Flag, Note, Highlight, Report, AI, Add flashcard | Luôn | — | Một số Premium | Icon bar / overflow menu |
| **Lab values reference** | Bảng chỉ số bình thường | Toggle | — | — | Drawer |
| **Hint** | Gợi ý (Study) | Study, khi bấm | Exam ẩn | Ghi `used_hint` | — |
| **Calculator/tools** | Máy tính lâm sàng | Toggle | — | — | Modal |
| **Question navigator** | Nhảy câu, xem flagged/answered | Luôn | — | — | Drawer mobile |
| **Pause/Exit modal** | Xác nhận thoát/lưu | Khi thoát | — | — | — |
| **Timer warning** | Cảnh báo sắp hết giờ | Exam, ngưỡng | — | Exam | Toast |
| **Autosave indicator** | "Đã lưu" | Sau mỗi answer | — | — | — |
| **Loading/Error/Empty** | Skeleton câu; lỗi tải; hết câu → summary | Theo trạng thái | — | — | — |

## 3. Phân tích Component

### `QuestionPlayer` (Livewire/Alpine)
- **Mục đích:** Điều phối toàn bộ phiên làm bài.
- **Props:** `sessionId`, `mode`, `initialIndex`.
- **State:** `currentIndex`, `question`, `selected`, `submittedMap`, `timerRemaining`, `flaggedSet`, `savingState`.
- **Events:** `selectOption`, `submitAnswer`, `next`, `prev`, `flag`, `pause`, `finish`.
- **Validation:** phải chọn đáp án trước submit (Study); Exam cho phép bỏ trống (omitted).
- **Permission:** entitlement kiểm tra khi tải câu; hint/AI có thể Premium.
- **A11y:** radios ARIA, phím tắt (1–5 chọn, Enter submit, ←/→ điều hướng), focus quản lý, timer thông báo `aria-live` polite.
- **Animation:** transition chuyển câu, reveal explanation, tô đúng/sai.
- **Loading:** skeleton; **Error:** retry giữ đáp án đã chọn; **Empty:** hết câu → summary; **Disabled:** submit khi chưa chọn (study).

### `AnswerOption`
- **Props:** `option, state(default/selected/correct/incorrect/eliminated)`, `disabled`.
- **Events:** `onSelect`, `onEliminate(gạch bỏ)`.
- **Business:** sau submit khóa lựa chọn, tô màu.
- **A11y:** trạng thái qua text + màu (không chỉ màu).

### `ExplanationPanel`
- **Props:** `explanation`, `optionExplanations`, `references`, `crossLinks`.
- **State:** expanded sections.
- **Events:** `onCrosslinkClick` (mở Library drawer).
- **Loading/Empty:** nếu thiếu giải thích → thông báo.

### `QuestionToolbar`
- **Props:** `question`, `states(bookmarked, flagged, hasNote)`.
- **Events:** `onBookmark, onFlag, onNote, onHighlight, onReport, onAI, onAddFlashcard`.
- **Permission:** AI Premium; optimistic cho bookmark/flag.

### `SessionTimer` (exam), `QuestionNavigator`, `LabValuesDrawer`, `HintButton`, `PauseModal`, `AutosaveBadge`.

## 4. Luồng người dùng
```
STUDY MODE:
 mở câu → chọn đáp án → Submit → chấm + hiện explanation → (tương tác: note/highlight/AI)
   → Next → ... → câu cuối → Summary/Review

EXAM MODE:
 mở câu → chọn (có thể bỏ trống) → Next (không chấm) → ... → câu cuối → "Nộp bài"
   → xác nhận → chấm toàn bộ → Summary → Review

Ngoại lệ:
 - Tạm dừng → lưu paused_state → thoát → Dashboard "Continue".
 - Hết giờ (exam) → tự động nộp.
 - Mất mạng → autosave local, đồng bộ khi có mạng lại.
 - Refresh/back → khôi phục vị trí từ paused_state/server.
 - Câu bị retired giữa chừng → bỏ qua, điều chỉnh tổng.
```

## 5. Business Logic
- **Chấm điểm:** so `selected_option_ids` với `is_correct`. Multi: đúng khi khớp hoàn toàn (hoặc partial theo cấu hình).
- **Study vs Exam:** Study chấm & hiện giải thích ngay; Exam hoãn tới khi nộp; ẩn hint/explanation.
- **Timer (exam):** đếm ngược `time_limit_seconds`; hết giờ auto-submit; lưu server-side để chống gian lận.
- **Autosave:** mỗi answer ghi ngay (`QuestionAttempt`) + cập nhật `paused_state`.
- **used_hint / confidence / flag** ghi vào attempt.
- **Cập nhật trạng thái câu:** `question_status` (incorrect/correct/omitted) sau chấm.
- **Continue Learning:** session `paused/active` là nguồn cho Dashboard.
- **Adaptive (Premium):** có thể chèn câu bổ sung theo hiệu suất trong phiên.
- **Anti-cheat exam:** không lộ đáp án qua API trước nộp; timer server; phát hiện tab switch (tùy chọn).

## 6. Database
- `question_sessions`, `question_attempts`, `question_status` (xem mục 4 data model).
- Đọc `questions` + `question_options` (không gửi `is_correct` cho FE trước khi chấm).
- Ghi tương tác: `bookmarks`, `highlights`, `notes`, `flashcards`, `question_reports`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/sessions/{id}` | — | session state + câu hiện tại (không đáp án đúng) | Owner |
| GET | `/api/v1/sessions/{id}/questions/{qid}` | — | nội dung câu (options không lộ correct) | Owner |
| POST | `/api/v1/sessions/{id}/answers` | `{question_id, option_ids[], used_hint, time_spent, confidence}` + Idempotency-Key | study: `{is_correct, explanation}`; exam: `{saved:true}` | Owner |
| POST | `/api/v1/sessions/{id}/flag` | `{question_id, flagged}` | ok | Owner |
| POST | `/api/v1/sessions/{id}/pause` | `{paused_state}` | ok | Owner |
| POST | `/api/v1/sessions/{id}/finish` | — | summary | Owner |
| GET | `/api/v1/sessions/{id}/summary` | — | kết quả tổng | Owner |

Validation: câu thuộc session; không nộp lại câu đã chấm (study) trừ cho phép; time hợp lệ.
Lỗi: `409` double submit, `410` câu retired, `403` không phải owner.

## 8. State Management
- **Client state:** câu hiện tại, đáp án chọn, timer, flagged, highlight tạm — Alpine/Livewire; lưu localStorage/IndexedDB để chống mất khi refresh/offline.
- **Server state:** attempts, session status; timer authoritative ở server.
- **Optimistic:** bookmark/flag/highlight cập nhật ngay, rollback nếu lỗi.
- **Caching:** nội dung câu cache (không cache trạng thái cá nhân); prefetch câu kế tiếp.
- **Realtime:** exam giám sát (org) qua Reverb (tùy chọn); đồng bộ đa thiết bị.
- **Offline:** Service Worker cache câu đã tải; hàng đợi answer đồng bộ khi online.

## 9. Phân quyền
- Owner của session. Free: chỉ câu free, quota/ngày; Premium full + hint/AI. Instructor xem session học viên (read) trong lớp.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Không internet | Autosave local, badge offline, đồng bộ lại; timer tiếp tục local nhưng đối chiếu server |
| Session hết hạn/expired | Chuyển summary với dữ liệu đã có |
| Subscription hết hạn giữa phiên | Cho hoàn thành phiên hiện tại; chặn tạo mới |
| Refresh/back | Khôi phục từ paused_state/server |
| Duplicate answer submit | Idempotency-Key → 1 attempt |
| Concurrent 2 thiết bị | Server là nguồn thật; cảnh báo/merge |
| Timeout API answer | Retry queue, giữ lựa chọn |
| Câu bị xóa/retired | Loại, điều chỉnh total/summary |
| Hết giờ khi mất mạng | Server auto-submit theo thời điểm hết hạn |

## 11. Tracking
`session_start`, `question_open`, `question_answer`, `question_skip`, `hint_use`, `question_flag`, `answer_change`, `crosslink_click`, `ai_open(from_question)`, `session_pause`, `session_resume`, `session_finish`, `exam_submit`.

## 12. Responsive
- **Desktop:** stem trái, options phải hoặc dọc; navigator bên; toolbar ngang.
- **Tablet:** 1 cột rộng; navigator drawer.
- **Mobile:** 1 câu/màn full-screen, options nút lớn, toolbar overflow menu, submit sticky đáy, navigator bottom drawer, timer thu gọn header.

## 13. Security
- **Không bao giờ** gửi `is_correct`/đáp án đúng tới FE trước khi chấm (đặc biệt Exam).
- Timer & chấm điểm server-side; chống sửa client.
- Rate limit submit; idempotency; scope owner; sanitize rich content (highlight/note).
- Chống scraping toàn bộ Qbank (giới hạn tốc độ tải câu).

## 14. Performance
- Prefetch câu kế tiếp; lazy media; cache nội dung câu; ghi attempt qua batch/queue nếu tải cao; skeleton chống CLS; tách bundle player.

## 15. Đề xuất cải tiến
- **Eliminate option** (gạch bỏ đáp án) như phòng thi thật.
- Highlight trực tiếp trên stem + tô màu.
- Chế độ "tutor" hỏi AI ngay trong câu.
- Confidence-based scoring (đo tự tin để phân tích).
- Đồng bộ realtime đa thiết bị + tiếp tục liền mạch.
- Peer stats: "X% chọn đáp án này".
