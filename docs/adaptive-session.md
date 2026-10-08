# Đặc tả phiên luyện thích ứng (Adaptive Session)

| | |
|--|--|
| **Pipeline** | `filter_group_quota_v2` (`AdaptiveLearning::PIPELINE`) |
| **Ngày** | 2026-10-06 |
| **Hợp đồng** | Quy tắc đã chốt (ngày học, cooldown, due, mode Điểm yếu) + hành vi code. Thrash **không đổi**. |

Mục **Quy tắc đã chốt** là nguồn sự thật cho các thay đổi sản phẩm. Phần còn lại mô tả pipeline; chỗ lệch với quy tắc đã chốt phải được sửa theo kế hoạch.

---

## 1. Mục tiêu

Học viên chọn **Thích ứng** trên Q-Bank → chọn hướng luyện + (tuỳ chọn) kỳ thi / hệ / môn → hệ thống chọn N câu để:

1. Vá điểm yếu (câu hay sai gần đây)
2. Chống quên (câu đã đến hạn theo độ bền)
3. Phủ câu mới (ưu tiên bài học dang dở; tỷ lệ mới giảm khi tồn đọng due cao)
4. Tránh spam (cooldown theo ngày học sau phiên thích ứng; thrash khi sai liên tiếp — giữ nguyên 72h + 2 phiên / 7 ngày)
5. Ghi được lý do chọn (`bucket` + `reason` trong adaptive log / admin briefing)

---

## 2. Phạm vi & điểm vào

### 2.1 Khi nào chạy adaptive selector

| Điều kiện | Code path |
|---|---|
| `source = weak_topics` và `lesson_ids` rỗng (UI Q-Bank Thích ứng) | `AdaptiveQuestionSelector::pick` |
| `source = weak_topics` và có `lesson_ids` (drill theo bài từ Dashboard) | `SessionQuestionSelector::legacyIncorrectFirstQuestions` — ưu tiên câu sai trong bài; **không** dùng pipeline 3 trụ |
| `source = custom` | Chọn theo bộ lọc thủ công — không adaptive |

`CreateQuestionSessionRequest` khi adaptive: ép `source=weak_topics`, xoá difficulty / status / lesson / CCT / tag / saved.

### 2.2 Hướng luyện (`adaptive_focus`)

| UI | Giá trị | Suất ôn | Khi thiếu nhóm chính |
|---|---|---|---|
| Điểm yếu | `weak_focus` | Toàn bộ suất ôn → nhóm Yếu | **Không** bù Sắp quên / `lap_day`. Hết yếu hôm nay → popup; có thể luyện thêm (xem Quy tắc đã chốt). |
| Cân bằng | `balanced` (mặc định) | `ceil(reviewSlots / 2)` Yếu; phần còn lại Sắp quên | Bù nhóm kia, rồi câu mới / `lap_day` |
| Củng cố | `retention` | Toàn bộ suất ôn → nhóm Sắp quên | **Không** bù Yếu / `lap_day` / toàn câu mới. Hết câu đến hạn → popup; có thể luyện thêm (giống Điểm yếu). |

Tỷ lệ câu mới (mọi mode, khi vẫn tạo phiên): 30% / 20% / 10% theo tồn đọng due; học viên mới → 100% mới. Mode Điểm yếu / Củng cố khi còn ít câu nhóm chính: phiên **ngắn hơn** = số nhóm chính + câu mới theo cùng tỷ lệ.

### 2.3 Luồng code

```text
custom-session.blade.php (source=weak_topics, adaptive_focus)
        │
        ▼
CreateQuestionSessionRequest → CreateSessionData
        │
        ▼
CreateQuestionSessionAction
        │  SessionQuestionSelector::forSession
        │     └─ weakTopicQuestions → AdaptiveQuestionSelector::pick
        │
        ▼
QuestionSession (question_ids)
  + markQuestionsServed → last_served_at (chỉ weak_topics)
  + AdaptiveTrace → storage/logs/adaptive.log
        │
        ├─ Study: AnswerQuestionAction
        │         → SyncUserQuestionLearningState::applyGraded
        │
        └─ CompleteQuestionSessionAction
                  → Exam: chấm batch / omit
                  → AdaptiveTrace `graded` (nếu có adaptive_trace_id)
```

| Thành phần | File |
|---|---|
| Selector | `Modules/QuestionBank/app/Services/AdaptiveQuestionSelector.php` |
| Hằng số / helpers | `Modules/QuestionBank/app/Support/AdaptiveLearning.php` |
| Độ bền / R / due | `Modules/QuestionBank/app/Support/MemoryStability.php` |
| Cập nhật state sau chấm | `Modules/QuestionBank/app/Support/SyncUserQuestionLearningState.php` |
| Router nguồn phiên | `Modules/QuestionBank/app/Services/SessionQuestionSelector.php` |
| Trace | `Modules/QuestionBank/app/Support/AdaptiveTrace.php` |
| Briefing admin | `Modules/QuestionBank/app/Services/AdaptiveSessionBriefing.php` |
| UI admin | `/admin/adaptive-log` (`AdaptiveLogController`) |
| UI học viên | `Modules/QuestionBank/resources/views/custom-session.blade.php` |

---

## 3. Dữ liệu (`question_status`)

| Cột | Vai trò trong code |
|---|---|
| `recent_results` | JSON bool[] ≤ 5 → tính W |
| `wrong_streak` | Số lần sai hợp lệ liên tiếp |
| `thrash_blocked_until` | Mốc tạm tránh |
| `content_version` | Khớp `question.published_version` |
| `memory_stability_days` | Độ bền S (ngày) |
| `last_graded_at` | Mốc lần chấm hợp lệ cuối |
| `last_served_at` / `last_served_session_id` | Lần đưa vào phiên `weak_topics` |
| `correct_count` / `wrong_count` / `attempts_count` | Counters; fallback khi `recent_results` rỗng |

