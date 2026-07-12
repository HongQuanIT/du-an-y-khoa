# Module 37 — Media Management (Admin)

**Nhóm:** Admin · **Ưu tiên:** Cao · **Phụ thuộc:** Images (13), Videos (14), Storage/CDN, RBAC (39) · **Trạng thái:** ✅

## 0. Tóm tắt module
Quản lý tài nguyên media tập trung (ảnh/video/audio/tài liệu): upload, xử lý (biến thể/transcode), metadata (alt/caption/credit), gắn quyền Premium, thư viện tái sử dụng, dọn rác.

| Route | Màn hình |
|-------|----------|
| `/admin/media` | Thư viện media |
| `/admin/media/upload` | Upload |
| `/admin/media/{id}` | Chi tiết media |

## 1. Tổng quan
- **Mục đích:** Nguồn media dùng chung cho câu hỏi & thư viện.
- **Đến từ:** Admin, MediaPicker trong editor.
- **Đi sang:** Gắn vào câu hỏi/bài viết.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Media grid** | Duyệt + filter type/status/premium, search | List | Grid |
| **Uploader** | Kéo-thả, đa file, progress, chunk | `/upload` | — |
| **Media detail** | Preview, metadata form, biến thể, usage (nơi dùng), annotation | `/{id}` | Tabs |
| **Bulk actions** | Xóa/gắn tag/premium | Grid | — |
| **Processing badge** | Trạng thái transcode/variant | Item | — |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `MediaGrid`, `Uploader`(chunk, validate mime/size), `MediaDetail`, `MetadataForm`(alt bắt buộc), `AnnotationEditor`(ảnh), `UsageList`, `VariantList`.

## 4. Luồng người dùng
```
Upload ảnh → job sinh thumbnail/webp → ready → điền alt/caption/credit → gắn premium
Editor mở MediaPicker → chọn/upload → chèn vào bài/câu.
Video → transcode HLS (job) → chapters/subtitle → ready.
```

## 5. Business Logic
- **Xử lý async:** ảnh → biến thể (thumbnail/webp/avif); video → HLS transcode; audio; document.
- **Status:** processing→ready/failed; chặn dùng khi chưa ready.
- **Premium media:** signed URL + watermark.
- **Usage tracking:** biết media dùng ở đâu (chống xóa nhầm).
- **Alt/credit** bắt buộc (a11y + bản quyền y khoa).
- **Dọn rác:** media không dùng → đề xuất xóa.

## 6. Database
- `media` (mục 6 data model), `media_usages(media_id, usable_type, usable_id)`, `media_jobs(media_id, type, status)`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| GET | `/api/v1/admin/media` | filter | list | `media.view` |
| POST | `/api/v1/admin/media` | file (chunk) | media(processing) | `media.manage` |
| PATCH | `/api/v1/admin/media/{id}` | metadata | media | `media.manage` |
| DELETE | `/api/v1/admin/media/{id}` | — | 204 (nếu không dùng) | `media.manage` |
| GET | `/api/v1/admin/media/{id}/usages` | — | usages | `media.view` |

## 8. State Management
- Upload progress client; processing status realtime (Reverb/poll); grid infinite scroll; jobs qua queue.

## 9. Phân quyền
- Media manager/Editor/Admin. Xem RBAC.

## 10. Edge Cases
- Transcode fail → retry/thông báo; xóa media đang dùng → chặn/cảnh báo; file quá lớn → chunk/giới hạn; mime giả mạo → verify thật; signed URL hết hạn → refresh.

## 11. Tracking (audit + product)
`media_upload`, `media_process_complete`, `media_update`, `media_delete`, `media_attach`.

## 12. Responsive
- Desktop tối ưu; mobile xem/gắn nhanh.

## 13. Security
- Verify mime thật, quét malware, tên ngẫu nhiên, lưu ngoài webroot; signed URL + watermark cho Premium; RBAC; audit; giới hạn size/loại; chống SSRF khi import từ URL.

## 14. Performance
- Transcode/variant qua queue; CDN; upload chunk resumable; lazy grid; infinite scroll.

## 15. Đề xuất cải tiến
- Tự sinh alt bằng AI (kiểm duyệt); nhận diện trùng ảnh; DICOM support; CDN đa vùng; nén thông minh; annotation cộng tác.
