# IRT chi tiết: công thức & áp dụng cho phiên thích ứng (3 mode)

| | |
|--|--|
| **Loại** | Giải thích mô hình + công thức có diễn giải (không phải code) |
| **Đọc kèm** | `docs/irt-model-plan.md` (kiến trúc & lộ trình), `docs/adaptive-session.md` (quy tắc mode đã chốt), `docs/adaptive-weakness-model.md` (quên / gợi ý) |
| **Mô hình IRT dùng ở đây** | **2PL** (hai tham số) — mặc định dự án |

---

## 0. Ý tưởng một câu

IRT trả lời: *“Với năng lực hiện tại của học viên, xác suất họ trả lời đúng câu này (khi còn nhớ / khi chưa quên) là bao nhiêu?”*

Phiên thích ứng **không** thay IRT bằng cách chọn câu chỉ theo độ khó. Nó giữ khung sư phạm:

```text
Lọc (eligible) → Phân nhóm (Yếu / Sắp quên / Mới) → Phân suất theo mode → Xếp hạng trong nhóm bằng tín hiệu IRT
```

Ba mode chỉ khác **cách chia suất** và **hàm xếp hạng trong nhóm**. IRT không được phép phá: cooldown, thrash, đến hạn theo ngày học, không bù nhóm khi mode Điểm yếu / Củng cố đã chốt trong `adaptive-session.md`.

---

## 1. Giải thích IRT dễ hiểu

### 1.1 Ba nhân vật

| Nhân vật | Ký hiệu | Nghĩa đời thường |
|----------|---------|------------------|
| Học viên | θ (theta) | “Trình độ” trên một miền (bài / môn), số thực. 0 ≈ trung bình cohort neo thang; dương = giỏi hơn; âm = yếu hơn |
| Độ khó câu | b | “Mức trình độ cần để có ~50% cơ hội đúng” (với 2PL). b cao = câu khó |
| Độ phân biệt | a | Câu “sắc” hay “tù”: a cao = người giỏi đúng rõ, người kém sai rõ; a thấp = câu gần như may rủi |

Hình dung: θ và b cùng một thước. Học viên θ = 0 đứng cạnh câu b = 0 → khoảng 50–50. Câu b = +1 khó hơn rõ; câu b = −1 dễ hơn rõ. Hệ số a quyết định khoảng cách đó “dốc” thế nào.

### 1.2 Hàm logistic — đổi khoảng cách thành xác suất

Khoảng cách năng lực − độ khó sống trên trục (−∞, +∞). Xác suất phải nằm trong (0, 1). Cầu nối:

```text
σ(x) = 1 / (1 + e^(−x))
```

| x | σ(x) | Đọc |
|---|-----:|-----|
| −2 | ≈ 0,12 | Rất khó đúng |
| −1 | ≈ 0,27 | Khó đúng |
| 0 | 0,50 | Ngang sức |
| +1 | ≈ 0,73 | Khá chắc đúng |
| +2 | ≈ 0,88 | Rất chắc đúng |

**Logit** là chiều ngược (đổi xác suất ra trục số):

```text
logit(p) = ln( p / (1 − p) )
```

Dùng khi cần cộng/trừ “mức chắc” rồi đổi lại thành xác suất (tránh cộng % vượt 100%).

### 1.3 Công thức 2PL (mô hình chính)

**Xác suất đúng nếu hỏi khi học viên còn nắm kiến thức (chưa tính quên):**

```text
P_i(θ) = σ( a_i · (θ − b_i) )
       = 1 / ( 1 + exp( −a_i · (θ − b_i) ) )
```

| Thành phần | Vai trò |
|------------|---------|
| `(θ − b_i)` | Học viên mạnh hơn câu bao nhiêu. Dương → nghiêng về đúng |
| `a_i` | Nhân độ dốc. a lớn → cùng một khoảng cách `(θ − b)` tạo chênh P lớn hơn |
| `σ(...)` | Ép kết quả về (0, 1) |

**Ví dụ số**

Học viên θ = 0. Câu A: a = 1,0 · b = 0 → P = σ(0) = **0,50**.  
Câu B: a = 1,0 · b = −1 → P = σ(1) ≈ **0,73** (dễ hơn).  
Câu C: a = 1,0 · b = +1 → P = σ(−1) ≈ **0,27** (khó hơn).  
Câu D: a = 2,0 · b = +1 → P = σ(−2) ≈ **0,12** (cùng độ khó b nhưng phân biệt mạnh → với θ = 0 gần như chắc sai).