Migration cột adaptive: `Modules/QuestionBank/database/migrations/2026_10_04_120000_add_adaptive_v2_fields_to_question_status.php`.

---

## 3b. Quy tắc đã chốt (2026-10-06)

Timezone: `config('app.timezone')` = `Asia/Ho_Chi_Minh`.

### Ngày học

Ngày học của một mốc thời gian = ngày lịch sau khi trừ **04:00**. Ví dụ 03:30 ngày 08/10 thuộc ngày học 07/10.

```text
study_day(t)     = calendar date of (t tại TZ app), lùi 1 ngày nếu giờ < 04:00
study_day_start  = 04:00 của study_day
next_study_day   = study_day_start + 1 ngày
```

### Cooldown phiên thích ứng

Thay nghỉ cứng 20 giờ. Chỉ khi có `last_served_at` (phiên `weak_topics`). Luyện theo bài không ghi mốc này.

```text
ready_at = max(04:00 ngày học kế tiếp, last_served_at + 8 giờ)
nghỉ ⇔ now < ready_at
```

| Làm phiên lúc | Sẵn sàng lại |
|---|---|
| 08:00 | 04:00 hôm sau |
| 22:00 | 06:00 hôm sau |
| 23:30 | 07:30 hôm sau |

### Đến hạn và lên bậc

```text
due_study_day = study_day(last_graded_at) + S ngày
đến hạn ⇔ study_day(now) ≥ due_study_day
due_at (hiển thị) = 04:00 của due_study_day
đúng + đến hạn → lên 1 bậc; đúng sớm → giữ bậc
R = 0,9^(t/S) — t vẫn là số ngày thực (giờ / 86400) để xếp hạng
```

Bậc 1 = ôn lại **ngày học hôm sau**. Học 21:00, sáng hôm sau 08:00 đúng → lên bậc.

### Mode Điểm yếu — hết câu yếu

Không bù Sắp quên / `lap_day`.

| Tình huống | Hành vi | Copy |
|---|---|---|
| Còn câu yếu eligible | Suất ôn từ Yếu; câu mới theo 30/20/10. Yếu ít hơn suất ôn → phiên ngắn = số yếu + mới theo tỷ lệ | — |
| Có câu yếu trong phạm vi nhưng tất cả đang nghỉ (cooldown; không thrash) | Preview **không** khoá Bắt đầu. Bấm Bắt đầu → popup. **Luyện tiếp** (`extra_practice=1`): lấy câu yếu đang nghỉ cooldown; vẫn ghi đúng/sai + W/streak; **không** đổi S / `last_graded_at`; **không** ghi lại `last_served_at`. **Để ngày mai** → đóng | Nếu `ready_at` sớm nhất thuộc ngày học kế tiếp: *Bạn đã làm hết câu điểm yếu hôm nay, hãy quay lại vào ngày mai.* Nếu muộn hơn: *Bạn đã làm hết câu điểm yếu hiện có. Câu tiếp theo sẵn sàng từ ngày {dd/mm}.* |
| Không còn câu W ≥ 0,5 trong phạm vi | Không tạo phiên; khoá Bắt đầu | *Bạn không còn câu yếu nào trong phạm vi này. Hãy thử Cân bằng hoặc mở rộng hệ/môn.* |

### Mode Củng cố — hết câu đến hạn

Không bù Yếu / `lap_day` / không lấy hết suất bằng câu mới.

| Tình huống | Hành vi | Copy |
|---|---|---|
| Còn câu due eligible | Suất ôn từ Sắp quên; câu mới theo 30/20/10. Due ít hơn suất ôn → phiên ngắn = số due + mới theo tỷ lệ. Nếu ngắn hơn số đã chọn → popup xác nhận trước khi vào phiên | *Bạn chọn N câu nhưng chỉ còn X câu đến hạn…* |
| Có câu due trong phạm vi nhưng tất cả đang nghỉ cooldown | Giống Điểm yếu: popup + **Luyện tiếp** lấy câu due đang nghỉ; cập nhật W, giữ S; không ghi `last_served_at` | *Bạn đã củng cố hết câu đến hạn hôm nay…* / *…sẵn sàng từ ngày {dd/mm}.* |
| Không còn câu đến hạn trong phạm vi | Không tạo phiên; khoá Bắt đầu | *Bạn không còn câu cần củng cố trong phạm vi này. Hãy thử Cân bằng hoặc Điểm yếu, hoặc mở rộng hệ/môn.* |

### Thrash — không đổi

Sai ≥ 3: 72 giờ **và** ≥ 2 phiên sau `last_graded_at`. Sai ≥ 5: `thrash_blocked_until` = lúc sai + 7 ngày.

---

## 4. Mô hình nhớ

### 4.1 Thang độ bền (`MemoryStability`)

```text
S ∈ {1, 3, 7, 14, 30, 60} ngày
```

`stepFromDays()` map giá trị `memory_stability_days` đang lưu về bậc gần nhất trên thang trước khi tính R / due / đổi bậc.

### 4.2 Còn nhớ & đến hạn

```text
t = ngày thực (giờ/86400) từ last_graded_at → now
R = 0,9 ^ (t / S)
đến hạn ⇔ study_day(now) ≥ study_day(last_graded_at) + S ngày
due_at = 04:00 của ngày học đến hạn
```

### 4.3 Đổi bậc sau lượt hợp lệ (`MemoryStability::afterGrade`)

