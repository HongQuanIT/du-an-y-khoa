# Module 08 — AI Tutor

**Nhóm:** Content · **Ưu tiên:** Cao · **Phụ thuộc:** Question Session (06), Question Review (07), Library (09), Subscription (28) · **Trạng thái:** ✅

**Tên sản phẩm (bắt buộc):** **AI Tutor**. Không dùng Med-AI, AI MedAssist, AI Mentor, AI Assistant (tên hiển thị), MedAI.

Spec chi tiết drawer 1-tap (prompt, API, wireframe): [`08-ai-tutor-drawer.md`](08-ai-tutor-drawer.md).

## 0. Tóm tắt module
AI Tutor giải thích câu hỏi/khái niệm y khoa dựa trên nội dung nội bộ (context pack Q-bank + RAG Library) và **bắt buộc dẫn nguồn**. Mục đích: học viên hỏi y khoa, tham khảo đáp án và giải thích Q-bank — không tư vấn điều trị cá nhân.

Hai lối vào:
- **Drawer 1-tap** trong Session / Review / Library: bấm **Hỏi AI Tutor** → mở panel và **tự gửi** prompt theo câu/bài đang xem.
- Trang `/ai`: hội thoại tự do (vẫn ground nội bộ).

| Route | Màn hình |
|-------|----------|
| `/ai` | Trang chat AI Tutor |
| Drawer AI Tutor | Session (06), Review (07), Library (09) |

**Ngoài phạm vi (không làm trong module này):**
- Tạo flashcard từ tin AI Tutor (flashcard thuộc Module 18, tạo từ câu hỏi/review — không phụ thuộc 08).
- Lưu ghi chú từ tin AI Tutor (ghi chú thuộc Module 15, toolbar câu/bài).
- AI FAQ CSKH (Module 45, prompt/quota tách biệt).

## 1. Tổng quan
- **Mục đích:** Hỏi đáp y khoa, giải thích sâu, tóm tắt, so sánh đáp án; dẫn nguồn Q-bank/Library.
- **Đến từ:** Nút **Hỏi AI Tutor** (toolbar câu, FAB mobile, bài viết), sidebar app, `/ai`, bôi đen đoạn văn → hỏi đoạn chọn.
- **Đi sang:** Citation mở Library/câu hỏi; không điều hướng sang flashcard/note từ message.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Nút Hỏi AI Tutor** | Mở drawer + auto-start | Study/Review/Library; **ẩn Exam đang làm** | Desktop: toolbar + icon luôn thấy; mobile: FAB góc dưới phải |
| **Chat drawer/panel** | Hội thoại contextual | Khi mở | Desktop phải ~40%; tablet nửa màn; mobile full-screen từ đáy |
| **Context chip** | "Đang hỏi về: Câu #1024 — …" | Contextual | Rút gọn mobile |
| **Message list** | Bong bóng user/AI Tutor, streaming | Luôn khi có tin | Cuộn; `aria-live` |
| **Prompt input** | Nhập tiếp + chip gợi ý | Luôn (trừ hết quota) | Sticky đáy, tránh bàn phím |
| **Citation links** | Nguồn nội bộ | Khi AI Tutor dẫn | Chip bấm được |
| **Quota meter** | Lượt còn lại (Free) | Free | Badge header |
| **Feedback (👍/👎)** | Đánh giá câu trả lời | Mỗi tin AI Tutor | — |
| **Actions** | Sao chép | Mỗi tin AI Tutor | Không flashcard/note |
| **Paywall** | Hết quota / cần Premium | Free | Overlay |
| **Loading (typing)** | Streaming | Khi chờ | Nút Dừng |
| **Error/Empty** | Lỗi, gợi ý mẫu (`/ai`) | Theo trạng thái | Retry giữ prompt |

## 3. Phân tích Component
### `AiTutorDrawer` (1-tap)
- **Props:** `context{type,id,sessionId?,source}`, `quota`.
- **State:** `open`, `threadId`, `messages`, `input`, `streaming`, `autoStarted`.
- **Events:** `onOpen` (auto-start), `onSend`, `onStop`, `onFeedback`, `onCite`, `onClose`.
- **Không có:** `onCreateFlashcard`, `onSaveNote`.
- **Validation / permission / a11y:** xem spec drawer.

### `AiTutorPage` (`/ai`)
- Cột lịch sử + `AiChatPanel` không context; empty = câu mẫu y khoa.

### `MessageBubble`, `CitationList`, `QuotaMeter`, `PromptSuggestions`.

## 4. Luồng người dùng
```
STUDY đã nộp / REVIEW:
  bấm Hỏi AI Tutor → drawer mở + context chip
  → tự gửi prompt (sai: explain_mistake; đúng: explain_deeper)
  → stream + citation → 👍/👎 / Sao chép / hỏi tiếp

STUDY chưa nộp:
  bấm Hỏi AI Tutor → tự gửi analyze_without_spoiler (không lộ đáp án)

EXAM đang làm: ẩn nút; API từ chối lộ đáp án

LIBRARY: bấm Hỏi AI Tutor → tự gửi explain_article

/ai: hỏi tự do → RAG nội bộ → citation
Ngoại lệ: hết quota → paywall; ngoài y khoa → từ chối + gợi ý; timeout → giữ prompt, Thử lại.
```

