# Module 27 — Notification

**Nhóm:** System · **Ưu tiên:** Cao · **Phụ thuộc:** mọi module (nguồn sự kiện), Settings (31), Realtime · **Trạng thái:** ✅

## 0. Tóm tắt module
Hệ thống thông báo đa kênh (in-app, email, push) cho nhắc học, streak, kết quả, hệ thống, tổ chức. Trung tâm thông báo + realtime + tùy chọn kênh.

| Route | Màn hình |
|-------|----------|
| Notification bell/dropdown | Header |
| `/notifications` | Trung tâm thông báo |
| `/settings/notifications` | Tùy chọn kênh (module 31) |

## 1. Tổng quan
- **Mục đích:** Giữ người dùng quay lại & thông tin quan trọng.
- **Đến từ:** Bell header, email, push.
- **Đi sang:** Nội dung liên quan (deep link).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Bell + badge** | Số chưa đọc, mở dropdown | Header | — |
| **Dropdown** | 10 thông báo gần nhất + "xem tất cả" | Khi mở | Full-width mobile |
| **Notification center** | List đầy đủ + filter (all/unread/type) | `/notifications` | List |
| **Item** | Icon, tiêu đề, thời gian, trạng thái đọc, action | Luôn | — |
| **Bulk actions** | Đánh dấu đọc tất cả/xóa | Center | — |
| **Toast realtime** | Thông báo tức thời | Khi có event | Góc màn |
| **Empty/Loading/Error** | Chưa có; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
- `NotificationBell` (badge realtime), `NotificationDropdown`, `NotificationCenter`, `NotificationItem` (mark read on view), `ToastManager`.

## 4. Luồng người dùng
```
Streak sắp mất → push/email + in-app → click → mở Dashboard/Session
Kết quả exam chấm xong → notification → mở result
Đánh dấu đọc / xem tất cả / chỉnh kênh trong settings.
```

## 5. Business Logic
- **Loại thông báo:** reminder (streak, study plan), result (exam graded), content (bài mới), social/org (giao bài), system (bảo trì, billing).
- **Đa kênh** theo `notification_preferences` (per type × channel).
- **Realtime** in-app qua Reverb (`private-user.{id}`); email/push qua queue.
- **Digest** (gộp) để tránh spam (vd tổng hợp hằng ngày).
- **Read state**; **quiet hours**; **dedup** cùng loại.

## 6. Database
- `notifications` (mục 10 data model), `notification_preferences(user_id, type, channel, enabled)`, `push_tokens(user_id, token, platform)`.

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/notifications?filter=unread` | list | Owner |
| POST | `/api/v1/notifications/{id}/read` | ok | Owner |
| POST | `/api/v1/notifications/read-all` | ok | Owner |
| DELETE | `/api/v1/notifications/{id}` | 204 | Owner |
| GET/PUT | `/api/v1/notifications/preferences` | prefs | Owner |
| POST | `/api/v1/push-tokens` | đăng ký thiết bị | Owner |

## 8. State Management
- Realtime badge/list qua WebSocket; optimistic mark read; unread count cache; email/push qua queue; pagination.

## 9. Phân quyền
- Owner. Admin/Org gửi thông báo hệ thống/tổ chức (module 33/32) qua tính năng broadcast (kiểm soát quyền).

## 10. Edge Cases
- Offline → nhận khi online (in-app persist); push token hết hạn → dọn; trùng thông báo → dedup; email bounce → tắt kênh + cảnh báo; quiet hours → hoãn.

## 11. Tracking
`notification_receive`, `notification_open`, `notification_read`, `notification_dismiss`, `preference_change`, `reminder_click`.

## 12. Responsive
- Desktop: dropdown. Mobile: center full-screen, toast top; push OS-native.

## 13. Security
- Scope owner; deep link an toàn; không nhồi PII vào push payload; verify push token; broadcast cần quyền + audit.

## 14. Performance
- Fan-out qua queue; unread count cache; batch email/push; realtime nhẹ; dọn thông báo cũ (retention).

## 15. Đề xuất cải tiến
- Nhắc học thông minh theo thói quen (giờ hay học); nội dung nhắc cá nhân hóa (chủ đề yếu); tổng hợp tuần; kênh Zalo/Telegram (VN).
