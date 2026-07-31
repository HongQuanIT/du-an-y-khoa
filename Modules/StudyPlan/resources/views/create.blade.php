@php
    // Static port of html/pc-study-path-create.html. Placeholders until wizard saves.
    $exams = [
        [
            'id' => 'resident',
            'icon' => 'stethoscope',
            'title' => 'Bác sĩ nội trú',
            'hint' => 'Dành cho sinh viên Y6 chuẩn bị thi BSCKNT.',
        ],
        [
            'id' => 'course',
            'icon' => 'menu_book',
            'title' => 'Thi hết học phần',
            'hint' => 'Ôn thi các môn lâm sàng & cận lâm sàng.',
        ],
        [
            'id' => 'usmle',
            'icon' => 'workspace_premium',
            'title' => 'USMLE Step 1',
            'hint' => 'Chuẩn bị cho kỳ thi cấp phép hành nghề Mỹ.',
        ],
    ];

    $scopes = ['Nội khoa', 'Tim mạch', 'Hô hấp', 'Tiêu hóa', 'Ngoại khoa', 'Nhi khoa', 'Sản khoa'];
    $selectedScopes = ['Nội khoa', 'Tim mạch', 'Hô hấp', 'Tiêu hóa'];
    $hours = ['1h', '2h', '3h', '4h', '5h+'];
    $weekdays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    $selectedDays = ['T2', 'T3', 'T4', 'T5', 'T6'];

    $preview = [
        ['icon' => 'summarize', 'label' => 'Tổng khối lượng', 'value' => '3.360 câu hỏi'],
        ['icon' => 'timer', 'label' => 'Thời gian dự kiến', 'value' => '84 ngày'],
        ['icon' => 'flag', 'label' => 'Mục tiêu hàng ngày', 'value' => '40 câu/ngày'],
    ];
@endphp

