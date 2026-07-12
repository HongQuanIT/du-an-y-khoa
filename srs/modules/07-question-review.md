# Module 07 — Question Review

**Nhóm:** Core · **Ưu tiên:** Cao · **Phụ thuộc:** Question Session (06), Analytics (19), Weak Topics (20) · **Trạng thái:** ✅

## 0. Tóm tắt module
Xem lại kết quả sau session/exam: tổng kết điểm, xem từng câu (đúng/sai/bỏ), đọc lại giải thích, ôn câu sai, phân tích theo chủ đề. Đóng vòng lặp học tập.

| Route | Màn hình |
|-------|----------|
| `/qbank/session/{id}/summary` | Tổng kết |
| `/qbank/session/{id}/review` | Xem lại từng câu |
| `/qbank/session/{id}/review/{qid}` | Chi tiết 1 câu |

## 1. Tổng quan
- **Mục đích:** Hiểu vì sao đúng/sai, xác định chủ đề yếu, hành động tiếp (ôn lại câu sai).
- **Đến từ:** Kết thúc Session (06), lịch sử session, Dashboard.
- **Đi sang:** Session mới (ôn câu sai), Weak Topics, Library (cross-link), Flashcards.

## 2. Phân tích giao diện

| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Score summary card** | % đúng, số đúng/sai/bỏ, thời gian, điểm exam | Summary | Donut chart |
| **Donut/Bar chart** | Tỉ lệ đúng/sai; theo chủ đề | Summary | Chart.js, cuộn |
| **Topic breakdown** | Bảng correct rate theo chủ đề + CTA ôn | Summary | Table → card |
| **Filter review** | Lọc câu: all/correct/incorrect/omitted/flagged | Review list | Chips |
| **Question review list** | Danh sách câu + trạng thái + đáp án đã chọn vs đúng | Review | List |
| **Review detail** | Stem + đáp án (tô đúng/sai) + explanation + toolbar | Detail | Full |
| **Action bar** | "Ôn lại câu sai", "Tạo flashcard từ câu sai", "Thêm vào weak practice" | Summary/Review | Sticky |
| **Peer comparison** | So sánh với cộng đồng (Premium) | Nếu Premium | Bar |
| **Empty/Loading/Error** | Chưa nộp → về session; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component

### `ScoreSummary`
- **Props:** `session(correct, incorrect, omitted, total, timeSpent, score, percentile?)`.
- **State:** chart range.
- **A11y:** số liệu text; chart có bảng thay thế.
- **Empty:** session chưa hoàn thành → CTA quay lại.

### `TopicBreakdownTable`
- **Props:** `topics[]{name, correct, total, rate}`.
- **Events:** `onPracticeTopic` → tạo session filtered.
- **Business:** highlight chủ đề < ngưỡng (đỏ).

### `ReviewQuestionRow` / `ReviewDetail`
- **Props:** `attempt`, `question`, `options(with correctness)`, `explanation`.
- **Events:** `onNext/onPrev`, `onCrosslink`, `onAddFlashcard`, `onAI`.
- **Permission:** giải thích đầy đủ (trong review luôn hiện, kể cả exam đã nộp).
- **A11y:** trạng thái qua icon+text.

### `ReviewFilterChips`, `PeerComparison`, `RetakeIncorrectButton`.

## 4. Luồng người dùng
```
Session finish → Summary (điểm, chart, topic breakdown)
 → Review list (lọc incorrect) → xem từng câu + explanation
 → hành động: "Ôn lại câu sai" → tạo session mới (chỉ câu sai)
             "Tạo flashcard từ câu sai"
             mở cross-link Library để đọc sâu
 → về Dashboard / Weak Topics (đã cập nhật)
Ngoại lệ:
 - Truy cập review khi chưa nộp → redirect về session.
 - Session cũ, câu đã đổi phiên bản → hiển thị snapshot lúc làm.
```

## 5. Business Logic
- **Tính tổng kết** từ `question_attempts` của session.
- **Topic breakdown**: nhóm attempt theo topic → correct rate.
- **Snapshot nội dung:** review dùng phiên bản câu lúc làm (versioning) để giải thích khớp.
- **Retake incorrect:** tạo session mới với `question_ids` = câu sai/omitted.
- **Cập nhật analytics/weak topics** đã xảy ra khi finish (event-driven).
- **Peer compare (Premium):** percentile so cohort.
- **Flashcard từ câu sai:** sinh front/back từ câu + giải thích.

## 6. Database
- Đọc `question_sessions`, `question_attempts`, `questions`(+version), `question_options`, `topic_mastery`.
- Tạo: session mới (retake), `flashcards` (nếu tạo).

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/sessions/{id}/summary` | tổng kết + topic breakdown + percentile | Owner |
| GET | `/api/v1/sessions/{id}/review?filter=incorrect` | list attempt + đáp án đúng + explanation | Owner |
| GET | `/api/v1/sessions/{id}/review/{qid}` | chi tiết câu | Owner |
| POST | `/api/v1/sessions/{id}/retake-incorrect` | session mới | Owner |
| POST | `/api/v1/flashcards/from-question` | `{question_id}` → flashcard | Owner |

Ở review, API **được phép** trả `is_correct`/đáp án đúng (đã nộp). Enforce owner + đã finish.

## 8. State Management
- **Server state:** summary có thể cache theo session (bất biến sau finish).
- **Client:** filter, câu đang xem.
- **Pagination:** review list phân trang nếu nhiều câu.
- **Realtime:** không.

## 9. Phân quyền
- Owner session. Instructor xem review của học viên trong lớp (read). Free vẫn review được session của mình; peer compare Premium.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Chưa nộp | Redirect session |
| Câu đã retired | Hiện snapshot version lúc làm |
| Subscription hết hạn | Review cơ bản vẫn xem; peer compare khóa |
| Session bị xóa | 404, thông báo |
| Refresh | State từ server (bất biến) |

## 11. Tracking
`review_open`, `summary_view`, `review_filter`, `review_question_open`, `retake_incorrect`, `flashcard_from_question`, `topic_practice_from_review`, `crosslink_click`, `peer_compare_view`.

## 12. Responsive
- Desktop: summary + chart cạnh nhau; review 2 cột (list + detail). Tablet: chart trên, breakdown dưới. Mobile: chart full, breakdown card, review detail full-screen, action bar sticky.

## 13. Security
- Chỉ owner (và instructor được phép) xem; snapshot không lộ dữ liệu người khác; sanitize nội dung.

## 14. Performance
- Summary cache bất biến; lazy chart; review list phân trang; snapshot version tránh query nặng.

## 15. Đề xuất cải tiến
- "Explain like I'm wrong": AI phân tích vì sao bạn chọn sai dựa trên lựa chọn.
- Timeline thời gian mỗi câu để phát hiện đoán/quá lâu.
- Tự động đề xuất bài đọc Library cho mỗi câu sai.
- Export review (PDF) để ôn offline.
