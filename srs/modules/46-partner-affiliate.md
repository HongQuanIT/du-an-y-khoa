# Module 46 — Partner (CTV / chia sẻ doanh thu)

**Nhóm:** Account / Growth · **Ưu tiên:** Cao · **Phụ thuộc:** Auth (02), Billing (29), Subscription (28), RBAC · **Trạng thái:** ✅

## 0. Tóm tắt module
Quản lý cộng tác viên (CTV): Admin tạo tài khoản CTV và **cấp mã/link mời** (hiệu lực, hết hạn, lượt dùng, % hoa hồng); CTV dùng portal `/partner` để xem/copy mã, xem người đăng ký & gói đang dùng; hệ thống ghi hoa hồng từ thanh toán thành công và Admin đối soát chi trả.

| Route | Màn hình |
|-------|----------|
| `/partner/login` | Đăng nhập CTV |
| `/partner` | Dashboard CTV |
| `/partner/codes` | Xem/copy mã-link (read-only) |
| `/partner/referrals` | Danh sách người được mời |
| `/partner/commissions` | Hoa hồng |
| `/partner/payouts` | Kỳ chi trả (read-only) |
| `/admin/partners` | CRUD CTV + mã mời |
| `/admin/partners-payouts` | Đối soát chi trả |

**Ngoài phạm vi:** coupon giảm giá checkout, CTV tự đăng ký public, MLM, chuyển khoản tự động.

## 1. Tổng quan
- **Mục đích:** Attribution first-touch + chia sẻ doanh thu minh bạch.
- **Đến từ:** Admin tạo CTV; CTV chia link; user đăng ký → mua Premium.
- **Đi sang:** Billing payment success → commission; Admin payout.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn |
|-----------|-----------|-------------|
| Dashboard KPI | #referral, pending/paid commission | `/partner` |
| Code table (RO) | Xem mã, hiệu lực, copy link | `/partner/codes` |
| Referrals table | Tên, email, gói, ĐK | `/partner/referrals` |
| Commissions table | Gross, %, hoa hồng, status | `/partner/commissions` |
| Admin partner CRUD | Tạo user/role, % mặc định, suspend | `/admin/partners` |
| Admin invite codes | Tạo/sửa mã: starts/expires/max_uses/% | `/admin/partners/{id}` |
| Admin payouts | Tạo kỳ, approve, mark paid | `/admin/partners-payouts` |

## 3. Phân tích Component
- `PartnerSettings` (typed accessors), `PartnerInviteIntent` (session + cookie), Admin invite form, referral/commission tables.
- Link công khai: `/register?ref={CODE}`.

## 4. Luồng người dùng
```
Admin cấu hình partner.* (window 7 ngày, % mặc định, hạn mã…)
 → Admin tạo CTV (+ % mặc định từ settings)
 → Admin tạo mã gắn CTV (expires/max_uses mặc định từ settings nếu trống)
 → User click link → cookie TTL = attribution_window_days
 → User ĐK trong cửa sổ → partner_attributions
 → Thanh toán Premium (theo gate renew/window) → partner_commissions
 → Admin payout (≥ min_payout_cents)
```

## 5. Business Logic
- **Attribution window (A):** click `?ref=` → giữ mã bằng session + cookie HttpOnly `partner_invite_ref` trong `partner.attribution_window_days` (mặc định 7). Đăng ký trong cửa sổ đó mới tạo `partner_attributions` (first-touch DB).
- **First-touch / last-touch:** `partner.overwrite_attribution=false` giữ mã click trước; `true` cho phép mã mới ghi đè.
- **Hiệu lực mã:** `is_active` + `starts_at`/`expires_at`/`max_uses`; khi Admin để trống expires/max_uses lúc tạo mã → áp `partner.default_invite_expires_days` / `default_invite_max_uses`.
- **% mặc định CTV:** `partner.default_commission_rate_percent` khi tạo CTV (có thể override từng CTV/mã).
- **Hoa hồng:** `rate_bps` × `payment.amount_cents`; idempotent `payment_id`. Gate: `commission_on_renewals`, `first_payment_window_days`, `require_active_partner`, chặn self-referral nếu `allow_self_referral=false`.
- **Payout:** pending → approved → paid; chặn nếu tổng &lt; `min_payout_cents`.

## 5.1 System settings (`partner.*`)
| Key | Default | Mô tả |
|-----|---------|--------|
| `attribution_window_days` | 7 | Cookie/session giữ ref tới lúc ĐK |
| `default_commission_rate_percent` | 10 | % mặc định CTV mới |
| `default_invite_expires_days` | 7 | Auto `expires_at` khi tạo mã (0 = không) |
| `default_invite_max_uses` | 0 | Auto `max_uses` (0 = không giới hạn) |
| `commission_on_renewals` | true | HH khi renew |
| `first_payment_window_days` | 0 | Chỉ HH trong N ngày sau ĐK (0 = không giới hạn) |
| `allow_self_referral` | false | CTV tự mời mình |
| `min_payout_cents` | 0 | Ngưỡng chi trả (xu) |
| `overwrite_attribution` | false | Last-touch nếu true |
| `require_active_partner` | true | CTV suspended → không attribute/commission mới |

UI: `/admin/settings` tab **Cộng tác viên**. Seed upsert: `PartnerSettingsSeeder`.

## 6. Database
`partners`, `partner_invite_codes`, `partner_attributions`, `partner_commissions`, `partner_payouts` — xem `04-mo-hinh-du-lieu.md` §10.

## 7. API
Web Blade là chính cho portal. Không bắt buộc API v1 Phase 1.

## 8. State Management
Server-side Eloquent; session + encrypted cookie cho `PartnerInviteIntent`; settings cache `settings.all`.

## 9. Phân quyền
| Role | Quyền |
|------|-------|
| `partner` | portal, codes.view (RO), referrals.view, commissions.view |
| `admin` / `super_admin` | admin.partners.manage (gồm CRUD mã), admin.partners.payouts; `system.manage` cho settings |

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Click rồi ĐK sau cửa sổ attribution | Không gắn CTV |
| Mã hết hạn / đạt max | Bỏ qua attribution |
| Đã attribution | Không gắn lại |
| Webhook trùng | Unique `payment_id` |
| Partner suspended + require_active | Không attribute mới / không commission mới |
| Self-referral | Bỏ attribution |
| Renew tắt | Chỉ HH payment đầu |
| Payout dưới min | 422 |
| CTV login learner portal | Redirect `/partner/login` |

## 11. Tracking
`partner_invite_capture`, `partner_attribution_created`, `partner_commission_created`, `partner_payout_paid`.

## 12. Responsive
Portal mirror `/teach`: sidebar desktop, drawer mobile; bảng → scroll ngang.

## 13. Security
Portal middleware `partner`; Admin permission; không lộ payment method/invoice đầy đủ cho CTV; code unique uppercase; rate-limit login.

## 14. Performance
Index `code`, `referred_user_id`, `(partner_id, status)`, `payment_id`; commission ghi sync trong transaction webhook (nhẹ).

## 15. Đề xuất cải tiến
Dashboard funnel click→ĐK→pay; export CSV kỳ; thông báo CTV khi có HH mới; IP/device fingerprint chống fraud.
