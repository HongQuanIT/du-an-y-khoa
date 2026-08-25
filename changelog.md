# Changelog

## 2026-08-25

### Admin — menu Horizon
- Thêm mục **Horizon** trên sidebar quản trị (`/horizon`), chỉ hiện với quyền `system.manage` (Admin / Super Admin).
- Siết gate `viewHorizon`: học viên, giảng viên, content editor và guest không truy cập được (kể cả môi trường local).

### Billing — Guest chọn gói từ `/pricing` (biến thể A)
- CTA Premium mang `plan_price_id` → `/register` hoặc `/login`, giữ intent trong session + `url.intended`.
- Sau auth → `/subscription/upgrade?plan_price_id=` (preselect SKU, user xác nhận rồi thanh toán).
- Paywall overlay và link đăng ký/đăng nhập chéo cũng giữ intent; 2FA learner tôn trọng intended URL.

### Billing — `redirect_url` đủ dài cho VNPay
- Đổi `billing_checkout_sessions.redirect_url` từ `VARCHAR(255)` sang `TEXT` (SecureHash VNPay vượt 255 ký tự).

### Auth — `/profile` học viên không còn badge Quản trị viên
- `account-layout` dùng `layouts.app` cho learner, `layouts.admin` chỉ cho staff.
- Layout admin hiển thị nhãn role thật (không hardcode “Quản trị viên”).

### Admin — danh sách thanh toán gồm chờ / fail
- `/admin/billing/payments` liệt kê mọi **checkout session** (pending, completed, failed, expired).
- Tạo `Payment` status `pending` khi mở checkout; webhook cập nhật thành succeeded/failed.
- Tự chuyển phiên quá `expires_at` sang **Hết hạn** (khi mở admin + job 5 phút); đồng bộ payment/invoice.
- UI badge trạng thái dạng pill SaaS (dot + màu ổn định, không wrap vỡ layout).

### Landing — header khi đã đăng nhập
- Ẩn Đăng nhập / Đăng ký; hiện tên user, badge gói (Free / Premium nổi bật), CTA **Tạo phiên học** → QBank.

### App sidebar — Free vs Premium
- Free: nút **Nâng cấp Premium** (thay “Nâng cấp tài khoản”).
- Premium: thẻ trạng thái Premium (icon + Active + hạn/SKU), không hiện CTA nâng cấp.
### Tối ưu lưu trữ Audit và theo dõi hoạt động
- Bổ sung thiết bị, hệ điều hành và trình duyệt; bộ lọc người thực hiện hỗ trợ tên hoặc ID.
- Phân tầng Audit đồng bộ/queue, chống trùng bằng event ID và chỉ ghi ngay các thao tác nhạy cảm.
- Gom heartbeat 2 phút qua Redis thành phiên hoạt động; không ghi từng request vào Audit Log.
- Archive log cũ thành JSONL gzip có checksum trước khi dọn dữ liệu nóng khỏi MySQL.

## 2026-08-24

### Admin — Hỗ trợ chat trên header
- Chuyển mục Hỗ trợ chat từ sidebar sang icon trên header (cạnh chuông thông báo), tooltip “Hỗ trợ chat”.
- Đồng bộ kích thước và hover tròn giữa icon hỗ trợ và chuông thông báo (`size-10`).

### Admin — cấu hình cổng thanh toán
- Trang `/admin/billing/gateways`: bật/tắt, credential VNPay/Fake, lưu sẵn MoMo & ZaloPay (Phase 2).
- Settings DB override env; secret field giữ giá trị cũ khi để trống; checkout chỉ dùng cổng “sẵn sàng”.

### Billing Phase 1 — checkout & thanh toán online
- Thiết kế: `docs/billing-subscription-redesign.md` (prepaid-first, VNPay + Fake, webhook idempotent).
- Checkout session, payments, webhook events; kích hoạt Premium sau thanh toán; Redis entitlement cache.
- UI: `/subscription/upgrade`, confirmation, Fake pay (local); Paywall overlay; admin `/admin/billing/payments`.
- API: `POST /api/v1/subscription/checkout` + `Idempotency-Key`.

### Taxonomy y khoa & blueprint thay thế Topic cũ
- Thay hệ thống Topic phẳng bằng Medical Taxonomy, Blueprint đề thi (core clinical topics) và Tags.
- Admin: trang taxonomy/blueprint/tags; form câu hỏi gắn taxonomy đa chiều; bỏ CRUD Topic cũ.
- QBank, Study Plan, Search, Exam, Analytics đồng bộ filter/seed theo taxonomy mới; migration gỡ legacy topics.

### Danh sách & thống kê câu hỏi (Admin)
- List `/admin/questions`: bỏ cột Kiểm duyệt riêng; đổi nhãn **Truy cập** (Miễn phí/Premium); chọn cột hiển thị (localStorage); sticky nội dung/thao tác.
- Thêm `stats_cache` + trang `/admin/questions/{id}/stats` (lượt làm, % đúng, báo lỗi từ rollup).
- Bộ lọc trạng thái/truy cập/báo lỗi; tìm theo mã câu hỏi.

