# 04 — Thi cử & Tìm kiếm (Exams, Self Assessment, Search, Global Search)

Prompt Stitch cho 4 module. **Đọc `00-he-thong-thiet-ke.md` và dán Prompt Theme trước.** Mỗi khối ``` là một màn hình. (Giao diện phòng thi dùng lại "Exam mode" ở file `01` — mục 6.2.)

---

## Module 23 — Exams (Mô phỏng thi)
Mô phỏng kỳ thi thật: đề, giới hạn thời gian, chấm & xếp hạng.

### 23.1 Danh sách đề thi (`/exams`)
```
Màn hình "Kỳ thi mô phỏng" (route /exams), app shell sidebar + header.
Khối chính: header "Luyện thi trong điều kiện thật"; bộ lọc theo loại (Mô phỏng chính thức / Theo chuyên ngành) và trạng thái (Chưa làm / Đã làm); lưới thẻ đề thi, mỗi thẻ gồm tên ("Mô phỏng thi Bác sĩ nội trú 2026", "Đánh giá năng lực Nội khoa", "Thi thử Dược lý"), số câu "150 câu", thời lượng "180 phút", điểm đạt "70%", badge độ khó, badge Premium nếu khóa, trạng thái ("Điểm cao nhất: 68%" nếu đã làm), nút "Bắt đầu" hoặc "Làm lại".
Trạng thái rỗng: "Chưa có kỳ thi nào khả dụng". Free: một số đề khóa + PaywallOverlay "Mô phỏng thi đầy đủ dành cho Premium".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: lưới 1 cột. Màu teal #0F766E, nhãn Premium gradient vàng-cam.
```

### 23.2 Chi tiết đề thi (`/exams/{id}`)
```
Màn hình giới thiệu đề thi (route /exams/{id}), app shell.
Khối chính: tiêu đề "Mô phỏng thi Bác sĩ nội trú 2026"; thẻ thông tin (150 câu · 180 phút · Điểm đạt 70% · Chế độ Exam không xem giải thích trong lúc thi); mô tả phạm vi chuyên ngành dạng chip (Nội, Ngoại, Sản, Nhi, Dược...); "Hướng dẫn làm bài" dạng danh sách; nếu đã làm thì hiện lịch sử điểm (bảng các lần làm với điểm & percentile); nút lớn "Bắt đầu thi" teal.
Modal "Kiểm tra trước khi thi" (Pre-exam checklist): "Đảm bảo kết nối ổn định", "Bạn có 180 phút", "Không thể tạm dừng sau khi bắt đầu", checkbox "Tôi đã sẵn sàng", nút "Vào phòng thi".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

### 23.3 Kết quả & xếp hạng (`/exams/{id}/result/{attemptId}`)
```
Màn hình "Kết quả kỳ thi" (route /exams/{id}/result/{attemptId}), app shell.
Khối chính:
(1) Thẻ điểm lớn: vòng tròn điểm "68/100", badge "ĐẠT" xanh (hoặc "CHƯA ĐẠT" đỏ), percentile "Top 15% (xếp 240/1.580 thí sinh)", thời gian làm "165/180 phút".
(2) Biểu đồ cột tỷ lệ đúng theo chuyên ngành (Tim mạch 82%, Dược lý 48% đỏ...).
(3) Bảng "Phân tích theo chủ đề" với nút "Ôn chủ đề yếu".
(4) Bảng xếp hạng (leaderboard) ẩn danh top 10 + vị trí của bạn được tô nổi.
(5) Thanh hành động: "Xem lại từng câu", "Ôn lại câu sai", "Cập nhật lộ trình học".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: điểm trên, chart full, bảng → thẻ. Màu teal #0F766E, đạt #16A34A, chưa đạt #DC2626.
```

---

## Module 24 — Self Assessment
Bài tự đánh giá năng lực, tạo baseline cho lộ trình học.

