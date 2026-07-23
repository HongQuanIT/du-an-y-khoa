# 06 — Quản trị & Nền tảng (Admin Dashboard, Managements, RBAC, Audit, Reports, CMS, API)

Prompt Stitch cho 11 module quản trị. **Đọc `00-he-thong-thiet-ke.md` và dán Prompt Theme trước.** Mỗi khối ``` là một màn hình.

> **Lưu ý shell quản trị:** Mọi màn hình admin dùng **App shell quản trị riêng** — sidebar quản trị (menu: Tổng quan, Người dùng, Câu hỏi, Thư viện, Media, Kỳ thi, Phân quyền, Nhật ký, Báo cáo, CMS, API) + topbar admin (tìm kiếm, thông báo, avatar admin, nhãn "Chế độ quản trị"). Giữ màu teal #0F766E, font Be Vietnam Pro; giao diện mật độ cao, nhiều bảng dữ liệu có sort/lọc/phân trang/hành động hàng loạt.

---

## Module 33 — Admin Dashboard

### 33.1 Dashboard quản trị (`/admin`)
```
Màn hình "Bảng điều khiển quản trị" (route /admin), dùng App shell quản trị (sidebar quản trị + topbar admin).
Khối chính:
(1) Hàng KPI card: "Người dùng hoạt động (DAU) 38.240", "Đăng ký mới hôm nay 420", "Doanh thu tháng (MRR) 1,24 tỷ₫", "Tỷ lệ rời bỏ (churn) 3,2%", "Câu hỏi đã xuất bản 12.450", "Báo lỗi câu hỏi chờ xử lý 24" — mỗi thẻ có icon, số lớn và delta % so kỳ trước.
(2) Biểu đồ: đường tăng trưởng người dùng 30 ngày, cột doanh thu theo tháng, đường mức độ tương tác.
(3) Panel "Cảnh báo hệ thống": danh sách cảnh báo màu (Hàng đợi job tồn 1.200 tác vụ - cam; Thanh toán thất bại tăng đột biến - đỏ; 24 báo lỗi câu hỏi chờ - vàng).
(4) Feed "Hoạt động quản trị gần đây" (audit): "Trần Văn B đã xuất bản câu hỏi #1024", "Lê Thị C đã khóa tài khoản user...".
(5) Hàng "Thao tác nhanh": Tạo câu hỏi, Mời quản trị viên, Xem báo cáo doanh thu.
Trạng thái loading skeleton widget. Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: chủ yếu xem KPI + cảnh báo, chart cuộn. Màu teal #0F766E, cảnh báo đỏ #DC2626/cam #D97706.
```

---

## Module 34 — User Management

### 34.1 Danh sách người dùng (`/admin/users`)
```
Màn hình "Quản lý người dùng" (route /admin/users), App shell quản trị.
Khối chính: thanh tìm kiếm (email/tên) + bộ lọc (Vai trò, Trạng thái, Gói); bảng người dùng mật độ cao mỗi dòng: checkbox, avatar + tên "Nguyễn Văn An", email, vai trò (badge Sinh viên/Biên tập/Quản trị viên), gói (Premium/Miễn phí), trạng thái (Hoạt động xanh/Chờ/Tạm khóa/Cấm), lần đăng nhập cuối "2 giờ trước", menu hành động (...); sort theo cột; phân trang server "1–50 trong 38.240"; thanh hành động hàng loạt khi chọn nhiều (Đổi vai trò, Khóa, Xuất CSV).
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 34.2 Chi tiết người dùng (`/admin/users/{id}`)
```
Màn hình chi tiết người dùng (route /admin/users/{id}), App shell quản trị.
Khối chính: header hồ sơ (avatar, Nguyễn Văn An, email, vai trò, trạng thái, ngày tham gia); menu hành động bên phải (Khóa tài khoản, Đặt lại mật khẩu, Xác thực email, Đổi vai trò, Gán/Thu Premium, Đăng nhập thay (impersonate) — chỉ Super Admin); tab: Tổng quan · Đăng ký (subscription) · Hoạt động học · Thiết bị · Nhật ký liên quan.
- Tab Tổng quan: thông tin cá nhân, số liệu học.
- Tab Đăng ký: gói hiện tại, lịch sử, nút "Gia hạn thủ công" (ghi lý do).
- Tab Thiết bị: bảng phiên đăng nhập + thu hồi.
- Tab Nhật ký: các hành động quản trị trên user này.
Modal xác nhận cho hành động nhạy cảm (khóa/đổi vai trò) yêu cầu nhập lý do. Nếu đang impersonate: banner cảnh báo đỏ trên cùng "Bạn đang đăng nhập với tư cách Nguyễn Văn An — Thoát".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: tab cuộn. Màu teal #0F766E.
```

