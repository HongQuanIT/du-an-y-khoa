# Template — Khung phân tích chuẩn cho mỗi Module

> Copy khung này khi viết SRS cho một module mới. Mỗi module bám đủ 15 mục. Nếu mục không áp dụng, ghi rõ "Không áp dụng" + lý do (không bỏ trống).

---

# Module XX — <Tên module>

**Nhóm:** … · **Ưu tiên:** … · **Phụ thuộc:** … · **Trạng thái:** ⬜/🟡/✅

## 0. Tóm tắt module
- Chức năng chính, giá trị mang lại, phạm vi bao gồm/không bao gồm.
- Danh sách màn hình/route trong module (bảng).

## 1. Tổng quan (cho từng màn hình)
- Tên màn hình · Mục đích · Khi nào dùng · Điều hướng đến từ đâu · Điều hướng sang đâu.

## 2. Phân tích giao diện
Liệt kê **toàn bộ** thành phần: Header, Sidebar, Menu, Breadcrumb, Search, Filter, Sort, Tabs, Button, Card, Widget, Table, List, Chart, Calendar, Progress, Pagination, Toast, Modal, Drawer, Floating Button, Footer, Tooltip, Empty/Loading/Error State.
Mỗi thành phần: Chức năng · Khi nào hiển thị · Khi nào ẩn · Điều kiện hoạt động · Responsive.

## 3. Phân tích Component
Mỗi component: Tên · Mục đích · Props · State · Events · Validation · Permission · Accessibility · Animation · Loading · Error · Empty · Disabled.

## 4. Luồng người dùng (User Flows)
Sơ đồ luồng chính + các nhánh ngoại lệ (happy path + edge).

## 5. Business Logic
Điều kiện hiển thị · khóa · Premium · logic điểm · tiến trình · gợi ý · AI · Adaptive · Recommendation · Bookmark · Highlight · Flashcard · Session · Continue Learning (những cái áp dụng).

## 6. Database
Entity liên quan (tham chiếu `04-mo-hinh-du-lieu.md`) + field/quan hệ/index đặc thù module.

## 7. API
Liệt kê endpoint (GET/POST/PUT/PATCH/DELETE): URL · Payload · Response · Validation · Error Code · Permission. Tham chiếu `05-api-conventions.md`.

## 8. State Management
Client state · Server state · Caching/Redis · Session · Pagination/Infinite scroll · Optimistic update · Realtime.

## 9. Phân quyền
Từng role làm được gì trong module (tham chiếu `03-phan-quyen-rbac.md`).

## 10. Edge Cases
Không internet · session hết hạn · subscription hết hạn · dữ liệu bị xóa · API lỗi · duplicate request · refresh · back · concurrent update · timeout.

## 11. Tracking
Event của module (tham chiếu `06-tracking-analytics.md`) + property.

## 12. Responsive
Khác biệt Desktop / Tablet / Mobile.

## 13. Security
Điểm bảo mật đặc thù (tham chiếu `07-security-performance.md`).

## 14. Performance
Điểm hiệu năng đặc thù (lazy load, cache, index, queue…).

## 15. Đề xuất cải tiến
UX/UI/Logic/Performance tốt hơn + tính năng mới.
