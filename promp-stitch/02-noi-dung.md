# 02 — Nội dung (AI Assistant, Thư viện, Bệnh học, Dược, Thủ thuật, Ảnh, Video)

Prompt Stitch cho 7 module nội dung. **Đọc `00-he-thong-thiet-ke.md` và dán Prompt Theme trước.** Mỗi khối ``` là một màn hình.

---

## Module 08 — AI Assistant (AI Tutor)
Trợ lý AI giải thích câu hỏi/khái niệm y khoa, dẫn nguồn nội bộ.

### 8.1 Trang chat AI (`/ai`)
```
Màn hình "AI Tutor" (route /ai) của MedPro, app shell sidebar + header.
Bố cục 2 cột: cột trái hẹp là danh sách hội thoại cũ ("Giải thích STEMI", "Cơ chế Metformin", "Chẩn đoán phân biệt đau ngực") + nút "Cuộc trò chuyện mới". Cột phải là khu vực chat:
- Danh sách tin nhắn dạng bong bóng: tin người dùng bên phải nền teal nhạt, tin AI bên trái nền trắng có avatar robot; câu trả lời AI mẫu tiếng Việt giải thích vì sao STEMI thành trước, kèm khối "Nguồn tham khảo" liệt kê chip liên kết ("Bài: Nhồi máu cơ tim cấp", "Câu hỏi #1024"); mỗi tin AI có nút 👍/👎, Sao chép, "Tạo flashcard", "Lưu ghi chú".
- Chỉ báo AI đang gõ (typing dots) khi chờ.
- Ô nhập prompt sticky đáy có placeholder "Hỏi AI Tutor về y khoa...", hàng chip gợi ý nhanh ("Tóm tắt suy tim", "Tạo 5 câu hỏi Dược lý", "So sánh Amlodipin vs Losartan"), nút gửi teal.
- Với tài khoản Free: QuotaMeter "Còn 3/10 lượt hỏi hôm nay".
Trạng thái rỗng (chưa có hội thoại): minh họa robot + gợi ý các câu hỏi mẫu.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: ẩn cột trái (mở qua nút), chat full-screen, input sticky đáy. Màu teal #0F766E.
```

### 8.2 Drawer AI theo ngữ cảnh (trong Session/Library)
```
Drawer "Hỏi AI về câu hỏi này" trượt từ bên phải, phủ 40% màn hình, dùng trong màn làm bài/đọc bài MedPro.
Đầu drawer: chip ngữ cảnh "Đang hỏi về: Câu #1024 — Nhồi máu cơ tim" + nút đóng. Thân: hội thoại ngắn, người dùng hỏi "Vì sao đáp án B sai?", AI trả lời tiếng Việt kèm nguồn dẫn. Đáy: ô nhập + chip gợi ý ("Giải thích đơn giản hơn", "Cho ví dụ lâm sàng"). 
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: drawer full-screen từ đáy. Màu teal #0F766E.
```

---

## Module 09 — Medical Library
Thư viện bài viết y khoa liên kết chéo — "sách giáo khoa số".

### 9.1 Trang chủ thư viện (`/library`)
```
Màn hình "Thư viện y khoa" (route /library), app shell sidebar + header.
Khối chính: thanh tìm kiếm lớn "Tìm bệnh, thuốc, thủ thuật..." + bộ lọc loại nội dung (Bài viết/Bệnh học/Thuốc/Thủ thuật/Hình ảnh/Video); banner "Nội dung nổi bật" carousel; lưới thẻ duyệt theo chuyên ngành/hệ cơ quan có icon (Tim mạch, Hô hấp, Tiêu hóa, Thần kinh, Nội tiết, Truyền nhiễm...) mỗi thẻ ghi số bài "324 bài"; mục "Đọc tiếp" liệt kê bài đang đọc dở với thanh % đọc.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: lưới 2 cột → 1 cột. Màu teal #0F766E.
```

### 9.2 Kết quả tìm kiếm thư viện (`/library/search`)
```
Màn hình kết quả tìm kiếm thư viện (route /library/search?q=nhồi máu cơ tim), app shell.
Khối chính: ô tìm kiếm giữ từ khóa "nhồi máu cơ tim"; cột lọc trái (Loại nội dung, Chuyên ngành, Chỉ nội dung miễn phí); danh sách kết quả mỗi dòng có badge loại (Bệnh học/Thuốc), tiêu đề tô đậm từ khóa, đoạn trích, chuyên ngành; sắp xếp "Liên quan nhất/Mới nhất"; phân trang.
Trạng thái rỗng: "Không tìm thấy kết quả cho 'nhồi máu cơ tim' — thử từ khóa khác". Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bộ lọc thành bottom sheet. Màu teal #0F766E.
```

### 9.3 Trang bài viết (`/library/{slug}`)
```
Màn hình đọc bài viết y khoa (route /library/{slug}), app shell, bố cục 3 cột.
- Thanh tiến trình đọc mảnh trên cùng.
- Cột trái: mục lục (TOC) sticky liệt kê các đề mục bài, đề mục đang đọc tô teal.
- Cột giữa: nội dung bài "Suy tim" — tiêu đề, breadcrumb (Thư viện › Tim mạch › Suy tim), nội dung rich tiếng Việt gồm đoạn văn, bảng, callout "High-yield" nền vàng nhạt, hình ảnh có chú thích, đoạn được bôi vàng (highlight) sẵn.
- Cột phải: aside cross-link "Liên quan" — chip Thuốc điều trị (Furosemid, Enalapril), Câu hỏi liên quan (3), Hình ảnh (X-quang ngực).
- Thanh công cụ đọc nổi: cỡ chữ A-/A+, Bôi vàng, Ghi chú, Đánh dấu, Hỏi AI, In.
- Giữa bài (tài khoản Free): PaywallOverlay mờ dần "Đọc tiếp với Premium" + nút "Nâng cấp".
- Cuối bài: "Bài viết liên quan" dạng thẻ + phần Tham khảo.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: TOC thành bottom sheet, aside xuống dưới, toolbar thành menu .... Màu teal #0F766E.
```

---

## Module 10 — Disease Library
Bệnh học có cấu trúc chuẩn hóa + chẩn đoán phân biệt.

### 10.1 Duyệt bệnh (`/library/diseases`)
```
Màn hình "Bệnh học" (route /library/diseases), app shell.
Khối chính: tìm kiếm + lọc theo hệ cơ quan; cây/lưới phân loại theo hệ (Tim mạch, Hô hấp, Tiêu hóa...) mỗi nhóm mở ra danh sách bệnh (Nhồi máu cơ tim cấp, Suy tim, Tăng huyết áp; Viêm phổi cộng đồng, Hen phế quản...); mỗi bệnh có badge chuyên ngành và nhãn "High-yield" nếu trọng tâm. Nút "So sánh bệnh" (Premium).
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: lưới 2 cột → 1. Màu teal #0F766E.
```

### 10.2 Trang bệnh (`/library/diseases/{slug}`)
```
Màn hình chi tiết bệnh "Nhồi máu cơ tim cấp" (route /library/diseases/{slug}), app shell, bố cục 3 cột như trang bài viết.
- Cột trái: section navigator chuẩn (Định nghĩa · Dịch tễ · Sinh lý bệnh · Lâm sàng · Cận lâm sàng · Chẩn đoán phân biệt · Điều trị · Biến chứng · Tiên lượng), section đang xem tô teal.
- Header bệnh: tên + mã ICD "I21" + badge hệ Tim mạch + nhãn High-yield.
- Nội dung: các section cấu trúc tiếng Việt; bảng "Chẩn đoán phân biệt (DDx)" so sánh Nhồi máu cơ tim / Viêm màng ngoài tim / Bóc tách ĐM chủ theo đặc điểm phân biệt; hình ECG có chú thích.
- Cột phải cross-link: Thuốc điều trị (Aspirin, Clopidogrel, Atorvastatin), Thủ thuật (Can thiệp mạch vành PCI), Câu hỏi liên quan.
- Thanh công cụ đọc (Bôi vàng/Ghi chú/Đánh dấu/AI). PaywallOverlay giữa bài cho Free.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: section nav thành accordion, DDx table cuộn ngang. Màu teal #0F766E.
```

### 10.3 So sánh bệnh (`/library/diseases/compare`) — Premium
```
Màn hình "So sánh bệnh" (route /library/diseases/compare), app shell, tính năng Premium.
Khối chính: ô thêm bệnh để so sánh (chip "Viêm phổi cộng đồng", "Lao phổi", nút + thêm cột); bảng so sánh cạnh nhau các cột bệnh theo hàng thuộc tính (Định nghĩa, Tác nhân, Triệu chứng, Cận lâm sàng, Điều trị, Biến chứng), ô khác biệt được tô nhấn.
Nếu tài khoản Free: toàn màn hình phủ PaywallOverlay "Tính năng so sánh dành cho Premium" + nút "Nâng cấp" gradient vàng-cam.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: các cột chuyển thành thẻ cuộn ngang. Màu teal #0F766E.
```

---

## Module 11 — Drug Library
Thư viện thuốc + công cụ tra tương tác.

### 11.1 Duyệt thuốc (`/library/drugs`)
```
Màn hình "Thư viện thuốc" (route /library/drugs), app shell.
Khối chính: tìm kiếm thuốc + lọc theo nhóm dược lý; lưới nhóm dược lý (Kháng sinh, Chẹn beta, Ức chế men chuyển, Hạ đường huyết, Giảm đau...) mở ra danh sách thuốc mỗi dòng: tên gốc "Metformin", biệt dược "Glucophage", nhóm "Biguanide", badge. Nút nổi "Kiểm tra tương tác thuốc".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: 2 cột → 1. Màu teal #0F766E.
```

### 11.2 Trang thuốc (`/library/drugs/{slug}`)
```
Màn hình chi tiết thuốc "Metformin" (route /library/drugs/{slug}), app shell, bố cục 3 cột.
- Header thuốc: tên gốc Metformin, biệt dược (Glucophage, Glucofast), badge nhóm "Biguanide", nhãn High-yield.
- Section navigator: Cơ chế · Chỉ định · Chống chỉ định · Liều dùng · Tác dụng phụ · Tương tác · Theo dõi.
- Nội dung tiếng Việt: đoạn cơ chế; bảng liều dùng theo chỉ định/đối tượng (người lớn, suy thận) với cột Liều khởi đầu/Liều tối đa; danh sách chống chỉ định; cảnh báo tương tác.
- Cột phải cross-link: Bệnh điều trị (Đái tháo đường type 2), Câu hỏi Dược lý, Thuốc cùng nhóm.
- Thanh công cụ đọc. PaywallOverlay cho Free.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: accordion, bảng liều cuộn ngang. Màu teal #0F766E.
```

### 11.3 Kiểm tra tương tác thuốc (`/library/drugs/interactions`)
```
Màn hình "Kiểm tra tương tác thuốc" (route /library/drugs/interactions), app shell.
Khối chính: ô multi-select thêm thuốc dạng chip ("Warfarin", "Aspirin", "Amiodaron", nút + thêm thuốc), nút "Kiểm tra tương tác" teal; kết quả liệt kê từng cặp tương tác với badge mức độ (Nặng đỏ / Trung bình cam / Nhẹ vàng), mô tả cơ chế và khuyến nghị xử trí tiếng Việt (vd "Warfarin + Aspirin: tăng nguy cơ chảy máu — theo dõi INR").
Trạng thái: cần ≥2 thuốc mới kiểm tra được; rỗng "Chưa phát hiện tương tác đáng kể". Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: chip xuống dòng. Màu teal #0F766E, cảnh báo đỏ #DC2626 / cam #D97706.
```

---

## Module 12 — Procedures (Thủ thuật)
Thủ thuật lâm sàng từng bước kèm media.

### 12.1 Duyệt thủ thuật (`/library/procedures`)
```
Màn hình "Thủ thuật lâm sàng" (route /library/procedures), app shell.
Khối chính: tìm kiếm + lọc theo chuyên khoa; lưới thẻ thủ thuật (Đặt nội khí quản, Chọc dịch màng phổi, Đặt catheter tĩnh mạch trung tâm, Can thiệp mạch vành PCI...) mỗi thẻ có ảnh minh họa, chuyên khoa, thời lượng video, badge Premium nếu khóa.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: lưới co giãn 1 cột. Màu teal #0F766E.
```

### 12.2 Trang thủ thuật (`/library/procedures/{slug}`)
```
Màn hình chi tiết thủ thuật "Chọc dịch màng phổi" (route /library/procedures/{slug}), app shell.
Khối chính:
- Header: tên thủ thuật + chuyên khoa Hô hấp + nút "Xem video hướng dẫn".
- Section nav: Chỉ định · Chống chỉ định · Chuẩn bị · Các bước thực hiện · Biến chứng · Chăm sóc sau.
- Danh sách kiểm tra chuẩn bị (checklist tick): "Sát khuẩn tay", "Bộ chọc dịch vô khuẩn", "Siêu âm định vị"...
- Trình xem từng bước (step-by-step): các bước đánh số 1→8 tiếng Việt, mỗi bước có ảnh/video minh họa lớn, nút "Bước trước / Bước sau".
- Cột phải cross-link: Bệnh liên quan (Tràn dịch màng phổi), Câu hỏi, Video.
PaywallOverlay cho bước chi tiết/video với Free.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: các bước dạng slider vuốt ngang, checklist accordion. Màu teal #0F766E.
```

---

## Module 13 — Images (Atlas hình ảnh y khoa)
Atlas hình ảnh với viewer zoom + chú thích.

### 13.1 Duyệt/tìm ảnh (`/images`)
```
Màn hình "Atlas hình ảnh y khoa" (route /images), app shell.
Khối chính: thanh tìm kiếm + bộ lọc (Modality: X-quang, CT, MRI, Siêu âm, Mô bệnh học, Lâm sàng; Hệ cơ quan; Bệnh); lưới ảnh dạng masonry nhiều cột, mỗi ảnh có caption ngắn ("X-quang ngực: đông đặc thùy dưới phải", "ECG: ST chênh lên V1–V4"), badge modality, biểu tượng ổ khóa cho ảnh Premium; cuộn vô hạn.
Trạng thái loading blur-up placeholder. Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: 2 cột, pinch zoom. Màu teal #0F766E.
```

### 13.2 Xem chi tiết ảnh / Viewer (`/images/{id}`)
```
Màn hình viewer ảnh y khoa toàn màn hình (route /images/{id}) của MedPro, nền tối để nổi ảnh.
Khối chính: ảnh lớn giữa màn hình có zoom/pan; thanh công cụ trên: nút phóng to/thu nhỏ, bật/tắt lớp chú thích (annotation hotspot đánh dấu vùng tổn thương kèm nhãn tiếng Việt), chế độ so sánh "Bình thường / Bất thường" dạng slider, toàn màn hình, Đánh dấu, Tải xuống (Premium). Panel dưới/bên: caption đầy đủ, nguồn (credit), cross-link Bệnh liên quan (Viêm phổi) và Câu hỏi dùng ảnh.
Ảnh Premium hiển thị watermark mờ tên người dùng; nút tải xuống mờ + "Nâng cấp" cho Free.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: viewer full-screen, pinch-zoom, thông tin ở bottom sheet. Nền tối, nhấn teal #0F766E.
```

---

## Module 14 — Videos
Thư viện video bài giảng/hướng dẫn với player chapter + ghi chú timestamp.

### 14.1 Duyệt video (`/videos`)
```
Màn hình "Video bài giảng" (route /videos), app shell.
Khối chính: tìm kiếm + lọc (Chủ đề, Thời lượng, Giảng viên); lưới thẻ video mỗi thẻ có thumbnail với nhãn thời lượng "12:45" và thanh tiến trình đã xem màu teal, tiêu đề tiếng Việt ("Tiếp cận đau ngực cấp", "Đọc ECG cơ bản", "Kỹ thuật đặt nội khí quản"), tên giảng viên (TS. Trần Văn Minh), badge Premium nếu khóa; mục "Xem tiếp" hàng đầu.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: lưới co giãn → 1 cột. Màu teal #0F766E.
```

### 14.2 Trang xem video (`/videos/{id}`)
```
Màn hình xem video (route /videos/{id}), app shell, bố cục 2 cột.
- Cột chính: trình phát video lớn với đầy đủ điều khiển (play/pause, thanh seek có đánh dấu chương, tốc độ 1x/1.25x/1.5x, chất lượng, phụ đề tiếng Việt, toàn màn hình, PiP); dưới player là tiêu đề "Đọc ECG cơ bản", giảng viên, mô tả, thanh công cụ (Đánh dấu, Ghi chú, Chia sẻ). Tab dưới: "Chương", "Bản chép (transcript) có tìm kiếm", "Ghi chú của tôi".
- Cột phải: danh sách chương (Chương 1: Trục điện tim 00:00, Chương 2: Sóng P 03:20, Chương 3: ST-T 08:10) nhảy timestamp; panel ghi chú theo thời điểm ("03:12 — nhớ tiêu chuẩn chẩn đoán"); playlist "Video tiếp theo".
Free: player hiện đoạn preview rồi PaywallOverlay "Xem đầy đủ với Premium".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: player full-width, chương/transcript thành tab dưới. Màu teal #0F766E.
```
