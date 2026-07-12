# 05 — Tài khoản & Hệ thống (Notification, Subscription, Billing, Profile, Settings, Organization)

Prompt Stitch cho 6 module. **Đọc `00-he-thong-thiet-ke.md` và dán Prompt Theme trước.** Mỗi khối ``` là một màn hình.

---

## Module 27 — Notification
Thông báo đa kênh: nhắc học, streak, kết quả, hệ thống, tổ chức.

### 27.1 Dropdown thông báo (header)
```
Dropdown thông báo trượt xuống từ biểu tượng chuông trên header MedLuyện, rộng ~380px, bo góc, đổ bóng.
Nội dung: tiêu đề "Thông báo" + link "Đánh dấu tất cả đã đọc"; tab nhỏ "Tất cả / Chưa đọc"; danh sách 8 thông báo gần nhất mỗi mục có icon theo loại, tiêu đề tiếng Việt và thời gian:
- 🔥 "Streak của bạn sắp mất! Học ngay để giữ chuỗi 15 ngày" · 5 phút trước (chưa đọc, chấm teal).
- ✅ "Kỳ thi 'Mô phỏng Bác sĩ nội trú' đã được chấm — 68 điểm" · 1 giờ trước.
- 📚 "Bài viết mới: Cập nhật guideline suy tim 2026" · hôm qua.
- 🎓 "Giảng viên đã giao bài: 50 câu Tim mạch, hạn 15/07" · 2 ngày trước.
Mục chưa đọc có nền xanh nhạt. Chân dropdown: nút "Xem tất cả thông báo".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: full-width sổ xuống. Màu teal #0F766E.
```

### 27.2 Trung tâm thông báo (`/notifications`)
```
Màn hình "Trung tâm thông báo" (route /notifications), app shell.
Khối chính: bộ lọc theo trạng thái (Tất cả/Chưa đọc) và theo loại (Nhắc học, Kết quả, Nội dung, Tổ chức, Hệ thống); nút "Đánh dấu tất cả đã đọc"; danh sách thông báo đầy đủ có icon, tiêu đề, mô tả, thời gian, nút hành động (mở nội dung liên quan), checkbox chọn nhiều để xóa.
Trạng thái rỗng: minh họa chuông + "Bạn đã xem hết thông báo". Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

---

## Module 28 — Subscription
Quản lý gói & quyền lợi Premium.

### 28.1 Gói hiện tại (`/subscription`)
```
Màn hình "Gói đăng ký" (route /subscription), app shell.
Khối chính:
(1) Thẻ gói hiện tại: badge "Premium" gradient vàng-cam (hoặc "Miễn phí"), trạng thái "Đang hoạt động", "Gia hạn tự động vào 12/08/2026", giá "199.000₫/tháng", danh sách quyền lợi đang có (tick). Với Free: hiện QuotaMeter "Đã dùng 12/20 câu hôm nay · 2/3 lượt AI".
(2) Nút "Nâng cấp / Đổi gói" teal và "Hủy gia hạn" (link phụ).
(3) Nếu đang dùng thử: banner "Đang dùng thử — còn 5 ngày".
(4) Bảng tóm tắt so sánh Miễn phí vs Premium.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E, nhãn Premium gradient vàng-cam.
```

### 28.2 Chọn gói & thanh toán (`/subscription/upgrade`)
```
Màn hình "Nâng cấp Premium" (route /subscription/upgrade), app shell, bố cục 2 cột.
- Cột trái: toggle "Theo tháng / Theo năm (tiết kiệm 25%)"; 2 thẻ gói (Premium 199.000₫/tháng hoặc 1.490.000₫/năm — nổi bật viền teal, nhãn "Tiết kiệm nhất"; Tổ chức "Liên hệ"); danh sách quyền lợi tick.
- Cột phải: thẻ "Tóm tắt đơn hàng": Gói Premium (năm), giá 1.490.000₫, ô nhập mã giảm giá (coupon) + nút "Áp dụng", dòng giảm giá "-149.000₫ (mã SINHVIEN10)", VAT, Tổng "1.341.000₫"; chọn phương thức thanh toán dạng radio có logo (MoMo, VNPay, ZaloPay, Chuyển khoản ngân hàng, Thẻ Visa/Mastercard); nút lớn "Thanh toán" teal; dòng bảo mật "Thanh toán an toàn, không lưu thông tin thẻ".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: gói trên, tóm tắt dưới, CTA sticky đáy. Màu teal #0F766E.
```