### 1.4 Công thức 1PL / Rasch (để đối chiếu)

```text
P_i(θ) = σ(θ − b_i)          (tương đương 2PL với a_i = 1 mọi câu)
```

Đơn giản, dễ neo thang; nhưng **không** phản ánh câu kém chất lượng (a thấp). Dự án dùng 1PL để kiểm chứng / baseline, không dùng làm mặc định chọn câu.

### 1.5 Công thức 3PL (chỉ khi sau này đủ dữ liệu)

```text
P_i(θ) = c_i + (1 − c_i) · σ( a_i · (θ − b_i) )
```

`c_i` = sàn đoán mò (ví dụ ~0,15–0,25 với MCQ). Khi θ rất thấp, P không về 0 mà về c.  
**Trong thuật toán luyện tập**, mức đoán mò vẫn cần khi mô hình hóa **quên** (mục 3) — có thể lấy `g = 1 / số phương án` ngay cả khi calibration mới ở 2PL.

### 1.6 Lượng thông tin câu — “câu này đo được θ tốt đến đâu?”

Với 2PL:

```text
I_i(θ) = a_i² · P_i(θ) · (1 − P_i(θ))
```

**Diễn giải**

- `P(1−P)` cực đại khi P = 0,5 → câu **ngang sức** mang nhiều thông tin nhất.
- Nhân `a²` → câu phân biệt mạnh, khi đúng độ khó, cho thông tin nhiều hơn.
- Câu quá dễ (P≈1) hoặc quá khó (P≈0) → I ≈ 0: làm đúng/sai ít nói về trình độ.

Thông tin cả phiên (gần đúng, giả định độc lập địa phương):

```text
T(θ) = Σ_i I_i(θ)
SE(θ) ≈ 1 / √T(θ)
```

SE nhỏ = ước lượng năng lực chắc hơn. Adaptive **luyện tập** không tối đa hóa T mọi lúc (vì còn vá yếu / chống quên); nhưng **trong một bucket hợp lệ**, ưu tiên I cao giúp vừa học vừa đo sạch hơn.

### 1.7 Ước lượng θ của học viên (ý niệm)

Sau khi item `(a_i, b_i)` đã được hiệu chuẩn và **đóng băng**:

Với các câu đã trả lời sạch `x_i ∈ {0,1}`:

```text
L(θ) = Π_i  P_i(θ)^{x_i} · (1 − P_i(θ))^{1−x_i}
```

θ̂ thường lấy **EAP** (trung bình posterior với prior, ví dụ Normal(0,1) trên miền) kèm SE.  
Giữa hai lần calibrate ngân hàng, chỉ cập nhật θ̂ — **không** sửa a, b từ mọi lượt ôn có gợi ý.

**θ dùng trong selector:** θ của **miền chứa câu** (bài học; co về môn nếu bài mỏng) — gọi θ_d. Nếu câu thuộc nhiều bài: lấy bài có nhiều bằng chứng nhất (N_eff lớn nhất), cùng quy ước weakness model.

---

## 2. Hai lớp xác suất — chỗ hay nhầm

IRT chuẩn **không** có “quên theo ngày”. Phiên luyện **có**. Phải tách:

| Lớp | Ký hiệu | Câu hỏi | Dùng để |
|-----|---------|---------|---------|
| **IRT / nắm kiến thức** | `P_cal` | Nếu hỏi khi kiến thức đang “sẵn sàng”, xác suất đúng? | Độ khó vừa sức, information, đo θ |
| **Hiện tại có quên** | `p_now` | Nếu hỏi *ngay bây giờ*, xác suất đúng? | Nhóm Yếu, xếp độ ưu tiên ôn |

```text
P_cal(i, θ_d) = σ( a_i · (θ_d − b_i) )     // 2PL; θ_d = năng lực miền của câu i
```

`P_cal` giả định local independence và chưa trừ forgetting. Đây là tín hiệu **đúng nguyên lý IRT**.

`p_now` = lớp học tập **bọc ngoài** IRT (mục 3). Không gọi `p_now` là “tham số IRT”; gọi là **xác suất vận hành luyện tập**.

---

## 3. Từ IRT → xác suất hỏi ngay bây giờ

### 3.1 Độ bền nhớ R (giữ hợp đồng adaptive)