| Tình huống | S sau |
|---|---|
| Lần đầu đúng | bậc 2 (3 ngày) |
| Lần đầu sai | bậc 1 (1 ngày) |
| Sai | về bậc 1 |
| Đúng khi đã đến hạn (theo ngày học) | lên 1 bậc (tối đa bậc 6) |
| Đúng khi chưa đến hạn | giữ bậc |

### 4.4 Lượt hợp lệ (`AdaptiveLearning::isValidGradedResponse`)

- Hợp lệ khi `time_spent_seconds * 1000 ≥ 5000`.
- Không hợp lệ: vẫn lưu attempt / status UI (đúng/sai); **không** đổi W, S, streak. Selector **không** đưa vào bucket câu mới — coi như đã làm, chỉ chưa có S/W nên không vào ôn.
- Omit (`applyOmitted`): tăng `omitted_count`; **không** đổi S / `last_graded_at` / W; vẫn là `unseen` (chưa trả lời).

### 4.5 Reset phiên bản nội dung

Khi `content_version` trên status ≠ `published_version` của câu: trước khi chấm, xoá recent / streak / thrash / S / `last_graded_at` / counters lifetime; coi như học bản mới.

---

## 5. Thuật toán chọn câu — Lọc → Phân nhóm → Phân suất

### 5.0 Pool

1. `ServePublishedQuestion::scopeAvailable` (published, hoặc có `published_version` và không retired/private).
2. Free → chỉ `is_free`.
3. Lesson theo hệ / môn (nếu có); thêm blueprint / exam catalog / profession / CCT / tag qua `QuestionFilterBuilder`.

Code **không** loại câu đang có báo lỗi mở, cũng **không** loại câu đang nằm trong phiên Active/Paused khác.

### 5.1 Lọc

| Tập | Điều kiện |
|---|---|
| `unseen` | Chưa trả lời đúng/sai cho đúng `content_version` (omit không tính) |
| `ungraded_status` | Đã trả lời trên bản hiện tại nhưng chưa có S (`last_graded_at` / lượt dưới 5s) — **không** câu mới, **không** eligible |
| Resting (loại khỏi chọn) | Đang thrash **hoặc** `now < serveReadyAt(last_served_at)` (ngày học + tối thiểu 8 giờ) |
| `eligible` | Graded hợp lệ đúng version + hết thrash + hết cooldown |

**Thrash** (`AdaptiveLearning::isThrashBlocked`)

| Tầng | Streak | Mở khóa |
|---|---|---|
| Vừa | ≥ 3 | Hết 72h **và** ≥ 2 phiên (mọi `QuestionSession` của user) sau `last_graded_at` |
| Nặng | ≥ 5 | `now < thrash_blocked_until` (set +7 ngày lúc sai) |

**Cooldown serve:** `CreateQuestionSessionAction::markQuestionsServed` chỉ ghi `last_served_at` khi `source = weak_topics`. `ready_at = max(04:00 ngày học kế tiếp, last_served_at + 8 giờ)`. Phiên custom không ghi mốc này.

### 5.2 Phân nhóm (trên `eligible`)

| Bucket | Điều kiện | Xếp hạng |
|---|---|---|
| `yeu` | `W ≥ 0.5` | W cao → R thấp → `question_id` |
| `sap_quen` | `is_due` | R thấp → W cao → `question_id` |
| `moi` | `unseen` | §5.4 |
| `lap_day` | eligible còn lại khi thiếu suất | R thấp trước |

Một câu có thể vừa yếu vừa due; khi pick chỉ lấy một lần (nhóm lấy trước theo focus).

```text
W = (số_sai + 1) / (số_lần + 2)   trên tối đa 5 phần tử recent_results
```

`recent_results` rỗng → selector dựng cửa sổ tạm từ `correct_count` / `wrong_count` (tối đa 5).

### 5.3 Phân suất

**Câu mới** — mọi focus giống nhau (`AdaptiveLearning::newQuestionQuota`):

| Band | Điều kiện | share |
|---|---|---:|
| `new` | seen (`eligible` + lượt dưới 5s chưa có S) = 0 | 100% |
| `low` | `duePool < 1 × N` | 30% |
| `mid` | `1×N ≤ duePool < 3×N` | 20% |
| `high` | `duePool ≥ 3×N` | 10% |

```text
newCount    = min(unseen, round(share × N))
reviewSlots = N − newCount
```

**Chia reviewSlots theo focus** — §2.2.

- **Điểm yếu:** không bù due / `lap_day`. Hết yếu eligible → không tạo phiên. Yếu ít hơn suất ôn → thu nhỏ số câu mới theo cùng `share` (`new ≈ round(share × yếu / (1 − share))`).
- **Cân bằng / Củng cố:** thiếu nhóm chính → bù nhóm kia; chỗ thiếu sau ôn được lấp bằng câu mới; hết unseen mới `lap_day`.

Review pick: `pickTop` — lấy cửa sổ `DIVERSITY_FACTOR × count` (2.0), shuffle, lấy `count` câu; giữ thứ tự xếp hạng gốc của các câu được chọn.

### 5.4 Câu mới — bài dang dở

Đơn vị: `lesson_id` của lesson đầu tiên gắn câu (`lessons` sort by id).

Mỗi lần lấy 1 unseen:

```text
score = (exposure_eligible_trong_bài > 0 ? 1e9 : 0) + weight / (1 + exposure)
weight = 1.0   // cố định trong code; chưa đọc trọng số ma trận
```

Trong bài: lấy từ list unseen đã `shuffle`. Reason: “tiếp tục bài đang học” hoặc “bài học mới” + tên bài.