### 28.3 Luồng hủy gói (`/subscription/cancel`)
```
Màn hình/wizard "Hủy gói Premium" (route /subscription/cancel), app shell, hướng giữ chân (retention).
Khối chính: tiêu đề "Chúng tôi rất tiếc khi bạn rời đi"; nhắc quyền lợi sẽ mất (danh sách gạch ngang); chọn lý do hủy (radio: Quá đắt, Không dùng nhiều, Đã thi xong, Lý do khác); ưu đãi giữ chân "Nhận giảm 50% cho 3 tháng tới?" nút "Nhận ưu đãi" teal nổi bật + nút phụ "Vẫn hủy gói"; ghi chú "Bạn vẫn dùng Premium đến hết 12/08/2026".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

---

## Module 29 — Billing
Thanh toán, hóa đơn, phương thức thanh toán.

### 29.1 Tổng quan thanh toán (`/billing`)
```
Màn hình "Thanh toán & Hóa đơn" (route /billing), app shell.
Khối chính: thẻ tóm tắt (Gói Premium, Kỳ tiếp theo 12/08/2026, Số tiền 199.000₫); thẻ phương thức mặc định (ví MoMo •• 8899 hoặc Visa •••• 4242) nút "Thay đổi"; bảng "Hóa đơn gần đây" 3 dòng mới nhất; link "Xem tất cả hóa đơn".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

### 29.2 Lịch sử hóa đơn (`/billing/invoices`)
```
Màn hình "Lịch sử hóa đơn" (route /billing/invoices), app shell.
Khối chính: bộ lọc theo thời gian/trạng thái; bảng hóa đơn mỗi dòng: mã "HĐ-2026-0042", ngày "12/07/2026", mô tả "Premium - gói tháng", số tiền "199.000₫", badge trạng thái (Đã thanh toán xanh / Chờ xử lý cam / Hoàn tiền xám), nút "Tải PDF". Tổng đã chi năm nay trên cùng.
Trạng thái rỗng: "Chưa có hóa đơn nào". Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 29.3 Phương thức thanh toán (`/billing/methods`)
```
Màn hình "Phương thức thanh toán" (route /billing/methods), app shell.
Khối chính: danh sách phương thức đã lưu dạng thẻ (Ví MoMo •• 8899 - Mặc định badge; Thẻ Visa •••• 4242 hết hạn 08/27) mỗi thẻ có nút "Đặt mặc định" và "Xóa"; nút "+ Thêm phương thức".
Modal "Thêm phương thức": chọn loại (MoMo/VNPay/ZaloPay/Thẻ), nếu thẻ thì các ô nhập do cổng thanh toán cung cấp (số thẻ, hết hạn, CVC), ghi chú bảo mật, nút "Thêm".
Trạng thái rỗng: "Chưa có phương thức thanh toán". Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

---

## Module 30 — User Profile
Hồ sơ cá nhân, thành tích, tiến trình.

### 30.1 Hồ sơ của tôi (`/profile`)
```
Màn hình "Hồ sơ của tôi" (route /profile), app shell.
Khối chính:
(1) Header hồ sơ: avatar lớn, tên "Nguyễn Văn An", trường "Đại học Y Hà Nội", mục tiêu "Ôn thi Bác sĩ nội trú 2026", huy hiệu streak "🔥 15 ngày", nút "Chỉnh sửa hồ sơ".
(2) Lưới thống kê: "Câu đã làm 1.240", "Tỷ lệ đúng 72%", "Giờ học 48h", "Kỳ thi đã làm 6".
(3) Khu "Huy hiệu thành tích" dạng lưới icon: "Chuỗi 7 ngày", "1000 câu hỏi", "Bậc thầy Tim mạch", "Cú đêm" — huy hiệu chưa đạt hiển thị mờ + tiến độ.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: avatar trên, stats 2 cột, badges lưới. Màu teal #0F766E.
```

