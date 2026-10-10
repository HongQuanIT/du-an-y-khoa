# Kế hoạch xây dựng mô hình IRT cho nền tảng luyện thi Y khoa

| | |
|--|--|
| **Loại tài liệu** | Thiết kế mô hình & lộ trình (không phải đặc tả code) |
| **Phạm vi** | Psychometrics cho ngân hàng câu hỏi, năng lực học viên, phiên thích ứng, item analysis, CAT (tương lai) |
| **Liên quan** | `docs/adaptive-weakness-model.md`, `docs/adaptive-session.md`, **`docs/irt-adaptive-modes.md`** (công thức + 3 mode), SRS Module 05 / 06 / 19 / 21 / 35 / 38 |
| **Nguyên tắc** | Tuân thủ lý thuyết IRT; tách rõ **ước lượng tham số** và **ứng dụng sản phẩm**; không thay thế CTT mà bổ sung khi đủ dữ liệu |

---

## 0. Tóm tắt điều hành

**Item Response Theory (IRT)** mô hình hóa xác suất trả lời đúng một câu hỏi như hàm của:

1. **Năng lực người làm** (ability / latent trait) — ký hiệu θ (theta)
2. **Đặc tính câu hỏi** (item parameters) — độ khó, độ phân biệt, (tuỳ chọn) đoán mò

Khác Classical Test Theory (CTT — tỷ lệ đúng thô, point-biserial…), tham số IRT **không phụ thuộc mẫu** khi giả định mô hình đúng: độ khó câu ổn định dù cohort dễ hay khó; năng lực học viên so sánh được giữa các phiên / đề khác nhau.

Trên dự án hiện tại:

| Lớp | Vai trò | Trạng thái |
|-----|---------|------------|
| CTT + nhãn soạn thảo (`difficulty` easy/medium/hard) | UI lọc, seed ban đầu | Đang dùng |
| Heuristic kiểu Rasch / Elo trong `adaptive-weakness-model.md` | Điểm yếu luyện tập, θ theo bài/môn, forgetting | Đề xuất / một phần vận hành |
| **IRT chuẩn (tài liệu này)** | Hiệu chuẩn ngân hàng, θ có sai số chuẩn, fit kiểm định, CAT, báo cáo psychometric admin | **Chưa có — cần kế hoạch** |

IRT không phải “công thức điểm yếu mới” để thay hết heuristic. IRT là **lớp đo lường**: cho ra `b` (độ khó), `a` (độ phân biệt), θ và SE(θ). Các module sản phẩm (adaptive session, mastery, predicted score, item analysis) **tiêu thụ** các ước lượng đó.

**Khuyến nghị mô hình khởi đầu:** **2PL dichotôm theo miền** (bài học / cụm bài trong môn), với **neo thang** và **ước lượng offline theo lịch**. 3PL chỉ khi đã chứng minh guessing systematic trên MCQ 4–5 lựa chọn và có đủ N. CAT (Module 38 đề xuất cải tiến) là **pha sau** khi ngân hàng đã hiệu chuẩn và fit chấp nhận được.

---

## 1. Mục tiêu & phạm vi

### 1.1 Mục tiêu nghiệp vụ

1. **Đo năng lực học viên** trên thang liên tục, có khoảng tin cậy, không chỉ % đúng.
2. **Hiệu chuẩn ngân hàng câu hỏi**: độ khó và độ phân biệt từ dữ liệu thật, tách khỏi nhãn soạn thảo.
3. **Cải thiện chọn câu thích ứng**: chọn câu gần năng lực hiện tại (information tối đa), vẫn tôn trọng điểm yếu / chống quên / blueprint.
4. **Item analysis admin** (SRS 35, 38): phát hiện câu lệch, kém phân biệt, nghi đoán mò / chìa khóa sai.
5. **Nền cho CAT / đề thích ứng** và dự báo điểm thi có khoảng tin cậy (SRS 19, 21).

### 1.2 Ngoài phạm vi (giai đoạn này)

- Implement thuật toán / thư viện / migration (tài liệu này **không chứa code**).
- Polytomous IRT đầy đủ cho câu multi-select / matching (chỉ ghi nhận hướng mở rộng).
- Cognitive Diagnostic Models (CDM), Knowledge Tracing sâu (BKT/DKT) — có thể kết hợp sau; không thay IRT.
- Module Organization B2B (32) — không phụ thuộc.

### 1.3 Quan hệ với tài liệu đã có

