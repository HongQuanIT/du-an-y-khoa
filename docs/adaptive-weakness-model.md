# Đề xuất: Điểm yếu theo xác suất & xoay vòng câu yếu cho học viên học thưa

---

## 0. Tóm tắt

Có ba vấn đề, giải quyết bằng ba thay đổi riêng:

| | Vấn đề | Giải pháp | Dữ liệu mới |
|---|---|---|---|
| **1** | Điểm yếu tính quá đơn giản, nhiều câu trùng giá trị, bỏ qua thời gian, gợi ý, độ khó câu và năng lực theo bài/môn | Điểm yếu = 1 − **xác suất trả lời đúng nếu hỏi ngay bây giờ**, tính qua 6 bước | 2 cột thống kê độ khó trên bảng `questions` |
| **2** | Học viên học thưa (ví dụ 1 buổi/tuần): câu yếu vừa không bao giờ được gọi vì không đủ suất | Nhân điểm yếu với một **hệ số xoay vòng theo số phiên** chưa gặp | Không |
| **3** | Câu yếu dồn vào vài bài / môn; điểm yếu của bài chưa rõ khi bài ít dữ liệu, hoặc bài có nhiều / ít câu | **Chọn lần lượt có giảm dần** theo bài và môn, kèm trần; định nghĩa **điểm yếu bài** = 1 − khả năng đúng trung bình trên cả bài, kèm **độ tin cậy** | Không |

Thứ tự triển khai đề xuất: **vấn đề 2 và 3 trước** (không cần dữ liệu mới), **vấn đề 1 sau**. Lưu ý: vấn đề 1 làm các câu cùng bài yếu cùng nhau, nên **không nên triển khai vấn đề 1 mà thiếu vấn đề 3** (mục 3.1).

Hai mô phỏng, học viên học 1 phiên/tuần trong 16 tuần (chi tiết ở mục 4):

| Chỉ số | Hiện tại | Sửa 1 + 2 | Sửa 1 + 2 + 3 |
|---|---:|---:|---:|
| Câu yếu vừa được ôn ít nhất 1 lần (mô phỏng 1) | 24% | 48% | — |
| Phần suất Yếu dành cho câu luôn yếu (mô phỏng 1) | 65% | 31% | — |
| Phần lớn nhất của một bài trong phiên (mô phỏng 2) | 40% | 44% | **18%** |
| Phiên bị một bài chiếm ≥ 50% suất (mô phỏng 2) | 23% | 27% | **2%** |
| Số bài khác nhau trong phiên 7 câu (mô phỏng 2) | 4,6 | 4,4 | **6,6** |
| Bài có câu yếu được ôn ít nhất 1 lần sau 16 tuần (mô phỏng 2) | 74% | 81% | **90%** |


---



## 1. Vấn đề 1 — Điểm yếu theo xác suất trả lời đúng



### 1.1 Hiện trạng

```text
W = (số lần sai + 1) / (số lần làm + 2)      — trên tối đa 5 lần làm gần nhất của câu
Câu vào nhóm Yếu khi W ≥ 0,5
```

**Hạn chế 1 — Trùng giá trị.** Với tối đa 5 lượt, câu thuộc nhóm Yếu chỉ có thể nhận **9 giá trị**: 0,5 · 0,57 · 0,6 · 0,67 · 0,71 · 0,75 · 0,8 · 0,83 · 0,86. Hàng trăm câu chia nhau 9 mức. Khi bằng điểm, code xếp theo `question_id`, nên câu có mã nhỏ luôn được ưu tiên một cách vô lý.

**Hạn chế 2 — Bỏ qua thông tin quan trọng:**


| Thông tin                                                         | W hiện tại                 |
| ----------------------------------------------------------------- | -------------------------- |
| Sai từ 3 tháng trước hay hôm qua                                  | Như nhau                   |
| Đúng tự lực hay đúng sau khi xem gợi ý (Key info / Attending tip) | Như nhau, đều tính là đúng |
| Câu dễ hay câu khó                                                | Không xét                  |
| Học viên giỏi hay kém ở bài / môn chứa câu                        | Không xét                  |
| Khoảng cách giữa các lần làm                                      | Không xét                  |




### 1.2 Ý tưởng

> **Điểm yếu của một câu = 1 − khả năng học viên trả lời đúng câu đó nếu hỏi ngay bây giờ.**

Câu có khả năng đúng 30% có điểm yếu 0,70; câu có khả năng đúng 80% có điểm yếu 0,20. Điểm yếu là một số liên tục, nên các câu gần như không còn trùng nhau. Nó cũng có ý nghĩa rõ ràng và **kiểm chứng được**: nếu mô hình nói 30%, thì trong nhóm các câu được dự đoán 30%, khoảng 30% phải được trả lời đúng.

Cách kết hợp **độ khó câu + năng lực học viên + lịch sử học + thời gian** để dự đoán khả năng nhớ là cấu trúc của mô hình DASH. Trong một thử nghiệm kéo dài một học kỳ, lịch ôn cá nhân hoá theo DASH giúp học sinh nhớ nhiều hơn 16,5% so với học dồn và 10% so với lịch ôn giãn cách chung cho mọi người [1]. Cách chia thành "ước lượng kiến thức ban đầu" rồi "cập nhật bằng kết quả làm bài" cũng là thiết kế đã được đánh giá trên một hệ thống luyện tập có rất nhiều người dùng [2].

### 1.3 Sơ đồ 6 bước

```text
Bước 0  Chấm điểm từng lượt làm         y = 1 / 0,5 / 0           ← dùng gợi ý được nửa điểm
             │
Bước 1  Độ dễ của câu                    d_q                       ← độ khó câu
             │
Bước 2  Năng lực học viên ở bài / môn    θ_bài                     ← chấm theo độ khó: đúng câu khó được cộng nhiều
             │                                                        sai câu dễ bị trừ nhiều
Bước 3  Kỳ vọng ban đầu cho câu          p₀ = f(d_q, θ_bài)
             │
Bước 4  Cập nhật bằng lịch sử của câu    p_nhớ                     ← lượt gần đây nặng hơn
             │
Bước 5  Trừ phần đã quên                 p_now                     ← khoảng cách & thời gian
             │
        Điểm yếu = 1 − p_now
```



### 1.4 Hai khái niệm cần nắm trước

**a) Chu kỳ bán rã H — lượt cũ nhẹ dần**

```text
trọng số của một lượt = 0,5 ^ (tuổi / H)       tuổi = số ngày từ lúc làm đến nay
```

Sau H ngày, một lượt chỉ còn **một nửa** giá trị; sau 2H còn một phần tư.


| Tuổi lượt làm | H = 40 | H = 60 |
| ------------- | ------ | ------ |
| 0 ngày        | 1,00   | 1,00   |
| 20 ngày       | 0,71   | 0,79   |
| 40 ngày       | 0,50   | 0,63   |
| 60 ngày       | 0,35   | 0,50   |
| 120 ngày      | 0,13   | 0,25   |


**b) Logit — cách cộng xác suất mà không vượt 0–100%**

```text
logit(p) = ln( p / (1 − p) )        đổi xác suất thành một con số trên trục (−∞, +∞)
σ(x)     = 1 / (1 + e^(−x))          đổi ngược lại thành xác suất
```


| p        | 0,20  | 0,35  | 0,50 | 0,65 | 0,80 |
| -------- | ----- | ----- | ---- | ---- | ---- |
| logit(p) | −1,39 | −0,62 | 0    | 0,62 | 1,39 |


Không thể cộng thẳng "câu này 80% người làm đúng" với "học viên giỏi hơn trung bình 20%", vì kết quả có thể vượt 100%. Cộng trên trục logit rồi đổi ngược thì kết quả luôn nằm trong 0–100%. PM không cần tính tay; chỉ cần hiểu: **câu khó kéo xác suất xuống, học viên giỏi kéo lên.** Đây là cấu trúc của mô hình Rasch trong khảo thí [5] và của hệ thống Elo dùng trong giáo dục [6].

---



### Bước 0 — Chấm điểm một lượt làm (`y`)

```text
Lượt hợp lệ: is_correct khác null  VÀ  time_spent_seconds ≥ 5

y = 1     đúng, không mở gợi ý
y = 0,5   đúng, có mở gợi ý (used_hint = true)
y = 0     sai
```


| Biến                 | Nguồn                    | Ý nghĩa                                                                                             |
| -------------------- | ------------------------ | --------------------------------------------------------------------------------------------------- |
| `is_correct`         | `question_attempts`      | Đúng / sai. Null (bỏ qua, omit) thì không tính.                                                     |
| `time_spent_seconds` | `question_attempts`      | Dưới 5 giây coi là bấm bừa, không tính. Giữ như quy tắc hiện tại.                                   |
| `used_hint`          | `question_attempts`      | Bật khi học viên mở **Key info** hoặc **Attending tip** trước khi trả lời (`AnswerQuestionAction`). |
| `y_gợi_ý`            | Tham số, đề xuất **0,5** | Điểm cho lượt đúng có gợi ý                                                                         |


**Vì sao đúng có gợi ý chỉ được nửa điểm:** học viên đã trả lời đúng, nhưng chưa chứng minh được là **tự nhớ ra**. Coi như đúng trọn vẹn thì mô hình đánh giá cao quá mức; coi như sai thì không công bằng.

**Cơ sở:** Wang & Heffernan thay đầu vào đúng/sai bằng điểm từng phần, trừ điểm theo số gợi ý đã dùng. Mô hình dùng điểm từng phần dự đoán kết quả học sinh **tốt hơn ổn định** so với mô hình chỉ ghi đúng/sai [3]. Con số 0,5 là giá trị khởi đầu, không lấy từ nghiên cứu.

**Mở rộng sau (tuỳ chọn):** dữ liệu ghi riêng `key_info_used` và `attending_tip_used`, nên có thể cho điểm khác nhau theo loại gợi ý, ví dụ Key info 0,6 và Attending tip 0,4, nếu PM đánh giá hai loại gợi ý "lộ đáp án" ở mức khác nhau.


