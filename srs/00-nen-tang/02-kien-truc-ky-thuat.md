# 02 — Kiến trúc kỹ thuật

## 1. Tổng quan kiến trúc

Kiến trúc **monolith modular** trên Laravel 12, tách rõ tầng, sẵn sàng scale ngang. Ưu tiên server-rendered (Blade) + tương tác cục bộ (Alpine/Livewire) thay vì SPA nặng.

```
┌──────────────────────────────────────────────────────────────┐
│                       Client (Browser)                         │
│  Blade views · TailwindCSS · AlpineJS · Livewire · Chart.js    │
│  Service Worker (offline session cache)                        │
└───────────────┬──────────────────────────┬───────────────────┘
                │ HTTP (web + api)           │ WebSocket (Reverb)
┌───────────────▼──────────────────────────▼───────────────────┐
│                     Laravel 12 Application                      │
│  ┌───────────┐  ┌────────────┐  ┌───────────┐  ┌───────────┐  │
│  │  Routing  │→ │ Middleware │→ │Controllers│→ │  Actions  │  │
│  │ web/api   │  │ auth/rbac  │  │ /Livewire │  │ (use case)│  │
│  └───────────┘  └────────────┘  └───────────┘  └─────┬─────┘  │
│  ┌───────────────────────────────────────────────────▼─────┐ │
│  │ Domain Services · Policies · Events · Jobs · Notifications │ │
│  └───────────────────────────────────────────────────┬─────┘ │
│  ┌────────────┐  ┌────────────┐  ┌──────────┐  ┌──────▼─────┐ │
│  │ Eloquent   │  │ Repositories│ │ Cache    │  │ Search      │ │
│  │ Models     │  │ (khi cần)  │  │ (Redis)  │  │ (Meili)     │ │
│  └─────┬──────┘  └────────────┘  └──────────┘  └────────────┘ │
└────────┼───────────────────────────────────────────────────── ┘
         │
┌────────▼────────┐ ┌─────────┐ ┌──────────┐ ┌────────┐ ┌───────────┐
│    MySQL 8      │ │  Redis  │ │Meilisearch│ │ Reverb │ │ S3 / R2   │
│ (primary+read  │ │ cache / │ │  index    │ │  WS    │ │ + CDN     │
│  replica)      │ │ queue / │ │           │ │        │ │           │
│                │ │ session │ │           │ │        │ │           │
└────────────────┘ └─────────┘ └───────────┘ └────────┘ └───────────┘
         │
┌────────▼────────────────────────────────────────────┐
│  Async workers: Queue (default/high/low), Scheduler   │
│  Jobs: analytics, email, media transcode, AI, export  │
└───────────────────────────────────────────────────────┘
```

## 2. Tầng ứng dụng (Application layers)

| Tầng | Trách nhiệm | Ví dụ |
|------|-------------|-------|
| **Routing** | Định tuyến `web.php`, `api.php`, `admin.php` | `/qbank`, `/api/v1/sessions` |
| **Middleware** | Auth, RBAC, rate limit, subscription check, locale | `auth`, `can:`, `throttle`, `subscription.active` |
| **Controller / Livewire** | Điều phối request → action, render view | `SessionController`, `QuestionPlayer` (Livewire) |
| **Action / UseCase** | 1 nghiệp vụ đơn lẻ, có thể test độc lập | `StartSessionAction`, `SubmitAnswerAction` |
| **Domain Service** | Logic phức tạp, tái sử dụng | `MasteryCalculator`, `SpacedRepetitionScheduler` |
| **Policy** | Ủy quyền chi tiết theo model | `QuestionPolicy@view` |
| **Event / Listener** | Tách side-effect | `AnswerSubmitted` → cập nhật analytics |
| **Job** | Xử lý nền | `TranscodeVideoJob`, `RecalculateWeakTopicsJob` |
| **Model / Repository** | Truy cập dữ liệu | Eloquent + query scope |

