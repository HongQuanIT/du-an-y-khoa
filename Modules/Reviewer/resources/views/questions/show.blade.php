<x-layouts.reviewer title="Review câu hỏi {{ $question->code }}">
    <x-admin.page-header :title="'Review câu hỏi '.$question->code" description="Xem câu hỏi rồi gắn cờ xanh hoặc đỏ.">
        <x-slot:actions><a href="{{ route('reviewer.questions.flags.index') }}" class="text-primary">← Hàng đợi</a></x-slot:actions>
    </x-admin.page-header>
    <x-admin.flash />
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-error/30 bg-error/5 p-4 text-error">{{ $errors->first() }}</div>
    @endif
    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        @include('questionbank::partials.question-learner-preview', ['question' => $question, 'revealAnswers' => true])
        <div class="rounded-xl border border-outline-variant bg-surface p-5">
            @if ($canFlag)
                <form method="post" action="{{ route('reviewer.questions.flags.store', $question) }}" x-data="{ flag: '{{ old('flag', '') }}' }">
                    @csrf
                    <p class="mb-4">Cờ đỏ cần ghi chú lý do để admin xử lý.</p>
                    @foreach ($flags as $flag)
                        <label class="mb-3 flex items-center gap-2"><input type="radio" name="flag" value="{{ $flag->value }}" x-model="flag" required @checked(old('flag') === $flag->value)>{{ $flag->label() }}</label>
                    @endforeach
                    <label for="reviewer-note" class="mb-2 block">Ghi chú</label>
                    <textarea id="reviewer-note" name="note" rows="4" :required="flag === 'red'" class="w-full rounded-lg border border-outline-variant p-3">{{ old('note') }}</textarea>
                    <button class="mt-4 w-full rounded-lg bg-primary px-4 py-2 text-on-primary">Gửi cờ</button>
                </form>
            @else
                <p class="text-on-surface-variant">Bạn đã gắn cờ cho câu hỏi này.</p>
            @endif
        </div>
    </div>
</x-layouts.reviewer>
