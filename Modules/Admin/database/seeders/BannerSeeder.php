<?php

declare(strict_types=1);

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Admin\Models\Banner;
use Modules\Admin\Support\Enums\BannerAudience;
use Modules\Admin\Support\Enums\BannerPlacement;
use Modules\Admin\Support\Enums\BannerVariant;

final class BannerSeeder extends Seeder
{
    public function run(): void
    {
        if (Banner::query()->exists()) {
            return;
        }

        Banner::query()->create([
            'title' => 'Landing — khuyến mãi mẫu',
            'body' => 'Ưu đãi khai trương: giảm 20% gói Premium trong tháng này. Bắt đầu luyện thi thông minh ngay hôm nay.',
            'cta_label' => 'Xem bảng giá',
            'cta_url' => '/pricing',
            'variant' => BannerVariant::Promo,
            'placement' => BannerPlacement::Landing,
            'audience' => BannerAudience::All,
            'is_enabled' => true,
            'is_dismissible' => true,
            'sort_order' => 10,
            'starts_at' => null,
            'ends_at' => now()->addMonths(1),
        ]);

        Banner::query()->create([
            'title' => 'Dashboard — nhắc Free nâng cấp',
            'body' => 'Mở khóa 10.000+ câu hỏi bản quyền và phân tích AI với Premium.',
            'cta_label' => 'Nâng cấp ngay',
            'cta_url' => '/pricing',
            'variant' => BannerVariant::Info,
            'placement' => BannerPlacement::Dashboard,
            'audience' => BannerAudience::Free,
            'is_enabled' => true,
            'is_dismissible' => true,
            'sort_order' => 10,
        ]);
    }
}
