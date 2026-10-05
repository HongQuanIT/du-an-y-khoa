# Kế hoạch triển khai — khung Lọc · Phân nhóm · Phân suất

| | |
|--|--|
| **Đặc tả** | [`khung-ly-thuyet-loc-phan-nhom-phan-suat.md`](./khung-ly-thuyet-loc-phan-nhom-phan-suat.md) · [`final.md`](./final.md) · `mophong/adaptiveSession.ts` |
| **Production hiện tại** | `AdaptiveQuestionSelector` · `MemoryStability` · `docs/adaptive-session-algorithm.md` |
| **UI / log** | `/admin/adaptive-briefing` · `storage/logs/adaptive.log` · `AdaptiveSessionBriefing` |
| **Mục tiêu** | Đưa selector PHP về pipeline 3 trụ; log/briefing kể đúng Lọc → Nhóm → Suất |
| **Trạng thái** | **Đã triển khai code** (2026-10-04) — chạy migrate + xóa `adaptive.log` cũ trên môi trường |

---

## 0. Quyết định kiến trúc (chốt trước khi code)

| Hạng mục | Quyết định đề xuất | Lý do |
|---|---|---|
| Pipeline chọn câu | **Đổi** sang Lọc → Phân nhóm → Phân suất (+ pickTop / lấp) | Khớp khung đã nghiệm thu |
| Độ bền `S` / công thức `R` | **Thang bậc** 1·3·7·14·30·60 + `R = 0,9^(t/S)` (**đã ship**) | Khớp mophong / Excel; cột `memory_stability_days` lưu S trên thang |
| Weakness | **Đổi** sang cửa sổ 5 lần gần nhất (+ Laplace) | Đã chốt trong khung |
| New share | **Đổi** 30% / 20% / 10% theo due, **mọi mode giống nhau** | Đã chốt |
| Câu mới | **Đổi** ưu tiên bài học dang dở rồi ma trận | Đã chốt §3.2.1 |
| Thrash cooldown | **≥3 → 72h+2 phiên; ≥5 → 7 ngày** | Đã chốt §3.1.1 |
| Cooldown serve | Phase 1: **giữ mềm** production + optional sàn cứng ngắn; Phase 2: cân nhắc 20h cứng như mẫu | Giảm shortfall đột ngột |
| Ladder bậc cố định | **Hoãn** Phase 3 (chỉ khi product muốn) | Chi phí cao, không chặn 3 trụ |
| Adaptive log + admin UI | **Làm lại** cùng PR selector (hoặc PR liền kề bắt buộc) | Log cũ gắn `weight`/`coverage_split` sẽ **sai nghĩa** |

> **Không** ship selector mới mà để briefing cũ: CEO/QA sẽ đọc “điểm ưu tiên / % suất” trong khi engine đã chuyển bucket.

---

## 1. Phạm vi ảnh hưởng (inventory)

### 1.1 Core chọn câu & tạo phiên

| File | Ảnh hưởng | Mức |
|---|---|---|
| `Modules/QuestionBank/app/Services/AdaptiveQuestionSelector.php` | Viết lại `pick()` theo 3 trụ | **P0** |
| `Modules/QuestionBank/app/Support/MemoryStability.php` | Đã đổi sang ladder + `0,9^(t/S)` | done |
| `Modules/QuestionBank/app/Actions/CreateQuestionSessionAction.php` | `last_served_*`; có thể ghi `bucket`/`reason` vào snapshot/filters | P0 |
| `Modules/QuestionBank/app/Actions/AnswerQuestionAction.php` | recent results, streak_wrong, thrash, minResponseMs, contentVersion reset | **P0** |
| `Modules/QuestionBank/app/Data/CreateSessionData.php` | Không đổi contract `adaptive_focus` (map mode) | — |
| `Modules/QuestionBank/app/Services/QuestionSessionSnapshots.php` | Nếu lưu `optionOrder` / reason per item | P1 |

### 1.2 Schema / model

| Hạng mục | Cần? | Ghi chú |
|---|---|---|
| `question_status.recent_results` JSON (bool[], max 5) | **Có** | Hoặc derive từ `question_attempts` mỗi lần pick (nặng hơn) |
| `question_status.wrong_streak` int | Nên có | Hoặc derive chuỗi sai từ attempts |
| `question_status.thrash_blocked_until` timestamp null | Nên có | Cache mở khóa; tránh scan phức tạp |
| `question_status.question_content_version` / so khớp published version | **Có** | Reset state khi nội dung đổi |
| `memory_stability_days` / `last_graded_at` / `last_served_*` | Giữ | Đồng hồ quên + serve |
| Migration backfill recent_results / streak từ attempts | **Có** | Job hoặc cùng migration |

