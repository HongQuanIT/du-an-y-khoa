# Adaptive Practice — Thuật toán chọn câu

| | |
|--|--|
| **Module** | Question Bank · Session type *Thích ứng* |
| **SRS** | `srs/modules/05-question-bank.md` |
| **UI** | `/qbank` · source `weak_topics` · focus `weak_focus` \| `balanced` \| `retention` |
| **Bản giải thích ngắn** | [`adaptive-session-explained.md`](./adaptive-session-explained.md) |
| **Trạng thái** | **V2 ship:** Lọc → Phân nhóm → Phân suất (`filter_group_quota_v2`). Độ bền thang **1·3·7·14·30·60**, `R = 0,9^(t/S)`. Đặc tả: `mophong/khung-ly-thuyet-loc-phan-nhom-phan-suat.md` |
| **Tham chiếu thuần** | `mophong/adaptiveSession.ts` · kế hoạch: `mophong/ke-hoach-trien-khai.md` |

> **V2 (đang chạy):** pool → **Lọc** (thrash, cooldown 20h, content version) → **Phân nhóm** (Yếu W≥0.5 cửa sổ 5 / Sắp quên t≥S / Mới) → **Phân suất** câu mới 30%/20%/10% theo due (mọi mode giống nhau) → chia suất ôn theo mode → câu mới ưu tiên bài học dang dở → lấp. Độ bền: ladder + `0,9^(t/S)`. Log: `/admin/adaptive-briefing`.
>
> Phần dưới giữ mô tả **V1** (soft-score + `exp`/×2/×0.3) để đối chiếu lịch sử; không còn là production.

---

## 1. Mục tiêu

Học viên chọn **đề thi (blueprint)** + **hướng luyện**. Hệ thống tự chọn N câu trong phạm vi ma trận:

| Mục tiêu học | Cách hệ thống thể hiện |
|--------------|------------------------|
| Vá lỗ hổng | Ưu tiên câu có **Weakness** cao |
| Chống quên | Ưu tiên câu có **xác suất còn nhớ thấp** (`memory_urgency = 1 − R`), theo độ bền riêng và thời gian từ lần chấm |
| Không nhàm / không spam | **Cooldown** giảm xác suất câu vừa được đưa vào session |
| Đa dạng | **Weighted random** (không cố định Top-N) |

Nguyên tắc:

> **Priority tạo xác suất · Random quyết định câu được chọn · Cooldown tách “cần ôn” khỏi “được phép ôn ngay”.**

---

## 2. Đầu vào phiên

| Input | Bắt buộc | Ghi chú |
|-------|----------|---------|
| `blueprint_id` (đề / ma trận) | Có | Pool câu = taxonomy map của blueprint |
| `adaptive_focus` | Có (default `balanced`) | 3 mode — xem §5 |
| `count` N | Có | Số câu session |
| `mode` study \| exam | Có | Không đổi thuật toán chọn câu |

Lưu `adaptive_focus` vào `question_sessions.filters` (JSON đã có — không cần cột mới).

---

## 3. Pipeline chọn câu

```text
1. Build pool          Blueprint + published + entitlement
2. Coverage split      Unseen vs Seen
3. Score Seen          Weakness + Memory → BasePriority (theo mode)
4. Apply cooldown      BasePriority × CooldownFactor → Weight
5. Sample              Weighted random without replacement → N câu
6. Order               Shuffle nhẹ / anti-clump theo lesson (tuỳ chọn)
```

### 3.1 Còn câu chưa chấm (`unseen > 0`)

Dành một phần chỗ cho câu chưa có `last_graded_at` (coverage), phần còn lại chấm điểm câu đã chấm. Câu chỉ bị bỏ qua vẫn thuộc nhóm này: chưa có `S`.

Gợi ý quota:

```text
unseen_ratio   = unseen_count / pool_size
quota_unseen   = clamp(round(N × (0.4 + 0.5 × unseen_ratio)), 1, N)
quota_review   = N − quota_unseen
```