| `y_gợi_ý` | Tăng lên (ví dụ 0,7)                                                            | Giảm xuống (ví dụ 0,3)                                                    |
| --------- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Ảnh hưởng | Mở gợi ý gần như không bị trừ; học viên dùng gợi ý nhiều vẫn được coi là đã nắm | Mở gợi ý gần như bị coi là sai; câu đúng nhờ gợi ý vẫn nằm trong nhóm Yếu |


---



### Bước 1 — Độ dễ của câu (`d_q`)

```text
d_q = (stat_correct + k_d × d_nhãn) / (stat_attempts + k_d)
```


| Biến / tham số  | Giá trị                     | Ý nghĩa                                                                                   |
| --------------- | --------------------------- | ----------------------------------------------------------------------------------------- |
| `stat_attempts` | dữ liệu **mới**             | Số học viên đã làm câu q. Mỗi người chỉ tính **lượt hợp lệ đầu tiên**.                    |
| `stat_correct`  | dữ liệu **mới**             | Tổng điểm `y` của các lượt đầu tiên đó (đúng 1, đúng có gợi ý 0,5, sai 0)                 |
| `d_nhãn`        | theo `questions.difficulty` | Rất dễ **0,90** · Dễ **0,80** · Trung bình **0,65** · Khó **0,45** · Rất khó **0,30**     |
| `k_d`           | **20**                      | Câu ít người làm thì tin nhãn của người soạn; khoảng 50 lượt trở lên thì tin dữ liệu thật |
| `d_q`           | 0..1                        | **Tỷ lệ người làm đúng câu q ngay lần đầu.** Số càng lớn càng dễ.                         |


Ví dụ: câu được gắn nhãn **Khó** (0,45).

- Mới có 5 người làm, 4 người đúng: `d_q = (4 + 20 × 0,45) / (5 + 20) = 0,52`. Vẫn gần nhãn.
- Có 200 người làm, 150 người đúng: `d_q = (150 + 9) / 220 = 0,72`. Dữ liệu cho thấy câu thật ra không khó, và d_q đi theo dữ liệu.

**Vì sao chỉ lấy lượt đầu tiên của mỗi người:** lượt sau bị ảnh hưởng bởi việc đã xem giải thích, và người hay sai sẽ bị hệ thống thích ứng hỏi lại nhiều lần, làm câu trông khó hơn thực tế [8].

**Cơ sở:** tỷ lệ người làm đúng là chỉ số độ khó kinh điển của lý thuyết khảo thí cổ điển [4]. Cách "kéo về nhãn khi ít dữ liệu" là ước lượng co về, giải thích ở Bước 2 [7].

**Dữ liệu mới:** thêm `stat_attempts`, `stat_correct` vào bảng `questions`, cập nhật bằng job chạy mỗi đêm.

---



### Bước 2 — Năng lực học viên ở bài / môn, chấm theo độ khó (`θ_bài`)

Đây là chỗ **độ khó câu được dùng để chấm điểm học viên**. Thay vì hỏi "học viên đúng bao nhiêu %", ta hỏi:

> **"Trên chính những câu học viên đã làm, họ làm tốt hơn hay kém hơn người học trung bình?"**

**2a. Mỗi câu đã làm đóng góp một điểm, so với kỳ vọng**

```text
Với mỗi câu học viên đã làm trong bài (mỗi câu chỉ tính 1 lần — lượt hợp lệ gần nhất):
  w  = 0,5 ^ (tuổi lượt đó / H_bài)        H_bài = 60 ngày
  y  = điểm lượt đó (Bước 0)
  d  = độ dễ của câu (Bước 1)

N_bài = Σ w           (lượng bằng chứng)
C_bài = Σ w × y       (điểm thật của học viên)
E_bài = Σ w × d       (điểm mà người học trung bình sẽ đạt trên đúng những câu đó)
```

Đúng câu khó được cộng nhiều; sai câu dễ bị trừ nhiều:


| Câu | Độ dễ d | Nếu đúng: y − d                                | Nếu sai: y − d                                         |
| --- | ------- | ---------------------------------------------- | ------------------------------------------------------ |
| Dễ  | 0,85    | **+0,15** (ai cũng đúng, không chứng tỏ nhiều) | **−0,85** (ai cũng đúng mà mình sai — dấu hiệu yếu rõ) |
| Khó | 0,45    | **+0,55** (đúng câu khó — dấu hiệu giỏi rõ)    | **−0,45** (nhiều người cũng sai)                       |


**2b. Co về mức của môn khi ít dữ liệu, rồi đổi thành chênh lệch năng lực**

```text
a_bài = (C_bài + k × a_môn) / (N_bài + k)        tỷ lệ đúng của học viên (đã co về)
e_bài = (E_bài + k × e_môn) / (N_bài + k)        tỷ lệ đúng kỳ vọng của người trung bình (co về cùng cách)

θ_bài = logit(a_bài) − logit(e_bài)
```

`a_môn`, `e_môn` tính giống hệt nhưng trên mọi câu thuộc môn và co về `a_tổng`, `e_tổng`. Cấp tổng co về mức trung bình (`a = e = 0,65`, tức θ = 0) với `k_tổng = 10`.


| Biến    | Ý nghĩa                                                                                                                                        |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `N_bài` | Khoảng bao nhiêu câu gần đây học viên đã làm ở bài. Cũng là **độ tin cậy** của đánh giá.                                                       |
| `k`     | **Số "lượt ảo"** kéo về mức của môn, đề xuất **5**. Bài mới làm 1–2 câu thì gần như lấy năng lực môn; làm 20 câu thì gần như lấy dữ liệu thật. |
| `θ_bài` | **Chênh lệch năng lực so với người trung bình**, trên trục logit. θ = 0 là ngang trung bình; θ < 0 là yếu hơn; θ > 0 là giỏi hơn.              |


**Cách đọc θ cho PM:** θ = −0,55 nghĩa là câu mà người trung bình đúng 65%, học viên này đúng khoảng **52%**. θ = +0,5 thì câu đó học viên đúng khoảng 75%.

**Vì sao mỗi câu chỉ tính 1 lần:** hệ thống thích ứng cố tình hỏi lại câu yếu nhiều lần. Nếu câu bị sai 6 lần được tính 6 lần, bài chứa nó sẽ bị kéo xuống 6 lần, rồi hệ thống lại đưa thêm câu của bài đó. Hệ thống tự làm lệch dữ liệu của chính mình [8].

**Câu thuộc nhiều bài / bài thuộc nhiều môn:** câu được tính cho mỗi bài chứa nó. Khi bài thuộc nhiều môn, mức môn để co về là trung bình các môn, môn nào có N lớn hơn thì nặng hơn.

**Cơ sở:**

- So sánh kết quả thật với kết quả kỳ vọng theo độ khó câu là nguyên lý của mô hình Rasch [5] và của hệ thống Elo trong giáo dục. Ở đó, năng lực được cộng theo `(kết quả − kỳ vọng)` sau mỗi câu [6].
- Co về mức chung khi ít dữ liệu cho sai số thấp hơn so với dùng tỷ lệ thô [7].
- Giảm trọng số lượt cũ theo hàm mũ là cách tóm tắt lịch sử dự đoán chính xác nhất trong các cách được Galyardt & Goldin so sánh [9].

---



### Bước 3 — Kỳ vọng ban đầu cho câu (`p₀`)

```text
p₀ = σ( logit(d_q) + θ_bài )          không thấp hơn g (mức đoán mò, Bước 5)
```

**Đọc công thức:** "Người trung bình đúng câu này `d_q`. Học viên này giỏi hơn hay kém hơn trung bình ở bài này bao nhiêu (`θ_bài`) thì điều chỉnh lên hoặc xuống bấy nhiêu."

`p₀` là dự đoán **trước khi** xét lịch sử riêng của học viên trên câu này. Với câu học viên mới làm 1 lần, `p₀` quyết định phần lớn kết quả. Đây là chỗ độ khó câu và năng lực bài/môn giúp **phân biệt hai câu cùng sai 1 lần**.

**Cơ sở:** mô hình Rasch [5]; mô hình DASH [1]. Câu thuộc nhiều bài thì lấy θ của bài có N lớn nhất.

---



### Bước 4 — Cập nhật bằng lịch sử của chính câu (`p_nhớ`)

```text
α = m × p₀       + Σ wᵢ × yᵢ
β = m × (1 − p₀) + Σ wᵢ × (1 − yᵢ)
wᵢ = 0,5 ^ (tuổiᵢ / H_câu)
p_nhớ = α / (α + β)
```


| Biến / tham số | Giá trị đề xuất | Ý nghĩa                                                                                |
| -------------- | --------------- | -------------------------------------------------------------------------------------- |
| `i`            | —               | Từng lượt hợp lệ của học viên trên câu này, tối đa **8** lượt gần nhất (hiện tại là 5) |
| `yᵢ`           | 1 / 0,5 / 0     | Điểm lượt i (Bước 0). Đúng có gợi ý chia đều nửa sang α, nửa sang β.                   |
| `wᵢ`           | 0..1            | Trọng số theo thời gian                                                                |
| `H_câu`        | **40 ngày**     | Chu kỳ bán rã của bằng chứng cấp câu                                                   |
| `m`            | **2**           | **Độ tin vào kỳ vọng ban đầu**, tính bằng số lượt ảo                                   |
| `α`, `β`       | —               | Tổng "bằng chứng đúng" và "bằng chứng sai", kể cả lượt ảo                              |
| `p_nhớ`        | 0..1            | Khả năng đúng nếu học viên **vừa ôn xong**, chưa tính phần quên                        |


**Cách hiểu:** bắt đầu với 2 lượt ảo mang giá trị `p₀`, rồi cộng các lượt thật có trọng số. Sau 2–3 lượt thật gần đây, lịch sử riêng của câu chiếm ưu thế. Lượt từ 2–3 tháng trước chỉ còn ảnh hưởng nhỏ.


| Tham số | Tăng lên thì                                                           | Giảm xuống thì                                           |
| ------- | ---------------------------------------------------------------------- | -------------------------------------------------------- |
| `m`     | Lịch sử câu ít ảnh hưởng; câu chịu chi phối bởi độ khó và năng lực bài | Một lần sai đã kéo câu xuống mạnh                        |
| `H_câu` | Lượt cũ còn nặng lâu                                                   | Câu sai lâu rồi nhanh chóng được coi như câu bình thường |


