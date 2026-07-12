# Module 11 — Drug Library

**Nhóm:** Content · **Ưu tiên:** Cao · **Phụ thuộc:** Medical Library (09), Disease (10) · **Trạng thái:** ✅

## 0. Tóm tắt module
Thư viện thuốc: phân loại, chỉ định, chống chỉ định, liều dùng, tác dụng phụ, tương tác. Cross-link với bệnh (điều trị) và câu hỏi dược lý. Có công cụ tra tương tác thuốc.

| Route | Màn hình |
|-------|----------|
| `/library/drugs` | Duyệt thuốc (theo nhóm dược lý) |
| `/library/drugs/{slug}` | Trang thuốc |
| `/library/drugs/interactions` | Kiểm tra tương tác |

## 1. Tổng quan
- **Mục đích:** Tra cứu thuốc nhanh, học dược lý, kiểm tra tương tác.
- **Đến từ:** Library, cross-link bệnh/câu hỏi, search.
- **Đi sang:** Bệnh được điều trị, câu hỏi dược, thuốc cùng nhóm.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Drug directory** | Cây nhóm dược lý | `/library/drugs` | Grid → list |
| **Drug header** | Tên gốc/biệt dược, nhóm, badge | Trang thuốc | — |
| **Structured sections** | Cơ chế, chỉ định, CCĐ, liều, TDP, tương tác, theo dõi | Trang thuốc | Accordion |
| **Dosing table** | Bảng liều theo chỉ định/đối tượng | Trang thuốc | Cuộn ngang |
| **Interaction checker** | Nhập nhiều thuốc → cảnh báo | `/interactions` | Multi-select |
| **Cross-link** | Bệnh điều trị / câu hỏi | Aside | Inline |
| **Toolbar** | Highlight/note/bookmark/AI | Trang thuốc | Overflow |
| **Empty/Loading/Error/Paywall** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `DrugReader` (kế thừa ArticleReader), `DosingTable`, `InteractionChecker`.
- `InteractionChecker`: props `drugs[]`; state selected; events `onCheck`; hiển thị mức độ (nặng/vừa/nhẹ); validation ≥2 thuốc; loading khi tra.

## 4. Luồng người dùng
```
Câu hỏi dược → cross-link thuốc → xem cơ chế/liều → kiểm tra tương tác với thuốc khác
/library/drugs → nhóm beta-blocker → mở thuốc → bệnh điều trị
```

## 5. Business Logic
- **Interaction logic:** tra từ `drug_interactions` (cặp thuốc + mức độ + mô tả); cảnh báo severity.
- **Dosing** dạng cấu trúc (JSON) theo chỉ định/nhóm tuổi/thận-gan.
- Gating/highlight/note/version như Library.
- **High-yield** markers cho dược lý thi.

## 6. Database
- `drugs` (mục 6 data model) + `drug_interactions(id, drug_a_id, drug_b_id, severity, mechanism, effect, management)`, unique `(drug_a,drug_b)`.
- `content_links`: `treats`(disease), `tested_by`(question), `same_class`(drug).

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/drugs` | — | directory | Auth |
| GET | `/api/v1/drugs/{slug}` | — | thuốc + sections + links | Auth (gated) |
| POST | `/api/v1/drugs/interactions` | `{drug_ids[]}` | cặp tương tác + severity | Auth |

## 8. State Management
- Như Library; interaction checker state client; cache drug render.

## 9. Phân quyền
- Free preview; Premium full + interaction checker đầy đủ. Editor soạn.

## 10. Edge Cases
- Thuốc không có dữ liệu tương tác → "chưa có dữ liệu"; thuốc trùng trong checker → loại; thuốc retired → ẩn cross-link.

## 11. Tracking
`drug_open`, `drug_section_view`, `interaction_check`, `crosslink_click`, highlight/note/bookmark.

## 12. Responsive
- Desktop: section nav + nội dung + cross-link. Mobile: accordion, dosing table cuộn, checker multi-select chip.

## 13. Security & 14. Performance
- Sanitize; cache render; index theo drug_class; disclaimer y khoa (không thay tư vấn lâm sàng).

## 15. Đề xuất cải tiến
- Máy tính liều theo cân nặng/độ lọc cầu thận.
- Cảnh báo tương tác realtime khi thêm thuốc.
- So sánh thuốc cùng nhóm (bảng).
- Nhắc "học dược lý qua câu hỏi" liên quan.