### 24.1 Danh sách / khởi động (`/self-assessment`)
```
Màn hình "Đánh giá năng lực" (route /self-assessment), app shell.
Khối chính: banner giới thiệu thân thiện "Kiểm tra trình độ hiện tại của bạn — không áp lực điểm số, chỉ để cá nhân hóa lộ trình"; thẻ các bài đánh giá (Đánh giá đầu vào tổng quát · 30 câu · ~40 phút; Đánh giá định kỳ Nội khoa · 20 câu); nếu đã làm trước đó thì hiện "Lần đánh giá gần nhất: 05/07/2026 — xem tiến bộ"; nút "Bắt đầu đánh giá" teal.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

### 24.2 Kết quả chẩn đoán (`/self-assessment/{id}/result`)
```
Màn hình "Kết quả đánh giá năng lực" (route /self-assessment/{id}/result), app shell, nhấn phân tích thay vì điểm.
Khối chính:
(1) Tiêu đề "Bản đồ năng lực của bạn"; biểu đồ radar (mạng nhện) mức thành thạo theo chuyên ngành (Tim mạch, Hô hấp, Tiêu hóa, Thần kinh, Nội tiết, Dược lý) so với "mức mục tiêu".
(2) Danh sách điểm mạnh (xanh) và điểm cần cải thiện (đỏ) kèm % .
(3) So sánh với lần đánh giá trước (nếu có): "Tiến bộ +8% ở Tim mạch".
(4) Thẻ đề xuất lộ trình học sinh ra từ kết quả.
(5) Nút lớn sticky "Tạo lộ trình học từ kết quả này" teal.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: radar full, danh sách stack. Màu teal #0F766E.
```

---

## Module 25 — Search (Tìm kiếm theo ngữ cảnh)
Tìm trong một khu vực (Library, Qbank, Notes...).

### 25.1 Kết quả tìm kiếm theo khu vực (`/library/search`, `/qbank?q=`...)
```
Màn hình kết quả tìm kiếm theo ngữ cảnh trong MedPro (ví dụ trong Thư viện, route /library/search?q=viêm phổi), app shell.
Khối chính: ô tìm kiếm lớn có nhãn phạm vi "Tìm trong Thư viện", đang chứa "viêm phổi", dropdown gợi ý typeahead khi gõ (Viêm phổi cộng đồng, Viêm phổi bệnh viện, Viêm phổi hít); cột facet trái (Loại nội dung, Chuyên ngành, Độ khó, Chỉ nội dung miễn phí); danh sách kết quả với từ khóa được tô đậm, badge loại; sắp xếp; phân trang.
Khi ô trống: hiện "Tìm kiếm gần đây" và "Phổ biến". Trạng thái không kết quả: "Không tìm thấy 'viêm phổi' — kiểm tra chính tả hoặc thử từ khóa khác".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: search overlay full-screen, facet bottom sheet. Màu teal #0F766E.
```

---

## Module 26 — Global Search (Command Palette)
Tìm kiếm toàn hệ thống bằng Cmd/Ctrl+K.

### 26.1 Command palette overlay (Cmd/Ctrl+K)
```
Overlay command palette tìm kiếm toàn cục của MedPro, xuất hiện giữa màn hình khi nhấn Cmd/Ctrl+K, nền phía sau tối mờ.
Hộp giữa màn hình bo góc, đổ bóng lớn: trên cùng là ô nhập lớn có icon kính lúp, placeholder "Tìm câu hỏi, bệnh, thuốc, hoặc gõ > để chạy lệnh...", đang gõ "nhồi máu". Bên dưới là kết quả nhóm theo loại, mỗi nhóm có tiêu đề nhỏ và icon:
- "Bệnh học": Nhồi máu cơ tim cấp, Nhồi máu não.
- "Câu hỏi": #1024 Đau ngực cấp ở nam 58 tuổi...
- "Thuốc": Aspirin, Clopidogrel.
- "Hình ảnh": ECG STEMI, Chụp mạch vành.
- "Của tôi": ghi chú "Tiêu chuẩn STEMI".
Mục đang chọn tô nền teal nhạt. Đáy hộp: gợi ý phím tắt "↑↓ để di chuyển · ↵ để mở · esc để đóng". Khi ô trống: hiện "Tìm kiếm gần đây" và "Hành động nhanh" (Tạo phiên luyện, Ôn flashcard, Đi tới Trang chủ).
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: overlay full-screen, bàn phím tự mở. Màu teal #0F766E, font Be Vietnam Pro.
```

### 26.2 Trang kết quả đầy đủ (`/search?q=`)
```
Màn hình kết quả tìm kiếm toàn hệ thống (route /search?q=nhồi máu), app shell.
Khối chính: ô tìm kiếm giữ từ khóa; tab lọc phạm vi (Tất cả · Câu hỏi · Bài viết · Bệnh · Thuốc · Ảnh · Video · Của tôi) kèm số lượng; danh sách kết quả nhóm theo loại với badge, tiêu đề tô đậm từ khóa, đoạn trích, chuyên ngành; nội dung Premium hiển thị kèm biểu tượng ổ khóa; phân trang / cuộn vô hạn.
Trạng thái không kết quả + gợi ý. Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: tab cuộn ngang, kết quả 1 cột. Màu teal #0F766E.
```
