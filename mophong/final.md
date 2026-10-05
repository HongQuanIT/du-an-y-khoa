# Final — Phân tích phương pháp tạo phiên thích ứng & hướng xử lý

| | |
|--|--|
| **Ngày** | 2026-10-03 |
| **Nguồn thiết kế** | Gói `mophong/` (đặc tả mẫu + TS + Excel + test) — artifact đi kèm hội thoại thiết kế tại [Claude share](https://claude.ai/share/dcee4dac-28f4-4b42-819d-0dce16bb88fd) |
| **Production** | `AdaptiveQuestionSelector` + `MemoryStability` · `docs/adaptive-session-algorithm.md` |
| **SRS** | `srs/modules/05-question-bank.md`, `06-question-session.md`, `04-mo-hinh-du-lieu.md` (QuestionStatus) |
| **Nghiệm thu mẫu** | 12/12 test · demo 10 phiên × 10 câu / 50 câu |
| **Khung 3 trụ** | [`khung-ly-thuyet-loc-phan-nhom-phan-suat.md`](./khung-ly-thuyet-loc-phan-nhom-phan-suat.md) — Lọc · Phân nhóm · Phân suất |
| **Kế hoạch ship** | [`ke-hoach-trien-khai.md`](./ke-hoach-trien-khai.md) — phase, ảnh hưởng, bug checklist, log V2 |

> **Ghi chú nguồn:** URL share Claude không đọc được trực tiếp từ môi trường agent (Cloudflare). Phân tích dưới đây dựa trên **toàn bộ artifact trong `mophong/`** (README gọi đây là bản tham chiếu của đặc tả thuật toán) và đối chiếu code/docs production đang chạy.

---

## 1. Bài toán

Học viên chọn **đề thi (blueprint / ma trận)** + **hướng luyện** → hệ thống tự chọn N câu:

1. Vá lỗ hổng (câu hay sai)
2. Chống quên (câu đã đến hạn ôn)
3. Phủ kiến thức mới theo trọng số đề
4. Không spam cùng câu liên tục
5. Giải thích được *vì sao* câu vào phiên (UX / QA / admin)

---

## 2. Hai phương pháp đang có

### 2.1 Production (đang ship)

Pipeline mềm:

```text
Pool (blueprint + published + entitlement)
  → chia unseen / graded
  → score graded: BasePriority = wW×Weakness + wM×(1−R)
  → × CooldownFactor (theo số phiên sau last_served)
  → weighted random without replacement
  → shuffle thứ tự hiển thị
```

| Thành phần | Cách làm |
|---|---|
| Độ bền `S` | Liên tục: lần đầu 1 ngày; đúng `×2`; sai `×0.3`; kẹp `[0.5, 365]` |
| Còn nhớ `R` | `exp(−t/S)` → tại `t = S`, `R ≈ 37%` |
| Weakness | Laplace trên **toàn bộ** `wrong/correct` lifetime |
| Mode | Trọng số mềm: Điểm yếu 85/15 · Cân bằng 55/45 · Củng cố 30/70 |
| Câu mới | `quota ≈ N × (0.4 + 0.5 × unseen_ratio)` — khá cao khi còn nhiều unseen |
| Cooldown | Mềm (hạ trọng số), không loại cứng |

**Ưu:** đã gắn schema/`adaptive.log`, mượt khi bể nhỏ, đa dạng nhờ random.  
**Nhược:** weakness lifetime khó “hồi phục”; dễ ngập câu mới; khó kể chuyện “nhóm Yếu / Sắp quên” cho học viên.

### 2.2 mophong — phương pháp đề xuất từ chat thiết kế (V2 mẫu)

Hai hàm thuần, không DB:

- `applyAttempt()` — cập nhật sau **một** lượt làm  
- `buildSession()` — dựng phiên N câu  

Pipeline cứng + xếp hạng:

```text
Lọc (published, không report, không phiên mở, cooldown ≥ 20h)
  → nhóm Yếu (W ≥ 0.5) + nhóm Sắp quên (t ≥ S)
  → số câu mới theo tồn đọng due
  → chia suất theo mode (điểm yếu / củng cố / cân bằng)
  → câu mới: bài học dang dở trước, rồi weight/(1+exposure)
  → lấp đầy (lap_day) nếu thiếu
  → đảo đáp án + reason string
```

| Thành phần | Cách làm |
|---|---|
| Độ bền `S` | Thang bậc cố định: `1, 3, 7, 14, 30, 60` ngày |
| Còn nhớ `R` | `0.9^(t/S)` → tại `t = S`, `R = 90%` (đến hạn = còn nhớ 90%) |
| Weakness | Laplace trên **5 lần hợp lệ gần nhất** |
| Mode | Suất nhóm cứng: 100% yếu / 100% sắp quên / ~50–50 |
| Câu mới | **30% / 20% / 10%** theo due thấp/vừa/cao — **mọi mode giống nhau** |
| Cooldown | Loại cứng 20 giờ kể từ `lastSelectedAt` |
| Khác | `contentVersion` reset state; `minResponseMs`; `submitted: false` không đổi W/S |

**Ưu:** minh bạch, nghiệm thu được (test + Excel), kiểm soát tồn đọng tốt, chống nhiễu.  
**Nhược:** đổi mô hình nhớ so với đã ship; phạt sai nặng (về bậc 1); dễ shortfall khi luyện dày / bể nhỏ.

---

## 3. So sánh có trọng số quyết định

| Tiêu chí | Thắng | Ghi chú |
|---|---|---|
| Giải thích cho học viên / QA | **mophong** | bucket + reason |
| Chống ngập câu mới khi due cao | **mophong** | backlog-aware |
| Weakness có thể hồi phục | **mophong** | cửa sổ 5 lần |
| Chống tap nhanh / nội dung đổi | **mophong** | minResponseMs + contentVersion |
| Bám ma trận + phủ bài dang dở | **mophong** | in-progress lesson trước, rồi topic weight |
| Đã chạy production + schema | **production** | migration đã ship |
| Ít shortfall / mượt vận hành | **production** | soft cooldown + random |
| Độ bền dài hạn mịn | **production** | S liên tục |
| Chi phí đổi công thức R / ladder | **production giữ** | `0.9^` ≠ `exp` — không trộn |

**Điểm không được trộn:** công thức `R` và ý nghĩa “đến hạn”.  
- mophong: đến hạn khi còn nhớ **90%**  
- production: đến hạn mang tính urgency cao (`1−e^{−1} ≈ 63%` quên)  

Đổi công thức = đổi pedagogic contract, không phải refactor thuần kỹ thuật.

---

## 4. Kết luận phương pháp

> **mophong là đặc tả V2 có kiểm chứng (spec + code thuần + Excel + 12 test), không phải bản port của selector hiện tại.**

Hướng đúng:

1. **Không flip toàn bộ** sang ladder + `0.9^(t/S)` trong một PR.  
2. **Giữ nền production** (`MemoryStability` liên tục + weighted sample + schema hiện có).  
3. **Port có chọn lọc** các ý mophong đã chứng minh giá trị pedagogic / vận hành.  
4. Coi `mophong/` là **contract nghiệm thu** cho các thay đổi selector (giữ test TS hoặc port sang PHPUnit tương đương).

---

## 5. Hướng xử lý đề xuất (roadmap)

### Phase A — Align tài liệu (1 PR docs)

- Ghi rõ trong `docs/adaptive-session-algorithm.md`:  
  - **V1 (lịch sử):** soft score + `exp(−t/S)` + ×2/×0.3  
  - **V2 (đã ship):** ladder 1·3·7·14·30·60 + `R = 0,9^(t/S)` + Lọc→Nhóm→Suất  
- Liên kết `mophong/README.md` + file này.

**Done khi:** docs không còn mâu thuẫn “một sự thật”.

### Phase B — Quick wins trên production (ưu tiên cao)

Port vào `AdaptiveQuestionSelector` / `AnswerQuestionAction` **kèm** ladder + `0,9^(t/S)` (không giữ ×2/`exp`):

| # | Thay đổi | Vì sao | Chạm |
|---|---|---|---|
| B1 | **newShare theo due backlog** | V1 đang đẩy ~40–90% unseen → đánh mất củng cố | `coverage_split` |
| B2 | **Weakness cửa sổ 5 lần** (hoặc decay) | Lifetime `wrong_count` khó khỏi yếu | `question_status` (+ JSON recent) hoặc derive từ attempts |
| B3 | **Reset learning state khi content version tăng** | Sửa đáp án/dữ kiện không kế thừa S cũ | publish path + status |
| B4 | **`minResponseMs` khi grade** | Bỏ spam khỏi W và S | `AnswerQuestionAction` |
| B5 | **Thrash cooldown** (đã chốt) | Sai ≥3 liên tiếp → tránh ~2 phiên + 72h; ≥6 → 7 ngày | Lọc trong selector + state/`attempts`; chi tiết `khung-ly-thuyet-…md` §3.1.1 |

**Done khi:** A/B trên `storage/logs/adaptive.log` — % câu mới/phiên giảm khi due pool lớn; weakness sau chuỗi đúng giảm rõ.

Tham số gợi ý lấy từ mophong (có thể chỉnh):

```text
newShareLowBacklog   = 0.3   # due < 1×N        → ~30% (mọi mode)
newShareMidBacklog   = 0.2   # 1×N ≤ due < 3×N  → ~20%
newShareHighBacklog  = 0.1   # due ≥ 3×N        → ~10%
midBacklogFactor     = 1
highBacklogFactor     = 3
weakWindow           = 5
minResponseMs        = 5000
thrashMildStreak     = 3     # ≥3 sai liên tiếp → tầng vừa
thrashSevereStreak   = 6     # ≥6 sai liên tiếp → tầng nặng
thrashMildSessions   = 2
thrashMildHours      = 72
thrashSevereDays     = 7
```

### Phase C — Explainability (UX + admin)

- Trả `bucket` ước lượng + `reason` (copy kiểu mophong) vào trace / summary phiên.  
- Admin briefing đã có — bổ sung nhãn “Yếu / Sắp quên / Mới / Lấp” *tương thích* với score V1 (map từ weakness & urgency thresholds, không bắt buộc đổi engine).  
- UI học viên: một dòng “Vì sao câu này” (Premium hoặc mọi tier — quyết định product).

**Done khi:** QA đọc một phiên và hiểu suất mà không mở code.

### Phase D — Model V2 (chỉ khi có metric)

Chỉ mở khi Phase B đã chạy và có số:

| Metric | Mục tiêu gợi ý |
|---|---|
| % câu mới / phiên khi `duePool ≥ N` | ↓ rõ so với baseline V1 |
| Due clearance (số câu quá hạn sau 7 ngày) | ↑ |
| Repeat-within-24h | ↓ |
| Shortfall rate | không tăng đột biến |
| Accuracy theo nhóm | ổn định / ↑ nhẹ |

Khi đó mới quyết định từng mục:

1. **Cooldown hybrid:** cứng ngắn (6–12h) + mềm như V1 — giảm spam mà hạn chế shortfall.  
2. **Mode overlay:** giữ soft weights; thêm sàn suất (vd. cân bằng ≥ 40% due nếu due pool lớn).  
3. **Ladder / đổi base R:** *chỉ nếu* product muốn giảng “bậc ôn” hoặc “đến hạn = còn nhớ 90%”. Nếu có: migration `S` + cập nhật SRS data model + docs + backfill kỳ vọng.  
4. **Giảm phạt sai (nếu dùng ladder):** tụt 1–2 bậc thay vì luôn về bậc 1.

---

## 6. Mapping triển khai vào codebase hiện tại

| Khái niệm mophong | Tương đương production | Hành động |
|---|---|---|
| `user_question_state` | `question_status` | Mở rộng cột / JSON; không tạo bảng song song trừ khi bắt buộc |
| `ladderStep` / `ladderDays` | `memory_stability_days` | **Chưa thay** ở Phase B |
| `lastAnsweredAt` | `last_graded_at` | Giữ |
| `lastSelectedAt` | `last_served_at` | Giữ; cân nhắc cooldown cứng bổ sung ở Phase D |
| `recentResults[5]` | chưa có | Thêm hoặc derive từ `question_attempts` |
| `contentVersion` | phiên bản nội dung câu published | Wire vào reset status |
| `buildSession(mode)` | `AdaptiveQuestionSelector::pick(adaptiveFocus)` | Port quota logic |
| `applyAttempt` | `AnswerQuestionAction` + `MemoryStability::afterGrade` | Port validity + recent weakness |
| `reason` / `bucket` | `AdaptiveTrace` / briefing | Phase C |
| Excel / `demo.ts` | — | Giữ làm nghiệm thu offline |

Ghép hệ thống (theo `mophong/README.md`) vẫn đúng kiến trúc:

1. Nạp state + pool + topic weights → gọi thuật toán thuần → ghi session items + `last_served_*`  
2. Mỗi answer → log attempt → upsert state  
3. Phiên bỏ dở → attempt `submitted: false` / omit: không đổi S (V1 đã làm một phần)

---

## 7. Quyết định product cần chốt sớm

1. **Đã chốt:** ladder + `0.9^(t/S)` theo Excel / mophong (không giữ `exp`/×2).  
2. Weakness: cửa sổ 5 lần có chấp nhận “quên lịch sử sai cũ” không?  
3. Due backlog cao: đã chốt còn **10% câu mới** (không về 0) — OK với product?  
4. Copy “Vì sao câu này” có hiện cho Free không?  
5. Cooldown cứng tối thiểu bao nhiêu giờ (0 / 6 / 12 / 20)?

---

## 8. Khuyến nghị cuối (một dòng hành động)

**Giữ production làm nền; lấy từ mophong ba thứ trước: (1) câu mới theo tồn đọng due, (2) weakness cửa sổ gần, (3) reset khi đổi nội dung câu — rồi mới bàn ladder / đổi công thức R.**

`mophong/` tiếp tục là bản đặc tả có test; mọi PR selector nên chứng minh hành vi bằng kịch bản tương đương `adaptiveSession.test.ts` + quan sát `adaptive.log`.

---

## Phụ lục A — Demo mophong (tham chiếu hành vi mong muốn)

Mode Cân bằng, 10 phiên, bể 50 câu, seed 2026:

| Phiên | Ngày | Chọn Yếu/Sắp quên/Mới | Đúng |
|---:|---:|---|---:|
| 1 | 1 | 0/0/10 | 8/10 |
| 2 | 2 | 2/0/8 | 6/10 |
| 4–5,7–9 | … | ~5/4/1 | 6–8/10 |
| 10 | 17 | 4/5/1 | 7/10 |

Cuối kỳ: 33/50 câu đã gặp; bậc 1:3 · bậc 2:10 · bậc 3:20; Tim mạch (w=3) phủ nhiều nhất.

→ Với new share 30/20/10: khi due cao còn ~1 câu mới/phiên (N=10); mục tiêu Phase B nên tiệm cận trên production.

## Phụ lục B — File liên quan

| File | Vai trò |
|---|---|
| `mophong/adaptiveSession.ts` | Thuật toán mẫu V2 |
| `mophong/adaptiveSession.test.ts` | 12 tiêu chí nghiệm thu |
| `mophong/demo.ts` | Mô phỏng 10 phiên |
| `mophong/mo_phong_10_phien_50_cau.xlsx` | Bảng tham số + nhật ký + so 3 mode |
| `mophong/README.md` | Cách ghép vào hệ thống |
| `docs/adaptive-session-algorithm.md` | Đặc tả production V1 |
| `Modules/QuestionBank/app/Services/AdaptiveQuestionSelector.php` | Selector đang chạy |
| `Modules/QuestionBank/app/Support/MemoryStability.php` | Forgetting curve V1 |
