# MedLearn — Nền tảng Học & Luyện thi Y khoa

Ứng dụng **Laravel (monolith modular)** xây dựng theo tài liệu SRS trong `srs/`
(kiến trúc: `srs/00-nen-tang/02-kien-truc-ky-thuat.md`). Ưu tiên **hiệu suất**,
mã nguồn **chuẩn PSR-12**, chia theo **module** tái sử dụng, môi trường dev bằng **Docker**.

> Framework cài đặt là **Laravel 13** (bản mới nhất tại thời điểm khởi tạo — kế thừa
> đặc tả Laravel 12 trong SRS, tối ưu hơn về hiệu năng).

## 1. Ngăn xếp công nghệ

| Thành phần | Công nghệ |
|-----------|-----------|
| Backend | Laravel 13 · PHP 8.4 |
| DB | MySQL 8 (read/write split) |
| Cache / Queue / Session | Redis · Horizon |
| Search | Meilisearch (Laravel Scout) |
| Realtime | Laravel Reverb (WebSocket) |
| Storage | S3-compatible (MinIO local · R2/S3 prod) |
| RBAC | spatie/laravel-permission |
| Module | nwidart/laravel-modules |
| Frontend | Blade · Tailwind 4 · Alpine · Livewire · Vite |
| Chất lượng | Pint (PSR-12) · Larastan (level 6) |

## 2. Khởi động nhanh (Docker)

```bash
cp .env.example .env          # đã có sẵn .env; bước này cho lần clone mới
docker compose up -d --build  # dựng toàn bộ stack

# Khởi tạo ứng dụng (chạy 1 lần)
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

| Dịch vụ | URL |
|---------|-----|
| App (Nginx) | http://localhost |
| Vite dev server | http://localhost:5173 |
| Reverb (WS) | ws://localhost:8080 |
| Meilisearch | http://localhost:7700 |
| MinIO Console | http://localhost:9001 |
| Mailpit | http://localhost:8025 |
| Horizon | http://localhost/horizon |

Health checks: `GET /health` (liveness) · `GET /health/ready` (DB/Redis/Meili).

## 3. Kiến trúc mã nguồn

Luồng: **Route → Middleware → Controller/Livewire → Action → Domain Service → Model/Repository**.
Controller mỏng; mỗi Action là một use-case đơn lẻ, test độc lập.

```
app/
├── Http/Middleware/        AssignRequestId · SetLocale · EnsureSubscriptionActive
├── Http/Controllers/       HealthController + base Controller (authorize/validate)
├── Models/                 User (HasRoles + HasApiTokens + entitlements)
├── Providers/              AppServiceProvider (strict models, rate limiters)
└── Support/                ← Kernel dùng chung cho mọi module
    ├── Concerns/AsAction           trait Action (::run / ::make)
    ├── Data/DataTransferObject     base DTO (whitelist, ::from)
    ├── Enums/                      Role · Permission · Entitlement (nguồn RBAC)
    ├── Http/ApiQuery               filter/sort/include/paginate (whitelist)
    ├── Http/Responses/ApiResponse  envelope chuẩn { data | error, meta, links }
    ├── Providers/ModuleRouteServiceProvider  route module (api/v1, tên chuẩn)
    └── Repositories/               EloquentRepository (base, generic)

Modules/<Ten>/                ← bounded context, tự đăng ký routes/migrations/providers
├── app/{Actions,Services,Http,Models,Enums,Policies,Repositories,Data,Providers}
├── config/  database/{migrations,factories,seeders}  routes/{web,api}.php
└── module.json · composer.json
```

### Các module (bounded context)

`Auth · QuestionBank · StudyPlan · Library · Media · Personalization · Analytics ·
Exam · Search · Notification · Billing · Account · AiAssistant · Admin`

> Gom 42 màn hình SRS thành các bounded context tái sử dụng (tránh trùng lặp).
> **QuestionBank** là module tham chiếu, hiện thực đầy đủ một lát cắt dọc
> (Model + Scout + Migration + DTO + Repository + Action + Resource + Policy + API).

## 4. Quy ước API

- Versioned: `/api/v1/...`; tên route module: `api.{module-kebab}.*`.
- Envelope thành công/lỗi + rate limit theo `srs/00-nen-tang/05-api-conventions.md`.
- Auth: Sanctum (token/cookie). RBAC: `role:` · `permission:`. Premium: `subscription:{entitlement}`.

Ví dụ: `GET /api/v1/questions?filter[difficulty]=hard&per_page=20`.

## 5. Lệnh thường dùng

```bash
# Tạo module mới (cấu trúc gọn đã cấu hình sẵn)
docker compose exec app php artisan module:make <Ten>

# Chất lượng mã
docker compose exec app ./vendor/bin/pint          # format PSR-12
docker compose exec app ./vendor/bin/phpstan analyse

# Search / Realtime / Queue
docker compose exec app php artisan scout:import "Modules\\QuestionBank\\Models\\Question"
# Reverb, Horizon, Scheduler chạy sẵn dưới dạng service riêng.
```

## 6. Tài khoản seed

| Vai trò | Email | Mật khẩu |
|---------|-------|----------|
| Super Admin | admin@medlearn.local | password |
| Student | student@medlearn.local | password |

---

Chi tiết nghiệp vụ từng module: xem `srs/modules/`. Nền tảng dùng chung: `srs/00-nen-tang/`.
