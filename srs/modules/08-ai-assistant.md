# Module 08 — AI Assistant (AI Tutor)

**Nhóm:** Content · **Ưu tiên:** Cao · **Phụ thuộc:** Question Session (06), Library (09), Subscription (28) · **Trạng thái:** ✅

## 0. Tóm tắt module
AI Tutor giải thích câu hỏi/khái niệm y khoa dựa trên nội dung nội bộ (retrieval-based, RAG) + dẫn nguồn. Truy cập dạng chat panel/drawer trong context (câu hỏi, bài viết) hoặc trang chat riêng.

| Route | Màn hình |
|-------|----------|
| `/ai` | Trang chat AI |
| Drawer AI | Trong Session/Library (contextual) |

## 1. Tổng quan
- **Mục đích:** Hỏi đáp, giải thích sâu, tóm tắt, tạo flashcard/câu hỏi từ nội dung.
- **Đến từ:** Toolbar câu hỏi, bài viết, sidebar, floating button.
- **Đi sang:** Cross-link tới Library/Question, tạo flashcard.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Chat drawer/panel** | Hội thoại | Khi mở AI | Full-screen mobile |
| **Message list** | Bong bóng user/AI, streaming | Luôn | Cuộn |
| **Prompt input** | Nhập câu hỏi + gợi ý nhanh (chips) | Luôn | Sticky đáy |
| **Context chip** | "Đang hỏi về câu #123 / bài X" | Contextual | — |
| **Citation links** | Nguồn dẫn (article/question) | Khi có | Inline |
| **Quota meter** | Số lượt còn lại (Free) | Free | Badge |
| **Feedback (👍/👎)** | Đánh giá câu trả lời | Mỗi message AI | — |
| **Actions** | Copy, tạo flashcard, lưu note | Mỗi message | Overflow |
| **Paywall** | Hết quota / Free chặn | Free | Overlay |
| **Loading (typing)** | Streaming/typing indicator | Khi chờ | — |
| **Error/Empty** | Lỗi AI, gợi ý câu hỏi mẫu | Theo trạng thái | — |

## 3. Phân tích Component
### `AiChatPanel`
- **Props:** `context?(type,id)`, `threadId?`, `quota`.
- **State:** `messages`, `input`, `streaming`, `remaining`.
- **Events:** `onSend`, `onStop`, `onFeedback`, `onCite`, `onCreateFlashcard`.
- **Validation:** input không rỗng, giới hạn độ dài; lọc nội dung không phù hợp.
- **Permission:** Premium/quota; server enforce.
- **A11y:** message có role log `aria-live`; nút stop khi streaming.
- **Loading:** typing; **Error:** retry; **Empty:** gợi ý prompt; **Disabled:** khi hết quota.

### `MessageBubble`, `CitationList`, `QuotaMeter`, `PromptSuggestions`.

## 4. Luồng người dùng
```
Trong Session → bấm AI → drawer mở với context câu hỏi → hỏi "giải thích vì sao B đúng"
 → AI stream trả lời + citation → 👍/👎 → tạo flashcard/lưu note
Trang /ai → hỏi tự do → RAG truy xuất nội dung nội bộ → trả lời có nguồn
Ngoại lệ: hết quota → paywall; câu hỏi ngoài phạm vi y khoa → từ chối lịch sự.
```

## 5. Business Logic
- **RAG:** truy xuất từ Qbank/Library (embeddings) → LLM tổng hợp, **bắt buộc dẫn nguồn nội bộ**, hạn chế bịa (guardrail y khoa).
- **Quota:** Free N lượt/ngày; Premium cao/không giới hạn; đếm theo user + reset.
- **Context injection:** đính kèm câu/bài đang xem.
- **Guardrails:** không đưa lời khuyên điều trị cá nhân; disclaimer; lọc nội dung nhạy cảm.
- **Actions:** tạo flashcard/note từ câu trả lời.
- **Logging:** lưu thread cho lịch sử + cải thiện.

## 6. Database
- `ai_threads(id,user_id,context_type,context_id,title,created_at)`, `ai_messages(id,thread_id,role,content,tokens,citations JSON,feedback,created_at)`, `ai_usage(user_id,date,count)`.
- Embeddings: `content_embeddings(source_type,source_id,vector)` (pgvector nếu dùng Postgres, hoặc dịch vụ vector; dự án đề xuất MySQL → dùng dịch vụ vector ngoài/Meili hybrid).

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| POST | `/api/v1/ai/threads` | `{context?}` | thread | Premium/quota |
| POST | `/api/v1/ai/threads/{id}/messages` | `{content}` (stream SSE) | message stream + citations | Premium/quota |
| POST | `/api/v1/ai/messages/{id}/feedback` | `{vote}` | ok | Auth |
| GET | `/api/v1/ai/threads` | — | lịch sử | Owner |

Lỗi: `429`(quota), `SUBSCRIPTION_REQUIRED`, `422`.

## 8. State Management
- **Client:** messages, streaming buffer.
- **Server:** thread/message DB; quota Redis (counter/day).
- **Realtime:** streaming SSE/WebSocket.
- **Caching:** cache embedding retrieval; cache câu trả lời cho câu hỏi FAQ giống nhau.

## 9. Phân quyền
- Premium/entitlement `ai.tutor`; Free quota nhỏ hoặc chặn. Editor/Instructor có thể dùng để soạn nội dung.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Hết quota | Paywall |
| AI provider timeout/down | Fallback thông báo, giữ prompt để retry |
| Nội dung ngoài phạm vi | Từ chối lịch sự + gợi ý |
| Prompt injection | Sanitize, tách system prompt |
| Mất mạng giữa stream | Giữ phần đã nhận, cho retry |

## 11. Tracking
`ai_open`, `ai_prompt`, `ai_response`, `ai_feedback`, `ai_citation_click`, `ai_flashcard_create`, `ai_quota_hit`.

## 12. Responsive
- Desktop: drawer bên phải. Tablet: drawer nửa màn. Mobile: full-screen chat, input sticky, bàn phím an toàn.

## 13. Security
- Không lộ nội dung Premium cho Free qua AI; server enforce entitlement; guardrail y khoa + disclaimer; chống prompt injection/SSRF; rate limit; ẩn PII trong log; kiểm duyệt output.

## 14. Performance
- Streaming để giảm cảm nhận chờ; cache retrieval; giới hạn token; queue cho tác vụ nặng (tóm tắt dài); batch embedding cập nhật khi nội dung đổi.

## 15. Đề xuất cải tiến
- "Explain my mistake" tự động theo lựa chọn sai.
- Sinh bộ câu hỏi luyện tập từ chủ đề yếu.
- Voice input; đa ngôn ngữ VI/EN.
- Trích dẫn có độ tin cậy (confidence) + link guideline gốc.
