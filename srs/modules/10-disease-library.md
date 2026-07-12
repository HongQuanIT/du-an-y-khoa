# Module 10 — Disease Library

**Nhóm:** Content · **Ưu tiên:** Cao · **Phụ thuộc:** Medical Library (09), Drug (11), Images (13) · **Trạng thái:** ✅

## 0. Tóm tắt module
Thư viện bệnh học có cấu trúc chuẩn hóa (định nghĩa → dịch tễ → cơ chế → lâm sàng → chẩn đoán → điều trị → biến chứng → tiên lượng). Dùng chung hạ tầng `Article(type=disease)` + section chuẩn + cross-link mạnh tới thuốc/thủ thuật/hình ảnh/câu hỏi.

| Route | Màn hình |
|-------|----------|
| `/library/diseases` | Duyệt bệnh (theo hệ cơ quan/chuyên ngành) |
| `/library/diseases/{slug}` | Trang bệnh |
| `/library/diseases/compare` | So sánh bệnh (Premium) |

## 1. Tổng quan
- **Mục đích:** Tra cứu bệnh nhanh & học có hệ thống; đối chiếu chẩn đoán phân biệt.
- **Đến từ:** Library, cross-link từ câu hỏi, search, AI.
- **Đi sang:** Thuốc điều trị, thủ thuật, hình ảnh minh họa, câu hỏi liên quan.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Disease directory** | Cây phân loại theo hệ cơ quan | `/library/diseases` | Grid → list |
| **Section navigator** | Nhảy nhanh section chuẩn | Trang bệnh | Sticky/drawer |
| **Structured sections** | Định nghĩa, dịch tễ, sinh lý bệnh, lâm sàng, cận lâm sàng, chẩn đoán phân biệt, điều trị, biến chứng | Trang bệnh | Accordion mobile |
| **DDx table** | Bảng chẩn đoán phân biệt | Trong bệnh | Cuộn ngang |
| **Compare tool** | So sánh nhiều bệnh cạnh nhau | `/compare` | Premium |
| **Cross-link panel** | Thuốc/thủ thuật/ảnh/câu hỏi | Aside | Inline mobile |
| **Reading toolbar** | Highlight/note/bookmark/AI | Trang bệnh | Overflow |
| **Empty/Loading/Error/Paywall** | Chuẩn Library | Theo trạng thái | — |

## 3. Phân tích Component
- Kế thừa `ArticleReader` (module 09) + `DiseaseSectionNav`, `DdxTable`, `DiseaseCompare`.
- `DdxTable`: props `rows[]{disease, features, distinguishers}`; events `onOpenDisease`.
- `DiseaseCompare`: props `diseases[]`; state cột đang so sánh; permission Premium.

## 4. Luồng người dùng
```
Câu hỏi (chẩn đoán) → cross-link "xem bệnh X" → trang bệnh → section điều trị → thuốc
/library/diseases → duyệt hệ tim mạch → mở bệnh → so sánh với bệnh tương tự (Premium)
```

## 5. Business Logic
- **Schema section chuẩn** cho mọi bệnh (đảm bảo nhất quán khi biên soạn).
- **DDx & compare:** lấy từ `content_links` (relation `differential`) + field cấu trúc.
- Gating/highlight/note/versioning: như module 09.
- **High-yield markers:** đánh dấu nội dung trọng tâm ra thi.

## 6. Database
- `articles(type=disease)` + `disease_meta(article_id, icd_code, system, prevalence JSON, high_yield JSON)`.
- `content_links` relation: `treats`(drug), `diagnosed_by`(procedure), `differential`(disease), `illustrated_by`(media), `tested_by`(question).

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/diseases` | directory | Auth |
| GET | `/api/v1/diseases/{slug}` | bệnh + sections + links | Auth (gated) |
| GET | `/api/v1/diseases/compare?ids=` | so sánh | Premium |

## 8. State Management
- Như module 09; compare state client; cache render theo version.

## 9. Phân quyền
- Free preview; Premium full + compare. Editor soạn (module 36).

## 10. Edge Cases
- Bệnh thiếu section → hiển thị "đang cập nhật"; link tới thuốc retired → ẩn; compare > giới hạn cột → chặn.

## 11. Tracking
`disease_open`, `disease_section_view`, `ddx_open`, `disease_compare`, `crosslink_click`, highlight/note/bookmark.

## 12. Responsive
- Desktop: section nav trái + nội dung + cross-link phải. Mobile: accordion section, compare chuyển dạng thẻ cuộn.

## 13. Security & 14. Performance
- Như module 09 (sanitize, signed media, cache render, search Meili, index theo system/ICD).

## 15. Đề xuất cải tiến
- Interactive DDx: nhập triệu chứng → gợi ý bệnh.
- Sơ đồ tiếp cận (algorithm flowchart) tương tác.
- Liên kết trực tiếp "luyện câu hỏi về bệnh này".
