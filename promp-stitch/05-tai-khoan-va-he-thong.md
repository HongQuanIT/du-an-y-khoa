# 05 — Tài khoản & Hệ thống (Notification, Subscription, Billing, Profile, Settings)

Prompt Stitch cho 5 module. **Đọc `00-he-thong-thiet-ke.md` và dán Prompt Theme trước.** Mỗi khối ``` là một màn hình.

> 🔵 Module 32 — Organization đã **hoãn (Phase 2)**, không còn trong bộ prompt này.

---

## Module 27 — Notification
Thông báo đa kênh: nhắc học, streak, kết quả, nội dung, hệ thống.

### 27.1 Dropdown thông báo (header)
```
Dropdown thông báo trượt xuống từ biểu tượng chuông trên header MedPro, rộng ~380px, bo góc, đổ bóng.
Nội dung: tiêu đề "Thông báo" + link "Đánh dấu tất cả đã đọc"; tab nhỏ "Tất cả / Chưa đọc"; danh sách 8 thông báo gần nhất mỗi mục có icon theo loại, tiêu đề tiếng Việt và thời gian:
- 🔥 "Streak của bạn sắp mất! Học ngay để giữ chuỗi 15 ngày" · 5 phút trước (chưa đọc, chấm teal).
- ✅ "Kỳ thi 'Mô phỏng Bác sĩ nội trú' đã được chấm — 68 điểm" · 1 giờ trước.
- 📚 "Bài viết mới: Cập nhật guideline suy tim 2026" · hôm qua.
- 📈 "Báo cáo tuần: bạn đã làm 240 câu, tỷ lệ đúng 74% (+3%)" · 2 ngày trước.
Mục chưa đọc có nền xanh nhạt. Chân dropdown: nút "Xem tất cả thông báo".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: full-width sổ xuống. Màu teal #0F766E.
```

### 27.2 Trung tâm thông báo (`/notifications`)
```
Màn hình "Trung tâm thông báo" (route /notifications), app shell.
Khối chính: bộ lọc theo trạng thái (Tất cả/Chưa đọc) và theo loại (Nhắc học, Kết quả, Nội dung, Hệ thống); nút "Đánh dấu tất cả đã đọc"; danh sách thông báo đầy đủ có icon, tiêu đề, mô tả, thời gian, nút hành động (mở nội dung liên quan), checkbox chọn nhiều để xóa.
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
- Cột trái: 3 thẻ gói theo thời hạn — Premium 1 tháng 199.000₫; Premium 6 tháng 990.000₫ (~165.000₫/tháng · tiết kiệm 17% — nổi bật viền teal, nhãn "Tiết kiệm nhất"); Premium 12 tháng 1.490.000₫ (~124.000₫/tháng); danh sách quyền lợi tick giống nhau ở mọi gói.
- Cột phải: thẻ "Tóm tắt đơn hàng": Gói Premium (6 tháng), giá 990.000₫, ô nhập mã giảm giá (coupon) + nút "Áp dụng", dòng giảm giá "-99.000₫ (mã SINHVIEN10)", VAT, Tổng "891.000₫"; chọn phương thức thanh toán dạng radio có logo (MoMo, VNPay, ZaloPay, Chuyển khoản ngân hàng, Thẻ Visa/Mastercard); nút lớn "Thanh toán" teal; dòng bảo mật "Thanh toán an toàn, không lưu thông tin thẻ".
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
Form: khu upload avatar (kéo-thả ảnh + cắt/crop hình tròn); input Họ tên "Nguyễn Văn An"; Trường/Đại học "Đại học Y Hà Nội"; Chuyên ngành quan tâm (chip nhiều lựa chọn); Mục tiêu thi + ngày thi; giới thiệu ngắn (textarea); toggle "Công khai hồ sơ"; nút "Lưu thay đổi" teal + "Hủy".
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
Nội dung: bảng tùy chọn theo loại × kênh — hàng là loại thông báo (Nhắc học & streak, Kết quả kỳ thi, Nội dung mới, Hệ thống & bảo trì), cột là kênh (Trong ứng dụng, Email, Push) với toggle ở mỗi ô; khối "Giờ yên tĩnh" chọn khoảng thời gian không nhận thông báo (22:00–07:00).
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
Màn hình "Cài đặt › Quyền riêng tư & Dữ liệu" (route /settings/privacy) của MedPro, app shell.
Nội dung: toggle "Công khai hồ sơ", toggle "Cho phép hiển thị trên bảng xếp hạng (ẩn danh)", toggle "Không tham gia phân tích không thiết yếu"; khối "Dữ liệu của tôi" nút "Tải xuống dữ liệu" (tạo file qua job); khối "Vùng nguy hiểm" viền đỏ: nút "Tạm ngưng tài khoản" và "Xóa tài khoản" (đỏ) — mở modal xác nhận yêu cầu gõ "XÓA" để xác nhận, cảnh báo "Dữ liệu sẽ bị xóa sau 30 ngày".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E, nguy hiểm #DC2626.
```

---

## Module 32 — Organization 🔵 (Hoãn — Phase 2)
Module tổ chức/lớp học (B2B) đã **hoãn**, chưa nằm trong phạm vi thiết kế hiện tại. Khi kích hoạt Phase 2 sẽ bổ sung lại các prompt: Dashboard tổ chức, Thành viên & ghế, Lớp học, Giao bài, Báo cáo tổ chức.
