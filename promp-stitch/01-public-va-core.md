# 01 — Public & Core (Landing, Auth, Dashboard, Study Plan, Qbank, Session, Review)

Prompt Stitch cho 7 module nền tảng. **Đọc `00-he-thong-thiet-ke.md` và dán Prompt Theme trước** khi dùng các prompt dưới đây. Mỗi khối ``` là một màn hình — copy dán vào Stitch.

---

## Module 01 — Landing Page
Trang công khai giới thiệu sản phẩm, tối ưu chuyển đổi cho Guest.

### 1.1 Trang chủ / Hero (`/`)
```
Trang landing marketing "MedPro — Học & luyện thi Y khoa thông minh" cho khách chưa đăng nhập.
Bố cục: public header (logo MedPro, menu Tính năng · Bảng giá · Blog · Về chúng tôi, nút "Đăng nhập" viền + "Đăng ký miễn phí" nền teal, dropdown ngôn ngữ VI/EN) sticky trong suốt chuyển sang nền trắng khi cuộn; footer nhiều cột cuối trang.
Các khối chính từ trên xuống:
(1) Hero 2 cột: trái là tiêu đề lớn "Học hiệu quả hơn — hiểu bản chất, nhớ lâu, luyện thi đúng trọng tâm", phụ đề, 2 nút "Bắt đầu luyện thi" (teal) và "Xem câu hỏi mẫu"; phải là ảnh minh họa giao diện làm bài.
(2) Dải số liệu tin cậy: "12.450 câu hỏi · 38.000+ người học · 18 chuyên ngành".
(3) 3 value props có icon: "Ngân hàng câu hỏi chuẩn hóa", "Luyện đề thông minh & phát hiện điểm yếu", "AI Tutor giải thích tận gốc".
(4) 4 feature section xen kẽ trái/phải kèm ảnh: Qbank, Thư viện y khoa, AI Assistant, Phân tích tiến độ (mô tả tiếng Việt).
(5) Widget câu hỏi mẫu tương tác: vignette bệnh nhân nam 58 tuổi đau ngực, 5 đáp án A–E, chọn được và bấm "Kiểm tra".
(6) Social proof: 3 testimonial sinh viên Y (Nguyễn Văn An - ĐH Y Hà Nội) + avatar.
(7) Bảng giá rút gọn 3 gói Miễn phí / Premium 199.000₫/tháng / Premium 6 tháng 990.000₫ (tiết kiệm ~17%).
(8) Accordion FAQ 5 câu tiếng Việt.
(9) Dải CTA cuối "Sẵn sàng chinh phục kỳ thi?" nút "Đăng ký miễn phí".
Có banner cookie ở đáy màn hình lần đầu.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột, header thành hamburger drawer, CTA sticky đáy, testimonial dạng carousel.
Màu chủ đạo teal #0F766E, nền trắng sạch, nhiều khoảng trắng, font Be Vietnam Pro.
```

### 1.2 Trang tính năng (`/features`)
```
Trang "Tính năng" công khai của MedPro. Public header + footer.
Nội dung: intro ngắn; lưới các nhóm tính năng có icon và mô tả tiếng Việt (Ngân hàng câu hỏi, Chế độ Study/Exam, Thư viện liên kết chéo, Flashcards spaced repetition, AI Tutor, Lộ trình học cá nhân hóa, Phân tích & Heatmap, Mô phỏng thi thật); mỗi nhóm có ảnh chụp giao diện. Dải CTA đăng ký cuối trang.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột. Màu teal #0F766E.
```

### 1.3 Bảng giá (`/pricing`)
```
Trang "Bảng giá" công khai. Public header + footer.
Khối chính: tiêu đề "Chọn gói phù hợp với bạn"; 3 thẻ giá cạnh nhau theo thời hạn:
- Miễn phí: 0₫ — "20 câu hỏi/ngày, thư viện giới hạn", nút "Bắt đầu".
- Premium 1 tháng: 199.000₫/tháng — danh sách tick "Toàn bộ Qbank, thư viện đầy đủ, AI không giới hạn, phân tích nâng cao, mô phỏng thi", nút "Nâng cấp Premium".
- Premium 6 tháng (thẻ nổi bật viền teal, nhãn "Tiết kiệm nhất"): 990.000₫/6 tháng — dòng phụ "Chỉ ~165.000₫/tháng · tiết kiệm 17%", cùng toàn bộ quyền lợi Premium, nút "Mua gói 6 tháng".
Bảng so sánh chi tiết tính năng theo hàng bên dưới; accordion FAQ thanh toán (MoMo, VNPay, ZaloPay, thẻ).
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 3 thẻ xếp dọc. Nhãn "Tiết kiệm nhất" gradient vàng-cam. Màu teal #0F766E.
```

### 1.4 Preview câu hỏi mẫu (`/qbank-preview`)
```
Trang "Dùng thử câu hỏi" công khai cho Guest. Public header tối giản.
Bố cục giống màn làm bài: thanh tiến trình "Câu 2/5", vignette lâm sàng tiếng Việt (bệnh nhân nữ 24 tuổi sốt cao ngày 4, đau cơ, chấm xuất huyết → nghi Sốt xuất huyết Dengue), 5 đáp án A–E dạng nút lớn, nút "Kiểm tra đáp án". Sau khi trả lời: tô xanh đáp án đúng, đỏ đáp án sai, hiện panel giải thích tiếng Việt. Nút "Câu tiếp theo".
Sau câu thứ 5: PaywallOverlay mờ nền "Bạn đã dùng hết 5 câu miễn phí — Đăng ký để mở khóa 12.450 câu hỏi", nút "Đăng ký miễn phí".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: full-width nút lớn. Màu teal #0F766E, đúng #16A34A, sai #DC2626.
```

---

## Module 02 — Authentication
Đăng ký, đăng nhập, OAuth, xác thực email, quên mật khẩu, 2FA, onboarding.

### 2.1 Đăng nhập (`/login`)
```
Màn hình đăng nhập MedPro, bố cục 2 cột: cột trái nền teal gradient với logo, tagline "Chào mừng trở lại!" và ảnh minh họa y khoa; cột phải là form trên nền trắng.
Form: tiêu đề "Đăng nhập", input Email, input Mật khẩu (có icon hiện/ẩn), checkbox "Ghi nhớ đăng nhập" + link "Quên mật khẩu?", nút "Đăng nhập" full-width teal. Divider "hoặc tiếp tục với". 3 nút OAuth: Google, Facebook, Apple. Dòng cuối "Chưa có tài khoản? Đăng ký".
Trạng thái lỗi: banner đỏ "Email hoặc mật khẩu không đúng"; nút loading khi gửi.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: ẩn cột trái, form full-width. Font Be Vietnam Pro, màu teal #0F766E.
```

### 2.2 Đăng ký (`/register`)
```
Màn hình đăng ký MedPro, bố cục 2 cột như trang đăng nhập.
Form "Tạo tài khoản": input Họ và tên (mẫu "Nguyễn Văn An"), Email, Mật khẩu kèm thanh đo độ mạnh mật khẩu (yếu/trung bình/mạnh), Nhập lại mật khẩu, checkbox "Tôi đồng ý với Điều khoản & Chính sách bảo mật", nút "Đăng ký" teal. 3 nút OAuth Google/Facebook/Apple. Dòng "Đã có tài khoản? Đăng nhập".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: ẩn cột trái. Màu teal #0F766E.
```

### 2.3 Xác thực email (`/verify-email`)
```
Màn hình trạng thái "Xác thực email" của MedPro, layout giữa màn hình một cột, không sidebar.
Nội dung: icon phong bì lớn, tiêu đề "Kiểm tra hộp thư của bạn", mô tả "Chúng tôi đã gửi liên kết xác thực đến an.nguyen@sv.vnu.edu.vn", nút "Mở ứng dụng email", link "Gửi lại email" kèm đếm ngược "Gửi lại sau 45s" khi trong cooldown. Link phụ "Dùng email khác".
Màu teal #0F766E, nền #F8FAFC.
```

### 2.4 Quên mật khẩu (`/forgot-password`)
```
Màn hình "Quên mật khẩu" MedPro, một cột giữa màn hình trên nền trắng bo góc.
Nội dung: tiêu đề "Đặt lại mật khẩu", mô tả "Nhập email để nhận liên kết đặt lại", input Email, nút "Gửi liên kết" teal, link "← Quay lại đăng nhập". Trạng thái thành công: alert xanh "Nếu email tồn tại, chúng tôi đã gửi hướng dẫn".
Màu teal #0F766E.
```

### 2.5 Đặt lại mật khẩu (`/reset-password/{token}`)
```
Màn hình "Tạo mật khẩu mới" MedPro, một cột giữa.
Form: input Mật khẩu mới kèm thanh đo độ mạnh, input Nhập lại mật khẩu, checklist yêu cầu ("ít nhất 8 ký tự", "có chữ hoa", "có số"), nút "Cập nhật mật khẩu" teal. Trạng thái token hết hạn: banner đỏ + nút "Gửi lại liên kết".
Màu teal #0F766E.
```

### 2.6 Nhập mã 2FA (`/2fa/challenge`)
```
Màn hình "Xác thực hai lớp" MedPro, một cột giữa.
Nội dung: icon khiên bảo mật, tiêu đề "Nhập mã xác thực", mô tả "Nhập mã 6 số từ ứng dụng Authenticator", 6 ô nhập OTP lớn cách đều tự động nhảy ô, nút "Xác nhận" teal, link "Dùng mã dự phòng". Bàn phím số trên mobile.
Màu teal #0F766E.
```

### 2.7 Onboarding wizard (`/onboarding`)
```
Wizard onboarding MedPro toàn màn hình cho người dùng mới, có stepper ngang "Bước 2/4" ở trên.
Hiển thị bước 2 "Mục tiêu học tập": tiêu đề "Bạn đang chuẩn bị cho kỳ thi nào?", lưới thẻ chọn có icon (Bác sĩ nội trú, Tốt nghiệp Y khoa, Chứng chỉ hành nghề, Ôn tập tổng quát); chọn ngày thi mục tiêu bằng date picker "15/06/2026"; chọn chuyên ngành quan tâm dạng chip nhiều lựa chọn (Nội khoa, Tim mạch, Nhi khoa, Dược lý...). Nút "Quay lại" viền + "Tiếp tục" teal, link "Bỏ qua".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: stepper dọc, thẻ 1 cột. Màu teal #0F766E.
```

---

## Module 03 — Dashboard
Trang chủ sau đăng nhập của người học — trung tâm điều khiển học tập.

### 3.1 Dashboard chính (`/dashboard`)
```
Màn hình dashboard "Trang chủ học tập" (route /dashboard) cho sinh viên, bố cục app shell: sidebar trái (menu Trang chủ, Ngân hàng câu hỏi, Thư viện, Flashcards, Lộ trình học, Phân tích, Kỳ thi) + header trên (ô tìm kiếm toàn cục, chuông thông báo có chấm đỏ, huy hiệu "Nâng cấp" gradient vàng-cam, avatar "Nguyễn Văn An").
Vùng nội dung từ trên xuống:
(1) Lời chào "Chào buổi sáng, An 👋" + huy hiệu streak "🔥 15 ngày".
(2) Thẻ lớn "Tiếp tục học" — "Phiên Tim mạch · Câu 12/40 · Chế độ Study", thanh tiến trình, nút "Tiếp tục" teal.
(3) Lưới 4 StatWidget: "Câu đã làm 1.240 (+45)", "Tỷ lệ đúng 72% (+3%)", "Thời gian học tuần 6h 20m", "Streak 15 ngày", mỗi thẻ có icon và mini-sparkline.
(4) Biểu đồ đường tiến trình 30 ngày (số câu/ngày) kèm bộ chọn 7 ngày/30 ngày/tất cả.
(5) 2 cột: cột trái widget "Chủ đề yếu" liệt kê Dược lý 48%, Thần kinh 55%, Nội tiết 61% có thanh % màu đỏ-cam + nút "Luyện ngay"; cột phải widget "Nhiệm vụ hôm nay" từ lộ trình học (Làm 30 câu Tim mạch, Ôn 15 flashcard) có checkbox.
(6) Carousel "Gợi ý cho bạn": thẻ câu hỏi/bài đọc/flashcard.
(7) Danh sách "Hoạt động gần đây" dạng timeline.
Banner nâng cấp Premium gradient vàng-cam cho tài khoản Free.
Trạng thái rỗng người mới: minh họa + CTA "Làm bài kiểm tra đầu vào".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: sidebar thành bottom navigation, widget xếp 1 cột, gợi ý dạng carousel. Màu teal #0F766E.
```

---

## Module 04 — Study Plan
Lộ trình học cá nhân hóa theo ngày thi mục tiêu.

### 4.1 Tổng quan kế hoạch (`/study-plan`)
```
Màn hình "Lộ trình học" (route /study-plan), app shell sidebar + header.
Khối chính: header trang có tên kế hoạch "Ôn thi Bác sĩ nội trú 2026" + progress ring lớn "42% hoàn thành" + đếm ngược "Còn 84 ngày đến kỳ thi"; panel "Hôm nay" liệt kê task (Làm 30 câu Tim mạch 0/30, Đọc bài 'Suy tim' , Ôn 15 flashcard) với nút bắt đầu; lưới thống kê nhỏ (câu đã hoàn thành trong kế hoạch, độ bám lịch); banner adaptive teal nhạt "Kế hoạch đã được điều chỉnh: tăng câu Dược lý do tỷ lệ đúng thấp". Nút "Tạo kế hoạch mới".
Trạng thái rỗng (chưa có kế hoạch): minh họa + tiêu đề "Chưa có lộ trình học" + nút "Tạo lộ trình học" teal.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 cột, task hôm nay nổi bật. Màu teal #0F766E.
```

### 4.2 Wizard tạo kế hoạch (`/study-plan/create`)
```
Wizard "Tạo lộ trình học" MedPro, app shell, stepper ngang 4 bước (Kỳ thi · Ngày thi · Phạm vi · Cường độ).
Hiển thị đồng thời tóm tắt các bước: chọn kỳ thi (thẻ Bác sĩ nội trú đang chọn), date picker ngày thi "15/06/2026", chọn phạm vi chủ đề dạng chip (Nội khoa, Tim mạch, Hô hấp, Tiêu hóa...), slider cường độ "40 câu/ngày ~ 90 phút", chọn chiến lược (Cố định / Thích ứng-Premium). Panel xem trước bên phải: "Tổng 3.360 câu · 84 ngày · 40 câu/ngày". Nút "Quay lại" + "Tạo lộ trình" teal.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: stepper dọc, xem trước xuống dưới. Màu teal #0F766E, nhãn Premium gradient vàng-cam.
```

### 4.3 Chi tiết kế hoạch & lịch (`/study-plan/{id}`)
```
Màn hình chi tiết lộ trình học với lịch, app shell.
Khối chính: header kế hoạch + progress; lịch dạng tháng (Calendar) với các ô ngày hiển thị chấm/nhãn khối lượng task ("30 câu", "Ôn 20 thẻ"), ngày hôm nay viền teal, ngày đã hoàn thành nền xanh nhạt, ngày lỡ nền đỏ nhạt; panel bên phải chi tiết task của ngày được chọn với checkbox và nút bắt đầu; nút "Chỉnh sửa kế hoạch".
Modal "Dời lịch": khi task bị lỡ, chọn ngày mới bằng date picker.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: lịch chuyển sang danh sách agenda theo ngày. Màu teal #0F766E.
```

---

## Module 05 — Question Bank (Qbank)
Trình tạo phiên luyện tập bằng bộ lọc, duyệt câu hỏi, lịch sử phiên.

### 5.1 Trình tạo phiên / Filter builder (`/qbank`)
```
Màn hình "Ngân hàng câu hỏi — Tạo phiên luyện" (route /qbank), app shell sidebar + header.
Bố cục 2 cột: cột trái là panel bộ lọc gồm nhóm checkbox Chuyên ngành (Nội khoa, Ngoại khoa, Tim mạch, Nhi khoa, Dược lý...), Hệ cơ quan, Độ khó (Dễ/Trung bình/Khó), Trạng thái câu (Chưa làm/Làm sai/Làm đúng/Đã đánh dấu/Bỏ qua) dạng chip, Tag (High-yield). Cột phải: thẻ "Cấu hình phiên" gồm toggle chế độ Study/Exam, ô nhập số câu "40", (nếu Exam) ô thời gian "60 phút"; badge count realtime lớn "Khớp 1.284 câu hỏi" cập nhật theo lọc; tab nguồn "Tất cả / Tùy chọn / Chủ đề yếu / Đã đánh dấu"; chips bộ lọc đã lưu; nút lớn "Bắt đầu luyện" teal (disabled khi 0 câu).
Trạng thái rỗng: "Không có câu hỏi khớp bộ lọc — thử bỏ bớt tiêu chí".
PaywallOverlay cho câu Premium khi tài khoản Free vượt quota "20 câu/ngày", nút "Nâng cấp".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: bộ lọc thành bottom sheet, count + nút "Bắt đầu" sticky đáy. Màu teal #0F766E.
```

### 5.2 Duyệt danh sách câu hỏi (`/qbank/browse`)
```
Màn hình "Duyệt câu hỏi" (route /qbank/browse), app shell.
Khối chính: thanh công cụ tìm kiếm + bộ lọc thu gọn + sắp xếp; bảng/list câu hỏi mỗi dòng gồm checkbox chọn, đoạn preview đề bài tiếng Việt (không lộ đáp án), badge chuyên ngành (Tim mạch), badge độ khó, badge trạng thái (Làm sai màu đỏ / Chưa làm màu xám), icon ổ khóa cho câu Premium; thanh hành động hàng loạt hiện khi chọn nhiều "Đã chọn 12 câu — Tạo phiên · Đánh dấu"; phân trang / cuộn vô hạn.
Trạng thái loading skeleton dòng. Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: bảng chuyển thành thẻ. Màu teal #0F766E.
```

### 5.3 Lịch sử phiên (`/qbank/sessions`)
```
Màn hình "Lịch sử phiên luyện" (route /qbank/sessions), app shell.
Khối chính: bộ lọc theo chế độ/chủ đề/thời gian; bảng các phiên mỗi dòng: ngày "10/07/2026", tên/nguồn ("Tùy chọn - Tim mạch"), chế độ (Study/Exam badge), số câu "40", tỷ lệ đúng "75%" có thanh mini, trạng thái (Hoàn thành/Tạm dừng), nút "Xem lại" và "Tiếp tục" (nếu tạm dừng). Thẻ tóm tắt trên cùng: tổng phiên, trung bình đúng.
Trạng thái rỗng: "Bạn chưa có phiên luyện nào — Bắt đầu ngay". Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: bảng chuyển thành thẻ. Màu teal #0F766E.
```

---

## Module 06 — Question Session (Player)
Giao diện làm bài — trái tim sản phẩm.

### 6.1 Màn làm bài — Study mode (`/qbank/session/{id}`)
```
Màn hình làm bài chế độ Study (route /qbank/session/{id}) toàn màn hình, tối giản không sidebar chính.
Bố cục:
- Header phiên: nút thoát (X), tiến trình "Câu 12/40", thanh progress mảnh, nhãn chế độ "Study", icon "Đã lưu" (autosave), nút mở Question Navigator.
- Vùng chính 2 cột: cột trái là đề bài — vignette tiếng Việt "Bệnh nhân nam 58 tuổi, tiền sử tăng huyết áp và đái tháo đường type 2, nhập viện vì đau ngực sau xương ức lan vai trái 40 phút. ECG ST chênh lên V1–V4." + lead-in in đậm "Chẩn đoán phù hợp nhất là gì?"; cột phải 5 đáp án A–E dạng nút lớn có thể chọn (A. Nhồi máu cơ tim cấp thành trước; B. Viêm màng ngoài tim; C. Bóc tách động mạch chủ; D. Thuyên tắc phổi; E. Trào ngược dạ dày thực quản).
- Sau khi bấm "Kiểm tra": tô xanh #16A34A đáp án đúng (A), đỏ #DC2626 nếu chọn sai; hiện ExplanationPanel bên dưới với giải thích tổng, giải thích từng đáp án, mục "Tham khảo" và cross-link "Xem bài: Nhồi máu cơ tim cấp" trong thư viện.
- Thanh công cụ câu hỏi (icon): Đánh dấu (bookmark), Cờ (flag), Ghi chú, Bôi vàng (highlight), Báo lỗi, Hỏi AI, Tạo flashcard.
- Nút chính dưới cùng "Kiểm tra đáp án" → sau đó thành "Câu tiếp theo →" (sticky đáy).
- Nút phụ mở "Chỉ số xét nghiệm" và "Máy tính lâm sàng".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: 1 câu/màn full-screen, đáp án nút lớn, thanh công cụ thu vào menu ..., nút chính sticky đáy, đề bài cuộn. Font Be Vietnam Pro, màu teal #0F766E.
```

### 6.2 Màn làm bài — Exam mode
```
Biến thể màn làm bài chế độ Exam (route /qbank/session/{id}?mode=exam) toàn màn hình.
Khác Study: header có đồng hồ đếm ngược lớn "58:24" (chuyển cam khi <5 phút), không hiện giải thích sau mỗi câu, nút chính là "Câu tiếp theo" và ở câu cuối là "Nộp bài" đỏ; đáp án cho phép để trống (omitted). Toast cảnh báo "Còn 5 phút" ở góc. Question Navigator dạng lưới số câu (đã trả lời nền teal, đánh dấu cờ có góc cam, chưa làm viền xám).
Modal "Nộp bài": "Bạn đã trả lời 37/40 câu, 3 câu bỏ trống. Xác nhận nộp?" nút "Nộp bài" đỏ + "Tiếp tục làm".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: timer thu gọn ở header, navigator dạng bottom drawer. Màu teal #0F766E.
```

### 6.3 Modal tạm dừng & Question Navigator drawer
```
Hai overlay cho màn làm bài MedPro.
(1) Modal "Tạm dừng phiên" giữa màn hình: icon tạm dừng, "Bạn muốn thoát? Tiến trình đã được lưu, có thể tiếp tục sau từ Trang chủ", nút "Lưu & thoát" teal + "Tiếp tục làm bài" viền.
(2) Drawer "Bản đồ câu hỏi" trượt từ phải: lưới số 1–40, chú thích màu (đã trả lời, đã đánh dấu cờ, chưa làm), bộ lọc nhanh "Chỉ câu đã đánh dấu", nút "Nhảy tới câu". 
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: drawer chiếm toàn màn hình từ đáy. Màu teal #0F766E.
```

---

## Module 07 — Question Review
Xem lại kết quả sau phiên/kỳ thi, đóng vòng lặp học tập.

### 7.1 Tổng kết phiên (`/qbank/session/{id}/summary`)
```
Màn hình "Tổng kết phiên luyện" (route /qbank/session/{id}/summary), app shell sidebar + header.
Khối chính:
(1) Thẻ điểm lớn: donut chart tỷ lệ đúng "75%", các số Đúng 30 / Sai 8 / Bỏ 2, thời gian "42 phút", (nếu exam) điểm và percentile "Top 20%".
(2) Biểu đồ cột tỷ lệ đúng theo chủ đề.
(3) Bảng "Phân tích theo chủ đề": mỗi dòng chủ đề (Tim mạch 85%, Dược lý 45% tô đỏ, Hô hấp 70%) với thanh %, số đúng/tổng, nút "Ôn chủ đề này".
(4) Thanh hành động sticky: "Ôn lại câu sai (8)" teal, "Tạo flashcard từ câu sai", "Xem lại từng câu".
(5) So sánh cộng đồng (Premium) dạng bar "Bạn 75% · Trung bình 68%" — nếu Free thì mờ + nút "Nâng cấp".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: chart full, bảng thành thẻ, action bar sticky đáy. Màu teal #0F766E, đúng #16A34A, sai #DC2626.
```

### 7.2 Xem lại từng câu — danh sách (`/qbank/session/{id}/review`)
```
Màn hình "Xem lại câu hỏi" (route /qbank/session/{id}/review), app shell, bố cục 2 cột.
Cột trái: chips lọc "Tất cả · Đúng · Sai · Bỏ qua · Đã đánh dấu", danh sách câu mỗi dòng có số thứ tự, icon trạng thái (✓ xanh / ✗ đỏ / – xám), preview đề bài, chuyên ngành, "Bạn chọn B · Đúng A". Cột phải: chi tiết câu đang chọn.
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: danh sách trước, mở chi tiết full-screen. Màu teal #0F766E.
```

### 7.3 Chi tiết một câu khi xem lại (`/qbank/session/{id}/review/{qid}`)
```
Màn hình chi tiết câu khi xem lại (route .../review/{qid}), app shell.
Khối chính: đề bài vignette đầy đủ; 5 đáp án hiển thị trạng thái cố định — đáp án đúng nền xanh có nhãn "Đáp án đúng", đáp án bạn chọn sai nền đỏ có nhãn "Bạn đã chọn"; ExplanationPanel đầy đủ giải thích tổng + từng đáp án + tham khảo + cross-link thư viện; thanh công cụ (Tạo flashcard, Hỏi AI, Ghi chú, Đánh dấu). Nút điều hướng "← Câu trước / Câu sau →".
Tạo đồng thời 2 khung trong cùng màn hình — bản Desktop (bố cục đầy đủ như mô tả trên) và bản Mobile. Trên mobile: full-width. Màu teal #0F766E.
```
