<x-layouts.auth title="Tạo kế hoạch học tập">
    <div class="mx-auto w-full max-w-container-max px-margin-mobile py-8 md:px-margin-desktop md:py-12">
        @include('studyplan::partials.plan-form', [
            'formAction' => route('study-plan.store'),
            'cancelUrl' => route('study-plan.index'),
            'submitLabel' => 'Tạo kế hoạch',
            'plan' => null,
        ])
    </div>
</x-layouts.auth>
