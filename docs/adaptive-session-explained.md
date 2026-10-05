# Phiên luyện thích ứng — Giải thích ngắn

**Đối tượng:** product, QA, instructor  
**Chi tiết kỹ thuật:** [`adaptive-session-algorithm.md`](./adaptive-session-algorithm.md) · khung: `mophong/khung-ly-thuyet-loc-phan-nhom-phan-suat.md`

App chọn câu theo **Lọc → Phân nhóm → Phân suất**. Độ bền theo thang **1 · 3 · 7 · 14 · 30 · 60** ngày; còn nhớ `R = 0,9^(t/S)`.

---

## Thích ứng là gì?

Học viên chọn **đề thi** + **hướng luyện**. Hệ thống tự chọn câu trong ma trận đề — không chọn độ khó / trạng thái thủ công.

> Lọc câu không được phép · Chia giỏ Yếu / Sắp quên / Mới · Chia suất theo tồn đọng ôn · Ưu tiên phủ bài đang học dở.

---

## Ba hướng luyện

Mode chỉ đổi **suất ôn** (không đổi % câu mới):

| Mode | Suất ôn |
|---|---|
| **Điểm yếu** | Gần như toàn bộ → nhóm Yếu |
| **Cân bằng** *(mặc định)* | Khoảng một nửa Yếu + một nửa Sắp quên |
| **Củng cố** | Gần như toàn bộ → nhóm Sắp quên |

**Câu mới (mọi mode giống nhau)** — so `duePool` (số câu đến hạn) với `N` (số câu phiên):

| Tồn đọng | Điều kiện | % câu mới |
|---|---|---|
| Due thấp | `duePool < 1×N` | ~30% |
| Due vừa | `1×N ≤ duePool < 3×N` | ~20% |
| Due cao | `duePool ≥ 3×N` | ~10% |
| Học viên mới | chưa có câu đã chấm | 100% |

---

## Các tín hiệu chính

| Tín hiệu | Ý |
|---|---|
| **Yếu** | Sai nhiều trong 5 lần gần nhất (độ yếu ≥ 50%) |
| **Sắp quên** | Đã đến hạn (`t ≥ S`); tại hạn còn nhớ ~90% |
| **Thrash** | Sai ≥3 → 72h + 2 phiên; ≥5 → tạm không đưa vào phiên 7 ngày |
| **Bài dang dở** | Câu mới ưu tiên bài đã có câu làm trước |

**Đổi bậc S:** lần đầu đúng → 3 ngày; sai → 1 ngày; đúng đúng hạn → lên bậc; đúng sớm → giữ bậc.

**Lưu ý:** Bỏ qua câu / làm quá nhanh không làm câu yếu hơn và không đổi độ bền.

---

## Cách chọn câu (tóm tắt)

```text
Pool theo đề thi
  → lọc (báo lỗi / đang ở phiên khác / thrash / nghỉ 20 giờ)
  → nhóm Yếu · Sắp quên · Mới
  → suất mới 30/20/10 theo due + suất ôn theo mode
  → trong suất mới: phủ bài dang dở trước
  → N câu (+ lý do từng câu trên log admin)
```

Admin xem từng lần chọn tại `/admin/adaptive-briefing`.