```text
                    ┌─────────────────────────────┐
                    │  IRT (tài liệu này)         │
                    │  Hiệu chuẩn item + θ + SE   │
                    │  Fit, neo thang, CAT later  │
                    └─────────────┬───────────────┘
                                  │ cung cấp a, b, (c), θ
          ┌───────────────────────┼───────────────────────┐
          ▼                       ▼                       ▼
┌──────────────────┐   ┌────────────────────┐   ┌────────────────────┐
│ Adaptive weakness│   │ Adaptive session   │   │ Admin / Exam       │
│ p_now, W, xoay   │   │ chọn câu, bucket   │   │ item analysis, CAT │
│ vòng, điểm bài   │   │ info + weakness    │   │ quality gate       │
└──────────────────┘   └────────────────────┘   └────────────────────┘
```

- `adaptive-weakness-model.md` dùng logit + θ kiểu Elo/Rasch **online, có quên, có gợi ý**. Đó là **mô hình học tập cá nhân hóa**, không phải calibration IRT batch.
- IRT **batch / định kỳ** ước lượng tham số ổn định trên tập attempt “sạch” (xem mục 5).
- Khi IRT đã chín: `d_q` và θ trong weakness model nên **neo theo** `b` và θ IRT (cùng thang), thay vì chỉ tỷ lệ đúng CTT + nhãn.

---

## 2. Nguyên lý IRT bắt buộc phải giữ

Mọi thiết kế sản phẩm phải **không phá** các nguyên lý sau. Nếu sản phẩm cần hành vi khác (gợi ý, ôn lại, quên), phải tách lớp — không “bẻ” likelihood IRT.

### 2.1 Mô hình xác suất

Với câu dichotôm (đúng = 1, sai = 0), họ logistic phổ biến:

| Mô hình | Công thức ý niệm | Tham số câu | Khi dùng trong dự án |
|---------|------------------|-------------|----------------------|
| **1PL / Rasch** | P(X=1\|θ) = σ(θ − b) | Chỉ **b** (độ khó) | Baseline lý thuyết, so sánh fit; ngân hàng nhỏ / N thấp |
| **2PL** | P = σ(a (θ − b)) | **a** (discrimination), **b** | **Khuyến nghị mặc định** cho MCQ y khoa |
| **3PL** | P = c + (1−c) σ(a (θ − b)) | Thêm **c** (guessing lower asymptote) | Chỉ khi MCQ nhiều lựa chọn + N lớn + kiểm định c hữu ích |

Với σ(x) = 1 / (1 + e^(−x)) (hoặc dùng hệ số D ≈ 1,7 nếu muốn gần ogive chuẩn — **chọn một convention và giữ cố định toàn hệ thống**).

**Đọc tham số:**

- **b**: vị trí trên thang θ nơi P ≈ 0,5 (2PL) hoặc nơi đường cong “giữa” (3PL điều chỉnh bởi c). b cao = câu khó.
- **a**: độ dốc ICC (Item Characteristic Curve). a cao = câu phân biệt tốt người giỏi / kém quanh b.
- **c**: sàn xác suất khi θ → −∞ (đoán mò). Với 4 đáp án, kỳ vọng thô ≈ 0,25 nhưng c thực thường thấp hơn vì distractor không đều.

### 2.2 Local independence (độc lập địa phương)

Cho θ (và miền latent đã chọn), các câu **độc lập có điều kiện**. Hệ quả:

- Không được đưa vào calibration các lần làm **phụ thuộc mạnh** (xem cùng giải thích rồi làm lại ngay; cùng cluster “câu A spoiler câu B”) mà không xử lý.
- Phiên luyện có gợi ý / xem explanation làm **ô nhiễm** tín hiệu independence nếu tính mọi attempt như thi độc lập.

→ Calibration IRT dùng **quy tắc lọc attempt** (mục 5), khác bộ attempt dùng cho forgetting / weakness.

### 2.3 Unidimensionality (đơn chiều) trong mỗi mô hình

Một θ chỉ có nghĩa khi các câu trong pool **chủ yếu đo một latent trait**. Ngân hàng y khoa **đa chiều** (Nội, Ngoại, Dược, từng bài học…).

**Nguyên tắc thiết kế dự án:** không ép một θ toàn cục cho mọi câu ngay từ đầu. Thay bằng:

1. **θ theo miền** — ưu tiên **Bài học (lesson)** khi đủ câu & attempt; co về **Môn học (subject)** / **Hệ cơ quan** khi mỏng dữ liệu (cùng tinh thần hierarchical trong weakness model).
2. Hoặc **multidimensional IRT (MIRT)** sau này — phức tạp hơn, cần N và chuyên gia psychometric.

