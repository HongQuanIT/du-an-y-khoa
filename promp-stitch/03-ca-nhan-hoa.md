# 03 — Cá nhân hóa (Notes, Bookmark, Highlight, Flashcards, Analytics, Weak Topics, Performance, Heatmap)

Prompt Stitch cho 8 module cá nhân hóa. **Đọc `00-he-thong-thiet-ke.md` và dán Prompt Theme trước.** Mỗi khối ``` là một màn hình.

---

## Module 15 — Notes (Ghi chú)
Ghi chú cá nhân gắn với câu hỏi/bài viết hoặc tự do.

### 15.1 Quản lý ghi chú (`/notes`)
```
Màn hình "Ghi chú của tôi" (route /notes), app shell sidebar + header, bố cục 2 cột.
- Cột trái: ô tìm kiếm ghi chú, bộ lọc theo loại nguồn (Câu hỏi/Bài viết/Thuốc/Tự do) và theo màu; danh sách thẻ ghi chú, mỗi thẻ có dải màu bên trái (vàng/xanh/hồng), trích nội dung tiếng Việt ("Nhớ tiêu chuẩn chẩn đoán STEMI: ST chênh ≥1mm ở ≥2 chuyển đạo liên tiếp"), chip nguồn liên kết ("Câu hỏi #1024 · Tim mạch"), thời gian "2 giờ trước".
- Cột phải: trình soạn ghi chú (rich text: đậm/nghiêng/danh sách, chọn màu), chip nguồn + nút "Về câu hỏi gốc", badge "Đã lưu" (autosave).
Trạng thái rỗng: minh họa + "Bạn chưa có ghi chú nào — tạo ghi chú khi làm bài hoặc đọc bài".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: danh sách trước, soạn thảo full-screen. Màu teal #0F766E.
```

### 15.2 Popover ghi chú nhanh (inline)
```
Popover "Ghi chú nhanh" nổi cạnh thanh công cụ câu hỏi/bài viết trong MebPro.
Nội dung: tiêu đề nhỏ "Ghi chú cho câu này", ô nhập rich text, hàng chọn màu (vàng/xanh lá/hồng/xanh dương), nút "Lưu" teal, badge "Tự động lưu". 
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: thành bottom sheet trượt từ đáy. Màu teal #0F766E.
```

---

## Module 16 — Bookmark (Đánh dấu/Lưu)
Lưu nhanh nội dung, tổ chức theo thư mục.

### 16.1 Quản lý bookmark (`/bookmarks`)
```
Màn hình "Đã lưu" (route /bookmarks), app shell, bố cục 2 cột.
- Sidebar thư mục bên trái: danh sách folder có màu và số lượng ("Tất cả 128", "Tim mạch 42", "Cần ôn lại 15", "Chưa phân loại 30"), nút "+ Tạo thư mục", kéo-thả sắp xếp.
- Khu vực chính: tab theo loại (Câu hỏi/Bài viết/Thuốc/Hình ảnh/Video); lưới thẻ nội dung đã lưu mỗi thẻ có badge loại, tiêu đề tiếng Việt, chuyên ngành, nút bỏ lưu; thanh hành động hàng loạt khi chọn nhiều "Đã chọn 8 — Chuyển thư mục · Tạo phiên ôn tập".
Trạng thái rỗng: "Chưa có mục nào được lưu — nhấn biểu tượng đánh dấu để lưu".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: thư mục thành drawer, thẻ 1 cột, vuốt để xóa. Màu teal #0F766E.
```

---

## Module 17 — Highlight (Tô sáng)
Tô sáng đoạn văn nhiều màu, tổng hợp để ôn nhanh.

### 17.1 Thanh chọn khi bôi text (selection toolbar)
```
Thanh công cụ nhỏ nổi lên khi người dùng bôi chọn một đoạn văn bản trong bài viết MebPro.
Nội dung: 4 chấm tròn chọn màu tô (vàng, xanh lá, hồng, xanh dương), nút "Ghi chú", nút "Sao chép", nút "Hỏi AI". Bo góc, đổ bóng nhẹ, xuất hiện ngay trên đoạn được chọn.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: thành thanh bottom sheet khi chạm-giữ chọn text. Màu teal #0F766E.
```

### 17.2 Tổng hợp highlight (`/highlights`)
```
Màn hình "Nội dung đã tô sáng" (route /highlights), app shell.
Khối chính: chú giải màu (Vàng = quan trọng, Đỏ/hồng = hay quên, Xanh = đã thuộc); bộ lọc theo màu và theo nguồn; danh sách highlight nhóm theo bài nguồn, mỗi mục hiển thị đoạn text đã tô đúng màu, tên bài nguồn ("Suy tim — mục Điều trị"), ghi chú kèm (nếu có), nút "Đến vị trí" và "Xóa".
Trạng thái rỗng: "Bạn chưa tô sáng nội dung nào".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: danh sách 1 cột. Màu teal #0F766E.
```

---

## Module 18 — Flashcards (Spaced Repetition)
Thẻ ghi nhớ với thuật toán lặp lại ngắt quãng.

### 18.1 Tổng quan flashcards (`/flashcards`)
```
Màn hình "Flashcards" (route /flashcards), app shell.
Khối chính:
(1) Banner đến hạn nổi bật: "🔔 20 thẻ đến hạn ôn hôm nay · 5 thẻ mới" + nút lớn "Bắt đầu ôn" teal.
(2) Lưới bộ thẻ (deck) mỗi thẻ hiển thị tên ("Dược lý tim mạch", "Chẩn đoán phân biệt", "Câu sai của tôi"), tổng số thẻ, số thẻ đến hạn (badge cam), số thẻ mới (badge xanh), progress ring. Nút "+ Tạo deck".
(3) Panel thống kê SRS: biểu đồ cột "Số thẻ đến hạn 7 ngày tới", chỉ số retention "89%", streak ôn thẻ.
Trạng thái rỗng: "Chưa có thẻ nào — tạo thẻ hoặc thêm từ câu hỏi".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: lưới 1 cột. Màu teal #0F766E, đến hạn #D97706.
```

### 18.2 Phiên ôn thẻ SRS (`/flashcards/review`)
```
Màn hình ôn flashcard (route /flashcards/review) toàn màn hình, tập trung, nền #F8FAFC.
Khối chính: thanh tiến trình phiên "12/20" trên cùng; thẻ lớn giữa màn hình hiển thị mặt trước (câu hỏi) "Cơ chế tác dụng của Metformin?"; nút "Hiện đáp án". Sau khi lật: hiện mặt sau (đáp án tiếng Việt) và 4 nút chấm độ nhớ có nhãn thời gian ôn lại: "Lại (<1 phút)" đỏ, "Khó (6 phút)" cam, "Tốt (1 ngày)" teal, "Dễ (4 ngày)" xanh lá. Nút phụ nhỏ: Sửa thẻ, Tạm ẩn.
Trạng thái hết thẻ: màn hình chúc mừng "Hết thẻ hôm nay 🎉 Bạn đã ôn 20 thẻ" + nút "Về Flashcards".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: thẻ full-width, nút chấm lớn hàng ngang, hỗ trợ vuốt. Màu teal #0F766E.
```

### 18.3 Quản lý bộ thẻ (`/flashcards/decks/{id}`)
```
Màn hình chi tiết deck "Dược lý tim mạch" (route /flashcards/decks/{id}), app shell.
Khối chính: header deck (tên, mô tả, tổng thẻ, đến hạn, nút "Ôn deck này", nút "Thêm thẻ"); bảng danh sách thẻ mỗi dòng: mặt trước, mặt sau (rút gọn), tag, trạng thái SRS (Mới/Đang học/Đã thuộc), lần ôn kế "còn 3 ngày", nhãn "Hay sai (leech)" nếu có; tìm kiếm + lọc; hành động hàng loạt (tạm ẩn, xóa, đổi deck).
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 18.4 Tạo/sửa thẻ (`/flashcards/create`)
```
Modal/màn hình "Tạo thẻ ghi nhớ" (route /flashcards/create) của MebPro.
Form: trình soạn Mặt trước (rich text) và Mặt sau (rich text) cạnh nhau với xem trước lật thẻ; chọn Bộ thẻ (dropdown), Thẻ tag (chip), liên kết nguồn "Từ câu hỏi #1024" (nếu có); nút "Tạo với AI" gợi ý sinh mặt trước/sau tự động (Premium); nút "Lưu thẻ" teal + "Lưu & tạo thẻ tiếp".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 2 mặt xếp dọc. Màu teal #0F766E, nhãn AI/Premium gradient vàng-cam.
```