### 5.5 Kết thúc

1. Cân bằng / Củng cố: hết unseen mà vẫn thiếu N → lấp `lap_day`. Điểm yếu: **không** lấp; phiên có thể ngắn hơn N. Preview `/qbank/create/count` trả `can_start`, `message`, `next_ready_at`.
2. `shuffle` thứ tự hiển thị toàn phiên.
3. Trace `result` (`bucket_counts`, `items`, `shortfall`, message nếu thiếu).
4. Trả `question_id[]` → `question_sessions.question_ids`.

---

## 6. Vòng đời phiên

### 6.1 Tạo

1. Trace steps: `path` → `start` → `pool` → `filter` → `group` → `quota` → `pick_review` → `pick_new` → `result`.
2. Tạo session Active; snapshot câu.
3. `markQuestionsServed`; gắn `filters.adaptive_trace_id`; step `served`; `AdaptiveTrace::finish()`.

### 6.2 Study — từng câu

1. Ghi `QuestionAttempt`, chấm ngay.
2. `applyGraded` cập nhật learning nếu hợp lệ.
3. Adaptive: lưu báo cáo vào `filters.adaptive_grades[question_id]`.
4. Hết câu → complete.

### 6.3 Complete / Exam

1. Exam: chấm batch; không chọn → omit.
2. Study đã chấm: không chạy lại learning (trừ câu chưa graded).
3. Step `graded` cùng `adaptive_trace_id`: S trước/sau, W, streak, due, note.

### 6.4 Quan sát

- Log channel `adaptive` → `storage/logs/adaptive.log`.
- Admin briefing đọc log → kể Lọc / Nhóm / Suất + resting / picked / graded.
- Player học viên **không** hiển thị `reason` từng câu (chỉ có trong log/admin).

---

## 7. Hằng số (`AdaptiveLearning` / `MemoryStability`)

| Hằng | Giá trị |
|---|---|
| `WEAK_WINDOW` | 5 |
| `WEAK_THRESHOLD` | 0.5 |
| `STUDY_DAY_HOUR` | 4 |
| `COOLDOWN_MIN_HOURS` | 8 |
| `MIN_RESPONSE_MS` | 5000 |
| `NEW_SHARE_LOW` / `MID` / `HIGH` | 0.3 / 0.2 / 0.1 |
| `MID_BACKLOG_FACTOR` / `HIGH_BACKLOG_FACTOR` | 1 / 3 |
| `DIVERSITY_FACTOR` | 2.0 |
| `THRASH_MILD_STREAK` / `SEVERE` | 3 / 5 |
| `THRASH_MILD_HOURS` / `SESSIONS` | 72 / 2 |
| `THRASH_SEVERE_DAYS` | 7 |
| `LADDER_DAYS` | 1, 3, 7, 14, 30, 60 |
| `RETENTION_BASE` | 0.9 |

---

## 8. Sơ đồ

```text
UI Thích ứng + focus
        ▼
Pool (published × entitlement × exam/profession × hệ/môn)
        ▼
Lọc: thrash · cooldown ngày học · content_version · đã làm dưới 5s
   → eligible | unseen | ungraded | resting
        ▼
Nhóm: Yếu · Due (overlap ok)
        ▼
Suất: new 30/20/10 + split theo focus
        ▼
pickTop / bài dang dở / lap_day → shuffle → Session
        ▼
Chấm (Study live / Exam complete) → question_status
        ▼
Phiên sau đọc W, S, thrash, last_served
```

---

## 9. Điểm yếu — nhìn từ góc độ học viên

Phần này đánh giá thuật toán theo các nguyên lý học tập đã được kiểm chứng: **truy hồi giãn cách** (spaced retrieval), **sửa lỗi có hướng dẫn** (error remediation), **độ khó vừa sức** (tỷ lệ đúng mục tiêu khoảng 70–85%), **xen kẽ chủ đề** (interleaving) và **bám ma trận đề thi**. Mỗi điểm yếu ghi rõ chỗ trong code và tác động lên người học.

### 9.1 Mô hình nhớ — thưởng / phạt chưa sát thực tế

**Y1. Một lần sai xoá toàn bộ tiến trình.** `MemoryStability::afterGrade` đưa mọi câu sai về bậc 1. Câu đã ở bậc 6 (60 ngày, đã nhớ qua 5 lần ôn) sai một lần vì đọc nhầm thì phải leo lại 1 → 3 → 7 → … mất khoảng 2 tháng. Học viên giỏi bị kéo vào ôn lại nhiều câu đã thực sự thuộc, trong khi lỗ hổng thật bị loãng.

**Y2. (Đã chốt sửa)** Đến hạn và lên bậc theo **ngày học**, không theo giờ `t ≥ S`. Cooldown theo ngày học + tối thiểu 8 giờ (không còn 20 giờ cứng).

**Y3. Đúng nhờ gợi ý vẫn tính là đúng trọn vẹn.** `AnswerQuestionAction` ghi `used_hint` vào attempt, nhưng `SyncUserQuestionLearningState::applyGraded` không nhận tín hiệu này. Câu đúng sau khi xem Key info / Attending tip vẫn lên bậc như tự nhớ → lịch ôn quá thưa so với mức nhớ thật.

**Y4. Đoán đúng lần đầu được thưởng như hiểu bài.** Lần đầu đúng → bậc 2 (3 ngày) bất kể thời gian làm hay có gợi ý. Với câu 4–5 phương án, xác suất đoán đúng 20–25% — câu “may mắn” bị hoãn ôn 3 ngày.

### 9.2 Vá điểm yếu — đang “né” thay vì “chữa”