### 1.3 Log & admin

| File | Ảnh hưởng | Mức |
|---|---|---|
| `Modules/QuestionBank/app/Support/AdaptiveTrace.php` | Giữ channel; **đổi tên step** | P0 |
| `Modules/QuestionBank/app/Services/AdaptiveSessionBriefing.php` | Viết lại parser + copy + bảng | **P0** |
| `Modules/Admin/resources/views/adaptive-log.blade.php` | Cột mới: nhóm, suất, lý do; bỏ “điểm ưu tiên” làm trục chính | **P0** |
| `Modules/Admin/app/Http/Controllers/AdaptiveLogController.php` | Ít đổi nếu briefing API ổn | P1 |
| `config/logging.php` channel `adaptive` | Giữ path; **xóa/rotate log cũ** khi đổi schema | P0 ops |

### 1.4 Docs & test

| File | Ảnh hưởng |
|---|---|
| `docs/adaptive-session-algorithm.md` | Đánh dấu V1 soft-score **deprecated**; mô tả V2 3 trụ (hoặc link `mophong/`) |
| `docs/adaptive-session-explained.md` | Copy product theo bucket/suất |
| `Modules/QuestionBank/tests/Feature/AdaptiveSessionSelectionTest.php` | Viết lại kịch bản |
| `Modules/QuestionBank/tests/Unit/AdaptiveSessionBriefingTest.php` | Fixtures log mới |
| `Modules/Admin/tests/Feature/AdaptiveLogBriefingTest.php` | UI/HTTP |
| `mophong/*` | Giữ làm golden reference (TS) |

### 1.5 UI học viên (có thể Phase 2)

| File | Ảnh hưởng |
|---|---|
| `custom-session.blade.php` | Copy 3 mode vẫn OK; có thể thêm dòng “phiên có ~X câu mới” |
| Summary / player | Optional: hiện `reason` từng câu |

---

## 2. Lộ trình theo phase

### Phase 0 — Align & freeze contract (0.5–1 ngày)

- [ ] Chốt bảng quyết định §0 với product (S/R giữ; new share 30/20/10; thrash; bài dang dở).
- [ ] Viết ADR ngắn trong `docs/` hoặc cập nhật header `adaptive-session-algorithm.md`: **V2 = 3 trụ**.
- [ ] Định nghĩa **schema log V2** (§4) — frozen trước khi code selector.
- [ ] Quyết định: xóa `adaptive.log` khi deploy (khuyến nghị) hay dual-read legacy trong briefing.

**Done khi:** schema log + mapping mode/`isDue`/weakness được ghi rõ, không tranh cãi lúc PR.

### Phase 1 — Data path (grade + state) (1–2 ngày)

Làm **trước hoặc song song** selector — nếu chỉ đổi pick mà grade vẫn lifetime weakness thì nhóm Yếu sai.

1. Migration cột (hoặc derive tạm từ attempts).
2. `AnswerQuestionAction`:
   - cập nhật `recent_results` (max 5);
   - `wrong_streak` / clear khi đúng;
   - set `thrash_blocked_until` theo §3.1.1;
   - `minResponseMs` (nếu có `time_spent` / response ms) → không đổi S/W;
   - so `content_version` → reset learning fields khi lệch.
3. Backfill: N attempts gần nhất / streak hiện tại.
4. Unit test grade path độc lập selector.

**Cảnh báo bug**

| Rủi ro | Cách tránh |
|---|---|
| Omit / chưa nộp bị tính sai → tăng streak | Chỉ graded `is_correct !== null` + hợp lệ thời gian |
| Double submit / idempotency làm streak ×2 | Tôn trọng Idempotency-Key hiện có; test |
| Exam mode chấm cuối phiên | Cập nhật state đúng lúc grade batch, không lúc “next” trống |
| Reset content version quên xóa thrash/S | Reset atomic: S, last_graded, recent, streak, thrash |

### Phase 2 — Selector 3 trụ (2–3 ngày) **P0**

Viết lại `AdaptiveQuestionSelector::pick()` bám `mophong/buildSession`:

```text
1. pool (blueprint + published + entitlement)     // giữ
2. Lọc: report?, open session?, thrash?, serve cooldown?
3. Phân nhóm: weakPool / duePool / unseen (theo lesson)
4. Phân suất: newCount 30/20/10; reviewSlots; mode → weakQuota/dueQuota
5. pickTop weak → due (bù ngược); new theo lesson dang dở + weight
6. lap_day / shortfall
7. (tuỳ chọn) shuffle thứ tự hiển thị — hoặc giữ thứ tự bucket để dễ debug
```

Mapping mode:

| UI / `adaptive_focus` | Mode mophong |
|---|---|
| `weak_focus` | `diem_yeu` |
| `retention` | `cung_co` |
| `balanced` | `can_bang` |

**Định nghĩa “đến hạn” Phase 1 (giữ S liên tục):**

```text
t = days since last_graded_at
S = memory_stability_days
isDue ⇔ S != null && t >= S
```

(Khác giá trị `R` so với `0.9^`, nhưng cùng ý “đã qua khoảng bền”.)

**Weakness:**

```text
W = (wrong + 1) / (n + 2) trên recent_results (≤5)
vào nhóm Yếu ⇔ W >= 0.5
```

**Cảnh báo bug selector**

| Rủi ro | Cách tránh |
|---|---|
| Câu thuộc cả Yếu + Due bị chọn 2 lần | `taken` set như mophong |
| `newCount` tính trên duePool **trước** lọc thrash/cooldown → lệch suất | Tính duePool trên **eligible** sau lọc |
| Lesson dang dở: câu multi-lesson | Chọn lesson “chính” hoặc any-lesson-in-progress; ghi rõ rule |
| Blueprint weight vs lesson exposure | Weight từ ma trận blueprint section/CCT; exposure theo lesson_id |
| Shortfall tăng sau thrash + cooldown | Message UX + metric; không silent trả ít hơn N mà UI vẫn hiện N |
| Free tier / `is_free` | Giữ filter entitlement trong bước pool |
| Concurrent 2 request tạo 2 phiên | Idempotency tạo session đã có — regression test |
| `countPool` preview trên `/qbank` | Vẫn đếm pool thô; **không** hứa = N sau lọc (copy: “tối đa”) |

Port tham số từ `DEFAULT_PARAMS` mophong vào config PHP (`config/questionbank.php` hoặc class const) — **không hardcode rải rác**.

### Phase 3 — Adaptive log + admin briefing (1–2 ngày) **bắt buộc cùng/ngay sau Phase 2**

Xem §4 chi tiết. Không ship selector thiếu log mới.

### Phase 4 — Docs, golden parity, rollout (1 ngày)

- [ ] Cập nhật `docs/adaptive-session-*.md`.
- [ ] So kịch bản then-assert với `mophong` tests (cùng seed dữ liệu giả lập).
- [ ] Feature flag `adaptive_pipeline=v2` (khuyến nghị) 1–2 ngày canary.
- [ ] Xóa/rotate `adaptive.log`; thông báo team đọc admin.
- [ ] Changelog.

### Phase 5 — (Tuỳ chọn) Ladder / `0.9^` / cooldown 20h cứng

Chỉ sau khi metric Phase 2–4 ổn. Không block MVP 3 trụ.

---

## 3. Checklist kiểm thử chống bug

### 3.1 Unit / feature (PHP)

- [ ] `newQuestionCount`: seen=0 → N; due thấp/vừa/cao → 30/20/10%.
- [ ] Mode weak/retention/balanced chỉ đổi **thành phần ôn**, không đổi newCount (cùng state).
- [ ] Câu mới: bài dang dở thắng bài weight cao chưa mở.
- [ ] Thrash: 3 sai → không vào 2 phiên + 72h; 6 sai → 7 ngày; đúng 1 lần → mở.
- [ ] Content version bump → state học reset, câu vào unseen.
- [ ] minResponseMs / omit không đổi S, W, streak.
- [ ] Câu overlapping Yếu+Due chỉ 1 lần trong session.
- [ ] Shortfall + message khi lọc cạn.
- [ ] Regression: custom session (không adaptive) không đụng pipeline mới.

### 3.2 Manual / staging