Theo quy tắc đã chốt / đang vận hành (`adaptive-session.md`):

```text
t = số ngày thực từ last_graded_at → now
S = memory_stability_days ∈ {1, 3, 7, 14, 30, 60}
R_sched = 0,9 ^ (t / S)                 // dùng xếp hạn / hiển thị hiện tại
```

Đến hạn (bucket Sắp quên) **không đổi**:

```text
is_due ⇔ study_day(now) ≥ study_day(last_graded_at) + S
```

Cho **điểm yếu có phân tán tốt** (tránh mọi câu bỏ lâu cùng p_now ≈ g), lớp tính p_now có thể dùng R “đuôi nặng” hơn (đề xuất trong weakness model), vẫn thỏa R = 0,9 khi t = S:

```text
R_mem = 1 / (1 + t / (9 · S))
```

| Việc | Dùng R nào |
|------|------------|
| Điều kiện vào bucket Sắp quên | `is_due` theo ngày học + S (không đổi) |
| Xếp hạng trong Sắp quên / tính p_now | `R_mem` (đề xuất) hoặc `R_sched` nếu muốn khớp code 1:1 lúc đầu |

Dưới đây viết tắt **R** = `R_mem` khi nói p_now.

### 3.2 Neo “nắm khi vừa ôn” từ IRT + lịch sử câu

**Bước A — Kỳ vọng IRT thuần**

```text
P_cal = σ( a · (θ_d − b) )
```

**Bước B — Cập nhật bằng lịch sử cá nhân trên câu** (vẫn trên thang xác suất, không phá ICC)

Lượt hợp lệ i: `y_i ∈ {0, 0,5, 1}` (sai / đúng có gợi ý / đúng tự lực) — quy ước weakness model.  
Chỉ các lượt này dùng cho **p_mastery**; **không** đưa hint vào ma trận calibrate a, b.

```text
w_i = 0,5 ^ (tuổi_i / H_câu)            H_câu = 40 ngày (đề xuất)
m   = 2                                   // số “lượt ảo” tin P_cal

α = m · P_cal     + Σ w_i · y_i
β = m · (1−P_cal) + Σ w_i · (1 − y_i)
p_mastery = α / (α + β)
```

**Diễn giải:** bắt đầu như đã “thấy” 2 lượt mang giá trị P_cal, rồi cộng lịch sử thật. Câu mới / ít làm → p_mastery ≈ P_cal (đúng IRT). Câu đã sai nhiều gần đây → kéo xuống dưới P_cal.

**Bước C — Trừ quên + sàn đoán mò**

```text
g = 1 / (số phương án)                  // ví dụ 5 lựa chọn → g = 0,2
p_now = g + (p_mastery − g) · R
```

Khi vừa ôn (R≈1): p_now ≈ p_mastery.  
Khi quên nhiều (R→0): p_now → g (đoán mò), **không** về 0 — đúng tinh thần cận dưới 3PL / Birnbaum, dù calibration đang 2PL.

### 3.3 Điểm yếu & nhóm Yếu

```text
W_irt = 1 − p_now                       // điểm yếu liên tục
vào nhóm Yếu  ⇔  p_now < τ_yếu          // đề xuất τ_yếu = 0,60
```

So với W cổ điển `(sai+1)/(n+2)` trên 5 lượt: cùng lịch sử sai có thể W_irt khác nhau vì **b, a, θ_d, R** khác nhau — đúng mục tiêu phân tán của weakness model, nhưng **P_cal lấy từ 2PL thật**, không chỉ từ tỷ lệ đúng cộng đồng.

**Fallback khi câu chưa calibrate:** thay `(a,b)` bằng proxy từ nhãn difficulty / CTT (như `d_q` trong weakness model), đặt `calibration_status = provisional`. Selector vẫn chạy; không gọi proxy là tham số IRT đã publish.

---

## 4. Nguyên tắc gắn IRT vào thuật toán thích ứng

### 4.1 Giữ pipeline — chỉ thay tín hiệu xếp hạng

```text
[1] Pool + filter entitlement / taxonomy / exam
[2] Lọc eligible (version, graded, hết thrash, hết cooldown)     ← không đổi hợp đồng
[3] Phân nhóm: yeu | sap_quen | moi | (lap_day khi được phép)   ← điều kiện nhóm: mục 3 + is_due
[4] Phân suất theo mode (weak_focus / balanced / retention)     ← không đổi tỷ lệ đã chốt
[5] Trong mỗi suất: xếp hạng bằng utility IRT của mode          ← chỗ IRT vào
[6] Diversity cửa sổ (DIVERSITY_FACTOR), shuffle hiển thị       ← giữ
```