### Chuẩn hóa Audit toàn hệ thống
- Tách lõi Audit dùng chung khỏi giao diện Admin; ghi lại actor role, portal Admin/Teach/Student, nhóm nghiệp vụ, kết quả và session liên quan.
- Nối Audit vào thao tác của Admin, Content Creator, Giảng viên và Học viên trong Auth, Classroom/Live, Question Session, Study Plan, Bookmark và Billing.
- Chuẩn hóa snapshot before/after, metadata và cơ chế tự che password, token, secret, email cùng dữ liệu cá nhân nhạy cảm.
- Tinh gọn trang Audit với bộ lọc hành động, người thực hiện, vai trò và IP; giữ liên kết trực tiếp theo User/Question cùng timeline User hai chiều.
- Audit phiên làm câu hỏi của học viên chỉ ghi hai mốc bắt đầu và kết thúc; chi tiết từng đáp án tiếp tục lưu tại `question_attempts`.
- Bổ sung index truy vấn, cursor pagination và test cho context, snapshot, lọc đối tượng, bất biến dữ liệu và redaction.

## 2026-08-22

### Content Creator và kiểm duyệt câu hỏi
- Content Creator tạo/sửa/xóa câu hỏi qua luồng chờ admin duyệt; chỉ thấy câu hỏi do chính mình tạo.
- Admin/Super Admin xem toàn bộ yêu cầu, phê duyệt/từ chối và xem chi tiết nội dung, đáp án, giải thích, kiến thức/gợi ý, hình ảnh.
- Lịch sử phiên bản câu hỏi hiển thị đầy đủ nội dung và bỏ qua phiên bản chỉ phát sinh khi admin duyệt.

### Quản lý chủ đề và dữ liệu seed
- Thêm CRUD chủ đề trong admin, quyền riêng cho chủ đề và seeder 150 chủ đề tiếng Việt.
- Cập nhật form câu hỏi để chọn nhiều chủ đề và giữ lại đáp án đã nhập khi validation lỗi.
- Bổ sung tài khoản Content Creator seed và đổi nhãn role sang Content Creator.

### Cấu hình và trải nghiệm quản trị
- Đặt timezone mặc định `Asia/Ho_Chi_Minh` qua `APP_TIMEZONE`.
- Cải thiện trang quản lý kỳ thi khi hiển thị câu hỏi thuộc nhiều chủ đề.

### Lịch sử phiên bản câu hỏi
- Nhấp **Phiên bản** trong màn hình chỉnh sửa để xem đầy đủ lịch sử nội dung, chủ đề và đáp án.
- Cho phép khôi phục phiên bản cũ thành một phiên bản mới ở trạng thái Bản nháp; lịch sử cũ không bị ghi đè.

### Câu hỏi thuộc nhiều chủ đề
- Admin có thể tìm kiếm và chọn nhiều chủ đề khi tạo hoặc chỉnh sửa câu hỏi.
- Bộ lọc Q-Bank, Study Plan, tìm kiếm, thống kê và snapshot phiên nhận diện tất cả chủ đề đã liên kết.
- Dữ liệu `topic_id` cũ được backfill sang quan hệ nhiều-nhiều và tiếp tục giữ làm chủ đề chính để tương thích.

### Admin vào lớp và xem live giảng viên
- Trang `/admin/lớp`: mỗi lớp có nút **Vào lớp** để admin xem thông tin, thành viên và các buổi học.
- Lớp đang LIVE hiện tên buổi, tên giảng viên và nút **Xem live**.
- Admin vào phòng với tư cách giám sát (xem video/đề/chat, không điều khiển, không micro/camera).

## 2026-08-21

### Notification Center (Module 27)
- Trung tâm thông báo in-app: chuông realtime (Reverb), trang `/notifications`, API đọc/xóa, category + deep link.
- Nhắc live sắp bắt đầu, cảnh báo streak, broadcast hệ thống (admin), listener live/recording/support/duyệt lớp.
- Mở rộng preference (classroom, support, billing); migration category/action_url + log dedup; test kèm theo.

### Exam review & summary
- Sửa nút «Làm lại» khi thiếu `exam_id` (fallback filters, ẩn nút nếu không có URL).
- Đồng bộ token màu/prose dark mode trên màn xem lại kỳ thi và live review.

### Admin settings layout
- Chuyển toàn bộ luồng settings tài khoản sang layout admin để dùng đúng sidebar và header quản trị.
- Gỡ hai khối `Thông tin nghề nghiệp` và `Mục tiêu học tập` khỏi trang settings.

## 2026-08-21

### Support Chat (Module 45)
- Thêm kênh chat hỗ trợ user ↔ admin với AI tuyến đầu (FAQ) và handoff khi cần nhân viên.
- Migration + model hội thoại/tin nhắn; badge unread và hàng đợi admin.
- UI launcher người dùng và inbox/thread admin; realtime Reverb (presence + typing).
- SRS module `45-support-chat.md`; cấu hình OpenAI cho `SupportAiResponder`.
### Classroom Live + Teach Portal
- Hoàn thiện luồng live teach: vào Studio trước khi bắt đầu live, kết thúc live tự quay về trang lớp thay vì báo lỗi 409.
- Thêm quản lý học viên trong Studio: đếm người đang vào phòng, kick khỏi live hiện tại và không ban khỏi lớp.
- Tối ưu LiveKit cho học viên xem live mượt hơn: camera giảng viên 540p, screen share 720p/15fps, ưu tiên track video chính.
- Sửa render đề/ghi chú rich text để không lộ thẻ HTML và đồng bộ hiển thị ở user/studio/màn phụ.
- Làm lại bộ lọc admin classroom cho đều hơn, có border rõ và spacing thống nhất.

## 2026-08-20