**Y5. Thrash chỉ ẩn câu, không dạy lại.** **Giữ nguyên** sai ≥3 → 72 giờ + 2 phiên; ≥5 → 7 ngày. Không đổi trong đợt này.

**Y6. Điểm yếu tính theo từng câu, bỏ qua bức tranh chủ đề.** W chỉ dựa trên `recent_results` của một câu. Bảng `topic_mastery` (theo `lesson_id`: `correct_rate`, `mastery_level`, `trend`) đã có trong module Analytics nhưng selector không đọc. Học viên sai 60% cả bài “Suy tim” nhưng mỗi câu mới làm 1 lần sẽ không được ưu tiên đúng mức.

### 9.3 Phân suất — kiểm soát tồn đọng bị thủng

**Y7. Ngày học dồn dập → đổ quá nhiều câu mới.** `newQuestionQuota` dùng `count(eligible)` làm “số câu đã học” và chỉ đếm due trong `eligible`. Câu đang cooldown / thrash không được tính. Thêm vào đó, chỗ thiếu của suất ôn được lấp bằng câu mới (§5.3).
Kịch bản: học viên mới làm 3 phiên × 10 câu trong buổi sáng. Phiên 1 toàn câu mới; phiên 2, 3 thì 10 câu cũ đang cooldown → `eligible = 0` → band `new` → lại 100% câu mới. Cuối ngày đã mở 30 câu; 1–3 ngày sau toàn bộ đến hạn cùng lúc → band `high`, học viên chỉ còn ôn, cảm giác “bị kẹt”.

**Y8. Câu mới không bám trọng số đề thi.** Code đặt `weight = 1.0` dù `CoreClinicalTopic.weight` và `BlueprintSection.weight_min / weight_max` đã có dữ liệu. Chủ đề chiếm 15% đề và chủ đề chiếm 2% được mở với cùng tốc độ.

**Y9. Không biết ngày thi.** `StudyPlan.exam_target_date` có sẵn nhưng adaptive không dùng. Hai tuần trước thi, câu vẫn có thể được hẹn ôn sau 30–60 ngày (sau ngày thi), và tỷ lệ câu mới không thay đổi theo thời gian còn lại.

**Y10. Không điều tiết độ khó theo kết quả gần đây.** Sau kỳ nghỉ dài, nhóm due rất lớn và toàn câu R thấp nhất được chọn trước → phiên toàn câu khó, tỷ lệ đúng có thể dưới 50%, dễ nản. Ngược lại học viên đang đúng 95% không được tăng thử thách.

### 9.4 Trải nghiệm trong phiên

**Y11. Xáo trộn ngẫu nhiên hoàn toàn.** `shuffle($ordered)` có thể xếp 3–4 câu yếu liền nhau ở đầu phiên, hoặc 2 câu cùng bài liền nhau (lộ đáp án cho nhau, mất hiệu ứng xen kẽ).

**Y12. Học viên không biết vì sao câu xuất hiện và sau phiên mình tiến bộ gì.** `bucket` / `reason` và báo cáo `adaptive_grades` (S trước/sau, hẹn ôn) chỉ có trong log admin. Thông báo shortfall “quay lại sau 20 giờ” không đưa ra việc nên làm tiếp.

### 9.5 Lỗi vận hành và kỹ thuật

| # | Vấn đề | Chỗ trong code | Tác động |
|---|---|---|---|
| Y13 | Câu được serve nhưng bỏ qua / bỏ dở vẫn bị cooldown 20 giờ | `markQuestionsServed` ghi lúc tạo phiên | Shortfall tăng mà không học được gì |
| Y14 | Không loại câu đang nằm trong phiên Active/Paused khác | `poolQuestionIds` | Một câu xuất hiện ở 2 phiên song song |
| Y15 | Không loại câu đang có báo lỗi mở | `poolQuestionIds` | Học viên học trên nội dung có thể sai |
| Y16 | Điều kiện “2 phiên” của thrash đếm mọi phiên (kể cả custom 1 câu) | `sessionsAfter` đọc toàn bộ `QuestionSession` | Mở khoá sớm hơn ý định |
| Y17 | Câu gắn nhiều bài chỉ tính bài có id nhỏ nhất | `loadQuestionMeta` | Sai đơn vị “bài dang dở” |
| Y18 | Thiếu `recent_results` → dựng W từ counter trọn đời | khối fallback trong `pick` | User cũ bị gắn nhãn yếu theo lịch sử xa |
| Y19 | Drill Dashboard (`lesson_ids`) dùng logic incorrect-first riêng | `legacyIncorrectFirstQuestions` | Hai trải nghiệm “thích ứng” khác nhau |
| Y20 | Nạp toàn bộ `created_at` các phiên + toàn bộ status pool mỗi lần pick | `pick` | Chậm dần theo lịch sử học viên |

---

## 10. Đề xuất cải tiến — thiết kế V2.1

### 10.1 Nguyên tắc

1. **Giữ khung Lọc → Phân nhóm → Phân suất** và ngôn ngữ bucket hiện có; chỉ thay tín hiệu và quy tắc bên trong.
2. **Mỗi lượt trả lời phản ánh đúng mức nhớ thật** — phân biệt tự nhớ, nhờ gợi ý, đoán.
3. **Sai thì chữa, không chỉ né** — thrash chuyển thành nhiệm vụ học lại.
4. **Nhịp học bền vững** — giới hạn câu mới theo ngày, tồn đọng tính đủ.
5. **Bám mục tiêu thi** — trọng số ma trận và ngày thi điều khiển phủ nội dung.
6. **Học viên thấy được tiến bộ** — lý do câu, kết quả sau phiên, việc nên làm tiếp.

