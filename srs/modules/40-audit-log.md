# Module 40 — Audit Log

**Nhóm:** Admin · **Ưu tiên:** Cao (tuân thủ) · **Phụ thuộc:** mọi module admin, Security (nền tảng) · **Trạng thái:** ✅

## 0. Tóm tắt module
Nhật ký kiểm toán bất biến ghi lại hành vi nhạy cảm (ai, làm gì, khi nào, trước/sau) phục vụ điều tra, tuân thủ, bảo mật. Chỉ đọc; không sửa/xóa.

| Route | Màn hình |
|-------|----------|
| `/admin/audit` | Tra cứu audit log |
| `/admin/audit/{id}` | Chi tiết bản ghi |

## 1. Tổng quan
- **Mục đích:** Truy vết & trách nhiệm giải trình.
- **Đến từ:** Admin dashboard, chi tiết user/nội dung ("xem lịch sử").
- **Đi sang:** Đối tượng liên quan (user/question/...).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Audit table** | Filter (actor/action/đối tượng/thời gian/IP), search, phân trang | List | Table |
| **Detail drawer** | Before/after diff, metadata (IP, UA, request id) | `/{id}` | Drawer |
| **Export** | Xuất CSV cho điều tra | List | — |
| **Timeline view** | Theo đối tượng (lịch sử 1 entity) | Từ entity | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `AuditTable`(server filter), `AuditDetail`(JSON diff), `ExportButton`, `EntityTimeline`.

## 4. Luồng người dùng
```
Nghi ngờ đổi quyền bất thường → /admin/audit filter action=permission_change → xem before/after + actor + IP
Điều tra user → mở timeline hành động trên user đó → export CSV.
```

## 5. Business Logic
- **Ghi tự động** qua middleware/observer cho hành vi nhạy cảm: login admin, đổi role/permission, CRUD publish nội dung, thao tác billing/refund, impersonate, xóa dữ liệu, thay đổi cấu hình/feature flag.
- **Bất biến:** insert-only; không update/delete (retention theo chính sách, có thể archive).
- **Before/after** snapshot cho mutate.
- **Correlation id** liên kết chuỗi hành động.
- **Không chứa secret/PII thô** (mask).

## 6. Database
- `audit_logs` (mục 10 data model): `actor_id, action, auditable_type/id, before JSON, after JSON, ip, user_agent, request_id, created_at`. Index `(auditable_type,auditable_id)`, `(actor_id)`, `(action)`, `(created_at)`. Partition theo tháng; archive lạnh.

## 7. API
| Method | URL | Query | Response | Quyền |
|--------|-----|-------|----------|-------|
| GET | `/api/v1/admin/audit` | filter | list | `audit.view` |
| GET | `/api/v1/admin/audit/{id}` | — | detail | `audit.view` |
| GET | `/api/v1/admin/audit/entity/{type}/{id}` | — | timeline | `audit.view` |
| POST | `/api/v1/admin/audit/export` | filter | job CSV | `audit.view` |

Không có POST/PUT/DELETE sửa log (ghi qua hệ thống nội bộ).

## 8. State Management
- Server insert-only + read; export async; server pagination; không cache mạnh (dữ liệu điều tra cần tươi).

## 9. Phân quyền
- Admin/Super Admin (`audit.view`); Org Admin xem audit phạm vi org (tùy chọn). Không ai sửa.

## 10. Edge Cases
- Volume rất lớn → partition + archive + filter theo thời gian bắt buộc; đối tượng đã xóa → giữ log (bất biến); PII trong before/after → mask; export lớn → queue.

## 11. Tracking
- Bản thân là hệ thống tracking; truy cập audit cũng được ghi (`audit_view`).

## 12. Responsive
- Desktop tối ưu; mobile tra cứu khẩn.

## 13. Security
- Bất biến (chống chỉnh sửa); append-only; hạn chế quyền xem; mask secret/PII; toàn vẹn (hash chain tùy chọn); truy cập audit cũng bị audit; lưu trữ an toàn.

## 14. Performance
- Insert-only nhẹ (ghi async qua queue để không chặn); partition; index filter; archive lạnh; export queue.

## 15. Đề xuất cải tiến
- Hash chain/ký số đảm bảo toàn vẹn (tamper-evident); cảnh báo bất thường (nhiều thay đổi quyền); SIEM integration; giữ log theo yêu cầu pháp lý; diff trực quan đẹp hơn.
