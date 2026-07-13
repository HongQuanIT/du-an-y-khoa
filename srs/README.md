# SRS — Nền tảng Học & Luyện thi Y khoa (AMBOSS-style)

> Bộ tài liệu **Software Requirement Specification (SRS)** phân tích chi tiết hệ thống theo mô hình AMBOSS/UWorld, phục vụ đội phát triển xây dựng lại toàn bộ nền tảng mà **không cần xem AMBOSS gốc**.

## 1. Mục đích tài liệu

- Đặc tả đầy đủ hệ thống theo **43 module**, trong đó **42 module thuộc phạm vi hiện tại** và **1 module hoãn (Phase 2)**: từng trang, màn hình, popup, modal, drawer, wizard, tab, component, form, empty/loading/error state.
- 🔵 **Đã hoãn:** Module 32 — Organization (chưa đưa vào hệ thống ở giai đoạn này; tài liệu giữ lại để triển khai sau).
- Chuẩn hóa **kiến trúc, mô hình dữ liệu, API, phân quyền, tracking, security, performance** dùng chung cho mọi module.
- Là "single source of truth" cho Product Owner, BA, UX/UI, Backend, Frontend, QA.

## 2. Công nghệ mục tiêu

| Tầng | Công nghệ |
|------|-----------|
| Backend | Laravel 12, PHP 8.4 |
| Database | MySQL 8 + Redis |
| Search | Meilisearch |
| Realtime | Laravel Reverb (WebSocket) |
| Queue/Jobs | Laravel Queue + Scheduler |
| Storage | S3 / Cloudflare R2 + CDN |
| Frontend | Blade + TailwindCSS + AlpineJS + Livewire (khi cần) |
| Charts | Chart.js |
| Build | Vite |

## 3. Cách đọc tài liệu

1. Đọc **`00-nen-tang/`** trước — đây là nền tảng dùng chung, mọi module tham chiếu về đây.
2. Đọc từng module trong **`modules/`** theo nhu cầu. Mỗi module tuân theo **khung 15 mục chuẩn** (xem `00-nen-tang/07-template-module.md`).
3. Thuật ngữ khó tra tại **`00-nen-tang/08-glossary.md`**.

## 4. Cấu trúc thư mục

```
srs/
├── README.md                         ← file này (mục lục)
├── 00-nen-tang/                      ← tài liệu nền tảng dùng chung
│   ├── 01-tong-quan-he-thong.md
│   ├── 02-kien-truc-ky-thuat.md
│   ├── 03-phan-quyen-rbac.md
│   ├── 04-mo-hinh-du-lieu.md
│   ├── 05-api-conventions.md
│   ├── 06-tracking-analytics.md
│   ├── 07-security-performance.md
│   ├── 07-template-module.md
│   ├── 08-glossary.md
│   ├── 09-trang-thai.md
│   ├── 10-erd.md                     ← ERD theo nhóm (Mermaid + ảnh SVG)
│   ├── 11-erd-full.md                ← ERD đầy đủ toàn hệ thống (1 sơ đồ)
│   └── erd-images/                   ← ảnh SVG dựng sẵn của ERD
└── modules/                          ← 42 module hiện tại (+ 1 hoãn: Organization)
    ├── 01-landing-page.md
    ├── 02-authentication.md
    ├── ...
    └── 43-api.md
```

## 5. Danh sách Module

> 🔵 = hoãn (Phase 2, chưa thuộc phạm vi build hiện tại).

| # | Module | Nhóm | File |
|---|--------|------|------|
| 1 | Landing Page | Public | `modules/01-landing-page.md` |
| 2 | Authentication | Core | `modules/02-authentication.md` |
| 3 | Dashboard | Core | `modules/03-dashboard.md` |
| 4 | Study Plan | Core | `modules/04-study-plan.md` |
| 5 | Question Bank | Core | `modules/05-question-bank.md` |
| 6 | Question Session | Core | `modules/06-question-session.md` |
| 7 | Question Review | Core | `modules/07-question-review.md` |
| 8 | AI Assistant | Content | `modules/08-ai-assistant.md` |
| 9 | Medical Library | Content | `modules/09-medical-library.md` |
| 10 | Disease Library | Content | `modules/10-disease-library.md` |
| 11 | Drug Library | Content | `modules/11-drug-library.md` |
| 12 | Procedures | Content | `modules/12-procedures.md` |
| 13 | Images | Content | `modules/13-images.md` |
| 14 | Videos | Content | `modules/14-videos.md` |
| 15 | Notes | Personal | `modules/15-notes.md` |
| 16 | Bookmark | Personal | `modules/16-bookmark.md` |
| 17 | Highlight | Personal | `modules/17-highlight.md` |
| 18 | Flashcards | Personal | `modules/18-flashcards.md` |
| 19 | Study Analytics | Personal | `modules/19-study-analytics.md` |
| 20 | Weak Topics | Personal | `modules/20-weak-topics.md` |
| 21 | Performance Dashboard | Personal | `modules/21-performance-dashboard.md` |
| 22 | Heatmap | Personal | `modules/22-heatmap.md` |
| 23 | Exams | Exam | `modules/23-exams.md` |
| 24 | Self Assessment | Exam | `modules/24-self-assessment.md` |
| 25 | Search | Discovery | `modules/25-search.md` |
| 26 | Global Search | Discovery | `modules/26-global-search.md` |
| 27 | Notification | System | `modules/27-notification.md` |
| 28 | Subscription | Account | `modules/28-subscription.md` |
| 29 | Billing | Account | `modules/29-billing.md` |
| 30 | User Profile | Account | `modules/30-user-profile.md` |
| 31 | User Settings | Account | `modules/31-user-settings.md` |
| 32 | 🔵 Organization *(hoãn - Phase 2)* | Account | `modules/32-organization.md` |
| 33 | Admin Dashboard | Admin | `modules/33-admin-dashboard.md` |
| 34 | User Management | Admin | `modules/34-user-management.md` |
| 35 | Question Management | Admin | `modules/35-question-management.md` |
| 36 | Library Management | Admin | `modules/36-library-management.md` |
| 37 | Media Management | Admin | `modules/37-media-management.md` |
| 38 | Exam Management | Admin | `modules/38-exam-management.md` |
| 39 | Role & Permission | Admin | `modules/39-role-permission.md` |
| 40 | Audit Log | Admin | `modules/40-audit-log.md` |
| 41 | Reports | Admin | `modules/41-reports.md` |
| 42 | CMS | Admin | `modules/42-cms.md` |
| 43 | API | Platform | `modules/43-api.md` |

## 6. Trạng thái tài liệu

- Ký hiệu: ✅ hoàn chỉnh · 🟡 bản nháp chi tiết · ⬜ khung chờ bổ sung · 🔵 hoãn (Phase 2)
- Xem cột trạng thái trong `00-nen-tang/09-trang-thai.md` (cập nhật liên tục).

## 7. Quy ước

- Mỗi module **độc lập đọc được**, tham chiếu nền tảng thay vì lặp lại.
- ID entity, tên API, tên event tracking dùng chung theo `00-nen-tang`.
- Ngôn ngữ: tiếng Việt (thuật ngữ kỹ thuật giữ tiếng Anh).