### Cài đặt Hệ thống & Chế độ Bảo trì (System Settings & Maintenance)
- Thêm bảng `settings`, model `Setting` và helper `setting()` phục vụ cấu hình hệ thống toàn cục.
- Trang Admin Cài đặt (`/admin/settings`): quản lý tên hệ thống, thông tin liên hệ, bật/tắt đăng ký học viên, chế độ bảo trì và thông báo hệ thống.
- Bổ sung middleware `EnsureSystemIsAvailable` chặn truy cập khi bật bảo trì và hiển thị trang thông báo bảo trì.
- Cập nhật Header, Footer đọc động cấu hình site name, hotline, support email và ẩn/hiện nút đăng ký theo settings.
- Bổ sung endpoint tìm kiếm câu hỏi AJAX `/admin/exams/questions/search` khi tạo và chỉnh sửa kỳ thi.
- Hoàn thiện giao diện tab Cài đặt tài khoản `/profile`.

## 2026-08-18

### Exam Module & Flow Improvements
- Lưu lại script `test_exam.php` dùng để test nhanh luồng thi thử trên terminal.
- Thêm tính năng CRUD quản lý kỳ thi (Exam) ở Admin.
- Tích hợp kết quả kỳ thi vào Global Search.
- Điều chỉnh phân luồng Session Mode giữa Exam và Study, đảm bảo URL và logic độc lập.
- Cập nhật thẻ trạng thái kỳ thi ở giao diện: hiển thị "Chưa xong", "Đã xong" và các nút Tiếp tục/Làm lại tương ứng.
- Bổ sung phân trang (pagination) cho danh sách kỳ thi.
- Thêm tính năng xem phóng to ảnh (Lightbox) khi click vào ảnh tĩnh ở màn hình làm bài.
## 2026-08-16

### Media library (local disk) + CMS image slot
- Module 37: bảng `media` / `media_usages` / `media_jobs`; ảnh + video lưu **local** (`public` disk), không R2/CDN.
- Admin `/admin/media`: lưới thư viện, chi tiết (metadata, biến thể, nơi dùng), xóa chặn khi đang gắn.
- Picker + Image slot trên form CMS (landing/trang tĩnh/OG/Twitter): tải lên, kéo-thả, chọn lại từ thư viện; lưu `image_media_id`.
- Upload ảnh sinh thumb/webp (GD); video lưu file gốc. Quyền `media.view` / `media.manage` (content editor có cả hai).
- Test `AdminMediaLibraryTest`.
- Picker tab **URL / CDN**: dán ảnh ngoài (tham chiếu URL, không lưu file) hoặc **tải về máy chủ**; chặn SSRF khi import. Badge CDN trên lưới.

## 2026-08-15

### Public — Cookie consent (localStorage + cookie)
- Banner cookie: Chấp nhận / Từ chối ghi `cookie_consent` vào **localStorage** và cookie 1 năm (`SameSite=Lax`).
- Đã chọn → không hiện lại; footer **Cookie Settings** (`#cookie-settings`) mở lại banner để đổi lựa chọn.
- API `window.CookieConsent` (`get` / `set` / `allowsAnalytics`) cho analytics sau này; link Chính sách bảo mật trên banner.
- Fix persist: banner vanilla JS + `style.display` (Tailwind `flex` ghi đè HTML `[hidden]`); xóa hash `#cookie-settings` sau khi chọn.

### CMS Phần 7 — Menu (header / footer)
- Bảng `menus` (key + items JSON); catalog cố định `header` / `footer`.
- Admin `/admin/cms/menus`: sửa label, loại route|url (allowlist), bật/tắt, sắp xếp ↑↓, thêm/xóa.
- Public header + footer đọc `ResolvedMenu` (cache plain array); Đăng nhập/Đăng ký giữ cứng.
- Seeder `MenuSeeder`; audit `cms.menu.update`; test `AdminCmsMenuTest`.

### CMS Phần 5 — Landing blocks (home, features)
- Catalog CMS thêm `home` / `features`; tab **Landing** quản lý riêng (copy/ảnh theo section, layout Blade cố định).
- Public `/` và `/features` luôn mở: đã publish → nội dung CMS + SEO; nháp → defaults (không 404).
- Form admin `home` / `features`; seeder xuất bản sẵn; sitemap không trùng URL landing.
- Test mở rộng `AdminCmsPageTest`, `CmsPageSeederTest`.

### CMS Phần 3 — Banner + hiển thị landing/dashboard
- Bảng `banners` (nội dung, CTA, variant, placement, audience, lịch, bật/tắt, dismissible).
- Admin `/admin/cms/banners`: CRUD, lọc, toggle nhanh; audit `cms.banner.*`.
- Component `<x-cms.announcement-banners>` trên landing home + dashboard học viên (target Free/Premium/guest…).
- Seeder 2 banner mẫu; test `AdminCmsBannerTest`.

### Tải ảnh câu hỏi & Giao diện hiển thị ảnh
- Sửa lỗi xử lý đường dẫn ảnh (`SaveAdminQuestionAction`): loại bỏ chính xác tiền tố `/storage/` giúp lưu trữ đúng relative path.
- Khắc phục lỗi 403 Forbidden hiển thị ảnh trên Docker do symlink tuyệt đối.
- Bổ sung tính năng Kéo thả (Drag & Drop) và Dán ảnh từ clipboard (Ctrl+V / Cmd+V) vào ô tải ảnh câu hỏi trong trang Admin (`rich-editor.js`, `questions/form.blade.php`).
- Cập nhật khung hiển thị ảnh câu hỏi tại trang tạo câu hỏi Admin, chế độ Study mode và Exam mode tự động co giãn theo tỷ lệ thực của ảnh (`form.blade.php`, `session.blade.php`, `exam-session.blade.php`).

