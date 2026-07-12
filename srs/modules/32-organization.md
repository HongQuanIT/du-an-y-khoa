# Module 32 — Organization (Tổ chức/Lớp học)

**Nhóm:** Account · **Ưu tiên:** Trung bình-Cao (B2B) · **Phụ thuộc:** RBAC (nền tảng), Subscription (28), Reports (41), Exams (23) · **Trạng thái:** ✅

## 0. Tóm tắt module
Quản lý tổ chức (trường/bệnh viện/trung tâm): thành viên, ghế license, lớp học/nhóm, giao bài/đề, theo dõi tiến độ, báo cáo tổ chức. Instructor & Org Admin thao tác trong phạm vi tổ chức.

| Route | Màn hình |
|-------|----------|
| `/org` | Dashboard tổ chức |
| `/org/members` | Thành viên & ghế |
| `/org/classes` | Lớp/nhóm |
| `/org/classes/{id}` | Chi tiết lớp (học viên, bài giao, tiến độ) |
| `/org/assignments` | Bài/đề đã giao |
| `/org/reports` | Báo cáo tổ chức |
| `/org/settings` | Cấu hình tổ chức |

## 1. Tổng quan
- **Mục đích:** Cho phép trường/bệnh viện quản lý học viên & đo hiệu quả.
- **Đến từ:** Lời mời tổ chức, avatar menu (nếu org_admin/instructor).
- **Đi sang:** Reports, Exams (giao đề), profile học viên.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Org dashboard** | KPI tổ chức (thành viên active, tiến độ TB, ghế dùng) | `/org` | Grid |
| **Member table** | Mời/xóa/đổi vai trò, trạng thái ghế | `/members` | Table → card |
| **Invite modal** | Mời email/CSV bulk | Members | — |
| **Seat meter** | Ghế đã dùng/tổng | Members | — |
| **Class list & detail** | Tạo lớp, thêm học viên, giao bài | `/classes` | — |
| **Assignment builder** | Chọn nội dung/đề + hạn nộp + giao cho lớp | `/assignments` | Wizard |
| **Progress dashboard** | Tiến độ/điểm học viên trong lớp | Class detail | Table/chart |
| **Reports** | Xuất báo cáo (module 41) | `/reports` | — |
| **Empty/Loading/Error/Paywall** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `OrgDashboard`, `MemberTable`(role change, seat), `InviteModal`(bulk CSV, validate), `SeatMeter`, `ClassManager`, `AssignmentBuilder`, `StudentProgressTable`.

## 4. Luồng người dùng
```
Org Admin → invite thành viên (CSV) → gán ghế Premium → tạo lớp → thêm học viên
Instructor → giao đề/bài cho lớp + hạn → theo dõi tiến độ → xuất báo cáo
Học viên nhận notification "được giao bài" → làm → điểm hiện trong lớp.
```

## 5. Business Logic
- **Ghế (seats):** entitlement Premium cấp cho thành viên khi có ghế; hết ghế → chặn/hàng chờ.
- **Vai trò org:** member/instructor/org_admin (khác role hệ thống).
- **Assignment:** giao nội dung/đề → tạo task/exam cho học viên + deadline + nhắc.
- **Progress rollup:** tổng hợp theo lớp (ẩn danh khi báo cáo tổng).
- **Data scoping:** mọi dữ liệu giới hạn trong org; instructor chỉ thấy lớp mình.
- **Billing tổ chức:** qua Subscription/Billing (org subscription).

## 6. Database
- `organizations`, `organization_members` (mục 2 data model), `classes(id,org_id,name,instructor_id)`, `class_members(class_id,user_id)`, `assignments(id,class_id,type,ref_id,due_at,created_by)`, `assignment_submissions(assignment_id,user_id,status,score,submitted_at)`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/org` | — | dashboard | Org member |
| GET/POST/DELETE | `/api/v1/org/members` | invite/remove | member | Org Admin |
| PATCH | `/api/v1/org/members/{id}` | `{org_role, seat}` | member | Org Admin |
| CRUD | `/api/v1/org/classes` | — | class | Instructor/Org Admin |
| POST | `/api/v1/org/classes/{id}/assignments` | `{type,ref,due}` | assignment | Instructor |
| GET | `/api/v1/org/classes/{id}/progress` | — | tiến độ | Instructor |
| GET | `/api/v1/org/reports` | filter | report/export | Org Admin |

## 8. State Management
- Server: org data scoped; seat count cache; progress rollup. Client: table filter. Realtime: presence lớp/exam (Reverb) tùy chọn. Bulk invite async.

## 9. Phân quyền
- **Org Admin:** quản lý thành viên/ghế/billing/báo cáo. **Instructor:** lớp mình, giao bài, xem tiến độ. **Member:** học viên. Data scope theo org (chống rò rỉ chéo tổ chức).

## 10. Edge Cases
- Hết ghế khi mời → chặn/hàng chờ; thành viên rời org → thu hồi entitlement, giữ dữ liệu cá nhân; xóa lớp có assignment → cảnh báo; CSV lỗi dòng → báo dòng lỗi; instructor xem ngoài lớp → 403.

## 11. Tracking
`org_invite`, `member_role_change`, `class_create`, `assignment_create`, `assignment_submit`, `org_report_export`, `seat_assign`.

## 12. Responsive
- Desktop: table + panel. Mobile: card, wizard giao bài từng bước, chart cuộn.

## 13. Security
- **Tenant isolation** nghiêm (org scope, chống IDOR chéo org); 2FA bắt buộc org_admin; audit thao tác thành viên/ghế/billing; privacy học viên (instructor xem tiến độ, không xem note riêng).

## 14. Performance
- Progress rollup async + cache; bulk invite queue; report export queue; index theo org_id.

## 15. Đề xuất cải tiến
- Bảng xếp hạng lớp; so sánh khóa/nhóm; tích hợp LMS (LTI); nhắc deadline tự động; dashboard giảng viên nâng cao (dự báo trượt học viên).
