@php
    $question = $reviewRequest->question;
    $payload = $reviewRequest->payload ?? [];
    $isUpdate = $reviewRequest->action === \Modules\QuestionBank\Enums\QuestionReviewAction::Update;
    $proposedStem = $isUpdate ? ($payload['stem'] ?? '') : $question->stem;
    $proposedKeyInfo = collect($isUpdate ? ($payload['key_info'] ?? []) : ($question->key_info ?? []));
    $proposedAttendingTip = $isUpdate ? ($payload['attending_tip'] ?? '') : $question->attending_tip;
    $proposedOptions = $isUpdate ? collect($payload['options'] ?? []) : $question->options;
    $proposedTopicIds = $isUpdate
        ? collect($payload['medical_taxonomy_node_ids'] ?? [])
        : $question->medicalTaxonomyNodes->pluck('id');
@endphp

<x-layouts.admin title="Kiểm duyệt câu hỏi">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.questions.index', ['status' => 'in_review']) }}"
                class="flex size-9 items-center justify-center rounded-xl border border-outline-variant text-on-surface-variant hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <h1 class="font-headline-sm font-bold text-on-surface">Kiểm duyệt {{ mb_strtolower($reviewRequest->action->label()) }}</h1>
                <p class="mt-0.5 text-sm text-on-surface-variant">
                    Người gửi: <span class="font-semibold text-on-surface">{{ $reviewRequest->requester?->name }}</span>
                    · {{ $reviewRequest->created_at?->format('d/m/Y H:i') }}
                </p>
            </div>
        </div>
        <span class="inline-flex whitespace-nowrap rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">
            {{ $reviewRequest->status->label() }}
        </span>
    </div>

    <x-admin.flash />

    @if ($reviewRequest->status === \Modules\QuestionBank\Enums\QuestionReviewStatus::Pending)
        <div class="mb-6 rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
            <label for="review_note" class="mb-2 block text-sm font-semibold text-on-surface">Ghi chú cho Content Creator</label>
            <textarea id="review_note" form="approve-review-form" name="review_note" rows="3"
                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm"
                placeholder="Nhập lý do hoặc góp ý (không bắt buộc)..."></textarea>
            <div class="mt-3 flex flex-wrap justify-end gap-2">
                <form id="reject-review-form" method="post" action="{{ route('admin.questions.reviews.reject', $reviewRequest) }}">
                    @csrf
                    <input type="hidden" name="review_note" id="reject-review-note">
                    <button type="submit" onclick="document.getElementById('reject-review-note').value = document.getElementById('review_note').value; return confirm('Từ chối yêu cầu này?')"
                        class="inline-flex whitespace-nowrap items-center gap-1 rounded-xl border border-rose-300 px-4 py-2.5 font-semibold text-rose-700 hover:bg-rose-50">
                        <span class="material-symbols-outlined text-[18px]">close</span>Từ chối
                    </button>
                </form>
                <form id="approve-review-form" method="post" action="{{ route('admin.questions.reviews.approve', $reviewRequest) }}">
                    @csrf
                    <button type="submit" onclick="return confirm('Phê duyệt yêu cầu này?')"
                        class="inline-flex whitespace-nowrap items-center gap-1 rounded-xl bg-primary px-4 py-2.5 font-semibold text-on-primary hover:bg-primary/90">
                        <span class="material-symbols-outlined text-[18px]">check</span>Phê duyệt
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if ($reviewRequest->action === \Modules\QuestionBank\Enums\QuestionReviewAction::Delete)
        <div class="mb-6 rounded-2xl border border-rose-300 bg-rose-50 p-5 text-rose-900">
            <p class="font-bold">Content Creator yêu cầu xóa câu hỏi này.</p>
            <p class="mt-1 text-sm">Câu hỏi chỉ bị xóa mềm sau khi bạn phê duyệt.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 {{ $isUpdate ? 'xl:grid-cols-2' : '' }}">
        @if ($isUpdate)
            <section class="rounded-2xl border border-outline-variant bg-surface p-5">
                <h2 class="mb-4 font-label-lg font-bold text-on-surface">Nội dung đang xuất bản</h2>
                <p class="whitespace-pre-wrap text-sm leading-6 text-on-surface">{{ strip_tags((string) $question->stem) }}</p>

                <h3 class="mt-5 text-sm font-bold text-on-surface">Đáp án và giải thích</h3>
                <div class="mt-2 space-y-2">
                    @foreach ($question->options as $index => $option)
                        <div class="rounded-xl border {{ $option->is_correct ? 'border-emerald-300 bg-emerald-50' : 'border-outline-variant bg-surface-container-lowest' }} px-3 py-2 text-sm">
                            <p><span class="mr-2 font-bold">{{ chr(65 + $index) }}.</span>{{ strip_tags((string) $option->content) }}</p>
                            @if (filled(strip_tags((string) $option->explanation)))
                                <p class="mt-1 border-t border-current/10 pt-1 text-xs leading-5 text-on-surface-variant">
                                    <span class="font-semibold">Giải thích:</span> {{ strip_tags((string) $option->explanation) }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <h3 class="mt-5 text-sm font-bold text-on-surface">Ý chính cần ghi nhớ</h3>
                @forelse ((array) $question->key_info as $item)
                    <p class="mt-1 text-sm text-on-surface-variant">• {{ strip_tags((string) $item) }}</p>
                @empty
                    <p class="mt-1 text-sm text-on-surface-variant">Chưa nhập.</p>
                @endforelse

                <h3 class="mt-5 text-sm font-bold text-on-surface">Kiến thức / Gợi ý</h3>
                <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-on-surface-variant">{{ filled(strip_tags((string) $question->attending_tip)) ? strip_tags((string) $question->attending_tip) : 'Chưa nhập.' }}</p>
            </section>
        @endif

        <section class="rounded-2xl border {{ $isUpdate ? 'border-primary/40 bg-primary/5' : 'border-outline-variant bg-surface' }} p-5">
            <h2 class="mb-4 font-label-lg font-bold text-on-surface">{{ $isUpdate ? 'Nội dung đề xuất' : 'Nội dung câu hỏi' }}</h2>
            <p class="whitespace-pre-wrap text-sm leading-6 text-on-surface">{{ strip_tags((string) $proposedStem) }}</p>

            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($proposedTopicIds as $nodeId)
                    <span class="inline-flex whitespace-nowrap rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-semibold">
                        {{ $nodeNames[(int) $nodeId] ?? $question->medicalTaxonomyNodes->firstWhere('id', (int) $nodeId)?->name ?? "Node #{$nodeId}" }}
                    </span>
                @endforeach
            </div>

            <h3 class="mt-5 text-sm font-bold text-on-surface">Đáp án</h3>
            <div class="mt-2 space-y-2">
                @foreach ($proposedOptions as $index => $option)
                    @php
                        $content = is_array($option) ? ($option['content'] ?? '') : $option->content;
                        $correct = is_array($option) ? (bool) ($option['is_correct'] ?? false) : (bool) $option->is_correct;
                    @endphp
                    @php
                        $optionExplanation = is_array($option) ? ($option['explanation'] ?? '') : $option->explanation;
                    @endphp
                    <div class="rounded-xl border px-3 py-2 text-sm {{ $correct ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-outline-variant bg-surface-container-lowest' }}">
                        <p>
                            <span class="mr-2 font-bold">{{ chr(65 + $index) }}.</span>{{ strip_tags((string) $content) }}
                            @if ($correct)<span class="ml-2 text-xs font-bold">Đáp án đúng</span>@endif
                        </p>
                        @if (filled(strip_tags((string) $optionExplanation)))
                            <p class="mt-1 border-t border-current/10 pt-1 text-xs leading-5 text-on-surface-variant">
                                <span class="font-semibold">Giải thích:</span> {{ strip_tags((string) $optionExplanation) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            <h3 class="mt-5 text-sm font-bold text-on-surface">Ý chính cần ghi nhớ</h3>
            @forelse ($proposedKeyInfo as $item)
                <p class="mt-1 text-sm text-on-surface-variant">• {{ strip_tags((string) $item) }}</p>
            @empty
                <p class="mt-1 text-sm text-on-surface-variant">Chưa nhập.</p>
            @endforelse

            <h3 class="mt-5 text-sm font-bold text-on-surface">Kiến thức / Gợi ý</h3>
            <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-on-surface-variant">{{ filled(strip_tags((string) $proposedAttendingTip)) ? strip_tags((string) $proposedAttendingTip) : 'Chưa nhập.' }}</p>
        </section>
    </div>
</x-layouts.admin>
