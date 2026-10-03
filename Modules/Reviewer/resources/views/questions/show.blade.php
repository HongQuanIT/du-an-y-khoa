<x-layouts.reviewer title="Review câu hỏi — {{ $question->code }}" :pending-count="0">
    <x-admin.page-header :title="'Review câu hỏi '.$question->code"
        description="Rà checklist, rồi chọn Đạt hoặc Không đạt. Không đạt phải đánh dấu ít nhất một mục.">
        <x-slot:actions>
            <a href="{{ route('reviewer.questions.flags.index', ['tab' => $inConflict ? 'warning' : 'pending']) }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Hàng đợi</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($inConflict)
        <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">Cần rà soát lại</p>
            <p class="mt-1">Kết quả gắn cờ chưa thống nhất. Hãy đọc lại toàn bộ câu hỏi rồi xác nhận quyết định của bạn.
                Giữ nguyên nếu vẫn đúng; chỉ đổi khi đã kiểm tra kỹ và chịu trách nhiệm với quyết định của mình.</p>
        </div>
    @endif

    @php
        $selectedChecks = array_values((array) old('failed_checks', $ownFailedChecks ?? []));
    @endphp

    <div class="grid grid-cols-1 items-start gap-6 max-xl:pb-[var(--review-sheet)] xl:grid-cols-[minmax(0,1fr)_26rem]"
        style="--review-sheet: min(58dvh, 32rem)"
        x-data="{ open: true }"
        :style="open ? '--review-sheet: min(58dvh, 32rem)' : '--review-sheet: 3.25rem'">
        <div class="min-w-0">
            @include('questionbank::partials.question-learner-preview', [
                'question' => $question,
                'revealAnswers' => true,
            ])
        </div>

        <div class="fixed inset-x-0 bottom-0 z-30 flex h-[var(--review-sheet)] max-h-[var(--review-sheet)] flex-col overflow-hidden rounded-t-2xl border border-outline-variant bg-surface pb-[env(safe-area-inset-bottom)] shadow-[0_-12px_32px_rgba(0,0,0,0.12)] xl:sticky xl:top-header-height xl:inset-auto xl:bottom-auto xl:z-20 xl:h-auto xl:max-h-[calc(100dvh-var(--spacing-header-height)-1rem)] xl:rounded-none xl:border-0 xl:bg-transparent xl:pb-0 xl:shadow-none"
            data-testid="reviewer-flag-panel">
            <button type="button"
                class="flex w-full shrink-0 items-center justify-center gap-2 border-b border-outline-variant px-4 py-2.5 text-xs font-semibold text-on-surface-variant xl:hidden"
                @click="open = !open"
                :aria-expanded="open">
                <span class="h-1 w-9 rounded-full bg-outline-variant" aria-hidden="true"></span>
                <span x-text="open ? 'Thu gọn để đọc câu' : 'Mở checklist và gắn cờ'">Thu gọn để đọc câu</span>
            </button>
            <div class="flex min-h-0 flex-1 flex-col xl:!flex" x-show="open">
            @if ($canFlag)
                <form id="reviewer-flag-form" method="post" action="{{ route('reviewer.questions.flags.store', $question) }}"
                    class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm max-xl:rounded-none max-xl:border-0 max-xl:shadow-none"
                    x-data="{
                        flag: '{{ old('flag', '') }}',
                        failed: @js($selectedChecks),
                        peer: @js($peerFlag ?? ''),
                        showConflict: false,
                        dismissConflict() { this.showConflict = false },
                        keeping($event) { return $event.submitter && $event.submitter.dataset.keep === '1' }
                    }"
                    @submit="if (flag === 'red' && failed.length === 0) { $event.preventDefault(); return }
                             if (!keeping($event) && peer && flag && flag !== peer) { $event.preventDefault(); showConflict = true }"
                    @keydown.escape.window="if (showConflict) dismissConflict()">
                    @csrf
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        @include('reviewer::questions.partials.checklist', [
                            'question' => $question,
                            'interactive' => true,
                            'selected' => $selectedChecks,
                        ])
                    </div>
                    <div class="shrink-0 border-t border-outline-variant bg-surface px-5 py-4">
                        @include('admin::questions.flags.partials.flag-chooser', [
                            'flags' => $flags,
                            'checkedValue' => null,
                        ])
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="note">Ghi chú (tuỳ chọn)</label>
                        <textarea id="note" name="note" rows="2"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm">{{ old('note') }}</textarea>
                        <p class="mt-3 text-xs font-medium text-rose-700" x-show="flag === 'red' && failed.length === 0" x-cloak>
                            Không đạt cần đánh dấu ít nhất một mục checklist.
                        </p>
                        <button type="submit"
                            class="mt-4 w-full rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="flag === 'red' && failed.length === 0">
                            Gửi quyết định
                        </button>
                    </div>
                    @include('reviewer::questions.partials.conflict-warning-modal', ['formId' => 'reviewer-flag-form'])
                </form>
            @elseif ($canChangeInConflict)
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-amber-200 bg-surface shadow-sm max-xl:rounded-none max-xl:border-0 max-xl:shadow-none"
                    x-data="{
                        flag: '{{ old('flag', $ownFlag?->value ?? '') }}',
                        original: '{{ $ownFlag?->value ?? '' }}',
                        failed: @js($selectedChecks),
                        originalFailed: @js($ownFailedChecks ?? []),
                        showAck: false,
                        acked: false,
                        get isChange() { return this.flag !== '' && this.flag !== this.original },
                        get checksChanged() {
                            const current = [...this.failed].sort().join('|');
                            const previous = [...this.originalFailed].sort().join('|');
                            return current !== previous;
                        },
                        get needsCheck() { return this.flag === 'red' && this.failed.length === 0 },
                        get canSubmit() { return ! this.needsCheck && (this.isChange || (this.flag === 'red' && this.checksChanged)) },
                        openAck() {
                            if (! this.isChange) return;
                            this.acked = false;
                            this.showAck = true;
                        },
                        cancelAck() {
                            this.showAck = false;
                            this.acked = false;
                        },
                        confirmChange() {
                            if (! this.acked) return;
                            this.showAck = false;
                            const ack = this.$refs.conflictForm.querySelector('[name=\'responsibility_acked\']');
                            if (ack) {
                                ack.disabled = false;
                                ack.value = '1';
                            }
                            this.$refs.conflictForm.requestSubmit();
                        }
                    }"
                    @keydown.escape.window="if (showAck) cancelAck()">
                    <form id="reviewer-conflict-form" method="post" action="{{ route('reviewer.questions.flags.update', $question) }}"
                        class="flex min-h-0 flex-1 flex-col"
                        x-ref="conflictForm"
                        @submit="if (!canSubmit) { $event.preventDefault(); return }
                                 if (isChange && !acked) { $event.preventDefault(); openAck() }">
                        @csrf
                        @method('PUT')
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div class="flex items-center gap-3 border-b border-outline-variant px-5 py-4">
                            <span class="text-sm font-semibold text-on-surface">Quyết định hiện tại</span>
                            @if ($ownFlag)
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm font-semibold {{ $ownFlag->chipClasses(true) }}">
                                    <span class="material-symbols-outlined text-[18px] leading-none {{ $ownFlag->iconClasses(true) }}"
                                        aria-hidden="true"
                                        style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 20;">{{ $ownFlag->icon() }}</span>
                                    {{ $ownFlag->shortLabel() }}
                                </span>
                            @endif
                        </div>
                        @include('reviewer::questions.partials.checklist', [
                            'question' => $question,
                            'interactive' => true,
                            'selected' => $selectedChecks,
                        ])
                    </div>

                    <div class="shrink-0 border-t border-outline-variant bg-surface px-5 py-4">
                        @if (filled($ownNote))
                            <div class="mb-3 rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2.5">
                                <p class="text-xs font-semibold text-on-surface-variant">Ghi chú đã ghi</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm text-on-surface">{{ $ownNote }}</p>
                            </div>
                        @endif
                        @include('admin::questions.flags.partials.flag-chooser', [
                            'flags' => $flags,
                            'checkedValue' => $ownFlag?->value,
                        ])
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="conflict-note">Ghi chú (tuỳ chọn)</label>
                        <textarea id="conflict-note" name="note" rows="2"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm">{{ old('note', $ownNote) }}</textarea>

                        <input type="hidden" name="responsibility_acked" value="1" :disabled="!acked">

                        <p class="mt-3 text-xs font-medium text-rose-700" x-show="needsCheck" x-cloak>
                            Không đạt cần đánh dấu ít nhất một mục checklist.
                        </p>
                        <button type="button" x-show="needsCheck" x-cloak
                            class="mt-4 w-full cursor-not-allowed rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary opacity-40"
                            disabled>
                            Gửi quyết định
                        </button>
                        <button type="button" x-show="!canSubmit && !needsCheck" x-cloak
                            class="mt-4 w-full cursor-default rounded-xl border border-outline-variant bg-surface-container-low px-4 py-2.5 font-label-md font-semibold text-on-surface-variant"
                            disabled>
                            Giữ nguyên — không thay đổi
                        </button>
                        <button type="button" x-show="canSubmit && isChange" x-cloak
                            class="mt-4 w-full rounded-xl bg-amber-600 px-4 py-2.5 font-label-md font-semibold text-white"
                            @click="openAck()">
                            Đổi quyết định…
                        </button>
                        <button type="submit" x-show="canSubmit && !isChange" x-cloak
                            class="mt-4 w-full rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary">
                            Cập nhật mục không đạt
                        </button>
                    </div>
                    </form>

                    <div x-show="showAck" x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                        role="dialog" aria-modal="true" aria-labelledby="flag-ack-title">
                        <div class="w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-6 shadow-lg"
                            @click.outside="cancelAck()">
                            <h3 id="flag-ack-title" class="text-base font-semibold text-on-surface">Xác nhận thay đổi</h3>
                            <p class="mt-3 text-sm text-on-surface-variant">{{ $ackText }}</p>
                            <label class="mt-4 flex items-start gap-2 text-sm text-on-surface">
                                <input type="checkbox" class="mt-1" x-model="acked">
                                <span>Tôi đã đọc lại câu hỏi, đáp án, giải thích và chịu trách nhiệm với quyết định này.</span>
                            </label>
                            <div class="mt-5 flex justify-end gap-2">
                                <button type="button" class="rounded-lg px-3 py-2 text-sm text-on-surface-variant"
                                    @click="cancelAck()">Hủy</button>
                                <button type="button"
                                    class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40"
                                    :disabled="!acked"
                                    @click="confirmChange()">
                                    Xác nhận đổi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm max-xl:rounded-none max-xl:border-0 max-xl:shadow-none">
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        @include('reviewer::questions.partials.checklist', [
                            'question' => $question,
                            'interactive' => false,
                            'selected' => $ownFailedChecks ?? [],
                        ])
                    </div>
                    <div class="shrink-0 border-t border-outline-variant bg-surface-container-low px-5 py-4 text-sm text-on-surface-variant">
                        @if (! $hasFlagPermission)
                            Bạn không có quyền thao tác gắn cờ.
                        @elseif ($ownFlag)
                            <div class="flex items-center gap-2">
                                <span>Bạn đã chọn</span>
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm font-semibold {{ $ownFlag->chipClasses(true) }}">
                                    <span class="material-symbols-outlined text-[18px] leading-none {{ $ownFlag->iconClasses(true) }}"
                                        aria-hidden="true"
                                        style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 20;">{{ $ownFlag->icon() }}</span>
                                    {{ $ownFlag->shortLabel() }}
                                </span>
                            </div>
                            <p class="mt-2">Chỉ xem lại nội dung.</p>
                            @if (filled($ownNote))
                                <div class="mt-3 rounded-xl border border-outline-variant bg-surface px-3 py-2.5">
                                    <p class="text-xs font-semibold text-on-surface-variant">Ghi chú đã ghi</p>
                                    <p class="mt-1 whitespace-pre-wrap text-sm text-on-surface">{{ $ownNote }}</p>
                                </div>
                            @endif
                            @if ($question->status->value === 'flag_conflict')
                                <p class="mt-3">Câu đang ở Cảnh báo nhưng bạn không còn quyền đổi quyết định.</p>
                            @endif
                        @else
                            Bạn đã gắn cờ cho câu này — chỉ xem lại nội dung.
                        @endif
                    </div>
                </div>
            @endif
            </div>
        </div>
    </div>
</x-layouts.reviewer>
