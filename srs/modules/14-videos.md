# Module 14 — Videos

**Nhóm:** Content · **Ưu tiên:** Trung bình · **Phụ thuộc:** Media Management (37), Library (09), Procedures (12) · **Trạng thái:** ✅

## 0. Tóm tắt module
Thư viện video bài giảng/hướng dẫn thủ thuật với player hỗ trợ chapter, tốc độ, phụ đề, ghi chú theo timestamp, theo dõi tiến độ xem. Streaming HLS, bảo vệ nội dung Premium.

| Route | Màn hình |
|-------|----------|
| `/videos` | Duyệt video |
| `/videos/{id}` | Trang xem video |

## 1. Tổng quan
- **Mục đích:** Học qua video, xem hướng dẫn thủ thuật.
- **Đến từ:** Library/thủ thuật, sidebar, search, study plan task.
- **Đi sang:** Bài viết/câu hỏi liên quan, video kế tiếp (playlist).

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Video grid** | Duyệt + filter chủ đề + thời lượng | `/videos` | Grid → list |
| **Video player** | Play/pause, seek, speed, quality, subtitle, fullscreen, PiP | `/videos/{id}` | Full-width |
| **Chapters** | Danh sách chương, nhảy timestamp | Bên player | Dưới mobile |
| **Timestamp notes** | Ghi chú gắn thời điểm | Panel | Drawer |
| **Playlist/Related** | Video tiếp theo | Aside | Dưới mobile |
| **Transcript** | Bản chép + tìm trong video | Tab | — |
| **Progress marker** | Đã xem đến đâu | Trên thumbnail/player | — |
| **Paywall/Empty/Loading/Error** | Chuẩn | Theo trạng thái | Skeleton |

## 3. Phân tích Component
### `VideoPlayer`
- **Props:** `media(hls, chapters, subtitles)`, `startAt`, `isPremium`.
- **State:** `playing`, `currentTime`, `speed`, `quality`, `captionOn`.
- **Events:** `onPlay/onPause/onSeek/onEnded/onChapter`, `onTimeUpdate(save progress)`.
- **Permission:** Premium → signed HLS; Free preview đoạn ngắn.
- **A11y:** keyboard controls, captions, ARIA cho control.
- **Loading:** spinner buffering; **Error:** retry/quality fallback; **Empty:** N/A.

### `ChapterList`, `TimestampNotePanel`, `TranscriptSearch`, `VideoGrid`.

## 4. Luồng người dùng
```
Thủ thuật → video hướng dẫn → xem theo chapter → ghi chú tại 03:12 → tiếp video liên quan
Study plan task "xem video" → mở → tự đánh dấu done khi xem ≥ ngưỡng
Free: xem preview → paywall.
```

## 5. Business Logic
- **Progress tracking:** lưu `currentTime`/percent định kỳ; "tiếp tục xem"; đánh dấu completed khi ≥ 90%.
- **Chapters/subtitles/transcript** từ metadata media.
- **Gating:** Premium signed HLS; Free preview.
- **Timestamp notes** gắn giây → `notes` với anchor time.

## 6. Database
- `media(type=video, variants=hls, duration, subtitles JSON, chapters JSON)`, `video_progress(user_id, media_id, position, percent, completed, updated_at)`, `notes`(anchor time), `content_links`.

## 7. API
| Method | URL | Response | Quyền |
|--------|-----|----------|-------|
| GET | `/api/v1/videos?filter=` | grid | Auth |
| GET | `/api/v1/videos/{id}` | detail + signed HLS + chapters | Auth (gated) |
| POST | `/api/v1/videos/{id}/progress` | `{position,percent}` | Owner |

## 8. State Management
- Player state client; progress lưu throttled; signed URL TTL; CDN HLS segments.

## 9. Phân quyền
- Free preview; Premium full. Media manager quản lý (module 37).

## 10. Edge Cases
- Video đang transcode → "đang xử lý"; mạng chậm → adaptive bitrate; signed URL hết hạn giữa chừng → refresh; retired → ẩn; offline → chỉ video đã tải (nếu hỗ trợ).

## 11. Tracking
`video_open`, `video_play`, `video_pause`, `video_seek`, `video_complete`, `chapter_jump`, `timestamp_note_create`, `paywall_view`.

## 12. Responsive
- Desktop: player lớn + chapter/related bên. Mobile: player full-width, PiP, chapter/transcript tab dưới.

## 13. Security
- Signed HLS + token per segment; chống tải xuống trái phép; DRM tùy chọn; watermark động (user id) để răn đe chia sẻ.

## 14. Performance
- HLS adaptive bitrate; CDN; preload metadata; transcode qua queue; lazy grid.

## 15. Đề xuất cải tiến
- Tốc độ ghi nhớ theo người dùng; tóm tắt AI video; câu hỏi chèn giữa video (interactive); đồng bộ tiến độ đa thiết bị.
