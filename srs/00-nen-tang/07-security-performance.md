# 07 — Security & Performance

> Chuẩn bảo mật và hiệu năng áp dụng cho mọi module. Mỗi module chỉ nêu điểm đặc thù.

## PHẦN A — SECURITY

### 1. Authentication
- Mật khẩu băm **bcrypt/argon2**; chính sách độ mạnh; chống brute-force (lockout + rate limit + captcha khi nghi ngờ).
- OAuth (Google/Facebook/Apple) qua Socialite; liên kết tài khoản; xác thực email cho đăng ký email.
- **2FA** (TOTP) tùy chọn cho tài khoản nhạy cảm (admin, org_admin bắt buộc).
- Session/token: Sanctum; token có thời hạn + refresh; revoke theo thiết bị.
- "Remember me" an toàn (token xoay vòng).

### 2. Authorization
- RBAC + Policy (xem `03-phan-quyen-rbac.md`); **deny by default**.
- Server luôn re-check quyền và entitlement (không tin FE).
- Data scoping theo owner/organization (global scope) chống IDOR.

### 3. Chống tấn công web (OWASP)
| Rủi ro | Biện pháp |
|--------|-----------|
| XSS | Escape output Blade mặc định; sanitize rich content (HTMLPurifier) cho stem/explanation/note; CSP header |
| CSRF | Token CSRF cho form web; SameSite cookie; Sanctum cho SPA |
| SQL Injection | Eloquent/prepared statements; không nối chuỗi query |
| IDOR | Policy + scope theo owner; dùng public_id thay id tuần tự khi cần |
| Mass assignment | `$fillable` whitelist; Form Request validation |
| SSRF | Whitelist domain khi fetch (AI, media remote) |
| File upload | Kiểm tra mime thật, giới hạn size, quét malware, lưu ngoài webroot, tên ngẫu nhiên |
| Clickjacking | `X-Frame-Options`/`frame-ancestors` |
| Open redirect | Whitelist redirect target |

### 4. Bảo mật dữ liệu
- Mã hóa at-rest field nhạy cảm (OAuth token, thông tin thanh toán không lưu — dùng cổng); TLS in-transit.
- **PII** tối thiểu hóa; chính sách lưu trữ & xóa (right to be forgotten → ẩn danh hóa attempt).
- Không lưu số thẻ; thanh toán qua cổng đạt PCI (token hóa).
- Secret trong secret manager, không commit.

### 5. Rate limiting & abuse
- Theo `05-api-conventions.md` mục 7.
- Chống scraping nội dung Premium: signed URL media, watermark ảnh, giới hạn tải, phát hiện bất thường.
- Chống chia sẻ tài khoản: giới hạn số thiết bị đồng thời, cảnh báo login lạ.

### 6. Audit & logging
- **Audit log** cho hành vi nhạy cảm: đăng nhập admin, thay đổi quyền, CRUD nội dung publish, thao tác billing, impersonate, xóa dữ liệu. Bảng `audit_logs` (module 40).
- Log không chứa secret/PII thô; correlation id.

### 7. Headers bảo mật chuẩn
```
Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
Content-Security-Policy: default-src 'self'; ...
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=()
```

### 8. Quy trình
- Dependency scanning, cập nhật vá lỗi; pen-test định kỳ.
- Incident response plan; backup mã hóa + kiểm thử restore.

---

## PHẦN B — PERFORMANCE

### 1. Frontend
- **Lazy load**: ảnh (`loading=lazy`), component nặng (chart, video) tải khi cần.
- **Image optimization**: webp/avif, responsive `srcset`, thumbnail; kích thước ảnh y khoa lớn → biến thể + zoom viewer riêng.
- **Code splitting** theo module qua Vite; defer JS không quan trọng.
- **Skeleton/loading state** thay vì trắng màn hình; tránh layout shift (CLS).
- Prefetch route/nội dung khả năng cao sẽ mở (câu tiếp theo trong session).

### 2. Backend
- **Redis cache** cho đọc nặng: dashboard, list, article render, config, leaderboard.
- **Search cache**: cache kết quả Meilisearch phổ biến; debounce query.
- **Database index** đúng chỗ (xem `04-mo-hinh-du-lieu.md` mục 12); tránh N+1 (eager load); read replica cho báo cáo.
- **Queue/Background job**: email, transcode media, AI, export, rollup analytics, đồng bộ search.
- **CDN** cho asset & media; signed URL cache-friendly.

### 3. Chiến lược tải dữ liệu
| Kịch bản | Chiến lược |
|----------|-----------|
| Danh sách dài (feed câu hỏi, media) | Infinite scroll / cursor pagination |
| Bảng admin | Server-side pagination + filter index |
| Session làm bài | Prefetch câu kế tiếp, cache client state |
| Dashboard | Đọc rollup cache, refresh nền |
| Realtime | WebSocket thay polling |

### 4. Mục tiêu hiệu năng (SLO)
| Chỉ số | Mục tiêu |
|--------|----------|
| p95 API đọc (cache hit) | < 300 ms |
| p95 API ghi | < 800 ms |
| LCP (trang chính) | < 2.5 s |
| Concurrent users | > 5.000 |
| Cache hit ratio (dashboard) | > 90% |
| Queue xử lý (email/notify) | < 30 s |

### 5. Khả mở rộng
- App-tier **stateless** → scale ngang sau load balancer.
- Session/state ở Redis; media ở object storage.
- DB: read replica; sharding/partition bảng ghi lớn khi cần.
- Job workers scale độc lập theo queue depth.

### 6. Giám sát hiệu năng
- APM: latency theo endpoint, DB slow query log, cache hit, queue depth.
- Cảnh báo ngưỡng (alert) + dashboard vận hành.
- Load test trước sự kiện cao điểm (mùa thi).
