@php
    use Illuminate\Support\Str;
    use Modules\QuestionBank\Enums\DuplicateSeverity;

    $stemPreview = Str::limit(strip_tags((string) $question->stem), 220);
    $checkedLabel = $question->similarity_checked_at?->diffForHumans() ?? 'Vừa quét';
@endphp

<x-layouts.admin title="Kiểm tra trùng lặp">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-3">
            <a href="{{ route('admin.questions.edit', $question) }}"
               class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl border border-outline-variant text-on-surface-variant transition-colors hover:bg-surface-container-low"
               aria-label="Quay lại chỉnh sửa">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div class="min-w-0">
                <h1 class="font-headline-sm font-bold text-on-surface">Kiểm tra trùng lặp</h1>
                <p class="mt-1 max-w-3xl font-body-sm text-on-surface-variant">
                    @if (filled($question->code))
                        <span class="font-mono font-semibold text-on-surface">{{ $question->code }}</span>
                        <span class="mx-1">·</span>
                    @endif
                    {{ $stemPreview }}
                </p>
                <p class="mt-1 font-label-sm text-on-surface-variant">
                    {{ $question->status->label() }}
                    <span class="mx-1">·</span>
                    Ngưỡng hiển thị ≥{{ (int) $threshold }}%
                    <span class="mx-1">·</span>
                    Cập nhật: {{ $checkedLabel }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="post" action="{{ route('admin.questions.check-duplicates', $question) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary hover:bg-primary/90">
                    <span class="material-symbols-outlined text-[18px]">radar</span>
                    Quét lại
                </button>
            </form>
            <a href="{{ route('admin.questions.edit', $question) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3 py-2.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[18px]">edit</span>
                Chỉnh sửa
            </a>
        </div>
    </div>

    <x-admin.flash />

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-6">
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm lg:col-span-1">
            <p class="text-label-sm font-medium text-on-surface-variant">Tổng (≥{{ (int) $threshold }}%)</p>
            <p class="mt-1 text-headline-sm font-bold tabular-nums text-on-surface">{{ number_format($kpi['total']) }}</p>
        </div>
        @foreach ([
            ['key' => 'exact', 'label' => '100%'],
            ['key' => 'very_high', 'label' => '≥90%'],
            ['key' => 'high', 'label' => '≥75%'],
            ['key' => 'medium', 'label' => '≥60%'],
            ['key' => 'low', 'label' => '≥30%'],
        ] as $card)
            <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
                <p class="text-label-sm font-medium text-on-surface-variant">{{ $card['label'] }}</p>
                <p class="mt-1 text-headline-sm font-bold tabular-nums text-on-surface">{{ number_format($kpi[$card['key']]) }}</p>
            </div>
        @endforeach
    </div>

    <section class="mb-6 rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
        <h2 class="mb-3 font-label-md font-semibold text-on-surface">Câu đang kiểm tra</h2>
        <div class="space-y-3 text-sm">
            <div>
                <p class="text-xs font-semibold text-on-surface-variant">Stem</p>
                <div class="mt-1 prose prose-sm max-w-none text-on-surface">{!! $question->stem !!}</div>
            </div>
            @if ($question->options->isNotEmpty())
                <div>
                    <p class="mb-1 text-xs font-semibold text-on-surface-variant">Đáp án</p>
                    <ul class="space-y-1">
                        @foreach ($question->options as $option)
                            <li @class(['rounded-lg px-2 py-1', 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' => $option->is_correct])>
                                <span class="font-mono font-semibold">{{ $option->label }}.</span>
                                {{ $option->content }}
                                @if ($option->is_correct)
                                    <span class="text-xs font-bold">(đúng)</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    <div class="overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm">
        <div class="border-b border-outline-variant px-4 py-3">
            <h2 class="font-label-md font-semibold text-on-surface">Kết quả trong ngân hàng</h2>
            <p class="mt-0.5 text-xs text-on-surface-variant">Chỉ hiện cặp ≥{{ (int) $threshold }}% tương đồng lexical (stem + đáp án).</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-outline-variant text-sm">
                <thead class="bg-surface-container-low text-left text-xs font-semibold uppercase tracking-wide text-on-surface-variant">
                    <tr>
                        <th class="px-4 py-3">%</th>
                        <th class="px-4 py-3">Mức độ</th>
                        <th class="px-4 py-3">Câu trùng / gần trùng</th>
                        <th class="px-4 py-3">Tín hiệu</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($rows as $row)
                        @php
                            /** @var \Modules\QuestionBank\Models\QuestionSimilarityMatch $match */
                            $match = $row['match'];
                            /** @var \Modules\QuestionBank\Models\Question $other */
                            $other = $row['other'];
                            /** @var DuplicateSeverity $severity */
                            $severity = $match->severity;
                            $signals = $match->signals ?? [];
                            $isExact = $severity === DuplicateSeverity::Exact;
                        @endphp
                        <tr @class(['align-top hover:bg-surface-container-low/60', 'bg-red-50/40 dark:bg-red-950/20' => $isExact])>
                            <td class="px-4 py-3 font-mono text-base font-bold tabular-nums text-on-surface">
                                {{ number_format($match->score, 1) }}%
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold {{ $severity->badgeClass() }}">
                                    {{ $severity->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-on-surface">
                                    {{ $other->code ?: Str::limit($other->getKey(), 13) }}
                                    <span class="ml-1 text-xs font-medium text-on-surface-variant">{{ $other->status->label() }}</span>
                                </p>
                                <p class="mt-1 text-on-surface-variant">{{ Str::limit(strip_tags((string) $other->stem), 180) }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-on-surface-variant">
                                <p>Stem: {{ isset($signals['stem_score']) ? number_format((float) $signals['stem_score'], 1).'%' : '—' }}</p>
                                <p>Đáp án: {{ isset($signals['options_score']) ? number_format((float) $signals['options_score'], 1).'%' : '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.questions.edit', $other) }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-2.5 py-1.5 text-xs font-semibold text-primary hover:bg-surface-container-low">
                                    Xem
                                    <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-on-surface-variant">
                                Không có câu ≥{{ (int) $threshold }}% trùng với câu này.
                                Bấm <strong>Quét lại</strong> nếu vừa cập nhật nội dung.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
