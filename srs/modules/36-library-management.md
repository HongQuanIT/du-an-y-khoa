# Module 36 — Library Management (Admin/Editor)

**Nhóm:** Admin · **Ưu tiên:** Cao · **Phụ thuộc:** Library (09), Disease (10), Drug (11), Procedures (12), Media (37), RBAC (39), Audit (40) · **Trạng thái:** ✅

## 0. Tóm tắt module
CRUD & duyệt nội dung thư viện (bài viết, bệnh, thuốc, thủ thuật): rich editor, cấu trúc section chuẩn, cross-link, media, versioning, publish workflow, đồng bộ search.

| Route | Màn hình |
|-------|----------|
| `/admin/library` | Danh sách nội dung (theo type) |
| `/admin/library/create` | Soạn bài |
| `/admin/library/{id}/edit` | Chỉnh sửa |
| `/admin/library/{id}/links` | Quản lý cross-link |

## 1. Tổng quan
- **Mục đích:** Sản xuất & duy trì "sách giáo khoa số".
- **Đến từ:** Admin dashboard.
- **Đi sang:** Preview như học viên, media, cross-link tới câu hỏi/thuốc.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Content table** | Filter type/status/topic, search | List | Table |
| **Rich editor** | WYSIWYG/Markdown, heading→TOC tự động, bảng, callout, media embed | Create/edit | Full |
| **Structured sections** | Template theo type (disease/drug/procedure) | Editor | — |
| **Cross-link manager** | Thêm/sửa liên kết chéo | `/links` | Panel |
| **Workflow bar** | Draft→review→published→retired | Editor | — |
| **Version history** | Diff/rollback | Editor | Drawer |
| **Preview** | Xem như học viên | Editor | Split |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `RichContentEditor`(sanitize, TOC), `SectionTemplate`(theo type), `CrossLinkManager`, `WorkflowStatusBar`, `VersionHistory`, `MediaPicker`(module 37), `LibraryPreview`.

## 4. Luồng người dùng
```
Editor tạo bài bệnh → điền section chuẩn → chèn ảnh/video → cross-link thuốc/câu hỏi
 → preview → gửi duyệt → publish → đồng bộ Meili → hiển thị.
```

## 5. Business Logic
- **Section template** đảm bảo cấu trúc nhất quán theo type.
- **Versioning + snapshot** (highlight/note re-anchor theo snapshot).
- **Cross-link** (`content_links`) hai chiều; kiểm tra target tồn tại.
- **is_free/premium** đặt mức truy cập.
- **Publish workflow** + quyền; retire thay xóa.
- **Đồng bộ search** async; sinh biến thể media.

## 6. Database
- `articles`(+meta bệnh/thuốc/thủ thuật), `content_links`, `article_versions`, `media`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/library` | filter | list | `library.view` |
| POST | `/api/v1/admin/library` | article payload | draft | `library.edit` |
| PUT | `/api/v1/admin/library/{id}` | fields | article | `library.edit` |
| POST | `/api/v1/admin/library/{id}/publish` | — | published | `library.publish` |
| CRUD | `/api/v1/admin/library/{id}/links` | — | links | `library.edit` |

## 8. State Management
- Autosave; version lock; search sync async; media embed async.

## 9. Phân quyền
- Editor soạn; reviewer/admin publish. Xem RBAC.

## 10. Edge Cases
- Link tới nội dung retired → cảnh báo/ẩn; publish khi media chưa ready → chặn; concurrent edit → 409; nội dung lớn → autosave chunk.

## 11. Tracking (audit + product)
`article_create`, `article_update`, `article_publish`, `article_retire`, `crosslink_add`, `article_preview`.

## 12. Responsive
- Desktop tối ưu; mobile hạn chế (duyệt/sửa nhỏ).

## 13. Security
- Sanitize rich content nghiêm (XSS); RBAC; audit; kiểm soát embed media/URL (SSRF); không editor tự publish nếu cần duyệt.

## 14. Performance
- Search sync queue; render cache invalidations khi publish; version snapshot; media CDN.

## 15. Đề xuất cải tiến
- AI soạn/tóm tắt/kiểm tra tính nhất quán; gợi ý cross-link tự động; dịch VI/EN; kiểm tra nguồn/citation tự động; lịch xuất bản.
