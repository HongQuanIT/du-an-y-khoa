# 01 — Tổng quan hệ thống

## 1. Bối cảnh & Tầm nhìn

Nền tảng **học và luyện thi y khoa trực tuyến** dành cho sinh viên y và bác sĩ trẻ, mô phỏng mô hình AMBOSS/UWorld. Giá trị cốt lõi:

- **Ngân hàng câu hỏi chuẩn hóa** (biên soạn/dịch từ nguồn quốc tế) kèm giải thích chi tiết, tham chiếu y khoa.
- **Luyện đề thông minh**: phân tích kết quả, phát hiện điểm yếu, gợi ý ôn tập cá nhân hóa.
- **Thư viện y khoa** liên kết chéo (bệnh học, dược, thủ thuật, hình ảnh, video).
- **AI Tutor**: giải thích câu hỏi, dẫn nguồn tin cậy.
- **Mô phỏng thi thật** theo chuẩn hành nghề.

Khẩu hiệu: *"Học hiệu quả hơn — hiểu bản chất, nhớ lâu, luyện thi đúng trọng tâm."*

## 2. Mục tiêu sản phẩm (Product Goals)

| # | Mục tiêu | Chỉ số đo (KPI) |
|---|----------|-----------------|
| G1 | Tăng hiệu quả học tập | Correct rate tăng theo thời gian; thời gian đến "mastery" giảm |
| G2 | Cá nhân hóa lộ trình | % người dùng dùng Study Plan; % làm theo gợi ý Weak Topics |
| G3 | Chuyển đổi Premium | Free→Premium conversion rate; MRR |
| G4 | Giữ chân người dùng | DAU/MAU, streak trung bình, retention D7/D30 |
| G5 | Chất lượng nội dung | Tỷ lệ câu hỏi bị report; thời gian xử lý report |

## 3. Actor (Đối tượng người dùng)

| Actor | Mô tả | Quyền chính |
|-------|-------|-------------|
| **Guest** | Khách chưa đăng nhập | Xem landing, preview giới hạn, đăng ký |
| **Student (Free)** | Người học miễn phí | Làm câu hỏi free, thư viện giới hạn, tính năng cá nhân cơ bản |
| **Premium Student** | Người học trả phí | Toàn bộ Qbank, thư viện, AI, analytics nâng cao |
| **Instructor** | Giảng viên/mentor | Tạo lớp, giao bài, xem tiến độ học viên |
| **Content Editor** | Biên tập nội dung | CRUD câu hỏi, bài viết, media (theo workflow duyệt) |
| **Organization Admin** | Quản trị tổ chức (trường/bệnh viện) | Quản lý thành viên, ghế license, báo cáo tổ chức |
| **Admin** | Quản trị hệ thống | Quản lý user, nội dung, cấu hình |
| **Super Admin** | Toàn quyền | Cấu hình hệ thống, phân quyền, billing, feature flag |

Chi tiết ma trận quyền: xem `03-phan-quyen-rbac.md`.

## 4. Phạm vi hệ thống (Scope) — 43 module theo 8 nhóm

1. **Public**: Landing Page.
2. **Core học tập**: Authentication, Dashboard, Study Plan, Question Bank, Question Session, Question Review.
3. **Nội dung**: AI Assistant, Medical Library, Disease Library, Drug Library, Procedures, Images, Videos.
4. **Cá nhân hóa**: Notes, Bookmark, Highlight, Flashcards, Study Analytics, Weak Topics, Performance Dashboard, Heatmap.
5. **Thi cử**: Exams, Self Assessment.
6. **Khám phá**: Search, Global Search.
7. **Tài khoản**: Notification, Subscription, Billing, User Profile, User Settings, Organization.
8. **Quản trị & Nền tảng**: Admin Dashboard, User/Question/Library/Media/Exam Management, Role & Permission, Audit Log, Reports, CMS, API.

## 5. Sơ đồ ngữ cảnh (Context)

```
                         ┌─────────────────────────────┐
        Guest/Student ──▶│      Web App (Blade/SPA-ish) │
                         │  Tailwind + Alpine + Livewire│
                         └───────────────┬─────────────┘
                                         │ HTTPS / JSON API / WebSocket
                         ┌───────────────▼─────────────┐
                         │     Laravel 12 (PHP 8.4)     │
                         │  Controllers / Actions / API │
                         └───┬───────┬───────┬──────┬───┘
                             │       │       │      │
               ┌─────────────▼┐ ┌────▼───┐ ┌─▼────┐ ┌▼─────────┐
               │   MySQL 8    │ │ Redis  │ │Meili │ │ Reverb   │
               │ (nguồn thật) │ │(cache/ │ │search│ │(realtime)│
               │              │ │ queue) │ │      │ │          │
               └──────────────┘ └────────┘ └──────┘ └──────────┘
                             │
               ┌─────────────▼───────────────┐   ┌──────────────┐
               │  S3 / Cloudflare R2 + CDN    │   │  AI Provider │
               │  (media: ảnh, video, file)   │   │  (LLM API)   │
               └──────────────────────────────┘  └──────────────┘
```

## 6. Nguyên tắc thiết kế xuyên suốt

- **Mobile-first responsive**; breakpoint chính 640px / 1024px / 1280px.
- **Mọi hành động có phản hồi** (loading, success checkmark, toast, error rõ ràng).
- **Offline-tolerant** ở mức tối thiểu: giữ trạng thái session làm bài, chống mất dữ liệu khi refresh/back.
- **Optimistic UI** cho thao tác nhẹ (bookmark, highlight, flag).
- **Accessibility** WCAG 2.1 AA: keyboard nav, ARIA, contrast, focus ring.
- **i18n-ready**: chuỗi tách riêng, hỗ trợ VI (mặc định) + EN.
- **Premium gating** nhất quán: cùng một component "Paywall/Upgrade" dùng lại khắp nơi.

## 7. Các khái niệm miền (Domain concepts) trọng tâm

- **Question**: đơn vị câu hỏi (thường dạng vignette lâm sàng + 4–5 đáp án + giải thích).
- **Session**: một phiên làm nhiều câu, có chế độ **Study** (xem giải thích ngay) hoặc **Exam** (giới hạn thời gian, xem kết quả sau nộp).
- **Attempt**: một lần trả lời một câu trong session.
- **Mastery / Weak Topic**: mức thành thạo theo chủ đề, tính từ lịch sử attempt.
- **Library Article**: bài viết y khoa liên kết chéo với câu hỏi, thuốc, hình ảnh.
- **Flashcard**: thẻ ghi nhớ (spaced repetition).
- **Study Plan**: lộ trình học cá nhân hóa theo ngày thi mục tiêu.

## 8. Ràng buộc phi chức năng (NFR) tóm tắt

| Loại | Yêu cầu |
|------|---------|
| Hiệu năng | Chịu tải > 5.000 người dùng đồng thời; p95 API < 300ms (đọc cache), < 800ms (ghi) |
| Sẵn sàng | Uptime ≥ 99.9% |
| Bảo mật | OWASP Top 10, mã hóa dữ liệu nhạy cảm, audit log đầy đủ |
| Khả mở rộng | Kiến trúc stateless app-tier, scale ngang |
| Khả bảo trì | Coding convention, test coverage cho business logic lõi |
| Tuân thủ | Chính sách dữ liệu người dùng, xử lý thanh toán qua cổng đạt chuẩn |

Chi tiết: `07-security-performance.md`.
