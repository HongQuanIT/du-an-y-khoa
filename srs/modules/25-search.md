# Module 25 — Search (Tìm kiếm trong ngữ cảnh)

**Nhóm:** Discovery · **Ưu tiên:** Trung bình · **Phụ thuộc:** Global Search (26), Library (09), Qbank (05) · **Trạng thái:** ✅

## 0. Tóm tắt module
Tìm kiếm phạm vi trong một khu vực (vd tìm trong Library, tìm trong Qbank, tìm trong Notes). Khác Global Search (26) tìm toàn hệ thống. Dựa trên Meilisearch với facet/filter theo ngữ cảnh.

| Route | Màn hình |
|-------|----------|
| `/library/search`, `/qbank?q=`, `/notes?q=` ... | Search theo ngữ cảnh |

## 1. Tổng quan
- **Mục đích:** Tìm nhanh nội dung trong khu vực đang dùng.
- **Đến từ:** Ô search trong header khu vực.
- **Đi sang:** Nội dung khớp.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Search input** | Nhập từ khóa (debounce) | Luôn | Sticky |
| **Suggestions dropdown** | Gợi ý tức thời (typeahead) | Khi gõ | Full-width mobile |
| **Facet filters** | Lọc theo loại/chủ đề/độ khó (tùy khu vực) | Có kết quả | Bottom sheet |
| **Result list** | Kết quả + highlight từ khóa | Sau tìm | List |
| **Recent/Popular** | Lịch sử & phổ biến | Khi trống | — |
| **Empty/Loading/Error** | Không kết quả; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
- `SearchInput` (debounce, clear, voice tùy chọn), `SuggestionDropdown`, `FacetPanel`, `ResultItem`(highlight), `RecentSearches`.

## 4. Luồng người dùng
```
Library → gõ "viêm phổi" → gợi ý → chọn → mở bài
Qbank → gõ từ khóa → lọc kết quả câu (không lộ đáp án) → tạo session.
```

## 5. Business Logic
- **Scope theo khu vực** (index/filter cố định).
- **Typeahead** từ Meili; **synonyms y khoa** (viêm phổi ~ pneumonia).
- **Gating:** kết quả Premium hiển thị nhưng khóa (paywall) hoặc ẩn tùy chính sách.
- **Recent** lưu client/server; **popular** từ tracking.
- Không lộ đáp án câu trong kết quả.

## 6. Database
- Index Meilisearch theo loại; đọc `tracking_events` cho popular; `search_history(user_id, query, scope, created_at)`.

## 7. API
| Method | URL | Query | Response | Quyền |
|--------|-----|-------|----------|-------|
| GET | `/api/v1/search?scope=library&q=` | facet, page | results + facets | Auth |
| GET | `/api/v1/search/suggest?scope=&q=` | — | suggestions | Auth |

## 8. State Management
- Client input/debounce; server Meili + cache truy vấn phổ biến; pagination/infinite; không realtime.

## 9. Phân quyền
- Theo khu vực & gating nội dung. Editor thấy draft trong khu vực quản lý.

## 10. Edge Cases
- Không kết quả → gợi ý sửa/nới; typo → fuzzy; query rỗng → recent/popular; ký tự đặc biệt → sanitize; Meili down → fallback DB LIKE + thông báo.

## 11. Tracking
`search`, `search_suggest`, `search_result_click`, `search_no_result`, `facet_apply`.

## 12. Responsive
- Desktop: dropdown + facet bên. Mobile: search full-screen overlay, facet bottom sheet.

## 13. Security
- Sanitize query; không lộ nội dung Premium/đáp án; scope theo quyền; rate limit.

## 14. Performance
- Meili nhanh + cache; debounce; typeahead giới hạn; index đồng bộ qua queue.

## 15. Đề xuất cải tiến
- Semantic search (embedding) hiểu ý; tìm bằng triệu chứng; lịch sử & lưu tìm kiếm; voice search.
