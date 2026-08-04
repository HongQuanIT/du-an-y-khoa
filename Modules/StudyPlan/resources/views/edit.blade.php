<x-layouts.auth title="Chỉnh sửa kế hoạch">
    <div class="mx-auto w-full max-w-container-max px-margin-mobile py-8 md:px-margin-desktop md:py-12">
        <header class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1
                    class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface md:font-headline-lg md:text-headline-lg">
                    Chỉnh sửa kế hoạch</h1>
                <p class="mt-2 font-body-md text-body-md text-on-surface-variant">
                    Các ngày đã học được giữ nguyên; những ngày còn lại sẽ được phân bổ lại.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('study-plan.detail', $plan) }}" aria-label="Đóng"
                    class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-surface-container-high">
                    <span class="material-symbols-outlined text-on-surface-variant">close</span>
                </a>
            </div>
        </header>

        @include('studyplan::partials.plan-form', [
            'formAction' => route('study-plan.update', $plan),
            'formMethod' => 'PUT',
            'cancelUrl' => route('study-plan.detail', $plan),
            'submitLabel' => 'Lưu thay đổi',
        ])
    </div>
</x-layouts.auth>