### 10.2 Nhóm A — Mô hình nhớ công bằng hơn

**A1. Phạt sai theo tỷ lệ (sửa Y1).**

```text
Sai:
  nếu wrong_streak (sau lượt này) ≥ 2  → bậc 1
  ngược lại                            → bậc mới = ceil(bậc_cũ / 2)

  bậc cũ:  1  2  3  4  5  6
  bậc mới: 1  1  2  2  3  3
```

Một lần sai lẻ ở bậc 6 (60 ngày) → bậc 3 (7 ngày): đủ sớm để kiểm tra lại, không xoá 2 tháng tiến trình. Sai 2 lần liên tiếp mới coi là quên thật → bậc 1.
Chạm: `MemoryStability::afterGrade` (thêm tham số `wrongStreakAfter`).

**A2. Dung sai khi xét lên bậc (sửa Y2).**

```text
Lên bậc khi đúng và t ≥ PROMOTE_TOLERANCE × S    (PROMOTE_TOLERANCE = 0,8)
Nhóm Sắp quên vẫn giữ điều kiện t ≥ S
```

Bậc 1: đúng sau ≥ 19,2 giờ là lên bậc → khớp nhịp học mỗi ngày dù lệch giờ. Bậc 6: đúng sau ≥ 48 ngày.
Chạm: `MemoryStability::afterGrade`.

**A3. Chất lượng câu trả lời (sửa Y3, Y4).**

| Lượt | `recent_results` | Bậc S | `wrong_streak` / thrash |
|---|---|---|---|
| Đúng, không gợi ý | `true` | theo A2 | reset |
| Đúng, có dùng gợi ý | `true` | **giữ bậc** (không lên) | **không** reset streak, không gỡ thrash |
| Lần đầu đúng, không gợi ý | `true` | bậc 2 | — |
| Lần đầu đúng, có gợi ý | `true` | **bậc 1** | — |
| Sai | `false` | theo A1 | +1 |

Chạm: truyền `used_hint` từ `AnswerQuestionAction` / `CompleteQuestionSessionAction` vào `applyGraded`.
Mở rộng sau (cần UI): nút “Chắc chắn / Đoán” sau khi chọn đáp án — “Đoán” đúng xử lý như “đúng có gợi ý”.

### 10.3 Nhóm B — Chữa lỗi thay vì né

**B1. Thrash → nhiệm vụ chữa lỗi (sửa Y5).**

Khi một câu vào thrash, bài học của câu được đưa vào danh sách “cần chữa” của học viên. Ở các phiên thích ứng tiếp theo trong thời gian câu bị ẩn:

```text
bucket mới: chua_loi
  ứng viên = câu cùng lesson với câu đang thrash
             (ưu tiên unseen, sau đó eligible có W cao)
  số suất  = min(2, số lesson đang cần chữa)   — lấy từ suất ôn Yếu
  reason   = "Củng cố bài {lesson}: bạn đang gặp khó ở câu tương tự"
```

Đồng thời: trang kết quả phiên hiện liên kết “Đọc lại bài {lesson}” và mở AI Tutor drawer (module 08) với ngữ cảnh câu sai.

**B2. Mở khoá sớm khi đã chữa.** Học viên trả lời đúng ≥ 2 câu `chua_loi` cùng lesson (hợp lệ, không gợi ý) → câu thrash tầng vừa được mở sau tối thiểu 24 giờ thay vì 72 giờ. Tầng nặng giữ nguyên 7 ngày.

**B3. Điểm yếu theo bài (sửa Y6).** Đọc `topic_mastery.correct_rate` theo lesson khi xếp hạng:

```text
Nhóm Yếu:      priority = W_câu + 0,3 × (1 − correct_rate_bài)
Câu mới:       trong tầng "bài dang dở", bài có correct_rate thấp được ưu tiên trước
Bài < 3 lượt:  correct_rate coi là 0,5 (trung tính)
```

### 10.4 Nhóm C — Nhịp học và bám đề thi

**C1. Tính tồn đọng đầy đủ (sửa Y7).**

```text
seen_total = số câu đã graded đúng version (gồm cả đang cooldown / thrash)
due_total  = số câu due trong seen_total
band       = theo due_total, không theo due trong eligible
band "new" (100% câu mới) chỉ khi seen_total = 0
```

**C2. Trần câu mới theo ngày (sửa Y7).**

```text
MAX_NEW_PER_DAY = 30   (mặc định; đọc từ StudyPlan nếu có mục tiêu câu/ngày)
new_today       = số câu lần đầu graded hôm nay (theo timezone học viên)
newCount        = min(newCount, MAX_NEW_PER_DAY − new_today)
```

Khi chạm trần, chỗ thiếu của suất ôn được lấp bằng `lap_day` (ôn câu chưa due, R thấp) thay vì câu mới. Reason: “Hôm nay bạn đã học đủ câu mới — ôn lại để nhớ lâu hơn”.

**C3. Trọng số ma trận thật (sửa Y8).**

```text
weight_bài = Σ CoreClinicalTopic.weight của các CCT (thuộc blueprint đang chọn) gắn bài đó
             (không chọn blueprint, hoặc weight null → 1,0)
score_mới  = (bài dang dở ? ưu tiên tầng) + weight_bài / (1 + exposure_bài)
```

Khi có `BlueprintSection.weight_min / weight_max`: nếu tỷ lệ câu đã học của một section thấp hơn `weight_min` → cộng ưu tiên cho các bài trong section đó.

**C4. Theo ngày thi (sửa Y9)** — chỉ khi học viên có `StudyPlan.exam_target_date`, D = số ngày còn lại:

| D | Câu mới | Độ bền |
|---|---|---|
| > 60 | Theo bảng 30 / 20 / 10 | Bình thường |
| 15–60 | +10% nếu độ phủ blueprint < 70% | Hẹn ôn tối đa `D / 2` ngày |
| ≤ 14 | Chỉ câu mới thuộc CCT trọng số cao chưa phủ; tối đa 10% | Hẹn ôn tối đa `D / 2` ngày; ưu tiên due + yếu |

“Hẹn ôn tối đa D/2” đảm bảo mọi câu đã học được ôn ít nhất một lần trước ngày thi.

**C5. Điều tiết độ khó theo kết quả (sửa Y10).**

```text
acc = tỷ lệ đúng hợp lệ trong 3 phiên thích ứng gần nhất (cần ≥ 15 lượt)

acc < 0,60  → giảm suất Yếu 30%, phần dời sang Sắp quên có R cao nhất (câu dễ nhớ lại)
0,60–0,90   → giữ nguyên
acc > 0,90  → tăng suất Yếu / câu mới thêm 20% (nếu chưa chạm trần C2)
```

Mục tiêu: giữ tỷ lệ đúng mỗi phiên quanh 70–85% — đủ khó để học, không quá khó để nản. Focus vẫn là ý định chính; C5 chỉ điều chỉnh trong biên.

### 10.5 Nhóm D — Trải nghiệm trong phiên

**D1. Sắp thứ tự có chủ đích thay cho `shuffle` (sửa Y11).**

```text
1. Câu đầu: câu có khả năng đúng cao nhất (Sắp quên có R cao, hoặc lap_day) — khởi động
2. Rải đều câu Yếu / chua_loi, không 2 câu Yếu liền nhau nếu tránh được
3. Không 2 câu cùng lesson liền nhau (xen kẽ chủ đề)
4. Câu cuối: câu có khả năng đúng cao — kết thúc phiên bằng thành công
5. Trong ràng buộc trên: ngẫu nhiên
```

Trần mỗi bài: tối đa `ceil(N / 3)` câu cùng lesson trong một phiên (trừ khi pool chỉ có 1–2 bài).

**D2. Hiện lý do và tiến bộ (sửa Y12).**

- Lưu `items` (bucket, reason) vào `filters.adaptive_items` lúc tạo phiên.
- Player: sau khi trả lời, hiện một dòng “Câu này xuất hiện vì: …”.
- Trang kết quả, dùng `adaptive_grades` đã có:
  - “X câu lên bậc — hẹn ôn lại sau N ngày”
  - “Y câu cần ôn lại ngày mai”
  - “Z câu tạm nghỉ — gợi ý đọc lại bài …” (từ B1)
- Shortfall: thay “quay lại sau 20 giờ” bằng nút hành động — “Mở rộng sang hệ/môn khác”, “Luyện bài đang yếu: {lesson}”, hoặc giờ cụ thể có câu đến hạn tiếp theo (min `due_at`).

### 10.6 Nhóm E — Sửa lỗi vận hành

| # | Sửa | Chạm code |
|---|---|---|
| E1 (Y13) | Khi complete phiên `weak_topics`, câu omit / không trả lời được trả `last_served_at` về giá trị trước phiên (lưu tạm trong `filters`) | `markQuestionsServed`, `CompleteQuestionSessionAction` |
| E2 (Y14) | Loại `question_ids` của các phiên Active/Paused của user | `poolQuestionIds` |
| E3 (Y15) | Loại câu có `QuestionFeedback` (báo lỗi) chưa xử lý | `poolQuestionIds` |
| E4 (Y16) | `sessionsAfter` chỉ đếm phiên `source = weak_topics` đã completed | `pick` (query sessions) |
| E5 (Y17) | Câu nhiều bài: chọn bài có exposure thấp nhất / mastery thấp nhất làm đơn vị | `loadQuestionMeta` + pick new |
| E6 (Y18) | Job backfill `recent_results` + `wrong_streak` từ 5 attempts hợp lệ gần nhất; sau đó bỏ fallback | migration / command |
| E7 (Y19) | Drill Dashboard gọi `AdaptiveQuestionSelector` với pool giới hạn theo lesson | `weakTopicQuestions` |
| E8 (Y20) | Đếm phiên bằng query có điều kiện `created_at > min(last_graded_at)`; chỉ nạp status của pool | `pick` |

### 10.7 Lộ trình

| Giai đoạn | Nội dung | Ghi chú |
|---|---|---|
| **Đã chốt — làm ngay** | Ngày học + cooldown; due/lên bậc theo ngày; mode Điểm yếu không bù + thông báo | Thrash **không** đổi |
| **Sau** | C1, A1, A3, C2–C5, D1–D2, E* (trừ phần cooldown 20h) | B1/B2 chữa-lỗi thrash **hoãn** — product giữ 72h / 7 ngày |

### 10.8 Hằng số mới đề xuất

| Hằng | Giá trị gợi ý | Nhóm |
|---|---|---|
| `PROMOTE_TOLERANCE` | 0,8 | A2 |
| `WRONG_RESET_STREAK` | 2 | A1 |
| `MAX_NEW_PER_DAY` | 30 | C2 |
| `LESSON_WEAKNESS_WEIGHT` | 0,3 | B3 |
| `REMEDIATION_SLOTS` | 2 | B1 |
| `REMEDIATION_UNLOCK_CORRECT` / `HOURS` | 2 / 24 | B2 |
| `TARGET_ACCURACY_LOW` / `HIGH` | 0,60 / 0,90 | C5 |
| `MAX_PER_LESSON_SHARE` | 1/3 | D1 |