---

## Module 19 — Study Analytics
Phân tích học tập tổng thể qua biểu đồ.

### 19.1 Trang phân tích (`/analytics`)
```
Màn hình "Phân tích học tập" (route /analytics), app shell sidebar + header.
Khối chính:
(1) Bộ chọn khoảng thời gian (7 ngày / 30 ngày / Tất cả / Tùy chọn) + nút "Xuất báo cáo" (Premium).
(2) Tab: Tiến trình · Chủ đề · Thời gian · So sánh cộng đồng.
(3) Hàng 4 KPI card: "Tổng câu đã làm 1.240", "Tỷ lệ đúng 72%", "Giờ học 48h", "Streak 15 ngày".
(4) Tab Tiến trình: biểu đồ đường kép (số câu/ngày + tỷ lệ đúng %) 30 ngày.
(5) Tab Chủ đề: biểu đồ cột ngang tỷ lệ đúng theo chuyên ngành (Tim mạch 85%, Hô hấp 70%, Dược lý 45% đỏ, Nội tiết 61%) + bảng chi tiết.
(6) Tab Thời gian: heatmap giờ học theo giờ-trong-ngày + biểu đồ giờ học theo ngày.
(7) Tab So sánh cộng đồng (Premium): biểu đồ percentile "Bạn Top 20%", so trung bình cohort — nếu Free thì mờ + nút "Nâng cấp".
Trạng thái rỗng: "Chưa đủ dữ liệu — học thêm để xem phân tích".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: KPI 2 cột, chart cuộn ngang, bảng → thẻ. Màu teal #0F766E, chart dùng palette teal/xanh dương.
```

