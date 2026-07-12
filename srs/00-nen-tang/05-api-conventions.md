# 05 — Quy ước API

> Quy ước chung cho toàn bộ REST API. Mỗi module chỉ liệt kê endpoint riêng và tham chiếu về đây cho format, lỗi, phân trang, auth.

## 1. Nguyên tắc

- REST + JSON, versioned: **`/api/v1/...`**.
- Đặt tên tài nguyên số nhiều, snake_case hoặc kebab tùy chuẩn (chọn **kebab-case URL**, snake_case field).
- Idempotent: `GET/PUT/DELETE`; `POST` tạo mới; `PATCH` cập nhật một phần.
- Auth: **Laravel Sanctum** (SPA cookie hoặc token Bearer). Web dùng session; API/mobile dùng token.
- Mọi request có `X-Request-Id` (server sinh nếu thiếu) để trace.

## 2. Header chuẩn

```
Authorization: Bearer <token>        # hoặc cookie session (SPA)
Accept: application/json
Content-Type: application/json
X-Request-Id: <uuid>                  # optional, echo lại
Accept-Language: vi                   # i18n
Idempotency-Key: <uuid>               # cho POST nhạy cảm (thanh toán, submit)
```

## 3. Format response chuẩn

### Thành công (single)
```json
{
  "data": { "id": "01H...", "type": "question", "attributes": { } },
  "meta": { "request_id": "..." }
}
```

### Thành công (list + phân trang)
```json
{
  "data": [ { } ],
  "meta": {
    "pagination": { "page": 1, "per_page": 20, "total": 340, "total_pages": 17 },
    "request_id": "..."
  },
  "links": { "next": "/api/v1/questions?page=2", "prev": null }
}
```

### Lỗi (chuẩn hóa)
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dữ liệu không hợp lệ.",
    "details": [ { "field": "email", "rule": "email", "message": "Email không đúng định dạng." } ],
    "request_id": "..."
  }
}
```

## 4. Mã lỗi & HTTP status

| HTTP | code | Ý nghĩa |
|------|------|---------|
| 400 | BAD_REQUEST | Request sai cú pháp |
| 401 | UNAUTHENTICATED | Chưa đăng nhập / token hết hạn |
| 403 | FORBIDDEN | Không đủ quyền (role/policy) |
| 402/403 | SUBSCRIPTION_REQUIRED | Cần Premium |
| 404 | NOT_FOUND | Không tồn tại / đã xóa |
| 409 | CONFLICT | Xung đột (concurrent update, duplicate) |
| 410 | GONE | Tài nguyên đã bị xóa/retired |
| 422 | VALIDATION_ERROR | Sai validation |
| 423 | LOCKED | Nội dung bị khóa |
| 429 | RATE_LIMITED | Vượt giới hạn (kèm `Retry-After`) |
| 500 | SERVER_ERROR | Lỗi hệ thống |
| 503 | SERVICE_UNAVAILABLE | Bảo trì / phụ thuộc down |

## 5. Phân trang, lọc, sắp xếp, tìm kiếm

- Phân trang: `?page=1&per_page=20` (max per_page = 100). Với feed dài dùng **cursor**: `?cursor=...&limit=20`.
- Lọc: `?filter[status]=published&filter[topic_id]=3`.
- Sắp xếp: `?sort=-created_at,title` (dấu `-` = desc).
- Tìm kiếm: `?q=viêm phổi` (qua Meilisearch cho tài nguyên hỗ trợ).
- Sparse fieldset: `?fields[question]=stem,difficulty`.
- Include quan hệ: `?include=options,topics`.

## 6. Versioning & deprecation

- Breaking change → tăng version (`/api/v2`).
- Field/endpoint deprecate: header `Deprecation: true` + `Sunset: <date>` + doc.

## 7. Rate limiting

| Nhóm | Giới hạn gợi ý |
|------|----------------|
| Auth (login/otp) | 5–10 req/phút/IP |
| Đọc chung | 120 req/phút/user |
| Ghi (submit answer) | 60 req/phút/user |
| AI Assistant | quota theo gói (vd 20/ngày free) |
| Export/report | 5 req/phút/user |

Vượt giới hạn → `429` + `Retry-After` + `X-RateLimit-Remaining`.

## 8. Idempotency & concurrency

- `POST` nhạy cảm (submit answer, thanh toán, tạo session) nhận `Idempotency-Key`; server lưu kết quả theo key trong TTL để chống double-submit.
- Cập nhật đồng thời dùng **optimistic locking**: field `version`/`updated_at`; sai → `409 CONFLICT`.

## 9. Webhook & realtime

- Webhook (billing provider) tại `/api/webhooks/{provider}` — verify signature, idempotent theo event id.
- Realtime qua Reverb: client subscribe kênh private/presence; payload theo format `{ event, data }`.

## 10. Bảo mật API

- HTTPS bắt buộc; HSTS.
- Sanctum token có ability (scope) khớp permission.
- Validate + sanitize mọi input; whitelisting field.
- CORS cấu hình domain tin cậy; CSRF cho web session.
- Không trả field nhạy cảm; dùng API Resource (transformer).

## 11. Danh sách nhóm endpoint (tổng quan)

```
/api/v1/auth/*            đăng nhập, đăng ký, refresh, logout, oauth
/api/v1/me                profile hiện tại, settings
/api/v1/questions         qbank: list/filter/detail
/api/v1/sessions          tạo/nộp/review session
/api/v1/attempts          lịch sử attempt
/api/v1/study-plan        lộ trình học
/api/v1/library           articles, diseases, drugs, procedures
/api/v1/media             upload/serve media
/api/v1/notes|bookmarks|highlights|flashcards
/api/v1/analytics         stats, weak-topics, heatmap
/api/v1/exams             đề thi, self-assessment
/api/v1/search            global search
/api/v1/notifications
/api/v1/billing           plans, subscription, invoices
/api/v1/ai                assistant/tutor
/api/v1/admin/*           quản trị (RBAC nghiêm ngặt)
/api/webhooks/*           webhook bên thứ 3
```

Chi tiết payload/response từng nhóm nằm trong file module tương ứng.
