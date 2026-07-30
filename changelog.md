# Changelog

## 2026-07-30

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
