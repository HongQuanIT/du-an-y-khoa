# Module 09 — Medical Library

**Nhóm:** Content · **Ưu tiên:** Cao · **Phụ thuộc:** Search (25), AI (08), Highlight/Note/Bookmark (15–17), Library Management (36) · **Trạng thái:** ✅

## 0. Tóm tắt module
Thư viện bài viết y khoa (kiến thức nền) liên kết chéo với câu hỏi, thuốc, hình ảnh. Là "sách giáo khoa số" của nền tảng. Disease/Drug/Procedures là các thể loại chuyên biệt (module 10–12) dùng chung hạ tầng Article/Media/ContentLink.

| Route | Màn hình |
|-------|----------|
| `/library` | Trang chủ thư viện (duyệt theo chủ đề) |
| `/library/search` | Kết quả tìm kiếm |
| `/library/{slug}` | Trang bài viết |

## 1. Tổng quan
- **Mục đích:** Đọc kiến thức nền, tra cứu nhanh khi làm bài.
- **Đến từ:** Sidebar, cross-link từ câu hỏi, search, AI citation.
- **Đi sang:** Bài liên quan, thuốc, hình ảnh, câu hỏi liên quan.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Library home** | Duyệt theo chuyên ngành/hệ cơ quan (grid card) | `/library` | Grid → list |
| **Search bar + filter** | Tìm + lọc loại nội dung | Luôn | Sticky |
| **TOC (mục lục)** | Điều hướng trong bài (sticky) | Trang bài | Drawer mobile |
| **Article body** | Nội dung rich: heading, bảng, ảnh, callout | Trang bài | Full |
| **Reading toolbar** | Highlight, note, bookmark, font size, AI, print | Trang bài | Overflow mobile |
| **Cross-link chips** | Liên kết câu hỏi/thuốc/ảnh liên quan | Trong bài | Inline/aside |
| **Related articles** | Gợi ý đọc thêm | Cuối bài | Carousel |
| **Reading progress** | Thanh % đọc | Trang bài | Top |
| **Paywall** | Bài Premium | Free | Overlay từ giữa bài |
| **Empty/Loading/Error** | Không kết quả; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
### `ArticleReader`
- **Props:** `article(body, toc, references, crossLinks)`, `userHighlights`, `userNotes`, `isPremiumLocked`.
- **State:** `activeSection`, `fontSize`, `selection`.
- **Events:** `onHighlight`, `onNote`, `onBookmark`, `onCrosslink`, `onAsk AI`.
- **Permission:** Free preview → paywall giữa bài; Premium full.
- **A11y:** heading structure, skip-to-content, contrast, font scaling.
- **Loading:** skeleton; **Error:** retry; **Empty:** N/A.

### `TableOfContents`, `HighlightLayer` (anchor selection), `CrossLinkChip`, `ReadingProgressBar`, `RelatedContent`.

## 4. Luồng người dùng
```
/library → chọn chuyên ngành → mở bài → đọc, highlight, note, bookmark
 → click cross-link → mở câu hỏi/thuốc liên quan
 → hỏi AI về đoạn đang đọc
Free: đọc preview → paywall → upgrade.
```

## 5. Business Logic
- **Cross-linking:** `ContentLink` liên kết Article ↔ Question ↔ Drug ↔ Media.
- **Gating:** `is_free` cho preview; Premium full; server enforce signed URL media.
- **Highlight/Note** gắn theo anchor (selector/offset) bền vững qua version (lưu text_snapshot).
- **Versioning:** hiển thị bản published mới nhất; highlight cũ cố gắng re-anchor.
- **Reading progress** lưu để "tiếp tục đọc".
- **Recommendation:** bài liên quan theo topic + hành vi.

## 6. Database
- `articles`, `media`, `content_links`, `highlights`, `notes`, `bookmarks`, `reading_progress(user_id,article_id,percent,updated_at)`.
- Search → Meilisearch (title, body, synonyms y khoa).

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/library` | cây chủ đề + featured | Auth (gated) |
| GET | `/api/v1/library/{slug}` | article + crosslinks (gated body) | Auth |
| GET | `/api/v1/library/search?q=` | kết quả Meili | Auth |
| POST | `/api/v1/library/{id}/progress` | lưu % đọc | Owner |

Lỗi: `SUBSCRIPTION_REQUIRED` khi vượt preview; `404` retired.

## 8. State Management
- **Server:** article render cache (Redis) theo version; search cache.
- **Client:** TOC active, font size (localStorage), selection.
- **Optimistic:** highlight/bookmark.
- **Realtime:** không.

## 9. Phân quyền
- Free: preview. Premium: full. Editor: xem draft/preview. Xem RBAC.

## 10. Edge Cases
| Case | Xử lý |
|------|-------|
| Bài retired | 410 + gợi ý bài thay thế |
| Media Premium hết hạn | Signed URL từ chối → blur + upgrade |
| Highlight mất anchor sau update | Re-anchor bằng text_snapshot; nếu fail → note nổi |
| Offline | Bài đã đọc cache (SW) |
| Refresh | Giữ vị trí đọc |

## 11. Tracking
`library_open`, `article_read`, `article_scroll_complete`, `highlight_create`, `note_create`, `bookmark_add`, `crosslink_click`, `library_search`, `paywall_view`.

## 12. Responsive
- Desktop: TOC trái + nội dung + aside cross-link. Tablet: TOC drawer. Mobile: nội dung full, TOC bottom sheet, toolbar overflow.

## 13. Security
- Sanitize rich content (XSS); signed URL media Premium; scope highlight/note theo owner; chống scraping (rate limit, watermark ảnh).

## 14. Performance
- Cache render article; lazy ảnh/nặng; TOC sticky nhẹ; search debounce + cache; CDN media.

## 15. Đề xuất cải tiến
- Chế độ đọc tập trung + text-to-speech.
- "Question from this section": tạo câu hỏi từ đoạn đang đọc.
- Cá nhân hóa: đánh dấu nội dung liên quan chủ đề yếu.
- Offline download bộ bài (Premium).
