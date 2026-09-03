# Module 45 — Support Chat

**Nhóm:** System · **Ưu tiên:** Cao · **Phụ thuộc:** Authentication (02), Billing (29), Notification (27), Admin (33) · **Trạng thái:** 🟡

## 0. Tóm tắt
Kênh chat hỗ trợ giữa người dùng và quản trị viên cho năm nhóm: **Tài khoản, Thanh toán, Khóa học, Lỗi hệ thống, Vấn đề khác**. AI CSKH là tuyến đầu cho FAQ ít rủi ro; mọi yêu cầu cần tra cứu, thay đổi dữ liệu, thanh toán hoặc AI không chắc chắn phải chuyển ngay sang nhân viên.

**Tách biệt AI Tutor (Module 08):** prompt, quota, thread CSKH không dùng chung với gia sư y khoa. Support không giải thích Q-bank.

| Route | Màn hình |
|---|---|
| `/support` | Hộp thư và khung chat của người dùng |
| `/admin/support` | Hàng đợi hỗ trợ của quản trị viên |
| `/admin/support/{id}` | Hội thoại xử lý bởi quản trị viên |

## 1. Luồng
```
Người dùng chọn danh mục → gửi tin nhắn → AI đánh giá an toàn/độ tin cậy
  ├─ trả lời được → AI phản hồi realtime, hội thoại vẫn mở
  └─ không trả lời được / cần dữ liệu cá nhân → waiting_admin → admin nhận hàng đợi
Admin nhận xử lý → chat realtime → đánh dấu resolved.
```

**Cơ chế phiên đề xuất (in-app messenger):** nút launcher nổi ở góc phải dưới mở hộp thoại ngay tại màn hình hiện tại, không điều hướng người học rời khỏi bài học. Khi mở, hệ thống ưu tiên hiển thị phiên còn mở cập nhật gần nhất; người học vẫn có thể tạo phiên mới nếu là một vấn đề độc lập. Mỗi phiên tương ứng một ticket, giữ nguyên danh mục và lịch sử để AI/admin không mất ngữ cảnh. Phiên `resolved` chỉ đọc; yêu cầu mới phải tạo phiên mới thay vì mở lại ticket cũ.

## 2. Giao diện & trạng thái
- User: tạo yêu cầu, danh sách hội thoại, message bubbles, trạng thái `ai_active`, `waiting_admin`, `admin_active`, `resolved`; mobile là một cột.
- Admin: bảng lọc theo trạng thái/danh mục, chi tiết lịch sử chat, ô phản hồi, nút đóng yêu cầu.
- Có loading/disabled khi gửi; empty state; retry khi WebSocket mất kết nối. Realtime không phải điều kiện duy nhất: tải lại vẫn lấy đủ lịch sử DB.

## 3. Business rules
- AI không bao giờ xác minh danh tính, đổi email/mật khẩu, hoàn tiền, sửa hóa đơn, quyết định quyền truy cập hoặc yêu cầu mật khẩu/OTP/thẻ.
- Khi AI timeout/provider lỗi hoặc trả lời không đủ tin cậy: tạo message thông báo và gắn `waiting_admin`.
- Admin trả lời sẽ nhận hội thoại (`assigned_admin_id`) và chuyển `admin_active`; chỉ admin mới được đóng.
- Người dùng chỉ đọc/ghi hội thoại của mình; staff có quyền quản lý hỗ trợ đọc/ghi mọi hội thoại.

## 4. Dữ liệu
- `support_conversations(id, user_id, assigned_admin_id?, category, status, subject?, last_message_at, resolved_at?, timestamps)`.
- `support_messages(id, support_conversation_id, sender_id?, sender_type[user|admin|ai|system], body, meta JSON?, timestamps)`.
- Chỉ số: index `(status,last_message_at)`, `(user_id,status)`, `(conversation_id,id)`.

## 5. API / realtime
| Method | URL | Quyền |
|---|---|---|
| GET | `/support` | Owner |
| POST | `/support` | Owner |
| POST | `/support/{id}/messages` | Owner |
| GET | `/admin/support` | Support admin |
| POST | `/admin/support/{id}/messages` | Support admin |
| POST | `/admin/support/{id}/resolve` | Support admin |

- WebSocket: presence channel `support-conversation.{id}` (message + typing whisper), private `support-admin` (badge/hàng đợi) qua Laravel Reverb.
- Channel authorization: owner hoặc staff; tuyệt đối không dùng public channel.

## 6. AI integration
- Dùng model cấu hình qua `OPENAI_API_KEY` và `OPENAI_SUPPORT_MODEL`; chỉ gửi danh mục và nội dung chat cần thiết.
- System prompt yêu cầu tiếng Việt, ngắn gọn, có tín hiệu `ESCALATE` khi không chắc chắn. Không log API key hay nội dung nhạy cảm.
- Có FAQ fallback hạn chế khi chưa cấu hình provider; các case khác luôn handoff.

## 7. Security, performance & tracking
- Rate limit gửi tin, giới hạn 4.000 ký tự, HTML được escape khi render; audit thao tác admin.
- Cảnh báo UI không gửi mật khẩu, OTP hoặc dữ liệu thẻ. Retention/xóa PII theo chính sách vận hành.
- `support_open`, `support_create`, `support_message_sent`, `support_ai_answer`, `support_ai_escalated`, `support_admin_reply`, `support_resolved`.
- Trang hàng đợi phân trang; broadcast payload chỉ chứa message tối thiểu.
