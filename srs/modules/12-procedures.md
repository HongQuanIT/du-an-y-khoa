# Module 12 — Procedures (Thủ thuật)

**Nhóm:** Content · **Ưu tiên:** Trung bình · **Phụ thuộc:** Library (09), Images (13), Videos (14) · **Trạng thái:** ✅

## 0. Tóm tắt module
Thư viện thủ thuật/kỹ thuật lâm sàng có cấu trúc: chỉ định, chống chỉ định, chuẩn bị, các bước thực hiện, biến chứng, chăm sóc sau. Nhiều media (ảnh minh họa, video hướng dẫn).

| Route | Màn hình |
|-------|----------|
| `/library/procedures` | Duyệt thủ thuật |
| `/library/procedures/{slug}` | Trang thủ thuật |

## 1. Tổng quan
- **Mục đích:** Học quy trình thủ thuật từng bước kèm media.
- **Đến từ:** Library, bệnh (chẩn đoán/điều trị), câu hỏi, search.
- **Đi sang:** Video hướng dẫn, ảnh, bệnh/câu hỏi liên quan.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Procedure directory** | Duyệt theo chuyên khoa | list | Grid → list |
| **Step-by-step viewer** | Các bước đánh số + media từng bước | Trang thủ thuật | Slider mobile |
| **Section nav** | Chỉ định/CCĐ/chuẩn bị/biến chứng | Trang | Sticky/drawer |
| **Media gallery** | Ảnh/video minh họa | Trong bước | Lightbox |
| **Checklist** | Danh sách chuẩn bị (tick) | Trang | — |
| **Cross-link** | Bệnh/câu hỏi/thiết bị | Aside | Inline |
| **Toolbar/Paywall/Empty/Loading/Error** | Chuẩn Library | Theo trạng thái | — |

## 3. Phân tích Component
- `ProcedureReader` (kế thừa ArticleReader), `StepViewer`, `PrepChecklist`, `MediaGallery`.
- `StepViewer`: props `steps[]{order, text, media_ids}`; state currentStep; events next/prev; a11y điều hướng phím.

## 4. Luồng người dùng
```
Bệnh → "thủ thuật chẩn đoán" → mở thủ thuật → xem step-by-step + video → checklist chuẩn bị
```

## 5. Business Logic
- **Steps** cấu trúc JSON có media theo bước.
- Gating/highlight/note/version như Library.
- Media Premium signed URL.

## 6. Database
- `articles(type=procedure)` + `procedure_meta(article_id, specialty, steps JSON, equipment JSON, complications JSON)`.
- `content_links`: `used_in`(disease), `illustrated_by`(media), `tested_by`(question).

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/procedures` | directory | Auth |
| GET | `/api/v1/procedures/{slug}` | thủ thuật + steps + media | Auth (gated) |

## 8. State Management
- Step index client; cache render; media lazy.

## 9. Phân quyền
- Free preview; Premium full (video/step chi tiết). Editor soạn.

## 10. Edge Cases
- Thiếu media bước → placeholder; video processing chưa xong → "đang xử lý"; retired → ẩn.

## 11. Tracking
`procedure_open`, `procedure_step_view`, `video_play`, `image_view`, `checklist_toggle`, `crosslink_click`.

## 12. Responsive
- Desktop: step list + media lớn. Mobile: slider bước, media full, checklist accordion.

## 13. Security & 14. Performance
- Signed media; sanitize; lazy media; cache render; disclaimer.

## 15. Đề xuất cải tiến
- Video có chương (chapter) theo bước.
- Chế độ "thực hành ảo" (simulation) từng bước.
- Ghép câu hỏi kiểm tra sau khi xem.
