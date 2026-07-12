# 00 — Hệ thống thiết kế & Prompt nền tảng cho Stitch

> File này là **nền tảng dùng chung** cho toàn bộ prompt Stitch. Dán **Prompt Theme (mục 2)** vào Stitch **một lần** trước khi tạo màn hình, sau đó mỗi màn hình chỉ cần prompt riêng (ở các file `01-...` → `06-...`). Mọi màn hình đều tham chiếu design token, thư viện component và bộ dữ liệu mẫu tiếng Việt ở đây.

---

## 1. Cách dùng bộ prompt này với Stitch

1. **Bước 1 — Cài theme:** Mở [Stitch](https://stitch.withgoogle.com), tạo project mới. Dán **Prompt Theme** ở mục 2 vào ô mô tả đầu tiên (hoặc phần "Design / Theme"). Đây là "bộ khung thương hiệu" áp cho mọi màn hình.
2. **Bước 2 — Tạo từng màn hình:** Copy prompt của từng màn hình trong các file module (`01`→`06`). Mỗi prompt là **một màn hình**. Dán lần lượt, mỗi lần một màn hình.
3. **Bước 3 — Tinh chỉnh:** Sau khi Stitch sinh giao diện, dùng câu lệnh chỉnh sửa ngắn (vd: *"đổi biểu đồ sang dạng cột"*, *"thêm trạng thái rỗng"*).
4. **Ngôn ngữ:** Toàn bộ nhãn UI và dữ liệu mẫu là **tiếng Việt**. Giữ vài thuật ngữ y khoa/kỹ thuật tiếng Anh khi phổ biến (Qbank, Flashcard, Heatmap...).
5. **Nền tảng gốc:** Đây là SaaS học & luyện thi Y khoa (mô hình AMBOSS/UWorld) cho thị trường Việt Nam — xem `srs/`.

---

## 2. PROMPT THEME (dán vào Stitch một lần)

```
Thiết kế một web app SaaS y khoa chuyên nghiệp tên "MedLuyện" — nền tảng học và luyện thi Y khoa trực tuyến cho sinh viên y và bác sĩ trẻ tại Việt Nam (mô hình AMBOSS/UWorld). Toàn bộ nội dung, nhãn nút, menu và dữ liệu mẫu bằng TIẾNG VIỆT.

Phong cách hình ảnh: hiện đại, sạch sẽ, đáng tin cậy, mật độ thông tin cao nhưng thoáng, giống các SaaS cao cấp (Linear, Notion, AMBOSS). Bo góc mềm 12px, đổ bóng nhẹ, đường kẻ mảnh màu xám nhạt, nhiều khoảng trắng.

Bảng màu:
- Primary (thương hiệu, nút chính, link): xanh y khoa #0F766E (teal-700), hover #0D9488.
- Secondary/accent: xanh dương #2563EB.
- Nền trang: #F8FAFC; bề mặt thẻ/card: #FFFFFF; viền: #E2E8F0.
- Chữ chính: #0F172A; chữ phụ: #64748B.
- Trạng thái: đúng/thành công #16A34A, sai/lỗi #DC2626, cảnh báo #D97706, thông tin #2563EB.
- Nhãn Premium: gradient vàng-cam #F59E0B → #EA580C.

Typography: font "Be Vietnam Pro" (hỗ trợ tiếng Việt tốt). Tiêu đề đậm (600–700), thân chữ 400–500, cỡ nền 14–16px, có phân cấp rõ.

Bố cục ứng dụng (sau đăng nhập): Sidebar trái cố định (rộng ~240px) chứa logo "MedLuyện" + menu điều hướng có icon; Header trên cùng chứa ô tìm kiếm toàn cục, chuông thông báo, huy hiệu "Nâng cấp", avatar người dùng. Vùng nội dung dạng lưới card.

Iconography: icon nét mảnh (outline) đồng nhất, phong cách Lucide/Phosphor.
Accessibility: tương phản đạt WCAG AA, có focus ring rõ, trạng thái không chỉ dựa vào màu.
Responsive: mobile-first; trên mobile sidebar thu thành bottom navigation, lưới card xếp 1 cột.
QUAN TRỌNG — ĐA THIẾT BỊ: Với MỖI màn hình, LUÔN tạo ĐỒNG THỜI hai bản thiết kế đặt cạnh nhau trong cùng khung kết quả: (1) bản Desktop (khung rộng ≥1280px, đầy đủ sidebar) và (2) bản Mobile (khung 375px, sidebar thành bottom navigation, nội dung xếp 1 cột). Không bao giờ chỉ xuất một bản.
Luôn hiển thị giao diện với dữ liệu mẫu tiếng Việt thực tế (tên người Việt, chuyên ngành y khoa Việt, tiền tệ VND).
```

---

## 3. Design tokens (tham chiếu nhanh)

| Nhóm | Token | Giá trị |
|------|-------|---------|
| Màu | Primary | `#0F766E` (hover `#0D9488`) |
| Màu | Accent | `#2563EB` |
| Màu | Nền / Card / Viền | `#F8FAFC` / `#FFFFFF` / `#E2E8F0` |
| Màu | Chữ chính / phụ | `#0F172A` / `#64748B` |
| Màu | Đúng / Sai / Cảnh báo | `#16A34A` / `#DC2626` / `#D97706` |
| Màu | Premium | gradient `#F59E0B → #EA580C` |
| Font | Chữ | Be Vietnam Pro |
| Bo góc | Card / nút / input | 12px / 10px / 10px |
| Bóng | Card | shadow nhẹ `0 1px 3px rgba(15,23,42,.08)` |
| Sidebar | Rộng | 240px (thu gọn 72px chỉ icon) |
| Breakpoint | sm / lg / xl | 640 / 1024 / 1280px |

---

## 4. Thư viện component dùng lại (nhắc tên trong prompt là đủ)

- **App Shell:** Sidebar (menu: Trang chủ, Ngân hàng câu hỏi, Thư viện, Flashcards, Phân tích, Kỳ thi, Lộ trình học), Header (tìm kiếm, thông báo, upgrade badge, avatar menu), Breadcrumb.
- **Học tập:** Thẻ "Tiếp tục học", QuestionCard, AnswerOption (A–E), ExplanationPanel, QuestionToolbar (Bookmark/Flag/Note/Highlight/AI/Flashcard), QuestionNavigator, SessionTimer, Bảng chỉ số xét nghiệm (Lab values).
- **Dữ liệu:** StatWidget (số + delta + xu hướng), biểu đồ (đường/cột/donut/radar) kiểu Chart.js, Heatmap lịch, Progress ring, Bảng có sort/filter/pagination, Timeline hoạt động.
- **Gating:** PaywallOverlay (preview mờ + nút "Nâng cấp"), UpgradeBadge, QuotaMeter ("Còn 8/20 câu hôm nay").
- **Trạng thái:** Skeleton loading, Empty state (minh họa + CTA), Error state (thử lại), Toast, Modal, Drawer, Bottom sheet (mobile).
- **Form:** Input/Select/Textarea có label + helper + lỗi, Toggle, Checkbox, Radio, Slider, Date picker, File uploader (kéo-thả).

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

**Trường/Tổ chức:** Đại học Y Hà Nội, Đại học Y Dược TP.HCM, Đại học Y Dược Huế, Bệnh viện Bạch Mai, Bệnh viện Chợ Rẫy.

**Kỳ thi mẫu:** Mô phỏng thi Bác sĩ nội trú, Kỳ thi tốt nghiệp Y khoa, Đánh giá năng lực Nội khoa.

**Gói & giá (VND):**
- **Miễn phí** — 0₫: 20 câu/ngày, thư viện giới hạn.
- **Premium** — 199.000₫/tháng hoặc 1.490.000₫/năm: toàn bộ Qbank, thư viện, AI, phân tích nâng cao.
- **Tổ chức** — báo giá theo số ghế.

**Thanh toán (VN):** MoMo, VNPay, ZaloPay, chuyển khoản ngân hàng, thẻ Visa/Mastercard.
**Số liệu mẫu:** "12.450 câu hỏi", "38.000+ người học", correct rate "72%", streak "15 ngày".

---

## 6. TEMPLATE viết prompt cho MỖI màn hình (bắt buộc theo)

Mỗi màn hình = 1 prompt. Viết theo cấu trúc sau (câu văn liền mạch, tiếng Việt):

```
[Loại màn hình] "[Tên màn hình]" (route /...) cho [vai trò người dùng].
Bố cục: [app shell nào — sidebar+header, hay full-screen, hay public header/footer].
Các khối chính từ trên xuống: [liệt kê từng section + component + dữ liệu mẫu tiếng Việt cụ thể].
Trạng thái đặc biệt: [loading skeleton / empty / error / paywall nếu có].
Đa thiết bị (BẮT BUỘC): tạo đồng thời khung Desktop và khung Mobile; nêu [khác biệt trên mobile].
Ghi chú màu/nhấn: [theo design token].
```

**Quy tắc:**
- Luôn đưa **dữ liệu mẫu tiếng Việt cụ thể** (tên, con số, nhãn) — không nói chung chung.
- Nêu rõ **component tái dùng** ở mục 4 khi phù hợp.
- Với nội dung Premium, mô tả **PaywallOverlay** (preview mờ + nút "Nâng cấp").
- Mỗi màn hình nêu ít nhất 1 trạng thái phụ (empty/loading/error) khi hợp lý.
- **Mỗi màn hình xuất cả 2 bản**: một khung Desktop và một khung Mobile (mobile-first) — luôn có câu "Tạo đồng thời 2 khung… Trên mobile: …" ở cuối prompt.
- Bám **design token** (không tự chế màu mới).

**Ví dụ 1 prompt hoàn chỉnh:**
> *Màn hình dashboard "Trang chủ học tập" (route `/dashboard`) cho sinh viên. Bố cục app shell: sidebar trái + header trên. Khối chính: (1) lời chào "Chào buổi sáng, An 👋" kèm huy hiệu streak "🔥 15 ngày"; (2) thẻ lớn "Tiếp tục học — Phiên Tim mạch, câu 12/40" nút "Tiếp tục"; (3) lưới 4 StatWidget: "Câu đã làm 1.240 (+45)", "Tỷ lệ đúng 72%", "Thời gian học tuần này 6h 20m", "Streak 15 ngày"; (4) biểu đồ đường tiến trình 30 ngày; (5) widget "Chủ đề yếu" liệt kê Dược lý 48%, Thần kinh 55% + nút "Luyện ngay"; (6) danh sách "Hoạt động gần đây". Có banner nâng cấp Premium gradient vàng-cam cho tài khoản Free. Trạng thái rỗng cho người mới: minh họa + CTA "Làm bài kiểm tra đầu vào". Tạo đồng thời 2 khung trong cùng màn hình: bản Desktop (đầy đủ như trên) và bản Mobile (sidebar thành bottom nav, widget xếp 1 cột). Màu chủ đạo teal #0F766E.*
