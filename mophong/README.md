# Thuật toán mẫu: chọn câu cho phiên ôn tập thích ứng

Bản tham chiếu đi kèm tài liệu "Đặc tả thuật toán chọn câu cho phiên ôn tập thích ứng".
TypeScript thuần, không phụ thuộc thư viện, không truy cập cơ sở dữ liệu.

| File | Nội dung |
| --- | --- |
| `adaptiveSession.ts` | Toàn bộ thuật toán: `applyAttempt()` (cập nhật sau một lượt làm) và `buildSession()` (dựng phiên) |
| `adaptiveSession.test.ts` | 12 test nghiệm thu, ứng với các tiêu chí ở mục 7 của đặc tả |
| `demo.ts` | Chạy thử 10 phiên Cân bằng × 10 câu trên bể 50 câu, 5 chủ đề |
| `khung-ly-thuyet-loc-phan-nhom-phan-suat.md` | Tóm tắt dễ hiểu + đánh giá khung Lọc → Phân nhóm → Phân suất |
| `final.md` | Phân tích hướng xử lý so với production |
| `ke-hoach-trien-khai.md` | Kế hoạch triển khai PHP + cảnh báo + làm lại adaptive log (đã ship V2) |

## Chạy

Yêu cầu Node.js 22.18 trở lên (chạy thẳng file `.ts`, không cần biên dịch).

```bash
node --test adaptiveSession.test.ts   # 12 test
node demo.ts                          # kịch bản 10 phiên
```

Khi đưa vào dự án có `tsc`, cài thêm `@types/node` cho file test.

## Ghép vào hệ thống

1. **Dựng phiên:** nạp `user_question_state` của học viên, danh sách câu (trạng thái phát hành, báo lỗi, `contentVersion`, chủ đề), trọng số chủ đề từ ma trận đề thi, và các câu đang nằm trong phiên chưa xong. Gọi `buildSession()`. Ghi `practice_sessions` và `session_items`, đồng thời cập nhật `last_selected_at` của các câu đã làm có trong phiên.
2. **Nộp câu trả lời:** gọi `applyAttempt()`. Ghi `log` vào `question_attempts` trước, rồi upsert `state` vào `user_question_state` (kèm `due_at = dueAt(state)`).
3. **Đóng phiên bỏ dở:** gọi `applyAttempt()` với `submitted: false` cho từng câu chưa nộp.

Mọi hằng số nằm trong `DEFAULT_PARAMS`. Khi hiệu chỉnh, đổi tham số, không sửa logic.
