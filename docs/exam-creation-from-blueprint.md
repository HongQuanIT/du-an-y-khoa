# Tạo kỳ thi / bài thi từ ma trận đề — Thuật toán & kiến trúc

| | |
|--|--|
| **UI học viên** | [`/exams`](http://localhost/exams) · Module Exam (23) |
| **Cấu hình ma trận** | `/admin/blueprints/{id}/edit` · Taxonomy / Blueprint |
| **SRS** | `srs/modules/23-exams.md` · `srs/modules/38-exam-management.md` |
| **Liên quan** | Adaptive practice: [`adaptive-session-algorithm.md`](./adaptive-session-algorithm.md) |
| **Trạng thái** | Đã ship: học viên tự tạo **bài thi** từ **kỳ thi (ma trận)**; Admin chỉ giám sát danh sách |

---

## 0. Thuật ngữ (quan trọng)

Hệ thống **phân tách** hai khái niệm dễ lẫn trên UI:

| Thuật ngữ UI | Entity DB | Ai tạo? | Ý nghĩa |
|--------------|-----------|---------|---------|
| **Kỳ thi** | `blueprints` (+ sections + CCT) | Admin | Ma trận cấu trúc đề: tổng số câu, tỉ trọng phần/chủ đề, map bài học |
| **Bài thi** | `exams` (+ `exam_topics` + `exam_question`) | Học viên (mỗi lần bấm «Tạo bài thi») | Một đề cụ thể: quota CCT đã làm tròn + snapshot câu hỏi từ ngân hàng đã xuất bản |

Mỗi lần học viên tạo bài thi từ cùng một kỳ thi → **đề mới** (có thể khác câu, vì pick theo `created_at DESC` + pool thay đổi; không tái sử dụng `exam_question` cũ).

Admin `/admin/exams` **không** còn wizard tạo đề — chỉ xem/xóa bài thi học viên đã sinh.

---

## 1. Mục tiêu thiết kế

1. **Chuẩn hóa theo ma trận thật** (QĐ / blueprint nội khoa): phần (Nội/Ngoại/…) có dải tỉ trọng min–max; chủ đề lâm sàng (CCT) có % trong phần.
2. **Admin không chọn từng câu** — chỉ cấu hình trọng số + map CCT ↔ Bài học (và tùy chọn Tag).
3. **Nguồn câu = ngân hàng đã xuất bản** — `ServePublishedQuestion` (không lấy `private`/`retired`). Cờ `is_priority` (Câu ưu tiên) dùng chữa đề livestream, không giới hạn pool sinh bài thi.
4. **Công bằng & xác định đủ câu** — thiếu pool → chặn tạo (transaction rollback), không bù sang CCT khác.
5. **Ưu tiên bài học trọng điểm** (`core_topic_lessons.is_priority`) trước khi nới sang toàn phạm vi CCT.

---

## 2. Mô hình dữ liệu

```text
blueprints                          ← Kỳ thi / ma trận
  └─ blueprint_sections             ← Phần (weight_min, weight_max % toàn ma trận)
       └─ core_clinical_topics      ← CCT (weight % trong phần, tổng ≈ 100)
            ├─ core_topic_lessons   ← map Bài học (+ is_priority)
            └─ core_topic_tags      ← map Tag (fallback eligibility)

exams                               ← Bài thi cá nhân (user_id, blueprint_id, duration…)
  ├─ exam_topics                    ← quota theo CCT (question_count [, difficulty_counts])
  └─ exam_question                  ← snapshot câu (order, core_clinical_topic_id)

questions                           ← NHCH; bài thi lấy bản live trong ngân hàng
  └─ question_lesson / question_tag ← khớp CCT gián tiếp qua map ở trên
```

**Nguyên tắc map:** câu hỏi **không** gắn trực tiếp CCT. Eligibility suy ra:

```text
CCT → (lessons ∪ tags) → questions gắn lesson hoặc tag đó
```

---

## 3. Cấu hình ma trận (Admin)

Trang `/admin/blueprints/{id}/edit` — khối **Tỉ trọng ma trận**:

| Trường | Vị trí | Ý nghĩa |
|--------|--------|---------|
| `blueprints.total_questions` | Toàn ma trận | N câu mục tiêu của đề |
| `sections.weight_min` / `weight_max` | Mỗi phần | % toàn ma trận; UI cảnh báo khi không thỏa `Σ min ≤ 100 ≤ Σ max` |
| `topics.weight` | Mỗi CCT trong phần | % trong phần; UI cảnh báo khi `Σ weight ≠ 100` |
| `core_topic_lessons.is_priority` | Map bài học | Sao = trọng điểm khi sinh đề |

Cảnh báo realtime trên form **không chặn lưu** — nhưng allocator sẽ trả `ready=false` nếu thiếu `total_questions`, section/topic weight, hoặc section không có CCT active.

**Thời lượng gợi ý** (khi ma trận ready):

```text
duration_minutes = max(30, round(N × 1.5 / 5) × 5)
```

≈ 1,5 phút/câu, làm tròn bội 5.

---

## 4. Pipeline end-to-end (`/exams`)

```text
[1] GET /exams
    ExamCatalogService::blueprintCards()
    → với mỗi blueprint Active: BlueprintExamAllocator::allocate()
    → card: ready | reason | question_count | duration | topic_count
    → gating UI: entitlement ExamSimulation (Premium)

[2] POST /exams/from-blueprint/{blueprint}
    middleware: auth, learner, exam.take, subscription:exam.simulation
    CreateExamFromBlueprintController
      → CreateLearnerExamFromBlueprintAction
           a. allocate() → quotas CCT
           b. tạo Exam (Published, user_id, blueprint_id, duration)
           c. ghi ExamTopic (mỗi CCT có question_count > 0)
           d. GenerateExamQuestionsAction → sync exam_question
              (lỗi thiếu pool → ValidationException, rollback transaction)
      → CreateQuestionSessionAction (mode=Exam, source=Exam, examId)
           SessionQuestionSelector lấy đúng danh sách exam_question theo order
           time_limit_seconds = duration_minutes × 60
      → redirect phòng thi exam.session

[3] Làm lại đề cũ
    POST /exams/{exam}/start  (chỉ owner)
    → session mới, cùng snapshot exam_question
```

### 4.1 Phân quyền & gói

| Điều kiện | Hành vi |
|-----------|---------|
| Chưa đăng nhập / thiếu `exam.view` | Không vào list |
| Thiếu entitlement `exam.simulation` | Card khóa → CTA nâng cấp; POST tạo bị middleware chặn |
| Blueprint không `Active` | 404 |
| Ma trận `ready=false` | Nút disabled + lý do |
| Pool thiếu câu | Error flash, không tạo exam/session |

---

## 5. Giai đoạn A — Phân bổ quota (`BlueprintExamAllocator`)

**File:** `Modules/QuestionBank/app/Support/BlueprintExamAllocator.php`

Chỉ làm việc với **số câu theo CCT**, chưa đụng NHCH.

### 5.1 Điều kiện `ready`

| Check | `reason` (rút gọn) |
|-------|---------------------|
| `total_questions ≤ 0` | Chưa cấu hình tổng số câu |
| Không có section Active | Chưa có phần |
| Section thiếu cả min và max | Phần «…» chưa có tỉ trọng min/max |
| Section không có CCT Active | Phần «…» chưa có chủ đề lâm sàng |
| CCT thiếu `weight` | Chủ đề «…» chưa có tỉ trọng |

### 5.2 Share của phần (section)

```text
mid = midpoint(weight_min, weight_max)
    = (min + max) / 2     nếu cả hai có
    = min hoặc max        nếu chỉ một bên
```

Ví dụ phần Nội 35–45% → share = **40**.

### 5.3 Largest-remainder (Hamilton) — `distributeByShare(total, shares[])`

Đảm bảo **tổng số nguyên = đúng `total`** (không lệch vì làm tròn).

```text
1. Chuẩn hóa: p_i = share_i / Σ share
2. exact_i = total × p_i
3. floor_i = ⌊exact_i⌋
4. remainder_i = exact_i − floor_i
5. left = total − Σ floor
6. Cộng +1 cho các chỉ số có remainder lớn nhất (arsort), đến hết left
7. Fallback nếu Σ share = 0: chia đều + dư cho phần tử đầu
```

Áp dụng **hai tầng**:

1. Phân `N = total_questions` → số câu từng **section** theo midpoint %.
2. Trong mỗi section, phân `sectionQuota` → số câu từng **CCT** theo `topic.weight`.

### 5.4 Ví dụ số

Ma trận N = **100**:

| Phần | min–max | mid | Quota phần |
|------|---------|-----|------------|
| Nội | 40–40 | 40 | 40 |
| Ngoại | 60–60 | 60 | 60 |

Trong Nội (40 câu), Tim mạch 50% + Hô hấp 50% → **20 + 20**.  
Ngoại (60 câu), một CCT 100% → **60**.

*(Khớp test `BlueprintExamAllocatorTest`.)*

### 5.5 Output allocator → `exam_topics`

`CreateLearnerExamFromBlueprintAction` duyệt `sections[].topics[]`, bỏ CCT có `question_count = 0`, tạo:

```text
exam_topics(
  exam_id,
  core_clinical_topic_id,
  question_count,      ← từ allocator
  difficulty_counts,   ← null khi học viên tạo (không tách độ khó)
  sort_order
)
```

`difficulty_counts` vẫn được `GenerateExamQuestionsAction` hỗ trợ (path admin/legacy): nếu tổng counts > 0 thì pick **theo từng độ khó**; nếu không → pick gộp theo `question_count`.

---

## 6. Giai đoạn B — Chọn câu từ ngân hàng (`GenerateExamQuestionsAction`)

**File:** `Modules/Exam/app/Actions/GenerateExamQuestionsAction.php`  
**Filter:** `Modules/QuestionBank/app/Support/QuestionFilterBuilder.php`

### 6.1 Ngân hàng đã xuất bản

Mỗi câu được xét phải **available trong ngân hàng** (`ServePublishedQuestion::scopeAvailable`):

```text
status = published
  HOẶC có published_version > 0 và không private/retired
[+ difficulty = … nếu đang đi theo difficulty_counts]
```

Câu `private` bị ẩn khỏi ngân hàng → không pick vào bài thi mới.

### 6.2 Thuật toán `pick(cctId, needed, difficulty?, usedIds)`

Hai vòng, **không random** — deterministic theo `created_at DESC` (câu mới hơn ưu tiên):

```text
Pass 1 — Priority lessons
  Filter: khớp CCT qua bài học có is_priority = true
  (whereMatchesCoreClinicalTopicPriorityLessons — KHÔNG dùng tag)
  Exclude: id ∈ usedQuestionIds
  Order: created_at DESC
  Limit: needed

Pass 2 — Full CCT scope (nếu còn thiếu)
  Filter: khớp CCT qua (lessons đã map ∪ tags đã map)
  (whereMatchesCoreClinicalTopic)
  Exclude: used ∪ đã pick pass 1
  Order: created_at DESC
  Limit: stillNeeded
```

### 6.3 Khớp CCT ↔ câu (`QuestionFilterBuilder`)

```text
resolve CCT id(s)
  → lesson_ids từ core_topic_lessons
  → tag_ids từ core_topic_tags
  → question WHERE
        has lesson ∈ lesson_ids
     OR has tag ∈ tag_ids
```

Nếu cả lesson lẫn tag map đều rỗng → query `0 = 1` (không eligible).

### 6.4 Dedup toàn đề

Mảng `usedQuestionIds` tích lũy qua mọi `exam_topics`. Một câu **không** xuất hiện hai lần dù thuộc nhiều CCT (nếu map chồng). Pivot lưu `core_clinical_topic_id` của **slot CCT đang pick**.

### 6.5 Validation thiếu pool

Với mỗi CCT (hoặc CCT × difficulty):

```text
if |picked| < needed:
  errors[] = "{CCT} cần X câu nhưng chỉ có Y eligible (thiếu Z)."
```

Nếu `errors !== []` → **không** `sync` pivot; action ném ValidationException → transaction rollback.  
**Không** lấy câu ngoài CCT đó để bù.

Khi đủ: `exam->questions()->sync(syncData)` với `order` tăng dần 1…N và `core_clinical_topic_id` pivot.

---

## 7. Giai đoạn C — Vào phòng thi (session)

`SessionQuestionSelector::forSession` khi có `examId`:

```text
SELECT question_id FROM exam_question
WHERE exam_id = ?
ORDER BY `order`
```

Không shuffle, không adaptive, không filter QBank published. Snapshot session (`question_session_snapshots`) khóa nội dung câu tại thời điểm tạo phiên.

Timer: `time_limit_seconds = exams.duration_minutes * 60` (ghi đè default ~90s/câu của CreateQuestionSessionAction).

---

## 8. Sơ đồ tổng hợp

```text
┌─────────────────────────────────────────────────────────────┐
│  Admin: Blueprint weight matrix + CCT ↔ Lesson/Tag mapping  │
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
              BlueprintExamAllocator.allocate()
              (midpoint % + largest remainder)
                             │
                             ▼
                   exam_topics quotas (CCT → n)
                             │
                             ▼
         GenerateExamQuestionsAction.pick()
         published bank · priority lesson → full CCT
         created_at DESC · no cross-CCT backfill
                             │
                             ▼
                    exam_question snapshot
                             │
                             ▼
         QuestionSession (mode=exam) · timer · player
```

---

## 9. So với SRS Module 38 & Adaptive

| Khía cạnh | SRS 38 (mô tả wizard Admin) | Implementation hiện tại |
|-----------|-----------------------------|-------------------------|
| Ai generate đề | Admin wizard → publish | **Học viên** từ `/exams` |
| Quota CCT | Admin nhập `question_count` tay | **Tự suy** từ tỉ trọng ma trận |
| Sort pool | `created_at DESC` take N | Giống + **priority lesson** trước |
| Difficulty split | Không bắt buộc trong SRS | Có cột `difficulty_counts` (learner path = null) |
| Admin `/admin/exams` | CRUD + generate | Chỉ list/show/destroy giám sát |

| Khía cạnh | Exam từ ma trận (doc này) | Adaptive QBank |
|-----------|---------------------------|----------------|
| Pool | Ngân hàng đã xuất bản | Published (+ entitlement) |
| Mục tiêu | Khớp tỉ trọng đề chuẩn | Vá điểm yếu / chống quên |
| Chọn câu | Deterministic newest-first | Weighted random + cooldown |
| Output | Snapshot cố định trên `exams` | Mỗi session một bộ mới |

---

## 10. Edge cases & hành vi

| Case | Xử lý |
|------|--------|
| Section mid hợp lệ nhưng mọi CCT weight → quota 0 sau làm tròn | Bỏ topic; nếu không còn topic nào → lỗi «không phân bổ được chủ đề» |
| Hai CCT share cùng lesson → câu mới nhất có thể bị CCT xử lý trước “hút” hết | CCT sau thiếu → lỗi rõ tên CCT (không soft-fail) |
| Priority lessons trống | Pass 1 = 0 câu; Pass 2 lấy full map |
| Chỉ map tag, không map lesson | Pass 1 fail; Pass 2 vẫn lấy qua tag |
| Học viên tạo 2 lần cùng kỳ thi | 2 `exams` riêng; câu có thể trùng nếu pool hẹp |
| Làm lại | `exam.start` — cùng `exam_question`, session mới |
| Bài thi của user khác | 403 |
| Ma trận sửa sau khi đã tạo bài | Bài cũ giữ snapshot; lần tạo mới dùng ma trận mới |

---

## 11. File code chính

| Vai trò | Path |
|---------|------|
| Allocator (quota) | `Modules/QuestionBank/app/Support/BlueprintExamAllocator.php` |
| Filter CCT / priority | `Modules/QuestionBank/app/Support/QuestionFilterBuilder.php` |
| Tạo bài thi learner | `Modules/Exam/app/Actions/CreateLearnerExamFromBlueprintAction.php` |
| Pick + sync câu | `Modules/Exam/app/Actions/GenerateExamQuestionsAction.php` |
| Catalog `/exams` | `Modules/Exam/app/Services/ExamCatalogService.php` |
| HTTP tạo + vào thi | `Modules/Exam/app/Http/Controllers/CreateExamFromBlueprintController.php` |
| Session lấy đề | `Modules/QuestionBank/app/Services/SessionQuestionSelector.php` |
| UI tỉ trọng | `Modules/Admin/resources/views/blueprints/form.blade.php` |
| Tests | `Modules/QuestionBank/tests/Unit/BlueprintExamAllocatorTest.php` · `Modules/Exam/tests/Feature/ExamModuleTest.php` |

---

## 12. Checklist vận hành (để kỳ thi «sẵn sàng»)

1. Blueprint `status = active`, có `total_questions`.
2. Mỗi phần Active có `weight_min`/`weight_max` (hoặc ít nhất một) và ≥1 CCT Active có `weight`.
3. CCT đã map ≥1 bài học (nên gắn **trọng điểm**) và/hoặc tag.
4. Trong ngân hàng đủ câu đã xuất bản gắn đúng lesson/tag — số eligible ≥ quota từng CCT.
5. Học viên có entitlement mô phỏng thi + permission `exam.take`.

Khi card `/exams` hiện «Chưa sẵn sàng», đọc `reason` từ allocator; khi tạo fail sau ready, đọc lỗi eligible count từng CCT.
