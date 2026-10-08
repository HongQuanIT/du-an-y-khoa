# Đề xuất: Điểm yếu theo xác suất & xoay vòng câu yếu cho học viên học thưa

---

## 0. Tóm tắt

Có hai vấn đề **độc lập với nhau**, giải quyết bằng hai thay đổi riêng:


|       | Vấn đề                                                                                                            | Giải pháp                                                                      | Dữ liệu mới                                 |
| ----- | ----------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ | ------------------------------------------- |
| **1** | Điểm yếu tính quá đơn giản, nhiều câu trùng giá trị, bỏ qua thời gian, gợi ý, độ khó câu và năng lực theo bài/môn | Điểm yếu = 1 − **xác suất trả lời đúng nếu hỏi ngay bây giờ**, tính qua 6 bước | 2 cột thống kê độ khó trên bảng `questions` |
| **2** | Học viên học thưa (ví dụ 1 buổi/tuần): câu yếu vừa không bao giờ được gọi vì không đủ suất                        | Nhân điểm yếu với một **hệ số xoay vòng theo số phiên** chưa gặp               | Không                                       |


Thứ tự triển khai đề xuất: **vấn đề 2 trước** (nhỏ, không cần dữ liệu mới, tác động lớn nhất), **vấn đề 1 sau**.

Mô phỏng 16 tuần, học viên học 1 phiên/tuần (chi tiết ở mục 3):


| Chỉ số                              | Hiện tại | Chỉ sửa vấn đề 2 | Sửa cả hai |
| ----------------------------------- | -------- | ---------------- | ---------- |
| Câu yếu vừa được ôn ít nhất 1 lần   | 24%      | 48%              | 48%        |
| Phần suất Yếu dành cho câu luôn yếu | 65%      | 41%              | 31%        |
| Số câu khác nhau được ôn            | 34       | 60               | 64         |


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
> Nếu PM muốn **ưu tiên câu dễ bị sai** (lỗ hổng kiến thức cơ bản), có thể thêm hệ số ưu tiên riêng. Xem câu hỏi ở mục 4.



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



## 3. Mô phỏng và cách đo sau triển khai



### 3.1 Mô phỏng

**Thiết lập:** 150 câu đã làm, 15 bài, 1 phiên/tuần, 7 suất Yếu/phiên, 16 tuần, trung bình 30 lần chạy. Câu chia 3 nhóm theo khả năng đúng thật: luôn yếu (5–20%, chiếm 15% số câu), yếu vừa (35–60%, 45%), khá (70–95%, 40%).


| Chỉ số                                 | Hiện tại | Chỉ sửa vấn đề 2 (W + xoay vòng) | Sửa cả hai |
| -------------------------------------- | -------- | -------------------------------- | ---------- |
| Câu yếu vừa được ôn ít nhất 1 lần      | 24%      | 48%                              | 48%        |
| Phần suất Yếu dành cho câu luôn yếu    | 65%      | 41%                              | 31%        |
| Số câu khác nhau được ôn               | 34       | 60                               | 64         |
| Mức tiến bộ trung bình của câu yếu vừa | +0,03    | +0,05                            | +0,05      |


**Giới hạn:** mô hình xác suất trong mô phỏng chưa có độ khó câu và gợi ý; giả định học viên tiến bộ bao nhiêu sau mỗi lần làm là đơn giản hoá. Kết quả chỉ cho thấy **xu hướng**; số liệu thật phải đo sau khi triển khai.

### 3.2 Đo sau triển khai

Ghi `p_now`, `gap`, `Ưu tiên` của từng câu vào log adaptive (bước `result`), rồi so với kết quả thật ở bước `graded`.


| Chỉ số                                          | Ý nghĩa                                                                   | Kỳ vọng                            |
| ----------------------------------------------- | ------------------------------------------------------------------------- | ---------------------------------- |
| **Brier score** = trung bình (p_now − kết quả)² | Mô hình đoán sai trung bình bao nhiêu. 0 là hoàn hảo; 0,25 là đoán 50/50. | Thấp hơn rõ so với dùng W cũ       |
| **Độ hiệu chuẩn**                               | Nhóm câu dự đoán khoảng 30% đúng thì thực tế có khoảng 30% đúng không     | Gần khớp                           |
| Tỷ lệ câu Yếu được ôn trong 4 tuần              | Còn câu bị bỏ quên không                                                  | Tăng                               |
| Phần suất dành cho câu luôn sai                 |                                                                           | Giảm                               |
| Tỷ lệ đúng mỗi phiên                            |                                                                           | Phần lớn phiên trong khoảng 60–85% |
| Học viên ≤ 1 phiên/tuần quay lại sau 4 tuần     |                                                                           | Tăng                               |