Phase 1–2: **nhiều mô hình đơn chiều theo miền**, có cơ chế co / liên kết thang giữa miền (mục 6).

### 2.4 Invariance (bất biến mẫu) — có điều kiện

Tham số item ổn định khi:

- Mô hình đúng (chiều, form ICC)
- Dữ liệu không bị selection bias nghiêm trọng (adaptive chỉ hỏi câu yếu → cần chiến lược sampling / weighted / sử dụng response từ exam mode)
- Population không đổi cấu trúc latent quá mạnh theo thời gian (re-calibrate định kỳ)

Nếu invariance bị phá (item drift), phải có **giám sát drift** (mục 9).

### 2.5 Information & sai số chuẩn

Độ chính xác θ phụ thuộc **chỗ** trên thang và **câu đã làm**:

- Item Information I_i(θ) lớn quanh b khi a lớn.
- Test Information T(θ) = Σ I_i(θ)
- SE(θ) ≈ 1 / √T(θ)

Sản phẩm **phải** mang SE hoặc độ tin cậy kèm θ khi hiển thị / quyết định (mastery, predicted score, CAT stop). θ điểm ước lượng không SE là không đủ cho quyết định cao rủi ro (đỗ/trượt giả lập).

### 2.6 Tách “đo lường” và “học tập”

| | IRT calibration | Adaptive weakness / memory |
|--|-----------------|---------------------------|
| Mục tiêu | Ước lượng a, b, (c), θ ổn định | Dự đoán P(đúng nếu hỏi *bây giờ*) có quên & gợi ý |
| Attempt | Lọc sạch, ưu tiên lần đầu / exam | Nhiều lượt, trọng số thời gian |
| Gợi ý | Thường loại hoặc mã hóa riêng | y = 0,5 khi dùng hint |
| Quên | Không nằm trong ICC chuẩn | Có decay / stability |
| Cập nhật | Batch (đêm / tuần) | Online sau mỗi phiên |

Vi phạm nguyên lý phổ biến cần tránh: cộng forgetting vào likelihood 2PL rồi gọi là “IRT”; hoặc dùng θ IRT cập nhật từ mọi lượt ôn có hint mà không chỉnh mô hình.

---

## 3. Đặc thù dữ liệu dự án (ràng buộc thiết kế)

### 3.1 Dạng câu

- Chủ đạo: **single best answer** → dichotôm sau khi chấm.
- Có thể có multi / matching (SRS Question.type) → Phase sau: chấm dichotôm tạm, hoặc GRM/GPCM.
- Options bị **shuffle theo session** — identity chấm theo `option.id` (không ảnh hưởng IRT nếu chấm đúng/sai đã chuẩn).

### 3.2 Hai ngữ cảnh làm bài

| Ngữ cảnh | `question_sessions.mode` / nguồn | Chất lượng cho IRT |
|----------|----------------------------------|--------------------|
| Study + gợi ý / explanation | study | Nhiễu: learning effect, hint |
| Exam mode / đề thi | exam | **Ưu tiên cao** cho calibration |
| Adaptive weak_topics | study, chọn lệch điểm yếu | Bias selection — dùng cẩn thận |
| Classroom / live review | riêng | Thường không đưa vào θ cá nhân chính thức |

### 3.3 Taxonomy

Câu gắn ≥1 **Bài học**; bài gắn nhiều **Môn** / **Hệ**. Một câu có thể thuộc nhiều miền → quy tắc gán miền calibration phải tường minh (primary lesson theo N attempt lớn nhất, hoặc ước lượng MIRT sau).

### 3.4 Nhãn độ khó soạn thảo

`questions.difficulty` (easy/medium/hard hoặc 1–5) là **prior / hiển thị**, không phải b IRT. Sau calibration: báo cáo tương quan nhãn vs b; cảnh báo lệch lớn cho editor (Module 35).

### 3.5 Thống kê hiện có

- `stats_cache`, correct rate CTT, reports — giữ cho UI nhanh.
- Weakness model đề xuất `stat_attempts` / `stat_correct` trên first valid attempt — **tương thích** làm prior hoặc warm-start cho b, không thay MLE/EM IRT.

---

## 4. Lựa chọn mô hình chi tiết

### 4.1 Quyết định Phase 1: 2PL theo miền

**Lý do chọn 2PL thay vì chỉ Rasch:**

