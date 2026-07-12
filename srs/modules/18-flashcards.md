# Module 18 — Flashcards (Spaced Repetition)

**Nhóm:** Personal · **Ưu tiên:** Cao · **Phụ thuộc:** Session (06), Review (07), Library (09), AI (08) · **Trạng thái:** ✅

## 0. Tóm tắt module
Thẻ ghi nhớ với thuật toán lặp lại ngắt quãng (SRS, kiểu SM-2). Tạo thủ công, từ câu hỏi/bài viết, hoặc AI. Ôn theo lịch "due", quản lý deck.

| Route | Màn hình |
|-------|----------|
| `/flashcards` | Tổng quan deck + due count |
| `/flashcards/review` | Phiên ôn (SRS) |
| `/flashcards/decks/{id}` | Quản lý deck |
| `/flashcards/create` | Tạo thẻ |

## 1. Tổng quan
- **Mục đích:** Ghi nhớ dài hạn hiệu quả.
- **Đến từ:** Toolbar câu/bài ("Add flashcard"), Dashboard (due), Study Plan task.
- **Đi sang:** Nội dung nguồn; kết thúc phiên → thống kê.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Deck grid** | Deck + số thẻ due/new | `/flashcards` | Grid → list |
| **Review player** | Front → reveal Back → chấm (Again/Hard/Good/Easy) | `/review` | Full-screen card |
| **Card editor** | Front/back rich, tag, deck, nguồn | Create/edit | Modal |
| **Progress bar phiên** | Còn lại/đã ôn | Review | — |
| **Stats panel** | Retention, streak SRS, dự báo tải | `/flashcards` | Chart |
| **Empty/Loading/Error** | Chưa có thẻ/hết due; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
### `FlashcardReviewPlayer`
- **Props:** `dueCards[]`.
- **State:** `index`, `revealed`, `grade`.
- **Events:** `onReveal`, `onGrade(again/hard/good/easy)`, `onEdit/onSuspend`.
- **Business:** cập nhật ease/interval/due theo SM-2.
- **A11y:** phím Space reveal, 1–4 chấm; **Loading/Empty:** "Hết thẻ hôm nay 🎉"; **Error:** retry giữ tiến độ.

### `CardEditor`, `DeckCard`, `SrsStats`, `AddFlashcardButton`.

## 4. Luồng người dùng
```
Câu sai (Review) → "Tạo flashcard" → auto front/back → lưu vào deck
Dashboard: "20 thẻ đến hạn" → /flashcards/review → reveal → chấm → cập nhật lịch
Hết due → gợi ý học thẻ mới / nghỉ.
```

## 5. Business Logic
- **SRS (SM-2):** `ease_factor`, `interval_days`, `repetitions`, `due_at`; grade điều chỉnh:
  - Again → reset repetitions, interval ngắn.
  - Hard/Good/Easy → tăng interval theo ease.
- **New vs due:** giới hạn thẻ mới/ngày (tránh quá tải).
- **Tạo từ nguồn:** link `source_type/id` để quay lại; AI sinh front/back.
- **Suspend/bury** thẻ; **leech** (thẻ hay sai) đánh dấu.
- **Continue Learning/Study Plan** đọc số due.

## 6. Database
- `flashcards`, `flashcard_reviews`(SRS state), `flashcard_decks` (mục 5 data model). Index `(user_id, due_at)`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/flashcards/due` | — | thẻ đến hạn | Owner |
| POST | `/api/v1/flashcards` | `{front,back,deck_id,source?,tags}` | card | Owner |
| POST | `/api/v1/flashcards/{id}/review` | `{grade}` | card cập nhật SRS | Owner |
| POST | `/api/v1/flashcards/from-question` | `{question_id}` | card | Owner |
| CRUD | `/api/v1/flashcard-decks` | — | deck | Owner |

## 8. State Management
- Review queue client; grade → optimistic + đồng bộ SRS; due count cache (invalidations sau review); không realtime.

## 9. Phân quyền
- Owner. Free: giới hạn số thẻ/deck; Premium: không giới hạn + AI tạo thẻ + deck chia sẻ. Instructor phát deck cho lớp.

## 10. Edge Cases
- Nguồn xóa → thẻ giữ; offline → ôn cache + sync grade; double grade → idempotent; đổi timezone → tính due theo tz user; import trùng → gộp.

## 11. Tracking
`flashcard_create`, `deck_create`, `flashcard_review`, `flashcard_from_question`, `review_session_finish`, `flashcard_suspend`.

## 12. Responsive
- Desktop: deck grid + review giữa. Mobile: full-screen card, swipe/tap chấm, bàn phím số.

## 13. Security
- Scope owner; sanitize rich; deck chia sẻ kiểm soát quyền; chống IDOR.

## 14. Performance
- Due query index `(user_id,due_at)`; prefetch queue; batch grade; cache due count.

## 15. Đề xuất cải tiến
- Cloze deletion (điền khuyết); image occlusion; FSRS thay SM-2 (tối ưu retention); deck cộng đồng có rating; đồng bộ Anki (import/export).