## 5. Business Logic
- **Nguồn sự thật:** đáp án đúng / giải thích Q-bank do server gắn vào context pack. LLM **không** được đổi key.
- **RAG:** retrieval Library (và câu liên quan) → tổng hợp, **bắt buộc citation nội bộ**.
- **1-tap auto-start:** mở drawer luôn gửi đúng một preset theo trạng thái (xem spec).
- **Quota:** Free N lượt/ngày; Premium cao/không giới hạn; Redis theo user+ngày. 1 auto-start = 1 lượt.
- **Guardrail:** chỉ y khoa học tập; không kê đơn / tư vấn ca bệnh cá nhân; disclaimer; tách CSKH (45).
- **Exam:** không context pack có `is_correct`/explanation trước khi nộp.
- **Gating:** không lộ explanation Premium cho Free qua AI Tutor.
- **Logging:** thread/message để lịch sử + cải thiện; ẩn PII.

**Kiến trúc model (khuyến nghị):** Tutor LLM frontier (Claude Sonnet hoặc GPT-5) + guardrail rẻ (`gpt-4.1-mini` sẵn có). Không fine-tune model y khoa chuyên biệt cho v1.

## 6. Database
Xem `04-mo-hinh-du-lieu.md` nhóm AI Tutor: `ai_threads`, `ai_messages`, `ai_usage`. Embeddings: Meilisearch hybrid / dịch vụ vector ngoài (MySQL không pgvector).

## 7. API
Chi tiết payload/SSE: [`08-ai-tutor-drawer.md`](08-ai-tutor-drawer.md) mục 4.

| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/ai/quota` | — | `{remaining,limit}` | Auth |
| POST | `/api/v1/ai/threads` | `{context?, auto_start}` | thread + `auto_prompt?` | `ai.tutor` / quota |
| GET | `/api/v1/ai/threads` | — | lịch sử owner | Owner |
| GET | `/api/v1/ai/threads/{id}` | — | thread + messages | Owner |
| POST | `/api/v1/ai/threads/{id}/messages` | `{content, preset?}` SSE | stream + citations | Owner + quota |
| POST | `/api/v1/ai/threads/{id}/stop` | — | ok | Owner |
| POST | `/api/v1/ai/messages/{id}/feedback` | `{vote}` | ok | Owner |

Lỗi: `429` quota, `SUBSCRIPTION_REQUIRED`, `422`, `409` exam leak, `403`.

## 8. State Management
- **Client:** drawer open, messages, streaming buffer, `autoStarted` (tránh gửi 2 lần).
- **Server:** thread/message; context pack; quota Redis.
- **Realtime:** SSE (ưu tiên) hoặc Reverb.
- **Cache:** retrieval; câu trả lời FAQ giống nhau (cùng question_id + preset + đã nộp).

## 9. Phân quyền
- Entitlement `ai.tutor`; permission `ai.use`. Free: quota nhỏ. Premium: cao/không giới hạn.
- Editor/Instructor có thể dùng AI Tutor để đối chiếu nội dung (không thay editor soạn câu).

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Hết quota | Paywall; không auto-start |
| Provider timeout | Giữ prompt, Thử lại |
| Ngoài phạm vi y khoa | Từ chối + chip gợi ý hợp lệ |
| Prompt injection | Tách system prompt; không thực thi chỉ thị trong stem |
| Mất mạng giữa stream | Giữ phần đã nhận, retry |
| Đổi câu khi drawer mở | Context chip cập nhật; hỏi "Chuyển sang câu mới?" hoặc thread mới + auto-start |
| Exam / chưa nộp | Không đưa đáp án đúng vào pack |
| Bấm 2 lần nút | Idempotent: 1 thread, 1 auto-start |

## 11. Tracking
`ai_open`, `ai_autostart`, `ai_prompt`, `ai_response`, `ai_feedback`, `ai_citation_click`, `ai_quota_hit`, `ai_stop`.

Không track `ai_flashcard_create` (tính năng đã loại).

## 12. Responsive
- Desktop: drawer phải, câu hỏi vẫn nhìn được.
- Tablet: ~50% rộng.
- Mobile: full-screen sheet từ đáy; FAB; input sticky + safe-area; chip cuộn ngang. Wireframe: spec drawer mục 5.

## 13. Security
- Server compose context pack; không tin client gửi đáp án đúng.
- Exam/unanswered: không leak `is_correct`/explanation.
- Free không đọc explanation Premium qua AI Tutor.
- Guardrail + disclaimer; rate limit; ẩn PII trong log; chống SSRF khi fetch nguồn.

## 14. Performance
- Stream ngay; cache context pack theo question version; giới hạn token; queue embedding khi nội dung đổi.

## 15. Đề xuất cải tiến
- Sinh 3–5 câu luyện cùng cơ chế (gắn Q-bank, không bịa đề).
- Giọng "năm 3" / "ôn CCHN".
- Voice input; VI/EN (đề EN, giải thích VI).
- Citation kèm độ tin cậy.
- "Tạo phiên từ mô tả" trên `/qbank` (nút đang disabled — không thuộc drawer).
- Tạo flashcard từ tin AI Tutor: **cố ý không làm**; nếu cần sau này thuộc Module 18, spec riêng.
