# Module 17 — Highlight (Tô sáng)

**Nhóm:** Personal · **Ưu tiên:** Trung bình · **Phụ thuộc:** Library (09), Session (06) · **Trạng thái:** ✅

## 0. Tóm tắt module
Tô sáng đoạn văn trong bài viết/giải thích câu hỏi, nhiều màu, kèm ghi chú, bền vững qua phiên bản nội dung. Tổng hợp highlight để ôn nhanh.

| Route | Màn hình |
|-------|----------|
| `/highlights` | Tổng hợp highlight |
| Inline | Trong Library/Session explanation |

## 1. Tổng quan
- **Mục đích:** Đánh dấu nội dung quan trọng để ôn.
- **Đến từ:** Bôi chọn text trong bài/giải thích.
- **Đi sang:** Về vị trí highlight trong nội dung gốc.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Selection toolbar** | Chọn màu, thêm note, copy khi bôi text | Khi có selection | Popover/bottom sheet |
| **Highlight layer** | Render vùng tô trên nội dung | Trang có nội dung | — |
| **Highlights manager** | List theo màu/nội dung + jump | `/highlights` | List |
| **Color legend** | Ý nghĩa màu (tùy biến) | Manager | — |
| **Empty/Loading/Error** | Chưa có; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
### `HighlightLayer`
- **Props:** `content`, `highlights[]`, `editable`.
- **State:** `selection`, `activeHighlight`.
- **Events:** `onCreate(anchor,color)`, `onDelete`, `onAddNote`, `onJump`.
- **Business:** anchor bằng selector + offset + `text_snapshot` để re-anchor sau update.
- **A11y:** highlight có nhãn; keyboard tạo highlight khó → cung cấp menu thay thế.
- **Loading/Error/Empty:** overlay tải; lỗi tạo → toast.

### `SelectionToolbar`, `HighlightsList`, `ColorPicker`.

## 4. Luồng người dùng
```
Đọc bài → bôi đoạn → chọn màu vàng → (thêm note) → lưu
/highlights → lọc màu đỏ → jump về vị trí trong bài
```

## 5. Business Logic
- **Anchor bền vững:** lưu selector/offset + snapshot text; khi nội dung đổi version, thử re-anchor theo snapshot; fail → chuyển thành "note mồ côi".
- **Màu** phân loại (tự định nghĩa ý nghĩa).
- **Overlap** highlight xử lý (merge/độ ưu tiên).
- Optimistic create/delete.

## 6. Database
- `highlights` (mục 5 data model): `highlightable_type/id, anchor JSON, text_snapshot, color, note`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/highlights?type=&color=` | list | Owner |
| POST | `/api/v1/highlights` | `{type,id,anchor,text,color,note?}` | highlight | Owner |
| PATCH | `/api/v1/highlights/{id}` | `{color,note}` | highlight | Owner |
| DELETE | `/api/v1/highlights/{id}` | — | 204 | Owner |

## 8. State Management
- Highlight của trang tải cùng nội dung; optimistic; cache theo (user,content); không realtime.

## 9. Phân quyền
- Owner (riêng tư). Premium: không giới hạn + màu tùy biến; Free: giới hạn.

## 10. Edge Cases
- Nội dung update mất anchor → re-anchor/mồ côi; selection cross-element phức tạp → chuẩn hóa; offline → sync sau; duplicate → idempotent.

## 11. Tracking
`highlight_create`, `highlight_delete`, `highlight_note_add`, `highlight_jump`, `highlights_open`.

## 12. Responsive
- Desktop: popover chọn màu. Mobile: bottom sheet, chạm-giữ để chọn text; layer scale theo font.

## 13. Security
- Scope owner; sanitize note; anchor không thực thi HTML; chống IDOR.

## 14. Performance
- Render layer hiệu quả (virtual khi nhiều highlight); tải kèm nội dung; index owner+content.

## 15. Đề xuất cải tiến
- "Study from highlights": tạo flashcard/ôn từ highlight; chia sẻ highlight nhóm; thống kê phần được tô nhiều nhất (crowd-highlight có kiểm duyệt).