- Câu y khoa chất lượng không đồng đều: vignette dài, distractor yếu → **a** khác nhau; ép a = 1 (Rasch) làm lệch θ và b.
- Item analysis admin cần **discrimination** tường minh (SRS 38 đã nêu discrimination).

**Lý do chưa mặc định 3PL:**

- Ước lượng c không ổn khi N per item thấp hoặc ít người θ thấp làm câu đó.
- Adaptive ít đưa câu quá khó cho người yếu → thiếu thông tin cho c.
- Có thể ước lượng 3PL song song trên tập exam-mode đủ lớn rồi so AIC/BIC / likelihood ratio / fit; chỉ promote nếu cải thiện rõ và c không “lang thang”.

### 4.2 Đơn vị latent (θ domain)

Thứ tự ưu tiên:

1. **Lesson-level 2PL** nếu lesson có ≥ N_item_min câu đã live **và** đủ người làm (ngưỡng mục 5).
2. Không đủ → **Subject-level** (gop các lesson trong môn).
3. Không đủ → **Exam-blueprint domain** (CCT / ma trận đề) cho pool thi.
4. θ toàn cục chỉ như **neo / prior**, không phải báo cáo chính.

Học viên có vector θ: `{ lesson_id → (θ, SE, N_eff) }`, với co về subject khi SE lớn (empirical Bayes / hierarchical — cùng tinh thần k shrink trong weakness model, nhưng trên thang IRT).

### 4.3 Thang đo & neo (linking / anchoring)

IRT chỉ ước lượng được **sai phân** θ và b trên cùng thang. Cần neo:

| Chiến lược | Mô tả | Khuyến nghị |
|------------|-------|-------------|
| **Mean-sigma / mean-mean linking** | Chuẩn hóa mean(θ)=0, sd(θ)=1 trên cohort tham chiếu mỗi miền | Phase 1 |
| **Anchor items** | Giữ cố định b (và a) của tập câu “vàng” giữa các lần calibrate | Phase 2 khi re-calibrate |
| **Fixed reference cohort** | Cohort “học viên năm X chuẩn bị kỳ Y” làm population gốc | Cần Product chọn |

**Quy ước hiển thị sản phẩm (tách khỏi thang nội bộ):**

- Nội bộ: θ ∈ ℝ (logit-ish), b cùng thang.
- UI có thể map sang 0–100 hoặc stanine **sau** khi neo ổn — luôn ghi chú “ước lượng, có SE”.
- Không trộn thang CTT % đúng với θ trên cùng một gauge nếu chưa convert có kiểm chứng.

### 4.4 Person response function & polytomous (sau)

- Hint credit (y=0,5): **không** đưa thẳng vào 2PL dichotôm. Options: (i) loại attempt có hint khỏi calibration; (ii) mô hình riêng partial credit sau này.
- Omit / thời gian < 5s: loại như quy tắc attempt hợp lệ hiện tại.

---

## 5. Thiết kế dữ liệu quan sát cho calibration

### 5.1 Định nghĩa “response sạch”

Một quan sát (person j, item i) đưa vào ma trận IRT khi **tất cả** đúng:

1. `is_correct` ∈ {0,1} (không null / omit).
2. `time_spent_seconds ≥ T_min` (đề xuất giữ 5s như hệ thống hiện tại; có thể nâng với exam).
3. Không `used_hint` (hoặc tách file riêng “hinted responses” không trộn).
4. Ưu tiên: **lượt hợp lệ đầu tiên** của user trên `question_id` + `published_version` tại thời điểm làm — tránh learning từ explanation.
5. Câu `published` / trong snapshot đề; loại retired khỏi calibrate mới (giữ lịch sử tham số cũ để audit).
6. User không phải tài khoản nội bộ / bot / instructor test (denylist).

**Ghi chú adaptive bias:** ma trận chỉ gồm first-attempt vẫn bị ảnh hưởng nếu user chưa từng thấy câu dễ vì selector. Giảm bias bằng:

- Bổ sung dữ liệu **exam / custom session** (ít lệch điểm yếu hơn).
- Định kỳ chạy **seeding sessions** nội bộ hoặc A/B “exploration rate” nhỏ trong adaptive (ε-greedy thông tin) — quyết định sản phẩm, ghi vào backlog Phase 2.
- Phương pháp ước lượng chịu missing not at random có giới hạn; báo cáo coverage theo θ.

### 5.2 Ma trận person × item