**Cơ sở:** cập nhật Beta–Binomial với lượt ảo là phép cập nhật Bayes chuẩn [10]; giảm trọng số lượt cũ theo kết quả của Galyardt & Goldin [9]; điểm từng phần theo Wang & Heffernan [3].

---



### Bước 5 — Trừ phần đã quên theo thời gian (`p_now`)

```text
p_now = g + (p_nhớ − g) × R
R     = 1 / (1 + t / (9 × S))
g     = 1 / số phương án của câu
```


| Biến    | Nguồn                                                 | Ý nghĩa                                                                   |
| ------- | ----------------------------------------------------- | ------------------------------------------------------------------------- |
| `S`     | `question_status.memory_stability_days` (**đang có**) | Bậc độ bền: 1, 3, 7, 14, 30, 60 ngày                                      |
| `t`     | `now − last_graded_at` (**đang có**)                  | Số ngày từ lần chấm hợp lệ cuối                                           |
| `R`     | Tính                                                  | Khả năng còn nhớ: 1 khi vừa ôn, **0,9 khi t = S**, rồi giảm dần           |
| `g`     | Số phương án (**đang có**)                            | **Mức đoán mò.** Quên hết thì chọn bừa vẫn đúng g; 5 phương án → g = 0,2. |
| `p_now` | Kết quả                                               | **Khả năng trả lời đúng nếu hỏi ngay bây giờ**                            |


**Khoảng cách giữa các lượt làm nằm ở S.** Thang độ bền hiện tại chỉ cho câu lên bậc khi trả lời đúng **sau khi đã đến hạn**. Câu đã nhớ được qua các khoảng nghỉ 7, 14, 30 ngày thì có S lớn và quên chậm. Câu chỉ đúng khi làm dồn liền nhau thì vẫn ở bậc thấp và quên nhanh.

**Vì sao đổi dạng công thức R:**

- Code hiện tại dùng `R = 0,9^(t/S)` (dạng mũ). Dạng này giảm về 0 rất nhanh: câu S = 1 bỏ 60 ngày có R ≈ 0,002. Mọi câu bỏ lâu sẽ cùng có `p_now ≈ g`, tức lại **trùng điểm yếu**.
- Dạng mới `1 / (1 + t/(9S))` giữ nguyên định nghĩa "**R = 0,9 khi t = S**", nhưng đuôi giảm chậm dần (dạng lũy thừa). Câu S = 1 bỏ 60 ngày có R ≈ 0,13.


| t (ngày), với S = 1       | 1    | 7    | 30   | 60    |
| ------------------------- | ---- | ---- | ---- | ----- |
| R dạng mũ (hiện tại)      | 0,90 | 0,48 | 0,04 | 0,002 |
| R dạng lũy thừa (đề xuất) | 0,90 | 0,56 | 0,23 | 0,13  |


Đề xuất chỉ dùng R mới trong công thức điểm yếu. Cách tính **đến hạn** theo ngày học **không đổi**.

**Cơ sở:**

- Đường cong quên được Ebbinghaus mô tả lần đầu [11].
- Wixted & Ebbesen so sánh 6 dạng hàm trên dữ liệu nhớ từ, nhận diện khuôn mặt và chính dữ liệu của Ebbinghaus. **Hàm lũy thừa khớp tốt nhất**, tốt hơn hàm mũ [12].
- Định nghĩa "sau S ngày còn nhớ 90%" tương đồng với định nghĩa độ bền của thuật toán ôn tập FSRS [13].
- `g` là tham số đoán mò của mô hình IRT ba tham số của Birnbaum, dùng cho câu trắc nghiệm [4].

---



### Kết quả — Điểm yếu và nhóm Yếu

```text
Điểm yếu = 1 − p_now
Câu vào nhóm Yếu ⇔ p_now < θ_yếu            θ_yếu = 0,6
Xếp hạng trong nhóm Yếu: điểm yếu cao trước; bằng nhau thì ngẫu nhiên (không theo question_id)
```


| `θ_yếu`       | Tăng lên (ví dụ 0,7)                 | Giảm xuống (ví dụ 0,5)           |
| ------------- | ------------------------------------ | -------------------------------- |
| Nhóm Yếu      | Rộng hơn, gồm cả câu "chưa chắc lắm" | Hẹp hơn, chỉ câu thật sự hay sai |
| Mode Điểm yếu | Ít gặp thông báo "hết câu yếu"       | Hay gặp "hết câu yếu" hơn        |


**Cơ sở:** nguyên lý "khó khăn đáng mong muốn": luyện ở mức khó vừa phải giúp nhớ lâu hơn luyện ở mức quá dễ [14]. Wilson và cộng sự chứng minh rằng với một lớp thuật toán học (dựa trên gradient descent), học nhanh nhất khi tỷ lệ đúng khoảng 85%. Họ kiểm chứng điều này trên mạng nơ-ron nhân tạo và trên một mô hình mô phỏng việc học tri giác [15]. Kết quả này **gián tiếp** với người học thật. Ngưỡng 0,6 (dưới vùng mục tiêu 70–85%) là giá trị khởi đầu.

---



### 1.5 Ví dụ tính trọn vẹn

**Bối cảnh:** học viên đang học bài *Suy tim*, thuộc môn *Tim mạch*. Năng lực ở môn: `a_môn = 0,62`, `e_môn = 0,66` (tức θ_môn ≈ −0,17, hơi kém trung bình). Câu có 5 phương án, nên g = 0,2.

**Bước 2 — Năng lực ở bài Suy tim.** Học viên đã làm 6 câu trong bài, mỗi câu lấy lượt gần nhất (H_bài = 60):


| Câu      | Độ dễ d    | Kết quả       | y   | Tuổi    | w            | w × y        | w × d        |
| -------- | ---------- | ------------- | --- | ------- | ------------ | ------------ | ------------ |
| 1        | 0,80       | Sai           | 0   | 10 ngày | 0,891        | 0            | 0,713        |
| 2        | 0,75       | Đúng          | 1   | 30 ngày | 0,707        | 0,707        | 0,530        |
| 3        | 0,45 (khó) | Đúng          | 1   | 5 ngày  | 0,944        | 0,944        | 0,425        |
| 4        | 0,60       | Sai           | 0   | 20 ngày | 0,794        | 0            | 0,476        |
| 5        | 0,85 (dễ)  | Đúng có gợi ý | 0,5 | 15 ngày | 0,841        | 0,420        | 0,715        |
| 6        | 0,50       | Sai           | 0   | 45 ngày | 0,595        | 0            | 0,297        |
| **Tổng** |            |               |     |         | **N = 4,77** | **C = 2,07** | **E = 3,16** |


```text
Tỷ lệ thô: học viên 43% (2,07 / 4,77), người trung bình 66% (3,16 / 4,77) trên đúng những câu này

a_bài = (2,07 + 5 × 0,62) / (4,77 + 5) = 0,529
e_bài = (3,16 + 5 × 0,66) / (4,77 + 5) = 0,661
θ_bài = logit(0,529) − logit(0,661) = 0,116 − 0,668 = −0,55
```

Học viên yếu hơn trung bình ở bài này, và yếu hơn mức chung của họ ở môn (−0,17). Nhưng vì mới có khoảng 5 câu bằng chứng, θ chỉ được kéo xuống −0,55 chứ chưa xuống hẳn mức thô (khoảng −0,94).

**Câu X** trong bài Suy tim:


| Thông tin      | Giá trị                                                                  |
| -------------- | ------------------------------------------------------------------------ |
| Độ dễ d_q      | 0,55                                                                     |
| Lịch sử        | Sai (50 ngày trước) · Đúng có gợi ý (20 ngày trước) · Sai (6 ngày trước) |
| Độ bền S       | 1 ngày (vừa sai nên về bậc 1)                                            |
| t              | 6 ngày                                                                   |
| **W hiện tại** | (2 + 1) / (3 + 2) = **0,6** (lượt có gợi ý vẫn tính là đúng)             |


```text
Bước 3:  p₀ = σ( logit(0,55) + (−0,55) ) = σ(0,20 − 0,55) = σ(−0,35) = 0,414

Bước 4:  w(50 ngày) = 0,420   (sai, y = 0)
         w(20 ngày) = 0,707   (đúng có gợi ý, y = 0,5)
         w( 6 ngày) = 0,901   (sai, y = 0)

         α = 2 × 0,414 + 0,707 × 0,5                       = 1,181
         β = 2 × 0,586 + 0,420 + 0,707 × 0,5 + 0,901       = 2,847
         p_nhớ = 1,181 / (1,181 + 2,847)                   = 0,293

Bước 5:  R = 1 / (1 + 6 / 9) = 0,600
         p_now = 0,2 + (0,293 − 0,2) × 0,600              = 0,256

Điểm yếu = 1 − 0,256 = 0,74          → thuộc nhóm Yếu (p_now < 0,6)
```



### 1.6 Sáu câu cùng W = 0,6 — điểm yếu mới khác nhau

Mỗi dòng chỉ đổi **một** yếu tố so với câu X:


| Câu | Khác câu X ở chỗ                                                        | p_now | **Điểm yếu mới** | W cũ |
| --- | ----------------------------------------------------------------------- | ----- | ---------------- | ---- |
| X   | —                                                                       | 0,26  | **0,74**         | 0,6  |
| B   | Lượt đúng 20 ngày trước **không** dùng gợi ý                            | 0,31  | **0,69**         | 0,6  |
| C   | Câu **dễ** (d = 0,85)                                                   | 0,36  | **0,64**         | 0,6  |
| D   | Câu **khó** (d = 0,35)                                                  | 0,20  | **0,80**         | 0,6  |
| E   | Thuộc bài học viên **giỏi** (θ = +0,5)                                  | 0,33  | **0,67**         | 0,6  |
| F   | Cùng lịch sử nhưng **cũ hơn** (sai cách đây 120 · 90 · 60 ngày; t = 60) | 0,22  | **0,78**         | 0,6  |


Cách đọc:

- **B so với X:** đúng tự lực là bằng chứng mạnh hơn đúng nhờ gợi ý, nên câu bớt yếu.
- **C, D so với X:** câu khó thì khả năng sai lần tới cao hơn, nên yếu hơn.
- **E so với X:** học viên giỏi bài này thì câu lẻ bị sai có khả năng là sai nhầm, nên bớt yếu.
- **F so với X:** để lâu không ôn sau lần sai thì đã quên nhiều hơn, nên yếu hơn.

