# Cấu hình LiveKit (self-host) — Classroom livestream

Self-host **LiveKit Server** (open source). Không bắt buộc LiveKit Cloud.
Laravel chỉ **ký JWT** join token; video/audio đi browser ↔ SFU.

## 1. Biến môi trường

Thêm vào `.env` (đã có sẵn trong `.env.example`):

```env
LIVEKIT_URL=ws://localhost:7880
LIVEKIT_API_KEY=medlearn_dev_key
LIVEKIT_API_SECRET=medlearn_dev_secret_change_me
LIVEKIT_TOKEN_TTL=3600
FORWARD_LIVEKIT_PORT=7880
FORWARD_LIVEKIT_RTC_TCP_PORT=7881
```

**Quan trọng:** `LIVEKIT_API_KEY` / `LIVEKIT_API_SECRET` trong `.env` được Docker Compose
đưa vào Laravel, LiveKit Server và Egress. Sau khi đổi một trong hai giá trị, phải
**recreate cả ba dịch vụ**; `docker compose restart` không nạp lại biến môi trường.

| Biến | Ai dùng | Ghi chú |
|------|---------|---------|
| `LIVEKIT_URL` | Browser (qua token payload) | Host máy bạn → port publish. Local: `ws://localhost:7880` |
| `LIVEKIT_API_KEY` / `SECRET` | Laravel, LiveKit, Egress | Một nguồn duy nhất trong `.env`; phải recreate container sau khi đổi |
| `FORWARD_LIVEKIT_*` | Docker publish | Đổi nếu cổng máy bị chiếm |

Production HTTPS: dùng `wss://livekit.your-domain.com` (nginx/caddy terminate TLS trước LiveKit hoặc TLS trực tiếp).

## 2. Chạy service

```bash
# Lần đầu / sau khi thêm service
docker compose up -d livekit

# Kiểm tra
docker compose ps livekit
curl -s http://localhost:7880   # thường trả về text/health của LiveKit
```

Khởi động cả stack:

```bash
docker compose up -d --build
```

Reload config Laravel sau khi sửa `.env`:

```bash
docker compose exec app php artisan config:clear
```

## 3. Sinh key mới (tuỳ chọn)

```bash
docker run --rm livekit/livekit-server:v1.13.6 generate-keys
```

Copy key/secret vào `.env`, rồi recreate toàn bộ dịch vụ sử dụng cặp khóa:

```bash
docker compose up -d --force-recreate app horizon scheduler livekit livekit-egress web
docker compose exec app php artisan optimize:clear
```

Nếu log có `401 invalid API key`, chạy lại đúng hai lệnh trên. Không chỉ dùng
`docker compose restart`, vì container cũ vẫn giữ biến môi trường cũ.

## 4. Kiểm tra trong app

1. Đăng nhập → sidebar **Classroom** → tạo lớp → lên lịch → **Bắt đầu live**.
2. Vào phòng live: trình duyệt xin quyền camera/micro (host). Học viên thấy stream của host.
3. Host có nút mic / camera / chia sẻ màn hình trên thanh phòng.
4. Cần Vite đang chạy (`docker compose up -d vite`) để bundle `livekit-client`.

Token được cấp bởi `Modules\Classroom\Services\LiveKitTokenService`:
- Host/cohost: `canPublish = true`
- Member: chỉ subscribe

## 5. Cổng mạng cần mở

| Port | Protocol | Mục đích |
|------|----------|----------|
| 7880 | TCP | Signaling WebSocket / HTTP API |
| 7881 | TCP | RTC over TCP |
| 7882 | UDP | Media (single port — local Docker) |
| 50000–60000 | UDP | Chỉ khi bỏ `udp_port` và dùng port range (production) |

**Lỗi `publishing rejected as engine not connected within timeout`:**  
Browser không tới được địa chỉ ICE mà LiveKit quảng bá (thường là IP Docker `172.x`).
Đặt `LIVEKIT_NODE_IP` trong `.env` thành IP LAN hiện tại của máy, giữ cổng
`7882/udp` được publish, rồi:

```bash
docker compose up -d --force-recreate livekit
```

Nếu IP LAN thay đổi khi đổi Wi-Fi, cập nhật `LIVEKIT_NODE_IP` và recreate LiveKit/Egress.

## 5b. Vì sao local lag? (và cách mượt hơn)

**Nguyên nhân chính trên Docker Desktop macOS:**

1. Media thường rơi về **TCP** (log `connectionType: tcp`) thay vì UDP — TCP retransmission = giật.
2. Docker Desktop chạy trong VM → thêm latency CPU/network.
3. Screen share Retina full-res 60fps + simulcast 3 lớp = rất nặng.

**Đã tối ưu trong code (local profile):**

- Camera ~360p, không simulcast, ~20fps
- Screen share ~720p @ 12–15fps, không simulcast
- Tắt `dynacast` / `adaptiveStream` khi local

Hard-refresh sau khi Vite reload. Recreate SFU:

```bash
docker compose up -d --force-recreate livekit
```

**Muốn mượt gần “thật” khi dev trên Mac** (khuyến nghị): chạy LiveKit **native** (không Docker):

```bash
# cài binary (ví dụ)
brew install livekit

livekit-server --dev --bind 0.0.0.0 --node-ip 127.0.0.1
```

Rồi trong `.env`:

