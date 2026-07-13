# Module 41 — Reports

**Nhóm:** Admin · **Ưu tiên:** Cao · **Phụ thuộc:** Analytics (19), Billing (29), Tracking (nền tảng) · **Trạng thái:** ✅

> 🔵 Báo cáo/scope theo **tổ chức** (`/org/reports`, segment theo org, quyền org_admin) thuộc **Phase 2** (Module Organization 32 đã hoãn). Phạm vi hiện tại: báo cáo toàn hệ thống cho Admin/Super Admin.

## 0. Tóm tắt module
Báo cáo & BI cho quản trị/tổ chức: người dùng, engagement, doanh thu, nội dung, học tập. Xây báo cáo, lọc, trực quan hóa, lên lịch & xuất (CSV/PDF/Excel).

| Route | Màn hình |
|-------|----------|
| `/admin/reports` | Trung tâm báo cáo |
| `/admin/reports/{type}` | Báo cáo cụ thể |
| `/admin/reports/builder` | Tạo báo cáo tùy biến |
| `/org/reports` | Báo cáo tổ chức (module 32) |

## 1. Tổng quan
- **Mục đích:** Ra quyết định dựa trên dữ liệu.
- **Đến từ:** Admin dashboard, Organization.
- **Đi sang:** Drill-down chi tiết, export.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Report catalog** | Danh mục báo cáo dựng sẵn | `/reports` | Grid |
| **Filter/date range** | Lọc theo thời gian/segment/cohort | Report | — |
| **Charts & tables** | Trực quan hóa (Chart.js) + bảng chi tiết | Report | Cuộn |
| **Report builder** | Chọn metric/dimension/filter → preview | `/builder` | Split |
| **Schedule panel** | Lên lịch gửi định kỳ (email) | Report | — |
| **Export** | CSV/PDF/Excel (async) | Report | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `ReportCatalog`, `ReportViewer`(chart+table), `ReportBuilder`(metric/dimension), `ScheduleForm`, `ExportButton`, `DrillDownTable`.

## 4. Luồng người dùng
```
/admin/reports → "Doanh thu" → range quý → chart MRR/churn → drill-down theo gói → export PDF
Builder → chọn metric "correct rate" × dimension "chủ đề" × filter cohort → lưu → lên lịch email tuần.
Org Admin → báo cáo tiến độ lớp (ẩn danh tổng hợp).
```

## 5. Business Logic
- **Nguồn:** rollup (`daily_stats`, `topic_mastery`), billing (`invoices/payments`), tracking aggregates, org data.
- **Read replica + cache**; báo cáo nặng chạy async (queue) → lưu snapshot.
- **Segment/cohort:** theo kỳ thi, org, gói, thời gian đăng ký.
- **Scope quyền:** admin toàn hệ thống; org_admin chỉ org; ẩn danh dữ liệu cá nhân trong báo cáo tổng.
- **Schedule:** cron gửi email định kỳ.
- **Export** async → file signed URL.

## 6. Database
- Đọc aggregates + billing + tracking; `report_definitions(id, name, config JSON, owner)`, `report_runs(id, definition_id, status, file_media_id, ran_at)`, `report_schedules`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/reports` | — | catalog | `report.view` |
| GET | `/api/v1/admin/reports/{type}` | filter | data | `report.view` |
| POST | `/api/v1/admin/reports/run` | config | job → snapshot | `report.view` |
| POST | `/api/v1/admin/reports/export` | config,format | job file | `report.export` |
| CRUD | `/api/v1/admin/reports/schedules` | — | schedule | `report.export` |

## 8. State Management
- Server: rollup cache + async runs; snapshot lưu; client filter/builder; không realtime (refresh nền).

## 9. Phân quyền
- Admin (`report.view/export`); Org Admin (scope org); Super Admin full (gồm doanh thu). Xem RBAC.

## 10. Edge Cases
- Dữ liệu lớn/range rộng → async + cảnh báo; cohort nhỏ → ẩn (privacy); export lỗi → retry; timezone → chuẩn hóa; số liệu lệch do cache → hiển thị "tính đến lúc...".

## 11. Tracking
`report_open`, `report_run`, `report_export`, `report_schedule_create`, `report_drilldown`.

## 12. Responsive
- Desktop tối ưu; mobile xem báo cáo tóm tắt.

## 13. Security
- RBAC nghiêm (đặc biệt doanh thu); ẩn danh dữ liệu cá nhân; export signed URL + hết hạn; audit export; scope org; không lộ dữ liệu chéo tổ chức.

## 14. Performance
- Read replica; rollup/pre-aggregate; async heavy reports; cache snapshot; downsample chart; index cho aggregate.

## 15. Đề xuất cải tiến
- Dashboard BI kéo-thả; cảnh báo KPI (alerting); dự báo doanh thu/churn; funnel & cohort retention; tích hợp kho phân tích ngoài (ClickHouse/BigQuery); insight ngôn ngữ tự nhiên (AI).