## 2026-08-14

### Fix — 404 / landing public luôn sáng
- `theme-init` hỗ trợ `force="light"`; layout public + `errors/404` khóa light (không theo OS/admin dark).

### CMS — Publish/unpublish + 404
- Form admin chỉnh layout (section phẳng, sticky actions, URL rõ khi xuất bản).
- Xuất bản / Lưu thay đổi / Ngừng xuất bản; ngừng xuất bản → URL public trả 404; sitemap chỉ liệt kê trang đã publish.
- Trang `errors/404` theo layout public (header/footer, CTA, lối tắt SaaS).

### CMS / SEO — On-page (Yoast-style) + sitemap
- Admin CMS: focus keyphrase, SEO title/description, keywords, canonical, robots index/follow, OG, Twitter Card, schema type.
- Public layout: meta robots/keywords, canonical, Open Graph, Twitter Card, JSON-LD (Organization + WebSite + WebPage/AboutPage/ContactPage).
- `/sitemap.xml` (tự cập nhật CMS + landing), `/robots.txt` động kèm Sitemap URL; footer link Sitemap.

### CMS Phần 3 — Nội dung có cấu trúc (JSON theo section)
- Migration `2026_08_15_140000` thêm `content` / drop `body` cho DB đã tạo trước (tránh lỗi MissingAttribute `content`).
- Cột `content` JSON thay `body`; admin nhập text/URL ảnh theo từng phần, không sửa HTML.
- Blade public giữ layout cố định; `CmsPageDefaults` + `CmsPageContentResolver` merge mặc định với DB.
- Form admin theo trang: about (hero/story/values/stats/experts/partners/cta), contact, terms/privacy (sections).
- Cache CMS lưu page ID (fix `__PHP_Incomplete_Class`); `ResolvedCmsPage::forget()` khi lưu.

### CMS Phần 2 — Trang tĩnh (admin + public terms/privacy)
- Bảng `faqs`, CRUD admin `/admin/cms/faq` (sub-nav CMS, KPI, lọc, sắp xếp lên/xuống).
- Rich editor + sanitize HTML; trạng thái nháp/xuất bản; audit `cms.faq.*`.
- Public `/faq` đọc FAQ đã xuất bản theo danh mục; tìm kiếm client-side; empty state.
- Seeder 4 FAQ mẫu; role `content_editor` được `cms.manage`; menu CMS trỏ `/admin/cms/faq`.
- Test `AdminCmsFaqTest`.

### Catalog /classes — card và duyệt lớp giảng viên
- Card lớp: cover fallback theo `purpose`, avatar host, badge live/VOD/lịch, filter (live/sắp tới/recording).
- Tắt học viên tạo lớp (`/classes/create`); chỉ giảng viên tạo trên `/teach` → `pending_approval`.
- Admin `/admin/classrooms`: duyệt/từ chối lớp; catalog học viên chỉ hiện lớp `active`; chặn join/start live khi chưa duyệt.
- SRS Module 44 §17, ERD `pending_approval`, RBAC; test `ClassroomCatalogTest` và cập nhật flow/admin.

### Portal /teach Phase B — lớp và hồ sơ giảng viên
- `/teach/classes`: danh sách, tạo và chi tiết stub lớp chữa đề (feedback QBank / exam); enum `ClassroomPurpose`/`MemberRole` bổ sung helper.
- `/teach/profile`: hub hồ sơ giảng viên (thông tin, liên hệ, bảo mật, giao diện); cập nhật avatar.
- Layout `/teach`: nav «Lớp của tôi», menu tài khoản (theme Sáng/Tối/Hệ thống, đăng xuất); dashboard «Bắt đầu nhanh».
- Test `TeachClassroomTest`, `TeachProfileTest`.
### Bộ sưu tập câu hỏi đã lưu (Bookmark Folders)
- Tạo bảng `bookmark_folders` và `bookmark_folder_items`, hỗ trợ quản lý câu hỏi lưu theo từng thư mục/bộ sưu tập.
- Thêm action tạo, xóa bộ sưu tập (`CreateBookmarkFolderAction`, `DeleteBookmarkFolderAction`), toggle câu hỏi vào bộ sưu tập (`ToggleBookmarkFolderItemAction`).
- Thiết kế lại trang `/qbank/bookmarks` dạng lưới bộ sưu tập, phân trang 3 bộ sưu tập/trang, hỗ trợ xóa bộ sưu tập có xác nhận.
- Cập nhật popup lưu câu hỏi trên trang luyện tập (Bookmark modal) chia thành 2 phần: "Trong thư mục" và "Chưa có trong thư mục".
- Cập nhật trang tạo phiên tùy chỉnh `/qbank/create`: tích hợp popup chọn bộ sưu tập khi lọc "Câu hỏi đã lưu", tự động khóa các bộ lọc khác khi chọn bộ sưu tập và đồng bộ đếm câu hỏi chính xác ngắt race-condition Alpine/FormData.

## 2026-08-13

