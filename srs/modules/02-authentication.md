# Module 02 — Authentication

**Nhóm:** Core · **Ưu tiên:** Rất cao · **Phụ thuộc:** User Profile (30), Notification (27), Security (nền tảng) · **Trạng thái:** ✅

## 0. Tóm tắt module
Đăng ký, đăng nhập (Email + Google/Facebook/Apple), xác thực email, quên/đặt lại mật khẩu, 2FA, quản lý phiên/thiết bị, onboarding. Là cổng vào toàn hệ thống.

| Route | Màn hình |
|-------|----------|
| `/register` | Đăng ký |
| `/login` | Đăng nhập |
| `/verify-email` | Xác thực email |
| `/forgot-password` | Quên mật khẩu |
| `/reset-password/{token}` | Đặt lại mật khẩu |
| `/2fa/challenge` | Nhập mã 2FA |
| `/2fa/setup` | Bật 2FA |
| `/onboarding` | Wizard thiết lập ban đầu |
| `/oauth/{provider}/callback` | Callback OAuth |

## 1. Tổng quan từng màn hình

### 1.1 Đăng ký (`/register`)
- **Mục đích:** Tạo tài khoản mới (email hoặc OAuth).
- **Đến từ:** Landing CTA, Paywall, `/pricing?plan=`.
- **Đi sang:** `/verify-email` (email) hoặc `/onboarding` (OAuth đã verified).

### 1.2 Đăng nhập (`/login`)
- **Mục đích:** Truy cập tài khoản.
- **Đến từ:** Header, redirect khi truy cập route cần auth.
- **Đi sang:** Dashboard, hoặc `/2fa/challenge` nếu bật 2FA, hoặc URL đích trước đó (intended).

### 1.3 Onboarding wizard (`/onboarding`)
- **Mục đích:** Thu thập mục tiêu học (kỳ thi, ngày thi, chuyên ngành quan tâm) để cá nhân hóa.
- **Đến từ:** Sau đăng ký thành công lần đầu.
- **Đi sang:** Dashboard / gợi ý tạo Study Plan.

## 2. Phân tích giao diện

| Thành phần | Chức năng | Hiển thị | Ẩn | Điều kiện | Responsive |
|-----------|-----------|----------|-----|-----------|-----------|
| **Auth layout** | Khung 2 cột (form + ảnh/branding) | Mọi trang auth | — | — | Mobile: 1 cột, ẩn cột ảnh |
| **Form đăng ký** | Name, email, password, confirm, checkbox điều khoản | `/register` | — | — | Full-width mobile |
| **OAuth buttons** | Google/Facebook/Apple | Register + Login | Nếu tắt provider | Feature flag | Stack |
| **Form đăng nhập** | Email, password, remember me | `/login` | — | — | — |
| **Password strength meter** | Đánh giá độ mạnh | Khi nhập password | — | Realtime | — |
| **OTP/2FA input** | 6 ô nhập mã | `/2fa/challenge` | — | User bật 2FA | — |
| **Wizard stepper** | Tiến trình onboarding (bước 1/3) | `/onboarding` | — | — | Ngang → dọc |
| **Toast/Alert** | Thông báo lỗi/thành công | Sau action | Tự ẩn | — | — |
| **Loading state** | Spinner nút submit | Khi gọi API | Sau xong | — | — |
| **Error state** | Lỗi field inline + banner chung | Khi lỗi | — | — | — |
| **Link phụ** | "Quên mật khẩu", "Chưa có tài khoản?" | Login/Register | — | — | — |
| **Resend banner** | Gửi lại email xác thực (đếm ngược) | `/verify-email` | Trong cooldown | — | — |

## 3. Phân tích Component

