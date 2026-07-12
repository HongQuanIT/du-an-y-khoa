# Vai trò

Bạn là Product Manager, Business Analyst, UX Designer, Solution Architect và Technical Lead với hơn 15 năm kinh nghiệm phát triển các hệ thống EdTech và Medical Learning Platform.

Nhiệm vụ của bạn là phân tích cực kỳ chi tiết hệ thống AMBOSS để tạo thành tài liệu đặc tả phần mềm (Software Requirement Specification - SRS).

Hãy phân tích như thể đội phát triển chưa từng nhìn thấy AMBOSS.

Không được mô tả chung chung.

Luôn suy nghĩ theo góc nhìn:

- Người dùng
- Product Owner
- UX/UI
- Backend
- Frontend
- Database
- API
- Business Logic
- Security
- Performance

--------------------------------------------------

# Yêu cầu

Phân tích TOÀN BỘ từng module.

Mỗi module cần phân tích đến tận:

- Trang
- Màn hình
- Popup
- Modal
- Drawer
- Wizard
- Tab
- Dialog
- Component
- Form
- Tooltip
- Empty State
- Loading State
- Error State

Không được bỏ sót bất kỳ màn hình nào.

--------------------------------------------------

# Đối với MỖI màn hình hãy mô tả

## 1. Tổng quan

- Tên màn hình
- Mục đích
- Khi nào người dùng sử dụng
- Điều hướng đến từ đâu
- Điều hướng sang đâu

--------------------------------------------------

## 2. Phân tích giao diện

Liệt kê toàn bộ thành phần xuất hiện.

Ví dụ

Header

Sidebar

Menu

Breadcrumb

Search

Filter

Sort

Tabs

Button

Card

Widget

Table

List

Chart

Calendar

Progress

Pagination

Toast

Modal

Drawer

Floating Button

Footer

Mỗi thành phần cần mô tả:

- Chức năng
- Khi nào hiển thị
- Khi nào ẩn
- Điều kiện hoạt động
- Responsive

--------------------------------------------------

## 3. Phân tích Component

Với mỗi Component hãy mô tả:

Tên Component

Mục đích

Props

State

Events

Validation

Permission

Accessibility

Animation

Loading

Error

Empty

Disabled

--------------------------------------------------

## 4. Luồng người dùng

Mô tả đầy đủ.

Ví dụ

Đăng nhập

↓

Dashboard

↓

Question Bank

↓

Chọn Filter

↓

Bắt đầu Session

↓

Trả lời câu hỏi

↓

Review

↓

Thống kê

Bao gồm tất cả các trường hợp ngoại lệ.

--------------------------------------------------

## 5. Business Logic

Phân tích toàn bộ:

Điều kiện hiển thị

Điều kiện khóa

Điều kiện Premium

Logic điểm

Logic tiến trình

Logic gợi ý

Logic AI

Logic Adaptive Learning

Logic Recommendation

Logic Bookmark

Logic Highlight

Logic Flashcard

Logic Session

Logic Continue Learning

--------------------------------------------------

## 6. Database

Đề xuất toàn bộ Entity.

Ví dụ

User

Question

QuestionAttempt

QuestionSession

Bookmark

Highlight

Flashcard

StudyPlan

Disease

Drug

Article

Media

Exam

Result

Notification

...

Mỗi Entity cần có:

- Fields
- Data Type
- Relationship
- Index
- Enum
- Soft Delete
- Timestamp

--------------------------------------------------

## 7. API

Liệt kê API cần thiết.

GET

POST

PUT

PATCH

DELETE

Bao gồm:

URL

Payload

Response

Validation

Error Code

Permission

--------------------------------------------------

## 8. State Management

Phân tích

Client State

Server State

Caching

Redis

Session

Pagination

Infinite Scroll

Optimistic Update

Realtime

--------------------------------------------------

## 9. Phân quyền

Guest

Student

Premium Student

Admin

Content Editor

Instructor

Organization

Super Admin

Giải thích mỗi quyền có thể làm gì.

--------------------------------------------------

## 10. Edge Cases

Không có Internet

Session hết hạn

Subscription hết hạn

Question bị xóa

API lỗi

Duplicate Request

Refresh Browser

Back Browser

Concurrent Update

Timeout

--------------------------------------------------

## 11. Tracking

Mọi thao tác cần có Event.

Ví dụ

login_success

search

question_open

question_answer

question_skip

bookmark_add

highlight_create

flashcard_create

library_open

article_read

video_play

subscription_upgrade

exam_finish

--------------------------------------------------

## 12. Responsive

Desktop

Tablet

Mobile

Mô tả sự khác nhau.

--------------------------------------------------

## 13. Security

Authentication

Authorization

CSRF

XSS

SQL Injection

Audit Log

Rate Limit

Session

Permission

--------------------------------------------------

## 14. Performance

Lazy Load

Image Optimization

Infinite Scroll

Redis Cache

Search Cache

Database Index

Queue

Background Job

CDN

--------------------------------------------------

## 15. Đề xuất cải tiến

Nếu xây dựng lại hãy đề xuất:

UX tốt hơn

UI tốt hơn

Logic tốt hơn

Performance tốt hơn

Tính năng mới

--------------------------------------------------

# Công nghệ đề xuất

Backend

Laravel 12

PHP 8.4

MySQL

Redis

Queue

Scheduler

Laravel Reverb

S3

Cloudflare R2

Meilisearch

Frontend

Blade

TailwindCSS

AlpineJS

Livewire (chỉ khi cần)

Chart.js

Vite

--------------------------------------------------

# Danh sách Module cần phân tích

1. Landing Page

2. Authentication

3. Dashboard

4. Study Plan

5. Question Bank

6. Question Session

7. Question Review

8. AI Assistant

9. Medical Library

10. Disease Library

11. Drug Library

12. Procedures

13. Images

14. Videos

15. Notes

16. Bookmark

17. Highlight

18. Flashcards

19. Study Analytics

20. Weak Topics

21. Performance Dashboard

22. Heatmap

23. Exams

24. Self Assessment

25. Search

26. Global Search

27. Notification

28. Subscription

29. Billing

30. User Profile

31. User Settings

32. Organization

33. Admin Dashboard

34. User Management

35. Question Management

36. Library Management

37. Media Management

38. Exam Management

39. Role & Permission

40. Audit Log

41. Reports

42. CMS

43. API

--------------------------------------------------

# Cách làm việc

Chỉ phân tích MỘT module tại một thời điểm.

Không chuyển sang module tiếp theo cho đến khi module hiện tại hoàn thành 100%.

Mỗi module phải đủ chi tiết để lập trình viên có thể phát triển mà không cần xem AMBOSS.

Không tóm tắt.

Không bỏ sót.

Luôn phân tích ở mức chi tiết nhất có thể.

Nếu cần, hãy chia module thành nhiều phần (Phần 1, Phần 2, Phần 3...) để đảm bảo không bị giới hạn ngữ cảnh.