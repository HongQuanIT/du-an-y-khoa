<x-layouts.auth title="Tạo kế hoạch học tập">
    <div class="mx-auto w-full max-w-container-max px-margin-mobile py-8 md:px-margin-desktop md:py-12">
        <header class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1
                    class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface md:font-headline-lg md:text-headline-lg">
                    Tạo kế hoạch học tập</h1>
                <p class="mt-2 font-body-md text-body-md text-on-surface-variant">Thiết lập mục tiêu và cá nhân hóa lịch
                    ôn tập của bạn.</p>
            </div>
            <a href="{{ route('study-plan.index') }}" aria-label="Đóng"
                class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-surface-container-high">
                <span class="material-symbols-outlined text-on-surface-variant">close</span>
            </a>
        </header>

        @include('studyplan::partials.plan-form', [
            'formAction' => route('study-plan.store'),
            'cancelUrl' => route('study-plan.index'),
            'submitLabel' => 'Tạo lộ trình',
            'plan' => null,
        ])
    </div>
</x-layouts.auth>