Sau 2–4 tuần có dữ liệu, chỉnh `m`, `H_câu`, `H_bài`, `k`, `y_gợi_ý` sao cho Brier score thấp nhất. Brier score là thước đo chuẩn cho dự báo xác suất [22]; cách dùng các thước đo này cho mô hình học viên được Pelánek tổng hợp [23].

---



## 4. Câu hỏi cần PM quyết định

1. **Điểm cho lượt đúng có gợi ý** (`y_gợi_ý` = 0,5). Có hợp lý về mặt sư phạm không? Có muốn tách điểm cho Key info và Attending tip không?
2. **Nhãn độ khó → độ dễ ban đầu** (0,90 / 0,80 / 0,65 / 0,45 / 0,30). Có khớp với cách đội nội dung đang gắn nhãn không?
3. **Câu dễ bị sai.** Mô hình xác suất xếp câu khó bị sai yếu hơn câu dễ bị sai (mục 1.6). Có muốn thêm ưu tiên riêng cho câu dễ bị sai, coi là lỗ hổng kiến thức nền, không?
4. **Ngưỡng câu Yếu** `θ_yếu` = 0,6. Nhóm Yếu nên rộng hay hẹp?
5. **Tốc độ xoay vòng** `T` = 3: câu yếu vừa chờ khoảng 4–5 phiên thì vượt câu rất yếu vừa làm phiên trước. Có chấp nhận không?
6. **Thứ tự triển khai:** vấn đề 2 trước (tạm dùng W hiện tại), vấn đề 1 sau?

---



## 5. Cơ sở khoa học — mức độ bằng chứng



### 5.1 Ba mức


| Mức                                  | Nghĩa                                                                                                                                                         |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **A — Bằng chứng trực tiếp**         | Có nghiên cứu thực nghiệm hoặc đánh giá trên dữ liệu người học thật, cho đúng kỹ thuật này hoặc kỹ thuật rất gần                                              |
| **B — Kỹ thuật chuẩn, áp dụng sang** | Phương pháp đã được chứng minh trong thống kê, khảo thí hoặc khoa học máy tính; áp dụng vào bài toán này là hợp lý nhưng chưa có nghiên cứu cho đúng bối cảnh |
| **C — Lựa chọn thiết kế**            | Phù hợp với các nguyên lý trên nhưng chưa có bằng chứng trực tiếp; **phải hiệu chỉnh bằng dữ liệu**                                                           |


**Toàn bộ giá trị số của tham số** đều ở **mức C**. Nghiên cứu ủng hộ **dạng công thức**, không quy định con số cho hệ thống này.

### 5.2 Từng thành phần


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
| Brier score, độ hiệu chuẩn                         | 3.2        | Thước đo chuẩn                                                          | B                           | [22] [23]           |




### 5.3 Giới hạn của các bằng chứng

1. **Khác lĩnh vực.** Phần lớn nghiên cứu về mô hình học viên làm với từ vựng ngoại ngữ [1], địa lý [2][6] và toán [3]. Bằng chứng trong y khoa chủ yếu về giãn cách và kiểm tra truy hồi [18][19]. Chưa có nghiên cứu đánh giá đúng mô hình này trên câu trắc nghiệm y khoa.
2. **Kết quả trung bình.** Mức cải thiện trong các nghiên cứu là trung bình trên nhóm, trong điều kiện thử nghiệm. Hiệu quả thật phụ thuộc chất lượng câu hỏi và mức độ học viên tham gia.
3. **Quy tắc 85% [15]** được chứng minh cho thuật toán học máy và mô hình mô phỏng, không phải thử nghiệm trên người học. Chỉ mang tính định hướng.
4. **Trắc nghiệm truy hồi yếu hơn tự luận ngắn.** Câu hỏi buộc tự nhớ lại giúp nhớ tốt hơn câu chỉ cần nhận ra đáp án; phản hồi sau khi trả lời là then chốt [18]. Q-Bank dùng trắc nghiệm có giải thích, nên lợi ích thực tế có thể thấp hơn trong các nghiên cứu.
5. **Mô phỏng ở mục 3** là mô phỏng nội bộ, không phải bằng chứng khoa học.
6. Vì vậy cần **đo trên dữ liệu thật** (mục 3.2), và nên triển khai dạng thử nghiệm A/B nếu có thể.

---



## 6. Tài liệu tham khảo

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

[22] Brier, G. W. (1950). Verification of forecasts expressed in terms of probability. *Monthly Weather Review, 78*(1), 1–3. [https://doi.org/10.1175/1520-0493(1950)078](https://doi.org/10.1175/1520-0493(1950)078)<0001:VOFEIT>2.0.CO;2

[23] Pelánek, R. (2015). Metrics for evaluation of student models. *Journal of Educational Data Mining, 7*(2), 1–19. [https://doi.org/10.5281/zenodo.3554665](https://doi.org/10.5281/zenodo.3554665)