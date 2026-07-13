# Module 28 — Subscription

**Nhóm:** Account · **Ưu tiên:** Rất cao (doanh thu) · **Phụ thuộc:** Billing (29), RBAC (nền tảng), Landing (01) · **Trạng thái:** ✅

## 0. Tóm tắt module
Quản lý gói & quyền lợi (entitlements): xem gói, nâng cấp/hạ cấp, dùng thử, hủy, gia hạn, coupon. Quyết định gating Premium toàn hệ thống.

> 🔵 **Subscription theo tổ chức** (org subscription, quản lý ghế/seats, role Org Admin) thuộc **Phase 2** (Module Organization 32 đã hoãn). Phạm vi hiện tại: subscription cá nhân.

| Route | Màn hình |
|-------|----------|
| `/pricing` | Bảng giá (public, module 01) |
| `/subscription` | Gói hiện tại + quản lý |
| `/subscription/upgrade` | Chọn gói + checkout |
| `/subscription/cancel` | Luồng hủy (retention) |

## 1. Tổng quan
- **Mục đích:** Bán & quản lý quyền truy cập Premium.
- **Đến từ:** Paywall khắp hệ thống, Landing, Settings.
- **Đi sang:** Billing checkout (29), quay lại tính năng đã mở khóa.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Current plan card** | Gói, trạng thái, ngày gia hạn, quyền lợi | `/subscription` | — |
| **Plan comparison** | So sánh Free/Premium/Org | Upgrade | Table → cột stack |
| **Interval toggle** | Tháng/năm (hiển thị tiết kiệm) | Upgrade | — |
| **Coupon field** | Nhập mã giảm giá | Checkout | — |
| **Trial badge** | Đang dùng thử + còn lại | Nếu trialing | — |
| **Cancel flow** | Lý do hủy + ưu đãi giữ chân | `/cancel` | Wizard |
| **Paywall component** | Overlay khóa (dùng lại toàn hệ thống) | Free ở nội dung khóa | — |
| **Quota meter** | Lượt AI/câu còn lại | Free | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `PlanComparison`, `PaywallOverlay` (dùng lại), `UpgradeBadge`, `QuotaMeter`, `CouponInput`(validate), `CancelWizard`, `CurrentPlanCard`.
- `PaywallOverlay`: props `feature`, `requiredEntitlement`; CTA upgrade; tracking `paywall_view`.

## 4. Luồng người dùng
```
Nội dung khóa → PaywallOverlay → /subscription/upgrade → chọn gói/năm → coupon → checkout (29)
 → thanh toán thành công → entitlement bật ngay → về nội dung
Hủy: /cancel → lý do → ưu đãi giữ → xác nhận → giữ quyền tới hết kỳ.
Trial: bắt đầu dùng thử → nhắc trước khi hết → auto chuyển trả phí/hạ cấp.
```

## 5. Business Logic
- **Entitlements** (xem `03-phan-quyen-rbac.md` mục 5) map từ Plan; server enforce mọi nơi.
- **Trạng thái:** trialing/active/past_due/canceled/expired; grace period khi past_due.
- **Upgrade/downgrade:** proration (tính bù trừ) qua Billing.
- **Cancel:** giữ quyền tới hết kỳ (không thu hồi ngay).
- **Coupon:** validate hạn/max redemptions/điều kiện.
- **Quota Free:** đếm (AI, câu/ngày) Redis; reset theo ngày.
- **Org subscription:** ghế (seats) → thành viên nhận entitlement.
- **Webhook billing** cập nhật trạng thái (nguồn thật là provider) — module 29.

## 6. Database
- `plans`, `subscriptions`, `coupons` (mục 9 data model); `entitlement_cache(user_id, entitlements JSON, updated_at)` (đồng bộ từ subscription).

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/plans` | — | gói + entitlements | Public |
| GET | `/api/v1/subscription` | — | subscription hiện tại | Owner |
| POST | `/api/v1/subscription/checkout` | `{plan_id, interval, coupon?}` + Idempotency-Key | checkout session (→29) | Auth |
| POST | `/api/v1/subscription/change` | `{plan_id}` | proration preview/confirm | Owner |
| POST | `/api/v1/subscription/cancel` | `{reason}` | subscription | Owner |
| POST | `/api/v1/coupons/validate` | `{code, plan_id}` | discount | Auth |

Lỗi: `409`(đã có sub active), `422`(coupon), `SUBSCRIPTION_REQUIRED`.

## 8. State Management
- Server: subscription/entitlement (nguồn provider + DB); entitlement cache Redis (invalidations qua webhook). Client: chọn gói. Realtime: cập nhật entitlement sau thanh toán (đẩy qua notification/refresh).

## 9. Phân quyền
- Owner quản lý sub cá nhân; Org Admin quản lý sub tổ chức (ghế); Admin/Super Admin cấu hình plan/coupon (module 33/29).

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Thanh toán thất bại | past_due + grace + nhắc; thu hồi khi hết grace |
| Webhook trễ | Trạng thái pending, đối soát; idempotent theo event id |
| Hủy rồi mua lại | Reactivate |
| Coupon hết hạn | 422 |
| Downgrade | Giữ quyền tới hết kỳ, entitlement giảm sau |
| Trùng subscribe (double click) | Idempotency-Key |
| Org hết ghế | Chặn thêm thành viên Premium |

## 11. Tracking
`paywall_view`, `upgrade_click`, `plan_select`, `checkout_start`, `coupon_apply`, `subscription_upgrade`, `subscription_downgrade`, `subscription_cancel`, `trial_start`, `trial_end`.

## 12. Responsive
- Desktop: bảng so sánh cột. Mobile: cột stack, gói nổi bật trước, sticky CTA.

## 13. Security
- Entitlement enforce server-side (không tin FE); webhook verify signature; không lưu thẻ (cổng); idempotency; audit thay đổi gói.

## 14. Performance
- Entitlement cache Redis (đọc cực nhiều); plans cache; webhook xử lý async.

## 15. Đề xuất cải tiến
- Gói theo mùa thi (ngắn hạn); giá địa phương hóa (VN); nhóm bạn học (group discount); dùng thử không cần thẻ; win-back campaign tự động.