- Dạng sparse: hầu hết học viên chỉ làm một phần ngân hàng.
- Ước lượng IRT hiện đại (MMLE/EM, marginal Bayes, Stan/JAGS, mirt, ltm, TAM…) chấp nhận sparse nếu mỗi item và mỗi person đủ liên kết qua đồ thị response.
- **Điều kiện tối thiểu đề xuất (khởi điểm, hiệu chỉnh bằng mô phỏng):**

| Đối tượng | Ngưỡng tối thiểu để ước lượng ổn định | Ghi chú |
|-----------|--------------------------------------|---------|
| Item | ≥ 200–500 first-clean responses (2PL); ≥ 800–1000 nếu thử 3PL | Dưới ngưỡng: giữ prior từ nhãn / CTT, cờ `calibration_status=provisional` |
| Person (θ điểm) | ≥ 15–30 clean responses trong miền | Dưới → SE lớn, co về prior miền |
| Miền (lesson/subject) | ≥ 20–30 items có thể calibrate + cohort đa năng lực | Nếu không → gộp miền |

### 5.3 Version câu hỏi

Khi `published_version` đổi nội dung stem/đáp án → **item psychometric mới** (item_id_version), không giữ b cũ như cùng một câu. Metadata liên kết `cloned_from` / version để editor xem lịch sử.

### 5.4 Tách tập

- **Calibration set:** dùng ước lượng a, b.
- **Validation set:** hold-out persons hoặc hold-out thời gian (tháng sau) để kiểm tra dự đoán P và fit.
- Không tối ưu tham số trên cùng dữ liệu rồi tuyên bố fit tốt mà không hold-out.

---

## 6. Quy trình ước lượng (thiết kế logic, không code)

### 6.1 Pipeline khái niệm

```text
[1] Trích attempt sạch     → response matrix theo miền
[2] Kiểm tra chiều         → PCA/eigen trên tetrachoric / DIMTEST-style heuristic
[3] Ước lượng 2PL          → a_i, b_i (+ prior yếu nếu N thấp)
[4] Neo thang              → mean θ cohort = 0, sd = 1 (hoặc anchor items)
[5] Ước lượng θ person     → EAP/MAP + SE cho mọi user đủ điều kiện
[6] Fit & QA               → item fit, person fit, ICC vs empirical
[7] Xuất bản tham số       → store versioned “calibration run”
[8] Consumer modules       → weakness prior, admin UI, analytics, (sau) CAT
```

### 6.2 Ước lượng item

- **Marginal maximum likelihood (MML)** với phân phối θ giả định (thường N(0,1) sau neo) là chuẩn ngành thi cử.
- Prior co trên a (log-normal) và b (normal) khi N item thấp — tránh a cực đại / b bay.
- Không dùng joint MLE cổ điển person+item trên dữ liệu lớn (thiên lệch đã biết).

### 6.3 Ước lượng person (sau khi item cố định)

- **EAP** (expected a posteriori) hoặc **MAP** với prior N(μ_domain, σ²) — phù hợp sparse.
- Online cập nhật θ giữa các lần calibrate đầy đủ: có thể dùng Bayes tuần tự với item parameters **đóng băng** (giống CAT scoring) — khác hoàn toàn việc re-fit a,b mỗi đêm trên toàn bộ lịch sử có hint.

### 6.4 Hierarchical / shrink giữa lesson và subject

Khi lesson mỏng:

```text
θ_lesson_j ≈ co về θ_subject(j)
b_item giữ theo mô hình miền đã chọn (subject-level pool)
```

Báo cáo UI: “Ước lượng bài A dựa nhiều vào môn B” khi trọng số shrink cao (tương tự N_bài thấp trong weakness model).

### 6.5 Linking giữa các lần chạy

Mỗi **calibration_run**:

- `id`, thời điểm, miền, mô hình (2PL/3PL), convention D, cohort filter, git/data snapshot hash
- Bảng tham số item versioned
- Không ghi đè im lặng: consumer chọn `run_id` đang `published`

Chuyển run mới: equating qua anchor items hoặc mean-sigma trên item chung.

---

## 7. Kiểm định chất lượng mô hình (bắt buộc trước khi “bật” sản phẩm)

### 7.1 Item fit

- So sánh ICC lý thuyết với tỷ lệ đúng thực theo bin θ (hoặc chi-square / infit-outfit kiểu Rasch nếu chạy 1PL song song).
- Câu fit kém → cờ `flag_fit`, không đưa vào CAT; editor review (chìa sai, Stem mơ hồ, dual key).

### 7.2 Discrimination & guessing thực nghiệm

