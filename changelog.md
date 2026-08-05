# Changelog

## 2026-08-05

### Seed demo Question Bank từ VM14K
- `TopicTaxonomySeeder` và `DemoLearningSeeder` đọc taxonomy/câu hỏi từ dataset VM14K (JSONL).
- Thêm `Modules/QuestionBank/database/seeders/data/vm14k/` (3 file JSONL + README); giới hạn seed qua `QUESTIONBANK_VM14K_LIMIT`.

### Vite HMR trong Docker + font Be Vietnam Pro
- Cấu hình Vite `host`/`port`/`hmr`/`cors` theo `VITE_PORT` và `APP_PORT`; truyền `APP_PORT` vào service vite trong Compose.
- Nạp Be Vietnam Pro qua Google Fonts (subset tiếng Việt) và set font-family trên `html`/`body`.

## 2026-08-04

### Study Plan MVP + phiên học Amboss-style
- Hoàn thiện tạo/xem kế hoạch, lịch, nhiệm vụ ngày; tự đánh dấu bỏ qua khi quá hạn.
- Phiên làm bài: đồng hồ từng câu, highlight đỏ/vàng/xanh, ghi chú/gắn cờ, bản đồ câu hỏi, phân tích + xem lại (lọc cần ôn).
- Topic mastery trên dashboard; seed taxonomy + ~500 câu Amboss; bỏ độ bám lịch và banner login dev.

## 2026-08-02

### Bảng giá theo năm (1 / 2 / 3 năm)
- Thay gói 6 tháng bằng gói Premium theo năm (1–3 năm) có tab chọn thời hạn, giá động Alpine trên trang chủ và `/pricing`.
- Cập nhật quyền lợi, FAQ và copy CTA cho đúng mô hình năm / tháng.

### Menu mobile header public
- Thêm drawer menu Alpine cho viewport `< lg`; nav và nút đăng nhập/đăng ký hiện từ `lg` trở lên.

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
