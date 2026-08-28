# Database seeding (local / staging / production)

## `db:seed` có xóa database không?

**Không.** `php artisan db:seed --class=StagingSeeder` (và các seeder khác) **không** drop bảng hay truncate. Hầu hết dùng `updateOrCreate` / `firstOrCreate` — chạy lại chỉ bổ sung / đồng bộ baseline, giữ data hiện có.

Muốn **xóa hết rồi seed lại từ đầu**, dùng `app:seed … --fresh` (bên dưới).

> Lưu ý: flag `--force` của Laravel `db:seed` / `migrate` nghĩa là *bỏ confirmation trên production*, **không** phải wipe data.

## Profiles

| Profile | Class | Nội dung chính |
|---------|--------|----------------|
| **local** | `DatabaseSeeder` | Full demo: QBank VM14K, library, study plan, banner, search, promo billing… |
| **staging** | `StagingSeeder` | Baseline gần prod + QA users + FAQ. Không demo learning content |
| **production** | `ProductionSeeder` | Baseline tối thiểu. Không user cố định, không FAQ demo |

### Staging gồm

- `RolePermissionSeeder`, `UserSeeder` (QA)
- Blueprint thi + medical taxonomy
- Billing plans/prices (promo / institution giả chỉ khi `APP_ENV=local`)
- Settings, CMS pages, Menu, **FaqSeeder**

### Production gồm

Như staging nhưng **không** `UserSeeder`, **không** `FaqSeeder`. Tạo admin thủ công sau seed.

## Cách chạy (khuyến nghị)

```bash
# Idempotent — không xóa DB
php artisan app:seed staging
php artisan app:seed production
php artisan app:seed local

# Wipe toàn bộ → migrate:fresh → seed lại
php artisan app:seed staging --fresh
php artisan app:seed production --fresh

# CI / non-interactive: bỏ bước confirm
php artisan app:seed staging --fresh --force
```

Tương đương thủ công:

```bash
# Không wipe
php artisan db:seed --class=StagingSeeder --force

# Wipe + seed
php artisan migrate:fresh --seed --seeder=StagingSeeder --force
```

(`--force` ở đây = chạy được khi `APP_ENV` không phải `local`.)

## Khi nào dùng `--fresh`

| Tình huống | Dùng |
|------------|------|
| Bootstrap staging lần đầu / reset môi trường QA | `app:seed staging --fresh` |
| Chỉ bổ sung role/plan/CMS sau deploy | `app:seed staging` (không `--fresh`) |
| Production lần đầu (empty DB) | `migrate` rồi `app:seed production` — tránh `--fresh` nếu đã có user thật |
| Local full demo | `app:seed local` hoặc `app:seed local --fresh` |

**Không** gắn `db:seed` / `--fresh` vào mỗi lần deploy (`scripts/deploy.sh` chỉ migrate). Seed chỉ khi bootstrap hoặc reset có chủ đích.

## Tài khoản QA (staging / local)

Xem `database/seeders/UserSeeder.php` — ví dụ `superadmin@medlearn.local`, `student@medlearn.local`. Đổi mật khẩu mạnh trên staging trước khi share.
