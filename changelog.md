# Changelog

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
