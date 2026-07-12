# Module 15 — Notes (Ghi chú)

**Nhóm:** Personal · **Ưu tiên:** Trung bình · **Phụ thuộc:** Session (06), Library (09) · **Trạng thái:** ✅

## 0. Tóm tắt module
Ghi chú cá nhân gắn với câu hỏi/bài viết/thuốc/ảnh/video (polymorphic) hoặc ghi chú tự do. Quản lý tập trung + chỉnh sửa tại chỗ (inline) trong context.

| Route | Màn hình |
|-------|----------|
| `/notes` | Quản lý tất cả ghi chú |
| Inline note | Trong Session/Library/... |

## 1. Tổng quan
- **Mục đích:** Lưu suy nghĩ, mẹo nhớ, đính vào nội dung.
- **Đến từ:** Toolbar câu hỏi/bài viết; sidebar Notes.
- **Đi sang:** Quay lại nội dung gốc (deep link).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Notes manager** | List + search + filter theo loại nội dung | `/notes` | List |
| **Note editor** | Rich text (bold/list/highlight color) | Khi tạo/sửa | Drawer/modal |
| **Inline note popover** | Ghi chú nhanh trong context | Toolbar | Bottom sheet mobile |
| **Note card** | Preview + link nguồn + màu | List | — |
| **Empty/Loading/Error** | Chưa có note; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
### `NoteEditor`
- **Props:** `note?`, `context(notable_type,id)`, `colors[]`.
- **State:** `body`, `color`, `dirty`, `saving`.
- **Events:** `onSave(debounce autosave)`, `onDelete`, `onColorChange`.
- **Validation:** giới hạn độ dài; sanitize rich text.
- **A11y:** editor label, phím tắt lưu; **Loading/Error:** autosave badge/retry; **Empty:** placeholder.

### `NotesList` (search/filter), `NotePopover`, `NoteCard`.

## 4. Luồng người dùng
```
Session → toolbar Note → viết → autosave → tiếp tục làm bài
/notes → tìm "suy tim" → mở note → nút "về câu hỏi gốc"
```

## 5. Business Logic
- **Polymorphic** `notable_type/id`; hỗ trợ note tự do (không gắn).
- **Autosave** debounce; version nhẹ (không bắt buộc).
- **Deep link** về nguồn (câu/bài + anchor).
- Rich text sanitize; màu để phân loại.

## 6. Database
- `notes` (mục 5 data model), index `(user_id, notable_type, notable_id)`. Full-text search Meili (nội dung note của chính user).

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/notes?filter=` | — | list | Owner |
| POST | `/api/v1/notes` | `{notable_type,notable_id,body,color}` | note | Owner |
| PATCH | `/api/v1/notes/{id}` | fields | note | Owner |
| DELETE | `/api/v1/notes/{id}` | — | 204 | Owner |

## 8. State Management
- Optimistic create/update/delete; autosave; search cache client; không realtime.

## 9. Phân quyền
- Owner only (riêng tư). Instructor không xem note học viên (privacy). Free có (giới hạn số lượng tùy chính sách).

## 10. Edge Cases
- Nội dung gốc bị xóa → note giữ (mồ côi) + nhãn "nội dung đã gỡ"; offline → autosave local; concurrent edit 2 tab → last-write + cảnh báo; duplicate save → idempotent.

## 11. Tracking
`note_create`, `note_update`, `note_delete`, `note_open`, `note_search`.

## 12. Responsive
- Desktop: 2 cột (list + editor). Mobile: list → editor full-screen; inline popover = bottom sheet.

## 13. Security
- Riêng tư tuyệt đối (scope owner); sanitize XSS; không index note vào search chung.

## 14. Performance
- Autosave debounce; phân trang list; search Meili riêng user; index owner.

## 15. Đề xuất cải tiến
- Note dạng markdown + đính ảnh; gộp note thành "sổ tay" theo chủ đề; xuất PDF; AI tóm tắt/tổng hợp note theo chủ đề yếu.
