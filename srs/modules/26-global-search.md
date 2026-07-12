# Module 26 — Global Search

**Nhóm:** Discovery · **Ưu tiên:** Cao · **Phụ thuộc:** Search (25), tất cả module nội dung · **Trạng thái:** ✅

## 0. Tóm tắt module
Tìm kiếm toàn hệ thống (command palette): câu hỏi, bài viết, bệnh, thuốc, thủ thuật, hình ảnh, video, flashcard, note của user. Truy cập nhanh bằng phím tắt (Cmd/Ctrl+K). Kết quả nhóm theo loại + điều hướng nhanh.

| Route | Màn hình |
|-------|----------|
| Overlay toàn cục (Cmd/Ctrl+K) | Mọi trang |
| `/search?q=` | Trang kết quả đầy đủ |

## 1. Tổng quan
- **Mục đích:** Cổng truy cập nhanh mọi nội dung + hành động (navigation + actions).
- **Đến từ:** Header search, phím tắt, mọi nơi.
- **Đi sang:** Bất kỳ nội dung/hành động nào.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Command palette overlay** | Input + kết quả nhóm + phím điều hướng | Cmd/Ctrl+K | Full-screen mobile |
| **Grouped results** | Nhóm: Câu hỏi/Bài viết/Bệnh/Thuốc/Ảnh/Video/Của tôi/Hành động | Sau gõ | Cuộn |
| **Quick actions** | "Tạo session", "Ôn flashcard", "Đi tới Dashboard" | Luôn | — |
| **Recent & suggestions** | Khi trống | Trống | — |
| **Filters (scope tabs)** | Lọc theo loại | Có kết quả | Chips |
| **Empty/Loading/Error** | Không kết quả; skeleton; retry | Theo trạng thái | — |

## 3. Phân tích Component
- `CommandPalette` (props open, onClose; state query, activeIndex, results; keyboard ↑↓ Enter Esc; a11y combobox/listbox ARIA), `ResultGroup`, `QuickActionList`.

## 4. Luồng người dùng
```
Bất kỳ đâu → Cmd+K → gõ "nhồi máu" → nhóm Bệnh/Câu hỏi/Ảnh → Enter mở
Gõ ">" → chế độ hành động (command) → chọn "Tạo session ngẫu nhiên".
```

## 5. Business Logic
- **Federated search** đa index Meili (multi-search) + kết quả cá nhân (notes/flashcards owner).
- **Ranking** trộn theo độ liên quan + loại ưu tiên + hành vi.
- **Actions mode** (prefix `>`): lệnh điều hướng/thao tác.
- **Gating:** nội dung Premium hiển thị + khóa.
- Không lộ đáp án câu.

## 6. Database
- Meili multi-index; owner-scope cho note/flashcard; `search_history`.

## 7. API
| Method | URL | Query | Response | Quyền |
|--------|-----|-------|----------|-------|
| GET | `/api/v1/search/global?q=` | scope?, limit | grouped results | Auth |
| GET | `/api/v1/search/global/suggest?q=` | — | suggestions | Auth |

## 8. State Management
- Client palette state; server multi-search + cache; debounce; không realtime.

## 9. Phân quyền
- Mọi user; kết quả cá nhân chỉ của owner; gating nội dung; admin có scope quản trị (tùy chọn tách palette admin).

## 10. Edge Cases
- Query ngắn (<2 ký tự) → gợi ý; không kết quả → gợi ý; Meili down → fallback; kết quả nhiều → giới hạn/nhóm; phím tắt xung đột → cấu hình.

## 11. Tracking
`global_search_open`, `search`, `search_result_click`, `quick_action_run`, `search_no_result`.

## 12. Responsive
- Desktop: overlay giữa màn. Mobile: full-screen, bàn phím tự mở, nhóm cuộn.

## 13. Security
- Owner-scope dữ liệu cá nhân; không lộ Premium/đáp án; sanitize; rate limit; ẩn nội dung theo quyền.

## 14. Performance
- Meili multi-search 1 round-trip; debounce; cache phổ biến; giới hạn kết quả/nhóm; prefetch điều hướng.

## 15. Đề xuất cải tiến
- Semantic + hỏi đáp AI ngay trong palette; "câu lệnh" mạnh hơn (jump to setting); lịch sử thông minh; tìm bằng hình ảnh (upload → nhận diện).