### 4.2 Việc IRT **được** làm

- Tính `P_cal`, `p_now`, `W_irt`, `I_i(θ_d)`.
- Xếp thứ tự trong bucket.
- (Tuỳ chọn) điều chỉnh nhẹ tỷ lệ Yếu/Due trong mode **Cân bằng** theo độ khó phiên gần đây — không đụng hai mode kia.

### 4.3 Việc IRT **không** được làm

- Không bỏ cooldown / thrash / study day.
- Mode Điểm yếu: không bù Sắp quên / `lap_day` khi hết yếu.
- Mode Củng cố: không bù Yếu / không lấp hết bằng câu mới khi hết due.
- Không chọn câu chỉ vì I(θ) cực đại nếu câu không thuộc bucket mode yêu cầu (đó là CAT thuần — pha sau, đề thi).
- Không cập nhật a, b từ lượt có hint trong đường scoring θ sạch.

### 4.4 Độ khó vừa sức (mục tiêu sư phạm)

Vùng luyện hiệu quả thường quanh xác suất đúng **0,70–0,85** (khó khăn đáng mong muốn). Định nghĩa khoảng cách tới mục tiêu:

```text
P★ = 0,75                           // tâm vùng mục tiêu (có thể chỉnh)
Δ_i = | P_cal(i, θ_d) − P★ |        // càng nhỏ càng “vừa sức” theo IRT
```

Utility sẽ **thưởng** Δ nhỏ trong các mode phù hợp; mode Điểm yếu vẫn ưu tiên vá `W_irt` trước, rồi mới dùng Δ / I để phá thế trùng điểm.

---

## 5. Công thức xếp hạng chung

Chuẩn hóa thông tin về [0,1] trong tập ứng viên hiện tại U (cùng bucket lần pick):

```text
I_max = max_{j ∈ U} I_j(θ_{d(j)})
Ĩ_i   = I_i(θ_{d(i)}) / I_max          // 0 nếu I_max = 0
```

Độ “vừa sức”:

```text
Fit_i = 1 − min(1, Δ_i / 0,35)         // Δ=0 → Fit=1; lệch ≥0,35 → Fit≈0
```

(Mẫu số 0,35 là độ rộng mềm; chỉnh sau A/B.)

---

## 6. Mode Điểm yếu (`weak_focus`)

### 6.1 Mục tiêu sư phạm

Tập trung sửa chỗ **đang dễ sai nếu hỏi ngay**. Không phân tán sang câu đến hạn nếu chưa yếu.

### 6.2 Phân suất (giữ hợp đồng)

```text
newCount    = theo bảng 30% / 20% / 10% (hoặc 100% nếu chưa từng làm)
reviewSlots = N − newCount
mọi reviewSlots → lấy từ nhóm Yếu
không bù sap_quen / lap_day
hết yếu eligible → không tạo phiên (popup / khoá theo adaptive-session)
yếu ít hơn suất → phiên ngắn + new co theo cùng share
```

### 6.3 Điều kiện vào nhóm Yếu

```text
i ∈ Yếu  ⇔  eligible ∧ (p_now(i) < τ_yếu)
τ_yếu = 0,60
```

(Giai đoạn chuyển tiếp có thể `W_cổ_điển ≥ 0,5` **hoặc** `p_now < τ_yếu` để không tụt coverage đột ngột; mục tiêu lâu dài chỉ dùng p_now.)

### 6.4 Hàm utility — xếp hạng trong Yếu

```text
U_weak(i) = λ_W · W_irt(i)
          + λ_I · Ĩ_i
          + λ_F · Fit_i
          + λ_U · Unexpected_i
```

| Thành phần | Công thức / định nghĩa | Vì sao |
|------------|------------------------|--------|
| `W_irt` | `1 − p_now` | Ưu tiên câu đang dễ sai nhất *bây giờ* |
| `Ĩ` | thông tin chuẩn hóa tại θ_d | Trong các câu yếu, ưu tiên câu còn đo được trình độ (tránh câu quá khó / a≈0) |
| `Fit` | gần P★ theo `P_cal` | Vá yếu ở mức vừa sức; tránh dồn toàn câu b >> θ (bẻ gãy tinh thần) |
| `Unexpected` | xem dưới | Sai “đáng ngạc nhiên” = lỗ hổng thật, ưu tiên chữa |

