# MedLearn — Deploy local/dev (Docker Compose)

Tài liệu này là “prompt” để bạn chạy theo thứ tự các lệnh cần thiết khi deploy lần đầu hoặc sau khi pull code.

## 1) Chuẩn bị

- Đảm bảo bạn đã cài `docker` và `docker compose` (Compose v2).
- Repo root là thư mục hiện tại của bạn (`/Users/user/Desktop/du-an-y-khoa`).

## 2) Tạo cấu hình môi trường

```bash
cp .env.example .env
```

`docker-compose.yml` dùng `.env` để cấu hình MySQL/Redis/Meili/Reverb/Mailpit.

Nếu muốn chạy seed dữ liệu volume lớn (performance test), bật:

```bash
# mở .env và đổi:
SEED_VOLUME=true
```

## 3) Khởi động stack (includes npm install + composer install lần đầu)

```bash
docker compose up -d --build
```

Sau khi chạy:
- Service `app` (PHP-FPM) sẽ cài `composer install` lần đầu nếu chưa có `vendor/`, tạo `APP_KEY` nếu cần.
- Service `vite` (node:20-alpine) sẽ tự chạy `npm install` rồi `npm run dev -- --host --port ${VITE_PORT:-5173}`.

## 4) Laravel setup: migrate + seed

Chạy 1 lần (hoặc khi bạn thêm migrations mới):

```bash
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --seed
```

Tạo link storage (idempotent):

```bash
docker compose exec app php artisan storage:link
```

## 5) (Tuỳ chọn) Re-seed / seed lại

- Chỉ seed lại mà không migrate:

```bash
docker compose exec app php artisan db:seed
```

- Nếu bạn đã bật `SEED_VOLUME=true` ở bước 2, chỉ cần:

```bash
docker compose exec app php artisan db:seed
```

## 6) (Tuỳ chọn) Chạy lại `npm install` thủ công

`vite` service đã tự làm `npm install` khi container start. Nếu bạn muốn chạy lại sau khi thay đổi `package.json`:

```bash
docker compose exec vite npm install
```

Sau đó để chắc chắn HMR/dev server chạy đúng, restart service:

```bash
docker compose restart vite
```

## 7) URL tham chiếu (local)

- App (Nginx): `http://localhost` (hoặc `http://localhost:${APP_PORT}` nếu đổi port)
- Vite dev server: `http://localhost:5173`
- Reverb WebSocket: `ws://localhost:8080`
- **LiveKit (Classroom SFU):** `ws://localhost:7880` — xem [`docs/livekit.md`](docs/livekit.md)
- Meilisearch: `http://localhost:7700`
- Mailpit UI: `http://localhost:8025`
- Adminer (MySQL UI): `http://localhost:8081`

## 8) LiveKit (Classroom livestream)

Self-host trong Docker — không cần tài khoản Cloud:

1. Copy biến từ `.env.example` vào `.env` (khối `LIVEKIT_*`), đảm bảo key/secret khớp `docker/livekit/livekit.yaml`.
2. `docker compose up -d livekit`
3. `docker compose exec app php artisan config:clear`
4. Vào Classroom → start live → UI báo LiveKit sẵn sàng.

Chi tiết: [`docs/livekit.md`](docs/livekit.md).

