# Module 01 — Landing Page

**Nhóm:** Public · **Ưu tiên:** Cao · **Phụ thuộc:** Auth (02), Subscription (28) · **Trạng thái:** ✅

## 0. Tóm tắt module
Trang công khai giới thiệu sản phẩm cho Guest, tối ưu chuyển đổi (đăng ký/dùng thử/nâng cấp) và SEO. Không yêu cầu đăng nhập.

| Route | Màn hình | Quyền |
|-------|----------|-------|
| `/` | Home/Hero | Guest & mọi role |
| `/features` | Trang tính năng | Public |
| `/pricing` | Bảng giá | Public |
| `/qbank-preview` | Preview câu hỏi mẫu | Public (giới hạn) |
| `/about`, `/blog`, `/contact` | Trang tĩnh/CMS | Public |
| `/faq` | Câu hỏi thường gặp | Public |

Không bao gồm: nội dung học thật (thuộc module core), thanh toán (module 29).

## 1. Tổng quan từng màn hình

### 1.1 Home (`/`)
- **Mục đích:** Truyền tải giá trị cốt lõi, dẫn tới đăng ký/dùng thử.
- **Khi dùng:** Lần đầu biết đến sản phẩm; điểm vào từ quảng cáo/SEO.
- **Đến từ:** Trực tiếp, search engine, ad, link chia sẻ.
- **Đi sang:** `/register`, `/login`, `/pricing`, `/qbank-preview`, `/features`.

### 1.2 Pricing (`/pricing`)
- **Mục đích:** So sánh gói Free/Premium/Org, CTA nâng cấp.
- **Đi sang:** `/register?plan=...`, checkout (module 29).

### 1.3 Qbank Preview (`/qbank-preview`)
- **Mục đích:** Cho Guest nếm thử 3–5 câu hỏi + giao diện làm bài, tạo "aha moment".
- **Đi sang:** Paywall → `/register`.

## 2. Phân tích giao diện

| Thành phần | Chức năng | Hiển thị | Ẩn | Điều kiện | Responsive |
|-----------|-----------|----------|-----|-----------|-----------|
| **Header (public)** | Logo, menu (Features/Pricing/Blog), nút Login/Đăng ký, chọn ngôn ngữ | Luôn | — | Sticky khi cuộn | Mobile: hamburger drawer |
| **Hero** | Tiêu đề + subtitle + CTA "Bắt đầu luyện thi" + ảnh/animation | Luôn | — | — | Mobile: 1 cột, ảnh dưới |
| **Value Props (3 cột)** | "Học thông minh / Thi hiệu quả / AI hỗ trợ" | Luôn | — | — | Mobile: stack dọc |
| **Feature sections** | Mô tả Qbank, Library, AI, Analytics kèm ảnh | Luôn | — | — | Xen kẽ trái/phải → stack |
| **Qbank preview widget** | Câu hỏi mẫu tương tác | Home + `/qbank-preview` | — | Guest | Full-width mobile |
| **Social proof** | Số liệu (số câu hỏi, người dùng), testimonial | Luôn | Nếu chưa có dữ liệu → ẩn | — | Carousel mobile |
| **Pricing teaser** | Tóm tắt gói + link `/pricing` | Home | — | — | Cột → stack |
| **FAQ accordion** | Câu hỏi thường gặp | Home/faq | — | — | Full-width |
| **CTA band** | Dải kêu gọi đăng ký cuối trang | Luôn | — | — | — |
| **Footer** | Link pháp lý, mạng xã hội, ngôn ngữ, newsletter | Luôn | — | — | Cột → accordion |
| **Cookie/consent banner** | Đồng ý cookie/analytics | Lần đầu | Sau khi chọn | Chưa có consent | Bottom sheet mobile |
| **Toast** | Thông báo (vd newsletter subscribed) | Sau action | Tự ẩn | — | — |
| **Loading state** | Skeleton hero/preview | Khi tải | Sau tải | — | — |
| **Empty state** | Blog chưa có bài → "Sắp ra mắt" | Khi rỗng | — | — | — |
| **Error state** | Lỗi tải preview → nút thử lại | Khi lỗi | — | — | — |

## 3. Phân tích Component

### `PublicHeader`
- **Mục đích:** Điều hướng + CTA đăng nhập/đăng ký.
- **Props:** `currentUser?`, `locale`, `transparent(bool)`.
- **State:** `mobileMenuOpen`, `scrolled`.
- **Events:** `onLoginClick`, `onRegisterClick`, `onLocaleChange`.
- **Permission:** Nếu đã đăng nhập → hiện avatar + "Vào học" (link Dashboard) thay vì Login.
- **Accessibility:** `nav` landmark, focus trap trong drawer, ARIA expanded.
- **Animation:** Chuyển nền khi scroll (transparent → solid).
- **Loading/Error/Empty/Disabled:** N/A (tĩnh).

