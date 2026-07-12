# Module 31 — User Settings

**Nhóm:** Account · **Ưu tiên:** Trung bình · **Phụ thuộc:** Auth (02), Notification (27), Subscription (28) · **Trạng thái:** ✅

## 0. Tóm tắt module
Cài đặt tài khoản: bảo mật (mật khẩu, 2FA, phiên/thiết bị), thông báo, giao diện (theme, ngôn ngữ, font), quyền riêng tư, tùy chọn học tập, xóa tài khoản.

| Route | Màn hình (tabs) |
|-------|-----------------|
| `/settings/account` | Tài khoản (email, mật khẩu) |
| `/settings/security` | 2FA, thiết bị/phiên |
| `/settings/notifications` | Kênh thông báo |
| `/settings/appearance` | Theme/ngôn ngữ/font |
| `/settings/privacy` | Quyền riêng tư, dữ liệu |
| `/settings/learning` | Tùy chọn học (mode mặc định, timer, shuffle) |
| `/settings/danger` | Xóa/tạm ngưng tài khoản |

## 1. Tổng quan
- **Mục đích:** Kiểm soát trải nghiệm & bảo mật cá nhân.
- **Đến từ:** Avatar menu.
- **Đi sang:** Auth (đổi mật khẩu/2FA), Billing (subscription).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Settings nav (tabs)** | Điều hướng nhóm cài đặt | Luôn | Desktop bên trái; mobile: list → trang |
| **Account form** | Đổi email (verify), tên | Tab account | — |
| **Password form** | Đổi mật khẩu (yêu cầu mật khẩu cũ) | Tab security | — |
| **2FA setup** | Bật/tắt TOTP, recovery codes | Tab security | — |
| **Device/session list** | Thiết bị đăng nhập + thu hồi | Tab security | Table → card |
| **Notification prefs** | Bật/tắt per type × channel | Tab notifications | Toggle grid |
| **Appearance** | Theme (light/dark/system), locale, font size | Tab appearance | — |
| **Privacy** | Hồ sơ public, analytics opt-out, tải/xóa dữ liệu | Tab privacy | — |
| **Learning prefs** | Mode mặc định, timer, shuffle, hint | Tab learning | — |
| **Danger zone** | Tạm ngưng/xóa tài khoản (xác nhận) | Tab danger | Modal xác nhận |
| **Toast/Loading/Error** | Lưu thành công/lỗi | Theo trạng thái | — |

## 3. Phân tích Component
- `SettingsNav`, `ToggleGrid`(notification prefs), `ThemeSelector`, `DeviceList`(revoke), `TwoFactorSetup`, `DangerZone`(confirm typing), `DataExportButton`.

## 4. Luồng người dùng
```
Settings → Security → bật 2FA (quét QR + xác nhận + lưu recovery)
Settings → Notifications → tắt email reminder, giữ push
Settings → Privacy → yêu cầu tải dữ liệu (job) / xóa tài khoản (grace 30 ngày)
```

## 5. Business Logic
- **Đổi email** yêu cầu verify email mới trước khi áp dụng.
- **Đổi mật khẩu** cần mật khẩu cũ; đăng xuất phiên khác (tùy chọn).
- **2FA** enroll → confirm → recovery codes (hiện 1 lần).
- **Notification prefs** ghi `notification_preferences`.
- **Theme/locale/font** lưu `users.meta`/`locale`; áp dụng ngay (client) + server.
- **Learning prefs** ảnh hưởng Qbank/Session mặc định.
- **Xóa tài khoản:** soft delete + grace period → ẩn danh hóa dữ liệu (giữ attempt ẩn danh cho thống kê).

## 6. Database
- `users` (meta, locale, timezone), `notification_preferences`, `two_factor_secrets`, `devices/sessions`, `data_export_requests(user_id,status,file_media_id)`, `account_deletion_requests(user_id,scheduled_at)`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| PATCH | `/api/v1/settings/account` | `{name,email?}` | ok/verify | Owner |
| POST | `/api/v1/settings/password` | `{current,new}` | ok | Owner |
| POST/DELETE | `/api/v1/settings/2fa` | enroll/confirm/disable | codes/ok | Owner |
| GET/DELETE | `/api/v1/settings/sessions` | — | list/revoke | Owner |
| GET/PUT | `/api/v1/settings/notifications` | prefs | prefs | Owner |
| PUT | `/api/v1/settings/appearance` | `{theme,locale,font}` | ok | Owner |
| PUT | `/api/v1/settings/learning` | prefs | ok | Owner |
| POST | `/api/v1/settings/data-export` | — | job | Owner |
| POST | `/api/v1/settings/delete-account` | `{confirm}` | scheduled | Owner |

## 8. State Management
- Optimistic toggle; theme áp dụng ngay (localStorage + server); session list realtime tùy chọn; export/delete async.

## 9. Phân quyền
- Owner. Admin có thể reset (module 34) nhưng không xem mật khẩu; org_admin/admin bắt buộc 2FA.

## 10. Edge Cases
- Đổi email trùng → 409; sai mật khẩu cũ → 422; tắt 2FA cần xác thực lại; thu hồi phiên hiện tại → đăng xuất; hủy xóa tài khoản trong grace → khôi phục.

## 11. Tracking
`settings_open`, `password_change`, `2fa_enabled`, `2fa_disabled`, `session_revoke`, `preference_change`, `theme_change`, `data_export_request`, `account_delete_request`.

## 12. Responsive
- Desktop: nav trái + nội dung. Mobile: danh sách nhóm → mỗi nhóm 1 trang; toggle lớn.

## 13. Security
- Re-auth cho hành động nhạy cảm; recovery codes mã hóa; thu hồi phiên; xác nhận xóa (typing); audit; không lộ 2FA secret; rate limit đổi mật khẩu.

## 14. Performance
- Prefs cache; export/delete queue; ít tải.

## 15. Đề xuất cải tiến
- Passkeys; nhật ký hoạt động bảo mật; cảnh báo đăng nhập lạ; quản lý quyền dữ liệu (GDPR-like) đầy đủ; đồng bộ theme hệ điều hành.
