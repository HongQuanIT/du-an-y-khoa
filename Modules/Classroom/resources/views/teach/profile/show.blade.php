@php
    $titles = [
        'profile' => ['title' => 'Hồ sơ giảng dạy', 'description' => 'Thông tin hiển thị khi bạn host lớp live.'],
        'contact' => ['title' => 'Liên hệ', 'description' => 'Email đăng nhập và tên hiển thị.'],
        'security' => ['title' => 'Bảo mật', 'description' => 'Đổi mật khẩu tài khoản giảng viên.'],
        'appearance' => ['title' => 'Giao diện', 'description' => 'Chế độ sáng, tối hoặc theo hệ thống.'],
    ];
    $meta = $titles[$tab] ?? $titles['profile'];
@endphp

<x-layouts.teach :title="$meta['title']">
    <div class="mx-auto w-full max-w-[1040px]">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
            <aside class="lg:w-56 lg:shrink-0">
                @include('classroom::teach.profile.partials.nav', ['active' => $tab])
            </aside>

            <div class="min-w-0 flex-1 space-y-6">
                <header class="space-y-1">
                    <h2 class="font-headline-lg text-headline-lg-mobile text-on-surface md:text-headline-lg">
                        {{ $meta['title'] }}
                    </h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">{{ $meta['description'] }}</p>
                </header>

                @if (session('status'))
                    <div role="alert"
                        class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3">
                        <span class="material-symbols-outlined mt-0.5 shrink-0 text-[20px] text-primary">check_circle</span>
                        <p class="font-body-md text-body-md text-on-surface">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert"
                        class="rounded-xl border border-error/30 bg-error-container px-4 py-3 font-body-md text-body-md text-on-error-container">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('classroom::teach.profile.partials.panels')
            </div>
        </div>
    </div>
</x-layouts.teach>