### `QbankPreviewCard`
- **Mục đích:** Cho Guest thử trả lời câu hỏi mẫu.
- **Props:** `question`, `remainingFree(int)`.
- **State:** `selectedOption`, `submitted`, `showExplanation`.
- **Events:** `onSelect`, `onSubmit`, `onNext`, `onPaywallHit`.
- **Validation:** Phải chọn đáp án trước khi submit.
- **Permission:** Guest giới hạn N câu; hết → `PaywallOverlay`.
- **Accessibility:** Radio group ARIA, keyboard chọn A–E.
- **Animation:** Reveal giải thích, tô màu đúng/sai.
- **Loading:** Skeleton câu hỏi. **Error:** thử lại. **Empty:** N/A. **Disabled:** nút Submit khi chưa chọn.

### `PricingTable`
- **Props:** `plans[]`, `billingInterval(month/year)`, `highlightPlan`.
- **State:** `interval toggle`.
- **Events:** `onSelectPlan`, `onToggleInterval`.
- **A11y:** bảng có scope header; nút CTA rõ ràng.

### `CookieConsentBanner`, `LanguageSwitcher`, `NewsletterForm` (validation email), `FAQAccordion` (ARIA disclosure).

## 4. Luồng người dùng
```
Guest vào "/" 
 → xem Hero/Value props 
 → (a) bấm "Bắt đầu" → /register 
 → (b) thử Qbank preview → trả lời N câu → hết quota → Paywall → /register?plan=free
 → (c) xem /pricing → chọn gói → /register?plan=premium → checkout (module 29)
Ngoại lệ:
 - Đã đăng nhập vào "/" → hiển thị CTA "Vào học" (Dashboard).
 - Lỗi tải preview → error state, không chặn phần còn lại.
 - Từ chối cookie → tắt analytics không thiết yếu.
```

## 5. Business Logic
- **Điều kiện hiển thị:** Nếu user đã đăng nhập, header đổi CTA; có thể redirect `/` → `/dashboard` (tùy cấu hình A/B).
- **Preview free:** Giới hạn số câu theo IP/cookie (chống lạm dụng), reset theo ngày.
- **Premium teaser:** Lấy giá từ module Subscription (Plan).
- **A/B testing:** Hero copy, CTA màu, pricing layout — qua feature flag.
- **SEO:** SSR, meta/OpenGraph, sitemap, schema.org (Course/Organization), i18n hreflang.

## 6. Database
- Chủ yếu đọc: `plans` (module 28), nội dung CMS (`Article type=general`, module 42), `settings` (site config).
- Preview dùng vài `questions` `is_free=true`.
- `newsletter_subscribers(id, email, locale, source, confirmed_at, created_at)`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/public/pricing` | — | plans + features | Public |
| GET | `/api/v1/public/qbank-preview` | `?limit` | câu hỏi free (không lộ đáp án trước submit) | Public, rate-limited |
| POST | `/api/v1/public/qbank-preview/answer` | `{question_id, option_id}` | `{is_correct, explanation, remaining}` | Public, rate-limited |
| POST | `/api/v1/public/newsletter` | `{email}` | 202 | Public, throttle |
| GET | `/api/v1/public/content/{slug}` | — | trang CMS | Public |

Lỗi: `429` khi spam preview/newsletter; `VALIDATION_ERROR` email sai.

## 8. State Management
- Chủ yếu **server-rendered**; Alpine cho menu/accordion/preview.
- Preview quota lưu cookie + xác thực server (Redis theo IP).
- Không cần realtime.

## 9. Phân quyền
- Public toàn bộ. Đã đăng nhập: header cá nhân hóa, ẩn "Đăng ký".

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Không internet | Trang đã tải vẫn xem được (static); preview submit báo lỗi mạng |
| Preview hết quota | Paywall, mời đăng ký |
| CMS trang trống | Empty "Sắp ra mắt" |
| API pricing lỗi | Fallback giá tĩnh cache |
| Bot scraping preview | Rate limit + captcha |

## 11. Tracking
`landing_view`, `hero_cta_click`, `pricing_view`, `plan_select`, `qbank_preview_open`, `question_answer(preview=true)`, `paywall_view`, `signup_start`, `newsletter_subscribe`, `faq_open`.

## 12. Responsive
- **Desktop:** hero 2 cột, feature xen kẽ, menu ngang.
- **Tablet:** hero 2 cột hẹp, feature 2 cột.
- **Mobile:** 1 cột, hamburger drawer, CTA sticky đáy, carousel testimonial/pricing.

## 13. Security
- Public nên: chống spam form (captcha/honeypot), rate limit preview/newsletter, không lộ đáp án câu preview qua API trước khi submit, sanitize nội dung CMS.

## 14. Performance
- SSR + cache trang toàn phần (CDN); ảnh webp/lazy; critical CSS inline; defer JS; LCP < 2.5s.
- Preview câu hỏi cache; pricing cache Redis.

## 15. Đề xuất cải tiến
- Interactive demo dài hơn (mini-session 5 câu có scoreboard) để tăng conversion.
- Cá nhân hóa landing theo nguồn traffic (chuyên ngành).
- Social proof động (số người đang học realtime).
- Exit-intent popup ưu đãi dùng thử.
- Video giới thiệu 60s + testimonial video.
