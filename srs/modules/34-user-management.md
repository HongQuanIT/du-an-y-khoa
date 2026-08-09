# Module 34 — User Management (Admin)

**Nhóm:** Admin · **Ưu tiên:** Cao · **Phụ thuộc:** RBAC (39), Audit (40), Subscription (28), Auth (02) · **Trạng thái:** ✅

## 0. Tóm tắt module
Quản lý người dùng toàn hệ thống: tìm kiếm, xem chi tiết, đổi vai trò, khóa/mở, đặt lại mật khẩu, quản lý subscription thủ công, impersonate, ghi audit.

| Route | Màn hình |
|-------|----------|
| `/admin/users` | Danh sách người dùng |
| `/admin/users/{id}` | Chi tiết người dùng |

## 1. Tổng quan
- **Mục đích:** Hỗ trợ vận hành & CSKH, xử lý vi phạm.
- **Đến từ:** Admin dashboard, search admin.
- **Đi sang:** Chi tiết user, audit, billing.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **User table** | Search, filter (role/status/plan/org), sort, phân trang server | `/admin/users` | Table → card |
| **Bulk actions** | Đổi role/khóa/xuất CSV | Table | — |
| **User detail** | Thông tin, subscription, hoạt động, thiết bị, audit liên quan | `/{id}` | Tabs |
| **Action menu** | Khóa/mở, reset password, verify email, đổi role, impersonate, gán Premium | Detail | — |
| **Confirm modals** | Xác nhận hành động nhạy cảm | Khi thao tác | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `UserTable`(server-side), `UserDetail`(tabs), `RoleSelector`, `SubscriptionOverride`, `ImpersonateButton`, `ConfirmDialog`, `BulkActionBar`.

## 4. Luồng người dùng
```
/admin/users → tìm email → mở detail → gia hạn Premium thủ công (CSKH) → audit ghi
Vi phạm → khóa tài khoản (lý do) → notification + audit.
Impersonate (Super Admin) → xem như user → banner cảnh báo → thoát.
```

## 5. Business Logic
- **Đổi role/status** ghi audit; giới hạn: admin không tạo super_admin.
- **Impersonate:** chỉ Super Admin, không thao tác billing khi impersonate, audit đầy đủ, banner.
- **Subscription override:** cấp/thu Premium thủ công (ghi lý do).
- **Reset password:** gửi link, không lộ mật khẩu.
- **Xóa/ẩn danh** tài khoản theo yêu cầu.
- **Scope:** admin tổ chức chỉ user org (nếu áp dụng).

## 6. Database
- `users`, `subscriptions`, `devices`, `audit_logs`. Search Meili (email/name) cho admin.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/users` | filter | list | `user.view` |
| GET | `/api/v1/admin/users/{id}` | — | detail | `user.view` |
| PATCH | `/api/v1/admin/users/{id}` | `{role,status}` | user | `user.manage` |
| POST | `/api/v1/admin/users/{id}/reset-password` | — | ok | `user.manage` |
| POST | `/api/v1/admin/users/{id}/subscription` | override | sub | `user.manage` |
| POST | `/api/v1/admin/users/{id}/impersonate` | — | token | `user.impersonate` (Super) |
| DELETE | `/api/v1/admin/users/{id}` | — | soft delete | `user.manage` |

## 8. State Management
- Server-side pagination/filter; audit ghi mọi mutate; không realtime.

## 9. Phân quyền
- Admin (`user.manage`); impersonate/super chỉ Super Admin; org_admin phạm vi org.

## 10. Edge Cases
- Tự khóa/hạ quyền chính mình → chặn; đổi role người có quyền cao hơn → chặn; xóa user có dữ liệu → ẩn danh; concurrent edit → optimistic lock; impersonate hết hạn → tự thoát.

## 11. Tracking (audit + product)
`admin_user_view`, `admin_user_role_change`, `admin_user_lock`, `admin_password_reset`, `admin_subscription_override`, `admin_impersonate_start/stop`.

## 12. Responsive
- Desktop tối ưu; mobile chủ yếu tra cứu/khẩn cấp.

## 13. Security
- RBAC + 2FA; audit bắt buộc mọi hành động; impersonate kiểm soát chặt + banner; không lộ PII thừa; rate limit; xác nhận hành động nguy hiểm.

## 14. Performance
- Search Meili; server pagination; index status/role/plan; bulk qua queue.

## 15. Đề xuất cải tiến
- Ghi chú CSKH per user; lịch sử hành động admin trên user; phát hiện tài khoản chia sẻ; công cụ merge tài khoản trùng.

## 16. Phạm vi triển khai & hạng mục hoãn

**Đã có (Phase 1):** list/filter users, chi tiết, đổi role/status, verify email, gửi reset password; Roles matrix; Audit UI.

**Hoãn — chưa làm ngay** (ưu tiên Phase 2 Question Management / nội dung học). Ghi lại để triển khai khi CSKH/Billing đủ nhu cầu:

| Hạng mục | Mục đích | Điều kiện nên làm |
|----------|----------|-------------------|
| **Impersonate** | Super Admin xem UI đúng như học viên để debug/CSKH; banner + audit; cấm billing | Khi thường xuyên cần tái hiện bug theo tài khoản |
| **Subscription override** | Cấp/thu Premium thủ công (khiếu nại, bồi hoàn, trial/partner); lý do + audit; tách `source=manual` khỏi cổng thanh toán | Khi Billing/Subscription đã chạy end-to-end |
| **Bulk actions** | Khóa/mở hoặc đổi role nhiều user; confirm + queue + cùng rule privilege | Khi quy mô user lớn và thao tác lặp lại thường xuyên |
| **Export CSV (Users)** | Xuất danh sách user cho báo cáo/CRM ngoài; kiểm soát PII + audit export | Khi có nhu cầu báo cáo vận hành thật |

Thứ tự gợi ý khi làm lại: Audit CSV (module 40) → Subscription override → Impersonate → Bulk.
