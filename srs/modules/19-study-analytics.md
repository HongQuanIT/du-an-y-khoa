# Module 19 — Study Analytics

**Nhóm:** Personal · **Ưu tiên:** Cao · **Phụ thuộc:** Session (06), Weak Topics (20), Performance (21), Heatmap (22) · **Trạng thái:** ✅

## 0. Tóm tắt module
Trang phân tích học tập tổng thể: tiến trình theo thời gian, correct rate theo chủ đề, thời gian học, so sánh peer (Premium), dự báo mục tiêu. Nguồn dữ liệu từ rollup (`daily_stats`, `topic_mastery`).

| Route | Màn hình |
|-------|----------|
| `/analytics` | Trang phân tích tổng |
| `/analytics?tab=progress\|topics\|time\|peer` | Tab |

## 1. Tổng quan
- **Mục đích:** Hiểu hiệu quả học, ra quyết định ôn tập.
- **Đến từ:** Dashboard, sidebar, sau review.
- **Đi sang:** Weak Topics (luyện), Heatmap, Qbank (luyện chủ đề).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **KPI cards** | Tổng câu, correct rate, giờ học, streak | Luôn | Grid |
| **Progress chart** | Correct rate/khối lượng theo thời gian | Tab progress | Chart.js |
| **Topic performance** | Bar/table correct rate theo chủ đề | Tab topics | Cuộn |
| **Time analysis** | Giờ học theo ngày/giờ trong ngày | Tab time | Chart |
| **Peer comparison** | Percentile vs cohort (Premium) | Tab peer | Chart |
| **Date range filter** | 7d/30d/all/custom | Luôn | — |
| **Export** | CSV/PDF (Premium) | Luôn | — |
| **Empty/Loading/Error/Paywall** | Chưa đủ dữ liệu; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
- `KpiCard`, `TrendChart`, `TopicPerformanceChart`, `TimeOfDayChart`, `PeerCompareChart`, `DateRangePicker`, `ExportButton`.
- `TrendChart`: props `series`, `range`; lazy Chart.js; empty state khi thiếu dữ liệu.

## 4. Luồng người dùng
```
Dashboard → Analytics → chọn 30 ngày → thấy chủ đề "Dược" tụt → sang Weak Topics → luyện
Premium: xem percentile so cohort → điều chỉnh Study Plan.
```

## 5. Business Logic
- **Đọc rollup** (không query attempt trực tiếp): `daily_stats`, `topic_mastery`.
- **Correct rate, mastery, streak** theo công thức `06-tracking-analytics.md` mục 6.
- **Peer/percentile (Premium):** so cohort cùng kỳ thi/org.
- **Dự báo:** xu hướng → ước lượng đạt mục tiêu ngày thi.
- **Gating:** peer compare, export, dự báo = Premium.

## 6. Database
- Đọc `daily_stats`, `topic_mastery`; cohort aggregates cache Redis; export tạo file qua queue.

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/analytics/overview?range=` | KPI + trend | Auth |
| GET | `/api/v1/analytics/topics?range=` | performance theo topic | Auth |
| GET | `/api/v1/analytics/time?range=` | phân bố thời gian | Auth |
| GET | `/api/v1/analytics/peer?range=` | percentile | Premium |
| POST | `/api/v1/analytics/export` | job export | Premium |

## 8. State Management
- Server: rollup cache Redis; export async. Client: range/tab. Không realtime (refresh nền).

## 9. Phân quyền
- Student cơ bản; Premium nâng cao (peer, export, dự báo). Instructor xem tổng hợp lớp (module 32/41).

## 10. Edge Cases
- Chưa đủ dữ liệu → empty "học thêm để thấy phân tích"; cohort quá nhỏ → ẩn peer; range custom lớn → giới hạn/aggregate; export lỗi → thông báo.

## 11. Tracking
`analytics_open`, `analytics_range_change`, `analytics_tab_change`, `peer_compare_view`, `analytics_export`.

## 12. Responsive
- Desktop: nhiều chart cạnh nhau. Tablet: 2 cột. Mobile: stack, chart cuộn ngang, bảng → card.

## 13. Security
- Chỉ dữ liệu của user; peer ẩn danh (không lộ cá nhân khác); export scope owner.

## 14. Performance
- Rollup cache; lazy chart; giới hạn điểm dữ liệu (downsample); export queue.

## 15. Đề xuất cải tiến
- Insight bằng ngôn ngữ tự nhiên (AI: "bạn tiến bộ ở Tim mạch nhưng chững ở Dược").
- Dự báo điểm thi + khoảng tin cậy; mục tiêu SMART; nhắc điều chỉnh kế hoạch tự động.