<x-layouts.auth title="Tạo kế hoạch học tập">
    <div class="mx-auto w-full max-w-container-max px-margin-mobile py-8 md:px-margin-desktop md:py-12"
        x-data="{
            exam: 'resident',
            scopes: @js($selectedScopes),
            intensity: 40,
            hours: '2h',
            days: @js($selectedDays),
            strategy: 'fixed',
            toggle(list, value) {
                const i = list.indexOf(value);
                if (i === -1) list.push(value);
                else list.splice(i, 1);
            },
        }">
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

        <div class="flex flex-col gap-gutter lg:flex-row">
            <!-- Left: Wizard -->
            <div class="flex-1 space-y-8">
                <div class="flex items-center gap-4 md:hidden">
                    <div
                        class="flex size-10 items-center justify-center rounded-full bg-primary font-headline-sm text-headline-sm text-on-primary">
                        1</div>
                    <div>
                        <span
                            class="block font-label-sm text-label-sm tracking-wider text-primary uppercase">Bước
                            1/4</span>
                        <span class="font-headline-sm text-headline-sm">Kỳ thi mục tiêu</span>
                    </div>
                </div>

                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm">
                    <!-- Exam -->
                    <section class="mb-10">
                        <h2 class="mb-4 flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                            <span class="material-symbols-outlined text-primary">school</span>
                            Chọn kỳ thi mục tiêu
                        </h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                            @foreach ($exams as $exam)
                                <button type="button" @click="exam = '{{ $exam['id'] }}'"
                                    @class([
                                        'relative cursor-pointer rounded-lg p-4 text-left transition-colors border',
                                    ])
                                    :class="exam === '{{ $exam['id'] }}'
                                        ? 'border-2 border-primary bg-[#f0fdfa]'
                                        : 'border-outline-variant bg-surface hover:border-primary/50'">
                                    <div class="absolute top-4 right-4 text-primary" x-show="exam === '{{ $exam['id'] }}'"
                                        x-cloak>
                                        <span class="material-symbols-outlined"
                                            style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                    </div>
                                    <span class="material-symbols-outlined mb-2 text-3xl"
                                        :class="exam === '{{ $exam['id'] }}' ? 'text-primary' : 'text-on-surface-variant'">{{ $exam['icon'] }}</span>
                                    <h3 class="mb-1 font-label-md text-label-md text-on-surface">{{ $exam['title'] }}
                                    </h3>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $exam['hint'] }}</p>
                                </button>
                            @endforeach
                        </div>
                    </section>

                    <div class="mb-10 h-px w-full bg-outline-variant"></div>

                    <!-- Date -->
                    <section class="mb-10">
                        <h2 class="mb-4 flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                            <span class="material-symbols-outlined text-primary">calendar_month</span>
                            Ngày thi dự kiến
                        </h2>
                        <div class="max-w-md">
                            <label class="mb-2 block font-label-md text-label-md text-on-surface-variant">Chọn
                                ngày</label>
                            <div class="relative">
                                <input type="text" readonly value="15/06/2026"
                                    class="w-full rounded-lg border border-outline-variant bg-surface px-4 py-3 font-body-md text-body-md transition-all focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none">
                                <span
                                    class="material-symbols-outlined pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-on-surface-variant">calendar_today</span>
                            </div>
                        </div>
                    </section>

                    <div class="mb-10 h-px w-full bg-outline-variant"></div>

                    <!-- Scope -->
                    <section class="mb-10">
                        <h2 class="mb-4 flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                            <span class="material-symbols-outlined text-primary">category</span>
                            Phạm vi ôn tập
                        </h2>
                        <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant">Chọn các chuyên khoa bạn muốn
                            tập trung (có thể thay đổi sau).</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($scopes as $scope)
                                <button type="button" @click="toggle(scopes, '{{ $scope }}')"
                                    class="flex items-center gap-2 rounded-md px-4 py-2 font-label-md text-label-md transition-colors"
                                    :class="scopes.includes('{{ $scope }}')
                                        ? 'bg-[#e6f2f1] text-[#005c55] border border-[#0f766e]'
                                        : 'bg-surface border border-outline-variant text-on-surface-variant hover:bg-surface-container'">
                                    <span class="material-symbols-outlined text-sm"
                                        x-show="scopes.includes('{{ $scope }}')" x-cloak>check</span>
                                    {{ $scope }}
                                </button>
                            @endforeach
                        </div>
                    </section>

                    <div class="mb-10 h-px w-full bg-outline-variant"></div>

                    <!-- Intensity -->
                    <section class="mb-10">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h2 class="flex items-center gap-2 font-headline-md text-headline-md text-on-surface">
                                <span class="material-symbols-outlined text-primary">speed</span>
                                Cường độ ôn tập
                            </h2>
                            <span
                                class="rounded-full bg-primary-container/10 px-3 py-1 font-label-md text-label-md text-primary"
                                x-text="intensity + ' câu/ngày ~ ' + Math.round(intensity * 2.25) + ' phút'"></span>
                        </div>
                        <div class="mb-8 px-2">
                            <input type="range" min="10" max="100" x-model.number="intensity"
                                class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-surface-variant accent-primary">
                            <div
                                class="mt-2 flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                                <span>Thảnh thơi (10/ngày)</span>
                                <span>Tập trung (100/ngày)</span>
                            </div>
                        </div>

                        <div class="mb-8">
                            <h3 class="mb-3 font-label-md text-label-md text-on-surface">Thời gian học mỗi ngày</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($hours as $hour)
                                    <button type="button" @click="hours = '{{ $hour }}'"
                                        class="rounded-md px-4 py-2 font-label-md text-label-md transition-colors"
                                        :class="hours === '{{ $hour }}'
                                            ? 'bg-[#e6f2f1] text-[#005c55] border border-[#0f766e]'
                                            : 'bg-surface border border-outline-variant text-on-surface-variant hover:bg-surface-container'">
                                        {{ $hour }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-8">
                            <h3 class="mb-3 font-label-md text-label-md text-on-surface">Ngày học trong tuần</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($weekdays as $day)
                                    <button type="button" @click="toggle(days, '{{ $day }}')"
                                        class="flex size-10 items-center justify-center rounded-full font-label-md text-label-md transition-colors"
                                        :class="days.includes('{{ $day }}')
                                            ? 'bg-[#e6f2f1] text-[#005c55] border border-[#0f766e]'
                                            : 'bg-surface border border-outline-variant text-on-surface-variant'">
                                        {{ $day }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <h3 class="mb-3 font-label-md text-label-md text-on-surface">Chiến lược phân bổ</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-lg border border-outline-variant p-4 transition-colors hover:bg-surface-container-lowest"
                                :class="strategy === 'fixed' && 'ring-1 ring-primary border-primary'">
                                <input type="radio" name="strategy" value="fixed" x-model="strategy"
                                    class="mt-1 text-primary focus:ring-primary">
                                <div>
                                    <span class="block font-label-md text-label-md text-on-surface">Cố định</span>
                                    <span
                                        class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">Số lượng
                                        câu hỏi không đổi mỗi ngày.</span>
                                </div>
                            </label>
                            <label
                                class="relative flex cursor-pointer items-start gap-3 overflow-hidden rounded-lg border border-outline-variant bg-surface p-4 transition-colors hover:bg-surface-container-lowest"
                                :class="strategy === 'adaptive' && 'ring-1 ring-primary border-primary'">
                                <input type="radio" name="strategy" value="adaptive" x-model="strategy"
                                    class="mt-1 text-primary focus:ring-primary">
                                <div class="relative z-10">
                                    <div class="flex items-center gap-2">
                                        <span class="block font-label-md text-label-md text-on-surface">Thích ứng</span>
                                        <span
                                            class="premium-gradient rounded px-2 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">Premium</span>
                                    </div>
                                    <span
                                        class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">AI tự động
                                        điều chỉnh dựa trên kết quả làm bài.</span>
                                </div>
                            </label>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Right: Preview -->
            <div class="w-full shrink-0 lg:w-[320px]">
                <div class="sticky top-24 space-y-4">
                    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm">
                        <h3
                            class="mb-4 border-b border-outline-variant pb-4 font-headline-sm text-headline-sm text-on-surface">
                            Xem trước lộ trình</h3>
                        <ul class="space-y-4">
                            @foreach ($preview as $item)
                                <li class="flex items-start gap-3">
                                    <span
                                        class="material-symbols-outlined mt-0.5 text-primary">{{ $item['icon'] }}</span>
                                    <div>
                                        <span
                                            class="block font-label-sm text-label-sm text-on-surface-variant">{{ $item['label'] }}</span>
                                        <span
                                            class="block font-label-md text-label-md text-on-surface">{{ $item['value'] }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                        <a href="{{ route('study-plan.index') }}"
                            class="flex-1 rounded-lg border border-outline-variant bg-surface-container-lowest px-4 py-3 text-center font-label-md text-label-md text-primary transition-colors hover:bg-surface-container">
                            Quay lại
                        </a>
                        <a href="{{ route('study-plan.detail') }}"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary-container px-4 py-3 font-label-md text-label-md text-white shadow-sm transition-colors hover:bg-primary">
                            <span class="material-symbols-outlined text-sm">rocket_launch</span>
                            Tạo lộ trình
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.auth>
