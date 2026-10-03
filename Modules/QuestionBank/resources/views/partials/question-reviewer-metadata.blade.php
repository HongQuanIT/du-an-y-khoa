@php
    /** @var \Modules\QuestionBank\Models\Question $question */
    $groups = [
        ['title' => 'Bài học', 'items' => $question->lessons->pluck('name')],
        ['title' => 'Đối tượng', 'items' => $question->professions->pluck('name')],
        ['title' => 'Độ khó', 'items' => collect([$question->difficulty->label()])],
        ['title' => 'Kỳ thi', 'items' => $question->blueprints->pluck('name')],
        ['title' => 'Truy cập', 'items' => collect([$question->is_free ? 'Miễn phí' : 'Chỉ Premium'])],
    ];
@endphp

<div class="order-30 space-y-5" data-testid="reviewer-question-metadata">
    <div class="space-y-5">
        @foreach ($groups as $group)
            <div>
                <h4 class="mb-2 text-sm font-bold text-on-surface">{{ $group['title'] }}</h4>
                <div class="flex flex-wrap gap-2">
                    @forelse ($group['items']->filter() as $item)
                        <span class="inline-flex rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                            {{ $item }}
                        </span>
                    @empty
                        <span class="text-sm text-on-surface-variant">Chưa nhập.</span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
