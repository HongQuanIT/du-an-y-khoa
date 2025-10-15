# 🩺 DỰ ÁN HỌC & LUYỆN THI Y KHOA TRỰC TUYẾN

## I. MÔ TẢ DỰ ÁN

### 🎯 1. Mục tiêu dự án
Xây dựng nền tảng **học & luyện thi y khoa trực tuyến** cho sinh viên y và bác sĩ trẻ tại Việt Nam.  

Hệ thống cung cấp:
- **Ngân hàng câu hỏi chuẩn hóa** (dịch và biên soạn từ nguồn nước ngoài như Amboss, UWorld).
- **Luyện đề thông minh**: AI phân tích kết quả, gợi ý ôn tập theo điểm yếu.
- **Mô phỏng thi thật** theo chuẩn hành nghề.
- **AI Tutor**: Giải thích chi tiết từng câu hỏi, dẫn nguồn y khoa đáng tin cậy.

> 🎯 Mục tiêu chính: “Giúp sinh viên y học hiệu quả hơn — hiểu bản chất, nhớ lâu, luyện thi đúng trọng tâm.”

---

### 👥 2. Đối tượng người dùng
- **Sinh viên y khoa.**
- **Quản trị** (tạo đề, thống kê, quản lý người học).

---

### 📚 3. Đặc điểm học tập
Mỗi câu hỏi bao gồm:
- Nội dung câu hỏi + 4 đáp án  
- Giải thích tại sao đúng / sai  
- Chỉ số tham chiếu (lab values, guideline)  
- Ghi chú cá nhân (note)  
- Gắn **tag chuyên ngành** (18 chủ đề: sản, nhi, nội, ngoại, dược...)  
- Phân loại **độ khó & tình trạng**:  
  - Chưa làm  
  - Trả lời sai  
  - Trả lời đúng  
  - Có dùng hint  

**Hai chế độ học:**
- 🧠 **Study Mode:** Học kèm giải thích, xem kết quả từng câu.
- 🧾 **Exam Mode:** Làm đề giới hạn thời gian, xem kết quả sau khi nộp.

**Dashboard học tập:**
Hiển thị:
- Tỷ lệ đúng theo chủ đề  
- Chủ đề sai nhiều  
- Gợi ý tài liệu đọc thêm  
- Study Plan cá nhân hóa  

---

### ⚙️ 4. Chức năng chính

#### 🧑‍🎓 Người học
- Đăng ký / Đăng nhập (Google, Facebook, Email)  
- Hồ sơ cá nhân (profile, lịch sử học, tiến trình)  
- Luyện đề, ôn tập sai, thi thử  
- Ghi chú, đánh dấu câu hỏi  
- Xếp hạng, thống kê, biểu đồ kết quả  

#### 👩‍💼 Quản trị viên
- Quản lý ngân hàng câu hỏi (CRUD, import Excel/PDF)  
- Tạo bộ đề mẫu, kỳ thi online  
- Quản lý người dùng, phân quyền  
- Theo dõi log, báo cáo thống kê  

---

### 🤖 5. Tích hợp AI ( Dự kiến )
- **Retrieval-based GPT Tutor:**  
  AI trả lời và giải thích dựa trên dữ liệu câu hỏi có sẵn.
- **AI Study Suggestion:**  
  Dựa vào kết quả làm sai → đề xuất block ôn tập phù hợp.

---

### 🧱 6. Công nghệ dự kiến

| Thành phần | Công nghệ |
|-------------|------------|
| Backend | Laravel Octane |
| Frontend | TailwindCSS |
| Database | PostgreSQL + Redis |
| AI/Search | OpenAI API + pgvector / Pinecone |
| DevOps | Docker, Nginx, GitHub Actions |
| Hạ tầng | VPS (DigitalOcean / Linode), Cloudflare CDN & SSL |

---

## II. THUẬT NGỮ KỸ THUẬT HÓA CHO DESIGNER (UI/UX BRIEF)

| Thành phần thiết kế | Thuật ngữ / Hướng dẫn kỹ thuật |
|----------------------|-------------------------------|
| **Trang chủ (Home)** | Hero banner (title + CTA "Bắt đầu luyện thi"); Section giới thiệu 3 giá trị cốt lõi: “Học thông minh”, “Thi hiệu quả”, “AI hỗ trợ” |
| **Đăng ký / Đăng nhập** | Modal hoặc page riêng (Auth layout), hỗ trợ Google/Facebook/Email |
| **Dashboard người học** | Layout 2 cột: Sidebar (menu học tập), Main content (thống kê, biểu đồ, gợi ý học tập) |
| **Trang luyện đề (Practice UI)** | Component hiển thị từng câu hỏi (1 câu/1 trang); có thanh tiến trình; switch Study/Exam mode |
| **Câu hỏi chi tiết** | Card gồm: Question text, Answer options (radio), Explain panel (ẩn/hiện), Bookmark icon, Note section |
| **Kết quả sau bài thi** | Summary page: Biểu đồ (donut chart % đúng/sai), list chủ đề sai, link “Ôn lại câu sai” |
| **Ngân hàng câu hỏi (Admin)** | Data table + CRUD modal, filter theo môn, độ khó, chủ đề; Import Excel/PDF |
| **Trang cộng đồng** | Discussion board, comment thread, rating hệ thống |
| **Responsive** | Ưu tiên mobile-first; breakpoint chính: 640px / 1024px |
| **Tông màu chủ đạo** | Xanh dương y khoa (#007BFF), trắng, xám nhạt; accent xanh lá (#28C76F) cho kết quả đúng |
| **Kiểu chữ đề xuất** | Inter / Poppins — tối giản, dễ đọc trên nền sáng |
| **Iconography** | Sử dụng bộ Lucide Icons hoặc Heroicons |
| **Animation** | Framer Motion: hiệu ứng mượt cho quiz chuyển câu, modal hiển thị, biểu đồ loading |
| **UX Notes** | Giữ flow tối giản, mọi hành động (chọn đáp án, chuyển câu, nộp bài) có phản hồi rõ ràng (loading, checkmark, toast). |

---

## III. OUTPUT MONG ĐỢI CHO DESIGNER

### 🧩 Function cần có
1. **Func 1:** Landing + Auth  
2. **Func 2:** Student Dashboard  
3. **Func 3:** Practice UI (Study / Exam Mode)  
4. **Func 4:** Result Summary + Analytics  
5. **Func 5:** Admin CMS  
6. **Func 6:** Community
...

### 🧱 Component Library
Button, Card, Modal, Table, Chart, Tabs, Tag, Toast, Tooltip, Pagination.

---

### ✅ Kết quả mong đợi
- Học viên có công cụ luyện thi y khoa chuyên nghiệp.  
- Tiết kiệm thời gian, học tập hiệu quả nhờ phân tích kết quả học tập, gợi học tập với 2 chế độ học, học theo cá nhân hoá.  
- Hệ thống ổn định, mở rộng dễ dàng, chịu tải > 5000 người truy cập đồng thời.