### Giao diện sáng/tối & menu tài khoản
- Dark mode token `@layer theme`; toggle Sáng/Tối/Hệ thống; đồng bộ mọi layout qua `theme-init`.
- Lưu `users.theme` + `PUT /settings/appearance`; áp dụng ngay, reload giữ preference.
- Menu header gọn: chip gói membership, bỏ ngôn ngữ/đơn vị giả; link nâng cấp khi Free.

### Billing — CMS bảng giá & thống kê học viên
- Migration `plan_prices`, SKU Premium (1 tháng / 1–3 năm); seed Free + Premium; auto `compare_at` từ `savings_percent`.
- Admin `/admin/billing/plans`: CRUD tier/SKU, menu **Bảng giá** (`billing.manage`); KPI học viên Free/Premium; phân bổ theo SKU.
- `/pricing` đọc DB; badge gói hiện tại; `/subscription` + tab membership; API `GET /api/v1/plans`, `/api/v1/subscription`.
- Thống kê chỉ role Học viên; Free = mặc định (không subscription); lịch sử Premium drill-down theo SKU/nguồn.

### Dev — Vite CORS localhost
- `vite.config.js`: cho phép Origin `localhost` có/không cổng `:80`; truyền `APP_URL` vào service vite.
### Q-Bank tạo phiên
- Số câu làm mặc định bằng tổng câu phù hợp (ví dụ 69/69); có thể giảm, không vượt quá tổng đó. Không kẹp theo hạn 20/100 của gói.

### Bookmark câu hỏi
- Thêm bookmark thật theo user cho câu hỏi; icon lưu đặt cạnh “Kiến thức” và dùng chung trong phiên Q-Bank/Study Plan.
- Bộ lọc “Chỉ câu đã lưu” khi tạo Q-Bank hoặc Study Plan đọc từ bảng `bookmarks`; gắn cờ tiếp tục chỉ thuộc phiên.
- Chuyển dữ liệu `question_status=marked` cũ sang bookmark và khôi phục trạng thái làm bài gần nhất.
- Trang `/qbank/bookmarks`: xem danh sách câu đã lưu, bấm câu để xem đề và đáp án, bỏ lưu, tạo phiên từ câu đã chọn.

### 2FA tùy chọn cho học viên
- Settings tab Bảo mật: bật TOTP (QR + mã khôi phục), tắt bằng xác nhận mật khẩu; staff không dùng luồng này.
- Login `/login`: nếu đã bật 2FA thì hỏi mã tại `/2fa/challenge`; cookie nhớ thiết bị 30 ngày (tắt/bật lại 2FA thì hết hiệu lực).
- Không challenge portal giảng viên `/teach`.

### Hub tài khoản thống nhất tại `/profile`
- Gộp Settings vào `/profile?tab=...`; `/settings` chuyển hướng 301; xóa trang settings riêng.
- Layout SaaS: component `account-layout`, sidebar nhóm Hồ sơ / Tài khoản / Thanh toán / Khác.
- Panel hồ sơ nghề nghiệp và cài đặt tách partial; sửa lỗi Blade khi tách layout.
- Sidebar app gộp mục «Tài khoản»; cập nhật redirect form và test Auth profile.

## 2026-08-11

### Profile, Settings và Billing cơ bản
- Thêm trang hồ sơ nghề nghiệp/mục tiêu học và Settings Amboss (liên hệ, bảo mật, thông báo, gói, đổi mã, giấy phép tổ chức, hóa đơn, ghi chú).
- Upload/xóa avatar; quên mật khẩu (email reset); tab Settings đồng bộ URL `?tab=`.
- Module Billing: plans/subscriptions/redeem/institution/invoices; nối `User::entitlements()`; seed mã `MEDLEARN2026` và domain `@medlearn.local`.
- Thông báo in-app khi hoàn thành phiên Q-Bank (chuông header); email nhắc Study Plan theo pref `email_plan` (cron 8:00).
- Mock HTML `profile-user` / `setting-user`; kiểm thử Auth/Billing/Notification.

### Study Plan — sửa nhãn hỗ trợ phiên
- Đổi nhãn tab “Kiến thức” ↔ “Gợi ý” cho đúng nghĩa trên session Study Plan.

## 2026-08-09

### Phase A — Instructor portal + Classroom oversight
- Role `instructor` + permissions Classroom/Live/`instructor.assign`; seed user `instructor@medlearn.local`.
- Portal `/teach` (login/logout/dashboard shell), middleware `instructor`; tách 3 cổng với learner & admin.
- Admin `/admin/classrooms`: giám sát mọi lớp, filter, force-end live, archive + audit.
- Cột `classrooms.purpose` (`community_review` / `feedback_review` / `exam_review`).
- Sau seed permission: reset Spatie cache (`permission:cache-reset`) — tránh menu admin chỉ còn Dashboard khi cache cũ.
- Chưa: CRUD lớp trên `/teach`, review queue, chữa exam (Phase B/C).

### SRS — chốt Instructor portal & Classroom oversight
- Ba portal: Learner `/login`, Instructor `/teach`, Admin `/admin` (cùng `web` session).
- Super Admin/Admin: **oversight** lớp only (`classroom.oversee`, force-end/archive) — không vận hành chữa đề.
- Instructor: role + workspace `/teach` (feedback QBank / exam); host không phụ thuộc Premium.
- Premium vẫn host lớp cộng đồng trên `/classes`. Roadmap Phase A→D ghi ở Module 44 §16.
- Cập nhật: `03-phan-quyen-rbac`, `01-tong-quan`, `02-auth`, `04-data`, `08-glossary`, `33-admin`, `44-classroom`.