**Unexpected (lỗi bất thường theo IRT)**

```text
Unexpected_i = max( 0,  P_cal(i, θ_d) − p_mastery(i) )
```

Học viên lẽ ra (theo θ và độ khó) phải làm tốt, nhưng lịch sử câu cho thấy nắm kém → ưu tiên chữa.  
Nếu câu vốn khó với θ (`P_cal` thấp) mà cũng yếu → Unexpected ≈ 0; vẫn có thể được chọn nhờ `W_irt` cao, nhưng không cộng thêm.

**Trọng số đề xuất khởi đầu**

```text
λ_W = 1,00
λ_I = 0,25
λ_F = 0,35
λ_U = 0,40
```

Thứ tự sort: `U_weak` giảm dần; hòa → ngẫu nhiên trong cửa sổ diversity (không theo `question_id`).

### 6.5 Ví dụ ngắn mode Điểm yếu

θ_d = 0. Hai câu cùng yếu:

| Câu | a | b | P_cal | p_now | W_irt | I(θ) | Ghi chú |
|-----|---|---|------:|------:|------:|-----:|---------|
| Q1 | 1,2 | 0,0 | 0,50 | 0,28 | 0,72 | cao | Ngang sức, yếu nặng |
| Q2 | 1,2 | 2,0 | 0,08 | 0,22 | 0,78 | rất thấp | Quá khó với θ |

Thuần theo W: Q2 trước. Sau utility: Q1 có thể vượt vì Fit + I cao hơn — **đúng IRT + sư phạm**: chữa lỗ hổng vừa sức trước câu gần như chắc sai.

### 6.6 Câu mới trong mode Điểm yếu

Suất mới giữ quy tắc bài dang dở. IRT bổ sung **trong bài đã chọn**:

```text
U_new_weak(i) = Fit_i + 0,5 · Ĩ_i
```

Ưu tiên câu mới vừa sức (P_cal gần P★), không đổ câu cực khó vào phiên đang vá yếu.

---

## 7. Mode Củng cố (`retention`)

### 7.1 Mục tiêu sư phạm

Ôn câu **đến hạn** để chống quên và lên bậc S. Không kéo câu yếu chưa due vào “củng cố”.

### 7.2 Phân suất (giữ hợp đồng)

```text
mọi reviewSlots → sap_quen (is_due ∧ eligible)
không bù Yếu / không lấp toàn bộ bằng mới / không lap_day như lối thoát chính
hết due → popup / khoá theo adaptive-session
due ít → phiên ngắn + new theo share
```

### 7.3 Điều kiện vào nhóm Sắp quên

```text
i ∈ Sắp quên  ⇔  eligible ∧ is_due
```

Một câu vừa yếu vừa due: mode này **chỉ** lấy vì due; utility củng cố (không dùng U_weak).

### 7.4 Hàm utility — xếp hạng trong Sắp quên

Mục tiêu: ôn trước câu **dễ quên nhất trong các câu đã đến hạn**, nhưng vẫn vừa sức và còn thông tin.

```text
U_ret(i) = μ_R · (1 − R_i)
         + μ_I · Ĩ_i
         + μ_F · Fit_i
         + μ_S · StabBoost_i
```

| Thành phần | Định nghĩa | Diễn giải |
|------------|------------|-----------|
| `(1 − R)` | R càng thấp càng ưu tiên | Trong các câu đã due, câu còn nhớ ít hơn ôn trước |
| `Ĩ` | I(θ_d) chuẩn hóa | Củng cố câu đang ở biên năng lực → vừa ôn vừa củng cố ước lượng θ |
| `Fit` | gần P★ | Tránh ôn củng cố toàn câu quá dễ (P_cal≈0,95) ít mang lại thử thách truy hồi |
| `StabBoost` | xem dưới | Ưu tiên câu đang ở bậc thấp / vừa xuống bậc sau sai — cần củng cố lại |

```text
StabBoost_i = 1 − (rank_S(S_i) − 1) / 5
```

Với S ∈ {1,3,7,14,30,60} đánh số bậc 1…6: bậc 1 → StabBoost = 1; bậc 6 → 0.  
(Câu due bậc thấp = khoảng cách ôn ngắn, thường dễ mất — cần gặp lại.)

