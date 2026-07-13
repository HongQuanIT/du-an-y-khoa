# Module 29 — Billing

**Nhóm:** Account · **Ưu tiên:** Cao · **Phụ thuộc:** Subscription (28), cổng thanh toán · **Trạng thái:** ✅

## 0. Tóm tắt module
Xử lý thanh toán qua cổng (Stripe/địa phương VN như VNPay/MoMo/ZaloPay), hóa đơn, phương thức thanh toán, lịch sử giao dịch, hoàn tiền, webhook đối soát. Không lưu số thẻ (token hóa qua cổng).

> 🔵 **Billing theo tổ chức** (hóa đơn org, role Org Admin) thuộc **Phase 2** (Module Organization 32 đã hoãn). Phạm vi hiện tại: billing cá nhân.

| Route | Màn hình |
|-------|----------|
| `/billing` | Tổng quan thanh toán |
| `/billing/checkout` | Trang/redirect thanh toán |
| `/billing/invoices` | Lịch sử hóa đơn |
| `/billing/methods` | Phương thức thanh toán |

## 1. Tổng quan
- **Mục đích:** Thu tiền an toàn & minh bạch, quản lý hóa đơn.
- **Đến từ:** Subscription checkout (28), Settings.
- **Đi sang:** Cổng thanh toán, xác nhận, kích hoạt entitlement.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Checkout summary** | Gói, giá, thuế, coupon, tổng | Checkout | — |
| **Payment method selector** | Thẻ/ví/chuyển khoản | Checkout | Radio |
| **Hosted payment (redirect/iframe)** | Nhập thanh toán an toàn (cổng) | Checkout | — |
| **Invoice list/table** | Hóa đơn + trạng thái + tải PDF | `/invoices` | Table → card |
| **Payment methods** | Thêm/xóa/mặc định | `/methods` | — |
| **Receipt/confirmation** | Xác nhận thành công | Sau thanh toán | — |
| **Empty/Loading/Error** | Chưa có hóa đơn; xử lý; lỗi | Theo trạng thái | — |

## 3. Phân tích Component
- `CheckoutSummary`, `PaymentMethodSelector`, `HostedPaymentFrame`, `InvoiceTable`, `PaymentMethodCard`, `RefundBadge`.
- Không tự render form thẻ (dùng element cổng để giảm phạm vi PCI).

## 4. Luồng người dùng
```
Subscription checkout → Billing tạo intent → redirect/iframe cổng → thanh toán
 → webhook success → tạo invoice paid + kích hoạt subscription/entitlement → confirmation
Thất bại → thông báo + thử lại/đổi phương thức.
Hoàn tiền (admin) → refund → invoice refunded → thu hồi entitlement nếu cần.
```

## 5. Business Logic
- **Payment intent** qua cổng; **webhook là nguồn thật** (idempotent theo event id).
- **Invoice** sinh cho mỗi kỳ; **proration** khi đổi gói.
- **Thuế/VAT** theo cấu hình VN; xuất hóa đơn.
- **Retry dunning** khi thất bại (nhắc, thử lại tự động) → past_due (module 28).
- **Refund** thủ công (admin) hoặc chính sách; ghi audit.
- **Đa cổng** (adapter) để hỗ trợ VNPay/MoMo/Stripe.

## 6. Database
- `invoices`, `payments` (mục 9 data model), `payment_methods(user_id, provider, token, brand, last4, exp, is_default)`, `webhook_events(provider, event_id, type, payload, processed_at)` (idempotency).

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| POST | `/api/v1/billing/checkout` | `{plan_id, method, coupon?}` + Idempotency-Key | intent/redirect | Auth |
| GET | `/api/v1/billing/invoices` | — | list | Owner |
| GET | `/api/v1/billing/invoices/{id}/pdf` | — | signed URL | Owner |
| GET/POST/DELETE | `/api/v1/billing/methods` | — | CRUD | Owner |
| POST | `/api/webhooks/{provider}` | signature | 200 | Cổng (verify) |
| POST | `/api/v1/admin/billing/refund` | `{payment_id, amount}` | refund | Admin |

Lỗi: `402/PAYMENT_FAILED`, `409` duplicate webhook, `422`.

## 8. State Management
- Server: nguồn thật là cổng + webhook; DB đối soát. Client: trạng thái checkout. Realtime: cập nhật sau webhook (notification/refresh). Idempotency chặt.

## 9. Phân quyền
- Owner (cá nhân), Org Admin (tổ chức), Admin/Super Admin (refund, cấu hình cổng, xem toàn bộ — module 33). Audit mọi thao tác tiền.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Webhook trùng/không thứ tự | Idempotent theo event_id; sắp xếp theo timestamp |
| Thanh toán thành công nhưng user đóng tab | Webhook vẫn kích hoạt; hiển thị khi quay lại |
| Double charge | Idempotency-Key; đối soát; refund |
| Cổng down | Thử cổng khác/thử lại; thông báo |
| Chargeback | Thu hồi entitlement + audit |
| Tiền tệ/tỉ giá | Lưu currency + amount_cents |

## 11. Tracking
`checkout_start`, `payment_method_add`, `payment_success`, `payment_failed`, `invoice_download`, `refund_issued`, `dunning_retry`.

## 12. Responsive
- Desktop: summary + method cạnh nhau. Mobile: summary trên, redirect cổng native, invoice card.

## 13. Security
- **PCI**: không lưu thẻ, dùng element/redirect cổng; webhook verify signature; HTTPS; idempotency; audit; mã hóa token; chống thao túng giá (giá tính server); RBAC nghiêm cho refund.

## 14. Performance
- Webhook async; invoice PDF sinh qua queue; đối soát batch; cache plans/tax config.

## 15. Đề xuất cải tiến
- Hỗ trợ ví VN (MoMo/ZaloPay/VNPay) + QR; trả góp; hóa đơn điện tử hợp lệ VN; nhắc gia hạn thông minh; dashboard doanh thu (module 41).