> **Lưu ý cho PM — "sai câu dễ" được tính ở đâu?**
> Ở Bước 2, sai câu dễ kéo **năng lực của cả bài** xuống mạnh (−0,85 so với −0,45 của câu khó), nên mọi câu trong bài đó đều yếu hơn.
> Nhưng ở cấp **từng câu** (dòng C), câu dễ vẫn có khả năng đúng lần sau cao hơn câu khó, nên điểm yếu thấp hơn. Mô hình xác suất trả lời câu hỏi "câu nào dễ sai nhất", chứ không phải "câu nào là kiến thức nền".
> Nếu PM muốn **ưu tiên câu dễ bị sai** (lỗ hổng kiến thức cơ bản), có thể thêm hệ số ưu tiên riêng. Xem câu hỏi ở mục 5.



### 1.7 Tham số của vấn đề 1


| Tham số             | Bước | Giá trị đề xuất                  | Khoảng hợp lý | Một câu mô tả                                 |
| ------------------- | ---- | -------------------------------- | ------------- | --------------------------------------------- |
| Thời gian tối thiểu | 0    | 5 giây                           | —             | Giữ như hiện tại                              |
| `y_gợi_ý`           | 0    | 0,5                              | 0,3–0,7       | Đúng có gợi ý được bao nhiêu điểm             |
| `d_nhãn`            | 1    | 0,90 / 0,80 / 0,65 / 0,45 / 0,30 | —             | Độ dễ ban đầu theo nhãn Rất dễ → Rất khó      |
| `k_d`               | 1    | 20                               | 10–50         | Bao nhiêu người làm thì tin dữ liệu hơn nhãn  |
| `CỬA_SỔ`            | 2    | 180 ngày                         | 90–365        | Lấy lịch sử bao xa khi tính năng lực bài/môn  |
| `H_bài`             | 2    | 60 ngày                          | 30–120        | Chu kỳ bán rã cấp bài/môn                     |
| `k`                 | 2    | 5                                | 3–10          | Số lượt ảo khi co bài về môn, môn về tổng     |
| `k_tổng`, mức chung | 2    | 10, 0,65                         | —             | Học viên mới coi như ngang trung bình         |
| `SỐ_LƯỢT_TỐI_ĐA`    | 4    | 8                                | 5–10          | Số lượt gần nhất của câu được xét             |
| `H_câu`             | 4    | 40 ngày                          | 20–60         | Chu kỳ bán rã cấp câu                         |
| `m`                 | 4    | 2                                | 1–4           | Độ tin vào kỳ vọng ban đầu so với lịch sử câu |
| `g`                 | 5    | 1 / số phương án                 | —             | Mức đoán mò                                   |
| `θ_yếu`             | KQ   | 0,6                              | 0,5–0,7       | Dưới mức này là câu Yếu                       |




### 1.8 Dữ liệu cần cho vấn đề 1


| Dữ liệu                                                                             | Trạng thái                                                       |
| ----------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| `question_attempts`: `is_correct`, `used_hint`, `time_spent_seconds`, `answered_at` | Đã có                                                            |
| `questions.difficulty` (5 mức)                                                      | Đã có                                                            |
| `question_status`: `memory_stability_days`, `last_graded_at`                        | Đã có                                                            |
| Quan hệ câu–bài (`question_lesson`), bài–môn (`lesson_subject`)                     | Đã có                                                            |
| Số phương án của câu                                                                | Đã có                                                            |
| `questions.stat_attempts`, `questions.stat_correct`                                 | **Mới** — job chạy mỗi đêm                                       |
| Năng lực bài/môn (N, C, E)                                                          | Tính lúc chọn câu và cache vài phút; nếu chậm thì lưu bảng riêng |


Bảng `topic_mastery` hiện có **không** dùng thẳng được: nó tính tỷ lệ thô, gồm cả lượt dưới 5 giây, không xét độ khó và gợi ý, không giảm trọng số lượt cũ, không giới hạn mỗi câu một lần.

---



## 2. Vấn đề 2 — Xoay vòng câu yếu cho học viên học thưa



### 2.1 Hiện trạng và nguyên nhân

Ví dụ: học viên học 1 buổi/tuần, có 150 câu yếu, mỗi phiên 10 câu (khoảng 7 suất Yếu).

1. **Xếp hạng cố định, chỉ xáo trong top** `2 × suất`**.** Code sắp nhóm Yếu theo W, rồi chỉ xáo ngẫu nhiên trong 14 câu đầu. Câu thứ 15 trở đi có **cơ hội bằng 0**.
2. **Vòng lặp tự khoá.** Câu luôn sai được chọn, sai tiếp, nên vẫn đứng top. Câu yếu vừa không được làm thì điểm yếu không đổi, nên không bao giờ lọt vào top.
3. **Cooldown tính theo ngày.** Câu chỉ nghỉ đến ngày học kế tiếp (tối thiểu 8 giờ). Học 1 buổi/tuần thì mọi câu đều đã hết nghỉ, nên cooldown không giúp nhường chỗ cho câu khác.

Kết quả mô phỏng: câu luôn yếu chiếm 15% số câu nhưng lấy **65%** suất Yếu; sau 16 tuần chỉ **24%** câu yếu vừa từng được ôn.

Điều này đi ngược hai kết quả được kiểm chứng nhiều nhất trong khoa học học tập: **kiểm tra truy hồi lặp lại theo thời gian** giúp nhớ lâu hơn học dồn [16][17], và điều này đúng cả trong đào tạo y khoa [18]. Kerfoot và cộng sự cho thấy chỉ cần câu hỏi tình huống lâm sàng được gửi **mỗi tuần một lần** cũng đã cải thiện rõ khả năng nhớ của sinh viên y [19]. Nghĩa là học thưa vẫn hiệu quả, **nếu** thuật toán xoay vòng được các câu cần ôn.

### 2.2 Giải pháp: hệ số xoay vòng

```text
Ưu tiên = Điểm yếu × (1 + (gap − 1) / T)

gap = số phiên thích ứng của học viên tính từ lần cuối câu được làm
      (tính cả phiên đang tạo; câu vừa làm ở phiên ngay trước có gap = 1)
T   = 3 phiên

Chọn các câu có Ưu tiên cao nhất cho đủ suất Yếu. Bằng nhau thì xếp ngẫu nhiên.
```


| Biến       | Nguồn                                                                                                                                                                                                      | Ý nghĩa                                                   |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------- |
| `Điểm yếu` | Vấn đề 1 (`1 − p_now`), hoặc tạm dùng W hiện tại                                                                                                                                                           | Mức độ yếu của câu                                        |
| `gap`      | Đếm số phiên thích ứng (`source = weak_topics`) của học viên được tạo sau `last_graded_at` của câu, cộng 1. **Không cần thêm cột**: selector hiện đã nạp danh sách thời điểm tạo phiên để kiểm tra thrash. | Câu đã "chờ" bao nhiêu phiên                              |
| `T`        | Tham số, đề xuất **3**                                                                                                                                                                                     | Cứ chờ thêm T phiên thì ưu tiên tăng thêm một lần mức gốc |



| gap (phiên)             | 1    | 2    | 4    | 7    | 10   |
| ----------------------- | ---- | ---- | ---- | ---- | ---- |
| Hệ số xoay vòng (T = 3) | 1,00 | 1,33 | 2,00 | 3,00 | 4,00 |


**Vì sao đếm theo phiên, không theo ngày:** "đã 4 phiên chưa gặp" có cùng ý nghĩa với người học mỗi ngày và người học mỗi tuần. Phần quên theo **thời gian thật** vẫn được tính ở Bước 5 của vấn đề 1 (theo ngày), nên câu bỏ lâu vẫn tự tăng điểm yếu.

**Không đặt trần cho hệ số**, nên **mọi câu yếu chắc chắn đến lượt**. Cooldown theo ngày học và thrash **giữ nguyên**: câu đang nghỉ vẫn bị loại ở bước Lọc như hiện tại.

### 2.3 Hệ quả: câu yếu hơn được hỏi lại thường hơn, nhưng câu nào cũng có lượt

Một câu được chọn lại khi ưu tiên của nó vượt **ngưỡng lọt phiên**, tức ưu tiên của câu cuối cùng còn lấy được suất. Khi đó:

```text
Khoảng cách giữa hai lần được hỏi ≈ 1 + T × (ngưỡng / Điểm yếu − 1)
```

Ví dụ với ngưỡng khoảng 1,0:


| Điểm yếu | Được hỏi lại khoảng mỗi |
| -------- | ----------------------- |
| 0,80     | 2 phiên                 |
| 0,60     | 3 phiên                 |
| 0,45     | 5 phiên                 |
| 0,40     | 5–6 phiên               |


Ngưỡng phụ thuộc vào số câu yếu so với số suất. Nhiều câu yếu thì vòng xoay dài hơn, nhưng **không có câu nào bị bỏ quên**.

### 2.4 Ví dụ

Học viên học 1 phiên/tuần.


|                     | Câu X (luôn sai) | Câu Y (yếu vừa)         |
| ------------------- | ---------------- | ----------------------- |
| Điểm yếu            | 0,74             | 0,45                    |
| Lần làm cuối        | Phiên tuần trước | 6 phiên trước           |
| gap                 | 1                | 6                       |
| Hệ số xoay vòng     | 1,00             | 1 + 5/3 = 2,67          |
| **Ưu tiên**         | **0,74**         | **1,20**                |
| Thuật toán hiện tại | Luôn được chọn   | Không bao giờ được chọn |


Tuần này Y được chọn trước X. Tuần sau, nếu X không được chọn thì gap của X tăng lên 2, ưu tiên thành 0,99, và X quay lại top.

### 2.5 Vì sao xếp hạng có quy tắc, không bốc thăm

Bản đề xuất trước dùng bốc thăm có trọng số. Bản này **xếp hạng cố định theo Ưu tiên** vì:

- Dễ giải thích cho học viên và bộ phận hỗ trợ: "câu này xuất hiện vì đã 6 phiên chưa ôn".
- Kết quả lặp lại được khi cần kiểm tra lỗi.
- Mô phỏng cho hiệu quả tương đương.



### 2.6 Tham số của vấn đề 2


| Tham số | Giá trị đề xuất | Khoảng hợp lý | Tăng lên thì                                  | Giảm xuống thì                                      |
| ------- | --------------- | ------------- | --------------------------------------------- | --------------------------------------------------- |
| `T`     | 3 phiên         | 2–5           | Ưu tiên câu rất yếu nhiều hơn, vòng xoay chậm | Xoay vòng nhanh, các câu yếu được chia suất đều hơn |




### 2.7 Cơ sở


| Ý                                           | Cơ sở                                                                                                   | Nguồn          |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------- | -------------- |
| Mọi câu yếu cần được hỏi lại theo thời gian | Hiệu ứng giãn cách, hiệu ứng kiểm tra; áp dụng trong y khoa                                             | [16] [17] [18] |
| Học 1 buổi/tuần vẫn hiệu quả nếu xoay vòng  | Thử nghiệm ngẫu nhiên có đối chứng trên sinh viên y, câu hỏi gửi hằng tuần                              | [19]           |
| Không nên hỏi câu luôn sai ở mọi phiên      | Người học hiệu quả ưu tiên những gì gần thuộc, và dừng khi việc học không còn tiến triển ("học vô ích") | [20]           |
| Tăng ưu tiên theo thời gian chờ             | Kỹ thuật "aging" chống bỏ đói trong lập lịch hệ điều hành                                               | [21]           |
| Đếm gap theo phiên                          | **Lựa chọn thiết kế**, không có nghiên cứu trực tiếp                                                    | —              |


---



## 3. Vấn đề 3 — Dàn trải câu yếu ra các bài và môn

### 3.1 Hiện trạng và nguyên nhân

Vấn đề 1 và 2 quyết định **ưu tiên của từng câu**, rồi lấy các câu ưu tiên cao nhất. Không có bước nào xét các câu được chọn thuộc bài / môn nào. Hệ quả là câu yếu dồn vào vài bài, vì ba lý do:

1. **Câu cùng bài yếu cùng nhau.** Ở Bước 3 của vấn đề 1, mọi câu trong bài dùng chung năng lực bài `θ_bài`. Học viên yếu bài *Suy tim* thì toàn bộ câu của bài này cùng tụt xuống và cùng đứng top. Ước lượng từng câu như vậy là **đúng**; chỉ việc lấy thẳng top mới gây dồn.
2. **Bài lớn chiếm suất chỉ vì có nhiều câu.** Bài 200 câu có 40 câu yếu sẽ lấn át bài 15 câu có 3 câu yếu, dù hai bài yếu như nhau.
3. **Xoay vòng chỉ chạy ở cấp câu.** Các câu thay nhau xuất hiện, nhưng các câu thay nhau vẫn có thể cùng một bài.

Mô phỏng 2 (mục 4.2) xác nhận điều này: chỉ sửa vấn đề 1 và 2 thì mức dồn **tăng nhẹ** so với hiện tại. Phần lớn nhất của một bài trong phiên tăng từ 40% lên 44%; số phiên bị một bài chiếm từ một nửa suất trở lên tăng từ 23% lên 27%.

**Vì sao dồn bài là vấn đề:**

- **Đi ngược hiệu ứng học xen kẽ (interleaving).** Trộn các dạng bài trong một buổi giúp nhớ lâu hơn và phân biệt khái niệm tốt hơn so với làm dồn từng dạng. Rohrer & Taylor cho thấy sinh viên luyện toán theo dạng trộn làm bài kiểm tra sau 1 tuần tốt hơn rõ rệt so với luyện theo từng khối [24]. Kornell & Bjork cho thấy học xen kẽ giúp nhận diện phong cách hoạ sĩ tốt hơn, dù người học lại **cảm thấy** học theo khối hiệu quả hơn [25]. Meta-analysis của Brunmair & Richter (59 nghiên cứu) tìm thấy hiệu ứng trung bình (g = 0,42), mạnh nhất khi các nhóm kiến thức dễ nhầm với nhau [26].
- **Có bằng chứng trong y khoa.** Sinh viên y luyện đọc điện tâm đồ theo dạng trộn nhiều chẩn đoán đạt độ chính xác 46% trên ca mới, so với 30% khi luyện theo từng khối chẩn đoán [27].
- **Lộ đáp án.** Các câu cùng bài thường chứa thông tin gợi ý cho nhau.
- **Bỏ sót bài.** Phiên dồn vào vài bài thì các bài yếu khác phải chờ lâu hơn.

### 3.2 Giải pháp A — Chọn lần lượt có giảm dần theo bài và môn

Thay vì lấy một lần các câu có ưu tiên cao nhất, ta **chọn từng câu một**. Mỗi khi chọn xong một câu, các câu còn lại cùng bài / cùng môn bị giảm ưu tiên:

```text
Ưu tiên hiệu dụng = Ưu tiên × λ^(số câu cùng bài đã chọn trong phiên) × μ^(số câu cùng môn đã chọn trong phiên)

Lặp lại cho đến khi đủ suất Yếu:
  1. Bỏ qua câu thuộc bài / môn đã chạm trần
  2. Chọn câu có Ưu tiên hiệu dụng cao nhất (bằng nhau thì ngẫu nhiên)
  3. Tăng bộ đếm bài / môn của câu vừa chọn

Nếu không còn ứng viên trong trần (ví dụ học viên chỉ lọc 1 bài) → bỏ trần để vẫn đủ phiên
```

| Biến / tham số | Giá trị đề xuất | Ý nghĩa |
|---|---|---|
| `Ưu tiên` | Mục 2.2 | Điểm yếu × hệ số xoay vòng của câu |
| `λ` | **0,5** | Hệ số giảm theo **bài**: câu thứ 2 cùng bài còn ½ ưu tiên, câu thứ 3 còn ¼ |
| `μ` | **0,8** | Hệ số giảm theo **môn**, nhẹ hơn vì một môn có nhiều bài khác nhau |
| `TRẦN_BÀI` | `ceil(N / 3)` câu | Tối đa số câu của một bài trong phiên N câu (N = 10 → 4) |
| `TRẦN_MÔN` | `ceil(N / 2)` câu | Tối đa số câu của một môn trong phiên (N = 10 → 5) |

**Ví dụ.** 7 suất Yếu. Bài A (*Suy tim*) có 40 câu yếu, ưu tiên khoảng 0,8. Các bài B, C, D có ít câu yếu hơn, ưu tiên 0,6–0,7. Để dễ theo dõi, ví dụ chỉ dùng λ:

| Lượt | Câu được chọn | Ưu tiên hiệu dụng | Thuật toán lấy thẳng top |
|---|---|---:|---|
| 1 | Bài A | 0,80 | Bài A |
| 2 | Bài B | 0,70 | Bài A |
| 3 | Bài D | 0,65 | Bài A |
| 4 | Bài C | 0,60 | Bài A |
| 5 | Bài A (câu thứ 2) | 0,40 | Bài A |
| 6 | Bài B (câu thứ 2) | 0,35 | Bài A |
| 7 | Bài D (câu thứ 2) | 0,33 | Bài A |

Kết quả: A 2 câu, B 2, C 1, D 2, thay vì A chiếm cả 7.

**Tính chất:**

- **Bài yếu hơn vẫn được nhiều suất hơn**, nhưng số suất tăng chậm dần chứ không tỷ lệ thuận với số câu yếu.
- **Bài ít câu không bị thiệt**: chỉ cần 1 câu đủ yếu là cạnh tranh ngang bài lớn ngay từ lượt đầu.
- **Kết hợp với xoay vòng**: xoay vòng quyết định ưu tiên từng câu qua các phiên; giảm dần quyết định cách dàn câu trong **một** phiên.
- **Trần chỉ là chốt an toàn.** Mô phỏng cho thấy chỉ dùng trần mà không có giảm dần (λ = μ = 1) thì gần như không đỡ dồn (mục 3.5).
- **Tôn trọng bộ lọc của học viên**: học viên tự chọn 1 bài thì trần tự bỏ, phiên vẫn đủ câu.

### 3.3 Giải pháp B — Điểm yếu của bài khi ít / nhiều dữ liệu, ít / nhiều câu

"Bài ít dữ liệu" và "bài ít câu" là **hai thứ khác nhau**, nên xử lý riêng.

#### a) Ít / nhiều dữ liệu = học viên đã làm bao nhiêu câu trong bài (`N_bài`)

Ít dữ liệu thì không tin tỷ lệ thô, mà kéo về mức của môn. Đây là phép co về đã có ở Bước 2 [7]. Ví dụ học viên đúng 60% ở môn nhưng chỉ đúng 30% ở bài đang xét (`k = 5`):

| Số câu đã làm trong bài (`N_bài`) | Ước lượng tỷ lệ đúng ở bài | Độ tin cậy |
|---:|---:|---|
| 1 | 55% (gần mức môn) | Chưa đủ dữ liệu |
| 3 | 49% | Sơ bộ |
| 10 | 40% | Khá |
| 30 | 34% (gần mức thật) | Tin cậy |

Quy tắc đề xuất:

| `N_bài` | Nhãn độ tin cậy | Hiển thị cho học viên "bài cần chú ý" |
|---|---|---|
| < 3 | Chưa đủ dữ liệu | **Không**, để tránh báo động giả. Ước lượng vẫn dùng nội bộ, nhưng đã gần mức môn. |
| 3–10 | Sơ bộ | Có, kèm chữ "sơ bộ" |
| > 10 | Tin cậy | Có |

**Rủi ro bỏ quên ở cấp bài.** Bài ít dữ liệu luôn bị ước lượng gần trung bình, nên ít được ưu tiên, nên càng ít dữ liệu. Chỗ xử lý hợp lý là **suất câu mới** (không phải nhóm Yếu, vì bài ít dữ liệu vốn có ít câu đã làm). Khi chọn bài để mở câu mới, cộng ưu tiên cho bài có độ tin cậy thấp. Đây là cách cân bằng "khai thác" (luyện chỗ đã biết là yếu) với "khám phá" (thu thêm dữ liệu chỗ chưa rõ), được nghiên cứu trong hệ thống dạy học dưới dạng bài toán multi-armed bandit [29]. Chi tiết suất câu mới nằm ngoài phạm vi tài liệu này.

