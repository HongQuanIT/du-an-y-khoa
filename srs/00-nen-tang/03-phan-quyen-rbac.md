# 03 — Phân quyền (RBAC)

## 1. Mô hình phân quyền

- **RBAC** (Role-Based Access Control) + **ability/permission** chi tiết + **Policy** theo model.
- Người dùng có **1 role hệ thống**. *(🔵 Phase 2: role theo ngữ cảnh tổ chức — org member/org admin — cùng Module Organization đã hoãn.)*
- **Subscription** là lớp gating độc lập với role: xác định quyền truy cập nội dung Premium.
- Kết hợp: `access = role_permissions ∩ subscription_entitlements ∩ feature_flags`.

> 🔵 **Phạm vi hiện tại:** Quyền **tổ chức B2B** (`org.*`, giao bài org, ghế license…) **hoãn Phase 2** (Module 32). **Classroom (44)** + **role `instructor` + portal `/teach`** thuộc phạm vi hiện tại (đã chốt). Cột Org Admin và dòng org.* vẫn đánh dấu 🔵.

## 1.1 Ba portal (cùng guard `web`)

| Portal | Login | Ai vào | Không vào |
|--------|-------|--------|-----------|
| **Learner** | `/login` | `student` | staff, instructor, partner |
| **Instructor (Teach)** | `/teach/login` | `instructor` | student, staff CMS, partner |
| **Partner (CTV)** | `/partner/login` | `partner` | student, instructor, staff |
| **Admin** | `/admin/login` | `content_editor`, `admin`, `super_admin` | student, instructor, partner |

- Giảng viên **không** dùng layout học viên và **không** vào `/admin` (CMS/users/RBAC).
- Admin/Super Admin **không** vận hành lớp hàng ngày trên `/teach`; chỉ **giám sát** (`classroom.oversee`) trên `/admin`.
- CTV (Module 46) chỉ dùng `/partner`; Admin tạo/gán role `partner` tại `/admin/partners`.
- Chi tiết Classroom: `srs/modules/44-classroom-live-review.md` §16.

## 2. Danh sách Role

| Role | Định danh | Ngữ cảnh |
|------|-----------|----------|
| Guest | `guest` | Chưa đăng nhập |
| Student (Free) | `student` | Mặc định sau đăng ký |
| Premium Student | `student` + subscription `premium` | Gating theo subscription; có thể host lớp **cộng đồng** trên `/classes` |
| **Instructor** | `instructor` | Portal `/teach`; host lớp chữa đề vận hành (feedback QBank / exam); **không** phụ thuộc Premium |
| **Partner (CTV)** | `partner` | Portal `/partner`; mã mời + theo dõi referral + hoa hồng (Module 46) |
| Content Editor | `content_editor` | CMS nội dung (`/admin`) |
| Admin | `admin` | Quản trị + oversight lớp |
| Super Admin | `super_admin` | Toàn quyền + oversight |
| 🔵 Organization Admin *(hoãn)* | `org_admin` | Phase 2 — phạm vi tổ chức |

> Premium là **entitlement**, không phải role. Host cộng đồng (Premium student) ≠ Instructor vận hành. Instructor có `classroom.host` theo **role** (không mất khi hết Premium — họ không dựa Premium).

## 3. Ma trận quyền (Permission Matrix)

Chú thích: ✅ full · 🔓 giới hạn/preview · ➖ không có · 🔒 cần Premium · 👁 oversight only

> Cột **Org Admin** (🔵) vẫn Phase 2. Cột **Instructor** đã **chốt** (portal `/teach`).