---

## Module 35 — Question Management

### 35.1 Danh sách câu hỏi (`/admin/questions`)
```
Màn hình "Quản lý câu hỏi" (route /admin/questions), App shell quản trị.
Khối chính: bộ lọc (Trạng thái: Nháp/Chờ duyệt/Đã xuất bản/Đã gỡ; Chuyên ngành; Độ khó; Có báo lỗi) + tìm kiếm; nút "+ Soạn câu hỏi" và "Import hàng loạt"; bảng câu hỏi mỗi dòng: mã #1024, trích đề bài tiếng Việt ("Bệnh nhân nam 58 tuổi đau ngực..."), chuyên ngành (Tim mạch), độ khó, badge trạng thái, tỷ lệ đúng thực nghiệm "68%", số báo lỗi (badge đỏ nếu >0), người cập nhật, menu hành động; sort + phân trang; hành động hàng loạt.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 35.2 Soạn/sửa câu hỏi (`/admin/questions/create`, `/{id}/edit`)
```
Màn hình "Soạn câu hỏi" (route /admin/questions/create), App shell quản trị, bố cục 2 cột (biên tập trái + xem trước phải).
Cột trái - form nhiều section:
- Thanh trạng thái workflow trên cùng: Nháp → Chờ duyệt → Đã xuất bản → Đã gỡ, nút "Lưu nháp", "Gửi duyệt", "Xuất bản".
- Section "Đề bài (stem)": trình soạn rich text với vignette mẫu tiếng Việt.
- Section "Câu hỏi dẫn (lead-in)": "Chẩn đoán phù hợp nhất là gì?".
- Section "Đáp án": danh sách A–E, mỗi đáp án có ô nội dung, radio đánh dấu đáp án đúng (A), và ô "Giải thích vì sao đúng/sai".
- Section "Giải thích chung", "Nguồn tham khảo", "Chỉ số xét nghiệm (lab values)".
- Section "Phân loại": chọn chuyên ngành/chủ đề (chip), tag (High-yield), độ khó.
- Section "Media": nút chèn ảnh/ECG từ thư viện media.
- Toggle "Câu hỏi miễn phí (preview)".
Cột phải: xem trước câu hỏi đúng như học viên thấy (chế độ Study), nút chuyển xem trước Exam; drawer "Lịch sử phiên bản" so sánh diff.
Validation hiển thị: "Cần ít nhất 1 đáp án đúng", "Giải thích bắt buộc". Badge "Tự động lưu nháp".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: xem trước xuống dưới. Màu teal #0F766E.
```

### 35.3 Import hàng loạt (`/admin/questions/import`)
```
Màn hình wizard "Import câu hỏi hàng loạt" (route /admin/questions/import), App shell quản trị.
Stepper 4 bước (Tải tệp → Ánh xạ cột → Kiểm tra → Xác nhận):
- Bước Tải tệp: vùng kéo-thả tệp Excel/CSV, nút tải mẫu template.
- Bước Ánh xạ cột: bảng ghép cột tệp với trường hệ thống (Cột A → Đề bài, Cột B → Đáp án đúng...).
- Bước Kiểm tra: bảng xem trước có tô đỏ dòng lỗi kèm thông báo ("Dòng 12: thiếu đáp án đúng"), tổng "480 hợp lệ · 5 lỗi".
- Bước Xác nhận: tóm tắt, nút "Import 480 câu (tạo bản nháp)".
Thanh tiến trình xử lý nền. Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: stepper dọc. Màu teal #0F766E.
```

### 35.4 Xử lý báo lỗi câu hỏi (`/admin/questions/reports`)
```
Màn hình "Hàng đợi báo lỗi câu hỏi" (route /admin/questions/reports), App shell quản trị.
Khối chính: bộ lọc theo trạng thái (Mới/Đang xử lý/Đã giải quyết/Đã từ chối) và lý do; bảng báo lỗi mỗi dòng: mã câu #1024, lý do (Đáp án sai / Giải thích chưa rõ / Lỗi chính tả), nội dung phản ánh của người dùng, người báo, thời gian, trạng thái, nút "Xem câu" và "Xử lý"; panel/drawer xử lý cho phép sửa nhanh và ghi cách giải quyết, nút "Đánh dấu đã giải quyết" + gửi thông báo cho người báo.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