#### b) Ít / nhiều câu = bài có bao nhiêu câu trong ngân hàng

Kích thước bài **không nên** ảnh hưởng tới điểm yếu của bài. Định nghĩa đề xuất:

```text
Điểm yếu bài = 1 − trung bình p của mọi câu trong bài
               câu đã làm:   p = p_now (Bước 5)
               câu chưa làm: p = p₀    (Bước 3)
```

**Cách đọc cho PM:** "Nếu kiểm tra toàn bộ bài này hôm nay, học viên đúng khoảng X%."

- Lấy **trung bình** chứ không lấy tổng, nên bài 200 câu và bài 15 câu được so sánh công bằng.
- Đã tính sẵn **độ khó** của bài: bài nhiều câu khó thì `p₀` thấp.
- Đã tính sẵn **độ phủ**: bài mới làm 5/200 câu thì phần lớn trung bình đến từ `p₀`, tức dựa trên năng lực môn và độ khó câu.
- Kích thước bài chỉ ảnh hưởng tới **số suất**, và việc đó do Giải pháp A kiểm soát.

Điểm yếu bài dùng để hiển thị và phân tích, không thay điểm yếu câu khi chọn câu.

#### c) Tóm tắt

| Tình huống | Xác suất / điểm yếu bài | Phân suất |
|---|---|---|
| Bài ít dữ liệu | Kéo về mức môn; nhãn "chưa đủ dữ liệu" | Nhóm Yếu: ít ứng viên. Câu mới: được ưu tiên mở để thu dữ liệu. |
| Bài nhiều dữ liệu | Gần tỷ lệ thật; nhãn "tin cậy" | Bình thường |
| Bài ít câu trong ngân hàng | Không ảnh hưởng (lấy trung bình) | Không bị thiệt: 1 câu yếu là đủ cạnh tranh |
| Bài nhiều câu trong ngân hàng | Không ảnh hưởng (lấy trung bình) | Có thể thêm suất, nhưng giảm dần (λ) và có trần |

### 3.4 Tham số của vấn đề 3

| Tham số | Giá trị đề xuất | Khoảng hợp lý | Tăng lên (gần 1) thì | Giảm xuống thì |
|---|---|---|---|---|
| `λ` | 0,5 | 0,3–0,7 | Cho phép nhiều câu cùng bài hơn; bám sát ưu tiên câu | Dàn đều các bài hơn |
| `μ` | 0,8 | 0,7–0,9 | Cho phép dồn môn hơn | Dàn đều các môn hơn |
| `TRẦN_BÀI` | ceil(N/3) | N/4–N/2 | Ít tác dụng chặn | Chặn cứng hơn |
| `TRẦN_MÔN` | ceil(N/2) | N/3–2N/3 | Ít tác dụng chặn | Chặn cứng hơn |
| Ngưỡng độ tin cậy `N_bài` | 3 / 10 | — | Ít bài được gắn nhãn hơn | Gắn nhãn sớm hơn, dễ báo động giả |

### 3.5 Độ nhạy của λ và μ (mô phỏng 2)

| λ, μ | Phần lớn nhất của 1 bài / phiên | Phiên bị 1 bài chiếm ≥ 50% | Số bài / phiên | Số môn / phiên | Bài yếu được ôn sau 16 tuần | Câu yếu vừa được ôn |
|---|---:|---:|---:|---:|---:|---:|
| 1,0; 1,0 (chỉ có trần) | 42% | 30% | 4,5 | 2,7 | 83% | 48% |
| 0,7; 0,9 | 21% | 4% | 6,4 | 3,4 | 90% | 45% |
| **0,5; 0,8 (đề xuất)** | **18%** | **2%** | **6,6** | **3,4** | **90%** | **44%** |
| 0,3; 0,7 | 18% | 2% | 6,7 | 3,5 | 90% | 43% |

Đọc bảng: từ 0,5 trở xuống thì mức dàn trải gần như bão hoà, giảm λ thêm không được gì. Cái giá là số câu yếu vừa được ôn giảm nhẹ (48% → 44%), vì một phần suất chuyển từ câu ưu tiên cao nhất sang câu của bài khác.

### 3.6 Cơ sở

| Ý | Cơ sở | Nguồn |
|---|---|---|
| Trộn các bài trong một phiên | Hiệu ứng học xen kẽ: toán, khái niệm, meta-analysis 59 nghiên cứu | [24] [25] [26] |
| Áp dụng cho y khoa | Thử nghiệm ngẫu nhiên: luyện ECG trộn chẩn đoán tốt hơn luyện theo khối | [27] |
| Chọn lần lượt, phạt mục giống mục đã chọn | Kỹ thuật đa dạng hoá MMR (Maximal Marginal Relevance) | [28] |
| Co về mức môn khi bài ít dữ liệu | Ước lượng co về | [7] |
| Ưu tiên thu dữ liệu ở bài chưa rõ | Cân bằng khai thác / khám phá bằng multi-armed bandit trong hệ thống dạy học | [29] |
| Giá trị λ, μ, trần, ngưỡng tin cậy | **Lựa chọn thiết kế**, hiệu chỉnh bằng dữ liệu | — |

---

## 4. Mô phỏng và cách đo sau triển khai

### 4.1 Mô phỏng 1 — Xoay vòng câu yếu

**Thiết lập:** 150 câu đã làm, 15 bài, 1 phiên/tuần, 7 suất Yếu/phiên, 16 tuần, trung bình 30 lần chạy. Câu chia 3 nhóm theo khả năng đúng thật: luôn yếu (5–20%, chiếm 15% số câu), yếu vừa (35–60%, 45%), khá (70–95%, 40%).


| Chỉ số                                 | Hiện tại | Chỉ sửa vấn đề 2 (W + xoay vòng) | Sửa cả hai |
| -------------------------------------- | -------- | -------------------------------- | ---------- |
| Câu yếu vừa được ôn ít nhất 1 lần      | 24%      | 48%                              | 48%        |
| Phần suất Yếu dành cho câu luôn yếu    | 65%      | 41%                              | 31%        |
| Số câu khác nhau được ôn               | 34       | 60                               | 64         |
| Mức tiến bộ trung bình của câu yếu vừa | +0,03    | +0,05                            | +0,05      |


**Giới hạn:** mô hình xác suất trong mô phỏng chưa có độ khó câu và gợi ý; giả định học viên tiến bộ bao nhiêu sau mỗi lần làm là đơn giản hoá. Kết quả chỉ cho thấy **xu hướng**; số liệu thật phải đo sau khi triển khai.

### 4.2 Mô phỏng 2 — Đo mức dồn bài / môn

**Thiết lập:** ngân hàng 189 câu, 4 môn, 20 bài với kích thước rất lệch nhau (40, 30, 20, 15, 12, 10, 8, 8, 6, 6, 5, 5, 4, 4, 3, 3, 3, 3, 2, 2 câu) để thử cả bài lớn lẫn bài nhỏ. Năng lực thật theo bài = hiệu ứng môn + hiệu ứng riêng của bài, nên câu cùng bài / cùng môn thật sự yếu cùng nhau. Độ khó câu ngẫu nhiên quanh 0,65. Mỗi câu có sẵn 1–3 lượt làm cũ, cách 7–90 ngày. Học viên học 1 phiên/tuần, 7 suất Yếu/phiên, 16 tuần, trung bình 40 lần chạy.

Ba phương án:

- **Hiện tại:** W, ngưỡng 0,5, lấy ngẫu nhiên trong top 2 × số suất.
- **Sửa 1 + 2:** mô hình xác suất (có co về bài → môn) + xoay vòng `T = 3`.
- **Sửa 1 + 2 + 3:** như trên + chọn lần lượt có giảm dần `λ = 0,5`, `μ = 0,8`, trần 4 câu/bài và 5 câu/môn.

| Chỉ số | Ý nghĩa | Hiện tại | Sửa 1 + 2 | Sửa 1 + 2 + 3 |
|---|---|---:|---:|---:|
| Phần lớn nhất của một bài trong phiên | Trung bình qua các phiên; càng thấp càng dàn trải | 40% | 44% | **18%** |
| Phiên bị một bài chiếm ≥ 50% suất | Tần suất phiên bị dồn nặng | 23% | 27% | **2%** |
| Số bài khác nhau trong phiên 7 câu | Tối đa 7 | 4,6 | 4,4 | **6,6** |
| Phần lớn nhất của một môn trong phiên | | 57% | 63% | **42%** |
| Số môn khác nhau trong phiên | Tối đa 4 | 2,9 | 2,6 | **3,4** |
| Bài có câu yếu được ôn ít nhất 1 lần sau 16 tuần | Độ phủ bài | 74% | 81% | **90%** |
| Câu yếu vừa được ôn ít nhất 1 lần | Kết quả của vấn đề 2 | 29% | **48%** | 44% |
| Phần suất dành cho câu luôn yếu | | 26% | 24% | 21% |
| Số câu khác nhau được ôn | | 39 | **59** | 56 |
| Phần suất dành cho bài nhỏ (≤ 5 câu) | Các bài này chứa 20% số câu yếu | 17% | 19% | 27% |

**Đọc kết quả:**

1. **Vấn đề 1 + 2 làm dồn bài nặng hơn một chút**, đúng như phân tích ở mục 3.1. Đó là lý do không nên triển khai vấn đề 1 mà thiếu vấn đề 3.
2. **Vấn đề 3 gần như xoá hiện tượng dồn**: phiên bị một bài chiếm nửa suất giảm từ 27% xuống 2%, và mỗi phiên 7 câu chạm trung bình 6,6 bài.
3. **Độ phủ bài tăng** từ 74% lên 90%.
4. **Cái giá nhỏ**: câu yếu vừa được ôn giảm từ 48% xuống 44%, số câu khác nhau giảm từ 59 xuống 56.
5. **Bài nhỏ được nhiều suất hơn tỷ lệ câu yếu của chúng** (27% so với 20%), vì mỗi bài nhỏ chỉ cần 1 câu là có mặt trong phiên. PM cần quyết định đây là ưu điểm (độ phủ) hay nhược điểm (mục 5).