| Năng lực | Guest | Student | Premium | Instructor | Content Editor | 🔵 Org Admin | Admin | Super Admin |
|----------|:----:|:-------:|:-------:|:----------:|:--------------:|:---------:|:-----:|:-----------:|
| Xem Landing | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Đăng ký/Đăng nhập | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Làm câu hỏi (learner UI) | 🔓 | ✅ | ✅ | ➖ | ➖ | ✅ | ➖ | ➖ |
| Toàn bộ Qbank (học) | ➖ | 🔒 | ✅ | 🔓 preview trong `/teach` | ✅ | ✅ | ✅ | ✅ |
| **Host lớp cộng đồng** (`/classes`) | ➖ | 🔒 | ✅ | ➖ | ➖ | ➖ | 🔓 | 🔓 |
| **Host lớp vận hành** (`/teach`) | ➖ | ➖ | ➖ | ✅ | ➖ | ➖ | 🔓 | 🔓 |
| **Tham gia lớp / live & VOD** | ➖ | ✅ | ✅ | 🔓 (preview) | ✅ | ✅ | ✅ | ✅ |
| **Oversight mọi lớp** (force-end, archive) | ➖ | ➖ | ➖ | ➖ | ➖ | ➖ | 👁 | 👁 |
| Gán role `instructor` | ➖ | ➖ | ➖ | ➖ | ➖ | ➖ | 🔓 | ✅ |
| 🔵 Tạo lớp org, giao bài *(Phase 2)* | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ | ✅ |
| 🔵 Xem tiến độ học viên org *(Phase 2)* | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ | ✅ |
| CRUD nội dung câu hỏi (working copy) | ➖ | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ |
| Gửi duyệt câu hỏi (submit lớp 1) | ➖ | ➖ | ➖ | ➖ | ✅ | ➖ | ✅ | ✅ |
| Duyệt chuyên môn câu hỏi (approve/reject lớp 1) | ➖ | ➖ | ➖ | ✅ | ➖ | ➖ | 👁 | 👁 |
| Publish câu hỏi lên Qbank (lớp 2, +version) | ➖ | ➖ | ➖ | ➖ | ➖ | ➖ | ➖ | ✅ |
| Duyệt/publish nội dung thư viện (khác Qbank) | ➖ | ➖ | ➖ | ➖ | 🔓 | ➖ | ✅ | ✅ |
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
question.view, question.create, question.update, question.delete, question.submit, question.review, question.publish, question.retire
session.start, session.submit, session.review
library.view, library.edit, library.publish
user.view, user.manage, user.impersonate
role.manage, permission.manage
audit.view, report.view, report.export, cms.manage, media.view, media.manage, contact.view, contact.manage, feature_flag.manage
notification.broadcast, support.manage, system.manage
classroom.create, classroom.create_on_behalf, classroom.manage, classroom.join, classroom.moderate, classroom.oversee
live.start, live.join, live.force_end, instructor.assign
# 🔵 Phase 2 (Organization, chưa dùng): org.manage_members, org.manage_billing, org.view_reports
ai.use, analytics.advanced, exam.take, exam.manage
```

### 4.1 Nhóm theo portal (admin UI)

Catalog `/admin/permissions` và ma trận role nhóm theo **4 portal** (không theo prefix resource):

| Portal | Roles | Permission chính |
|--------|-------|------------------|
| Học viên | `student` | `session.*`, `exam.take`, `classroom.join`, `live.join`, `question.view`, `library.view`, … |
| Giảng viên | `instructor` | `classroom.create/manage/moderate`, `live.start`, `question.review` (hàng đợi `/teach/questions/reviews`), … |
| Admin | `content_editor`, `admin`, `super_admin` | CMS, user/RBAC, oversight, billing, `admin.partners.*`, `notification.broadcast`, `support.manage`, … — **`question.publish` / `question.retire`: `admin` + `super_admin`**; **`question.submit` chỉ `content_editor`** |
| Cộng tác viên | `partner` | `partner.portal`, `partner.codes.manage`, `partner.referrals.view`, `partner.commissions.view` |

- Mỗi permission có **một portal chính** (catalog). Ability dùng chung vẫn hiện badge “cũng dùng bởi …” theo ma trận role.
- Tạo/đổi user: chọn **portal → role**; permission **không** gán trực tiếp trên user (lấy từ role).

| Permission Classroom | Ai có | Ý nghĩa |
|----------------------|-------|---------|
| `classroom.create` / `classroom.manage` / `live.*` (lớp mình) | **Instructor** (tạo trên `/teach`; lớp mới `pending_approval`) | Vận hành lớp mình host/cohost sau admin duyệt |
| `classroom.create_on_behalf` | Admin, Super Admin | Tạo lớp trên `/admin/classrooms/create`; **host_user_id** bắt buộc thuộc role `instructor` |
| `classroom.oversee` / `live.force_end` | Admin, Super Admin | Giám sát, **duyệt/từ chối** lớp, force-end — **không** thay workspace `/teach` |
| `instructor.assign` | Super Admin (Admin hạn chế) | Gán/thu hồi role `instructor` |

## 5. Subscription entitlements

| Entitlement | Mô tả |
|-------------|-------|
| `qbank.full` | Toàn bộ ngân hàng câu hỏi |
| `library.full` | Toàn bộ thư viện + liên kết chéo |
| `ai.tutor` | AI Tutor không giới hạn (hoặc quota cao) |
| `analytics.advanced` | Heatmap, dự báo, so sánh peer |
| `exam.simulation` | Mô phỏng thi đầy đủ |
| `offline.download` | Tải nội dung offline (nếu có) |
| `classroom.host` | Host lớp **cộng đồng** trên `/classes` (Premium student). Instructor **không** dựa entitlement này — có quyền host qua role trên `/teach`. Free vẫn join được. |

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
- **Hết hạn subscription**: role giữ nguyên, entitlement Premium (gồm `classroom.host` cộng đồng) bị thu hồi; dữ liệu cá nhân giữ. Lớp cộng đồng đã tạo: VOD/read-only; **không** start live mới trên `/classes`. **Instructor** không bị ảnh hưởng (host bằng role).
- 🔵 *(Phase 2)* **Role kép** (Instructor + Org Admin) và **Downgrade tổ chức** — khi bật Module Organization.
