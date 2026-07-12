# Module 42 — CMS (Quản lý nội dung tĩnh & marketing)

**Nhóm:** Admin · **Ưu tiên:** Trung bình · **Phụ thuộc:** Landing (01), Media (37), RBAC (39) · **Trạng thái:** ✅

## 0. Tóm tắt module
Quản lý trang tĩnh & marketing: landing sections, pricing copy, blog, FAQ, trang pháp lý, banner/thông báo hệ thống, i18n. Cho phép chỉnh nội dung không cần deploy.

| Route | Màn hình |
|-------|----------|
| `/admin/cms/pages` | Trang tĩnh |
| `/admin/cms/blog` | Bài blog |
| `/admin/cms/faq` | FAQ |
| `/admin/cms/banners` | Banner/thông báo |
| `/admin/cms/menus` | Menu/navigation |

## 1. Tổng quan
- **Mục đích:** Đội marketing/nội dung tự chủ chỉnh trang công khai.
- **Đến từ:** Admin dashboard.
- **Đi sang:** Preview trang public.

## 2. Phân tích giao diện
| Thành phần | Chức năng | Hiển thị/Ẩn | Responsive |
|-----------|-----------|-------------|-----------|
| **Page list** | Danh sách trang/blog/faq + trạng thái | List | Table |
| **Block editor** | Chỉnh section (hero, feature, CTA...) dạng block | Editor | — |
| **Rich editor** | Blog/FAQ nội dung | Editor | Full |
| **SEO panel** | Meta title/description, OG image, slug | Editor | — |
| **i18n tabs** | Nội dung VI/EN | Editor | — |
| **Banner manager** | Bật/tắt banner, lịch hiển thị, target | `/banners` | — |
| **Publish/schedule** | Xuất bản/hẹn giờ | Editor | — |
| **Preview** | Xem trang thật | Editor | Split |
| **Empty/Loading/Error** | Chuẩn | Theo trạng thái | — |

## 3. Phân tích Component
- `PageList`, `BlockEditor`(kéo-thả section), `RichEditor`, `SeoPanel`, `LocaleTabs`, `BannerManager`(schedule/target), `MenuBuilder`, `CmsPreview`.

## 4. Luồng người dùng
```
Marketing → sửa hero landing (block) → cập nhật CTA → SEO → preview → publish (hoặc hẹn giờ)
Tạo banner khuyến mãi → target Free users → lịch 1 tuần → auto tắt.
```

## 5. Business Logic
- **Block-based** cho landing (linh hoạt, an toàn); rich cho blog/faq.
- **i18n:** nội dung theo locale; fallback VI.
- **Publish/schedule/versioning**; cache invalidate + CDN purge khi publish.
- **Banner targeting:** theo role/segment/lịch.
- **SEO:** slug, meta, sitemap tự cập nhật.

## 6. Database
- `cms_pages(id, type, slug, blocks JSON, status, locale, seo JSON, published_at)`, `cms_versions`, `blog_posts` (có thể dùng `articles(type=general)`), `faqs(id, question, answer, order, locale)`, `banners(id, content, target JSON, schedule, enabled)`, `menus(id, items JSON)`.

## 7. API
| Method | URL | Payload | Response | Quyền |
|--------|-----|---------|----------|-------|
| CRUD | `/api/v1/admin/cms/pages` | page | page | `cms.manage` |
| POST | `/api/v1/admin/cms/pages/{id}/publish` | — | published | `cms.manage` |
| CRUD | `/api/v1/admin/cms/blog` | post | post | `cms.manage` |
| CRUD | `/api/v1/admin/cms/faq` | faq | faq | `cms.manage` |
| CRUD | `/api/v1/admin/cms/banners` | banner | banner | `cms.manage` |

Public đọc qua `/api/v1/public/content/{slug}` (module 01), cache mạnh.

## 8. State Management
- Autosave block; version; publish → invalidate cache/CDN; preview draft; schedule qua cron.

## 9. Phân quyền
- `cms.manage` (marketing/admin). Xem RBAC.

## 10. Edge Cases
- Publish nội dung thiếu locale → fallback + cảnh báo; slug trùng → 409; banner lịch chồng → ưu tiên; block lỗi cấu trúc → validate; ảnh media chưa ready → chặn publish.

## 11. Tracking
`cms_edit`, `cms_publish`, `banner_toggle`, `blog_publish`, `cms_preview`. (Public: `landing_view`, `blog_read` — module 01.)

## 12. Responsive
- Desktop tối ưu (block editor); mobile hạn chế.

## 13. Security
- Sanitize rich content (XSS); RBAC; audit; kiểm soát embed/URL; không cho script tùy ý; CDN purge an toàn.

## 14. Performance
- Public content cache mạnh + CDN; publish purge; block render tối ưu; ảnh CDN.

## 15. Đề xuất cải tiến
- A/B testing landing tích hợp; personalization theo nguồn traffic; template landing tái dùng; xem trước đa thiết bị; lịch biên tập; AI viết/dịch nội dung marketing.
