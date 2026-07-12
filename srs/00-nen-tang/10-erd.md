# 10 — ERD (Sơ đồ quan hệ thực thể)

> Sơ đồ trực quan cho mô hình dữ liệu ở `04-mo-hinh-du-lieu.md`. Có **2 dạng**:
> 1. **Ảnh PNG dựng sẵn** trong thư mục `erd-images/` (nhúng ngay dưới mỗi mục) — xem được ngay, kể cả khi trình xem không hỗ trợ Mermaid.
> 2. **Source Mermaid** (khối ```mermaid) để chỉnh sửa/version — mở file ở chế độ Preview (Cursor: `Cmd/Ctrl+Shift+V`) để render động.
>
> Muốn dựng lại ảnh sau khi sửa source, xem mục 11.
>
> Ghi chú ký hiệu quan hệ Mermaid: `||--o{` = một–nhiều (0..n) · `||--|{` = một–nhiều (1..n) · `}o--o{` = nhiều–nhiều · `||--||` = một–một.
> Quan hệ **đa hình (polymorphic)** được vẽ bằng đường nét đứt + bảng trung tâm (Note), vì ERD không mô tả trực tiếp polymorphic.

---

## 1. Sơ đồ tổng quan (High-level)

Các thực thể trung tâm và luồng chính. Bỏ bớt cột để dễ nhìn.

![ERD tổng quan](erd-images/1-00-tong-quan.png)

```mermaid
erDiagram
    USER ||--o{ QUESTION_SESSION : "tạo"
    USER ||--o{ QUESTION_ATTEMPT : "trả lời"
    USER ||--o{ QUESTION_STATUS : "trạng thái câu"
    USER ||--o{ FLASHCARD : "sở hữu"
    USER ||--o{ STUDY_PLAN : "sở hữu"
    USER ||--o{ TOPIC_MASTERY : "thành thạo"
    USER ||--o{ DAILY_STAT : "thống kê ngày"
    USER ||--o{ EXAM_ATTEMPT : "làm"
    USER ||--o{ NOTIFICATION : "nhận"
    USER ||--o| SUBSCRIPTION : "có"
    USER }o--o| ORGANIZATION : "thuộc"

    QUESTION_SESSION ||--o{ QUESTION_ATTEMPT : "gồm"
    QUESTION ||--o{ QUESTION_OPTION : "có đáp án"
    QUESTION ||--o{ QUESTION_ATTEMPT : "được trả lời"
    QUESTION }o--o{ TOPIC : "gắn chủ đề"

    EXAM ||--o{ EXAM_ATTEMPT : "sinh"
    EXAM_ATTEMPT ||--|| QUESTION_SESSION : "dùng"

    SUBSCRIPTION }o--|| PLAN : "theo gói"
    SUBSCRIPTION ||--o{ INVOICE : "phát hành"
    INVOICE ||--o{ PAYMENT : "thanh toán"

    ORGANIZATION ||--o{ ORGANIZATION_MEMBER : "gồm"
    USER ||--o{ ORGANIZATION_MEMBER : "là thành viên"

    ARTICLE }o--o{ CONTENT_LINK : "liên kết chéo"
    DRUG }o--o{ CONTENT_LINK : "liên kết chéo"
    MEDIA }o--o{ CONTENT_LINK : "liên kết chéo"
    QUESTION }o--o{ CONTENT_LINK : "liên kết chéo"
```

---

## 2. Nhóm Identity & Access

![ERD Identity & Access](erd-images/2-identity-access.png)

```mermaid
erDiagram
    USER ||--o{ OAUTH_ACCOUNT : "liên kết"
    USER ||--o{ DEVICE_SESSION : "đăng nhập"
    USER ||--o| TWO_FACTOR_SECRET : "bảo mật 2FA"
    USER }o--o{ ROLE : "role_user"
    ROLE }o--o{ PERMISSION : "permission_role"
    USER }o--o| ORGANIZATION : "thuộc"
    ORGANIZATION ||--o{ ORGANIZATION_MEMBER : "gồm"
    USER ||--o{ ORGANIZATION_MEMBER : "tham gia"

    USER {
        bigint id PK
        char uuid
        string name
        string email UK
        string password "null nếu OAuth"
        string role "enum"
        string status "enum"
        bigint avatar_media_id FK
        bigint organization_id FK
        date exam_target_date
        int streak_count
        json meta
        timestamp deleted_at
    }
    OAUTH_ACCOUNT {
        bigint id PK
        bigint user_id FK
        string provider "google/facebook/apple"
        string provider_user_id
        text access_token "enc"
        timestamp expires_at
    }
    DEVICE_SESSION {
        bigint id PK
        bigint user_id FK
        string device_name
        string ip
        string token_hash
        timestamp last_active_at
        timestamp revoked_at
    }
    TWO_FACTOR_SECRET {
        bigint id PK
        bigint user_id FK
        text secret_enc
        json recovery_codes_enc
        timestamp confirmed_at
    }
    ROLE {
        bigint id PK
        string name
        string slug UK
    }
    PERMISSION {
        bigint id PK
        string name
        string slug UK "resource.action"
    }
    ORGANIZATION {
        bigint id PK
        char uuid
        string name
        string type "university/hospital/company"
        int seats_total
        int seats_used
        string status
    }
    ORGANIZATION_MEMBER {
        bigint id PK
        bigint organization_id FK
        bigint user_id FK
        string org_role "member/instructor/org_admin"
        string status "invited/active/removed"
        timestamp joined_at
    }
```

---

## 3. Nhóm Câu hỏi & Học tập (Question & Learning)

![ERD Question & Learning](erd-images/3-question-learning.png)

```mermaid
erDiagram
    QUESTION ||--o{ QUESTION_OPTION : "có"
    QUESTION }o--o{ TOPIC : "question_topic"
    QUESTION }o--o{ TAG : "question_tag"
    QUESTION ||--o{ QUESTION_REPORT : "bị báo lỗi"
    QUESTION ||--o{ QUESTION_VERSION : "phiên bản"
    TOPIC ||--o{ TOPIC : "parent (phân cấp)"

    USER ||--o{ QUESTION_SESSION : "tạo"
    QUESTION_SESSION ||--o{ QUESTION_ATTEMPT : "gồm"
    QUESTION ||--o{ QUESTION_ATTEMPT : "được trả lời"
    USER ||--o{ QUESTION_ATTEMPT : "thực hiện"
    USER ||--o{ QUESTION_STATUS : "theo dõi"
    QUESTION ||--o{ QUESTION_STATUS : "trạng thái/user"

    QUESTION {
        bigint id PK
        char uuid
        longtext stem
        text lead_in
        string type "single_best/multi/matching"
        string difficulty
        string status "draft/in_review/published/retired"
        bool is_free
        longtext explanation
        json references
        json lab_values
        json media_ids
        int version
        json stats_cache
        timestamp deleted_at
    }
    QUESTION_OPTION {
        bigint id PK
        bigint question_id FK
        string label "A/B/C.."
        text content
        bool is_correct
        text explanation
        int order
    }
    TOPIC {
        bigint id PK
        bigint parent_id FK
        string name
        string slug
        string type "specialty/system/subtopic"
        int order
    }
    TAG {
        bigint id PK
        string name
        string slug
        string type
    }
    QUESTION_REPORT {
        bigint id PK
        bigint question_id FK
        bigint user_id FK
        string reason "enum"
        text detail
        string status "open/reviewing/resolved/rejected"
        bigint resolved_by FK
    }
    QUESTION_VERSION {
        bigint id PK
        bigint question_id FK
        int version
        json snapshot
        bigint created_by FK
    }
    QUESTION_SESSION {
        bigint id PK
        char uuid
        bigint user_id FK
        string mode "study/exam"
        string status "active/paused/completed/expired/abandoned"
        string source
        json filters
        json question_ids
        int total
        int answered_count
        int correct_count
        int time_limit_seconds
        json paused_state
        bigint exam_id FK
        timestamp deleted_at
    }
    QUESTION_ATTEMPT {
        bigint id PK
        bigint session_id FK
        bigint user_id FK
        bigint question_id FK
        json selected_option_ids
        bool is_correct "null nếu skip"
        bool used_hint
        int time_spent_seconds
        string confidence
        bool flagged
        timestamp answered_at
    }
    QUESTION_STATUS {
        bigint id PK
        bigint user_id FK
        bigint question_id FK
        string status "unseen/incorrect/correct/omitted/marked"
        int attempts_count
        timestamp last_attempt_at
        timestamp last_correct_at
    }
```

---

## 4. Nhóm Cá nhân hóa (Personalization)

> `NOTE`, `BOOKMARK`, `HIGHLIGHT` là **đa hình** (gắn với Question/Article/Drug/Media/Video...). Cột `*_type` + `*_id` trỏ tới bất kỳ thực thể nào.

![ERD Personalization](erd-images/4-personalization.png)

```mermaid
erDiagram
    USER ||--o{ NOTE : "viết"
    USER ||--o{ BOOKMARK : "lưu"
    USER ||--o{ BOOKMARK_FOLDER : "tổ chức"
    BOOKMARK_FOLDER ||--o{ BOOKMARK : "chứa"
    USER ||--o{ HIGHLIGHT : "tô sáng"
    USER ||--o{ FLASHCARD : "tạo"
    USER ||--o{ FLASHCARD_DECK : "sở hữu"
    FLASHCARD_DECK ||--o{ FLASHCARD : "gồm"
    FLASHCARD ||--o{ FLASHCARD_REVIEW : "lịch SRS"
    USER ||--o{ FLASHCARD_REVIEW : "ôn"

    NOTE {
        bigint id PK
        bigint user_id FK
        string notable_type "poly"
        bigint notable_id "poly"
        text body
        string color
        timestamp deleted_at
    }
    BOOKMARK {
        bigint id PK
        bigint user_id FK
        string bookmarkable_type "poly"
        bigint bookmarkable_id "poly"
        bigint folder_id FK
    }
    BOOKMARK_FOLDER {
        bigint id PK
        bigint user_id FK
        string name
        string color
        int order
    }
    HIGHLIGHT {
        bigint id PK
        bigint user_id FK
        string highlightable_type "poly"
        bigint highlightable_id "poly"
        json anchor
        text text_snapshot
        string color
        text note
        timestamp deleted_at
    }
    FLASHCARD {
        bigint id PK
        bigint user_id FK
        bigint deck_id FK
        text front
        text back
        string source_type "poly, null"
        bigint source_id "poly, null"
        json tags
        timestamp deleted_at
    }
    FLASHCARD_REVIEW {
        bigint id PK
        bigint flashcard_id FK
        bigint user_id FK
        float ease_factor
        int interval_days
        int repetitions
        timestamp due_at
        string last_grade "again/hard/good/easy"
        timestamp last_reviewed_at
    }
    FLASHCARD_DECK {
        bigint id PK
        bigint user_id FK
        string name
        bool is_shared
    }
```

---

## 5. Nhóm Thư viện nội dung (Content Library)

> `CONTENT_LINK` là bảng liên kết chéo **đa hình hai đầu** (source & target): Article ↔ Drug ↔ Media ↔ Question ↔ Procedure.

![ERD Content Library](erd-images/5-content-library.png)

```mermaid
erDiagram
    ARTICLE ||--o| DISEASE_META : "mở rộng (type=disease)"
    ARTICLE ||--o| PROCEDURE_META : "mở rộng (type=procedure)"
    ARTICLE ||--o{ ARTICLE_VERSION : "phiên bản"
    DRUG ||--o{ DRUG_INTERACTION : "cặp tương tác"
    MEDIA ||--o{ MEDIA_USAGE : "được dùng ở"
    MEDIA ||--o{ MEDIA_JOB : "xử lý"

    ARTICLE {
        bigint id PK
        char uuid
        string slug UK
        string type "disease/topic/procedure/general"
        string title
        text summary
        longtext body
        string status
        bool is_free
        json toc
        json references
        int version
        timestamp deleted_at
    }
    DISEASE_META {
        bigint id PK
        bigint article_id FK
        string icd_code
        string system
        json prevalence
        json high_yield
    }
    PROCEDURE_META {
        bigint id PK
        bigint article_id FK
        string specialty
        json steps
        json equipment
        json complications
    }
    ARTICLE_VERSION {
        bigint id PK
        bigint article_id FK
        int version
        json snapshot
        bigint created_by FK
    }
    DRUG {
        bigint id PK
        char uuid
        string name
        string generic_name
        json brand_names
        string drug_class
        text indications
        text contraindications
        json dosing
        json interactions
        bool is_free
        timestamp deleted_at
    }
    DRUG_INTERACTION {
        bigint id PK
        bigint drug_a_id FK
        bigint drug_b_id FK
        string severity
        text mechanism
        text effect
        text management
    }
    MEDIA {
        bigint id PK
        char uuid
        string type "image/video/audio/document"
        string disk
        string path
        json variants
        string alt
        string caption
        bool is_premium
        string status "processing/ready/failed"
        timestamp deleted_at
    }
    MEDIA_USAGE {
        bigint id PK
        bigint media_id FK
        string usable_type "poly"
        bigint usable_id "poly"
    }
    MEDIA_JOB {
        bigint id PK
        bigint media_id FK
        string type
        string status
    }
    CONTENT_LINK {
        bigint id PK
        string source_type "poly"
        bigint source_id "poly"
        string target_type "poly"
        bigint target_id "poly"
        string relation "mentions/see_also/treats/differential.."
    }
```

---

## 6. Nhóm Study Plan & Analytics

![ERD Study Plan & Analytics](erd-images/6-study-plan-analytics.png)

```mermaid
erDiagram
    USER ||--o{ STUDY_PLAN : "sở hữu"
    STUDY_PLAN ||--o{ STUDY_PLAN_TASK : "gồm"
    USER ||--o{ TOPIC_MASTERY : "theo chủ đề"
    TOPIC ||--o{ TOPIC_MASTERY : "được đo"
    USER ||--o{ DAILY_STAT : "theo ngày"

    STUDY_PLAN {
        bigint id PK
        bigint user_id FK
        string name
        date exam_target_date
        int daily_goal_questions
        int daily_goal_minutes
        json topic_scope
        string strategy "adaptive/fixed"
        string status
        json progress_cache
    }
    STUDY_PLAN_TASK {
        bigint id PK
        bigint study_plan_id FK
        date date
        string type "questions/read/flashcards/review"
        int target
        int done
        string status "pending/done/skipped"
        json ref
    }
    TOPIC_MASTERY {
        bigint id PK
        bigint user_id FK
        bigint topic_id FK
        int attempts
        int correct
        decimal correct_rate
        int mastery_level "0-5"
        timestamp last_activity_at
        json trend
    }
    DAILY_STAT {
        bigint id PK
        bigint user_id FK
        date date
        int questions_answered
        int correct
        int minutes
        int sessions
        int avg_time
        bool streak_flag
    }
```

---

## 7. Nhóm Thi cử (Exam)

![ERD Exam](erd-images/7-exam.png)

```mermaid
erDiagram
    EXAM ||--o{ EXAM_ATTEMPT : "sinh ra"
    USER ||--o{ EXAM_ATTEMPT : "thực hiện"
    EXAM_ATTEMPT ||--|| QUESTION_SESSION : "dung engine"
    CLASSROOM ||--o{ ASSIGNMENT : "giao"
    EXAM ||--o{ ASSIGNMENT : "duoc giao"
    ASSIGNMENT ||--o{ ASSIGNMENT_SUBMISSION : "nop"
    USER ||--o{ ASSIGNMENT_SUBMISSION : "nop bai"

    EXAM {
        bigint id PK
        char uuid
        string title
        string type "mock/self_assessment/org_exam"
        text description
        json question_ids
        json rule
        int duration_minutes
        int pass_score
        timestamp available_from
        timestamp available_to
        bool is_premium
        string status
        timestamp deleted_at
    }
    EXAM_ATTEMPT {
        bigint id PK
        char uuid
        bigint exam_id FK
        bigint user_id FK
        bigint session_id FK
        int score
        float percentile
        string status "in_progress/submitted/graded"
        timestamp started_at
        timestamp submitted_at
    }
    CLASSROOM {
        bigint id PK
        bigint org_id FK
        string name
        bigint instructor_id FK
    }
    ASSIGNMENT {
        bigint id PK
        bigint class_id FK
        string type
        bigint ref_id "exam/content"
        timestamp due_at
        bigint created_by FK
    }
    ASSIGNMENT_SUBMISSION {
        bigint id PK
        bigint assignment_id FK
        bigint user_id FK
        string status
        int score
        timestamp submitted_at
    }
```

---

## 8. Nhóm Thương mại (Commerce)

![ERD Commerce](erd-images/8-commerce.png)

```mermaid
erDiagram
    USER ||--o| SUBSCRIPTION : "cá nhân"
    ORGANIZATION ||--o| SUBSCRIPTION : "tổ chức"
    SUBSCRIPTION }o--|| PLAN : "theo gói"
    SUBSCRIPTION ||--o{ INVOICE : "phát hành"
    INVOICE ||--o{ PAYMENT : "thanh toán"
    USER ||--o{ PAYMENT_METHOD : "lưu"
    COUPON ||--o{ SUBSCRIPTION : "áp dụng"

    PLAN {
        bigint id PK
        string name
        string slug
        int price_cents
        char currency
        string interval "month/year/lifetime"
        json features
        int trial_days
        bool is_active
    }
    SUBSCRIPTION {
        bigint id PK
        bigint user_id FK
        bigint organization_id FK
        bigint plan_id FK
        string status "trialing/active/past_due/canceled/expired"
        timestamp current_period_start
        timestamp current_period_end
        timestamp cancel_at
        string provider
        string provider_sub_id
        int seats
    }
    INVOICE {
        bigint id PK
        bigint subscription_id FK
        int amount_cents
        char currency
        string status "paid/open/void/refunded"
        timestamp issued_at
        timestamp paid_at
        bigint pdf_media_id FK
    }
    PAYMENT {
        bigint id PK
        bigint invoice_id FK
        int amount_cents
        string method
        string status
        string provider_payment_id
        timestamp paid_at
    }
    PAYMENT_METHOD {
        bigint id PK
        bigint user_id FK
        string provider
        string token
        string brand
        string last4
        bool is_default
    }
    COUPON {
        bigint id PK
        string code UK
        string type "percent/fixed"
        int value
        int max_redemptions
        int redeemed_count
        timestamp valid_to
        bool active
    }
```

---

## 9. Nhóm Hệ thống (System)

![ERD System](erd-images/9-system.png)

```mermaid
erDiagram
    USER ||--o{ NOTIFICATION : "nhận"
    USER ||--o{ NOTIFICATION_PREFERENCE : "cấu hình"
    USER ||--o{ PUSH_TOKEN : "thiết bị"
    USER ||--o{ TRACKING_EVENT : "sinh (nullable)"
    USER ||--o{ AUDIT_LOG : "actor (nullable)"
    USER ||--o{ API_KEY : "sở hữu (owner)"
    API_KEY ||--o{ API_REQUEST_LOG : "ghi log"
    WEBHOOK ||--o{ WEBHOOK_DELIVERY : "giao"

    NOTIFICATION {
        bigint id PK
        bigint user_id FK
        string type
        string title
        text body
        json data
        string channel "in_app/email/push"
        timestamp read_at
        string action_url
    }
    NOTIFICATION_PREFERENCE {
        bigint id PK
        bigint user_id FK
        string type
        string channel
        bool enabled
    }
    PUSH_TOKEN {
        bigint id PK
        bigint user_id FK
        string token
        string platform
    }
    TRACKING_EVENT {
        bigint id PK
        bigint user_id FK "null nếu guest"
        string session_uuid
        string name
        json properties
        string url
        timestamp occurred_at
    }
    AUDIT_LOG {
        bigint id PK
        bigint actor_id FK
        string action
        string auditable_type "poly"
        bigint auditable_id "poly"
        json before
        json after
        string ip
        string request_id
        timestamp created_at
    }
    FEATURE_FLAG {
        bigint id PK
        string key UK
        bool enabled
        json rules
    }
    SETTING {
        bigint id PK
        string group
        string key
        json value
        bool is_public
    }
    API_KEY {
        bigint id PK
        string owner_type "poly"
        bigint owner_id "poly"
        string name
        string key_hash
        json scopes
        timestamp expires_at
        timestamp revoked_at
    }
    API_REQUEST_LOG {
        bigint id PK
        bigint key_id FK
        string endpoint
        int status
        int ms
        timestamp created_at
    }
    WEBHOOK {
        bigint id PK
        bigint owner_id FK
        string url
        json events
        string secret
        bool active
    }
    WEBHOOK_DELIVERY {
        bigint id PK
        bigint webhook_id FK
        string event_id
        string status
        int attempts
        int response_code
    }
```

---

## 10. Bản đồ quan hệ đa hình (Polymorphic map)

Các bảng dùng cột `{name}_type` + `{name}_id` để trỏ tới nhiều loại thực thể:

| Bảng | Cột đa hình | Trỏ tới (targets) |
|------|-------------|-------------------|
| NOTE | notable_type/id | Question, Article, Drug, Media, Video |
| BOOKMARK | bookmarkable_type/id | Question, Article, Drug, Procedure, Media, Video |
| HIGHLIGHT | highlightable_type/id | Article, Question (explanation) |
| FLASHCARD | source_type/id | Question, Article |
| MEDIA_USAGE | usable_type/id | Question, Article, Procedure |
| CONTENT_LINK | source & target _type/id | Article, Drug, Media, Question, Procedure |
| AUDIT_LOG | auditable_type/id | mọi entity nghiệp vụ |
| API_KEY | owner_type/id | User, Organization |

---

## 11. Dựng lại ảnh sau khi sửa source

Ảnh trong `erd-images/` được sinh từ các khối ```mermaid của file này bằng Mermaid CLI. Sau khi chỉnh source, chạy lại:

```bash
# Cài Mermaid CLI (một lần)
npm install -g @mermaid-js/mermaid-cli

# Dựng lại toàn bộ (mỗi khối ```mermaid → 1 ảnh erd-N.png)
cd srs/00-nen-tang
mmdc -i 10-erd.md -o erd.png -b white
# → tạo erd-1.png ... erd-9.png (đổi tên/di chuyển vào erd-images/ nếu muốn)

# SVG (nét, phóng to không vỡ)
mmdc -i 10-erd.md -o erd.svg -b white
```

Ngoài ra có thể dán từng khối `mermaid` vào [mermaid.live](https://mermaid.live) để chỉnh và tải ảnh.

> Thứ tự khối ↔ ảnh: 1=Tổng quan · 2=Identity · 3=Question/Learning · 4=Personalization · 5=Content Library · 6=Study Plan/Analytics · 7=Exam · 8=Commerce · 9=System.
