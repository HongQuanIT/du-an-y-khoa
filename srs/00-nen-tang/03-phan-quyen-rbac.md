# 03 — Phân quyền (RBAC)

## 1. Mô hình phân quyền

- **RBAC** (Role-Based Access Control) + **ability/permission** chi tiết + **Policy** theo model.
- Người dùng có **1 role hệ thống** + có thể có **role theo ngữ cảnh tổ chức** (org member/org admin).
- **Subscription** là lớp gating độc lập với role: xác định quyền truy cập nội dung Premium.
- Kết hợp: `access = role_permissions ∩ subscription_entitlements ∩ feature_flags`.

## 2. Danh sách Role

| Role | Định danh | Ngữ cảnh |
|------|-----------|----------|
| Guest | `guest` | Chưa đăng nhập |
| Student (Free) | `student` | Mặc định sau đăng ký |
| Premium Student | `student` + subscription `premium` | Gating theo subscription |
| Instructor | `instructor` | Được cấp bởi org/admin |
| Content Editor | `content_editor` | Nội dung |
| Organization Admin | `org_admin` | Phạm vi tổ chức |
| Admin | `admin` | Toàn hệ thống (giới hạn cấu hình) |
| Super Admin | `super_admin` | Toàn quyền |

> Premium là **entitlement** gắn với subscription, không phải role riêng — người dùng vẫn là `student` nhưng có `entitlement: premium`.

## 3. Ma trận quyền (Permission Matrix)

Chú thích: ✅ full · 🔓 giới hạn/preview · ➖ không có · 🔒 cần Premium

| Năng lực | Guest | Student | Premium | Instructor | Content Editor | Org Admin | Admin | Super Admin |
|----------|:----:|:-------:|:-------:|:----------:|:--------------:|:---------:|:-----:|:-----------:|
| Xem Landing | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Đăng ký/Đăng nhập | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Làm câu hỏi free | 🔓 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Toàn bộ Qbank | ➖ | 🔒 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Thư viện y khoa đầy đủ | 🔓 | 🔒 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| AI Assistant | ➖ | 🔒 | ✅ | ✅ | ✅ | ➖ | ✅ | ✅ |
| Analytics nâng cao | ➖ | 🔓 | ✅ | ✅ | ➖ | 🔓 | ✅ | ✅ |
| Flashcards/Notes/Highlight | ➖ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Exams/Self Assessment | ➖ | 🔒 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Tạo lớp, giao bài | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ | ✅ |
| Xem tiến độ học viên | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ | ✅ |
| CRUD nội dung câu hỏi | ➖ | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ |
| Duyệt/publish nội dung | ➖ | ➖ | ➖ | ➖ | 🔓 | ➖ | ✅ | ✅ |
| Quản lý thành viên tổ chức | ➖ | ➖ | ➖ | ➖ | ➖ | ✅ | ✅ | ✅ |
| Quản lý license/ghế | ➖ | ➖ | ➖ | ➖ | ➖ | ✅ | ✅ | ✅ |
| Quản lý toàn bộ user | ➖ | ➖ | ➖ | ➖ | ➖ | ➖ | ✅ | ✅ |
| Cấu hình hệ thống/feature flag | ➖ | ➖ | ➖ | ➖ | ➖ | ➖ | 🔓 | ✅ |
| Quản lý role & permission | ➖ | ➖ | ➖ | ➖ | ➖ | ➖ | 🔓 | ✅ |
| Xem Audit Log | ➖ | ➖ | ➖ | ➖ | ➖ | 🔓 | ✅ | ✅ |
| Billing hệ thống | ➖ | ➖ | ➖ | ➖ | ➖ | ➖ | 🔓 | ✅ |

## 4. Permission (ability) chi tiết — quy ước đặt tên

Định dạng: `{resource}.{action}` — ví dụ:

```
question.view, question.create, question.update, question.delete, question.publish
session.start, session.submit, session.review
library.view, library.edit, library.publish
user.view, user.manage, user.impersonate
role.manage, permission.manage
org.manage_members, org.manage_billing, org.view_reports
audit.view, report.export, cms.manage, feature_flag.manage
ai.use, analytics.advanced, exam.take, exam.manage
```

## 5. Subscription entitlements

| Entitlement | Mô tả |
|-------------|-------|
| `qbank.full` | Toàn bộ ngân hàng câu hỏi |
| `library.full` | Toàn bộ thư viện + liên kết chéo |
| `ai.tutor` | AI Assistant không giới hạn (hoặc quota cao) |
| `analytics.advanced` | Heatmap, dự báo, so sánh peer |
| `exam.simulation` | Mô phỏng thi đầy đủ |
| `offline.download` | Tải nội dung offline (nếu có) |

Free tier có quota giới hạn (vd: N câu/ngày, N lượt AI/ngày).

## 6. Cơ chế thực thi (Enforcement)

| Lớp | Cơ chế |
|-----|--------|
| Route | Middleware `auth`, `role:`, `can:`, `subscription:premium`, `feature:` |
| Controller/Action | Gọi `authorize()` qua Policy |
| Livewire/Blade | Directive `@can`, `@subscribed`, `@feature` để ẩn/hiện UI |
| API | Ability token (Sanctum) + Policy giống web |
| Data | Global scope theo org/owner cho dữ liệu riêng |

## 7. Quy tắc gating Premium (UX nhất quán)

- Nội dung khóa hiển thị **preview mờ + nút "Nâng cấp"** (không ẩn hoàn toàn) → tăng conversion.
- Component dùng lại: `PaywallOverlay`, `UpgradeBadge`, `QuotaMeter`.
- Server luôn re-check entitlement (không tin FE).

## 8. Trường hợp đặc biệt

- **Impersonate**: chỉ Super Admin; ghi audit; banner cảnh báo; không thao tác billing khi impersonate.
- **Role kép**: user vừa là Instructor vừa Org Admin → hợp quyền (union) trong phạm vi cho phép.
- **Hết hạn subscription**: role giữ nguyên, entitlement Premium bị thu hồi ngay; dữ liệu cá nhân (notes, flashcards) vẫn giữ (read-only nếu vượt quota free).
- **Downgrade tổ chức**: khi org hết license, thành viên rớt về Free nhưng giữ dữ liệu.
