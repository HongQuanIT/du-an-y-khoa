# Thiết kế lại Subscription & Billing

**Ngày:** 2026-08-24  
**Phạm vi:** Module 28 (Subscription) + 29 (Billing) — cá nhân (Organization Phase 2 hoãn)  
**Module code:** `Modules/Billing` (không tách module Subscription)

---

## 1. Hiện trạng (trước Phase 1)

| Lớp | Trạng thái |
|-----|-----------|
| Catalog Plan/SKU + Admin CRUD | ✅ |
| Entitlements + middleware `subscription:` | ✅ |
| Redeem code + Institution license | ✅ |
| Checkout / cổng thanh toán / webhook | ❌ |
| Cancel / renew / upgrade / trial | ❌ |
| Invoice PDF / refund / payment methods | ❌ |

---

## 2. Nguyên tắc thiết kế

1. **Webhook = nguồn sự thật** cho trạng thái tiền (idempotent theo `event_id`).
2. **Giá tính server-side** — không tin FE.
3. **Idempotency-Key** trên mọi thao tác ghi tiền.
4. **Prepaid-first** (phù hợp mùa thi Y khoa VN); recurring auto-debit sau.
5. **Giữ một module Billing**; adapter đa cổng (Strategy).
6. **Không lưu thẻ** (PCI) — redirect/hosted payment.

---

## 3. Kiến trúc

```
Paywall / Pricing → /subscription/upgrade → CheckoutService
  → GatewayAdapter (VNPay | Fake | MoMo…) → redirect
  → Webhook → ProcessBillingWebhookJob → ActivatePurchase → Entitlement cache
  → /billing/confirmation
```

### Gateway contract

```php
interface PaymentGatewayInterface
{
    public function createCheckout(CheckoutRequest $req): CheckoutResult;
    public function verifyWebhook(Request $request): WebhookPayload;
    public function supportsRecurring(): bool;
}
```

---

## 4. Luồng Phase 1 (MVP monetization)

### Mua prepaid

1. User chọn SKU trên `/subscription/upgrade`.
2. `POST` tạo `billing_checkout_sessions` (pending) + `billing_invoices` (open).
3. Redirect cổng (VNPay hoặc Fake ở local).
4. Webhook/IPN verify → payment succeeded → subscription `active` + invoice `paid`.
5. Invalidate Redis entitlement cache; hiển thị confirmation.

### Quy tắc

- Đóng tab sau thanh toán → webhook vẫn kích hoạt.
- Double click → cùng `idempotency_key` trả cùng session.
- Checkout pending quá hạn → job đối soát / expire.

---

## 5. Database (Phase 1)

- `billing_checkout_sessions`
- `billing_payments`
- `billing_webhook_events`
- Extend `billing_subscriptions`: `cancel_at_period_end`, `canceled_at`, `provider`, …
- Extend `billing_invoices`: `checkout_session_id`, `paid_at`, `discount_cents`, …

---

## 6. Cổng thanh toán

| Phase | Cổng | Ghi chú |
|-------|------|---------|
| **1** | VNPay + Fake (local/test) | Prepaid QR/ATM/Visa |
| 2 | MoMo, ZaloPay | Wallet |
| 3 | Stripe | Thẻ quốc tế + recurring |

---

## 7. Lộ trình

### Phase 1 — MVP Monetization ✅ (đang triển khai)

- Migration checkout/payments/webhooks
- Gateway adapter + VNPay + Fake
- CheckoutService + ActivatePurchase
- Webhook idempotent + queue
- UI: upgrade, checkout, confirmation
- Entitlement Redis cache
- PaywallOverlay
- Admin: danh sách payments
- Admin: cấu hình cổng thanh toán (VNPay / Fake / MoMo+ZaloPay credential)
- Tests cơ bản

### Phase 2 — Lifecycle

- Cancel wizard, renewal reminders, extend stack
- Coupon tại checkout, invoice PDF
- Expire job, MoMo, admin redeem CRUD / refund

### Phase 3 — Advanced

- Upgrade/downgrade + proration, trial, Stripe, dunning, QuotaMeter

### Phase 4 — Operations

- Revenue dashboard, e-invoice VN, win-back

---

## 8. Quyết định quan trọng

| Quyết định | Chọn | Lý do |
|-----------|------|-------|
| Tách module Subscription? | Không | Đã merge trong Billing |
| Recurring vs Prepaid | Prepaid-first | VNPay + mùa thi |
| Auto-renew | Manual (Phase 2) | Ít auto-debit VN |
| Coupon vs Redeem | Unify sau (Phase 2) | Phase 1 giữ redeem standalone |
| Entitlement cache | Redis TTL 5 phút | Invalidate on activate |

---

## 9. Env

```env
BILLING_DEFAULT_GATEWAY=fake   # local: fake | production: vnpay
BILLING_CURRENCY=VND
BILLING_TAX_RATE=0
BILLING_CHECKOUT_TTL_MINUTES=60
BILLING_ENTITLEMENT_CACHE_TTL=300

VNPAY_TMN_CODE=
VNPAY_HASH_SECRET=
VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL="${APP_URL}/billing/return/vnpay"

# MoMo / ZaloPay — credential có thể lưu qua Admin; adapter Phase 2
MOMO_PARTNER_CODE=
MOMO_ACCESS_KEY=
MOMO_SECRET_KEY=
MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create
ZALOPAY_APP_ID=
ZALOPAY_KEY1=
ZALOPAY_KEY2=
ZALOPAY_ENDPOINT=https://sb-openapi.zalopay.vn/v2/create
```

Cấu hình runtime ưu tiên **Admin → Cổng thanh toán** (settings DB); env là fallback khi chưa lưu.

---

## 10. Routes Phase 1

| Method | Path | Tên |
|--------|------|-----|
| GET | `/subscription/upgrade` | `subscription.upgrade` |
| POST | `/billing/checkout` | `billing.checkout.store` |
| GET | `/billing/checkout/{uuid}` | `billing.checkout.show` |
| GET | `/billing/confirmation/{uuid}` | `billing.confirmation` |
| GET | `/billing/return/{gateway}` | `billing.return` |
| GET/POST | `/billing/fake-pay/{uuid}` | `billing.fake-pay` (local) |
| POST | `/webhooks/billing/{provider}` | `webhooks.billing` |
| POST | `/api/v1/subscription/checkout` | `api.billing.subscription.checkout` |
| GET | `/admin/billing/payments` | `admin.billing.payments.index` |
| GET | `/admin/billing/gateways` | `admin.billing.gateways.index` |
| PUT | `/admin/billing/gateways` | `admin.billing.gateways.update` |
