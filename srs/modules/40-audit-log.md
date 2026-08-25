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
- **Bất biến:** insert-only trong thời gian lưu nóng; chỉ lệnh archive hệ thống được xóa theo lô sau khi file nén và checksum đã lưu thành công.
- **Before/after** snapshot cho mutate.
- **Correlation id** liên kết chuỗi hành động.
- **Không chứa secret/PII thô** (mask).

## 6. Database
- `audit_logs`: thêm `event_id` duy nhất để queue retry không ghi trùng; lưu actor/context/snapshot/IP/User-Agent và thông tin thiết bị đã chuẩn hóa.
- `user_activity_sessions`: dữ liệu presence đã gom theo user, browser session và khu vực; không trộn heartbeat vào Audit Log.
- `audit_archives`: manifest file JSONL gzip gồm phạm vi thời gian/ID, số dòng, disk/path và SHA-256.

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
- Sự kiện bảo mật, billing và quản trị ghi đồng bộ; sự kiện học tập/hồ sơ/lớp ít rủi ro vào Redis queue sau transaction commit.
- Heartbeat tối đa mỗi 120 giây khi tab hiển thị, chỉ cập nhật Redis; sau 300 giây idle scheduler gom thành một `user_activity_sessions`.
- MySQL giữ log nóng mặc định 180 ngày. `audit:archive` nén JSONL gzip, lưu SHA-256 và chỉ sau đó mới xóa theo batch. Disk/path và thời gian giữ được cấu hình bằng biến môi trường.
- Snapshot allow-list có giới hạn kích thước; danh sách dùng cursor pagination và chỉ eager-load actor cần hiển thị.

## 15. Đề xuất cải tiến
- Hash chain/ký số đảm bảo toàn vẹn (tamper-evident); cảnh báo bất thường (nhiều thay đổi quyền); SIEM integration; giữ log theo yêu cầu pháp lý; diff trực quan đẹp hơn.

## 16. Phạm vi triển khai & hạng mục hoãn

**Đã có:** lõi Audit dùng chung cho Admin/Giảng viên/Học viên; phân tầng immediate/queued; idempotency bằng `event_id`; action chuẩn theo nghiệp vụ; snapshot allow-list và mask secret/PII; actor role/portal/category/result/session; nhận diện thiết bị/OS/trình duyệt; UI `/admin/audit`; Activity heartbeat được gom qua Redis; archive lạnh theo batch có checksum. Phiên làm câu hỏi của học viên chỉ audit lúc bắt đầu và kết thúc, còn chi tiết đáp án nằm trong `question_attempts`.

**Hoãn — chưa làm ngay:**

| Hạng mục | Mục đích | Ghi chú |
|----------|----------|---------|
| **Export CSV** | Mang log ra ngoài cho điều tra/legal/sec; filter thời gian bắt buộc; job async nếu lớn; audit chính việc export | Ưu tiên hơn User CSV khi cần bằng chứng |
| **Timeline theo entity** (API chuyên sâu) | Endpoint timeline riêng cho tích hợp ngoài UI | UI đã lọc trực tiếp theo User/Question và User detail có “audit gần đây” |

Ưu tiên sản phẩm hiện tại: **Phase 2 Question Management (module 35)** trước các export/CSKH nâng cao.
