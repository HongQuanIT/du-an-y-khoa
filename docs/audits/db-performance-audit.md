# Database performance audit

Laravel modular monolith · static code review · **2026-08-16** · không chạy EXPLAIN trên production.

> **Phạm vi:** Chỉ audit/report — chưa sửa code. Một số mục đánh dấu **NEED VERIFICATION** khi thiếu query log / EXPLAIN runtime.

## Tóm tắt

| Metric | Số |
| --- | ---: |
| Tổng issues | 24 |
| CRITICAL | 2 |
| HIGH | 12 |
| MEDIUM | 7 |
| LOW | 3 |

### Phân loại kỹ thuật

| Loại | Số |
| --- | ---: |
| N+1 / lazy / ignored eager | 6 |
| Query dư thừa / lặp | 5 |
| Full scan / WHERE DATE | 3 |
| Index gaps (đề xuất) | 2 |
| Pagination / unbounded get | 2 |
| Race / exists-then-create | 3 |
| Accessor/Resource DB hit | 0 |

Source: static audit `Modules/*` + `app/*`.

## Top 10 optimizations

Xếp theo impact × query reduction × scale risk × độ an toàn khi sửa.

| # | Issue | Severity | Est. queries | Why first |
| ---: | --- | --- | --- | --- |
| 1 | Community attempts unbounded | CRITICAL | 1 huge set | Scale theo toàn platform |
| 2 | Complete session N×exists/status | CRITICAL | 1+3N in TX | Lock + exam size |
| 3 | Study plan detail write-on-read | HIGH | ~5/view | Mọi pageview ghi DB |
| 4 | Reminder job 1+N | HIGH | 1+3N | Nightly fan-out |
| 5 | memberFor ignore eager load | HIGH | 2–6/req | Hot classroom path |
| 6 | Banner subscription N× | HIGH | 1+N | Landing mỗi hit |
| 7 | Snapshot N×updateOrCreate | HIGH | 1+N | Start session TX |
| 8 | MediaUsage N×firstOrCreate | HIGH | 1+2N | CMS save nhiều ảnh |
| 9 | Invoice count+1 race | HIGH | race | Billing correctness |
| 10 | whereDate → index | MEDIUM | scan | Dễ sửa, lợi ngay |

## Bottleneck tables khi dữ liệu tăng

| Bảng | Quy mô rủi ro | Query nóng | Index hiện có | Ghi chú |
| --- | --- | --- | --- | --- |
| `question_attempts` | 1M–10M+ | `whereIn(question_id)` community overview | `session_id`; `(user_id,question_id)`; FK `question_id` | Thiếu aggregate/peer table; ORDER BY id trên tập lớn |
| `question_statuses` | 100k–1M+ | Complete session sync per question | unique`(user,question)`; `(user,status)` | Batch upsert thay N save |
| `study_plan_tasks` | 100k–1M+ | `whereDate` + reminder join | `(plan_id,date)`; `(plan_id,status)` | `whereDate` phá index date |
| `media` + `media_usages` | 10k–100k+ | `LIKE %`; `firstOrCreate` loop | `(type,status)`; usages unique | Search + write path |
| `billing_invoices` | 10k–100k+ | `LIKE 'INV-year-%'` count | unique`(number)` | Cần counter, không COUNT |
| `classroom_members` | 10k–100k+ | `memberFor` mỗi request | NEED VERIFICATION FK indexes | Dùng relation đã load |

### Index đề xuất (chỉ sau khi xem query pattern)

1. Peer stats / covering cho attempts theo `question_id` + `is_correct` + `id` — hoặc bảng aggregate.
2. Tránh `whereDate`: dùng so sánh date trực tiếp trên `(study_plan_id, date)`. Không thêm index máy móc cho `LIKE %term%`.

## Chi tiết findings