### `AuthForm` (đăng nhập/đăng ký dùng chung có biến thể)
- **Props:** `mode(login/register)`, `providers[]`, `intendedUrl?`, `plan?`.
- **State:** `email`, `password`, `errors`, `submitting`, `showPassword`.
- **Events:** `onSubmit`, `onOAuthClick`, `onTogglePassword`.
- **Validation:** email format, password ≥ 8 ký tự (+ chữ hoa/số/ký tự đặc biệt khuyến nghị), confirm khớp, bắt buộc tick điều khoản (register).
- **Permission:** Guest only; nếu đã đăng nhập → redirect.
- **Accessibility:** label liên kết input, `aria-invalid`, thông báo lỗi qua `aria-live`, autocomplete tokens (`email`, `current-password`, `new-password`).
- **Animation:** shake nhẹ khi lỗi; transition show/hide password.
- **Loading:** nút disabled + spinner. **Error:** inline + banner. **Disabled:** submit khi form invalid.

### `OtpInput` (2FA)
- **Props:** `length=6`, `onComplete`.
- **State:** mảng ký tự, focus index.
- **Events:** auto-advance, paste-fill, resend.
- **A11y:** mỗi ô có label ẩn; `inputmode=numeric`.

### `OnboardingWizard`
- **Props:** `steps[]`, `initialData`.
- **State:** `currentStep`, `formData`, `canProceed`.
- **Events:** `onNext`, `onBack`, `onSkip`, `onFinish`.
- **Validation:** mỗi bước có rule riêng (chọn kỳ thi, ngày thi tương lai).
- **A11y:** stepper role, focus chuyển đầu bước.

### `PasswordStrengthMeter`, `SocialButton`, `ResendCountdownButton`.

## 4. Luồng người dùng
```
ĐĂNG KÝ EMAIL:
 /register → nhập form → submit → tạo user (status=pending) → gửi email verify
   → /verify-email → click link → email_verified_at set → /onboarding → Dashboard

ĐĂNG KÝ OAUTH:
 /register → chọn Google → OAuth consent → callback
   → nếu email tồn tại: liên kết account / cảnh báo → login
   → nếu mới: tạo user (verified) → /onboarding

ĐĂNG NHẬP:
 /login → submit → (2FA bật? → /2fa/challenge) → set session → intended URL/Dashboard

QUÊN MẬT KHẨU:
 /forgot-password → nhập email → gửi link (luôn báo "đã gửi nếu tồn tại")
   → /reset-password/{token} → đặt mật khẩu mới → login

Ngoại lệ:
 - Sai mật khẩu nhiều lần → khóa tạm + captcha.
 - Token verify/reset hết hạn → nút gửi lại.
 - OAuth từ chối quyền → quay lại /login kèm thông báo.
 - Email chưa verify khi login → chặn + nhắc verify.
 - Tài khoản suspended/banned → thông báo, chặn.
```

## 5. Business Logic
- **Trạng thái user:** pending (chưa verify) → active → suspended/banned.
- **Verify bắt buộc** trước khi dùng tính năng học (tùy cấu hình: cho vào nhưng banner nhắc).
- **2FA:** bắt buộc với admin/org_admin; tùy chọn với student.
- **Liên kết OAuth ↔ email:** nếu email trùng, yêu cầu xác nhận để merge (chống chiếm tài khoản).
- **Remember me:** token dài hạn xoay vòng.
- **Rate limit & lockout:** 5 lần sai/15 phút → lockout tăng dần.
- **Intended URL:** lưu để redirect sau login (chống open redirect: chỉ nội bộ).
- **Onboarding data** → ghi vào `users.exam_target_date`, `meta`, tạo gợi ý Study Plan.

## 6. Database
- `users`, `oauth_accounts`, `devices/sessions`, `password_reset_tokens(email, token_hash, created_at)`, `two_factor_secrets(user_id, secret_enc, recovery_codes_enc, confirmed_at)`.
- Tham chiếu `04-mo-hinh-du-lieu.md` mục 2.