**Trọng số đề xuất**

```text
μ_R = 1,00
μ_I = 0,30
μ_F = 0,25
μ_S = 0,35
```

**Không** cộng `W_irt` lớn vào U_ret: tránh biến Củng cố thành Điểm yếu trá hình. Nếu câu due mà rất yếu, mode Cân bằng hoặc Điểm yếu sẽ xử lý; học viên chọn Củng cố là chọn **trục thời gian nhớ**.

### 7.5 Tín hiệu IRT đặc trưng củng cố: “lẽ ra còn nhớ”

```text
Gap_ret(i) = P_cal(i, θ_d) · (1 − R_i)
```

Có thể thay `(1−R)` trong U_ret bằng `Gap_ret` (đặt μ_R cho Gap): câu học viên **đủ sức** (P_cal cao) nhưng R đã thấp → mất mát lớn nếu không ôn. Câu vốn vượt quá sức (P_cal thấp) dù R thấp vẫn kém ưu tiên hơn trong mode này.

Công thức gộp đề xuất tinh gọn:

```text
U_ret(i) = μ_G · Gap_ret(i) + μ_I · Ĩ_i + μ_F · Fit_i + μ_S · StabBoost_i
μ_G = 1,00
```

### 7.6 Câu mới trong mode Củng cố

Vẫn theo quota 30/20/10. Xếp trong unseen:

```text
U_new_ret(i) = Fit_i + 0,3 · Ĩ_i
```

Thiên về vừa sức; không nhồi câu cực khó khi đang củng cố lịch ôn.

---

## 8. Mode Cân bằng (`balanced`)

### 8.1 Mục tiêu sư phạm

Một phiên vừa vá yếu vừa chống quên; mặc định sản phẩm.

### 8.2 Phân suất (giữ hợp đồng + tinh chỉnh IRT tùy chọn)

**Cứng (đã chốt):**

```text
n_weak = ceil(reviewSlots / 2)
n_due  = reviewSlots − n_weak
thiếu nhóm này → bù nhóm kia; rồi mới / lap_day theo adaptive-session
```

**Mềm (IRT, chỉ mode này):** chỉnh tỷ lệ theo “độ khó phiên gần đây”, bám vùng P★.

Gọi `acc_recent` = tỷ lệ đúng các lượt hợp lệ trong K phiên thích ứng gần nhất (ví dụ K=3), hoặc trung bình `p_now` quan sát hóa (đúng/sai thực).

```text
nếu acc_recent < 0,60:
    n_weak' = max(1, round(0,70 · n_weak))     // giảm tải vá yếu
    n_due'  = reviewSlots − n_weak'             // thêm củng cố câu chắc hơn
nếu acc_recent > 0,90:
    n_weak' = min(reviewSlots, round(1,20 · n_weak))
    n_due'  = reviewSlots − n_weak'
nếu không:
    n_weak', n_due' giữ n_weak, n_due
```

Vẫn tôn trọng bù nhóm khi một bên thiếu câu. Đây là **điều chỉnh suất**, không đổi định nghĩa bucket.

### 8.3 Xếp hạng trong từng nửa suất

```text
các suất Yếu:     sort theo U_weak  (mục 6.4)
các suất Sắp quên: sort theo U_ret  (mục 7.5)
```

Không trộn một bảng utility duy nhất rồi bỏ phân suất — như vậy sẽ phá tỷ lệ mode.

### 8.4 Utility phiên (đo sau khi chọn — không dùng để phá bucket)

Sau khi đã pick đủ danh sách L:

```text
T_hat = Σ_{i ∈ L} I_i(θ_{d(i)})
P̄_cal = trung bình P_cal(i, θ_{d(i)}) trên L
```

Mục tiêu vận hành: `P̄_cal ∈ [0,65; 0,85]`, `T_hat` không giảm so với baseline khi A/B. Nếu `P̄_cal` lệch, chỉ chỉnh trọng số Fit / điều chỉnh suất mềm — **không** thay câu ngoài bucket.

### 8.5 Câu mới

```text
U_new_bal(i) = 0,6 · Fit_i + 0,4 · Ĩ_i
```

+ quy tắc bài dang dở hiện tại (exposure) giữ nguyên làm lớp ngoài; IRT chỉ reorder trong ứng viên cùng bài / cùng bước chọn mới.