| ID | Severity | Issue | File | Queries | Fix |
| ---: | --- | --- | --- | --- | --- |
| 1 | CRITICAL | Unbounded community attempts load | `Modules/QuestionBank/app/Services/QuestionSessionInsights.php:213–217` | 1 query → có thể 10k–100k+ rows khi platform lớn | Aggregate SQL (window/latest per user+question) hoặc bảng peer-stats; giới hạn thời gian |
| 2 | CRITICAL | Complete session: N×exists + N×status trong transaction | `Modules/QuestionBank/app/Actions/CompleteQuestionSessionAction.php:41–118, 195–198` | 1 + ~3N (N = số câu; exam 100 câu ≈ 300 query trong TX) | `whereIn` existence 1 lần; batch-load `question_statuses`; upsert chunk; tách rollup khỏi TX nếu được |
| 3 | HIGH | Study Plan detail: write-on-read + tasks ×3 | `Modules/StudyPlan/app/Http/Controllers/StudyPlanDetailController.php:31–41` | 1 UPDATE overdue + 1 SELECT tasks + 1 UPDATE plan + 2× SELECT tasks | Recalc chỉ khi overdue đổi; truyền 1 collection tasks vào timeline helpers |
| 4 | HIGH | Study-plan reminder job: 1+N per user | `Modules/Notification/app/Actions/SendStudyPlanReminderEmailsAction.php:27–60` | 1 + ~3N (+ mail) | `whereIn` users; 1 query reminder logs; 1 query tasks `groupBy user_id` + `with(plan)` |
| 5 | HIGH | Classroom `memberFor` luôn query lại | `Modules/Classroom/app/Models/Classroom.php:157–177` | 2–6 membership lookups / request (show + policy + live) | Ưu tiên collection đã load; memoize member trên instance |
| 6 | HIGH | ActiveBanners gọi `CurrentSubscription` trong filter | `Modules/Admin/app/Support/Cms/ActiveBanners.php:72–80` | 1 banners + N subscription stacks | Resolve subscription 1 lần trước filter |
| 7 | HIGH | Profile resolve subscription 2 lần | `Modules/Auth/app/Http/Controllers/ProfileController.php:57–58` | 2× cùng subscription query | Gọi 1 lần; derive membership; memoize trên User |
| 8 | HIGH | `User::entitlements()` không memoize | `app/Models/User.php:82–107` | ~(2–3) × số lần gọi trong request | Request-level cache trên User instance |
| 9 | HIGH | Landing CMS: fetch page ×2 + Media hydrate ×2 | `Modules/Landing/app/Http/Controllers/LandingController.php:111–132` | 2× CmsPage + 2× Media `whereIn` (ID chồng) | Pass `$page` vào content; gộp media IDs 1 `whereIn` |
| 10 | HIGH | SyncMediaUsages: N×`firstOrCreate` | `Modules/Media/app/Actions/SyncMediaUsagesAction.php:35–41` | 1 delete + ~2N | Diff IDs hiện có; upsert/insert bulk |
| 11 | HIGH | Session snapshot: N×`updateOrCreate` | `Modules/QuestionBank/app/Services/QuestionSessionSnapshots.php:29–43` | 1 (questions) + N upserts | Bulk upsert / insert after delete-missing |
| 12 | HIGH | Invoice number: count+1 race | `Modules/Billing/app/Actions/RedeemCodeAction.php:131–135` | 1 COUNT scan prefix + race | Counter row `lockForUpdate` / sequence / unique+retry |
| 13 | HIGH | StartLiveSession: exists-then-update race | `Modules/Classroom/app/Actions/StartLiveSessionAction.php:40–56` | Race condition | TX + `lockForUpdate`; hoặc unique partial “one Live/classroom” |
| 14 | HIGH | Bookmark folder ensure: exists-then-create | QuestionBank + Personalization bookmark controllers | Race → exception; thừa SELECT | `firstOrCreate` / upsert trên unique |
| 15 | MEDIUM | `whereDate()` trên cột date đã index | StudyPlan models/actions + reminder job | Full/index-less scan trên tasks của plan | `where('date', $today)` / `where('date', '<', $today)` |
| 16 | MEDIUM | Media admin `LIKE %term%` + 3 COUNT | `Modules/Media/app/Http/Controllers/MediaController.php:35–58` | 3 COUNT + 1 search scan | Scout/FTS; prefix search; 1 conditional aggregate / cache stats |
| 17 | MEDIUM | Search DB fallback `%term%` trên text lớn | GlobalSearchService / QbankSearchService / QuestionRepository | Full table scan + 3 aggregate | Rate-limit/cache degraded; FULLTEXT nếu giữ fallback. **NEED VERIFICATION:** chỉ nóng khi Scout null/fail |
| 18 | MEDIUM | Unbounded `get()` một số list | Teach/Classroom index, FAQ, Profile invoices | 1 unbounded result set | `paginate` / `limit(50)`; FAQ OK khi nhỏ |
| 19 | MEDIUM | TX rộng + Replan per-task save | Complete/Create session, ReplanStudyPlanAction | N UPDATEs trong TX | Batch update; thu hẹp lock scope |
| 20 | MEDIUM | CMS catalog `syncCatalog` mỗi index | `CmsPage::syncCatalog` / `Menu::syncCatalog` | 1 SELECT+optional INSERT × số key | Chỉ seed/migrate; flag “catalog synced” |
| 21 | MEDIUM | QBank history with all attempts | QuestionBankPageController ~52–54 | 1 + 1 (attempts lớn) | `withCount` / cột summary nếu UI chỉ cần aggregate. **NEED VERIFICATION** theo Blade history |
| 22 | LOW | Faq/catalog nhỏ không paginate | Landing FAQ | 1 | Theo dõi khi FAQ > vài trăm |
| 23 | LOW | `SELECT *` mặc định paginate | Nhiều list admin | 1 nhưng wide rows | `select` cột cần trên hot path |
| 24 | LOW | ResolvedMenu cold `syncCatalog` | ResolvedMenu | `firstOrCreate` × menu keys | Seeder only |

