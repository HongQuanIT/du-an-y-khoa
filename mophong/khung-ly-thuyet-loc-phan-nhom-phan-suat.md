# Khung lý thuyết đề xuất: Lọc → Phân nhóm → Phân suất

| | |
|--|--|
| **Mô hình** | Phiên ôn tập thích ứng (đề xuất Claude / gói `mophong`) |
| **Nguồn** | [Claude share](https://claude.ai/share/dcee4dac-28f4-4b42-819d-0dce16bb88fd) · artifact `mophong/` (TS + Excel + test) |
| **Đối chiếu** | Production hiện tại: `docs/adaptive-session-algorithm.md` |
| **Mục đích file** | Tóm tắt dễ hiểu + đánh giá khung Lọc / Phân nhóm / Phân suất |
| **Kế hoạch triển khai** | [`ke-hoach-trien-khai.md`](./ke-hoach-trien-khai.md) |

---

## 1. Ý tưởng một câu

Hệ thống **không chấm điểm mọi câu rồi bốc ngẫu nhiên**, mà làm ba việc tuần tự:

1. **Lọc** — bỏ câu không được phép ra hôm nay  
2. **Phân nhóm** — xếp câu còn lại vào vài “giỏ” có ý nghĩa học tập  
3. **Phân suất** — theo loại phiên, lấy bao nhiêu câu từ mỗi giỏ  

Sau đó mới chọn câu cụ thể trong từng suất (xếp hạng + một chút ngẫu nhiên), rồi bổ sung câu mới / lấp đầy nếu thiếu.

```text
Bể câu theo đề thi
        │
        ▼
   ┌─────────┐
   │  LỌC    │  published? report? đang ở phiên khác? nghỉ 20h?
   └────┬────┘
        ▼
   ┌─────────────┐
   │ PHÂN NHÓM   │  Yếu · Sắp quên · Chưa làm · (còn lại để lấp)
   └──────┬──────┘
          ▼
   ┌─────────────┐
   │ PHÂN SUẤT   │  mode + tồn đọng due → bao nhiêu chỗ cho mỗi nhóm
   └──────┬──────┘
          ▼
   Chọn câu trong suất → đảo đáp án → phiên N câu + “vì sao”
```

Đây là khác biệt lớn so với production: production **gộp** “yếu” và “sắp quên” thành một điểm số mềm rồi random; mô hình Claude **tách** chúng thành nhóm và suất rõ ràng.

---

## 2. Tóm tắt mô hình (dễ hiểu)

### 2.1 Mỗi câu có “hồ sơ học” riêng

Với mỗi cặp *học viên × câu* đã làm hợp lệ, hệ thống nhớ:

| Khái niệm | Ý nghĩa đời thường | Cách tính |
|---|---|---|
| **Độ yếu (W)** | Gần đây hay sai không? | `(số sai + 1) / (số lần + 2)` trên **5 lần gần nhất** |
| **Độ bền (S)** | “Nhớ được bao lâu” trước khi cần ôn lại | Thang bậc cố định: 1 → 3 → 7 → 14 → 30 → 60 **ngày** |
| **Còn nhớ (R)** | Hôm nay còn nhớ khoảng bao nhiêu % | `R = 0,9 ^ (t / S)` (`t` = số ngày từ lần làm hợp lệ cuối) |
| **Đến hạn** | Đã tới lúc nên ôn lại | khi `t ≥ S` (lúc đó `R ≤ 90%`) |

**Quy tắc đổi bậc S (sau mỗi lượt hợp lệ):**

- Lần đầu đúng → bậc 2 (3 ngày); lần đầu sai → bậc 1 (1 ngày)  
- Sai → **về bậc 1** (ôn lại sớm)  
- Đúng **đúng hạn** (`t ≥ S`) → lên 1 bậc  
- Đúng **sớm** (`t < S`) → giữ bậc (ôn sớm không làm nhớ bền thêm)  

Lượt quá nhanh (&lt; 5 giây) hoặc chưa nộp: **không** đổi W/S, chỉ ghi nhận đã đưa câu ra (cho thời gian nghỉ).

### 2.2 Ba loại phiên (mode)

| Mode UI | Ý học viên muốn | Suất ôn (sau khi đã trừ chỗ câu mới) |
|---|---|---|
| **Điểm yếu** | Vá lỗ hổng | Gần như toàn bộ suất ôn → nhóm Yếu |
| **Củng cố** | Chống quên | Gần như toàn bộ suất ôn → nhóm Sắp quên |
| **Cân bằng** | Ngày thường | Khoảng một nửa Yếu + một nửa Sắp quên |

Thiếu ở nhóm chính → **bù** từ nhóm kia; vẫn thiếu → lấp bằng câu eligible còn lại (bucket `lap_day`).

---

## 3. Ba trụ: Lọc · Phân nhóm · Phân suất

### 3.1 Lọc — “Câu nào được phép vào phiên hôm nay?”

Mục tiêu: tránh câu lỗi, câu trùng phiên, câu vừa mới đưa ra.

| Điều kiện loại | Lý do |
|---|---|
| Chưa phát hành (`!isPublished`) | Học viên không được thấy |
| Đang có báo lỗi mở (`hasOpenReport`) | Nội dung có thể sai |
| Đang nằm trong phiên khác chưa xong | Tránh trùng / xung đột |
| State thuộc **phiên bản nội dung cũ** | Câu đã sửa đáp án/dữ kiện → coi như chưa học phiên bản mới |
| Vừa được đưa vào phiên &lt; **20 giờ** | Thời gian nghỉ (cooldown cứng) |
| Đang trong **tạm tránh thrash** (xem §3.1.1) | Sai liên tiếp quá nhiều → không spam cùng câu |

Sau lọc còn hai tập:

- **Đã học hợp lệ + hết nghỉ + hết tạm tránh thrash** → `eligible` (ứng viên ôn)  
- **Chưa từng làm đúng phiên bản hiện tại** → `unseen` (ứng viên câu mới)

#### 3.1.1 Tạm tránh khi sai liên tiếp (thrash cooldown) — đã chốt

Mục tiêu: câu bị sai liên tục không bị đẩy vào mọi phiên kế tiếp. Có tầng vừa và tầng nặng.

Chỉ đếm **lượt làm hợp lệ** (đã nộp, đủ thời gian tối thiểu). Chuỗi sai bị **cắt** ngay khi có một lần đúng hợp lệ.

| Tầng | Điều kiện | Tạm tránh câu đó | Cách hiểu |
|---|---|---|---|
| **Vừa** | **≥ 3** lần sai liên tiếp | **72 giờ** và đủ **2 phiên** | Phanh ngắn |
| **Nặng** | **≥ 5** lần sai liên tiếp | **7 ngày** | Câu “kẹt” |

```text
streak_wrong = số lần sai hợp lệ liên tiếp gần nhất (đúng → reset 0)

nếu streak_wrong ≥ 5:
    blocked_until = lần sai kích hoạt + 7 ngày
nếu không, và streak_wrong ≥ 3:
    blocked_until = lần sai + 72 giờ
    mở khóa khi đã hết 72 giờ VÀ đã qua ≥ 2 phiên kể từ lần chấm kích hoạt
nếu streak_wrong < 3:
    không áp thrash cooldown
```

**Ghi chú vận hành**

- Tầng nặng ghi đè tầng vừa.  
- Trong thời gian tạm tránh **đúng `question_id` đó**: vẫn được chọn câu **cùng chủ đề / bài học**.  
- Làm **đúng một lần hợp lệ** → `streak_wrong = 0`, hết tạm tránh.  
- Lượt không hợp lệ / chưa nộp **không** cộng vào chuỗi sai.

**Tham số (DEFAULT / production)**

```text
thrashMildStreak     = 3
thrashSevereStreak   = 5
thrashMildSessions   = 2
thrashMildHours      = 72
thrashSevereDays     = 7
```

**Đánh giá trụ Lọc**

| Ưu | Nhược |
|---|---|
| Rõ, dễ test, dễ giải thích “hôm nay hết câu” | Cooldown **cứng 20h** dễ shortfall nếu luyện nhiều phiên/ngày |
| `contentVersion` đúng pedagogic (sửa câu ≠ giữ S cũ) | Cần wire vào publish path production |
| Thrash cooldown chống spam câu sai liên tiếp | Cần lưu `streak_wrong` / `thrash_blocked_until` (hoặc derive từ attempts) |
| Tách “được phép” khỏi “cần ôn” | Production hiện dùng cooldown **mềm** — mượt hơn nhưng kém chắc |

→ **Nên giữ tinh thần Lọc; thêm thrash cooldown đã chốt ở §3.1.1; cân nhắc nới cooldown serve thường (6–12h) hoặc hybrid cứng ngắn + mềm.**

---

### 3.2 Phân nhóm — “Câu thuộc giỏ học nào?”

Chỉ phân nhóm trên `eligible` (đã lọc). Một câu **có thể thuộc cả hai** nhóm Yếu và Sắp quên; khi chọn chỉ tính **một lần** (nhóm nào lấy trước theo mode).

| Nhóm | Điều kiện vào giỏ | Xếp hạng trong giỏ | Ý nghĩa |
|---|---|---|---|
| **Yếu** | `W ≥ 0,5` | W cao trước; hòa thì R thấp trước | Hay sai gần đây → cần sửa |
| **Sắp quên** | `t ≥ S` (đến hạn) | R thấp trước; hòa thì W cao trước | Đúng lúc ôn lại theo độ bền |
| **Mới** | Chưa có state hợp lệ | Xem §3.2.1 — ưu tiên **bài học dang dở**, rồi mới ma trận | Phủ bài đang học trước khi mở bài mới |
| **Lấp đầy** | Không thuộc suất trên nhưng vẫn eligible | R thấp trước | Chỉ khi các suất chính thiếu câu |

Công thức hỗ trợ (đơn giản hóa):

```text
W = (sai + 1) / (lần + 2)     trên tối đa 5 lần gần nhất
R = 0,9 ^ (t / S)
đến hạn ⇔ t ≥ S  ⇔  R ≤ 0,9
```

#### 3.2.1 Nhóm câu mới — ưu tiên bài học dang dở (đã chốt)

**Ý nghĩa:** học viên được **phủ nốt các bài học đã bắt đầu** trước khi hệ thống kéo sang bài chưa đụng tới. Tránh rải một câu mỗi bài rồi bỏ dở hàng loạt.

Đơn vị phủ: **bài học** (`lesson_id` trên production; trong mẫu `mophong` dùng `topicId` tương đương đơn vị nhóm).

Một bài học gọi là **dang dở** khi học viên đã có **≥ 1 câu** thuộc bài đó đã làm hợp lệ (còn câu unseen trong cùng bài).  
Bài **chưa mở** = chưa có câu nào trong bài đã làm.

**Thứ tự chọn câu mới** (mỗi lần lấy 1 câu cho suất mới):

```text
1) Ưu tiên tuyệt đối các bài học dang dở (đã có câu làm) còn unseen
2) Trong cùng tầng (dang dở hoặc chưa mở):
     điểm = trọng số_ma_trận / (1 + số_câu_đã_gặp_trong_bài)
   → bài nặng hơn / còn ít câu đã gặp hơn lên trước
3) Trong bài đã chọn: bốc ngẫu nhiên một câu unseen
4) Hết bài dang dở mới sang bài chưa mở (vẫn theo điểm ma trận ở bước 2)
```

| Tầng | Điều kiện bài học | Mục tiêu |
|---|---|---|
| **A — Dang dở** | `số câu đã làm trong bài ≥ 1` và còn unseen | Phủ nốt bài đang học |
| **B — Chưa mở** | `số câu đã làm trong bài = 0` | Chỉ khi không còn suất/câu ở tầng A |

**Ví dụ nhanh:** đã làm 2/10 câu bài Tim mạch, chưa đụng Hô hấp → suất câu mới ưu tiên Tim mạch trước, dù Hô hấp có trọng số ma trận cao hơn.

**Reason gợi ý**

- Tầng A: *“Câu mới — tiếp tục bài đang học: {tên bài}”*
- Tầng B: *“Câu mới — bài học mới: {tên bài}”*

**Lưu ý**

- Học viên hoàn toàn mới (chưa làm câu nào): mọi bài ở tầng B → câu đầu theo điểm ma trận; **ngay trong phiên**, bài vừa mở chuyển sang tầng A nên các suất mới còn lại **phủ nốt bài đó** (không rải sang bài khác trong cùng phiên).  
- Suất *số lượng* câu mới vẫn do **Phân suất** (§3.3) quyết; mục này chỉ đổi *thứ tự / ưu tiên* trong nhóm Mới.  
- Câu đang thrash-block không lấy đúng `question_id` đó; sibling cùng bài dang dở vẫn được ưu tiên (khớp §3.1.1).  
- Production map đơn vị nhóm = `lesson_id` (bài học), không phải organ system.

**Đánh giá trụ Phân nhóm**

| Ưu | Nhược |
|---|---|
| Ngôn ngữ học tập tự nhiên: “yếu” vs “sắp quên” | Hai nhóm chồng lấn — cần quy tắc ưu tiên rõ (đã có) |
| `reason` string sẵn sàng cho UI (“Sai 3/5…”, “Đã 7 ngày…”) | Ngưỡng W = 0,5 và “đến hạn = còn nhớ 90%” là **chọn pedagogic**, khác production (`exp`) |
| Weakness cửa sổ 5 lần → có thể “khỏi yếu” | Sai → về bậc 1 rất nặng; dễ kẹt vòng ôn ngắn |
| Câu mới ưu tiên bài dang dở → ít “rải bài” | Bài trọng số cao nhưng chưa mở có thể chờ lâu hơn nếu nhiều bài dang dở |
| Dễ QA / admin / Excel mô phỏng | Bucket cứng kém linh hoạt hơn score liên tục |

→ **Đây là trụ mạnh nhất của đề xuất Claude:** biến thuật toán thành câu chuyện học viên hiểu được. Production nên **mượn nhãn nhóm** dù engine bên dưới vẫn score mềm.

---

### 3.3 Phân suất — “Phiên này lấy bao nhiêu từ mỗi giỏ?”

Hai lớp suất:

#### (A) Suất câu mới vs suất ôn — theo tồn đọng “Sắp quên” (đã chốt)

**Chung cho mọi mode** (Điểm yếu / Củng cố / Cân bằng giống nhau). Mode chỉ chia phần suất ôn ở (B).

| Tồn đọng nhóm Sắp quên | Tỷ lệ câu mới | Ví dụ N = 10 |
|---|---:|---:|
| Học viên chưa từng làm câu nào | 100% | 10 |
| Thấp (`due < 1×N`) | **30%** | 3 |
| Vừa (`1×N ≤ due < 3×N`) | **20%** | 2 |
| Cao (`due ≥ 3×N`) | **10%** | 1 |

```text
newCount     = round(share × N)   # bị chặn bởi số unseen còn lại
reviewSlots  = N − newCount
```

Ý nghĩa: khi due cao vẫn giữ ~10% câu mới để phủ bài dang dở / ma trận — không đóng băng hoàn toàn nội dung mới.

#### (B) Chia `reviewSlots` theo mode

| Mode | Suất Yếu | Suất Sắp quên |
|---|---:|---:|
| Điểm yếu | ≈ 100% reviewSlots | 0 (thiếu thì bù) |
| Củng cố | 0 (thiếu thì bù) | ≈ 100% reviewSlots |
| Cân bằng | `ceil(reviewSlots / 2)` | phần còn lại |

Trong mỗi suất: lấy từ **top** danh sách đã xếp hạng, nhưng bốc ngẫu nhiên trong cửa sổ `diversityFactor × số cần` (mặc định ×2) để tránh luôn cùng một vài câu.

Thiếu sau cùng → lấp đầy → nếu vẫn thiếu: `shortfall` + thông báo “quay lại sau 20 giờ / mở rộng chủ đề”.

**Đánh giá trụ Phân suất**

| Ưu | Nhược |
|---|---|
| Mode = lời hứa UX rõ (“bạn chọn Điểm yếu → hầu hết câu ôn là yếu”) | Suất cứng có thể lệch kỳ vọng khi một nhóm rỗng |
| New share 30/20/10 theo due — đơn giản, mọi mode giống nhau | Due cao vẫn 10% mới → dọn hàng đợi ôn chậm hơn một chút so với 0% |
| Diversity window cân bằng giữa ưu tiên và nhàm | Vẫn kém đa dạng hơn weighted random thuần |

→ **Nên port lớp (A) 30/20/10 sang production sớm.** Lớp (B) có thể giữ soft weights production nhưng thêm **sàn suất** khi pool đủ lớn.

---

## 4. Thuật toán chọn phiên — phiên bản “nói chuyện”

Giả sử học viên chọn **Cân bằng**, N = 10.

1. **Lọc** còn lại: 40 câu eligible + 15 câu chưa làm.  
2. **Phân nhóm:** 12 câu Yếu, 25 câu Sắp quên (có trùng), 15 câu Mới.  
3. **Phân suất:** due = 25 ≥ 3×10 → **1 câu mới (10%)**, 9 suất ôn → Cân bằng ≈ 5 Yếu + 4 Sắp quên.  
4. Trong nhóm Yếu: xếp W cao → bốc 5 trong top ~10.  
5. Trong nhóm Sắp quên: bỏ câu đã lấy ở bước 4 → xếp R thấp → bốc đủ 4.  
6. 1 câu mới ưu tiên bài dang dở (§3.2.1) → gắn lý do + đảo đáp án → mở phiên.

Nếu học viên mới (chưa làm gì): sau Lọc không có eligible → Phân suất cho **10 câu mới** theo trọng số ma trận (mọi bài ở tầng “chưa mở”).  
Nếu đã có bài dang dở: suất câu mới **phủ nốt bài đó** trước khi mở bài mới (§3.2.1).

---

## 5. Đánh giá tổng thể mô hình Claude đề xuất

### 5.1 Phù hợp bài toán luyện đề y khoa

| Nhu cầu sản phẩm | Mô hình đáp ứng? |
|---|---|
| Vá điểm yếu sát thi | Có — mode + nhóm Yếu |
| Spaced repetition đơn giản, giảng được | Có — thang bậc + đến hạn R≤90% |
| Bám ma trận / phủ bài dang dở | Có — câu mới: bài dang dở trước, rồi `weight/(1+exposure)` |
| Không spam cùng câu | Có — cooldown 20h |
| Giải thích cho học viên | Có — `reason` + bucket |
| Nghiệm thu trước khi code PHP | Có — 12 test + Excel + demo |

### 5.2 Điểm mạnh cốt lõi của khung 3 trụ

1. **Tách trách nhiệm rõ:** Lọc ≠ Nhóm ≠ Suất — dễ debug (“thiếu câu vì lọc” vs “vì suất”).  
2. **Pedagogic trước, random sau:** suất quyết định ý định học; random chỉ trong top hẹp.  
3. **Tồn đọng điều khiển nhịp học:** due cao thì ngừng đổ câu mới — đúng với spaced repetition thực dụng.  
4. **Hợp nghiệm thu sản phẩm:** Excel “So sánh 3 phiên” chứng minh cùng state → 3 mode chọn khác nhau.

### 5.3 Điểm yếu / rủi ro cần biết

1. **Đã ship ladder + `0,9^(t/S)`** — S cũ (×2/×0.3) map về bậc gần nhất khi đọc/ghi.  
2. **Phạt sai nặng** (về bậc 1) có thể gây frustration.  
3. **Cooldown cứng** dễ báo “hết câu” khi luyện dày.  
4. **Nhóm chồng lấn** nếu không có quy tắc bù/ưu tiên sẽ khó giải thích — code mẫu đã xử lý nhưng UI cần nói rõ.  
5. **optionCount = 5** trong mẫu lệch SRS session (thường A–D) — chỉ là hằng số demo.

### 5.4 So với production (một bảng)

| | Claude / mophong (3 trụ) | Production V2 (đã ship) |
|---|---|---|
| Kiến trúc | Lọc → Nhóm → Suất | Lọc → Nhóm → Suất (`filter_group_quota_v2`) |
| Độ bền / R | Ladder + `0,9^(t/S)` | Giống — `MemoryStability` |
| Kiểm soát tồn đọng | newShare theo due | 30/20/10 mọi mode |
| Thrash | ≥3 → 72h+2 phiên; ≥5 → 7 ngày | Giống |
| V1 soft-score | — | Chỉ còn trong log/docs lịch sử |

---

## 6. Khuyến nghị xử lý (gắn với khung 3 trụ)

| Trụ | Khuyến nghị | Độ ưu tiên |
|---|---|---|
| **Lọc** | Giữ filter publish/report/open-session/version; **thrash cooldown §3.1.1 đã chốt**; cooldown serve: soft/hybrid | **P0** (thrash) / P1 (serve) |
| **Phân nhóm** | **Mượn nhãn** Yếu / Sắp quên / Mới cho UI & admin ngay; threshold map từ W và urgency production | P1 |
| **Phân suất** | **Port ngay** quy tắc câu mới theo due backlog; mode: giữ soft weights + thêm sàn suất khi pool đủ | **P0** |
| Bộ nhớ nền (S, R) | **Giữ production** cho đến khi có metric; không flip ladder/`0,9^` trong Phase đầu | — |
| Weakness | Port cửa sổ 5 lần (thuộc “nguyên liệu” của Phân nhóm) | **P0** |

**Một dòng:**  
Khung *Lọc → Phân nhóm → Phân suất* của Claude là **khung tổ chức đúng** cho phiên thích ứng (dễ hiểu, dễ test, dễ giảng). Production nên **khoác khung này lên** (nhãn + suất + lọc chặt), còn **máy tính nhớ bên dưới** có thể vẫn dùng `MemoryStability` hiện tại cho đến khi product chủ động đổi pedagogic contract.

---

## 7. Thuật toán cập nhật sau mỗi câu (đi kèm khung)

Khung 3 trụ chỉ dựng phiên. Sau khi học viên trả lời, `applyAttempt` cập nhật hồ sơ để lần sau Phân nhóm đúng:

```text
Lượt hợp lệ?
  không → chỉ cập nhật lastSelectedAt (nghỉ)
  có    → cập nhật recentResults (W) + ladderStep (S)
          ghi log: S trước, R trước (để học / audit)
```

Đóng phiên bỏ dở: các câu chưa nộp = không hợp lệ → không làm “yếu hơn” giả tạo.

---

## 8. Tài liệu & code liên quan

| File | Nội dung |
|---|---|
| `mophong/adaptiveSession.ts` | Hiện thực đủ Lọc / Nhóm / Suất |
| `mophong/adaptiveSession.test.ts` | Nghiệm thu (gồm shortfall, mode, topic weight) |
| `mophong/demo.ts` | 10 phiên cân bằng |
| `mophong/mo_phong_10_phien_50_cau.xlsx` | Tham số + nhật ký + so 3 mode |
| `mophong/final.md` | Phân tích hướng xử lý tổng thể vs production |
| `docs/adaptive-session-algorithm.md` | Thuật toán production đang ship |

---

## Phụ lục — Thuật ngữ nhanh

| Thuật ngữ | Nghĩa ngắn |
|---|---|
| Lọc | Loại câu không được phép chọn |
| Phân nhóm | Gán câu vào giỏ Yếu / Sắp quên / Mới / Lấp |
| Phân suất | Quyết định số chỗ mỗi giỏ trong phiên N câu |
| Eligible | Đã học + qua lọc + hết thời gian nghỉ + hết thrash |
| Due / Sắp quên | Đã đến hạn ôn (`t ≥ S`) |
| Shortfall | Không đủ câu để đủ N sau mọi bước |
| Diversity window | Bốc ngẫu nhiên trong top (k × suất), không lấy cứng đúng Top-N |
| `streak_wrong` | Số lần sai hợp lệ liên tiếp; đúng → về 0 |
| Thrash cooldown | Tạm không đưa đúng câu đó vào phiên vì sai liên tiếp (§3.1.1) |
| Bài học dang dở | Đã có ≥1 câu làm trong bài, còn unseen — ưu tiên khi chọn câu mới (§3.2.1) |
