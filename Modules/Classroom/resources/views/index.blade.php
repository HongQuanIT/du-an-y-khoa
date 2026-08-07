@php
    /** @var \Illuminate\Support\Collection<\Modules\Classroom\Models\Classroom> $publicClassrooms */
    /** @var \Illuminate\Support\Collection<\Modules\Classroom\Models\Classroom> $myClassrooms */
    /** @var \Illuminate\Support\Collection<\Modules\Classroom\Models\Classroom> $liveNow */
@endphp

<x-layouts.app title="Classroom">
    <div class="mx-auto max-w-[1200px] space-y-8 p-6 md:p-8">
        <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Classroom</h1>
                <p class="mt-2 font-body-md text-body-md text-on-surface-variant">
                    Lớp chữa đề livestream — tham gia cộng đồng hoặc host buổi live của bạn.
                </p>
            </div>
            @if ($canHost)
                <a href="{{ route('classroom.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 font-semibold text-white shadow-md transition hover:opacity-90">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Tạo lớp
                </a>
            @else
                <a href="{{ route('landing.pricing') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-low px-5 py-3 font-semibold text-on-surface transition hover:bg-surface-container">
                    <span class="material-symbols-outlined text-[20px]">workspace_premium</span>
                    Nâng cấp để host
                </a>
            @endif
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm text-primary">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('classroom.index') }}"
                class="rounded-full px-3 py-1 text-sm {{ empty($filter) ? 'bg-primary text-white' : 'bg-surface-container-low text-on-surface' }}">Tất cả</a>
            <a href="{{ route('classroom.index', ['filter' => 'live']) }}"
                class="rounded-full px-3 py-1 text-sm {{ ($filter ?? '') === 'live' ? 'bg-error text-white' : 'bg-surface-container-low text-on-surface' }}">Đang live</a>
        </div>

        @if ($liveNow->isNotEmpty())
            <section>
                <h2 class="mb-4 flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                    <span class="inline-flex size-2 rounded-full bg-error animate-pulse"></span>
                    Đang live
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($liveNow as $classroom)
                        @include('classroom::partials.card', ['classroom' => $classroom, 'highlightLive' => true])
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <h2 class="mb-4 font-headline-sm text-headline-sm text-on-surface">Lớp của tôi</h2>
            @if ($myClassrooms->isEmpty())
                <p class="rounded-xl border border-dashed border-outline-variant bg-surface-container-low px-4 py-8 text-center text-on-surface-variant">
                    Bạn chưa tham gia lớp nào. Khám phá lớp công khai bên dưới hoặc tạo lớp mới.
                </p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($myClassrooms as $classroom)
                        @include('classroom::partials.card', ['classroom' => $classroom])
                    @endforeach
                </div>
            @endif
        </section>

        <section>
            <h2 class="mb-4 font-headline-sm text-headline-sm text-on-surface">Lớp công khai</h2>
            @if ($publicClassrooms->isEmpty())
                <p class="rounded-xl border border-dashed border-outline-variant bg-surface-container-low px-4 py-8 text-center text-on-surface-variant">
                    Chưa có lớp công khai. Hãy là người đầu tiên tạo lớp chữa đề.
                </p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($publicClassrooms as $classroom)
                        @include('classroom::partials.card', ['classroom' => $classroom])
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
