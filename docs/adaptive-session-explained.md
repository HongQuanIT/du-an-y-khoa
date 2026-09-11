# Phiên luyện thích ứng — Giải thích ngắn

**Đối tượng:** product, QA, instructor  
**Chi tiết kỹ thuật + migration:** [`adaptive-session-algorithm.md`](./adaptive-session-algorithm.md)

---

## Thích ứng là gì?

Học viên chọn **đề thi** + **hướng luyện**. Hệ thống tự chọn câu trong ma trận đề — không chọn độ khó / trạng thái thủ công.

> Câu yếu ôn nhiều hơn · Câu lâu chưa gặp được củng cố · Câu vừa vào session thì tạm tránh · Vẫn có random có trọng số.

---

## Ba hướng luyện

| Mode | Trọng số (yếu / củng cố) | Khi nào chọn |
|------|--------------------------|--------------|
| **Điểm yếu** | 85% / 15% | Sắp thi, vá lỗ hổng |
| **Cân bằng** *(mặc định)* | 55% / 45% | Luyện ngày thường |
| **Củng cố** | 30% / 70% | Chống quên kiến thức đã học |

---

## Ba tín hiệu (nói tiếng người)

| Tín hiệu | Ý | Dữ liệu chính |
|----------|---|----------------|
| **Weakness** | Hay sai không? | Đúng / sai (đã làm mượt) |
| **Memory** | Lâu chưa **gặp** chưa? | `last_seen_at` — gồm **đã làm hoặc bỏ qua** |
| **Cooldown** | Vừa bị đưa vào session? | `last_served_at` — giảm xác suất chọn lại ngay |

**Lưu ý:** Bỏ qua câu vẫn tính là “đã gặp” (Memory), nhưng **không** khiến câu bị đánh giá yếu hơn.

---

## Cách chọn câu (tóm tắt)

```text
Pool theo đề thi
  → dành chỗ cho câu chưa làm (nếu còn)
  → chấm điểm câu đã gặp theo mode
  → nhân cooldown
  → random có trọng số → N câu
```

### Ví dụ nhanh (cùng 2 câu, đổi mode)

| Câu | | Điểm yếu | Cân bằng | Củng cố |
|-----|--|----------|----------|---------|
| A — hay sai, mới làm | | cao | trung bình | thấp hơn |
| D — khá vững, 40 ngày chưa gặp | | thấp | gần A | **cao** |

Chi tiết số liệu và schema: xem file thuật toán.