Phần "luôn yếu" ở mô phỏng 2 (26%) thấp hơn mô phỏng 1 (65%) vì thế giới mô phỏng khác: mô phỏng 2 có ít câu cực khó hơn. Chỉ nên so các phương án **trong cùng một** mô phỏng.

**Giới hạn:** mô phỏng 2 chưa mô phỏng quên theo thời gian và chưa có gợi ý. Lợi ích học tập của việc dàn trải (hiệu ứng xen kẽ [24]–[27]) **không được mô phỏng**: mô phỏng chỉ đo cách phân suất, không đo học viên học tốt hơn bao nhiêu.

### 4.3 Đo sau triển khai

Ghi `p_now`, `gap`, `Ưu tiên` của từng câu vào log adaptive (bước `result`), rồi so với kết quả thật ở bước `graded`.


| Chỉ số                                          | Ý nghĩa                                                                   | Kỳ vọng                            |
| ----------------------------------------------- | ------------------------------------------------------------------------- | ---------------------------------- |
| **Brier score** = trung bình (p_now − kết quả)² | Mô hình đoán sai trung bình bao nhiêu. 0 là hoàn hảo; 0,25 là đoán 50/50. | Thấp hơn rõ so với dùng W cũ       |
| **Độ hiệu chuẩn**                               | Nhóm câu dự đoán khoảng 30% đúng thì thực tế có khoảng 30% đúng không     | Gần khớp                           |
| Tỷ lệ câu Yếu được ôn trong 4 tuần              | Còn câu bị bỏ quên không                                                  | Tăng                               |
| Phần suất dành cho câu luôn sai                 |                                                                           | Giảm                               |
| Tỷ lệ đúng mỗi phiên                            |                                                                           | Phần lớn phiên trong khoảng 60–85% |
| Học viên ≤ 1 phiên/tuần quay lại sau 4 tuần     |                                                                           | Tăng                               |
| Phần lớn nhất của một bài trong phiên           | Mức dồn bài                                                               | Giảm, khoảng dưới 25%              |
| Bài có câu yếu được ôn trong 4 tuần             | Độ phủ bài                                                                | Tăng                               |


Sau 2–4 tuần có dữ liệu, chỉnh `m`, `H_câu`, `H_bài`, `k`, `y_gợi_ý` sao cho Brier score thấp nhất. Brier score là thước đo chuẩn cho dự báo xác suất [22]; cách dùng các thước đo này cho mô hình học viên được Pelánek tổng hợp [23].

---



## 5. Câu hỏi cần PM quyết định

1. **Điểm cho lượt đúng có gợi ý** (`y_gợi_ý` = 0,5). Có hợp lý về mặt sư phạm không? Có muốn tách điểm cho Key info và Attending tip không?
2. **Nhãn độ khó → độ dễ ban đầu** (0,90 / 0,80 / 0,65 / 0,45 / 0,30). Có khớp với cách đội nội dung đang gắn nhãn không?
3. **Câu dễ bị sai.** Mô hình xác suất xếp câu khó bị sai yếu hơn câu dễ bị sai (mục 1.6). Có muốn thêm ưu tiên riêng cho câu dễ bị sai, coi là lỗ hổng kiến thức nền, không?
4. **Ngưỡng câu Yếu** `θ_yếu` = 0,6. Nhóm Yếu nên rộng hay hẹp?
5. **Tốc độ xoay vòng** `T` = 3: câu yếu vừa chờ khoảng 4–5 phiên thì vượt câu rất yếu vừa làm phiên trước. Có chấp nhận không?
6. **Mức dàn trải** `λ` = 0,5 và `μ` = 0,8 (mục 3.4–3.5). Có chấp nhận đổi một ít độ bám ưu tiên câu (48% → 44% câu yếu vừa được ôn) để lấy độ dàn trải bài / môn không?
7. **Bài nhỏ được nhiều suất hơn tỷ lệ câu yếu** (27% so với 20%). Ưu tiên độ phủ bài, hay muốn chia suất sát theo số câu yếu?
8. **Hiển thị điểm yếu bài cho học viên** (mục 3.3): có hiển thị không? Có dùng nhãn độ tin cậy "chưa đủ dữ liệu / sơ bộ / tin cậy" và ẩn nhãn "bài cần chú ý" khi `N_bài` < 3 không?
9. **Thứ tự triển khai:** vấn đề 2 và 3 trước (tạm dùng W hiện tại), vấn đề 1 sau?

---



## 6. Cơ sở khoa học — mức độ bằng chứng



### 6.1 Ba mức


| Mức                                  | Nghĩa                                                                                                                                                         |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **A — Bằng chứng trực tiếp**         | Có nghiên cứu thực nghiệm hoặc đánh giá trên dữ liệu người học thật, cho đúng kỹ thuật này hoặc kỹ thuật rất gần                                              |
| **B — Kỹ thuật chuẩn, áp dụng sang** | Phương pháp đã được chứng minh trong thống kê, khảo thí hoặc khoa học máy tính; áp dụng vào bài toán này là hợp lý nhưng chưa có nghiên cứu cho đúng bối cảnh |
| **C — Lựa chọn thiết kế**            | Phù hợp với các nguyên lý trên nhưng chưa có bằng chứng trực tiếp; **phải hiệu chỉnh bằng dữ liệu**                                                           |


**Toàn bộ giá trị số của tham số** đều ở **mức C**. Nghiên cứu ủng hộ **dạng công thức**, không quy định con số cho hệ thống này.

### 6.2 Từng thành phần


| Thành phần                                         | Mục        | Cơ sở                                                                   | Mức                         | Nguồn               |
| -------------------------------------------------- | ---------- | ----------------------------------------------------------------------- | --------------------------- | ------------------- |
| Kết hợp độ khó + năng lực + lịch sử + thời gian    | 1.2        | Mô hình DASH, thử nghiệm một học kỳ                                     | A                           | [1]                 |
| Ước lượng ban đầu rồi cập nhật                     | 1.2, B3–B4 | Thiết kế mô-đun được đánh giá trên hệ thống lớn                         | A                           | [2]                 |
| Điểm từng phần khi dùng gợi ý                      | B0         | Cải thiện độ chính xác của mô hình học viên                             | A (hướng) / C (giá trị 0,5) | [3]                 |
| Độ khó = tỷ lệ người làm đúng lần đầu              | B1         | Lý thuyết khảo thí cổ điển                                              | B                           | [4]                 |
| Chấm năng lực theo (kết quả − kỳ vọng theo độ khó) | B2         | Mô hình Rasch; Elo trong giáo dục                                       | B                           | [5] [6]             |
| Co về mức chung; bài → môn → tổng                  | B1, B2     | Ước lượng co về                                                         | B                           | [7]                 |
| Mỗi câu tính 1 lần; chỉ lượt đầu cho độ khó        | B1, B2     | Hệ thống tự chọn câu làm lệch dữ liệu; cách sửa là thiết kế riêng       | B (vấn đề) / C (cách sửa)   | [8]                 |
| Giảm trọng số lượt cũ theo hàm mũ                  | B2, B4     | Cách tóm tắt lịch sử dự đoán chính xác nhất trong các cách được so sánh | A                           | [9]                 |
| Cập nhật Beta–Binomial với lượt ảo                 | B4         | Cập nhật Bayes chuẩn                                                    | B                           | [10]                |
| Đường cong quên dạng lũy thừa                      | B5         | Khớp tốt hơn hàm mũ trên nhiều bộ dữ liệu                               | A                           | [11] [12]           |
| Định nghĩa S: còn nhớ 90% sau S ngày               | B5         | Tương đồng FSRS; **đã có trong hệ thống**                               | B                           | [13]                |
| Mức đoán mò g                                      | B5         | Mô hình IRT 3 tham số                                                   | B                           | [4]                 |
| Ngưỡng Yếu dưới vùng đúng mục tiêu                 | KQ         | Khó khăn đáng mong muốn; quy tắc 85% (gián tiếp)                        | B                           | [14] [15]           |
| Ôn lại mọi câu yếu theo thời gian, kể cả học thưa  | 2.1        | Giãn cách, kiểm tra truy hồi; thử nghiệm trên sinh viên y học hằng tuần | A                           | [16] [17] [18] [19] |
| Không hỏi câu luôn sai ở mọi phiên                 | 2.2        | Vùng học gần; tránh "học vô ích"                                        | B                           | [20]                |
| Tăng ưu tiên theo thời gian chờ                    | 2.2        | Aging trong lập lịch                                                    | B                           | [21]                |
| Đếm gap theo phiên                                 | 2.2        | Không có nghiên cứu trực tiếp                                           | C                           | —                   |
| Trộn bài / môn trong một phiên                     | 3.1, 3.2   | Hiệu ứng học xen kẽ; thử nghiệm ECG trên sinh viên y                    | A                           | [24] [25] [26] [27] |
| Chọn lần lượt, giảm ưu tiên câu cùng bài / môn     | 3.2        | Đa dạng hoá MMR trong truy hồi thông tin                                | B                           | [28]                |
| Điểm yếu bài = trung bình p; co về môn khi ít dữ liệu | 3.3     | Ước lượng co về                                                         | B                           | [7]                 |
| Ưu tiên mở câu mới ở bài ít dữ liệu                | 3.3        | Multi-armed bandit trong hệ thống dạy học                               | B                           | [29]                |
| Giá trị λ, μ, trần, ngưỡng tin cậy                 | 3.4        | Không có nghiên cứu trực tiếp                                           | C                           | —                   |
| Brier score, độ hiệu chuẩn                         | 4.3        | Thước đo chuẩn                                                          | B                           | [22] [23]           |




### 6.3 Giới hạn của các bằng chứng

