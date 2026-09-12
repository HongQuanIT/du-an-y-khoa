# Adaptive Practice — Thuật toán chọn câu

| | |
|--|--|
| **Module** | Question Bank · Session type *Thích ứng* |
| **SRS** | `srs/modules/05-question-bank.md` |
| **UI** | `/qbank` · source `weak_topics` · focus `weak_focus` \| `balanced` \| `retention` |
| **Bản giải thích ngắn** | [`adaptive-session-explained.md`](./adaptive-session-explained.md) |
| **Trạng thái** | UI + migration + **selector V3 (weighted)** đã áp dụng khi tạo phiên thích ứng |

---

## 1. Mục tiêu

Học viên chọn **đề thi (blueprint)** + **hướng luyện**. Hệ thống tự chọn N câu trong phạm vi ma trận:

| Mục tiêu học | Cách hệ thống thể hiện |
|--------------|------------------------|
| Vá lỗ hổng | Ưu tiên câu có **Weakness** cao |
| Chống quên | Ưu tiên câu **lâu chưa gặp** (**Memory** / `last_seen`) |
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

### 3.1 Còn câu chưa làm (`unseen > 0`)

Dành một phần chỗ cho câu mới (coverage), phần còn lại chấm điểm câu đã gặp.

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

`attempt_count` ở đây = số lần **đã chấm** (đúng hoặc sai). **Omit không tính vào mẫu wrong-rate** (tránh làm loãng/làm méo weakness), nhưng **có cập nhật `last_seen_at`** (xem §4.2).

### 4.2 Memory — đến hạn ôn lại

**Câu hỏi:** Đã bao lâu user **gặp** câu này (làm hoặc bỏ qua)?

```text
days = max(0, now − last_seen_at)   # đơn vị ngày
MemoryScore = 1 − exp(−days / T)    # đề xuất T = 20
```

| `last_seen_at` cách đây | MemoryScore (T=20) |
|-------------------------|--------------------|
| 1 ngày | ~0.05 |
| 10 ngày | ~0.39 |
| 40 ngày | ~0.86 |
| 60+ ngày | ~0.95 → bão hòa |

**Định nghĩa `last_seen_at` (quan trọng):**

> Timestamp lần gần nhất user **đã tiếp xúc có ý nghĩa** với câu: **trả lời (đúng/sai)** hoặc **bỏ qua / omit**.

| Sự kiện | Cập nhật `last_seen_at`? | Ghi chú |
|---------|--------------------------|---------|
| Trả lời đúng / sai | Có | Đồng thời cập nhật counters weakness |
| Bỏ qua / omit (`is_correct = null`) | Có | Không tăng `wrong_count` |
| Chỉ nằm trong session nhưng chưa mở (chưa serve UI) | Không (V1) | |
| Mở câu trên player (optional V2) | Có thể | Nếu muốn Memory nhạy với “đã xem stem” |

`last_attempt_at` hiện có trên `question_status` ≈ lần attempt gần nhất; **không thay thế** `last_seen_at` nếu sau này tách “xem” khỏi “attempt”. V1 có thể backfill `last_seen_at = last_attempt_at` rồi ghi cả omit.

### 4.3 Cooldown — tạm tránh lặp session

**Câu hỏi:** Câu này vừa được **đưa vào** session gần đây chưa?

Không đo bằng ngày (đó là Memory). Đo bằng **lần serve gần nhất**:

```text
SelectionWeight = max(ε, BasePriority × CooldownFactor)
```

| Sessions kể từ `last_served_at` | CooldownFactor |
|---------------------------------|----------------|
| 0 và serve ≤ 2 ngày trước | 0.3 |
| 1 | 0.1 |
| ≥2 **hoặc** serve đã > 2 ngày mà chưa có session mới | 1.0 |

(Tránh phạt oan câu serve lâu rồi khi user chưa mở session mới.)

**Need to review ≠ Can review now.**

---

## 5. Ba mode (`adaptive_focus`)

Cùng công thức, khác trọng số:

```text
BasePriority = w_W × WeaknessScore + w_M × MemoryScore
```

| Mode UI | Key | w_W | w_M | Khi nào dùng |
|---------|-----|----:|----:|--------------|
| **Điểm yếu** | `weak_focus` | 0.85 | 0.15 | Sát thi, vá lỗ hổng |
| **Cân bằng** *(default)* | `balanced` | 0.55 | 0.45 | Luyện ngày thường |
| **Củng cố** | `retention` | 0.30 | 0.70 | Chống quên |

Ví dụ cùng cặp câu:

| | Q yếu mới làm (W=0.90, M=0.05) | Q khá vững 40 ngày (W=0.20, M=0.86) |
|--|--:|--:|
| Điểm yếu | **0.77** | 0.30 |
| Cân bằng | 0.52 | 0.50 |
| Củng cố | 0.31 | **0.66** |

