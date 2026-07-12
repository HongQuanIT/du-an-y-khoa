# Module 33 — Admin Dashboard

**Nhóm:** Admin · **Ưu tiên:** Cao · **Phụ thuộc:** Reports (41), User Mgmt (34), Audit (40), Billing (29) · **Trạng thái:** ✅

## 0. Tóm tắt module
Bảng điều khiển vận hành cho Admin/Super Admin: KPI hệ thống (người dùng, doanh thu, nội dung, hiệu năng), cảnh báo, lối tắt quản trị. Layout admin riêng, tách khỏi khu học viên.

| Route | Màn hình |
|-------|----------|
| `/admin` | Dashboard quản trị |

## 1. Tổng quan
- **Mục đích:** Nhìn nhanh sức khỏe hệ thống & kinh doanh.
- **Đến từ:** Đăng nhập admin, avatar menu (admin).
- **Đi sang:** Các module quản trị.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Admin layout** | Sidebar quản trị + topbar | Luôn | Mobile: drawer |
| **KPI cards** | DAU/MAU, đăng ký mới, MRR, churn, câu hỏi published, report chờ xử lý | Luôn | Grid |
| **Charts** | Tăng trưởng user, doanh thu, engagement | Luôn | Chart |
| **Alerts panel** | Sự cố (queue backlog, payment failed spike, report tồn) | Khi có | List |
| **Recent activity/audit** | Hành động admin gần đây | Luôn | List |
| **Quick actions** | Tạo nội dung, mời admin, xem báo cáo | Luôn | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `AdminLayout`, `KpiCard`, `TrendChart`, `AlertsPanel`, `AuditFeed`, `QuickActions`.

## 4. Luồng người dùng
```
Admin login (2FA) → /admin → thấy report chờ 12 → sang Question Management xử lý
Thấy payment_failed tăng → sang Billing/Reports điều tra.
```

## 5. Business Logic
- **KPI** từ rollup/reports (read replica + cache), không query nặng realtime.
- **Alerts** theo ngưỡng (queue depth, error rate, report backlog, dunning).
- **Scope theo quyền:** Admin thấy phần được phép; Super Admin toàn bộ.

## 6. Database
- Đọc rollup/aggregate nhiều bảng; `audit_logs`; `system_metrics` (nếu lưu).

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/admin/dashboard` | KPI + alerts | Admin |
| GET | `/api/v1/admin/dashboard/charts?range=` | series | Admin |

## 8. State Management
- Rollup cache; refresh nền; alerts realtime (Reverb) tùy chọn; client range/tab.

## 9. Phân quyền
- Admin/Super Admin. Phân quyền chi tiết theo permission (vd admin nội dung không thấy billing).

## 10. Edge Cases
- Dữ liệu lớn → aggregate/cache; phần thiếu quyền → ẩn; metrics nguồn down → hiển thị "không khả dụng".

## 11. Tracking
`admin_dashboard_open`, `admin_alert_click`, `admin_quick_action`.

## 12. Responsive
- Desktop tối ưu; tablet dùng được; mobile hạn chế (chủ yếu xem KPI/alert).

## 13. Security
- Chỉ admin (RBAC + 2FA bắt buộc); audit truy cập; scope quyền; IP allowlist tùy chọn cho admin.

## 14. Performance
- Read replica + cache; downsample chart; alerts nhẹ.

## 15. Đề xuất cải tiến
- Cảnh báo chủ động (anomaly detection); tùy biến widget; drill-down nhanh; health board realtime.
