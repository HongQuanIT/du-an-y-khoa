<?php

declare(strict_types=1);

namespace Modules\Admin\Actions\Cms;

use App\Support\Concerns\AsAction;
use Modules\Admin\Models\Faq;

final class ReorderFaqAction
{
    use AsAction;

    public function handle(Faq $faq, string $direction): Faq
    {
        $neighbor = Faq::query()
            ->where('category', $faq->category->value)
            ->when($direction === 'up', function ($query) use ($faq): void {
                $query->where('sort_order', '<', $faq->sort_order)
                    ->orderByDesc('sort_order');
            }, function ($query) use ($faq): void {
                $query->where('sort_order', '>', $faq->sort_order)
                    ->orderBy('sort_order');
            })
            ->first();

        if ($neighbor === null) {
            return $faq;
        }

        $currentOrder = $faq->sort_order;
        $faq->sort_order = $neighbor->sort_order;
        $neighbor->sort_order = $currentOrder;

        $faq->save();
        $neighbor->save();

        return $faq->refresh();
    }
}