### 30.2 Chỉnh sửa hồ sơ (`/profile/edit`)
```
Màn hình "Chỉnh sửa hồ sơ" (route /profile/edit), app shell.
Form: khu upload avatar (kéo-thả ảnh + cắt/crop hình tròn); input Họ tên "Nguyễn Văn An"; Trường/Tổ chức "Đại học Y Hà Nội"; Chuyên ngành quan tâm (chip nhiều lựa chọn); Mục tiêu thi + ngày thi; giới thiệu ngắn (textarea); toggle "Công khai hồ sơ"; nút "Lưu thay đổi" teal + "Hủy".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

---

## Module 31 — User Settings
Cài đặt tài khoản, bảo mật, thông báo, giao diện, quyền riêng tư.

### 31.1 Cài đặt — bố cục tab & Tài khoản (`/settings/account`)
```
Màn hình "Cài đặt" (route /settings/account), app shell, bố cục 2 cột.
- Cột trái: điều hướng cài đặt dạng danh sách nhóm có icon (Tài khoản, Bảo mật, Thông báo, Giao diện, Quyền riêng tư, Học tập, Vùng nguy hiểm), mục đang chọn tô teal.
- Cột phải (tab Tài khoản): form Họ tên; ô Email hiện tại "an.nguyen@sv.vnu.edu.vn" + nút "Đổi email" (cần xác thực); múi giờ; nút "Lưu".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: điều hướng thành danh sách → mở từng trang. Màu teal #0F766E.
```

### 31.2 Bảo mật (`/settings/security`)
```
Màn hình "Cài đặt › Bảo mật" (route /settings/security), app shell (nav trái + nội dung phải).
Nội dung: khối "Đổi mật khẩu" (mật khẩu hiện tại, mật khẩu mới có thanh đo độ mạnh, xác nhận); khối "Xác thực hai lớp (2FA)" với toggle bật/tắt, khi bật hiện mã QR + ô nhập mã xác nhận + danh sách mã dự phòng; khối "Thiết bị đăng nhập" dạng bảng (Chrome trên macOS - Hà Nội - đang hoạt động; iPhone - 2 ngày trước) mỗi dòng nút "Thu hồi", nút "Đăng xuất tất cả thiết bị khác".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 31.3 Thông báo (`/settings/notifications`)
```
Màn hình "Cài đặt › Thông báo" (route /settings/notifications), app shell.
Nội dung: bảng tùy chọn theo loại × kênh — hàng là loại thông báo (Nhắc học & streak, Kết quả kỳ thi, Nội dung mới, Bài được giao, Hệ thống & bảo trì), cột là kênh (Trong ứng dụng, Email, Push) với toggle ở mỗi ô; khối "Giờ yên tĩnh" chọn khoảng thời gian không nhận thông báo (22:00–07:00).
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: mỗi loại thành thẻ có 3 toggle. Màu teal #0F766E.
```