### 10.9 Kịch bản trước / sau

| Kịch bản | Hiện tại | Sau V2.1 |
|---|---|---|
| Học mỗi tối, hôm sau sớm hơn 1 giờ, câu bậc 1 trả lời đúng | Giữ bậc 1 | Lên bậc 2 (A2) |
| Câu bậc 6 sai một lần vì đọc nhầm | Về bậc 1 (1 ngày) | Bậc 3 (7 ngày) (A1) |
| Đúng sau khi xem Key info | Lên bậc như tự nhớ | Giữ bậc, vẫn trong nhóm theo dõi (A3) |
| 3 phiên liền trong buổi sáng, học viên mới | 30 câu mới | Phiên 2–3 tính tồn đọng đủ; trần 30 câu/ngày (C1, C2) |
| Sai 3 lần liên tiếp | Ẩn 72 giờ, quay lại vẫn sai | Ẩn câu, học câu cùng bài + gợi ý đọc bài; mở sớm khi đã chữa (B1, B2) |
| Còn 10 ngày thi | Hẹn ôn 30–60 ngày, câu mới như thường | Hẹn ôn ≤ 5 ngày; câu mới chỉ CCT trọng số cao (C4) |
| Quay lại sau 2 tuần nghỉ | Phiên toàn câu R thấp, đúng < 50% | Giảm suất khó, khởi động bằng câu dễ nhớ (C5, D1) |

### 10.10 Đo lường hiệu quả

Theo dõi qua `adaptive.log` (step `result`, `graded`) và admin briefing, so sánh 2–4 tuần trước / sau mỗi giai đoạn:

| Metric | Kỳ vọng | Liên quan |
|---|---|---|
| Tỷ lệ đúng khi ôn câu due (retention thực) | ≥ 85% và ổn định | A1, A2, A3 |
| Tỷ lệ câu bậc 1 trong tổng câu đã học | ↓ | A1, A2 |
| Tỷ lệ đúng mỗi phiên | Phần lớn phiên trong 70–85% | C5 |
| Câu thrash sai lại sau khi mở khoá | ↓ | B1, B2 |
| Số câu mới / ngày và số câu due 7 ngày sau | Dao động hẹp, không có đỉnh | C1, C2 |
| Độ phủ blueprint theo trọng số CCT | Tiệm cận phân bố ma trận | C3, C4 |
| Shortfall rate | ↓ | E1, D2 |
| Tỷ lệ học viên quay lại phiên thích ứng trong 7 ngày | ↑ | D1, D2 |

---

## 11. Nghiệm thu (khớp test PHP hiện có)

Tham chiếu: `AdaptiveSessionSelectionTest`, `AdaptiveLearningTest`, `AdaptiveSessionBriefingTest`.

1. `weak_focus` lấy từ pool yếu khi đủ câu.
2. `retention` ưu tiên due.
3. `balanced` chia suất ôn giữa yếu và due.
4. Due cao → khoảng 10% câu mới.
5. Chưa có eligible → toàn câu mới.
6. Thrash ≥3 / ≥5 chặn đúng giờ + phiên.
7. Cooldown theo ngày học (04:00 + tối thiểu 8 giờ) sau serve adaptive. **Thrash giữ 72h + 2 phiên / 7 ngày.**
8. Lệch `content_version` → unseen / reset khi chấm.
9. `time_spent` dưới 5s → không đổi S/W; vẫn là đã làm (không vào câu mới).
10. Omit → không đổi S.
11. Câu mới ưu tiên bài dang dở.
12. Shortfall → ít hơn N + message gợi ý.
13. `weak_focus` / `retention` hết nhóm chính hôm nay → popup; luyện thêm cập nhật W, giữ S, không ghi `last_served_at`. Không bù toàn câu mới.

---

## 12. File liên quan (code)

| File | Vai trò |
|---|---|
| `AdaptiveQuestionSelector.php` | Chọn câu |
| `AdaptiveLearning.php` | Hằng số, W, quota, thrash, validity |
| `MemoryStability.php` | Ladder, R, due |
| `SyncUserQuestionLearningState.php` | Cập nhật state sau chấm |
| `CreateQuestionSessionAction.php` | Tạo phiên + serve |
| `AnswerQuestionAction.php` / `CompleteQuestionSessionAction.php` | Chấm + log graded |
| `AdaptiveTrace.php` / `AdaptiveSessionBriefing.php` | Log + briefing |
| `tests/Feature/AdaptiveSessionSelectionTest.php` | Feature |
| `tests/Unit/AdaptiveLearningTest.php` | Unit helpers |

---

## Phụ lục — Thuật ngữ (theo code)

| Thuật ngữ | Nghĩa |
|---|---|
| `filter_group_quota_v2` | Pipeline Lọc → Nhóm → Suất |
| Eligible | Graded đúng version + hết thrash + hết cooldown |
| Unseen | Chưa graded hợp lệ cho bản nội dung hiện tại |
| Due / `sap_quen` | `study_day(now) ≥ study_day(last_graded) + S` |
| W / `yeu` | Laplace trên ≤5 lần gần nhất; vào nhóm khi `W ≥ 0.5` |
| Shortfall | Không đủ câu để đủ N |
| Diversity window | `pickTop` trong top `2 × suất` |
| Thrash | Tạm không serve đúng `question_id` vì sai liên tiếp |
| Bài dang dở | Đã có ≥1 câu eligible trong bài và còn unseen |
| Ngày học | Ngày lịch tại TZ app, lùi 1 ngày nếu trước 04:00 |
| `trace_id` | Id gắn từ lúc pick đến step `graded` |