### 3.2 Đã làm hết pool (`unseen = 0`)

Chấm **mọi** câu trong pool → weighted sample N câu.  
Không dùng bucket cứng kiểu “40% weak / 30% low-exposure …” (các nhóm chồng lấn).

---

## 4. Ba tín hiệu

### 4.1 Weakness — mức độ yếu

**Câu hỏi:** Học viên có ổn định đúng câu này không?

```text
WeaknessScore = (wrong_count + 1) / (attempt_count + 2)   # Laplace smoothing
```

| Ví dụ | Raw wrong-rate | Smoothed |
|-------|----------------|----------|
| Sai 1/1 | 100% | **0.67** |
| Sai 8/10 | 80% | **0.75** |

→ Không kết luận “yếu hơn” chỉ vì mới sai 1 lần.

`attempt_count` ở đây = số lần **đã chấm** (đúng hoặc sai). **Omit không tính vào mẫu wrong-rate** (tránh làm loãng/làm méo weakness) và **không đổi độ bền nhớ** (xem §4.2).

### 4.2 Memory — đường cong quên theo độ bền

**Câu hỏi:** Nếu đưa câu này ra bây giờ, học viên còn nhớ được bao nhiêu?

Hai câu cùng số ngày chưa gặp có mức nhớ khác nhau nếu lịch sử đúng/sai khác nhau. Memory vì vậy tách thành độ bền (lưu) và xác suất còn nhớ (tính lúc chọn câu).

```text
S     = memory_stability_days          # lưu trên user × câu
t     = max(0, now − last_graded_at)   # ngày kể từ lần chấm đúng/sai gần nhất
R     = exp(−t / S)                    # xác suất còn nhớ
urgency = 1 − R                        # MemoryScore dùng trong §5
```

`R` không ghi xuống database. Ngày hôm sau `t` đổi nên `R` đổi.

| Cùng 10 ngày kể từ lần chấm | S | R | Urgency |
|-----------------------------|--:|--:|--------:|
| Đúng nhiều lần | 32 | ~0.73 | 0.27 |
| Sai nhiều lần (kẹp sàn) | 0.5 | ~0 | ~1.0 |

**Cập nhật `S` (V1, rule-based).** Hệ số là điểm xuất phát để hiệu chỉnh bằng dữ liệu thật, chưa khóa cứng.

```text
Chưa từng chấm          S = null          # câu unseen, không vào Memory
Lần chấm đầu            S₀ = 1 ngày, rồi áp rule dưới
Đúng                    S = clamp(S × 2,   0.5, 365)
Sai                     S = clamp(S × 0.3, 0.5, 365)
```

Thứ tự: nhân hệ số trước, kẹp sau. Lần sai đầu: `1 × 0.3 = 0.3` rồi kẹp lên `0.5`.

**`last_seen_at` vẫn là “đã gặp”, không phải đồng hồ quên.**

> Timestamp lần gần nhất user **đã tiếp xúc có ý nghĩa** với câu: **trả lời (đúng/sai)** hoặc **bỏ qua / omit**.

| Sự kiện | `last_seen_at` | `last_graded_at` | `S` |
|---------|----------------|------------------|-----|
| Trả lời đúng / sai | Có | Có (`now`) | **V2:** đổi bậc trên thang 1·3·7·14·30·60 (sai→1; đúng đúng hạn→lên; đúng sớm→giữ). *V1 lịch sử: ×2/×0.3* |
| Bỏ qua / omit (`is_correct = null`) | Có | Không | Không đổi |
| Chỉ nằm trong session nhưng chưa mở | Không | Không | Không |
| Mở câu trên player (optional V2) | Có thể | Không | Không |

`last_attempt_at` hiện cập nhật cả omit, nên **không dùng làm `t`**. Đồng hồ `R(t)` là `last_graded_at`.

