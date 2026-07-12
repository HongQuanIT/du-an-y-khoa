# Module 13 — Images (Atlas hình ảnh y khoa)

**Nhóm:** Content · **Ưu tiên:** Trung bình · **Phụ thuộc:** Media Management (37), Library (09) · **Trạng thái:** ✅

## 0. Tóm tắt module
Thư viện hình ảnh y khoa (X-quang, CT, mô bệnh học, lâm sàng...) với viewer zoom cao, chú thích (annotation), cross-link tới bệnh/câu hỏi. Ảnh Premium bảo vệ bằng signed URL + watermark.

| Route | Màn hình |
|-------|----------|
| `/images` | Duyệt/tìm ảnh |
| `/images/{id}` | Xem chi tiết (lightbox/viewer) |

## 1. Tổng quan
- **Mục đích:** Học nhận diện hình ảnh, tra cứu atlas.
- **Đến từ:** Library/bệnh, câu hỏi (ảnh đính kèm), search.
- **Đi sang:** Bệnh liên quan, câu hỏi dùng ảnh.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Image grid (masonry)** | Duyệt ảnh + filter loại | `/images` | Cột co giãn |
| **Filter/Search** | Theo modality, hệ cơ quan, bệnh | Luôn | Sticky |
| **Image viewer** | Zoom/pan, annotation toggle, so sánh normal/abnormal | `/images/{id}` | Full-screen |
| **Annotation layer** | Chú thích vùng (hotspot) | Toggle | — |
| **Caption/credit** | Mô tả + nguồn | Viewer | — |
| **Toolbar** | Bookmark, download (nếu phép), AI, fullscreen | Viewer | Overflow |
| **Cross-link** | Bệnh/câu hỏi | Aside | — |
| **Paywall/Empty/Loading/Error** | Chuẩn | Theo trạng thái | Skeleton |

## 3. Phân tích Component
### `ImageViewer`
- **Props:** `media(variants, annotations)`, `isPremium`, `canDownload`.
- **State:** `zoom`, `pan`, `showAnnotations`, `compareMode`.
- **Events:** `onZoom`, `onToggleAnnotation`, `onBookmark`, `onDownload`.
- **Permission:** download Premium; signed URL.
- **A11y:** alt text bắt buộc; annotation có mô tả; điều khiển bàn phím.
- **Loading:** blur-up placeholder; **Error:** retry; **Empty:** N/A.

### `ImageGrid` (masonry, infinite scroll), `AnnotationHotspot`, `CompareSlider`.

## 4. Luồng người dùng
```
Câu hỏi có ảnh → mở viewer → zoom xem chi tiết → bật annotation → cross-link bệnh
/images → filter X-quang ngực → mở → so sánh bình thường/bất thường
```

## 5. Business Logic
- **Annotation** lưu vùng (JSON toạ độ) + nhãn; toggle hiển thị.
- **Variants:** thumbnail/webp/full; chọn theo thiết bị & zoom.
- **Gating:** ảnh Premium signed URL + watermark; download giới hạn.
- **Cross-link** tới bệnh/câu hỏi.

## 6. Database
- `media(type=image, variants, annotations JSON, alt, caption, credit, is_premium)`, `content_links`.
- Tìm kiếm: metadata + tag → Meilisearch.

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/images?filter=` | grid (thumbnail) | Auth |
| GET | `/api/v1/images/{id}` | detail + signed URL variants | Auth (gated) |
| POST | `/api/v1/images/{id}/download` | signed download (nếu phép) | Premium |

## 8. State Management
- Grid infinite scroll (cursor); viewer state client; CDN cache thumbnail; signed URL TTL ngắn.

## 9. Phân quyền
- Free: thumbnail + ảnh free. Premium: full res + download. Editor/Media manager: quản lý (module 37).

## 10. Edge Cases
- Ảnh processing chưa xong → placeholder; signed URL hết hạn → refresh; ảnh lớn mạng chậm → progressive load; retired → ẩn.

## 11. Tracking
`image_view`, `image_zoom`, `annotation_toggle`, `image_download`, `image_compare`, `crosslink_click`.

## 12. Responsive
- Desktop: grid nhiều cột + viewer lớn. Mobile: 2 cột, viewer full-screen, pinch-zoom.

## 13. Security
- Signed URL + watermark chống scraping; giới hạn download; alt/credit đầy đủ; không lộ full-res cho Free.

## 14. Performance
- Thumbnail webp + lazy + blur-up; CDN; progressive/tiled cho ảnh siêu lớn; infinite scroll.

## 15. Đề xuất cải tiến
- DICOM-like viewer (window/level) cho ảnh CĐHA.
- Quiz "nhận diện hình ảnh".
- So sánh nhiều ảnh cùng lúc; annotation cộng đồng có kiểm duyệt.