## 3. Thành phần hạ tầng

### 3.1 MySQL 8
- Nguồn dữ liệu thật (source of truth). Bảng chi tiết: `04-mo-hinh-du-lieu.md`.
- **Read replica** cho truy vấn nặng (analytics, reports).
- Chiến lược index/partition cho bảng lớn (`question_attempts`, `tracking_events`).

### 3.2 Redis
- **Cache**: kết quả đọc nặng, cấu hình, feature flag, thống kê tổng hợp.
- **Queue**: hàng đợi job (`high`, `default`, `low`).
- **Session store** (nếu dùng session server-side) + **rate limiter**.
- **Realtime presence** & ephemeral state (session timer, đang làm bài).

### 3.3 Meilisearch
- Full-text search cho Question, Library Article, Drug, Disease, Media.
- Faceted search (lọc theo chủ đề, độ khó, loại nội dung).
- Đồng bộ qua queue khi CRUD nội dung (`SyncToSearchJob`).

### 3.4 Laravel Reverb (WebSocket)
- Realtime notification, cập nhật tiến độ live (exam đang diễn ra), presence trong lớp học.
- Kênh: `private-user.{id}`, `presence-exam.{id}`, `private-org.{id}`.

### 3.5 Storage S3 / Cloudflare R2 + CDN
- Media: ảnh y khoa, video bài giảng, file import, export báo cáo.
- Signed URL cho nội dung Premium. Ảnh biến thể (thumbnail/webp) sinh qua job.

### 3.6 Queue & Scheduler
- **Scheduler (cron)**: cập nhật analytics hằng ngày, gửi reminder streak, dọn session hết hạn, tính lại Weak Topics, đồng bộ billing.
- **Queue workers**: email/notification, transcode media, gọi AI, export CSV/PDF, đồng bộ search.

## 4. Môi trường & cấu hình

| Môi trường | Mục đích |
|-----------|----------|
| `local` | Dev, seed dữ liệu mẫu |
| `staging` | Kiểm thử tích hợp, giống production |
| `production` | Vận hành thật |

- Cấu hình qua `.env`; secret quản lý bằng vault/secret manager.
- **Feature flags** (bảng `feature_flags` + cache) để bật/tắt tính năng theo môi trường/role/tổ chức.

## 5. Chiến lược build & deploy

- **Vite** build asset (JS/CSS), hashing, code-splitting theo module.
- CI/CD: lint → test → build → migrate → deploy (zero-downtime, health check).
- Migration có review; rollback plan cho migration phá vỡ.

## 6. Đa tầng cache (Cache layers)

| Lớp | Nội dung | TTL gợi ý | Invalidations |
|-----|----------|-----------|---------------|
| CDN | Asset tĩnh, ảnh public | dài (immutable hash) | theo hash |
| Redis app | Article render, list câu hỏi, config | 5–60 phút | event CRUD |
| Redis stats | Dashboard tổng hợp, leaderboard | 1–15 phút | job định kỳ |
| DB query cache | Kết quả nặng lặp lại | ngắn | tag-based |

## 7. Xử lý lỗi & quan sát (Observability)

- **Logging** tập trung (structured JSON), correlation id per request.
- **Error tracking** (Sentry-like) cho FE & BE.
- **Metrics**: latency, queue depth, cache hit ratio, DB slow query.
- **Health endpoints**: `/health` (liveness), `/health/ready` (readiness: DB, Redis, Meili).
- **Audit log** riêng cho hành vi nhạy cảm (xem `07-security-performance.md` và module 40).

## 8. Quy ước mã nguồn (tóm tắt)

- PSR-12, static analysis (PHPStan/Larastan), Pint format.
- Action một trách nhiệm; Controller mỏng.
- DTO cho input/output nghiệp vụ phức tạp.
- Tên bảng số nhiều snake_case; enum khai báo tập trung.
- Mọi endpoint API versioned: `/api/v1/...`.