Câu chưa chấm (`S` null) không có urgency. Chúng đi theo quota unseen ở §3.1.

### 4.3 Cooldown — tạm tránh lặp session

**Câu hỏi:** Câu này vừa được **đưa vào** session gần đây chưa?

Không đo bằng ngày (đó là Memory). Đo bằng **lần serve gần nhất**:

```text
SelectionWeight = max(ε, BasePriority × CooldownFactor)
```

| Sessions kể từ `last_served_at` | Trong vòng 2 ngày | Quá 2 ngày |
|---------------------------------|------------------:|----------:|
| 0 — câu nằm trong phiên mới nhất | 0.10 | 1.00 |
| 1 — câu không ở phiên mới nhất | 0.30 | 1.00 |
| ≥2 | 1.00 | 1.00 |

(Tránh phạt oan câu serve lâu rồi khi user chưa mở session mới.)

**Need to review ≠ Can review now.**

---

## 5. Ba mode (`adaptive_focus`)

Cùng công thức, khác trọng số:

```text
BasePriority = w_W × WeaknessScore + w_M × memory_urgency
```

| Mode UI | Key | w_W | w_M | Khi nào dùng |
|---------|-----|----:|----:|--------------|
| **Điểm yếu** | `weak_focus` | 0.85 | 0.15 | Sát thi, vá lỗ hổng |
| **Cân bằng** *(default)* | `balanced` | 0.55 | 0.45 | Luyện ngày thường |
| **Củng cố** | `retention` | 0.30 | 0.70 | Chống quên |

Ví dụ cùng cặp câu đã chấm:

| | A — vừa chấm (W=0.90, t=0, R=1, urgency=0) | D — khá vững (W=0.20, S=20, t=40, R≈0.14, urgency≈0.86) |
|--|--:|--:|
| Điểm yếu | **0.77** | 0.30 |
| Cân bằng | 0.50 | 0.50 |
| Củng cố | 0.27 | **0.66** |

`R(40/20) = e^{-2} ≈ 0.135`. Câu A vừa được chấm nên urgency về 0 dù vẫn yếu; mode Củng cố vì vậy nhường chỗ cho câu D.

---

## 6. Hiện trạng dữ liệu vs forgetting curve

Selector V3 đang chạy với `memory_urgency = 1 − exp(−t / S)` lấy `t` từ `last_graded_at` và `S` từ `memory_stability_days`.

### Đã có

| Nguồn | Field | Dùng cho |
|-------|-------|----------|
| `question_status` | `correct_count`, `wrong_count`, `omitted_count`, `last_seen_at`, `last_graded_at`, `memory_stability_days`, `last_served_at` | Weakness, đã gặp, đường cong quên, cooldown |
| `question_attempts` | `is_correct`, `answered_at` | Lịch sử thô; omit = `is_correct` null. Backfill `S` và `last_graded_at` |
| `question_sessions` | `filters.adaptive_focus` | Ba mode |

---

## 7. Schema

§7.1–7.3 và §7.5 đã có trên `question_status`.

### 7.1 Mở rộng `question_status` — đã ship

Bảng này đã là cache tiến độ; phù hợp làm **learning stats** cho adaptive.

```php
Schema::table('question_status', function (Blueprint $table): void {
    // Lần cuối user "gặp" câu: trả lời đúng/sai HOẶC bỏ qua
    $table->timestamp('last_seen_at')->nullable()->after('last_attempt_at');

    // Counters cho Weakness (omit không cộng vào đúng/sai)
    $table->unsignedInteger('correct_count')->default(0)->after('attempts_count');
    $table->unsignedInteger('wrong_count')->default(0)->after('correct_count');
    $table->unsignedInteger('omitted_count')->default(0)->after('wrong_count');

    // Cooldown: lần gần nhất câu được đưa vào session của user
    $table->timestamp('last_served_at')->nullable()->after('last_seen_at');
    $table->foreignUuid('last_served_session_id')
        ->nullable()
        ->after('last_served_at')
        ->constrained('question_sessions')
        ->nullOnDelete();

    $table->index(['user_id', 'last_seen_at']);
    $table->index(['user_id', 'last_served_at']);
});
```