---

## Module 36 — Library Management

### 36.1 Danh sách nội dung thư viện (`/admin/library`)
```
Màn hình "Quản lý thư viện" (route /admin/library), App shell quản trị.
Khối chính: bộ lọc theo loại (Bài viết/Bệnh/Thuốc/Thủ thuật), trạng thái, chuyên ngành + tìm kiếm; nút "+ Soạn nội dung"; bảng nội dung mỗi dòng: tiêu đề ("Nhồi máu cơ tim cấp", "Metformin"), loại (badge), chuyên ngành, trạng thái workflow, miễn phí/Premium, số liên kết chéo, người cập nhật, ngày; menu hành động (Sửa, Xem trước, Xuất bản, Gỡ, Quản lý liên kết).
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 36.2 Soạn nội dung thư viện (`/admin/library/create`, `/{id}/edit`)
```
Màn hình "Soạn nội dung thư viện" (route /admin/library/create), App shell quản trị, bố cục 2 cột (biên tập + xem trước).
Cột trái:
- Thanh workflow (Nháp → Chờ duyệt → Đã xuất bản → Đã gỡ).
- Chọn loại nội dung (Bệnh/Thuốc/Thủ thuật/Bài viết) → nạp template section chuẩn tương ứng (vd Bệnh: Định nghĩa, Dịch tễ, Sinh lý bệnh, Lâm sàng, Chẩn đoán, Điều trị, Biến chứng).
- Trình soạn WYSIWYG/Markdown giàu định dạng (heading tự sinh mục lục, bảng, callout High-yield, chèn media), tiêu đề "Suy tim", trường tóm tắt, toggle miễn phí/Premium, mã ICD (nếu bệnh).
Cột phải: xem trước bài như học viên + drawer "Lịch sử phiên bản" (diff/rollback).
Badge "Tự động lưu". Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: xem trước xuống dưới. Màu teal #0F766E.
```

### 36.3 Quản lý liên kết chéo (`/admin/library/{id}/links`)
```
Màn hình "Liên kết chéo (cross-link)" (route /admin/library/{id}/links), App shell quản trị.
Khối chính: header nội dung nguồn "Nhồi máu cơ tim cấp"; các nhóm liên kết theo quan hệ (Điều trị bởi thuốc → Aspirin, Clopidogrel; Chẩn đoán bởi thủ thuật → Chụp mạch vành; Chẩn đoán phân biệt → Viêm màng ngoài tim; Minh họa bởi → ảnh ECG; Được kiểm tra bởi → câu hỏi #1024); mỗi liên kết có nút xóa; ô tìm kiếm + thêm liên kết mới với chọn loại quan hệ.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

---

## Module 37 — Media Management

### 37.1 Thư viện media (`/admin/media`)
```
Màn hình "Thư viện Media" (route /admin/media), App shell quản trị.
Khối chính: bộ lọc theo loại (Ảnh/Video/Âm thanh/Tài liệu), trạng thái (Đang xử lý/Sẵn sàng/Lỗi), Premium + tìm kiếm; nút "Tải lên"; lưới media dạng grid mỗi ô có thumbnail, tên, badge loại, badge trạng thái xử lý ("Đang xử lý HLS..." cho video), biểu tượng Premium; hành động hàng loạt (Xóa, Gắn tag, Đặt Premium); cuộn vô hạn.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: 2 cột. Màu teal #0F766E.
```

### 37.2 Tải lên & Chi tiết media (`/admin/media/upload`, `/{id}`)
```
Hai màn hình Media của MedPro, App shell quản trị.
(1) Tải lên (route /admin/media/upload): vùng kéo-thả nhiều tệp lớn, danh sách tệp đang tải với thanh tiến trình từng tệp và trạng thái ("Đang tải 45%", "Đang tạo biến thể", "Sẵn sàng"), hỗ trợ chunk/resumable.
(2) Chi tiết media (route /admin/media/{id}): xem trước lớn (ảnh ECG / video player); tab Thông tin (form metadata: Alt bắt buộc, Chú thích, Nguồn/credit, toggle Premium), tab Biến thể (thumbnail/webp/HLS + kích thước), tab Nơi sử dụng (danh sách câu hỏi/bài viết đang dùng — chặn xóa nếu đang dùng), tab Chú thích (annotation editor: vẽ vùng hotspot + nhãn trên ảnh).
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: tab cuộn. Màu teal #0F766E.
```

---

## Module 38 — Exam Management

### 38.1 Danh sách đề (`/admin/exams`)
```
Màn hình "Quản lý kỳ thi" (route /admin/exams), App shell quản trị.
Khối chính: bộ lọc theo loại (Mô phỏng/Tự đánh giá), trạng thái, lịch + tìm kiếm; nút "+ Tạo đề thi"; bảng đề mỗi dòng: tên "Mô phỏng thi Bác sĩ nội trú 2026", loại, số câu 150, thời lượng 180 phút, điểm đạt 70%, trạng thái, khoảng khả dụng "01/06–30/06/2026", số lượt làm, menu hành động (Sửa, Xuất bản, Xem kết quả).
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 38.2 Tạo đề thi (wizard) (`/admin/exams/create`)
```
Màn hình wizard "Tạo đề thi" (route /admin/exams/create), App shell quản trị, stepper (Thông tin → Chọn câu → Cấu hình → Lịch → Xuất bản).
Nội dung theo bước:
- Thông tin: tên đề, mô tả, loại.
- Chọn câu: chuyển đổi "Thủ công" (tìm & tick câu vào đề) hay "Theo quy tắc" (định nghĩa blueprint: 150 câu, phân bổ 40% Nội, 20% Ngoại, 20% Sản-Nhi, 20% Dược; độ khó cân bằng) — panel BlueprintPreview hiển thị biểu đồ tròn phân bổ theo chủ đề và cảnh báo nếu không đủ câu.
- Cấu hình: thời lượng 180 phút, điểm đạt 70%, số lần làm, ngẫu nhiên hóa thứ tự, hiển thị đáp án sau nộp (toggle).
- Lịch: khoảng khả dụng bằng date range.
- Xuất bản: tóm tắt + nút "Xuất bản đề".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: stepper dọc. Màu teal #0F766E.
```

### 38.3 Kết quả & phân tích đề (`/admin/exams/{id}/results`)
```
Màn hình "Kết quả kỳ thi" (route /admin/exams/{id}/results), App shell quản trị.
Khối chính: KPI (Số thí sinh 1.580, Điểm trung bình 64, Tỷ lệ đạt 58%, Điểm cao nhất 96); biểu đồ phân phối điểm (histogram); bảng "Phân tích từng câu (item analysis)" mỗi dòng: mã câu, tỷ lệ đúng, độ phân biệt, cờ cảnh báo câu quá dễ/khó/mơ hồ; bảng bảng xếp hạng thí sinh; nút "Xuất kết quả" (CSV/PDF).
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: KPI 2 cột, chart full, bảng cuộn. Màu teal #0F766E.
```

---

## Module 39 — Role & Permission

### 39.1 Danh sách vai trò & Ma trận quyền (`/admin/roles`, `/{id}`)
```
Màn hình "Vai trò & Phân quyền" (route /admin/roles), App shell quản trị.
Khối chính:
(1) Danh sách vai trò dạng bảng: Sinh viên (38.100 user), Biên tập nội dung (18), Quản trị viên (8), Super Admin (2) — mỗi dòng có nút Sửa, Nhân bản, và khóa với vai trò hệ thống được bảo vệ; nút "+ Tạo vai trò".
(2) Khi chọn một vai trò: ma trận quyền (permission matrix) — hàng là quyền nhóm theo tài nguyên (question.view, question.create, question.publish, library.publish, user.manage, billing..., role.manage), cột là bật/tắt bằng toggle; ô quyền nhạy cảm có cảnh báo; nút "Lưu thay đổi" mở modal xác nhận "Thay đổi quyền sẽ áp dụng ngay".
Trang danh mục quyền (route /admin/permissions): liệt kê toàn bộ permission nhóm theo tài nguyên với mô tả tiếng Việt.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: ma trận cuộn ngang. Màu teal #0F766E.
```

---

## Module 40 — Audit Log

### 40.1 Tra cứu nhật ký (`/admin/audit`)
```
Màn hình "Nhật ký kiểm toán" (route /admin/audit), App shell quản trị, chỉ đọc.
Khối chính: bộ lọc mạnh (Người thực hiện, Hành động, Loại đối tượng, Khoảng thời gian bắt buộc, Địa chỉ IP) + tìm kiếm; bảng nhật ký mật độ cao mỗi dòng: thời gian "12/07/2026 14:32:05", người thực hiện (Lê Thị C - Quản trị viên), hành động (badge: Đổi quyền / Xuất bản câu hỏi / Khóa user / Hoàn tiền), đối tượng ("Người dùng #4821"), IP, nút "Chi tiết"; phân trang server; nút "Xuất CSV".
Trạng thái loading skeleton. Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 40.2 Chi tiết bản ghi (`/admin/audit/{id}`)
```
Drawer/màn hình chi tiết nhật ký (route /admin/audit/{id}) của MedPro, App shell quản trị.
Nội dung: tiêu đề hành động "Đổi vai trò người dùng"; metadata (Người thực hiện, Thời gian, IP, User-Agent, Mã request); khối so sánh "Trước / Sau" dạng diff hai cột tô màu (Trước: vai trò = Sinh viên; Sau: vai trò = Biên tập nội dung); liên kết tới đối tượng liên quan; ghi chú không thể chỉnh sửa "Bản ghi bất biến".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: 2 cột diff xếp dọc. Màu teal #0F766E, thêm #16A34A / bớt #DC2626.
```

---

## Module 41 — Reports

### 41.1 Trung tâm báo cáo (`/admin/reports`)
```
Màn hình "Trung tâm báo cáo" (route /admin/reports), App shell quản trị.
Khối chính: lưới danh mục báo cáo dựng sẵn dạng thẻ có icon (Người dùng & tăng trưởng, Tương tác/engagement, Doanh thu & churn, Hiệu quả nội dung, Kết quả học tập); nút "Tạo báo cáo tùy biến"; khu "Báo cáo đã lên lịch" liệt kê báo cáo gửi email định kỳ.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: lưới 1 cột. Màu teal #0F766E.
```

### 41.2 Báo cáo chi tiết & Trình tạo (`/admin/reports/{type}`, `/builder`)
```
Hai màn hình báo cáo của MedPro, App shell quản trị.
(1) Báo cáo Doanh thu (route /admin/reports/doanh-thu): bộ lọc khoảng thời gian/gói/segment; KPI (MRR 1,24 tỷ₫, Khách trả phí 6.240, Churn 3,2%, ARPU 199.000₫); biểu đồ đường MRR theo tháng, biểu đồ churn, biểu đồ phễu chuyển đổi Free→Premium; bảng drill-down theo gói; nút "Xuất PDF/CSV/Excel" và "Lên lịch gửi email".
(2) Trình tạo báo cáo (route /admin/reports/builder): bố cục 2 cột — trái chọn Chỉ số (metric: tỷ lệ đúng, số câu, doanh thu...), Chiều (dimension: chủ đề, thời gian, gói), Bộ lọc/cohort; phải là vùng xem trước biểu đồ + bảng cập nhật realtime; nút "Lưu báo cáo" và "Lên lịch".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bộ lọc/cấu hình trên, kết quả dưới. Màu teal #0F766E.
```

---

## Module 42 — CMS

### 42.1 Trang tĩnh & Block editor (`/admin/cms/pages`)
```
Màn hình "CMS — Trang & Landing" (route /admin/cms/pages), App shell quản trị.
Khối chính: danh sách trang (Trang chủ, Tính năng, Bảng giá, Về chúng tôi, Điều khoản) với trạng thái (Đã xuất bản/Nháp) và ngôn ngữ; khi mở một trang → trình biên tập theo BLOCK: danh sách section kéo-thả (Hero, Value props, Feature, Bảng giá, FAQ, CTA) với nút thêm/sửa/xóa/di chuyển, mỗi block mở form chỉnh nội dung tiếng Việt; panel SEO (meta title, description, ảnh OG, slug); tab ngôn ngữ VI/EN; nút "Xem trước", "Xuất bản", "Hẹn giờ xuất bản".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: block xếp dọc. Màu teal #0F766E.
```

### 42.2 Blog, FAQ, Banner, Menu (`/admin/cms/blog`, `/faq`, `/banners`, `/menus`)
```
Màn hình quản lý CMS phụ của MedPro, App shell quản trị, dạng nhiều mục quản trị.
- Blog (route /admin/cms/blog): bảng bài viết + trình soạn rich text + panel SEO + hẹn giờ.
- FAQ (route /admin/cms/faq): danh sách câu hỏi–trả lời kéo-thả sắp xếp, thêm/sửa, ngôn ngữ VI/EN.
- Banner (route /admin/cms/banners): danh sách banner khuyến mãi với nội dung, đối tượng hiển thị (target: Người dùng Free), lịch hiển thị (date range), toggle bật/tắt; xem trước banner.
- Menu (route /admin/cms/menus): trình xây dựng menu điều hướng kéo-thả các mục và liên kết.
Chọn 1 màn hình để dựng (ví dụ Banner). Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

---

## Module 43 — API (Developer Portal)

### 43.1 Cổng lập trình viên & API Keys (`/developer`, `/developer/keys`)
```
Màn hình "Cổng lập trình viên (Developer Portal)" (route /developer), App shell quản trị.
Khối chính:
(1) Tổng quan sử dụng: KPI (Số lượt gọi API tháng 1,2 triệu, Tỷ lệ lỗi 0,4%, Giới hạn tốc độ 1.000 req/phút) + biểu đồ đường lượt gọi theo ngày.
(2) Quản lý API Key (route /developer/keys): bảng key mỗi dòng: tên ("Ứng dụng mobile MedPro"), tiền tố key "sk_live_a1b2••••", phạm vi (scope: read:questions, read:exams), lần dùng cuối, ngày hết hạn, nút "Thu hồi"; nút "+ Tạo API key" mở modal chọn tên + phạm vi + hạn, sau khi tạo hiện key đầy đủ MỘT LẦN kèm cảnh báo "Sao chép ngay, key chỉ hiển thị một lần" và nút sao chép.
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 43.2 Tài liệu API & Webhook (`/developer/docs`, `/developer/webhooks`)
```
Hai màn hình Developer Portal của MedPro.
(1) Tài liệu API (route /developer/docs): giao diện kiểu Swagger UI — cột trái danh mục endpoint nhóm theo tài nguyên (Sessions, Questions, Exams, Library), khu chính hiển thị chi tiết endpoint (method GET/POST màu, đường dẫn /api/v1/..., mô tả tiếng Việt, tham số, ví dụ request/response JSON) và khu "Thử ngay (playground)" nhập tham số + nút "Gửi" xem kết quả.
(2) Webhook (route /developer/webhooks): danh sách webhook (URL endpoint, sự kiện đăng ký như exam.finished, subscription.updated, trạng thái Hoạt động), nút "Thêm webhook" (nhập URL, chọn sự kiện, secret ký HMAC), và bảng "Nhật ký gửi (delivery log)" mỗi dòng: sự kiện, mã phản hồi (200/500), số lần thử, thời gian, nút "Gửi lại".
Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, KHÔNG gộp chung): một màn hình Desktop với bố cục đầy đủ như mô tả trên, và một màn hình Mobile. Trên mobile: danh mục thành dropdown. Màu teal #0F766E, method GET xanh #16A34A / POST teal / DELETE đỏ #DC2626.
```
