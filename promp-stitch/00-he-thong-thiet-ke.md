# 00 — Hệ thống thiết kế & Prompt nền tảng cho Stitch

> File này là **nền tảng dùng chung** cho toàn bộ prompt Stitch. Dán **Prompt Theme (mục 2)** vào Stitch **một lần** trước khi tạo màn hình, sau đó mỗi màn hình chỉ cần prompt riêng (ở các file `01-...` → `06-...`). Mọi màn hình đều tham chiếu design token, thư viện component và bộ dữ liệu mẫu tiếng Việt ở đây.

---

## 1. Cách dùng bộ prompt này với Stitch

1. **Bước 1 — Cài theme:** Mở [Stitch](https://stitch.withgoogle.com), tạo project mới. Dán **Prompt Theme** ở mục 2 vào ô mô tả đầu tiên (hoặc phần "Design / Theme"). Đây là "bộ khung thương hiệu" áp cho mọi màn hình.
2. **Bước 2 — Tạo từng màn hình:** Copy prompt của từng màn hình trong các file module (`01`→`06`). Mỗi prompt là **một màn hình**. Dán lần lượt, mỗi lần một màn hình.
3. **Bước 3 — Tinh chỉnh:** Sau khi Stitch sinh giao diện, dùng câu lệnh chỉnh sửa ngắn (vd: *"đổi biểu đồ sang dạng cột"*, *"thêm trạng thái rỗng"*).

### Chống Stitch "quên" (header/sidebar/footer lệch giữa các màn)

Stitch sinh **mỗi màn hình một lần độc lập**, không tự nhớ khung màn trước. Để đồng bộ:

1. **Làm việc trong CÙNG một project Stitch**, dựng **màn "Trang chủ" trước tiên** làm màn tham chiếu chuẩn cho khung.
2. **Dán khối KHUNG CHUNG (mục 4A) y NGUYÊN vào đầu mỗi prompt** — giống nhau từng chữ thì Stitch mới dựng giống nhau. Đừng viết lại theo cách khác nhau.
3. Với màn tiếp theo, ưu tiên câu nối: *"Giữ NGUYÊN sidebar và header y hệt màn Trang chủ đã tạo, chỉ thay vùng nội dung ở giữa thành: …"* (chỉnh sửa tiếp trong cùng luồng thay vì tạo mới hoàn toàn).
4. Nếu vẫn lệch: sửa tại chỗ bằng *"làm sidebar/header giống hệt mục 4A"* rồi dán lại đúng khối 4A.
5. Chốt **văn bản khung là bất biến**: không đổi nhãn, thứ tự mục, icon hay avatar giữa các màn.
6. **Ngôn ngữ:** Toàn bộ nhãn UI và dữ liệu mẫu là **tiếng Việt**. Giữ vài thuật ngữ y khoa/kỹ thuật tiếng Anh khi phổ biến (Qbank, Flashcard, Heatmap...).
7. **Nền tảng gốc:** Đây là SaaS học & luyện thi Y khoa (mô hình AMBOSS/UWorld) cho thị trường Việt Nam — xem `srs/`.

---

## 2. PROMPT THEME (dán vào Stitch một lần)

```
Thiết kế một web app SaaS y khoa chuyên nghiệp tên "MedPro" — nền tảng học và luyện thi Y khoa trực tuyến cho sinh viên y và bác sĩ trẻ tại Việt Nam (mô hình AMBOSS/UWorld). Toàn bộ nội dung, nhãn nút, menu và dữ liệu mẫu bằng TIẾNG VIỆT.

Phong cách hình ảnh: hiện đại, sạch sẽ, đáng tin cậy, mật độ thông tin cao nhưng thoáng, giống các SaaS cao cấp (Linear, Notion, AMBOSS). Bo góc mềm 12px, đổ bóng nhẹ, đường kẻ mảnh màu xám nhạt, nhiều khoảng trắng.

Bảng màu:
- Primary (thương hiệu, nút chính, link): xanh y khoa #0F766E (teal-700), hover #0D9488.
- Secondary/accent: xanh dương #2563EB.
- Nền trang: #F8FAFC; bề mặt thẻ/card: #FFFFFF; viền: #E2E8F0.
- Chữ chính: #0F172A; chữ phụ: #64748B.
- Trạng thái: đúng/thành công #16A34A, sai/lỗi #DC2626, cảnh báo #D97706, thông tin #2563EB.
- Nhãn Premium: gradient vàng-cam #F59E0B → #EA580C.

Typography: font "Be Vietnam Pro" (hỗ trợ tiếng Việt tốt). Tiêu đề đậm (600–700), thân chữ 400–500, cỡ nền 14–16px, có phân cấp rõ.

Bố cục ứng dụng (sau đăng nhập): Sidebar trái cố định (rộng ~240px) chứa logo "MedPro" + menu điều hướng có icon; Header trên cùng chứa ô tìm kiếm toàn cục, chuông thông báo, huy hiệu "Nâng cấp", avatar người dùng. Vùng nội dung dạng lưới card.
NHẤT QUÁN KHUNG (BẮT BUỘC): Sidebar, Header, Footer công khai và Bottom nav phải GIỐNG HỆT NHAU trên mọi màn hình — cùng thứ tự mục, cùng nhãn tiếng Việt, cùng icon, cùng avatar "Nguyễn Văn An". Không được đổi menu hay bố cục khung giữa các màn; chỉ thay phần nội dung ở giữa.

Iconography: icon nét mảnh (outline) đồng nhất, phong cách Lucide/Phosphor.
Accessibility: tương phản đạt WCAG AA, có focus ring rõ, trạng thái không chỉ dựa vào màu.
Responsive: mobile-first; trên mobile sidebar thu thành bottom navigation, lưới card xếp 1 cột.
QUAN TRỌNG — ĐA THIẾT BỊ: Với MỖI màn hình, LUÔN thiết kế 2 MÀN HÌNH RIÊNG BIỆT, TÁCH RỜI, KHÔNG gộp chung: (1) một màn hình Desktop (đầy đủ sidebar) và (2) một màn hình Mobile (sidebar thành bottom navigation, nội dung xếp 1 cột). Không bao giờ chỉ xuất một bản.
Luôn hiển thị giao diện với dữ liệu mẫu tiếng Việt thực tế (tên người Việt, chuyên ngành y khoa Việt, tiền tệ VND).
```

---

## 3. Design tokens (tham chiếu nhanh)


| Nhóm       | Token                 | Giá trị                                   |
| ---------- | --------------------- | ----------------------------------------- |
| Màu        | Primary               | `#0F766E` (hover `#0D9488`)               |
| Màu        | Accent                | `#2563EB`                                 |
| Màu        | Nền / Card / Viền     | `#F8FAFC` / `#FFFFFF` / `#E2E8F0`         |
| Màu        | Chữ chính / phụ       | `#0F172A` / `#64748B`                     |
| Màu        | Đúng / Sai / Cảnh báo | `#16A34A` / `#DC2626` / `#D97706`         |
| Màu        | Premium               | gradient `#F59E0B → #EA580C`              |
| Font       | Chữ                   | Be Vietnam Pro                            |
| Bo góc     | Card / nút / input    | 12px / 10px / 10px                        |
| Bóng       | Card                  | shadow nhẹ `0 1px 3px rgba(15,23,42,.08)` |
| Sidebar    | Rộng                  | 240px (thu gọn 72px chỉ icon)             |
| Breakpoint | sm / lg / xl          | 640 / 1024 / 1280px                       |


---

## 4. Thư viện component dùng lại (nhắc tên trong prompt là đủ)

- **App Shell:** Sidebar + Header + Breadcrumb + Footer công khai + Bottom nav — **nội dung chính xác, bất biến xem mục 4A** (luôn dán y hệt để chống lệch giữa các màn).
- **Học tập:** Thẻ "Tiếp tục học", QuestionCard, AnswerOption (A–E), ExplanationPanel, QuestionToolbar (Bookmark/Flag/Note/Highlight/AI/Flashcard), QuestionNavigator, SessionTimer, Bảng chỉ số xét nghiệm (Lab values).
- **Dữ liệu:** StatWidget (số + delta + xu hướng), biểu đồ (đường/cột/donut/radar) kiểu Chart.js, Heatmap lịch, Progress ring, Bảng có sort/filter/pagination, Timeline hoạt động.
- **Gating:** PaywallOverlay (preview mờ + nút "Nâng cấp"), UpgradeBadge, QuotaMeter ("Còn 8/20 câu hôm nay").
- **Trạng thái:** Skeleton loading, Empty state (minh họa + CTA), Error state (thử lại), Toast, Modal, Drawer, Bottom sheet (mobile).
- **Form:** Input/Select/Textarea có label + helper + lỗi, Toggle, Checkbox, Radio, Slider, Date picker, File uploader (kéo-thả).

---

## 4A. KHUNG CHUNG — BẤT BIẾN (chống Stitch "quên" & lệch header/sidebar/footer)

> **Cách chống lệch:** Stitch sinh mỗi màn một lần độc lập; mô tả bằng lời khác nhau → nó dựng khác nhau. Vì vậy **4 khối dưới đây là VĂN BẢN CỐ ĐỊNH — dán y NGUYÊN, KHÔNG diễn giải lại, KHÔNG đổi nhãn/thứ tự/icon** vào MỌI prompt liên quan. Prompt màn hình chỉ mô tả **vùng nội dung ở giữa**.

**A. SIDEBAR ứng dụng (desktop, rộng 240px, nền trắng, viền phải #E2E8F0) — thứ tự CỐ ĐỊNH:**
Trên cùng: logo chữ **"MedPro"** màu teal #0F766E + icon dấu cộng y tế.
Menu chính (icon outline Lucide bên trái, chữ 14px):

1. Trang chủ (home)
2. Lộ trình học (map/route)
3. Ngân hàng câu hỏi (list-checks)
4. Flashcards (layers)
5. Thư viện (book-open) — có submenu: Bệnh học, Thuốc, Thủ thuật, Hình ảnh, Video
6. Ghi chú & Đánh dấu (bookmark)
7. Phân tích học tập (bar-chart)
8. Kỳ thi (file-check)

Đáy sidebar (ghim dưới): thẻ **"Nâng cấp Premium"** gradient vàng-cam #F59E0B→#EA580C + nút; mục **Cài đặt** (settings); khối avatar tròn "NA" + tên **"Nguyễn Văn An"** + email `an.nguyen@sv.vnu.edu.vn` (bấm mở menu).
Mục đang chọn: nền teal nhạt (#0F766E ~10%), chữ + icon teal, có thanh nhấn 3px bên trái.

**B. HEADER ứng dụng (cao 64px, nền trắng, viền dưới #E2E8F0) — thứ tự trái→phải CỐ ĐỊNH:**
Trái: nút thu/mở sidebar (hamburger) + Breadcrumb (vd "Trang chủ / Ngân hàng câu hỏi").
Giữa: ô tìm kiếm toàn cục rộng, bo 10px, icon kính lúp, placeholder **"Tìm câu hỏi, bệnh học, thuốc…  Ctrl K"**.
Phải: huy hiệu **"Nâng cấp"** gradient vàng-cam → icon trợ giúp (?) → chuông thông báo có badge đỏ **"3"** → avatar tròn "NA" (mở menu: Hồ sơ, Cài đặt, Đăng xuất).

**C. HEADER công khai (trang chưa đăng nhập: landing, bảng giá, blog) — CỐ ĐỊNH:**
Trái: logo **"MedPro"**. Giữa: menu **Tính năng · Ngân hàng câu hỏi · Thư viện · Bảng giá · Blog**. Phải: **"Đăng nhập"** (dạng text) + **"Dùng thử miễn phí"** (nút teal #0F766E). Trên mobile: gộp thành hamburger drawer.

**D. FOOTER công khai (mọi trang public) — CỐ ĐỊNH, 4 cột + thanh dưới:**

- **Sản phẩm:** Ngân hàng câu hỏi · Thư viện y khoa · Flashcards · Lộ trình học · Bảng giá.
- **Công ty:** Về chúng tôi · Blog · Tuyển dụng · Liên hệ.
- **Hỗ trợ:** Trung tâm trợ giúp · Điều khoản dịch vụ · Chính sách bảo mật.
- **Kết nối:** icon Facebook · YouTube · email `hotro@MedPro.vn` + ô đăng ký bản tin (input email + nút "Đăng ký").
Thanh dưới cùng: logo nhỏ + **"© 2026 MedPro — Nền tảng học & luyện thi Y khoa."** + chọn ngôn ngữ "Tiếng Việt".

**E. BOTTOM NAVIGATION (mobile, thay sidebar) — 5 mục CỐ ĐỊNH:**
Trang chủ (home) · Câu hỏi (list-checks) · Thư viện (book-open) · Phân tích (bar-chart) · Tài khoản (avatar). Mục đang chọn tô teal #0F766E.

> **Cú pháp dùng nhanh trong mỗi prompt màn hình sau đăng nhập:** mở đầu bằng câu
> *"Dùng KHUNG APP CHUẨN: sidebar 240px và header như đã mô tả (giữ NGUYÊN nhãn, thứ tự, icon, avatar 'Nguyễn Văn An' — KHÔNG thay đổi). Chỉ thiết kế vùng nội dung ở giữa như sau: …"*
> Với trang công khai: *"Dùng HEADER CÔNG KHAI và FOOTER CÔNG KHAI chuẩn (giữ nguyên). Nội dung giữa: …"*

---

## 5. Bộ dữ liệu mẫu tiếng Việt (tái sử dụng cho mọi màn hình)

**Người dùng mẫu:** Nguyễn Văn An, Trần Thị Bích Ngọc, Lê Minh Quân, Phạm Thu Hà, Đỗ Hoàng Long, Vũ Thị Mai (email dạng `an.nguyen@sv.vnu.edu.vn`).

**Chuyên ngành / chủ đề (Topic):** Nội khoa, Ngoại khoa, Sản phụ khoa, Nhi khoa, Tim mạch, Hô hấp, Tiêu hóa, Thần kinh, Nội tiết, Truyền nhiễm, Dược lý, Giải phẫu, Sinh lý, Miễn dịch, Vi sinh, Da liễu, Tâm thần, Chẩn đoán hình ảnh.

**Vignette câu hỏi mẫu:**

> *Bệnh nhân nam 58 tuổi, tiền sử tăng huyết áp và đái tháo đường type 2, nhập viện vì đau ngực sau xương ức lan lên vai trái kéo dài 40 phút. ECG cho thấy ST chênh lên ở các chuyển đạo V1–V4.*
> **Lead-in:** *Chẩn đoán phù hợp nhất là gì?*
> **Đáp án:** A. Nhồi máu cơ tim cấp thành trước · B. Viêm màng ngoài tim · C. Bóc tách động mạch chủ · D. Thuyên tắc phổi · E. Trào ngược dạ dày thực quản. (Đúng: A)

**Bệnh học mẫu:** Nhồi máu cơ tim cấp, Đái tháo đường type 2, Viêm phổi cộng đồng, Xuất huyết tiêu hóa trên, Đột quỵ nhồi máu não, Sốt xuất huyết Dengue, Hen phế quản, Suy tim.

**Thuốc mẫu:** Paracetamol, Amoxicillin, Metformin, Amlodipin, Aspirin, Salbutamol, Omeprazol, Furosemid, Insulin.

**Trường/Đại học:** Đại học Y Hà Nội, Đại học Y Dược TP.HCM, Đại học Y Dược Huế, Bệnh viện Bạch Mai, Bệnh viện Chợ Rẫy.

**Kỳ thi mẫu:** Mô phỏng thi Bác sĩ nội trú, Kỳ thi tốt nghiệp Y khoa, Đánh giá năng lực Nội khoa.

**Gói & giá (VND):**

- **Miễn phí** — 0₫: 20 câu/ngày, thư viện giới hạn.
- **Premium 1 tháng** — 199.000₫/tháng: toàn bộ Qbank, thư viện, AI, phân tích nâng cao.
- **Premium 6 tháng** — 990.000₫ (~165.000₫/tháng, tiết kiệm ~17%): quyền lợi như Premium.
- **Premium 12 tháng** — 1.490.000₫ (~124.000₫/tháng): quyền lợi như Premium.

**Thanh toán (VN):** MoMo, VNPay, ZaloPay, chuyển khoản ngân hàng, thẻ Visa/Mastercard.
**Số liệu mẫu:** "12.450 câu hỏi", "38.000+ người học", correct rate "72%", streak "15 ngày".

---

## 6. TEMPLATE viết prompt cho MỖI màn hình (bắt buộc theo)

Mỗi màn hình = 1 prompt. Viết theo cấu trúc sau (câu văn liền mạch, tiếng Việt):

```
[Loại màn hình] "[Tên màn hình]" (route /...) cho [vai trò người dùng].
Bố cục: [KHUNG nào — "KHUNG APP CHUẨN (mục 4A), giữ nguyên sidebar+header" / "HEADER + FOOTER CÔNG KHAI (mục 4A)" / full-screen không khung]. Dán y hệt, không diễn giải lại.
Các khối chính từ trên xuống: [liệt kê từng section + component + dữ liệu mẫu tiếng Việt cụ thể].
Trạng thái đặc biệt: [loading skeleton / empty / error / paywall nếu có].
Đa thiết bị (BẮT BUỘC): thiết kế 2 màn hình RIÊNG BIỆT — một màn hình Desktop và một màn hình Mobile (tách rời, không gộp chung); nêu [khác biệt trên mobile].
Ghi chú màu/nhấn: [theo design token].
```

**Quy tắc:**

- Luôn đưa **dữ liệu mẫu tiếng Việt cụ thể** (tên, con số, nhãn) — không nói chung chung.
- Nêu rõ **component tái dùng** ở mục 4 khi phù hợp.
- Với nội dung Premium, mô tả **PaywallOverlay** (preview mờ + nút "Nâng cấp").
- Mỗi màn hình nêu ít nhất 1 trạng thái phụ (empty/loading/error) khi hợp lý.
- **Mỗi màn hình thiết kế 2 màn hình RIÊNG BIỆT**: một màn hình Desktop và một màn hình Mobile (tách rời, không gộp chung) — luôn có câu "Thiết kế 2 màn hình RIÊNG BIỆT… Trên mobile: …" ở cuối prompt.
- Bám **design token** (không tự chế màu mới).

**Ví dụ 1 prompt hoàn chỉnh:**

> *Màn hình dashboard "Trang chủ học tập" (route `/dashboard`) cho sinh viên. Bố cục app shell: sidebar trái + header trên. Khối chính: (1) lời chào "Chào buổi sáng, An 👋" kèm huy hiệu streak "🔥 15 ngày"; (2) thẻ lớn "Tiếp tục học — Phiên Tim mạch, câu 12/40" nút "Tiếp tục"; (3) lưới 4 StatWidget: "Câu đã làm 1.240 (+45)", "Tỷ lệ đúng 72%", "Thời gian học tuần này 6h 20m", "Streak 15 ngày"; (4) biểu đồ đường tiến trình 30 ngày; (5) widget "Chủ đề yếu" liệt kê Dược lý 48%, Thần kinh 55% + nút "Luyện ngay"; (6) danh sách "Hoạt động gần đây". Có banner nâng cấp Premium gradient vàng-cam cho tài khoản Free. Trạng thái rỗng cho người mới: minh họa + CTA "Làm bài kiểm tra đầu vào". Thiết kế 2 màn hình RIÊNG BIỆT (tách rời, không gộp chung): một màn hình Desktop (đầy đủ như trên) và một màn hình Mobile (sidebar thành bottom nav, widget xếp 1 cột). Màu chủ đạo teal #0F766E.*

