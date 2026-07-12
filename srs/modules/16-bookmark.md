# Module 16 — Bookmark (Đánh dấu/Lưu)

**Nhóm:** Personal · **Ưu tiên:** Trung bình · **Phụ thuộc:** Session (06), Library (09) · **Trạng thái:** ✅

## 0. Tóm tắt module
Lưu nhanh câu hỏi/bài viết/thuốc/ảnh/video để xem lại, tổ chức theo thư mục (folder). Khác Flag (flag là trong phạm vi session để review).

| Route | Màn hình |
|-------|----------|
| `/bookmarks` | Quản lý bookmark + folder |
| Inline bookmark | Toolbar mọi nội dung |

## 1. Tổng quan
- **Mục đích:** Tạo bộ sưu tập nội dung quan tâm để quay lại.
- **Đến từ:** Toolbar nội dung, sidebar.
- **Đi sang:** Nội dung gốc; tạo session từ bookmark câu hỏi.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Bookmark manager** | Tab theo loại + folder sidebar | `/bookmarks` | List |
| **Folder list** | Tạo/sửa/xóa folder, kéo-thả | Manager | Drawer mobile |
| **Bookmark icon** | Toggle lưu (optimistic) | Toolbar | — |
| **Bulk actions** | Chọn nhiều → chuyển folder / tạo session | Manager | — |
| **Empty/Loading/Error** | Chưa lưu; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
### `BookmarkButton`
- **Props:** `bookmarkable(type,id)`, `initialState`, `folderId?`.
- **State:** `active`, `saving`.
- **Events:** `onToggle(optimistic)`, `onChooseFolder`.
- **A11y:** pressed state; **Error:** rollback + toast.

### `BookmarkManager`, `FolderSidebar`, `BookmarkCard`.

## 4. Luồng người dùng
```
Nội dung → icon bookmark → chọn folder (tuỳ chọn) → lưu
/bookmarks → folder "Tim mạch" → chọn nhiều câu → "Tạo session ôn tập"
```

## 5. Business Logic
- **Polymorphic** bookmarkable; unique theo (user,type,id).
- **Folder** tùy chọn; mặc định "Chưa phân loại".
- **Tạo session từ bookmark câu hỏi** (chuyển sang Qbank/Session).
- Optimistic toggle.

## 6. Database
- `bookmarks`, `bookmark_folders` (mục 5 data model). Index owner + type.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| POST | `/api/v1/bookmarks` | `{type,id,folder_id?}` | bookmark | Owner |
| DELETE | `/api/v1/bookmarks` | `{type,id}` | 204 | Owner |
| GET | `/api/v1/bookmarks?type=&folder=` | list | Owner |
| POST/PATCH/DELETE | `/api/v1/bookmark-folders` | — | CRUD | Owner |
| POST | `/api/v1/bookmarks/to-session` | `{ids[]}` | session | Owner |

## 8. State Management
- Optimistic toggle; trạng thái bookmark cache trong context; list phân trang; không realtime.

## 9. Phân quyền
- Owner. Free có (giới hạn số/folders tuỳ chính sách); Premium không giới hạn.

## 10. Edge Cases
- Nội dung xóa → bookmark mồ côi (nhãn); duplicate toggle nhanh → idempotent theo unique; offline → hàng đợi sync; xóa folder có item → hỏi chuyển/xoá.

## 11. Tracking
`bookmark_add`, `bookmark_remove`, `folder_create`, `bookmark_to_session`, `bookmarks_open`.

## 12. Responsive
- Desktop: folder sidebar + grid. Mobile: folder drawer, list card, swipe xóa.

## 13. Security
- Scope owner; chống IDOR folder/bookmark.

## 14. Performance
- Optimistic; index; phân trang; cache trạng thái để render nhanh icon.

## 15. Đề xuất cải tiến
- Bookmark chia sẻ trong lớp; smart folder theo tag; nhắc "ôn bookmark chưa động tới".