---

## 9. Thuật toán chọn câu — giả mã có diễn giải

```text
INPUT: user, N, mode ∈ {weak_focus, balanced, retention}, scope (hệ/môn/kỳ thi…)

1. pool ← câu available theo scope + entitlement
2. tách unseen / eligible / resting (cooldown, thrash)     // hợp đồng cũ
3. với mỗi câu eligible (và provisional proxy nếu cần):
     θ_d ← θ miền của câu (EAP; shrink môn nếu SE lớn)
     P_cal ← σ(a (θ_d − b))
     p_mastery ← Beta–Binomial cập nhật từ P_cal + lịch sử y_i
     p_now ← g + (p_mastery − g) · R
     W_irt ← 1 − p_now
     I ← a² · P_cal · (1 − P_cal)

4. Yếu      ← { i eligible | p_now < τ_yếu }
   Sắp_quen ← { i eligible | is_due }
   // overlap: câu nằm cả hai tập; mode quyết định lấy ở nhánh nào

5. newCount, reviewSlots ← quota câu mới (30/20/10/100%)

6. theo mode:
   weak_focus:
       lấy k = min(|Yếu|, reviewSlots) theo U_weak
       không bù; co N nếu thiếu
   retention:
       lấy k = min(|Sắp_quen|, reviewSlots) theo U_ret
       không bù Yếu
   balanced:
       tính n_weak', n_due' (có chỉnh mềm theo acc_recent)
       pick n_weak' từ Yếu theo U_weak
       pick n_due'  từ Sắp_quen \ đã pick theo U_ret
       thiếu → bù chéo như adaptive-session
       vẫn thiếu → mới / lap_day

7. pick newCount unseen theo U_new_* + bài dang dở
8. diversity window → shuffle thứ tự hiển thị
9. trả về danh sách; ghi trace: P_cal, p_now, I, U, bucket, mode
```

---

## 10. Cập nhật sau phiên (đúng nguyên lý IRT)

### 10.1 Tham số câu (a, b)

- **Không** cập nhật từ một phiên luyện.
- Chỉ qua calibration batch (xem `irt-model-plan.md`).

### 10.2 Năng lực θ_d

Sau lượt sạch (không hint, ≥ 5s), cập nhật Bayes với item đóng băng:

Ý niệm (Bernoulli–logit):

```text
prior:     θ ~ Normal(μ_d, σ_d²)     // μ_d có thể = θ_môn khi bài mỏng
likelihood: x | θ ~ Bernoulli( σ(a(θ − b)) )
posterior → θ_new, SE_new
```

Lượt có hint: cập nhật **p_mastery / W_irt / S** theo quy tắc học tập; **không** (hoặc có trọng số rất thấp, nếu Product chốt) đưa vào θ sạch.

### 10.3 Bộ nhớ S và cooldown

Giữ nguyên `MemoryStability` và cooldown / thrash trong `adaptive-session.md`. IRT không thay lịch đến hạn.

---

## 11. Bảng tra nhanh 3 mode

| | Điểm yếu | Cân bằng | Củng cố |
|--|----------|----------|---------|
| `adaptive_focus` | `weak_focus` | `balanced` | `retention` |
| Suất ôn chính | 100% Yếu | ~50% Yếu + ~50% Due | 100% Due |
| Bù nhóm kia | Không | Có | Không |
| Điều kiện nhóm | `p_now < τ_yếu` | cả hai | `is_due` |
| Utility chính | `U_weak` | `U_weak` + `U_ret` theo suất | `U_ret` / `Gap_ret` |
| Vai trò `W_irt` | Chủ đạo | Chủ đạo nửa Yếu | Không dùng để xếp Due |
| Vai trò `I(θ)` | Phụ (phá trùng, vừa sức) | Phụ trong từng nửa | Phụ + Gap với P_cal |
| Vai trò `P_cal` | Fit + Unexpected | Fit + chỉnh suất mềm | Gap_ret = P_cal(1−R) |
| Câu mới | Fit nặng | Fit + I | Fit |
| Mục tiêu P̄ phiên | ~0,60–0,75 (khó hơn một chút vì đang vá) | ~0,70–0,85 | ~0,75–0,90 (ôn lại điều đã từng biết) |

---

## 12. Ví dụ số một phiên Cân bằng (N = 10)

