# Phiên luyện thích ứng — Giải thích ngắn

**Đối tượng:** product, QA, instructor  
**Chi tiết kỹ thuật + migration:** `[adaptive-session-algorithm.md](./adaptive-session-algorithm.md)`

App chọn câu bằng đường cong quên riêng từng câu: độ bền tăng ×2 khi đúng, giảm ×0.3 khi sai.

---

## Thích ứng là gì?

Học viên chọn **đề thi** + **hướng luyện**. Hệ thống tự chọn câu trong ma trận đề — không chọn độ khó / trạng thái thủ công.

> Câu yếu ôn nhiều hơn · Câu có khả năng còn nhớ thấp được củng cố · Câu vừa vào session thì tạm tránh · Vẫn có random có trọng số.

---

## Ba hướng luyện


| Mode                      | Trọng số (yếu / củng cố) | Khi nào chọn                |
| ------------------------- | ------------------------ | --------------------------- |
| **Điểm yếu**              | 85% / 15%                | Sắp thi, vá lỗ hổng         |
| **Cân bằng** *(mặc định)* | 55% / 45%                | Luyện ngày thường           |
| **Củng cố**               | 30% / 70%                | Chống quên kiến thức đã học |


---



## Ba tín hiệu 


| Tín hiệu     | Ý                       | Dữ liệu chính                                  |
| ------------ | ----------------------- | ---------------------------------------------- |
| **Weakness** | Hay sai không?          | Đúng / sai (đã làm mượt)                       |
| **Memory**   | Còn nhớ bao nhiêu?      | Độ bền `S` của câu đó × số ngày từ lần **chấm** gần nhất |
| **Cooldown** | Vừa bị đưa vào session? | `last_served_at` — giảm xác suất chọn lại ngay |


Độ bền tăng khi trả lời đúng (`×2`) và giảm khi trả lời sai (`×0.3`). Hai câu cùng 10 ngày chưa làm có mức nhớ khác nhau nếu một câu đúng nhiều lần và câu kia sai nhiều lần.

**Lưu ý:** Bỏ qua câu vẫn tính là “đã gặp”, nhưng **không** làm câu yếu hơn và **không** đổi độ bền. Đồng hồ quên chỉ chạy lại sau lần chấm đúng hoặc sai.

---



## Cách chọn câu (tóm tắt)

```text
Pool theo đề thi
  → dành chỗ cho câu chưa chấm (nếu còn)
  → chấm điểm câu đã chấm theo mode
  → nhân cooldown
  → random có trọng số → N câu
```



### Ví dụ nhanh (cùng 2 câu, đổi mode)


| Câu                            |     | Điểm yếu | Cân bằng   | Củng cố  |
| ------------------------------ | --- | -------- | ---------- | -------- |
| A — hay sai, vừa chấm          |     | cao      | ngang D    | thấp hơn |
| D — khá vững, đã quá độ bền |     | thấp     | ngang A    | **cao**  |


Chi tiết số liệu và schema: xem file thuật toán.