### Phase 2a — Question Management (MVP)
- Admin `/admin/questions`: list/filter, tạo/sửa stem+options+topic+difficulty, workflow `draft → in_review → published → retired`.
- Thêm status `in_review`; Content Editor gửi duyệt; `question.publish` để xuất bản/retire; ghi audit.
- Chưa: media module đầy đủ, import, report queue, version history UI.
- Sửa `audit_logs.auditable_id` → string (UUID câu hỏi không còn bị truncate trên MySQL).
- Rich editor (Quill) cho câu hỏi / giải thích / gợi ý: format text + chèn ảnh; sanitize HTML; hiển thị an toàn phía học viên.

### SRS — hoãn CSKH nâng cao, ưu tiên QBank
- Ghi chú hoãn impersonate, subscription override, bulk users, export CSV (Users/Audit) tại module 34 §16, 40 §16, 33, 35 §16.

### Phase 1 — User Management, Roles, Audit UI
- Users: `/admin/users` list/filter + chi tiết; đổi role/status, verify email, gửi reset password; cột `users.status`; chặn login khi suspended/banned.
- Roles: `/admin/roles`, ma trận permission (chỉ Super Admin lưu), `/admin/permissions` catalog.
- Audit: `/admin/audit` filter + chi tiết before/after; mọi mutate user/role ghi `audit_logs`.
- Password reset guest tối thiểu (`/reset-password/{token}`) để link admin gửi được.
- Chưa làm: impersonate, subscription override, bulk, export CSV.

### Phase 0 Admin shell + 2FA TOTP bắt buộc
- Bảng `two_factor_secrets` / `audit_logs`; TOTP (Google2FA + QR); `/admin/2fa/setup|challenge|recovery`.
- Middleware `staff.2fa`: staff phải enroll + xác thực mỗi phiên trước khi vào `/admin`.
- `AdminMenu` lọc theo permission; layout + KPI placeholder; `Auditor` ghi `admin.login` / `admin.2fa.enabled`.
- Component `admin.page-header`, `admin.kpi-card`; cập nhật test portal.
- Sửa lưu `recovery_codes`: dùng cast `array` (hash bcrypt) thay `encrypted:array` — tránh lỗi MySQL JSON invalid.

### Tách portal học viên / admin sau login
- Middleware `learner`: staff không vào được dashboard/QBank/StudyPlan/Classroom/Flashcards — redirect về `/admin`.
- Layout admin riêng (`layouts.admin`) + trang tổng quan; redirect sau login chỉ giữ intended cùng portal.
- Cập nhật test tách portal.

### Tách cổng đăng nhập học viên / admin (mức 2)
- Thêm `/admin/login` và `/admin/logout`; guest vào `/admin/*` được đưa tới cổng admin (không dùng `/login` học viên).
- Cùng guard/session `web`: staff bị từ chối ở `/login`, học viên bị từ chối ở `/admin/login` (lỗi chung); admin không OAuth / remember me.
- Cập nhật SRS Auth (02) và Admin Dashboard (33); thêm feature test tách portal.

## 2026-08-07

### Sửa lỗi frontend phòng live sau rebase
- Gỡ conflict marker còn sót trong `app.js` / `changelog.md` (lỗi `Unexpected token '<<'`).
- Chỉ gọi `Livewire.start()` một lần để tránh `Cannot redefine property: $persist`.
- Bổ sung `@livewireStyles` / `@livewireScriptConfig` cho layout live.

### Module Classroom — Live chữa đề (LiveKit)
- Thêm module Classroom: tạo/tham gia lớp, lịch buổi live, phòng full-bleed với LiveKit (cam/mic/share).
- Chat realtime (Reverb), lọc “Chỉ hỏi”, tắt/bật chat, giơ tay có hàng đợi + âm thanh, reaction tim/like bay kiểu Meet.
- Panel đề đồng bộ host/viewer, cửa sổ màn phụ presenter; tách “Chữa đề” khỏi screen-share để tránh loop.
- Egress/VOD (HLS) tùy chọn, webhook LiveKit, entitlement `classroom.host`, Docker LiveKit + docs.

### An toàn test và QBank
- Ép `APP_ENV=testing` + SQLite in-memory trong `TestCase` để `php artisan test` không `migrate:fresh` MySQL thật.
- Phiên QBank không còn câu hỏi redirect về index thay vì summary (tránh vòng redirect vô hạn).
## 2026-08-08

### Hoàn thiện công cụ học tập và phân tích phiên
- Thêm Kiến thức, Gợi ý và Nghiên cứu dùng chung cho Question Bank và Study Plan; bổ sung bảng tham chiếu Lab theo bốn nhóm xét nghiệm.
- Tự mở kiến thức/gợi ý sau khi trả lời, lưu lịch sử dùng trợ giúp và chặn Back/Forward bằng popup xác nhận thoát như nút X.
- Bổ sung tổng quan từng câu với thời gian, tỷ lệ đồng nghiệp, mức độ khó và phân trang 5 câu; giữ biểu đồ chủ đề responsive.
- Mở rộng snapshot, dữ liệu demo Goodpasture, migration và kiểm thử cho các luồng học tập, thi và tổng kết.

## 2026-08-06

