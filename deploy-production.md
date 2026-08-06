# MedLearn — Hướng dẫn Deploy Production (Manual)

Tài liệu này mô tả quy trình **deploy thủ công** MedLearn lên server production (VPS / bare metal), **không** dùng Docker Compose (Compose chỉ dành cho local — xem `README.md`).

> Stack: Laravel 13 · PHP 8.4 · MySQL 8 · Redis 7 · Meilisearch · Horizon · Reverb · Local storage · Nginx · Vite.

---

## Mục lục

1. [Yêu cầu hệ thống](#1-yêu-cầu-hệ-thống)
2. [Chuẩn bị server](#2-chuẩn-bị-server)
3. [Cài PHP-FPM & extension](#3-cài-php-fpm--extension)
4. [Cài Nginx](#4-cài-nginx)
5. [Cài MySQL, Redis, Meilisearch](#5-cài-mysql-redis-meilisearch)
6. [Deploy mã nguồn](#6-deploy-mã-nguồn)
7. [Cấu hình `.env` production](#7-cấu-hình-env-production)
8. [Composer / NPM / Artisan](#8-composer--npm--artisan)
9. [Quyền thư mục](#9-quyền-thư-mục)
10. [Nginx virtual host](#10-nginx-virtual-host)
11. [Supervisor: Horizon · Reverb · Scheduler](#11-supervisor-horizon--reverb--scheduler)
12. [SSL (HTTPS)](#12-ssl-https)
13. [Kiểm tra môi trường (`check.php`)](#13-kiểm-tra-môi-trường-checkphp)
14. [Smoke test & health](#14-smoke-test--health)
15. [Quy trình cập nhật (release)](#15-quy-trình-cập-nhật-release)
16. [Rollback](#16-rollback)
17. [Checklist vận hành](#17-checklist-vận-hành)
18. [Bảo mật sau deploy](#18-bảo-mật-sau-deploy)
19. [Deploy qua aaPanel / Git webhook](#19-deploy-qua-aapanel--git-webhook)
20. [Seeding lần đầu trên production](#20-seeding-lần-đầu-trên-production)
21. [Troubleshooting thường gặp](#21-troubleshooting-thường-gặp)

---

## 1. Yêu cầu hệ thống

| Thành phần | Yêu cầu tối thiểu | Khuyến nghị |
|------------|-------------------|-------------|
| OS | Ubuntu 22.04 / 24.04 LTS | Ubuntu 24.04 |
| CPU / RAM | 2 vCPU · 4 GB | 4 vCPU · 8 GB+ |
| Disk | 40 GB SSD | 80 GB+ (media/log) |
| PHP | **≥ 8.3** | **8.4** (khớp `docker/php/Dockerfile`) |
| Web | Nginx 1.24+ | Nginx 1.27 |
| DB | MySQL **8.0+** | MySQL 8.4 |
| Cache/Queue | Redis **7** | Redis 7 + AOF |
| Search | Meilisearch **1.10+** | bản ổn định gần nhất |
| Storage | Local disk (`storage/`) | SSD đủ dung lượng + backup |
| Node (build) | Node **20** | Node 20 LTS |
| Composer | 2.x | 2.x mới nhất |

### PHP extensions bắt buộc

Khớp image production trong `docker/php/Dockerfile`:

```
pdo_mysql  redis  gd  intl  bcmath  zip  exif  pcntl  opcache
```

Thêm các extension Laravel core thường cần:

```
mbstring  openssl  tokenizer  xml  ctype  json  fileinfo  curl
```

### Process chạy nền (bắt buộc trên production)

| Process | Lệnh | Vai trò |
|---------|------|---------|
| PHP-FPM | `php-fpm` | HTTP |
| Horizon | `php artisan horizon` | Queue workers |
| Scheduler | `php artisan schedule:work` **hoặc** cron `schedule:run` mỗi phút | Job định kỳ |
| Reverb | `php artisan reverb:start` | WebSocket realtime |

---

## 2. Chuẩn bị server

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl unzip software-properties-common \
  build-essential acl supervisor ufw fail2ban
```

### Firewall (ví dụ UFW)

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
# Reverb: chỉ mở public nếu client nối trực tiếp WS (hoặc terminate qua Nginx proxy)
sudo ufw allow 8080/tcp   # tùy kiến trúc — xem mục Reverb
sudo ufw enable
```

### User deploy

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy
sudo mkdir -p /var/www
sudo chown deploy:www-data /var/www
```

Đăng nhập bằng user `deploy` cho các bước tiếp theo (trừ lệnh cần `sudo`).

---

## 3. Cài PHP-FPM & extension

### Ubuntu 24.04 (PHP 8.4)

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y \
  php8.4-fpm php8.4-cli php8.4-common \
  php8.4-mysql php8.4-redis php8.4-gd php8.4-intl \
  php8.4-bcmath php8.4-zip php8.4-xml php8.4-mbstring \
  php8.4-curl php8.4-readline php8.4-opcache
```

> `pcntl` thường có sẵn trong `php8.4-cli` (Horizon/Reverb chạy CLI). Không enable `pcntl` trong FPM web request.

### `php.ini` gợi ý (FPM)

File: `/etc/php/8.4/fpm/conf.d/99-medlearn.ini`

```ini
memory_limit = 512M
max_execution_time = 60
upload_max_filesize = 64M
post_max_size = 68M
max_input_vars = 5000
expose_php = Off
date.timezone = UTC
realpath_cache_size = 4096k
realpath_cache_ttl = 600
```

### OPcache production

File: `/etc/php/8.4/fpm/conf.d/99-opcache.ini`

```ini
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.jit = 1255
opcache.jit_buffer_size = 128M
```

```bash
sudo systemctl enable --now php8.4-fpm
sudo systemctl restart php8.4-fpm
php -v
php -m | grep -E 'pdo_mysql|redis|gd|intl|bcmath|zip|exif|pcntl|opcache'
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

---

## 4. Cài Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable --now nginx
```

Document root **phải** trỏ vào thư mục `public/` của Laravel — không trỏ root project.

---

## 5. Cài MySQL, Redis, Meilisearch

### 5.1 MySQL 8

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Tạo database & user:

```sql
CREATE DATABASE medlearn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'medlearn'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON medlearn.* TO 'medlearn'@'127.0.0.1';
FLUSH PRIVILEGES;
```

> Production: chỉ cho phép kết nối từ app host; bật backup (mysqldump / snapshot) hàng ngày.

### 5.2 Redis

```bash
sudo apt install -y redis-server
```

Trong `/etc/redis/redis.conf` (gợi ý):

```
bind 127.0.0.1
requirepass CHANGE_ME_REDIS_PASSWORD
appendonly yes
maxmemory 1gb
maxmemory-policy allkeys-lru
```

```bash
sudo systemctl enable --now redis-server
redis-cli -a 'CHANGE_ME_REDIS_PASSWORD' ping
```

### 5.3 Meilisearch

Cài binary theo [tài liệu chính thức](https://www.meilisearch.com/docs/learn/getting_started/installation), ví dụ:

```bash
# Ví dụ cài nhanh (kiểm tra URL phiên bản hiện tại trên docs Meilisearch)
curl -L https://install.meilisearch.com | sh
sudo mv ./meilisearch /usr/local/bin/
sudo useradd --system --no-create-home meilisearch || true
sudo mkdir -p /var/lib/meilisearch/data
sudo chown -R meilisearch:meilisearch /var/lib/meilisearch
```

Unit systemd `/etc/systemd/system/meilisearch.service`:

```ini
[Unit]
Description=Meilisearch
After=network.target

[Service]
User=meilisearch
Group=meilisearch
ExecStart=/usr/local/bin/meilisearch \
  --http-addr 127.0.0.1:7700 \
  --env production \
  --db-path /var/lib/meilisearch/data \
  --master-key CHANGE_ME_MEILI_MASTER_KEY
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now meilisearch
curl -s http://127.0.0.1:7700/health
```

### 5.4 File storage (local disk)

Self-host dùng **local disk** Laravel (`FILESYSTEM_DISK=local`):

- File nằm trong `storage/app` (private) và `storage/app/public` (media public).
- Chạy `php artisan storage:link` để phục vụ file public qua `/storage/...`.
- Đảm bảo quyền ghi cho user PHP-FPM; backup thư mục `storage/` cùng MySQL.
- Nên dùng `shared/storage` giữa các release (xem cấu trúc thư mục bên dưới).

---

## 6. Deploy mã nguồn

### Cấu trúc thư mục khuyến nghị (zero-downtime nhẹ)

```
/var/www/medlearn/
├── current -> releases/20260730_153000   # symlink
├── releases/
│   └── 20260730_153000/
├── shared/
│   ├── .env
│   └── storage/          # optional: shared storage giữa releases
└── repo/                 # optional bare repo
```

### Lần đầu (đơn giản — một thư mục)

```bash
cd /var/www
git clone git@github.com:YOUR_ORG/du-an-y-khoa.git medlearn
cd medlearn
git checkout main   # hoặc tag release
```

> Không commit `.env`. Không để `APP_DEBUG=true` trên production.

---

## 7. Cấu hình `.env` production

```bash
cp .env.example .env
chmod 600 .env
nano .env   # hoặc dùng secret manager rồi sync vào file
```

### Giá trị bắt buộc (mẫu)

```dotenv
APP_NAME=MedLearn
APP_ENV=production
APP_KEY=                      # generate ở bước sau
APP_DEBUG=false
APP_URL=https://medlearn.example.com

APP_LOCALE=vi
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning             # hoặc info — tránh debug trên prod

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medlearn
DB_USERNAME=medlearn
DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD
# DB_READ_HOST=...            # nếu có read replica

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=CHANGE_ME_REDIS_PASSWORD
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
QUEUE_CONNECTION=redis
HORIZON_PREFIX=medlearn_horizon

SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=CHANGE_ME_MEILI_MASTER_KEY

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=medlearn
REVERB_APP_KEY=CHANGE_ME_REVERB_KEY
REVERB_APP_SECRET=CHANGE_ME_REVERB_SECRET
REVERB_HOST=medlearn.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Browser (build-time Vite) — phải khớp domain/WS public
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=medlearn.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=smtp.provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@medlearn.example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Token bảo vệ public/check.php — ĐỔI NGAY
CHECK_TOKEN=CHANGE_ME_LONG_RANDOM_TOKEN
```

Sinh `APP_KEY`:

```bash
php artisan key:generate
```

---

## 8. Composer / NPM / Artisan

Cài Node 20 (build asset trên server **hoặc** CI rồi upload `public/build`):

```bash
# Node 20 (NodeSource hoặc nvm)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

### Cài dependency & build

```bash
cd /var/www/medlearn

# PHP deps — không cài dev packages trên production
composer install --no-dev --optimize-autoloader --no-interaction

# Frontend production assets
npm ci
npm run build

# Liên kết storage public
php artisan storage:link

# Migration (backup DB trước!)
php artisan migrate --force

# (Tuỳ chọn lần đầu) seed admin — CHỈ khi môi trường mới, hiểu rõ rủi ro
# php artisan db:seed --force

# Cache cấu hình / route / view
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Index search (sau khi có dữ liệu)
php artisan scout:import "Modules\\QuestionBank\\Models\\Question"
# Import thêm các model Scout khác khi module sẵn sàng
```

### Reload PHP-FPM sau deploy (OPcache)

```bash
sudo systemctl reload php8.4-fpm
```

---

## 9. Quyền thư mục

```bash
cd /var/www/medlearn

sudo chown -R deploy:www-data .
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;

# Writable cho runtime
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

# .env chỉ user deploy đọc được
chmod 600 .env
```

Nếu dùng ACL:

```bash
sudo setfacl -R -m u:www-data:rwX -m u:deploy:rwX storage bootstrap/cache
sudo setfacl -dR -m u:www-data:rwX -m u:deploy:rwX storage bootstrap/cache
```

---

## 10. Nginx virtual host

Tạo `/etc/nginx/sites-available/medlearn`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name medlearn.example.com;
    root /var/www/medlearn/public;

    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    index index.php;
    charset utf-8;
    client_max_body_size 68M;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript application/xml image/svg+xml;
    gzip_min_length 1024;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_read_timeout 120;
    }

    # Proxy WebSocket tới Reverb (khuyến nghị thay vì mở 8080 public)
    location /app {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_pass http://127.0.0.1:8080;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/medlearn /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 11. Supervisor: Horizon · Reverb · Scheduler

Cài `supervisor` (đã nêu ở mục 2). Tạo `/etc/supervisor/conf.d/medlearn.conf`:

```ini
[program:medlearn-horizon]
process_name=%(program_name)s
command=php /var/www/medlearn/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/medlearn/storage/logs/horizon.log
stopwaitsecs=3600

[program:medlearn-reverb]
process_name=%(program_name)s
command=php /var/www/medlearn/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/medlearn/storage/logs/reverb.log

[program:medlearn-scheduler]
process_name=%(program_name)s
command=php /var/www/medlearn/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/medlearn/storage/logs/scheduler.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

### Cách thay thế scheduler bằng cron

Nếu không dùng `schedule:work`:

```cron
* * * * * www-data cd /var/www/medlearn && php artisan schedule:run >> /dev/null 2>&1
```

### Horizon dashboard

Truy cập `/horizon` (đã bảo vệ theo cấu hình Laravel Horizon — chỉ user được phép). Kiểm tra:

```bash
php artisan horizon:status
```

Sau mỗi deploy code:

```bash
php artisan horizon:terminate   # Supervisor sẽ tự restart worker với code mới
sudo supervisorctl restart medlearn-reverb
```

---

## 12. SSL (HTTPS)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d medlearn.example.com
```

Đảm bảo `APP_URL`, `REVERB_SCHEME=https`, và các `VITE_REVERB_*` dùng HTTPS/WSS. Rebuild asset nếu đổi biến `VITE_*`:

```bash
npm run build
php artisan config:cache
sudo systemctl reload php8.4-fpm
```

Thêm header HSTS (sau khi HTTPS ổn định) trong Nginx:

```nginx
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
```

---

## 13. Kiểm tra môi trường (`check.php`)

File `public/check.php` kiểm tra:

- Phiên bản PHP & ini quan trọng
- Extension bắt buộc / khuyến nghị
- OPcache
- Quyền `storage/`, `bootstrap/cache/`
- `vendor/`, Vite `public/build/manifest.json`, `.env`
- Document root
- Biến môi trường quan trọng
- Kết nối MySQL · Redis · Meilisearch · local storage writable

### Cách dùng

1. Đặt `CHECK_TOKEN` mạnh trong `.env`.
2. Mở trình duyệt:

```
https://medlearn.example.com/check.php?token=YOUR_CHECK_TOKEN
```

Hoặc CLI trên server:

```bash
cd /var/www/medlearn
CHECK_TOKEN=... php public/check.php
# hoặc nếu .env đã có CHECK_TOKEN:
php public/check.php
```

3. **Xóa ngay sau khi đạt PASS:**

```bash
rm public/check.php
```

> Không để `check.php` public lâu dài — dù đã có token.

---

## 14. Smoke test & health

```bash
# Liveness
curl -s https://medlearn.example.com/health
# → {"status":"ok"}

# Readiness (DB + Redis + Meilisearch)
curl -s https://medlearn.example.com/health/ready
# → {"status":"ready","checks":{...}}

# Horizon
php artisan horizon:status

# Logs
tail -f storage/logs/laravel.log
```

Checklist tay:

- [ ] Trang landing load CSS/JS (không 404 `/build/...`)
- [ ] Đăng nhập / session Redis hoạt động
- [ ] Upload media vào `storage/` (local) thành công; `/storage/...` serve được
- [ ] Job queue được Horizon xử lý
- [ ] WebSocket (notification / presence) qua WSS
- [ ] Search Meilisearch trả kết quả
- [ ] Mail SMTP gửi được (hoặc queue mail)

---

## 15. Quy trình cập nhật (release)

Thực hiện trong cửa sổ bảo trì ngắn hoặc theo blue/green nếu có load balancer.

```bash
cd /var/www/medlearn

# 1. Bảo trì (tuỳ chọn)
php artisan down --retry=60 --secret=ops-bypass-token

# 2. Backup DB
mysqldump -u medlearn -p medlearn | gzip > ~/backups/medlearn-$(date +%Y%m%d%H%M).sql.gz

# 3. Code mới
git fetch --tags origin
git checkout vX.Y.Z   # hoặc pull main đã tag

# 4. Dependencies & assets
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

# 5. Migrate
php artisan migrate --force

# 6. Cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Restart workers / OPcache
php artisan horizon:terminate
sudo supervisorctl restart medlearn-reverb
sudo systemctl reload php8.4-fpm

# 8. Mở lại
php artisan up
```

Bypass maintenance (nếu dùng `--secret`):

```
https://medlearn.example.com/ops-bypass-token
```

---

## 16. Rollback

1. `php artisan down`
2. Checkout tag/commit trước đó: `git checkout vX.Y.Z-prev`
3. `composer install --no-dev --optimize-autoloader`
4. Khôi phục `public/build` tương ứng (hoặc `npm run build` lại)
5. **Chỉ** rollback migration nếu đã chuẩn bị migration down an toàn — nhiều migration không rollback được dữ liệu; ưu tiên restore DB từ backup nếu migration phá vỡ.
6. `php artisan config:cache` + reload FPM + `horizon:terminate`
7. `php artisan up`

---

## 17. Checklist vận hành

### Hằng ngày / theo dõi

- [ ] `/health/ready` = 200
- [ ] Horizon không stalled; queue depth bình thường
- [ ] Disk `storage/logs` không đầy
- [ ] Redis memory dưới ngưỡng
- [ ] Backup DB thành công

### Hằng tuần

- [ ] Rotate / archive log
- [ ] Kiểm tra chứng chỉ SSL còn hạn
- [ ] `composer audit` / cập nhật bảo mật theo kế hoạch
- [ ] Meilisearch disk & index health

### Backup tối thiểu

| Dữ liệu | Tần suất | Ghi chú |
|---------|----------|---------|
| MySQL dump | Daily | Giữ ≥ 7–30 ngày |
| `.env` / secrets | Khi đổi | Lưu vault, không trong git |
| `storage/` (uploads) | Daily / cùng dump | Rsync hoặc snapshot disk |
| Meilisearch | Có thể rebuild từ DB | `scout:import` |

---

## 18. Bảo mật sau deploy

1. `APP_DEBUG=false`, `APP_ENV=production`
2. Xóa `public/check.php` và mọi file debug
3. Không publish port MySQL / Redis / Meilisearch ra Internet
4. Secret mạnh cho DB, Redis, Meili, Reverb, `CHECK_TOKEN`
5. HTTPS + HSTS; cookie `Secure` / `SameSite`
6. Chỉ mở Horizon UI cho admin
7. Quyền file chặt; `.env` mode `600`
8. Fail2ban / rate limit ở Nginx hoặc Cloudflare
9. Không chạy seed volume (`SEED_VOLUME`) trên production
10. Theo dõi `srs/00-nen-tang/07-security-performance.md`

---

## 19. Deploy qua aaPanel / Git webhook

Repo có sẵn script tự động hóa: [`scripts/deploy.sh`](scripts/deploy.sh) — phù hợp khi host dùng **aaPanel** (PHP-FPM + Nginx + Supervisor có sẵn trên panel).

### 19.1 Chuẩn bị trên aaPanel

1. Tạo site PHP, **Run Directory = `/public`** (bắt buộc).
2. Cài PHP **8.4** (hoặc 8.3+) và bật extension ở mục 3 tài liệu này.
3. Clone repo vào thư mục site (ví dụ `/www/wwwroot/medlearn.example.com`).
4. Tạo `.env` production **trong thư mục site** (không commit git).
5. Cấu hình Supervisor cho Horizon / Reverb / Scheduler (mục 11), đặt tên program khớp biến trong script.

### 19.2 Cấu hình script

Chỉnh các biến đầu file `scripts/deploy.sh` (hoặc export trước khi chạy):

```bash
export SITE_PATH=/www/wwwroot/medlearn.example.com
export BRANCH=main
export APP_USER=www                    # user PHP-FPM trên aaPanel
export PHP_BIN=/www/server/php/84/bin/php
export PHP_FPM_SERVICE=php-fpm-84
export HORIZON_PROGRAM=medlearn-horizon
export REVERB_PROGRAM=medlearn-reverb
export BUILD_ASSETS=true               # npm ci && npm run build trên server
export RUN_MIGRATE=true
```

### 19.3 Gắn Git webhook (aaPanel Git Manager)

1. **Website → site → Git Manager → Script** — tạo script alias `Deploy_Script`.
2. Nội dung script:

```bash
bash /www/wwwroot/medlearn.example.com/scripts/deploy.sh
```

3. **Repository** — gắn script, copy Webhook URL → thêm vào GitHub/GitLab (push event).
4. Thêm **Deploy Key** (SSH) của aaPanel vào repo (read-only).

Script sẽ tự:

- Bật maintenance mode (`artisan down --secret=...`)
- `git fetch` + `reset --hard origin/<branch>` (giữ `.env`, `storage/`, `vendor/`, `public/build`)
- `composer install --no-dev --optimize-autoloader`
- `npm ci && npm run build` (nếu `BUILD_ASSETS=true`)
- `migrate --force`, cache config/route/view/event
- `horizon:terminate`, restart Supervisor, reload PHP-FPM
- `artisan up`

Bypass maintenance khi deploy:

```
https://medlearn.example.com/ops-bypass-token
```

### 19.4 Chạy tay (không webhook)

```bash
cd /www/wwwroot/medlearn.example.com
chmod +x scripts/deploy.sh
SITE_PATH=$(pwd) bash scripts/deploy.sh
```

---

## 20. Seeding lần đầu trên production

**Chỉ chạy trên môi trường mới**, sau `migrate --force`, khi DB còn trống.

### 20.1 Tài khoản & RBAC (bắt buộc lần đầu)

```bash
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=UserSeeder --force
```

Tạo các tài khoản cố định (mật khẩu mặc định `password` — **đổi ngay sau deploy**):

| Vai trò | Email |
|---------|-------|
| Super Admin | `admin@medlearn.local` |
| Content Editor | `editor@medlearn.local` |
| Student (demo) | `student@medlearn.local` |

### 20.2 Dữ liệu ngân hàng câu hỏi (tuỳ chọn)

```bash
# Demo ~30 câu + topics + session mẫu (không cần file ngoài)
php artisan db:seed --class=Modules\\QuestionBank\\Database\\Seeders\\DemoLearningSeeder --force

# Hoặc seed toàn bộ module QuestionBank (demo + volume nếu bật SEED_VOLUME)
php artisan db:seed --class=Modules\\QuestionBank\\Database\\Seeders\\QuestionBankDatabaseSeeder --force
```

> **Không** đặt `SEED_VOLUME=true` trên production — biến này sinh hàng nghìn bản ghi test hiệu năng.

Dataset VM14K (~14k câu): copy file JSONL vào `Modules/QuestionBank/database/seeders/data/vm14k/` rồi chạy seeder tương ứng (xem README trong thư mục đó). Chỉ dùng khi đã có đủ disk/RAM và thời gian import Meilisearch.

### 20.3 Index Meilisearch sau seed

```bash
php artisan scout:import "Modules\\QuestionBank\\Models\\Question"
```

Theo dõi queue Horizon nếu `SCOUT_QUEUE=true`.

---

## 21. Troubleshooting thường gặp

| Triệu chứng | Nguyên nhân thường gặp | Cách xử lý |
|-------------|------------------------|------------|
| Trang trắng / 500 | Thiếu extension, sai quyền `storage/` | Chạy `php public/check.php`; xem `storage/logs/laravel.log` |
| CSS/JS 404 (`/build/...`) | Chưa build Vite | `npm ci && npm run build`; kiểm tra `public/build/manifest.json` |
| `Route [login] not defined` trên API | Client không gửi JSON | API đã ép JSON qua middleware — client gọi với `Accept: application/json` |
| Queue không chạy | Horizon chưa start | `supervisorctl status`; `php artisan horizon:status` |
| WebSocket không kết nối | Reverb down / sai proxy Nginx | Kiểm tra `location /app` proxy tới `127.0.0.1:8080`; `VITE_REVERB_*` khớp domain HTTPS |
| Search không trả kết quả | Meili chưa index | `curl http://127.0.0.1:7700/health`; chạy `scout:import` |
| Upload media lỗi | Thiếu quyền `storage/app` | `chown www-data storage -R`; `php artisan storage:link` |
| OPcache code cũ sau deploy | FPM chưa reload | `sudo systemctl reload php8.4-fpm`; `php artisan horizon:terminate` |
| MySQL `Access denied` | Sai user/host | User nên là `'medlearn'@'127.0.0.1'` khớp `.env` |
| Redis `NOAUTH` | Thiếu/sai `REDIS_PASSWORD` | Khớp password trong `redis.conf` và `.env` |
| `502 Bad Gateway` | PHP-FPM socket sai | Nginx `fastcgi_pass` khớp socket thực (`/run/php/php8.4-fpm.sock`) |
| Maintenance kẹt | Deploy fail giữa chừng | `php artisan up` hoặc xóa `storage/framework/down` |

### Log cần xem

```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/horizon.log      # nếu cấu hình Supervisor
tail -f /var/log/nginx/error.log
journalctl -u meilisearch -f
```

---

## Phụ lục A — Lệnh một dòng kiểm tra nhanh

```bash
php -v \
  && php -m | grep -E 'pdo_mysql|redis|gd|intl|bcmath|zip|pcntl|opcache' \
  && redis-cli ping \
  && mysqladmin ping -h 127.0.0.1 -u medlearn -p \
  && curl -s http://127.0.0.1:7700/health \
  && curl -s https://medlearn.example.com/health/ready
```

## Phụ lục B — Khác biệt Local (Docker) vs Production

| Hạng mục | Local (`docker compose`) | Production (manual) |
|----------|--------------------------|---------------------|
| App runtime | Container `app` + `web` | PHP-FPM + Nginx trên host |
| Storage | Local disk (`storage/`) | Local disk (`storage/`, shared giữa releases) |
| Mail | Mailpit | SMTP provider |
| Meili env | `development` | `production` + master key mạnh |
| Assets | Vite HMR (`npm run dev`) | `npm run build` → `public/build` |
| Workers | Service compose | Supervisor |
| Debug | `APP_DEBUG=true` | **luôn false** |

---

*Tài liệu đi kèm mã nguồn MedLearn. Cập nhật khi thay đổi stack (PHP, Meilisearch, Horizon, Reverb).*