| Dấu hiệu | Hành động |
|----------|-----------|
| a rất thấp | Câu không phân biệt — sửa distractor hoặc retire khỏi đề cao stakes |
| a âm | Đáp án / chìa có thể đảo — escalate Module 35 |
| Tỷ lệ đúng cao ở nhóm θ thấp + flat ICC | Nghi guessing / clue; cân nhắc 3PL hoặc sửa câu |
| b lệch cực so với nhãn difficulty | Gợi ý đổi nhãn hoặc review độ khó |

### 7.3 Person fit

- Pattern “đúng câu khó / sai câu dễ” bất thường → nghi cheat, distract, hoặc đa chiều.
- Không tự động phạt user; đưa vào hàng đợi QA / hỗ trợ nếu stakes cao.

### 7.4 Reliability

- Empirical reliability từ phân tán θ và SE trung bình.
- Mục tiêu luyện tập: thấp hơn thi cử chuẩn hóa vẫn chấp nhận được; mục tiêu báo cáo “sẵn sàng thi”: đặt ngưỡng SE tối đa trước khi khẳng định.

### 7.5 Predictive check

Trên hold-out: calibration plot — nhóm câu có P̂ ∈ [0,3; 0,4] phải đúng ~30–40%. Đây là cùng tinh thần “kiểm chứng được” như weakness model nêu với p_now.

---

## 8. Ứng dụng vào các module sản phẩm

### 8.1 Question Management (35) & Exam Management (38)

- Item analysis nâng từ CTT (p-value, discrimination cổ điển) lên **a, b, fit, drift**.
- Quality gate trước publish đề: cảnh báo item provisional / fit kém.
- Blueprint: phân bố b theo CCT để đề không dồn một mức khó.

### 8.2 Qbank filter & hiển thị (05)

- Lọc theo b (thang IRT) song song nhãn easy/medium/hard.
- Preview “độ khó cộng đồng” → dùng b hoặc P(θ=0), không chỉ correct rate thô.

### 8.3 Adaptive Session (05/06 + adaptive-session.md)

**Chi tiết công thức & 3 mode:** xem [`docs/irt-adaptive-modes.md`](irt-adaptive-modes.md).

Tóm tắt gắn IRT đúng nguyên lý:

| Mode | Suất (giữ hợp đồng) | Tín hiệu IRT khi xếp hạng |
|------|---------------------|---------------------------|
| Điểm yếu (`weak_focus`) | 100% nhóm Yếu | `U_weak = W_irt + …` với `W_irt = 1 − p_now`, `P_cal = σ(a(θ−b))` |
| Củng cố (`retention`) | 100% nhóm Due | `U_ret` / `Gap_ret = P_cal·(1−R)` — không biến thành mode yếu |
| Cân bằng (`balanced`) | ~50% Yếu + ~50% Due | Mỗi nửa dùng đúng utility riêng; có thể chỉnh nhẹ tỷ lệ theo acc gần đây |

Pipeline vẫn **Lọc → Nhóm → Suất → Rank**. IRT không phá cooldown / thrash / “không bù nhóm” của Điểm yếu & Củng cố. CAT thuần (chỉ tối đa hóa I(θ)) dành cho đề thích ứng — pha sau.

### 8.4 Adaptive weakness model

- Thay `d_q` CTT bằng `σ(−b)` hoặc P(X=1\|θ=0) từ IRT khi item đã `calibrated`.
- θ_bài heuristic có thể dần thay / hòa bởi EAP θ_lesson.
- Giữ bước forgetting & hint — **phía trên** P0 IRT.

### 8.5 Study Analytics / Performance (19, 21)

- Mastery 0–5: map từ θ và SE + coverage (số item / thông tin tích lũy), có decay riêng tầng sản phẩm.
- Predicted score: hồi quy hoặc mapping θ → điểm đề thử; luôn kèm CI từ SE(θ) và độ bất định đề.
- Peer percentile: xếp theo θ neo cùng cohort / cùng kỳ thi — công bằng hơn % đúng khi mỗi người làm bộ câu khác nhau.

### 8.6 CAT / đề thích ứng (tương lai, Module 38)

Chỉ khi:

1. Pool theo blueprint đủ item calibrated mỗi CCT.
2. Exposure control (không lộ ít câu “vàng” quá nhiều).
3. Stopping rule: SE(θ) < ε hoặc N_max hoặc time.
4. Content constraints (ma trận) — CAT thuần information dễ phá blueprint.

---

## 9. Vận hành, giám sát, đạo đức dữ liệu

### 9.1 Lịch vận hành đề xuất