### Ổn định phiên thi và môi trường phát triển
- Tự tính thời gian thi theo 90 giây mỗi câu và giới hạn số câu theo đúng tập câu khớp bộ lọc.
- Cảnh báo thời gian tại các mốc 5, 4, 3 phút, 30 và 15 giây; đổi nút ba chấm thành biểu tượng ghi chú.
- Sửa biểu thức Alpine ở phiên Study Plan; chỉ khởi tạo Reverb khi bật cấu hình và xử lý kết nối khi dùng BFCache.

### check.php — JSON output và kiểm tra chặt hơn
- Hỗ trợ `?format=json` / `--json`; load `.env` thống nhất cho CLI và web.
- Bổ sung kiểm tra PHP 8.4 khuyến nghị, mask secret, security token; cập nhật mục `deploy-production.md`.

### Deploy production — aaPanel, seed và troubleshooting
- Bổ sung mục lục và hướng dẫn deploy qua aaPanel / Git webhook (`scripts/deploy.sh`).
- Thêm seeding lần đầu trên production và bảng troubleshooting thường gặp.

### SRS Module 44 — Classroom / Live Review
- Thêm đặc tả lớp chữa đề livestream LiveKit (B2C), phân biệt Organization (32) B2B Phase 2.
- Cập nhật nền tảng: tổng quan, kiến trúc, RBAC, mô hình dữ liệu, tracking, glossary, trạng thái.
- Liên kết Videos (14), Notification (27); ghi chú phân biệt trong Organization (32).
- Bổ sung quan hệ User ↔ Classroom trên mô hình dữ liệu; sửa tham chiếu mục 8.
- Làm rõ Instructor/`classroom.host` trong RBAC; WebRTC trên sơ đồ kiến trúc; đồng bộ tên cột module 44.

### Header public — breakpoint menu về md
- Nav và nút đăng nhập hiện từ `md`; drawer mobile dưới `md` (trước dùng `lg`).
- Đơn giản hóa nút menu Material Symbols; bỏ `@resize.window` đóng drawer.

### Hoàn thiện Question Bank và đồng bộ phiên học
- Làm thật bộ lọc kỳ thi, bài viết, triệu chứng, chủ đề, trạng thái và năm mức độ khó; bổ sung dữ liệu seed 200 câu hỏi.
- Đồng bộ giao diện tạo phiên, học tập, thi, tổng kết và xem lại với Study Plan; thêm máy tính thi, tạm dừng đúng đồng hồ và thao tác lịch sử phiên.
- Thêm chấm điểm tập trung, thống kê chủ đề responsive, làm lại theo nhóm kết quả và snapshot bất biến cho nội dung câu hỏi từng phiên.
- Đồng bộ tiến độ Topic Mastery giữa Question Bank, Study Plan và Dashboard; tăng kiểm thử cho toàn bộ luồng.

## 2026-08-05

### Seed demo Question Bank từ VM14K
- `TopicTaxonomySeeder` và `DemoLearningSeeder` đọc taxonomy/câu hỏi từ dataset VM14K (JSONL).
- Thêm `Modules/QuestionBank/database/seeders/data/vm14k/` (3 file JSONL + README); giới hạn seed qua `QUESTIONBANK_VM14K_LIMIT`.

### Vite HMR trong Docker + font Be Vietnam Pro
- Cấu hình Vite `host`/`port`/`hmr`/`cors` theo `VITE_PORT` và `APP_PORT`; truyền `APP_PORT` vào service vite trong Compose.
- Nạp Be Vietnam Pro qua Google Fonts (subset tiếng Việt) và set font-family trên `html`/`body`.

### Tài liệu và script deploy
- Thêm `deploy-dev.md` hướng dẫn dựng stack Docker local (env, migrate, seed, Vite).
- Thêm `scripts/deploy.sh` cho webhook aaPanel (pull, build assets, migrate, restart services).

## 2026-08-02

### Bảng giá theo năm (1 / 2 / 3 năm)
- Thay gói 6 tháng bằng gói Premium theo năm (1–3 năm) có tab chọn thời hạn, giá động Alpine trên trang chủ và `/pricing`.
- Cập nhật quyền lợi, FAQ và copy CTA cho đúng mô hình năm / tháng.

### Menu mobile header public
- Thêm drawer menu Alpine cho viewport `< lg`; nav và nút đăng nhập/đăng ký hiện từ `lg` trở lên.

## 2026-08-04

### Study Plan MVP + phiên học Amboss-style
- Hoàn thiện tạo/xem kế hoạch, lịch, nhiệm vụ ngày; tự đánh dấu bỏ qua khi quá hạn.
- Phiên làm bài: đồng hồ từng câu, highlight đỏ/vàng/xanh, ghi chú/gắn cờ, bản đồ câu hỏi, phân tích + xem lại (lọc cần ôn).
- Topic mastery trên dashboard; seed taxonomy + ~500 câu Amboss; bỏ độ bám lịch và banner login dev.

## 2026-07-31

### Bỏ MinIO — dùng local storage
- Gỡ service `minio` / `minio-init` khỏi `docker-compose`; `FILESYSTEM_DISK=local`.
- Cập nhật README, `.env.example`, `deploy-production.md`, `check.php` cho self-host local disk.