### 31.4 Giao diện (`/settings/appearance`)
```
Màn hình "Cài đặt › Giao diện" (route /settings/appearance), app shell.
Nội dung: chọn giao diện (3 thẻ xem trước: Sáng / Tối / Theo hệ thống); chọn ngôn ngữ (Tiếng Việt / English) dạng dropdown có cờ; chọn cỡ chữ (Nhỏ/Vừa/Lớn) với xem trước đoạn văn; toggle "Giảm chuyển động".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

### 31.5 Quyền riêng tư & Vùng nguy hiểm (`/settings/privacy`, `/settings/danger`)
```
Màn hình "Cài đặt › Quyền riêng tư & Dữ liệu" (route /settings/privacy) của MedLuyện, app shell.
Nội dung: toggle "Công khai hồ sơ", toggle "Cho phép hiển thị trên bảng xếp hạng (ẩn danh)", toggle "Không tham gia phân tích không thiết yếu"; khối "Dữ liệu của tôi" nút "Tải xuống dữ liệu" (tạo file qua job); khối "Vùng nguy hiểm" viền đỏ: nút "Tạm ngưng tài khoản" và "Xóa tài khoản" (đỏ) — mở modal xác nhận yêu cầu gõ "XÓA" để xác nhận, cảnh báo "Dữ liệu sẽ bị xóa sau 30 ngày".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E, nguy hiểm #DC2626.
```

---

## Module 32 — Organization (Tổ chức/Lớp học)
Quản lý tổ chức B2B: thành viên, ghế, lớp, giao bài, báo cáo.

### 32.1 Dashboard tổ chức (`/org`)
```
Màn hình "Tổng quan tổ chức" (route /org) cho Quản trị tổ chức, app shell (menu tổ chức: Tổng quan, Thành viên, Lớp học, Bài đã giao, Báo cáo, Cài đặt).
Khối chính: header tên tổ chức "Đại học Y Hà Nội — Khoa Y"; lưới KPI ("Thành viên hoạt động 320/350", "Ghế Premium đã dùng 300/350", "Tiến độ trung bình 64%", "Số lớp 12"); biểu đồ đường mức độ hoạt động thành viên 30 ngày; danh sách lớp học nổi bật với tiến độ; bảng "Học viên cần chú ý" (tiến độ thấp/không hoạt động).
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: KPI 2 cột, chart full. Màu teal #0F766E.
```

### 32.2 Thành viên & ghế (`/org/members`)
```
Màn hình "Thành viên" (route /org/members), app shell tổ chức.
Khối chính: thanh SeatMeter "Ghế Premium: 300/350 đã dùng" + thanh tiến trình; nút "Mời thành viên"; bộ lọc theo vai trò/trạng thái + tìm kiếm; bảng thành viên mỗi dòng: avatar + tên (Trần Thị Bích Ngọc), email, vai trò tổ chức (dropdown: Thành viên/Giảng viên/Quản trị tổ chức), trạng thái ghế (Đã cấp Premium / Chưa), trạng thái (Đang hoạt động/Đã mời/Đã xóa), menu hành động (Đổi vai trò, Cấp/thu ghế, Xóa).
Modal "Mời thành viên": nhập nhiều email hoặc tải lên CSV, chọn vai trò & lớp, nút "Gửi lời mời".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: bảng → thẻ. Màu teal #0F766E.
```

### 32.3 Lớp học & chi tiết lớp (`/org/classes`, `/org/classes/{id}`)
```
Hai màn hình quản lý lớp học của MedLuyện, app shell tổ chức.
(1) Danh sách lớp (route /org/classes): lưới thẻ lớp (Lớp Y3A · 45 học viên · Giảng viên: TS. Lê Minh Quân · Tiến độ 62%), nút "+ Tạo lớp".
(2) Chi tiết lớp (route /org/classes/{id}): header lớp + tab (Học viên · Bài đã giao · Tiến độ); tab Học viên là bảng học viên với tiến độ và điểm; tab Tiến độ có biểu đồ phân bố điểm và bảng xếp hạng lớp; nút "Giao bài" mở wizard AssignmentBuilder.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: lưới 1 cột, tab cuộn. Màu teal #0F766E.
```

### 32.4 Giao bài (wizard) & Báo cáo tổ chức (`/org/assignments`, `/org/reports`)
```
Hai màn hình của MedLuyện, app shell tổ chức.
(1) Wizard "Giao bài" (từ /org/assignments): stepper (Chọn nội dung → Chọn lớp/học viên → Hạn nộp → Xác nhận); chọn loại (Phiên câu hỏi 50 câu Tim mạch / Kỳ thi mô phỏng / Bài đọc), chọn lớp "Y3A", date picker hạn nộp "20/07/2026", toggle "Gửi nhắc nhở"; xem trước; nút "Giao bài" teal. Danh sách bài đã giao bên dưới với trạng thái nộp "38/45 đã nộp".
(2) Báo cáo tổ chức (route /org/reports): bộ lọc (khoảng thời gian, lớp); các biểu đồ (hoạt động, tỷ lệ hoàn thành, điểm trung bình theo lớp); bảng tổng hợp theo lớp; nút "Xuất báo cáo" (CSV/PDF).
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: stepper dọc, chart full. Màu teal #0F766E.
```