| Việc | Tần suất | Ghi chú |
|------|----------|---------|
| Incremental θ scoring (item đóng băng) | Sau phiên / nightly | Nhẹ, phục vụ UI |
| Re-calibrate item (2PL) | Tuần hoặc khi +X% response mới | Versioned run |
| Drift detection | Tuần | So b mới vs b neo; flag |
| Báo cáo psychometric cho content | Sprint / tháng | Editor + instructor |

### 9.2 Item parameter drift

Nguyên nhân: sửa nhẹ stem, đáp án “lan truyền”, cohort đổi (mùa thi), lộ đề. Quy trình: phát hiện → freeze CAT → human review → recalibrate item version.

### 9.3 Công bằng & bias

- Kiểm tra DIF (differential item functioning) theo nhóm có thể quan sát được **chỉ khi** có biến nhân khẩu được phép và có ethics review — không suy diễn từ dữ liệu thiếu.
- Tránh dùng IRT để “xếp hạng” công khai gây hại; percentile peer đã gated Premium (SRS 19).

### 9.4 Giải thích cho học viên

Ngôn ngữ sản phẩm: “mức sẵn sàng ước lượng”, “độ chắc của ước lượng”, không tuyên bố IQ hay năng lực lâm sàng thật. IRT đo **latent trait trên ngân hàng câu hỏi của nền tảng**.

---

## 10. Lộ trình triển khai theo pha

### Pha 0 — Nền tảng đo lường (1–2 sprint phân tích)

- Chốt convention: logistic 2PL, thang neo, định nghĩa clean response, miền θ.
- Kiểm kê N response sạch theo lesson/subject/exam.
- Gap analysis: miền nào đủ calibrate, miền nào provisional.
- Đồng bộ thuật ngữ glossary (θ, b, a, SE, ICC, information).

**Cổng ra:** bảng coverage + quyết định miền Phase 1.

### Pha 1 — Calibration offline & admin đọc được

- Chạy 2PL trên miền đủ dữ liệu (ưu tiên exam-mode + first clean).
- Lưu calibration_run versioned; UI admin: bảng a, b, N, fit, so với nhãn difficulty.
- Song song giữ CTT như hiện tại — không Breaking UI học viên.

**Cổng ra:** ≥ X% câu priority / đề chính có `calibrated`; predictive check đạt ngưỡng thỏa thuận (ví dụ MAE xác suất hold-out).

### Pha 2 — θ học viên & hòa với adaptive

- EAP θ + SE theo miền trên profile / analytics (có thể chỉ Premium hoặc ẩn SE dạng qualitative).
- Weakness model: P0 lấy từ IRT khi có; fallback heuristic.
- Adaptive selector: information re-rank trong bucket.

**Cổng ra:** A/B hoặc offline replay — cùng ràng buộc sư phạm, tăng chuẩn hóa dự đoán / đa dạng b.

### Pha 3 — Quality gate & DIF/drift

- Chặn / cảnh báo publish câu–đề theo fit & a.
- Drift job + quy trình editor.
- Tài liệu vận hành cho content team.

### Pha 4 — CAT / đề thích ứng

- Constraint-based CAT trên blueprint.
- Exposure control, stopping rule, audit đề.
- Chỉ bật khi Pha 1–3 ổn định.

### Pha 5 — Mở rộng mô hình

- 3PL có kiểm chứng; polytomous; MIRT; liên kết cross-lesson sâu hơn.
- Hòa Deep knowledge tracing chỉ như lớp xếp chồng, không thay thế ICC.

---

## 11. Rủi ro & biện pháp

| Rủi ro | Hệ quả | Giảm thiểu |
|--------|--------|------------|
| N sạch quá thấp | a, b không ổn định | Provisional + prior; không bật CAT sớm |
| Adaptive selection bias | b câu “khó假” hoặc a lệch | Ưu tiên exam data; exploration; first-attempt |
| Gộp đa chiều vào 1 θ | θ vô nghĩa, fit kém | θ theo miền; kiểm tra chiều |
| Đồng nhất hóa với weakness | Quên / hint phá IRT | Tách pipeline |
| Đổi version câu im lặng | Tham số sai | Item theo published_version |
| Over-trust θ trên UI | Học viên hiểu nhầm | Luôn kèm SE / qualitative confidence |
| Chi phí tính toán | Job nặng | Batch theo miền; sparse methods; không re-fit mỗi request |

---

## 12. Tiêu chí thành công (KPI mô hình)