**Ngữ nghĩa cột mới**

| Cột | Cập nhật khi | Không cập nhật khi |
|-----|--------------|--------------------|
| `last_seen_at` | Answer correct/incorrect; omit/skip | Chỉ add câu vào session mà user chưa tương tác (V1) |
| `correct_count` / `wrong_count` | Chấm đúng / sai | Omit |
| `omitted_count` | Omit | Answer |
| `last_served_at` | Session được tạo và câu nằm trong danh sách chọn | User mở lại session cũ |
| `last_served_session_id` | Như trên | — |

**Quan hệ với cột cũ**

| Cột cũ | Giữ? | Ghi chú |
|--------|------|---------|
| `last_attempt_at` | Có | Lần attempt gần nhất (đúng/sai/omit tùy write-path hiện tại) |
| `last_correct_at` | Có | Không đổi |
| `attempts_count` | Có | Nên = `correct_count + wrong_count` (+ policy omit nếu product muốn) |

### 7.2 Backfill (cùng migration hoặc lệnh Artisan)

```text
Từ question_attempts (user_id, question_id):
  correct_count  = COUNT where is_correct = 1
  wrong_count    = COUNT where is_correct = 0
  omitted_count  = COUNT where is_correct IS NULL
  last_seen_at   = MAX(COALESCE(answered_at, updated_at, created_at))
                 trên mọi attempt (đúng/sai/omit)
  last_attempt_at (nếu null) = last_seen_at

Từ question_sessions + câu trong session (snapshots / attempts):
  last_served_at = MAX(session.created_at) mà câu thuộc session đó
```

### 7.3 Không cần migration riêng

| Việc | Cách |
|------|------|
| Lưu mode | `filters.adaptive_focus` trên `question_sessions` |
| Validate request | `adaptive_focus` ∈ `weak_focus,balanced,retention` khi `source=weak_topics` |

### 7.4 (Tuỳ chọn) Bảng riêng nếu không muốn phình `question_status`

```text
user_question_learning_stats
  user_id, question_id
  correct_count, wrong_count, omitted_count
  last_seen_at, last_served_at, last_served_session_id
  unique(user_id, question_id)
```

Chỉ tách khi team muốn tách “status filter UI” khỏi “adaptive scoring”. Mặc định **không cần**.

### 7.5 Forgetting curve

Thêm trên `question_status` (cùng hàng user × câu):

```php
$table->decimal('memory_stability_days', 8, 2)->nullable()->after('last_seen_at');
$table->timestamp('last_graded_at')->nullable()->after('memory_stability_days');
$table->index(['user_id', 'last_graded_at']);
```

| Cột | Ý nghĩa | Null khi |
|-----|---------|----------|
| `memory_stability_days` | Độ bền `S`, đơn vị ngày | Chưa từng chấm |
| `last_graded_at` | Mốc tính `t` cho `R(t)` | Chưa từng chấm, và sau omit |

Backfill từ `question_attempts` đã chấm (`is_correct` không null), theo thứ tự `answered_at`:

```text
S = null
với từng lần đúng/sai, cũ → mới:
    nếu S null: S = 1
    đúng: S = clamp(S × 2, 0.5, 365)
    sai:  S = clamp(S × 0.3, 0.5, 365)
last_graded_at = MAX(answered_at) của các lần đã chấm
```

Omit không tham gia replay. Câu chỉ có omit giữ `S` null và `last_graded_at` null.

---

## 8. Write-path (để số liệu đúng)

