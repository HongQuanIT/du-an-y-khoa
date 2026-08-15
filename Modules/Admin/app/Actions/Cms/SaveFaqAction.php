<?php

declare(strict_types=1);

namespace Modules\Admin\Actions\Cms;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Html\SafeHtml;
use Modules\Admin\Http\Requests\Cms\SaveFaqRequest;
use Modules\Admin\Models\Faq;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\Enums\FaqCategory;

final class SaveFaqAction
{
    use AsAction;

    public function handle(User $actor, SaveFaqRequest $request, ?Faq $faq = null): Faq
    {
        $isNew = $faq === null;
        $faq ??= new Faq;

        $before = $isNew ? null : $faq->only([
            'category',
            'question',
            'answer',
            'sort_order',
            'is_published',
            'published_at',
        ]);

        $category = FaqCategory::from((string) $request->validated('category'));
        $publish = $request->boolean('is_published');

        $faq->fill([
            'category' => $category,
            'question' => trim((string) $request->validated('question')),
            'answer' => SafeHtml::fromEditor((string) $request->validated('answer')),
            'sort_order' => $request->integer('sort_order') ?: Faq::nextSortOrder($category),
            'is_published' => $publish,
            'published_at' => $publish ? ($faq->published_at ?? now()) : null,
        ]);

        $faq->save();

        Auditor::record(
            $publish ? 'cms.faq.publish' : ($isNew ? 'cms.faq.create' : 'cms.faq.update'),
            $actor,
            $faq,
            $before,
            $faq->only([
                'category',
                'question',
                'sort_order',
                'is_published',
                'published_at',
            ]),
        );

        return $faq->refresh();
    }
}
