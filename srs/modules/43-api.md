# Module 43 — API (Public/Platform API)

**Nhóm:** Platform · **Ưu tiên:** Trung bình-Cao · **Phụ thuộc:** Auth (02), RBAC (39), API conventions (nền tảng) · **Trạng thái:** ✅

## 0. Tóm tắt module
Nền tảng API cho: (a) client web/mobile nội bộ, (b) tích hợp bên thứ 3/tổ chức (LMS), (c) webhook. Bao gồm quản lý API key/token, tài liệu (OpenAPI), versioning, rate limit, developer portal.

| Route | Màn hình |
|-------|----------|
| `/developer` | Developer portal |
| `/developer/keys` | Quản lý API key |
| `/developer/docs` | Tài liệu API (OpenAPI/Swagger) |
| `/developer/webhooks` | Cấu hình webhook |

## 1. Tổng quan
- **Mục đích:** Cho phép tích hợp lập trình an toàn & có kiểm soát.
- **Đến từ:** Settings tổ chức/developer, Admin.
- **Đi sang:** Tài liệu, thử API (playground).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **API key manager** | Tạo/thu hồi key, scope, hiện 1 lần | `/keys` | Table |
| **Docs (Swagger UI)** | Tài liệu tương tác + try-it | `/docs` | — |
| **Webhook config** | Endpoint, sự kiện, secret, retry log | `/webhooks` | — |
| **Usage dashboard** | Lượt gọi, rate limit, lỗi | Portal | Chart |
| **Playground** | Thử endpoint | Docs | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `ApiKeyManager`(scope, reveal-once), `SwaggerViewer`, `WebhookConfig`(test/redeliver), `UsageChart`, `Playground`.

## 4. Luồng người dùng
```
Org developer → tạo API key (scope read:questions) → thấy key 1 lần → dùng gọi API
Cấu hình webhook `exam.finished` → nhận sự kiện realtime → xem retry log.
```

## 5. Business Logic
- **Auth API:** Sanctum token / API key (per org/app) với **scope = permission**.
- **Versioning** `/api/v1` (xem `05-api-conventions.md`); deprecation policy.
- **Rate limit** theo key/tier; quota; `429` + headers.
- **Webhook outbound:** ký HMAC secret, retry backoff, dedup theo event id, log giao.
- **Scopes:** giới hạn tài nguyên (read/write theo resource).
- **Docs:** sinh từ OpenAPI (nguồn thật là code/annotation).
- **Enforcement RBAC** như web (server re-check).

## 6. Database
- `api_keys(id, owner_type/id, name, key_hash, scopes JSON, last_used_at, revoked_at, expires_at)`, `api_requests_log(key_id, endpoint, status, ms, created_at)` (aggregate), `webhooks(id, owner_id, url, events JSON, secret, active)`, `webhook_deliveries(webhook_id, event_id, status, attempts, response_code, created_at)`.

## 7. API (meta-endpoints + tổng hợp)
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| CRUD | `/api/v1/developer/keys` | key config | key (reveal once) | Owner/Org Admin |
| CRUD | `/api/v1/developer/webhooks` | webhook | webhook | Owner/Org Admin |
| POST | `/api/v1/developer/webhooks/{id}/test` | — | delivery | Owner |
| GET | `/api/v1/developer/usage` | — | usage stats | Owner |
| GET | `/api/v1/openapi.json` | — | OpenAPI spec | Public/Auth |

> Tổng hợp toàn bộ endpoint hệ thống nằm ở từng module + `05-api-conventions.md`. Module này quản lý **hạ tầng & truy cập** API.

## 8. State Management
- Server: key/scope, rate limit Redis, webhook delivery queue + retry; usage aggregate. Client: portal state. Realtime: log giao webhook.

## 9. Phân quyền
- Developer/Org Admin tạo key trong phạm vi quyền; Admin/Super Admin quản lý toàn cục, tắt key vi phạm. Scope ≤ quyền người tạo.

## 10. Edge Cases
- Key lộ → thu hồi ngay + rotate; webhook endpoint down → retry backoff → tắt sau N lần + cảnh báo; vượt rate limit → 429; scope không đủ → 403; version cũ deprecated → header cảnh báo.

## 11. Tracking (audit + product)
`api_key_create`, `api_key_revoke`, `webhook_create`, `webhook_delivery`, `api_rate_limited`, `docs_open`, `playground_run`.

## 12. Responsive
- Desktop tối ưu (docs/portal); mobile xem docs.

## 13. Security
- Key **hash** (không lưu plaintext), reveal-once; scope tối thiểu; rate limit; HMAC webhook + timestamp chống replay; HTTPS; audit; IP allowlist tùy chọn; chống SSRF khi test webhook (validate URL); revoke tức thì.

## 14. Performance
- Rate limit Redis; webhook async + backoff; usage aggregate; docs tĩnh/cache; OpenAPI cache.

## 15. Đề xuất cải tiến
- OAuth2 client credentials cho đối tác; SDK chính thức (JS/PHP/Python); sandbox môi trường thử; GraphQL tùy chọn; LTI cho LMS; webhook signature versioning; API analytics chi tiết.
