@php
    use App\Support\Html\SafeHtml;
    use Modules\QuestionBank\Enums\Difficulty;
    use Modules\QuestionBank\Enums\QuestionStatus;

    $eventLabels = [
        'baseline' => 'Phiên bản ban đầu',
        'save' => 'Cập nhật nội dung',
        'status' => 'Đổi trạng thái',
        'restore' => 'Khôi phục phiên bản',
    ];
@endphp

<x-layouts.admin title="Lịch sử phiên bản câu hỏi">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('admin.questions.edit', $question) }}"
                class="mb-3 inline-flex items-center gap-1 text-sm text-primary hover:underline">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Quay lại chỉnh sửa
            </a>
            <h1 class="font-headline-sm font-bold text-on-surface">Lịch sử phiên bản</h1>
            <p class="mt-1 max-w-3xl text-sm text-on-surface-variant">
                {{ \Illuminate\Support\Str::limit(SafeHtml::plainText($question->stem), 160) }}
            </p>
        </div>
        <span class="inline-flex shrink-0 items-center rounded-full bg-primary/10 px-3 py-1.5 text-sm font-semibold text-primary">
            Hiện tại: phiên bản {{ $contentVersion }}
        </span>
    </div>

    <x-admin.flash />

    @if ($errors->has('version'))
        <div class="mb-4 rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
            {{ $errors->first('version') }}
        </div>
    @endif

    <div class="space-y-4">
        @forelse ($versions as $version)
            @php
                $snapshot = $version->snapshot;
                $stemImagePath = (string) ($snapshot['stem_image_path'] ?? '');
                $stemImageUrl = filled($stemImagePath)
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($stemImagePath)
                    : null;
                $status = QuestionStatus::tryFrom((string) ($snapshot['status'] ?? ''));
                $difficulty = Difficulty::tryFrom((string) ($snapshot['difficulty'] ?? ''));
                $versionTopicNames = collect((array) ($snapshot['topic_ids'] ?? []))
                    ->map(fn ($id) => $topicNames->get((int) $id, "Chủ đề #{$id}"))
                    ->values();
                $isCurrent = (int) $version->version === (int) $contentVersion;
            @endphp

            <article class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-label-lg font-bold text-on-surface">Phiên bản {{ $version->version }}</h2>
                            @if ($isCurrent)
                                <span class="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-on-primary">Hiện tại</span>
                            @endif
                            <span class="rounded-full bg-surface-container px-2 py-0.5 text-xs text-on-surface-variant">
                                {{ $eventLabels[$version->event] ?? $version->event }}
                            </span>
                            @if ($version->restored_from_version)
                                <span class="text-xs text-on-surface-variant">
                                    từ phiên bản {{ $version->restored_from_version }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            {{ $version->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
                            · {{ $version->creator?->name ?? 'Hệ thống' }}
                        </p>
                    </div>

                    @if ($canRestore && ! $isCurrent)
                        <form method="post" action="{{ route('admin.questions.versions.restore', [$question, $version]) }}"
                            onsubmit="return confirm('Khôi phục phiên bản {{ $version->version }}? Nội dung khôi phục sẽ được lưu thành một phiên bản mới ở trạng thái Bản nháp.')">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1 rounded-xl border border-primary px-3 py-2 text-sm font-semibold text-primary hover:bg-primary/10">
                                <span class="material-symbols-outlined text-[18px]">history</span>
                                Khôi phục
                            </button>
                        </form>
                    @endif
                </div>

                <div class="mt-4 rounded-xl bg-surface-container-lowest p-4">
                    <p class="line-clamp-3 text-sm text-on-surface">
                        {{ \Illuminate\Support\Str::limit(SafeHtml::plainText((string) ($snapshot['stem'] ?? '')), 300) }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-on-surface-variant">
                        @if ($status)
                            <span class="rounded-lg bg-surface-container px-2 py-1">{{ $status->label() }}</span>
                        @endif
                        @if ($difficulty)
                            <span class="rounded-lg bg-surface-container px-2 py-1">{{ $difficulty->label() }}</span>
                        @endif
                        <span class="rounded-lg bg-surface-container px-2 py-1">
                            {{ count((array) ($snapshot['options'] ?? [])) }} đáp án
                        </span>
                        @if ($stemImageUrl)
                            <span class="rounded-lg bg-surface-container px-2 py-1">Có hình ảnh</span>
                        @endif
                        @foreach ($versionTopicNames as $topicName)
                            <span class="rounded-lg bg-primary/10 px-2 py-1 text-primary">{{ $topicName }}</span>
                        @endforeach
                    </div>
                </div>

                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer font-medium text-primary">Xem nội dung phiên bản</summary>
                    <div class="mt-3 space-y-3 rounded-xl border border-outline-variant p-4">
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase text-on-surface-variant">Đề bài</p>
                            <div class="prose prose-sm max-w-none text-on-surface">{!! SafeHtml::forDisplay((string) ($snapshot['stem'] ?? '')) !!}</div>
                        </div>
                        @if ($stemImageUrl)
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase text-on-surface-variant">Hình ảnh</p>
                                <div class="overflow-hidden rounded-lg border border-outline-variant bg-white">
                                    <img src="{{ $stemImageUrl }}" alt="Hình ảnh câu hỏi phiên bản {{ $version->version }}"
                                        class="max-h-[420px] w-full object-contain">
                                </div>
                            </div>
                        @endif
                        @if (filled($snapshot['explanation'] ?? null))
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase text-on-surface-variant">Giải thích</p>
                                <div class="prose prose-sm max-w-none text-on-surface">{!! SafeHtml::forDisplay((string) $snapshot['explanation']) !!}</div>
                            </div>
                        @endif
                        @if (filled($snapshot['attending_tip'] ?? null))
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase text-on-surface-variant">Kiến thức / Gợi ý</p>
                                <div class="prose prose-sm max-w-none text-on-surface">{!! SafeHtml::forDisplay((string) $snapshot['attending_tip']) !!}</div>
                            </div>
                        @endif
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase text-on-surface-variant">Đáp án</p>
                            <ol class="space-y-2">
                                @foreach ((array) ($snapshot['options'] ?? []) as $option)
                                    <li class="rounded-lg border px-3 py-2 {{ ($option['is_correct'] ?? false) ? 'border-primary bg-primary/5' : 'border-outline-variant' }}">
                                        <div>
                                            <span class="font-semibold">{{ $option['label'] ?? $loop->iteration }}.</span>
                                            {!! SafeHtml::forDisplay((string) ($option['content'] ?? '')) !!}
                                            @if ($option['is_correct'] ?? false)
                                                <span class="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-on-primary">Đáp án đúng</span>
                                            @endif
                                        </div>
                                        @if (filled($option['explanation'] ?? null))
                                            <div class="mt-2 border-t border-outline-variant pt-2 text-xs text-on-surface-variant">
                                                <span class="font-semibold">Giải thích đáp án:</span>
                                                <div class="prose prose-sm mt-1 max-w-none text-on-surface-variant">{!! SafeHtml::forDisplay((string) $option['explanation']) !!}</div>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </details>
            </article>
        @empty
            <div class="rounded-2xl border border-outline-variant bg-surface p-10 text-center text-on-surface-variant">
                Chưa có lịch sử phiên bản.
            </div>
        @endforelse
    </div>

    <div class="mt-5">{{ $versions->links() }}</div>
</x-layouts.admin>