---

## Module 20 — Weak Topics
Phát hiện & luyện chủ đề yếu bằng một chạm.

### 20.1 Danh sách chủ đề yếu (`/weak-topics`)
```
Màn hình "Chủ đề yếu" (route /weak-topics), app shell.
Khối chính: header "Tập trung vào điểm yếu để tiến bộ nhanh nhất"; bộ lọc theo chuyên ngành + sắp xếp (Ưu tiên/Yếu nhất/High-yield); danh sách chủ đề yếu mỗi dòng gồm: tên chủ đề ("Dược lý — Kháng sinh"), thanh tỷ lệ đúng màu đỏ-cam "45%", nhãn mức thành thạo (2/5), mini biểu đồ xu hướng (đang giảm ▼), số câu chưa làm "còn 84 câu", badge "High-yield", nút "Luyện ngay" teal; mở rộng dòng hiện "Bài đọc đề xuất" (chip bài Library) và "Hỏi AI vì sao yếu".
Trạng thái rỗng tích cực: "Tuyệt vời! Chưa phát hiện chủ đề yếu nào" hoặc "Làm thêm câu hỏi để hệ thống phát hiện điểm yếu".
Free chỉ hiện top 3 + PaywallOverlay "Xem tất cả chủ đề yếu với Premium".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: card, nút "Luyện ngay" sticky. Màu teal #0F766E, yếu #DC2626.
```

---

## Module 21 — Performance Dashboard
Bảng hiệu suất hướng "sẵn sàng thi".

### 21.1 Bảng hiệu suất (`/performance`)
```
Màn hình "Mức độ sẵn sàng thi" (route /performance), app shell.
Khối chính:
(1) Gauge lớn "Chỉ số sẵn sàng 62/100" nửa vòng cung màu chuyển đỏ→vàng→xanh, phụ đề "Cần cải thiện — còn 84 ngày đến kỳ thi".
(2) Thẻ điểm dự báo (Premium): "Điểm dự báo 68/100 (khoảng 62–74)" — mờ + nút "Nâng cấp" nếu Free.
(3) Lưới mức sẵn sàng theo chủ đề (Topic readiness grid): ô màu theo mức (Tim mạch xanh 85%, Hô hấp vàng 65%, Dược lý đỏ 45%, Nội tiết đỏ 50%...), click ô để luyện.
(4) Biểu đồ đường "Lịch sử điểm các kỳ thi mô phỏng" (55 → 62 → 68).
(5) Thanh "Mục tiêu vs Thực tế" so tiến độ với mục tiêu ngày thi.
(6) Danh sách "Hành động ưu tiên": "Luyện 100 câu Dược lý", "Đọc bài Suy tim", "Làm 1 kỳ thi mô phỏng".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: gauge trên, grid cuộn, chart full. Màu teal #0F766E, thang đỏ-vàng-xanh.
```

---

## Module 22 — Heatmap
Bản đồ nhiệt hoạt động học & mức thành thạo.

### 22.1 Trang heatmap (`/heatmap`)
```
Màn hình "Bản đồ nhiệt" (route /heatmap), app shell.
Khối chính: toggle 2 chế độ "Hoạt động học ↔ Mức thành thạo".
- Chế độ Hoạt động: lưới ô theo ngày kiểu GitHub contribution (52 tuần × 7 ngày), màu teal đậm dần theo số câu làm/ngày, bộ chọn năm "2026", chú giải thang "Ít → Nhiều", tổng "Đã học 186 ngày trong năm", hover ô hiện tooltip "10/07/2026 · 45 câu · 55 phút".
- Chế độ Mức thành thạo: ma trận chủ đề (hàng) × hệ cơ quan (cột), mỗi ô tô màu theo mastery 0–5 (đỏ→vàng→xanh), tooltip "Tim mạch/Nội khoa · Thành thạo 4/5 · 82%", click ô đỏ để "Luyện ngay".
Chú giải thang màu dưới cùng. Trạng thái rỗng: "Chưa có dữ liệu hoạt động".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: lưới cuộn ngang, tap hiện tooltip. Màu teal #0F766E, thang mastery đỏ-vàng-xanh.
```