```env
LIVEKIT_URL=ws://127.0.0.1:7880
LIVEKIT_API_KEY=devkey
LIVEKIT_API_SECRET=secret
```

(`--dev` dùng key `devkey`/`secret` — khớp `.env`, tắt service `livekit` trong compose nếu trùng cổng.)

**Production “mượt”:** Linux VM/bare metal (UDP mở), hoặc LiveKit Cloud — không kỳ vọng Docker Desktop Mac = production quality.

## 6. Production (tóm tắt)

1. Đổi secret mạnh; không dùng `medlearn_dev_*`.
2. Trong `livekit.yaml`: `rtc.use_external_ip: true`, gắn Redis, bật **TURN** (coturn) nếu học viên sau NAT khó.
3. TLS: `wss://` qua reverse proxy.
4. Egress (ghi VOD): thêm LiveKit Egress + webhook → cập nhật `live_recordings` (xem SRS module 44).
5. `CLASSROOM_OPEN_HOSTING=false` — chỉ Premium/`classroom.host` được host.

## 7. LiveKit Cloud từ máy local (khuyến nghị để test mượt)

App Laravel vẫn chạy local; **chỉ SFU** dùng Cloud. Browser nối thẳng tới edge LiveKit (UDP) → mic/cam/share mượt hơn Docker Desktop nhiều.

1. Tạo project free tại [cloud.livekit.io](https://cloud.livekit.io) (Build, không cần thẻ).
2. **Settings → Keys**: copy **URL** (`wss://…livekit.cloud`), **API Key**, **API Secret**.
3. Sửa `.env` local:

```env
LIVEKIT_URL=wss://YOUR_PROJECT.livekit.cloud
LIVEKIT_API_KEY=APIxxxxxxxx
LIVEKIT_API_SECRET=xxxxxxxxxxxxxxxx
```

4. Không cần container `livekit` (có thể `docker compose stop livekit`).
5. Reload config + hard-refresh browser:

```bash
docker compose exec app php artisan config:clear
# Vite đang chạy → hard refresh Cmd+Shift+R
```

Client tự nhận `wss://` / `*.livekit.cloud` và dùng **cloud profile** (720p + simulcast). Self-host `ws://127.0.0.1` vẫn dùng profile nhẹ.

**Lưu ý:** token do Laravel local ký bằng Cloud secret — đúng. Test vài session thường nằm trong free quota (xem pricing). Đừng commit key Cloud vào git.

## 8. Egress (recording VOD)

Trong Live Studio, giảng viên chủ động bấm **Bắt đầu ghi hình** sau khi xác nhận đã thông báo cho học viên.
Giảng viên có thể dừng recording mà vẫn tiếp tục live; nếu kết thúc live khi đang ghi, hệ thống tự dừng egress.
Các nút recording chỉ khả dụng khi `LIVEKIT_EGRESS_ENABLED=true` và bucket S3/R2 đã cấu hình.

```env
LIVEKIT_EGRESS_ENABLED=true
LIVEKIT_WEBHOOK_SECRET=your-webhook-secret
LIVEKIT_EGRESS_BUCKET=medlearn-recordings
LIVEKIT_EGRESS_REGION=auto
LIVEKIT_EGRESS_ENDPOINT=https://xxx.r2.cloudflarestorage.com
LIVEKIT_EGRESS_ACCESS_KEY=...
LIVEKIT_EGRESS_SECRET_KEY=...
```

Webhook URL (public HTTPS): `POST /webhooks/livekit` với header `Authorization: Bearer {LIVEKIT_WEBHOOK_SECRET}`.

Local dev: để `LIVEKIT_EGRESS_ENABLED=false` nếu chưa chạy LiveKit Egress. Studio sẽ hiển thị rõ “Recording chưa cấu hình”.

Repo đã có profile local `livekit-egress` + MinIO. Với cấu hình `.env.example`, khởi động bằng:

```bash
docker compose up -d redis livekit minio minio-init livekit-egress app web

Trong local Docker, đặt `LIVEKIT_NODE_IP` thành IP LAN của máy chạy Docker
(ví dụ `192.168.1.22`), không dùng `127.0.0.1` cho ICE. Địa chỉ này phải truy
cập được từ cả trình duyệt và container Egress; nếu không browser sẽ kẹt kết
nối hoặc Egress kết thúc với lỗi `Start signal not received`.
docker compose exec app php artisan optimize:clear
```

MinIO Console: `http://localhost:9001`; bucket mặc định: `medlearn-recordings`.
`LIVEKIT_API_URL=http://livekit:7880` là URL nội bộ để Laravel gọi Egress API; giữ
`LIVEKIT_URL=ws://127.0.0.1:7880` cho trình duyệt.

## 9. Sự cố thường gặp

| Triệu chứng | Cách xử lý |
|-------------|------------|
| UI vẫn “LiveKit chưa cấu hình” | Thiếu 3 biến env hoặc `config:clear` chưa chạy |
| Token reject / unauthorized | Key/secret `.env` ≠ `livekit.yaml` |
| Connect timeout từ browser | Sai `LIVEKIT_URL` (phải là host:port publish, không dùng `ws://livekit:7880` từ browser) |
| Có signaling nhưng không có video | Firewall/UDP; thử RTC TCP; thêm TURN |
| Cổng 7880 bận | Đổi `FORWARD_LIVEKIT_PORT` và `LIVEKIT_URL` cho khớp |