Giả sử reviewSlots = 8, newCount = 2 → n_weak = 4, n_due = 4.  
θ môn Tim mạch = −0,2. Các câu đã calibrate.

**Ứng viên Yếu (rút gọn):**

| ID | a | b | P_cal | R | p_mastery | p_now | W_irt | U_weak (định tính) |
|----|---|---|------:|---:|----------:|------:|------:|--------------------|
| A | 1,1 | −0,2 | 0,50 | 0,55 | 0,32 | 0,26 | 0,74 | cao (vừa sức + yếu) |
| B | 1,0 | 1,5 | 0,15 | 0,40 | 0,20 | 0,18 | 0,82 | W cao nhưng Fit kém |
| C | 1,3 | 0,0 | 0,44 | 0,70 | 0,35 | 0,30 | 0,70 | I cao, Unexpected vừa |

Pick 4: A, C ưu tiên trước B nếu λ_F, λ_I đủ lớn — **không** chỉ sort W.

**Ứng viên Due:**

| ID | P_cal | R | Gap_ret | S bậc | Ưu tiên |
|----|------:|---:|--------:|------:|---------|
| D | 0,80 | 0,35 | 0,52 | 2 | cao |
| E | 0,55 | 0,30 | 0,39 | 1 | cao (StabBoost) |
| F | 0,92 | 0,85 | 0,14 | 4 | thấp hơn (còn nhớ nhiều) |

Pick 4 theo U_ret: D, E trước F.

Hai câu mới: chọn trong bài dang dở có Fit cao nhất với θ = −0,2 (b gần −0,2…0).

---

## 13. Kiểm chứng “đúng IRT” khi vận hành adaptive

Checklist chấp nhận:

1. **ICC:** với nhóm học viên có θ̂ trong bin hẹp, tỷ lệ đúng thực trên câu i gần `P_cal(i, θ_bin)`.
2. **p_now:** trong các câu selector gán p_now ≈ 0,3, tỷ lệ đúng quan sát ≈ 0,3 (± sai số mẫu).
3. **Mode isolation:** log phiên `weak_focus` không chứa bucket `sap_quen` (trừ khi Product đổi hợp đồng).
4. **Không ô nhiễm calibrate:** pipeline a,b loại attempt hint; θ sạch tách khỏi θ hiển thị luyện tập nếu cần.
5. **Vùng khó:** median `P_cal` theo mode khớp bảng mục 11; nếu lệch, chỉnh λ_F / μ_F trước khi đụng quota.

---

## 14. Tham số chốt nhanh (một chỗ)

| Tham số | Giá trị đề xuất | Ảnh hưởng |
|---------|-----------------|-----------|
| Mô hình | 2PL | P_cal, I |
| P★ | 0,75 | Fit, câu mới |
| τ_yếu | 0,60 | Độ rộng nhóm Yếu |
| H_câu | 40 ngày | Pha lịch sử trong p_mastery |
| m | 2 | Độ tin P_cal khi ít lượt |
| g | 1/(số phương án) | Sàn p_now |
| R trong p_now | `1/(1+t/(9S))` | Phân tán điểm yếu |
| λ_W, λ_I, λ_F, λ_U | 1 / 0,25 / 0,35 / 0,40 | Mode Điểm yếu |
| μ_G, μ_I, μ_F, μ_S | 1 / 0,30 / 0,25 / 0,35 | Mode Củng cố |
| Ngưỡng chỉnh balanced | acc 0,60 / 0,90 | Đổi n_weak' |

---

## 15. Kết luận

1. **IRT đúng nghĩa** trong adaptive = dùng `P_cal = σ(a(θ−b))` và `I = a²P(1−P)` từ tham số đã hiệu chuẩn; θ cập nhật trên response sạch.
2. **Quên, gợi ý, cooldown, 3 mode** là lớp sư phạm bọc ngoài — tạo `p_now`, bucket, suất — không sửa lại ICC.
3. **Điểm yếu** tối ưu `W_irt` + vừa sức + lỗi bất thường.  
   **Củng cố** tối ưu khoảng trống nhớ `P_cal(1−R)` trên câu due.  
   **Cân bằng** giữ chia suất, mỗi nửa dùng đúng utility của nó, chỉ nới tỷ lệ khi phiên quá dễ/quá khó.

Tài liệu này là đặc tả công thức để implement sau; không thay quy tắc popup / extra_practice / study day đã chốt ở `adaptive-session.md`.