---

## 6. Hiện trạng dữ liệu vs nhu cầu

### Đã có

| Nguồn | Field | Dùng cho |
|-------|-------|----------|
| `question_status` | `status`, `attempts_count`, `last_attempt_at`, `last_correct_at` | Filter QBank; gần với attempt gần nhất |
| `question_attempts` | `is_correct`, `answered_at`, … | Lịch sử thô; omit = `is_correct` null |
| `question_sessions` | `filters` JSON | Có thể lưu `adaptive_focus` |
| Selector hiện tại | Incorrect-first → unseen → top-up | Chưa có score / mode / cooldown |

### Còn thiếu (cần migrate / rollup)

| Nhu cầu thuật toán | Thiếu gì hôm nay |
|--------------------|------------------|
| Memory theo “đã làm **hoặc** bỏ qua” | Chưa có `last_seen_at` tường minh; omit có thể không đồng nhất với “seen” |
| Weakness nhanh (không aggregate mỗi request) | Chưa có `correct_count` / `wrong_count` trên cache user×question |
| Cooldown theo session serve | Chưa có `last_served_at` / `last_served_session_id` |
| Mode | UI đã gửi `adaptive_focus`; chưa persist / chưa dùng khi chọn câu |

---

## 7. Đề xuất migration

### 7.1 Mở rộng `question_status` (khuyến nghị — 1 hàng / user×question)

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

---

## 8. Write-path (để số liệu đúng)

| Hook | Việc cần làm |
|------|----------------|
| Tạo adaptive session (sau khi chọn N câu) | Với mỗi `question_id`: set `last_served_at = now`, `last_served_session_id` |
| `AnswerQuestionAction` (đúng/sai) | `last_seen_at = now`; ± `correct_count` / `wrong_count`; sync `status` |
| Omit / complete session bỏ trống | `last_seen_at = now`; `omitted_count++`; `status = omitted` khi phù hợp |
| (V2) Mở câu trên player | Có thể touch `last_seen_at` nếu product đồng ý “xem = gặp” |

Idempotency: re-answer cùng session không double-count (giữ rule attempt unique hiện tại).

---

## 9. Pseudo-code chọn N câu (Review)

```text
for q in pool_seen:
    W = (wrong + 1) / (correct + wrong + 2)
    M = 1 - exp(-days_since(last_seen_at) / T)
    P = w_W(mode)*W + w_M(mode)*M
    C = cooldown_factor(last_served_at, recent_sessions)
    weight[q] = max(eps, P * C)

return weighted_sample_without_replacement(pool, weight, N)
```

Hằng số V1 đề xuất: `T = 20`, `ε = 0.01`, trọng số mode §5. **Chưa tối ưu** — cần simulation trước khi khóa.

---

## 10. Lộ trình triển khai

| Phase | Việc | Kết quả |
|-------|------|---------|
| **A** | Migration `question_status` + backfill + write-path | ✅ Done — đủ dữ liệu Memory / Weakness / Cooldown |
| **B** | Persist + validate `adaptive_focus` | UI nối backend |
| **C** | `AdaptiveQuestionSelector` V1: Top-N theo Weakness (+ coverage) | Chứng minh “yếu → ưu tiên” |
| **D** | V2: + Memory theo mode | 3 mode có hiệu ứng đo được |
| **E** | V3: + Cooldown + weighted random | Hành vi Practice đích |
| **F** | Simulation + metrics | Chỉnh `T`, trọng số, cửa sổ cooldown |

**Hiện tại:** UI 3 mode đã có; selector vẫn incorrect-first.

---

## 11. Metrics & kiểm thử

- % session có ≥1 câu high-memory (ví dụ MemoryScore ≥ 0.8)
- Mean Weakness của câu được chọn vs random baseline (theo từng mode)
- Tỷ lệ trùng câu giữa 2 session liền kề (cooldown hiệu lực)
- Unit: smoothing, memory curve, mode ranking, cooldown factor
- Feature: adaptive bắt buộc blueprint; focus ảnh hưởng thứ tự ưu tiên (seed cố định trong test)

---

## 12. Quyết định đã chốt

| Chủ đề | Quyết định |
|--------|------------|
| Một tỷ lệ W/M cố định cho mọi user? | Không — 3 mode |
| Count là % priority riêng? | Không — chỉ làm mượt / tin cậy Weakness |
| Omit có tính Weakness? | Không |
| Omit có tính Memory (`last_seen`)? | **Có** |
| Elo / IRT sớm? | Không |
| Nơi lưu stats? | Mở rộng `question_status` |