### Nạp Material Symbols qua Vite fonts
- Thay link CDN Google Fonts bằng `@fonts` trong layout `app`, `auth`, `public`.
- Cấu hình `google('Material Symbols Outlined')` trong `vite.config.js` (weights, display block, không preload).
### Port UI học viên (Q-Bank, Study Plan, Flashcards)
- Wire shell Q-Bank từ mockup: danh sách phiên, tạo phiên, study/exam session, tổng kết, xem lại câu hỏi; điều hướng theo chế độ học/thi.
- Wire Study Plan: danh sách, lịch, tạo kế hoạch, chi tiết lộ trình (accordion tuần), phiên học riêng (`/study-plan/session`) thoát về detail.
- Wire Flashcards: dashboard, tạo thẻ, chi tiết bộ thẻ, ôn thẻ; gắn điều hướng giữa các màn.
- Bổ sung CSS (donut, flip card, exam UI…); bỏ icon mở menu trên landing và layout dashboard.

### Thêm Adminer cho Docker local
- Thêm service Adminer (cổng `FORWARD_ADMINER_PORT`, mặc định 8081) vào `docker-compose`.
- Ghi chú cổng trong `.env.example` và README.

## 2026-07-30

### Hoàn thiện Auth + Dashboard
- Wire đăng nhập/đăng ký thật: FormRequest, DTO, Action, controller session; redirect guest/user theo `HomePath`.
- Tách component Blade auth dùng chung (shell, input, password, errors, submit, social).
- Thêm dashboard (Analytics) và layout app có sidebar/header/mobile drawer sau khi đăng nhập.
- Tách `UserSeeder`, policy mật khẩu mặc định, validation tiếng Việt; tắt Scout trong phpunit.

### Dựng UI thật từ mockup (Landing + Auth)
- Thêm module `Landing` (controller, provider, routes) phục vụ trang chủ, tính năng, bảng giá, giới thiệu, liên hệ, FAQ bằng Blade responsive; bật module trong `modules_statuses.json`.
- Thêm layout dùng chung `layouts/public`, `layouts/auth` và component `public/header`, `footer`, `cookie-banner`.
- Thêm view đăng nhập/đăng ký cho module `Auth` và route `guest` (mới chỉ UI).
- Chuyển `/` sang module Landing, `/billing/plans` redirect sang `/pricing`.
- Thêm design tokens (màu, typography, spacing) và component CSS; đổi font sang `Be Vietnam Pro` (Vite + Tailwind), nạp Material Symbols.

### Mockup HTML landing/marketing (PC + Mobile)
- Thêm trang giới thiệu (about-us), liên hệ (contact), tính năng (feature), bảng giá (pricing), câu hỏi thường gặp (questions) cho cả bản PC và mobile.
- Thêm màn đăng nhập/đăng ký (login, register) cho PC và mobile.
- Thêm trang chủ (home) bản PC.

### Mockup HTML PC (Stitch)
- Thêm 20 trang HTML desktop prototype MedQuest Pro: dashboard, ngân hàng câu hỏi, review câu hỏi, phiên tùy chỉnh.
- Bổ sung màn thi (exam session, pause map), flashcards (dashboard, tạo thẻ, chi tiết bộ, ôn thẻ).
- Bổ sung lộ trình học (danh sách, tạo, chi tiết, lịch) và phiên học (session, highlight, navigator, ghi chú, thêm flashcard) cùng trang thống kê.

## 2026-07-29

### Lát cắt học tập QuestionBank
- Thêm model học tập: `Topic`, `QuestionOption`, `QuestionSession`, `QuestionAttempt`, `QuestionStatus` và quan hệ `topic`/`options` cho `Question`.
- Thêm enum `SessionMode`, `SessionStatus`, `UserQuestionStatus`.
- Thêm migration cho topics, options, sessions, attempts, status và khóa ngoại `topic_id` cho questions.
- Bổ sung factory (kèm `QuestionFactory::withOptions`) và seeder `DemoLearningSeeder` (dữ liệu demo cố định) + `VolumeLearningSeeder` (tùy chọn qua `SEED_VOLUME`).
- Mở rộng `DatabaseSeeder`: tài khoản dev cố định (mật khẩu `password`) và gọi seeder học tập; thêm biến seeding vào `.env.example`.

### Khởi tạo dự án
- Dựng nền tảng Laravel theo kiến trúc monolith modular (`nwidart/laravel-modules`) với shared kernel: Action, DTO, Repository, ApiResponse, ApiQuery, Enums (Role/Permission/Entitlement).
- Tích hợp stack: MySQL 8, Redis, Meilisearch (Scout), Reverb (WebSocket), Horizon, MinIO, Mailpit; Sanctum + spatie/laravel-permission (RBAC).
- Frontend: Vite + Tailwind + Alpine + Livewire; health endpoints, API versioning `/api/v1`, rate limiting, request tracing.
- Module mẫu QuestionBank (model/enum/migration/DTO/repository/action/resource/policy) làm lát cắt tham chiếu.
- Docker hoá đầy đủ: Dockerfile PHP 8.4-FPM, Nginx, `docker-compose` cho toàn bộ dịch vụ.

### Sửa lỗi Docker & API
- Fix MySQL 8.4 exit(1): bỏ option `--default-authentication-plugin` (đã bị gỡ ở 8.4) và reset volume init dở.
- Tách port nội bộ vs host bằng cơ chế `FORWARD_*` để né xung đột cổng máy host (Redis 6380, Reverb 8082, web `APP_PORT=8100`).
- Fix Meilisearch `unhealthy`: healthcheck dùng `127.0.0.1` thay `localhost` (IPv4/IPv6).
- Fix API trả 500 thay vì 401 khi chưa auth: thêm middleware `ForceJsonResponse` cho nhóm `api` + `shouldRenderJsonWhen`, tránh redirect tới route `login` không tồn tại.
