# Prompt Stitch — Giao diện SaaS Y khoa "MedPro"

Bộ prompt để tạo **toàn bộ giao diện** nền tảng học & luyện thi Y khoa (mô hình AMBOSS/UWorld) trên [Stitch](https://stitch.withgoogle.com), thiết kế cho thị trường **Việt Nam**, dữ liệu mẫu **tiếng Việt**.

## Cách dùng

1. Đọc **`00-he-thong-thiet-ke.md`** trước — dán **Prompt Theme** vào Stitch **một lần** để cài đồng bộ thương hiệu/màu/font.
2. Mở file module tương ứng, copy prompt của từng màn hình, dán vào Stitch (mỗi lần một màn hình).
3. Tinh chỉnh bằng câu lệnh ngắn sau khi Stitch sinh giao diện.

## Cấu trúc thư mục

```
promp-stitch/
├── README.md                     ← file này
├── 00-he-thong-thiet-ke.md       ← THEME + design token + dữ liệu mẫu + template (đọc trước)
├── 01-public-va-core.md          ← Landing, Auth, Dashboard, Study Plan, Qbank, Session, Review
├── 02-noi-dung.md                ← AI Assistant, Thư viện, Bệnh học, Dược, Thủ thuật, Ảnh, Video
├── 03-ca-nhan-hoa.md             ← Notes, Bookmark, Highlight, Flashcards, Analytics, Weak Topics, Performance, Heatmap
├── 04-thi-va-tim-kiem.md         ← Exams, Self Assessment, Search, Global Search
├── 05-tai-khoan-va-he-thong.md   ← Notification, Subscription, Billing, Profile, Settings
└── 06-quan-tri-va-nen-tang.md    ← Admin Dashboard, các Management, RBAC, Audit, Reports, CMS, API
```

> 🔵 Module 32 — Organization đã **hoãn (Phase 2)**, chưa nằm trong bộ prompt hiện tại.

## Ánh xạ module → file prompt

| Nhóm | Module | File |
|------|--------|------|
| Public + Core | 01 Landing, 02 Auth, 03 Dashboard, 04 Study Plan, 05 Question Bank, 06 Question Session, 07 Question Review | `01-public-va-core.md` |
| Nội dung | 08 AI Assistant, 09 Medical Library, 10 Disease, 11 Drug, 12 Procedures, 13 Images, 14 Videos | `02-noi-dung.md` |
| Cá nhân hóa | 15 Notes, 16 Bookmark, 17 Highlight, 18 Flashcards, 19 Study Analytics, 20 Weak Topics, 21 Performance, 22 Heatmap | `03-ca-nhan-hoa.md` |
| Thi + Khám phá | 23 Exams, 24 Self Assessment, 25 Search, 26 Global Search | `04-thi-va-tim-kiem.md` |
| Tài khoản + Hệ thống | 27 Notification, 28 Subscription, 29 Billing, 30 Profile, 31 Settings *(32 Organization: 🔵 hoãn)* | `05-tai-khoan-va-he-thong.md` |
| Quản trị + Nền tảng | 33 Admin Dashboard, 34 User Mgmt, 35 Question Mgmt, 36 Library Mgmt, 37 Media Mgmt, 38 Exam Mgmt, 39 Role & Permission, 40 Audit Log, 41 Reports, 42 CMS, 43 API | `06-quan-tri-va-nen-tang.md` |

## Nguyên tắc chung khi tạo giao diện

- Thương hiệu: **MedPro** · màu chủ đạo teal `#0F766E` · font **Be Vietnam Pro**.
- Mọi nhãn & dữ liệu mẫu **tiếng Việt**; tiền tệ **VND**; thanh toán MoMo/VNPay/ZaloPay/thẻ.
- Gating Premium nhất quán: preview mờ + nút "Nâng cấp".
- Luôn có trạng thái loading/empty/error khi hợp lý; responsive mobile-first.
- **Mỗi prompt màn hình đều tạo ĐỒNG THỜI 2 khung: bản Desktop + bản Mobile** (xem câu "Tạo đồng thời 2 khung… Trên mobile: …" ở cuối mỗi prompt).
