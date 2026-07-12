# Module 30 — User Profile

**Nhóm:** Account · **Ưu tiên:** Trung bình · **Phụ thuộc:** Auth (02), Settings (31), Analytics (19) · **Trạng thái:** ✅

## 0. Tóm tắt module
Hồ sơ người dùng: thông tin cá nhân, avatar, mục tiêu học, thành tích (badges/streak), tóm tắt tiến trình. Có hồ sơ công khai (tùy chọn) và hồ sơ riêng.

| Route | Màn hình |
|-------|----------|
| `/profile` | Hồ sơ của tôi |
| `/profile/edit` | Chỉnh sửa |
| `/u/{username}` | Hồ sơ công khai (tùy chọn) |

## 1. Tổng quan
- **Mục đích:** Quản lý danh tính & xem thành tích.
- **Đến từ:** Avatar menu, leaderboard.
- **Đi sang:** Settings, Analytics.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Profile header** | Avatar, tên, trường, mục tiêu, streak | Luôn | — |
| **Stats summary** | Câu đã làm, correct rate, giờ học | Luôn | Grid |
| **Badges/Achievements** | Huy hiệu đạt được | Luôn | Grid |
| **Edit form** | Cập nhật thông tin + avatar upload | `/edit` | — |
| **Privacy toggle** | Công khai/riêng tư | Edit | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `ProfileHeader`, `AvatarUploader`(crop, validate ảnh), `StatsSummary`, `BadgeGrid`, `ProfileEditForm`(validate).

## 4. Luồng người dùng
```
Avatar menu → Profile → xem thành tích → Edit → cập nhật mục tiêu/avatar → lưu
Leaderboard → xem hồ sơ công khai người khác (nếu bật).
```

## 5. Business Logic
- **Badges** trao theo mốc (streak, số câu, mastery) — event-driven.
- **Privacy:** hồ sơ công khai chỉ hiện dữ liệu cho phép (ẩn số nhạy cảm).
- **Avatar** upload → media (biến thể, moderation cơ bản).
- **Mục tiêu học** đồng bộ Study Plan/Onboarding.

## 6. Database
- `users` (mục 2), `badges(id,key,name,criteria)`, `user_badges(user_id,badge_id,earned_at)`, `media`(avatar).

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/me/profile` | — | profile | Owner |
| PATCH | `/api/v1/me/profile` | fields | profile | Owner |
| POST | `/api/v1/me/avatar` | file | media | Owner |
| GET | `/api/v1/users/{username}` | — | public profile | Auth (nếu public) |

## 8. State Management
- Optimistic edit; avatar upload progress; badges cache; không realtime (trừ badge toast).

## 9. Phân quyền
- Owner sửa của mình; public profile theo privacy; Admin xem/sửa (module 34).

## 10. Edge Cases
- Ảnh quá lớn/sai định dạng → lỗi validate; username trùng → 409; hồ sơ private → 403 khi người khác xem; xóa tài khoản → ẩn danh hóa.

## 11. Tracking
`profile_open`, `profile_edit`, `avatar_upload`, `badge_earned`, `public_profile_view`.

## 12. Responsive
- Desktop: header + grid stats/badges. Mobile: stack, avatar lớn trên.

## 13. Security
- Scope owner; validate/scan avatar; privacy enforce server; không lộ email/PII ở public; chống IDOR `/u/{username}`.

## 14. Performance
- Avatar biến thể + CDN; stats từ cache; badges cache.

## 15. Đề xuất cải tiến
- Bảng thành tích gamified; chia sẻ thành tích mạng xã hội; hồ sơ so sánh trong lớp; mục tiêu công khai tạo cam kết.