- [ ] Tạo phiên adaptive 3 mode, N=10, học viên mới / mid / due cao — đối chiếu admin log.
- [ ] Học viên spam sai 1 câu → biến mất đúng cửa sổ; sibling cùng bài vẫn ra.
- [ ] Hai tab double-start.
- [ ] Câu có open report / retired giữa chừng.
- [ ] Premium vs free pool.

### 3.3 Metric theo dõi 7 ngày sau ship

| Metric | Kỳ vọng vs V1 |
|---|---|
| % câu mới / phiên (theo due band) | ~30/20/10 |
| Shortfall rate | không tăng đột biến |
| Repeat same question &lt; 72h | ↓ (thrash + cooldown) |
| Số bài học “mở dở” / user | ↓ (phủ nốt bài) |
| Accuracy nhóm Yếu | theo dõi, không regression nặng |

---

## 4. Làm lại Adaptive log cho khung 3 trụ

### 4.1 Vì sao log hiện tại không tái sử dụng được

| Step / field V1 | Vấn đề với V2 |
|---|---|
| `coverage_split` + `unseen_ratio` | Công thức quota cũ; thay bằng due-band 30/20/10 |
| `review_scores` + `weight` + `w_weakness`/`w_memory` | Không còn BasePriority mềm |
| `% suất vòng đầu` trên UI | Weighted random → thành **suất nhóm cứng + hạng trong nhóm** |
| `sheet` / `ranking` “fresh vs review” | Thiếu bucket `yeu` / `sap_quen` / `moi` / `lap_day` + `reason` |
| `formulas()` trong Briefing | Copy Laplace+exp+cooldown sessions — lệch pipeline |

→ **Breaking change có chủ đích.** Dual-read legacy chỉ trong 1–2 tuần nếu cần; mặc định rotate log.

### 4.2 Schema log V2 (đề xuất đóng băng)

Mỗi lần tạo phiên: `AdaptiveTrace::begin()` → các step → `served` → `finish()`.

| Step | Khi nào | Payload chính |
|---|---|---|
| `start` | Đầu pick | `user_id`, `limit`, `focus`, `blueprint_id`, `pipeline: "filter_group_quota_v2"` |
| `pool` | Sau build pool | `pool_size`, `lesson_scope`, entitlement flags |
| `filter` | Sau Lọc | `active_count`, `eligible_count`, `unseen_count`, `excluded`: `{ thrash, cooldown, open_session, report, version_mismatch }` counts |
| `group` | Sau Phân nhóm | `weak_pool`, `due_pool`, `unseen_by_lesson` (top), `overlap_weak_due` |
| `quota` | Sau Phân suất | `due_band`: low\|mid\|high, `new_count`, `review_slots`, `weak_quota`, `due_quota`, shares `0.3/0.2/0.1` |
| `pick_weak` / `pick_due` / `pick_new` / `pick_fill` | Sau mỗi giai đoạn lấy | `requested`, `taken[]` với `{ question_id, rank, W?, R?, t?, S?, lesson_id, reason }` |
| `result` | Tổng hợp | `items[]`: `{ question_id, position, bucket, reason, lesson_id }`, `shortfall`, `message?` |
| `served` | CreateSession | `session_id`, `count` (như hiện tại) |

`trace_id` / channel `adaptive` giữ nguyên.

### 4.3 Briefing + UI admin

**Stats cards (thay “Câu chưa chấm / Câu ôn lại”):**

- Câu được chọn  
- Câu mới / Yếu / Sắp quên / Lấp  
- Due band + new%  
- Shortfall (nếu &gt; 0)

**Bảng chọn câu (thay hạng theo weight):**

| Cột | Nội dung |
|---|---|
| STT | position |
| Mã câu | code |
| Nhóm | Yếu / Sắp quên / Mới / Lấp |
| Lý do | `reason` |
| W / R / t / S | chỉ nhóm ôn |
| Bài học | tên lesson |
| Vào phiên | luôn yes ở bảng result (bảng phụ: ứng viên bị loại ở `filter`) |

**Formulas panel:** giải thích 30/20/10, ngưỡng W≥0.5, `isDue ⇔ t≥S`, thrash — **không** còn `0.85×W+0.15×M`.

**Copy summary ví dụ:**

> Học viên A · Cân bằng · due cao → 1 câu mới (10%), 5 yếu + 4 sắp quên. Ưu tiên phủ bài Tim mạch dang dở.

### 4.4 Việc cần làm cho log (checklist)