## 7. API
| Method | URL | Payload | Response | Lỗi | Quyền |
|--------|-----|---------|----------|-----|-------|
| POST | `/api/v1/auth/register` | `{name,email,password}` | user + verify sent | 422, 409(email tồn tại) | Guest |
| POST | `/api/v1/auth/login` | `{email,password,remember}` | token/session hoặc `2fa_required` | 401,422,423(locked),429 | Guest |
| POST | `/api/v1/auth/2fa/verify` | `{code}` | token | 401,422 | Pending-2fa |
| POST | `/api/v1/auth/logout` | — | 204 | 401 | Auth |
| POST | `/api/v1/auth/forgot-password` | `{email}` | 202 (luôn) | 429 | Guest |
| POST | `/api/v1/auth/reset-password` | `{token,email,password}` | 200 | 422,410 | Guest |
| POST | `/api/v1/auth/email/verify/{id}/{hash}` | signed | 200 | 403,410 | Auth pending |
| POST | `/api/v1/auth/email/resend` | — | 202 | 429 | Auth pending |
| GET | `/api/v1/auth/oauth/{provider}/redirect` | — | redirect | — | Guest |
| GET | `/api/v1/auth/oauth/{provider}/callback` | code | session | 401 | Guest |
| GET/DELETE | `/api/v1/auth/sessions` | — | list/thu hồi thiết bị | 401 | Auth |
| POST | `/api/v1/onboarding` | `{exam, target_date, specialties[]}` | 200 | 422 | Auth |

## 8. State Management
- **Client:** trạng thái form (Alpine), countdown resend, OTP.
- **Server:** session (Redis), token Sanctum, rate limiter Redis.
- **Caching:** không cache dữ liệu nhạy cảm; cache danh sách provider bật.
- **Realtime:** không (trừ cảnh báo login lạ qua notification).

## 9. Phân quyền
- Guest: dùng toàn bộ auth. Đã đăng nhập truy cập `/login` → redirect Dashboard. 2FA/quản lý phiên: mọi role đã auth.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Không internet | Form báo lỗi mạng, giữ dữ liệu nhập |
| Session hết hạn | Redirect `/login?intended=` |
| Token verify/reset hết hạn | `410`, nút gửi lại |
| Duplicate submit đăng ký | Idempotency-Key + unique email → `409` |
| Refresh giữa OAuth | State/nonce chống CSRF; hết hạn → làm lại |
| Back sau logout | Xóa session; trang cache không lộ dữ liệu (no-store) |
| Concurrent login nhiều thiết bị | Cho phép; hiện danh sách thiết bị; giới hạn nếu cấu hình |
| Timeout gọi OAuth provider | Thông báo thử lại |

## 11. Tracking
`signup_start`, `signup_success`, `login_success`, `login_failed`, `oauth_connect`, `logout`, `password_reset_request`, `password_reset_success`, `email_verify_success`, `2fa_enabled`, `onboarding_start`, `onboarding_complete`.

## 12. Responsive
- Desktop: 2 cột (branding + form). Tablet: form giữa, ẩn ảnh lớn. Mobile: full-width, bàn phím numeric cho OTP, nút lớn dễ chạm.

## 13. Security
- Băm mật khẩu (argon2id), chống brute-force (rate limit + lockout + captcha), token verify/reset **hash + hết hạn + single-use**.
- OAuth state/nonce chống CSRF; verify email provider trả về.
- Chống user enumeration (forgot-password luôn trả 202).
- 2FA TOTP + recovery codes mã hóa. HttpOnly/SameSite cookie. Cảnh báo đăng nhập thiết bị mới (email + notification).
- CSRF cho form web; no-store cho trang auth.

## 14. Performance
- Trang auth nhẹ, ít JS; gửi email/notification qua queue; rate limiter Redis; cache provider config.

## 15. Đề xuất cải tiến
- **Magic link / passwordless** + OTP qua email.
- **Passkeys (WebAuthn)** thay mật khẩu.
- Social proof onboarding (đề xuất lộ trình theo cohort cùng kỳ thi).
- Progressive profiling (hỏi dần thay vì wizard dài).
- Phát hiện đăng nhập bất thường bằng device fingerprint + geo.