1. **Khác lĩnh vực.** Phần lớn nghiên cứu về mô hình học viên làm với từ vựng ngoại ngữ [1], địa lý [2][6] và toán [3]. Bằng chứng trong y khoa chủ yếu về giãn cách và kiểm tra truy hồi [18][19]. Chưa có nghiên cứu đánh giá đúng mô hình này trên câu trắc nghiệm y khoa.
2. **Kết quả trung bình.** Mức cải thiện trong các nghiên cứu là trung bình trên nhóm, trong điều kiện thử nghiệm. Hiệu quả thật phụ thuộc chất lượng câu hỏi và mức độ học viên tham gia.
3. **Quy tắc 85% [15]** được chứng minh cho thuật toán học máy và mô hình mô phỏng, không phải thử nghiệm trên người học. Chỉ mang tính định hướng.
4. **Trắc nghiệm truy hồi yếu hơn tự luận ngắn.** Câu hỏi buộc tự nhớ lại giúp nhớ tốt hơn câu chỉ cần nhận ra đáp án; phản hồi sau khi trả lời là then chốt [18]. Q-Bank dùng trắc nghiệm có giải thích, nên lợi ích thực tế có thể thấp hơn trong các nghiên cứu.
5. **Hiệu ứng học xen kẽ không đồng đều.** Meta-analysis [26] cho thấy hiệu ứng mạnh với hình ảnh / nhóm khái niệm dễ nhầm, yếu hoặc không có với từ vựng, và không rõ với văn bản giải thích. Câu hỏi chẩn đoán y khoa gần với dạng "phân biệt nhóm dễ nhầm" (như nghiên cứu ECG [27]), nhưng chưa có nghiên cứu trên ngân hàng câu trắc nghiệm y khoa. Các nghiên cứu cũng trộn **dạng bài** do người thiết kế chọn, không phải trộn **câu yếu** do thuật toán chọn.
6. **Mô phỏng ở mục 4** là mô phỏng nội bộ, không phải bằng chứng khoa học.
7. Vì vậy cần **đo trên dữ liệu thật** (mục 4.3), và nên triển khai dạng thử nghiệm A/B nếu có thể.

---



## 7. Tài liệu tham khảo

[1] Lindsey, R. V., Shroyer, J. D., Pashler, H., & Mozer, M. C. (2014). Improving students' long-term knowledge retention through personalized review. *Psychological Science, 25*(3), 639–647. [https://doi.org/10.1177/0956797613504302](https://doi.org/10.1177/0956797613504302)

[2] Pelánek, R., Papoušek, J., Řihák, J., Stanislav, V., & Nižnan, J. (2017). Elo-based learner modeling for the adaptive practice of facts. *User Modeling and User-Adapted Interaction, 27*(1), 89–118. [https://doi.org/10.1007/s11257-016-9185-7](https://doi.org/10.1007/s11257-016-9185-7)

[3] Wang, Y., & Heffernan, N. T. (2013). Extending knowledge tracing to allow partial credit: Using continuous versus binary nodes. In *Artificial Intelligence in Education (AIED 2013)*, LNCS 7926, 181–188. Springer. [https://doi.org/10.1007/978-3-642-39112-5_19](https://doi.org/10.1007/978-3-642-39112-5_19)

[4] Lord, F. M., & Novick, M. R. (1968). *Statistical Theories of Mental Test Scores* (gồm các chương 17–20 của A. Birnbaum về mô hình logistic có tham số đoán mò). Reading, MA: Addison-Wesley.

[5] Rasch, G. (1960). *Probabilistic Models for Some Intelligence and Attainment Tests*. Copenhagen: Danish Institute for Educational Research.

[6] Pelánek, R. (2016). Applications of the Elo rating system in adaptive educational systems. *Computers & Education, 98*, 169–179. [https://doi.org/10.1016/j.compedu.2016.03.017](https://doi.org/10.1016/j.compedu.2016.03.017)

[7] Efron, B., & Morris, C. (1975). Data analysis using Stein's estimator and its generalizations. *Journal of the American Statistical Association, 70*(350), 311–319. [https://doi.org/10.1080/01621459.1975.10479864](https://doi.org/10.1080/01621459.1975.10479864)

[8] Pelánek, R., Řihák, J., & Papoušek, J. (2016). Impact of data collection on interpretation and evaluation of student models. In *Proceedings of the 6th International Conference on Learning Analytics & Knowledge (LAK '16)*, 40–47. ACM. [https://doi.org/10.1145/2883851.2883868](https://doi.org/10.1145/2883851.2883868)

[9] Galyardt, A., & Goldin, I. (2015). Move your lamp post: Recent data reflects learner knowledge better than older data. *Journal of Educational Data Mining, 7*(2), 83–108. [https://doi.org/10.5281/zenodo.3554672](https://doi.org/10.5281/zenodo.3554672)

[10] Gelman, A., Carlin, J. B., Stern, H. S., Dunson, D. B., Vehtari, A., & Rubin, D. B. (2013). *Bayesian Data Analysis* (3rd ed.). Boca Raton, FL: CRC Press.

[11] Ebbinghaus, H. (1885/1913). *Memory: A Contribution to Experimental Psychology* (H. A. Ruger & C. E. Bussenius, Trans.). New York: Teachers College, Columbia University. (Bản gốc: *Über das Gedächtnis*, 1885.)

[12] Wixted, J. T., & Ebbesen, E. B. (1991). On the form of forgetting. *Psychological Science, 2*(6), 409–415. [https://doi.org/10.1111/j.1467-9280.1991.tb00175.x](https://doi.org/10.1111/j.1467-9280.1991.tb00175.x)

[13] Ye, J., Su, J., & Cao, Y. (2022). A stochastic shortest path algorithm for optimizing spaced repetition scheduling. In *Proceedings of the 28th ACM SIGKDD Conference on Knowledge Discovery and Data Mining*, 4381–4390. [https://doi.org/10.1145/3534678.3539081](https://doi.org/10.1145/3534678.3539081)

[14] Bjork, R. A. (1994). Memory and metamemory considerations in the training of human beings. In J. Metcalfe & A. Shimamura (Eds.), *Metacognition: Knowing about Knowing* (pp. 185–205). Cambridge, MA: MIT Press.

[15] Wilson, R. C., Shenhav, A., Straccia, M., & Cohen, J. D. (2019). The Eighty Five Percent Rule for optimal learning. *Nature Communications, 10*, 4646. [https://doi.org/10.1038/s41467-019-12552-4](https://doi.org/10.1038/s41467-019-12552-4)

[16] Cepeda, N. J., Pashler, H., Vul, E., Wixted, J. T., & Rohrer, D. (2006). Distributed practice in verbal recall tasks: A review and quantitative synthesis. *Psychological Bulletin, 132*(3), 354–380. [https://doi.org/10.1037/0033-2909.132.3.354](https://doi.org/10.1037/0033-2909.132.3.354)

[17] Roediger, H. L., & Karpicke, J. D. (2006). Test-enhanced learning: Taking memory tests improves long-term retention. *Psychological Science, 17*(3), 249–255. [https://doi.org/10.1111/j.1467-9280.2006.01693.x](https://doi.org/10.1111/j.1467-9280.2006.01693.x)

[18] Larsen, D. P., Butler, A. C., & Roediger, H. L. (2008). Test-enhanced learning in medical education. *Medical Education, 42*(10), 959–966. [https://doi.org/10.1111/j.1365-2923.2008.03124.x](https://doi.org/10.1111/j.1365-2923.2008.03124.x)

[19] Kerfoot, B. P., DeWolf, W. C., Masser, B. A., Church, P. A., & Federman, D. D. (2007). Spaced education improves the retention of clinical knowledge by medical students: A randomised controlled trial. *Medical Education, 41*(1), 23–31. [https://doi.org/10.1111/j.1365-2929.2006.02644.x](https://doi.org/10.1111/j.1365-2929.2006.02644.x)

[20] Metcalfe, J., & Kornell, N. (2005). A Region of Proximal Learning model of study time allocation. *Journal of Memory and Language, 52*(4), 463–477. [https://doi.org/10.1016/j.jml.2004.12.001](https://doi.org/10.1016/j.jml.2004.12.001)

[21] Silberschatz, A., Galvin, P. B., & Gagne, G. (2018). *Operating System Concepts* (10th ed.), chương CPU Scheduling — mục starvation và aging. Hoboken, NJ: Wiley.

[22] Brier, G. W. (1950). Verification of forecasts expressed in terms of probability. *Monthly Weather Review, 78*(1), 1–3. <https://doi.org/10.1175/1520-0493(1950)078%3C0001:VOFEIT%3E2.0.CO;2>

[23] Pelánek, R. (2015). Metrics for evaluation of student models. *Journal of Educational Data Mining, 7*(2), 1–19. [https://doi.org/10.5281/zenodo.3554665](https://doi.org/10.5281/zenodo.3554665)

[24] Rohrer, D., & Taylor, K. (2007). The shuffling of mathematics problems improves learning. *Instructional Science, 35*(6), 481–498. [https://doi.org/10.1007/s11251-007-9015-8](https://doi.org/10.1007/s11251-007-9015-8)

[25] Kornell, N., & Bjork, R. A. (2008). Learning concepts and categories: Is spacing the "enemy of induction"? *Psychological Science, 19*(6), 585–592. [https://doi.org/10.1111/j.1467-9280.2008.02127.x](https://doi.org/10.1111/j.1467-9280.2008.02127.x)

[26] Brunmair, M., & Richter, T. (2019). Similarity matters: A meta-analysis of interleaved learning and its moderators. *Psychological Bulletin, 145*(11), 1029–1052. [https://doi.org/10.1037/bul0000209](https://doi.org/10.1037/bul0000209)

[27] Hatala, R. M., Brooks, L. R., & Norman, G. R. (2003). Practice makes perfect: The critical role of mixed practice in the acquisition of ECG interpretation skills. *Advances in Health Sciences Education, 8*(1), 17–26. [https://doi.org/10.1023/A:1022687404380](https://doi.org/10.1023/A:1022687404380)

[28] Carbonell, J., & Goldstein, J. (1998). The use of MMR, diversity-based reranking for reordering documents and producing summaries. In *Proceedings of the 21st Annual International ACM SIGIR Conference on Research and Development in Information Retrieval (SIGIR '98)*, 335–336. [https://doi.org/10.1145/290941.291025](https://doi.org/10.1145/290941.291025)

[29] Clement, B., Roy, D., Oudeyer, P.-Y., & Lopes, M. (2015). Multi-armed bandits for intelligent tutoring systems. *Journal of Educational Data Mining, 7*(2), 20–48. [https://arxiv.org/abs/1310.3174](https://arxiv.org/abs/1310.3174)