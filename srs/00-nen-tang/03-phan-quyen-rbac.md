# 03 — Phân quyền (RBAC)

## 1. Mô hình phân quyền

- **RBAC** (Role-Based Access Control) + **ability/permission** chi tiết + **Policy** theo model.
- Người dùng có **1 role hệ thống**. *(🔵 Phase 2: role theo ngữ cảnh tổ chức — org member/org admin — cùng Module Organization đã hoãn.)*
- **Subscription** là lớp gating độc lập với role: xác định quyền truy cập nội dung Premium.
- Kết hợp: `access = role_permissions ∩ subscription_entitlements ∩ feature_flags`.

> 🔵 **Phạm vi hiện tại:** Các role **Instructor** và **Organization Admin** cùng mọi quyền liên quan tổ chức/lớp học (`org.*`, "tạo lớp/giao bài", "quản lý thành viên/ghế", "xem tiến độ học viên") **đã hoãn sang Phase 2** (theo Module Organization 32). Các dòng/cột tương ứng bên dưới giữ lại để tham khảo, đánh dấu 🔵.

## 2. Danh sách Role

| Role | Định danh | Ngữ cảnh |
|------|-----------|----------|
| Guest | `guest` | Chưa đăng nhập |
| Student (Free) | `student` | Mặc định sau đăng ký |
| Premium Student | `student` + subscription `premium` | Gating theo subscription |
| Content Editor | `content_editor` | Nội dung |
| Admin | `admin` | Toàn hệ thống (giới hạn cấu hình) |
| Super Admin | `super_admin` | Toàn quyền |
| 🔵 Instructor *(hoãn)* | `instructor` | Phase 2 — gắn Organization |
| 🔵 Organization Admin *(hoãn)* | `org_admin` | Phase 2 — phạm vi tổ chức |

> Premium là **entitlement** gắn với subscription, không phải role riêng — người dùng vẫn là `student` nhưng có `entitlement: premium`.

## 3. Ma trận quyền (Permission Matrix)

Chú thích: ✅ full · 🔓 giới hạn/preview · ➖ không có · 🔒 cần Premium

> Cột **Instructor** và **Org Admin** (🔵) thuộc Phase 2 — chưa áp dụng ở phạm vi hiện tại.

| Năng lực | Guest | Student | Premium | 🔵 Instructor | Content Editor | 🔵 Org Admin | Admin | Super Admin |
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
| 🔵 Tạo lớp, giao bài *(Phase 2)* | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ | ✅ |
| 🔵 Xem tiến độ học viên *(Phase 2)* | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ | ✅ |
| CRUD nội dung câu hỏi | ➖ | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ |
| Duyệt/publish nội dung | ➖ | ➖ | ➖ | ➖ | 🔓 | ➖ | ✅ | ✅ |
| 🔵 Quản lý thành viên tổ chức *(Phase 2)* | ➖ | ➖ | ➖ | ➖ | ➖ | ✅ | ✅ | ✅ |
| 🔵 Quản lý license/ghế *(Phase 2)* | ➖ | ➖ | ➖ | ➖ | ➖ | ✅ | ✅ | ✅ |
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
audit.view, report.export, cms.manage, feature_flag.manage
# 🔵 Phase 2 (Organization, chưa dùng): org.manage_members, org.manage_billing, org.view_reports
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
| Data | Global scope theo owner cho dữ liệu riêng *(scope theo org: 🔵 Phase 2)* |

## 7. Quy tắc gating Premium (UX nhất quán)

- Nội dung khóa hiển thị **preview mờ + nút "Nâng cấp"** (không ẩn hoàn toàn) → tăng conversion.
- Component dùng lại: `PaywallOverlay`, `UpgradeBadge`, `QuotaMeter`.
- Server luôn re-check entitlement (không tin FE).

## 8. Trường hợp đặc biệt

- **Impersonate**: chỉ Super Admin; ghi audit; banner cảnh báo; không thao tác billing khi impersonate.
- **Hết hạn subscription**: role giữ nguyên, entitlement Premium bị thu hồi ngay; dữ liệu cá nhân (notes, flashcards) vẫn giữ (read-only nếu vượt quota free).
- 🔵 *(Phase 2)* **Role kép** (Instructor + Org Admin) và **Downgrade tổ chức** (org hết license → thành viên về Free) — áp dụng khi bật Module Organization.
