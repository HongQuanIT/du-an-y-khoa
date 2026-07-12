# Module 22 — Heatmap

**Nhóm:** Personal · **Ưu tiên:** Trung bình · **Phụ thuộc:** Analytics (19), Weak Topics (20) · **Trạng thái:** ✅

## 0. Tóm tắt module
Bản đồ nhiệt trực quan: (a) hoạt động học theo ngày (kiểu GitHub contribution) và (b) mức thành thạo theo chủ đề × hệ cơ quan. Giúp nhìn nhanh thói quen & vùng yếu/mạnh.

| Route | Màn hình |
|-------|----------|
| `/heatmap` | Trang heatmap (2 chế độ) |
| Widget | Dashboard/Analytics |

## 1. Tổng quan
- **Mục đích:** Trực quan hóa hoạt động & năng lực để tạo động lực và định hướng.
- **Đến từ:** Dashboard, Analytics.
- **Đi sang:** Weak Topics / Qbank (click ô chủ đề → luyện).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Mode toggle** | Activity ↔ Mastery | Luôn | — |
| **Activity heatmap** | Lưới ngày × cường độ (streak) | Mode activity | Cuộn ngang |
| **Mastery matrix** | Chủ đề × hệ, màu theo mastery | Mode mastery | Cuộn |
| **Legend** | Thang màu | Luôn | — |
| **Cell tooltip** | Chi tiết ô (ngày/chủ đề) | Hover/tap | Popover |
| **Range/year selector** | Chọn năm/khoảng | Activity | — |
| **Empty/Loading/Error/Paywall** | Chưa đủ dữ liệu | Theo trạng thái | — |

## 3. Phân tích Component
- `ActivityHeatmap` (props days[]{date,count}), `MasteryMatrix` (props cells[]{topic,system,mastery}), `HeatmapLegend`, `CellTooltip`.
- Click cell mastery → `onPractice(topic)`.

## 4. Luồng người dùng
```
/heatmap (activity) → thấy chuỗi ngày học → chuyển Mastery → ô đỏ "Nội tiết" → click → luyện
```

## 5. Business Logic
- **Activity:** đếm hoạt động/ngày từ `daily_stats` → mức màu (0–4).
- **Mastery matrix:** từ `topic_mastery` map sang màu (0–5).
- **Streak** liên kết activity heatmap.
- **Gating:** Free xem activity + mastery cơ bản; Premium chi tiết hơn/nhiều năm.

## 6. Database
- Đọc `daily_stats` (activity), `topic_mastery` (matrix). Cache Redis.

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/heatmap/activity?year=` | days[] | Auth |
| GET | `/api/v1/heatmap/mastery` | cells[] | Auth |

## 8. State Management
- Server rollup cache; client mode/year; không realtime.

## 9. Phân quyền
- Student cơ bản; Premium chi tiết. Instructor: heatmap tổng hợp lớp (ẩn danh).

## 10. Edge Cases
- Chưa có dữ liệu → empty; năm không có hoạt động → lưới rỗng; nhiều chủ đề → matrix cuộn/nhóm.

## 11. Tracking
`heatmap_open`, `heatmap_mode_toggle`, `heatmap_cell_click`, `heatmap_practice`.

## 12. Responsive
- Desktop: lưới đầy đủ. Mobile: cuộn ngang, tap tooltip, matrix nhóm theo hệ.

## 13. Security
- Scope owner; tổng hợp lớp ẩn danh.

## 14. Performance
- Cache; render SVG/canvas hiệu quả; downsample; lazy.

## 15. Đề xuất cải tiến
- Kết hợp activity + mastery (ô vừa hiển thị hoạt động vừa năng lực); chia sẻ streak; gambadge theo chuỗi; so sánh với cohort.