| Hook | Việc cần làm |
|------|----------------|
| Tạo adaptive session (sau khi chọn N câu) | Với mỗi `question_id`: set `last_served_at = now`, `last_served_session_id` |
| `AnswerQuestionAction` (đúng/sai) | `last_seen_at = now`; `last_graded_at = now`; ± `correct_count` / `wrong_count`; cập nhật `S` theo §4.2; sync `status` |
| Omit / complete session bỏ trống | `last_seen_at = now`; `omitted_count++`; `status = omitted` khi phù hợp. Không đụng `S` và `last_graded_at` |
| (V2) Mở câu trên player | Có thể touch `last_seen_at` nếu product đồng ý “xem = gặp” |

Idempotency: re-answer cùng session không double-count counter và không nhân `S` lần hai (giữ rule attempt unique hiện tại).

---

## 9. Pseudo-code chọn N câu (Review)

```text
for q in pool where last_graded_at is not null:
    W = (wrong + 1) / (correct + wrong + 2)
    t = days_since(last_graded_at)
    R = exp(-t / S)
    M = 1 - R
    P = w_W(mode)*W + w_M(mode)*M
    C = cooldown_factor(last_served_at, recent_sessions)
    weight[q] = max(eps, P * C)

return weighted_sample_without_replacement(pool, weight, N)
```

Hằng số V1: `S` khởi tạo `1`, đúng `× 2`, sai `× 0.3`, kẹp `[0.5, 365]`, `ε = 0.01`, trọng số mode §5. Hệ số nhân là điểm xuất phát — chỉnh sau khi có dữ liệu thật.

---

## 10. Lộ trình triển khai

| Phase | Việc | Kết quả |
|-------|------|---------|
| **A** | Migration `question_status` + backfill + write-path | ✅ Done |
| **B** | Persist + validate `adaptive_focus` | ✅ UI nối backend |
| **C–E** | Selector V3: Weakness + cooldown + weighted random | ✅ Đang chạy |
| **G** | Forgetting curve: ladder 1·3·7·14·30·60, `R = 0,9^(t/S)` | ✅ V2 đang chạy |
| **F** | Simulation + metrics | Chỉnh hệ số `S`, trọng số mode, cửa sổ cooldown |

**Hiện tại:** UI 3 mode và selector dùng forgetting curve theo độ bền riêng từng câu.

---

## 11. Metrics & kiểm thử

- % session có ≥1 câu urgency cao (ví dụ `memory_urgency ≥ 0.8`)
- Mean Weakness của câu được chọn vs random baseline (theo từng mode)
- Tỷ lệ trùng câu giữa 2 session liền kề (cooldown hiệu lực)
- Unit: ladder `S`, `R = 0,9^(t/S)`, lên/giữ bậc, omit không đổi `S`, mode quota, thrash/cooldown
- Feature: adaptive bắt buộc blueprint; focus ảnh hưởng thứ tự ưu tiên (seed cố định trong test)

---

## 12. Quyết định đã chốt

| Chủ đề | Quyết định |
|--------|------------|
| Một tỷ lệ W/M cố định cho mọi user? | Không — 3 mode |
| Count là % priority riêng? | Không — chỉ làm mượt / tin cậy Weakness |
| Omit có tính Weakness? | Không |
| Omit có cập nhật `last_seen_at`? | Có — đánh dấu đã gặp |
| Omit có đổi `S` hoặc `last_graded_at`? | Không — đồng hồ quên giữ nguyên |
| Memory Engine của adaptive practice | V2: `R = 0,9^(t/S)`, đến hạn `t ≥ S`. Flashcard giữ SM-2 ở module 18 |
| Hệ số `S` | V2: thang **1·3·7·14·30·60**. Sai → 1; đúng đúng hạn → lên bậc; đúng sớm → giữ. V1 (×2/×0.3/`exp`) chỉ còn tài liệu lịch sử |
| Elo / IRT sớm? | Không |
| Nơi lưu stats? | Mở rộng `question_status` (`memory_stability_days`, `last_graded_at`) |