### CRITICAL / HIGH — mô tả đầy đủ

**#1 Unbounded community attempts**  
`QuestionSessionInsights::questionOverview` load mọi attempt đã chấm theo `question_ids` rồi dedupe PHP — scale theo toàn platform. Aggregate SQL hoặc peer-stats table.

**#2 Complete session N×exists + N×status trong TX**  
`CompleteQuestionSessionAction` lock session rồi mỗi câu exists + sync status (~1+3N). Batch `whereIn` + upsert; thu hẹp transaction.

**#3–8 Study plan / reminder / classroom / banners / entitlements**  
Detail write-on-read + tasks×3; reminder 1+N; `memberFor` bỏ qua eager load; banners `CurrentSubscription` trong filter; profile subscription×2; entitlements không memoize.

**#9–14 Landing hydrate / MediaUsage / snapshots / races**  
Landing CmsPage+Media hydrate trùng; SyncMediaUsages N×`firstOrCreate`; snapshot N×`updateOrCreate`; invoice count+1 race; StartLiveSession race; bookmark ensure race.

## Đã làm tốt (tránh false positive)

| Khu vực | Pattern |
| --- | --- |
| Classroom index/show | `with(host, sessions…)` + `withCount(activeMembers)` |
| Live messages/hands | `with(user)` / `loadMissing(user)` |
| Bookmarks page | batch `Question::with(topic,options)->whereIn` |
| Session snapshots load | `with(options,topic)` + `relationLoaded` guards |
| Billing CurrentSubscription | `with(plan, planPrice)` |
| StudyPlanResource | `whenLoaded(tasks)` |
| HydrateMediaUrls | 1× `whereIn` cho mọi `*_media_id` |
| QuestionResource | không đụng relation |
| Accessor/appends | Không thấy `getXxxAttribute` / `Attribute::make` query DB |

## Recommended action plan

### Fix ngay

1. Community attempts + Complete session batching (#1–2)
2. `memberFor` + ActiveBanners subscription once (#5–6)
3. Memoize subscription/entitlements (#7–8)
4. Invoice counter + Live session lock (#12–13)

### Trước production

1. Study plan detail + reminder job (#3–4)
2. Landing hydrate + MediaUsage + snapshots bulk (#9–11)
3. Thay `whereDate` (#15)
4. Query-count tests trên Complete + Landing + Banners

### Tối ưu sau

1. Media search, Scout fallback, pagination list (#16–18)
2. TX hẹp, catalog sync, history over-fetch (#19–21)
3. SELECT cột trên admin list JSON-heavy

### Không cần tối ưu ngay

1. FAQ nhỏ, SELECT * chung, menu cold sync (#22–24)
2. Eager load đã đúng ở Classroom/Billing/Bookmarks
3. Không cache dữ liệu thay đổi từng giây

## Đề xuất test query-count (CI)

```php
// Ví dụ: CompleteQuestionSessionAction với 20 câu
DB::flushQueryLog();
DB::enableQueryLog();
CompleteQuestionSessionAction::run($session);
expect(count(DB::getQueryLog()))->toBeLessThan(40); // hiện ~1+3N

// Landing home: CmsPage ≤1, Media hydrate ≤1
// ActiveBanners Free+Premium: CurrentSubscription ≤1
```

Xác nhận runtime bằng Laravel Debugbar / EXPLAIN khi triển khai fix.

---

Bản canvas tương tác (chỉ mở trong Cursor managed folder):  
`~/.cursor/projects/Users-quan-Desktop-du-an-y-khoa/canvases/db-performance-audit.canvas.tsx`
