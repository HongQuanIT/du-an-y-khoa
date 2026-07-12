# Module 39 — Role & Permission

**Nhóm:** Admin · **Ưu tiên:** Cao · **Phụ thuộc:** RBAC nền tảng (03), Audit (40), User Mgmt (34) · **Trạng thái:** ✅

## 0. Tóm tắt module
Giao diện quản lý vai trò & quyền: tạo/sửa role, gán permission, xem ma trận quyền, phân quyền cho user. Thực thi mô hình RBAC ở `00-nen-tang/03-phan-quyen-rbac.md`.

| Route | Màn hình |
|-------|----------|
| `/admin/roles` | Danh sách vai trò |
| `/admin/roles/{id}` | Chi tiết & gán permission |
| `/admin/permissions` | Danh mục permission |

## 1. Tổng quan
- **Mục đích:** Kiểm soát ai làm được gì, an toàn & linh hoạt.
- **Đến từ:** Admin dashboard/Settings hệ thống.
- **Đi sang:** User Management (gán role).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Role list** | Danh sách role + số user | List | Table |
| **Permission matrix** | Ma trận role × permission (toggle) | `/{id}` | Bảng lớn cuộn |
| **Role editor** | Tạo/sửa role, mô tả, clone | Detail | — |
| **Permission catalog** | Nhóm permission theo resource | `/permissions` | List |
| **User assignment** | Gán/gỡ role cho user | Detail | — |
| **Confirm modal** | Cảnh báo thay đổi quyền nhạy cảm | Khi lưu | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `RoleList`, `PermissionMatrix`(toggle, group), `RoleEditor`(clone), `PermissionCatalog`, `RoleUserAssign`, `ConfirmDialog`.

## 4. Luồng người dùng
```
Super Admin → tạo role "Reviewer" → gán permission (question.publish, library.publish)
 → gán user vào role → audit ghi → hiệu lực ngay (invalidate cache quyền).
```

## 5. Business Logic
- **Role/permission** chuẩn RBAC; permission dạng `resource.action`.
- **System roles** (student/admin/super_admin) bảo vệ (không xóa/hạ quyền lõi).
- **Chỉ Super Admin** quản lý role/permission (mặc định); admin bị giới hạn.
- **Cache quyền** invalidate khi thay đổi.
- **Nguyên tắc:** không cho tạo quyền vượt quyền bản thân (privilege escalation).
- **Audit** mọi thay đổi.

## 6. Database
- `roles`, `permissions`, `permission_role`, `role_user` (mục 2 data model).

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/roles` | — | list | `role.manage` |
| POST/PUT/DELETE | `/api/v1/admin/roles` | role | role | `role.manage` (Super) |
| GET | `/api/v1/admin/permissions` | — | catalog | `role.manage` |
| PUT | `/api/v1/admin/roles/{id}/permissions` | `{permission_ids}` | ok | `role.manage` |
| POST/DELETE | `/api/v1/admin/roles/{id}/users` | `{user_ids}` | ok | `role.manage` |

## 8. State Management
- Matrix optimistic toggle (confirm khi lưu); cache quyền invalidate; không realtime (trừ thông báo user bị đổi quyền).

## 9. Phân quyền
- Super Admin (mặc định). Admin có thể được cấp `role.manage` giới hạn. Xem RBAC.

## 10. Edge Cases
- Tự gỡ quyền của mình → chặn/cảnh báo; xóa role có user → yêu cầu chuyển; nâng quyền vượt bản thân → chặn; đổi quyền đang đăng nhập → áp dụng lần request kế/force re-auth.

## 11. Tracking (audit)
`role_create`, `role_update`, `role_delete`, `permission_change`, `role_assign_user`.

## 12. Responsive
- Desktop (ma trận lớn); mobile hạn chế.

## 13. Security
- Chỉ Super Admin; chống privilege escalation; audit bắt buộc; bảo vệ system roles; invalidate cache; 2FA bắt buộc.

## 14. Performance
- Cache quyền (Redis); matrix tải theo nhóm; ít thay đổi → cache mạnh.

## 15. Đề xuất cải tiến
- Quyền theo phạm vi (scope: org/lớp); role tạm thời (thời hạn); mô phỏng "user này thấy gì"; template role gợi ý; phê duyệt 2 người cho thay đổi nhạy cảm.