1. **Calibration plot** hold-out: độ lệch tuyệt đối trung bình giữa P̂ và tỷ lệ đúng thực < ngưỡng chốt (ví dụ 0,05 trên các bin đủ N).
2. **Tương quan** b IRT với p-value CTT (đảo dấu) mạnh nhưng không hoàn hảo — chứng tỏ IRT bắt thêm thông tin mẫu.
3. **Tương quan** a với discrimination CTT dương rõ.
4. **Giảm** tỷ lệ item “provisional” theo thời gian khi traffic tăng.
5. **Adaptive replay:** với cùng ngân hàng, thông tin tích lũy T(θ) / phiên tăng so với baseline không dùng I_i, mà không làm xấu KPI phủ điểm yếu / đa dạng bài (mục tiêu trong `adaptive-weakness-model.md`).
6. **Editor actionability:** % câu fit kém được xử lý (sửa/retire) trong SLA nội bộ.

---

## 13. Thuật ngữ nhanh (cho glossary sau)

| Thuật ngữ | Nghĩa ngắn |
|-----------|------------|
| θ (theta) | Năng lực latent trên một miền |
| a | Độ phân biệt câu |
| b | Độ khó câu trên cùng thang θ |
| c | Lower asymptote / guessing (3PL) |
| ICC | Đường cong đặc trưng câu: P(đúng\|θ) |
| I(θ) | Lượng thông tin Fisher của câu tại θ |
| SE(θ) | Sai số chuẩn ước lượng năng lực |
| EAP/MAP | Ước lượng θ với prior |
| MML | Ước lượng biên tham số câu |
| Anchor | Câu neo thang giữa các lần calibrate |
| Fit | Độ khớp ICC / pattern thực tế |
| DIF | Lệch chức năng câu giữa nhóm |
| CTT | Lý thuyết khảo thí cổ điển (p, r_pbis) |

---

## 14. Quyết định cần Product / Academic chốt trước khi build

1. Cohort neo thang mặc định là ai? (mọi learner / chỉ Premium / theo kỳ thi mục tiêu)
2. Miền θ mặc định hiển thị: bài vs môn vs kỳ thi?
3. Có cho học viên thấy số θ thô hay chỉ “sẵn sàng / độ chắc”?
4. Attempt study không hint nhưng sau khi xem explanation tuần trước — có được vào calibration không? (khuyến nghị: **không**, chỉ first clean)
5. Ngân sách exploration trong adaptive (% câu chọn theo information / ngẫu nhiên có chủ đích) để nuôi calibration?
6. Ngưỡng N item / person chính thức (chỉnh sau mô phỏng trên data thật Phase 0)?

---

## 15. Tài liệu tham chiếu nội bộ & nguyên lý ngoài

**Nội bộ**

- `docs/adaptive-weakness-model.md` — heuristic logit, θ bài, forgetting, hint credit
- `docs/adaptive-session.md` — pipeline chọn câu thích ứng hiện tại
- SRS `05-question-bank`, `06-question-session`, `19-study-analytics`, `21-performance-dashboard`, `35-question-management`, `38-exam-management`
- `srs/00-nen-tang/06-tracking-analytics.md` — mastery, rollup
- `srs/00-nen-tang/04-mo-hinh-du-lieu.md` — Question, attempts, difficulty

**Nguyên lý / nền tảng lý thuyết (để đội đọc thêm, không copy công thức vào code từ đây)**

- Lord & Novick; Birnbaum — 2PL/3PL
- Rasch (1960) — 1PL / đo lường khách quan
- Embretson & Reise — *Item Response Theory for Psychologists*
- van der Linden & Hambleton — handbook IRT / CAT
- Thực hành thi cử y khoa quốc tế: ngân hàng MCQ + item analysis + (đôi khi) IRT/Rasch cho chuẩn hóa đề

---

## 16. Kết luận thiết kế

1. Dự án cần **IRT 2PL theo miền**, calibration **offline versioned**, scoring θ **có SE**, tách khỏi mô hình quên/gợi ý.
2. Heuristic adaptive hiện tại **không bị thay thế tức thì**; nó trở thành consumer của `a, b, θ` khi sẵn sàng.
3. CAT và 3PL là **hậu quả** của ngân hàng đã chuẩn, không phải điểm bắt đầu.
4. Thành công đo bằng **fit dự đoán + hữu ích cho editor/adaptive**, không phải bằng việc “đã chạy được một thư viện IRT”.

Khi Phase 0 chốt được coverage và các quyết định mục 14, mới chuyển sang đặc tả kỹ thuật (schema `calibration_runs`, job, API admin) — ngoài phạm vi tài liệu này.
