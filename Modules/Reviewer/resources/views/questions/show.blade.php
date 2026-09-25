<x-layouts.reviewer title="Review câu hỏi — {{ $question->code }}" :pending-count="0">
    <x-admin.page-header :title="'Review câu hỏi '.$question->code"
        description="Xem câu như học viên rồi gắn một cờ: xanh (đạt) hoặc đỏ (không đạt — bắt buộc ghi chú).">
        <x-slot:actions>
            <a href="{{ route('reviewer.questions.flags.index') }}"
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

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        @include('questionbank::partials.question-learner-preview', [
            'question' => $question,
            'revealAnswers' => true,
        ])

        <div class="xl:sticky xl:top-4">
            @if ($canFlag)
                <form method="post" action="{{ route('reviewer.questions.flags.store', $question) }}"
                    class="rounded-2xl border border-outline-variant bg-surface p-5"
                    x-data="{ flag: '{{ old('flag', '') }}' }">
                    @csrf
                    <p class="mb-3 text-sm text-on-surface-variant">
                        Chọn một cờ. Cờ đỏ (≥1) sẽ đẩy câu sang Admin để trả về biên tập — không xuất bản được.
                    </p>
                    <div class="mb-4 flex flex-wrap gap-3">
                        @foreach ($flags as $flag)
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-outline-variant px-3 py-2"
                                :class="flag === '{{ $flag->value }}' ? 'border-primary bg-primary/5' : ''">
                                <input type="radio" name="flag" value="{{ $flag->value }}" required
                                    x-model="flag"
                                    @checked(old('flag') === $flag->value)>
                                <span>{{ $flag->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="note">
                        <span x-text="flag === 'red' ? 'Ghi chú (bắt buộc với cờ đỏ)' : 'Ghi chú (tuỳ chọn)'"></span>
                    </label>
                    <textarea id="note" name="note" rows="3"
                        :required="flag === 'red'"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm">{{ old('note') }}</textarea>
                    <button type="submit" class="mt-4 w-full rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary">
                        Gửi cờ
                    </button>
                </form>
            @else
                <div class="rounded-2xl border border-outline-variant bg-surface-container-low p-5 text-sm text-on-surface-variant">
                    @if (! $hasFlagPermission)
                        Bạn không có quyền thao tác gắn cờ.
                    @else
                        Bạn đã gắn cờ cho câu này — chỉ xem lại nội dung.
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.reviewer>