- [ ] Thêm `pipeline` version vào `start` để briefing reject/soft-legacy.
- [ ] Rewrite `AdaptiveSessionBriefing::brief()` đọc step V2.
- [ ] Rewrite `sessionTable` / `formulas` / `summary` / `stats`.
- [ ] Update `adaptive-log.blade.php` headers + empty states.
- [ ] Update `AdaptiveSessionBriefingTest` + `AdaptiveLogBriefingTest` với fixture log V2.
- [ ] Ops: document “Deploy xong vào `/admin/adaptive-briefing` → Xóa log” (hoặc auto-rotate).
- [ ] Giữ nút xóa log hiện có.

### 4.5 Tương thích ngược

```text
nếu start.pipeline != "filter_group_quota_v2":
  hiện banner "Log phiên cũ (thuật toán trước)" + bảng rút gọn
  hoặc ẩn formulas mới
```

Sau khi xóa log: không cần dual-read lâu dài.

---

## 5. Thứ tự PR đề xuất

| PR | Nội dung | Review focus |
|---|---|---|
| **PR-A** | Migration + AnswerQuestionAction (recent, streak, thrash, version, minResponseMs) + unit tests | Đúng graded-only; không phá custom session |
| **PR-B** | `AdaptiveQuestionSelector` 3 trụ + feature flag + `AdaptiveSessionSelectionTest` | Suất 30/20/10; mode; lesson dang dở; overlap |
| **PR-C** | AdaptiveTrace steps V2 + Briefing + blade + tests; rotate log note | UI khớp bucket; không còn cột weight làm trục |
| **PR-D** | Docs `adaptive-session-*.md` + changelog + bỏ flag (nếu ổn) | Một nguồn sự thật |

Có thể gộp B+C nếu team nhỏ — **không** merge B lên production mà chưa có C.

---

## 6. Cảnh báo tổng hợp (đọc trước khi implement)

1. **Breaking log/UI** — bắt buộc làm lại briefing; log cũ gây hiểu nhầm nghiêm trọng.  
2. **Đổi weakness → lifetime sang cửa sổ** làm nhiều câu “hết yếu” đột ngột — expected; đừng “fix” bằng backfill sai.  
3. **Thrash + cooldown** tăng shortfall trên bể nhỏ / user luyện dày — cần copy + metric.  
4. **Đã dùng ladder + `0,9^(t/S)`** — admin copy “đến hạn còn nhớ 90%” khớp code. Dữ liệu S cũ (×2/×0.3) được map về bậc gần nhất khi đọc/ghi.  
5. **Multi-lesson questions** — quy tắc “bài dang dở” phải chốt 1 lesson đại diện.  
6. **Preview count trên QBank** ≠ số câu sau lọc thrash — tránh promise sai.  
7. **Exam vs Study** — adaptive thường study; nếu cho exam, omit/thrash phải test riêng.  
8. **mophong TS** vẫn là golden; mỗi thay đổi rule PHP nên port ngược test TS hoặc bảng parity.  
9. **Không flip ladder trong PR đầu** — dễ lệch scope và rollback khó.  
10. **Seed/demo learners** — sau deploy, tạo lại vài phiên adaptive để verify admin trước khi demo CEO.

---

## 7. Định nghĩa xong (Definition of Done)

- [ ] Selector PHP hành xử theo 3 trụ; new share 30/20/10 mọi mode.  
- [ ] Thrash + recent weakness + bài dang dở có test.  
- [ ] `/admin/adaptive-briefing` đọc log V2: thấy nhóm, suất, reason, due band.  
- [ ] Docs V1 soft-score đánh dấu thay thế; link `mophong/`.  
- [ ] Không regress tạo session custom.  
- [ ] Flag tắt được trong 5 phút nếu shortfall/spike lỗi.

---

## 8. Liên kết nhanh

| Tài liệu | Vai trò |
|---|---|
| [`khung-ly-thuyet-loc-phan-nhom-phan-suat.md`](./khung-ly-thuyet-loc-phan-nhom-phan-suat.md) | Đặc tả 3 trụ + thrash + câu mới |
| [`final.md`](./final.md) | So sánh production & hướng hybrid |
| [`adaptiveSession.ts`](./adaptiveSession.ts) | Reference thuần |
| `docs/adaptive-session-algorithm.md` | Đặc tả production (sẽ cập nhật thành V2) |
| `/admin/adaptive-briefing` | Mặt kiểm tra vận hành sau mỗi lần tạo phiên |
