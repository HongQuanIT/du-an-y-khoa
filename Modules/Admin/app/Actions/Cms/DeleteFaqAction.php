<?php

declare(strict_types=1);

namespace Modules\Admin\Actions\Cms;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Models\Faq;
use Modules\Admin\Support\Auditor;

final class DeleteFaqAction
{
    use AsAction;

    public function handle(User $actor, Faq $faq): void
    {
        $before = $faq->only([
            'category',
            'question',
            'sort_order',
            'is_published',
        ]);

        $faq->delete();

        Auditor::record(
            'cms.faq.delete',
            $actor,
            $faq,
            $before,
            null,
        );
    }
}
