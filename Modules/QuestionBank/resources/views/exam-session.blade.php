@php
    // Static port of html/pc-exam-session.html. Placeholders until timed exam state lands.
    $options = [
        'A' => 'Nhồi máu cơ tim cấp vùng trước (STEMI)',
        'B' => 'Cơn đau thắt ngực không ổn định',
        'C' => 'Phình tách động mạch chủ ngực',
        'D' => 'Viêm màng ngoài tim cấp',
        'E' => 'Thuyên tắc phổi cấp',
    ];

    $current = 12;
    $total = 40;
    $answered = range(1, 11);
    $flagged = [15];

    $nav = collect(range(1, $total))->map(function (int $n) use ($current, $answered, $flagged) {
        return [
            'n' => $n,
            'state' => match (true) {
                $n === $current => 'active',
                in_array($n, $answered, true) => 'answered',
                in_array($n, $flagged, true) => 'flagged',
                default => 'empty',
            },
        ];
    })->all();
@endphp

<x-layouts.auth title="Chế độ thi">
    <div class="overflow-hidden bg-[#f7faf8] text-on-background" x-data="{
        selected: 'B',
        pauseOpen: false,
        mobileNav: false,
        toastOpen: true,
        seconds: 56 * 60 + 38,
        get timer() {
            const m = Math.floor(this.seconds / 60).toString().padStart(2, '0');
            const s = (this.seconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        },
        init() {
            setInterval(() => { if (this.seconds > 0) this.seconds--; }, 1000);
        },
    }">
        <header
            class="fixed inset-x-0 top-0 z-50 flex h-16 items-center justify-between border-b border-outline-variant bg-surface px-4 md:px-8">
            <div class="flex items-center gap-6">
                <h1 class="font-headline-sm text-headline-sm font-bold text-primary">{{ config('app.name') }}</h1>
                <div class="hidden items-center gap-2 rounded-full bg-secondary-fixed/30 px-3 py-1 md:flex">
                    <span class="material-symbols-outlined text-sm text-primary"
                        style="font-variation-settings: 'FILL' 1;">timer</span>
                    <span class="font-headline-sm text-primary tabular-nums" x-text="timer">56:38</span>
                </div>
                <span class="font-label-md text-on-surface-variant">Câu {{ $current }}/{{ $total }}</span>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" title="Đánh dấu câu hỏi"
                    class="group rounded-full p-2 transition-colors hover:bg-surface-variant">
                    <span
                        class="material-symbols-outlined text-on-surface-variant group-hover:text-tertiary">flag</span>
                </button>
                <button type="button" title="Cài đặt"
                    class="rounded-full p-2 transition-colors hover:bg-surface-variant">
                    <span class="material-symbols-outlined text-on-surface-variant">more_vert</span>
                </button>
                <button type="button" @click="pauseOpen = true"
                    class="ml-2 rounded-lg bg-error px-5 py-2 font-label-md text-white transition-colors hover:bg-red-700">
                    Thoát
                </button>
            </div>
        </header>

        <main class="flex h-screen flex-col overflow-hidden pt-16 md:flex-row">
            <div class="relative flex h-full flex-1 flex-col bg-white">
                <div class="custom-scrollbar flex-1 overflow-y-auto p-6 pb-32 md:p-10">
                    <div class="mx-auto flex max-w-5xl flex-col gap-12">
                        <div class="space-y-6">
                            <div class="flex items-center gap-3">
                                <span
                                    class="rounded bg-primary px-3 py-1 text-label-sm tracking-wider text-white uppercase">Lâm
                                    sàng</span>
                                <span class="font-label-sm text-on-surface-variant">Nội khoa / Tim mạch</span>
                            </div>
                            <article class="max-w-none">
                                <h2 class="mb-4 font-headline-md text-headline-md text-on-surface">Trường hợp lâm sàng
                                </h2>
                                <p class="text-body-md leading-relaxed text-on-surface">
                                    Một bệnh nhân nam 58 tuổi nhập viện vì đau ngực dữ dội sau xương ức khởi phát đột
                                    ngột khi đang nghỉ ngơi cách đây 2 giờ. Đau lan lên cằm và tay trái, kèm vã mồ hôi,
                                    buồn nôn. Tiền sử tăng huyết áp 10 năm, hút thuốc lá 20 gói-năm.
                                </p>
                                <p class="mt-4 text-body-md leading-relaxed text-on-surface">
                                    Khám lâm sàng: Mạch 96 lần/phút, HA 150/90 mmHg, nhịp thở 20 lần/phút. Tim T1, T2 đều,
                                    rõ, không có tiếng thổi. Phổi không rale.
                                </p>
                                <p class="mt-4 text-body-md leading-relaxed text-on-surface">
                                    Cận lâm sàng: Điện tâm đồ (ECG) cho thấy đoạn ST chênh lên ở các chuyển đạo V1-V4. Men
                                    tim Troponin I đang tăng.
                                </p>
                                <div
                                    class="mt-8 rounded-xl border border-outline-variant bg-surface-container-low p-4 text-on-surface-variant italic">
                                    Câu hỏi: Chẩn đoán sơ bộ phù hợp nhất đối với bệnh nhân này là gì?
                                </div>
                            </article>
                        </div>

                        <div class="space-y-4">
                            <h3 class="mb-4 flex items-center gap-2 font-label-md text-on-surface-variant">
                                <span class="material-symbols-outlined text-sm">edit_note</span>
                                Chọn đáp án đúng nhất
                            </h3>
                            <div class="grid grid-cols-1 gap-3">
                                @foreach ($options as $key => $text)
                                    <button type="button" @click="selected = '{{ $key }}'"
                                        class="option-button group flex items-center gap-4 rounded-xl border p-5 text-left transition-all"
                                        :class="selected === '{{ $key }}'
                                            ? 'selected border-primary'
                                            : 'border-outline-variant'">
                                        <span
                                            class="flex size-10 items-center justify-center rounded-lg font-bold transition-colors"
                                            :class="selected === '{{ $key }}'
                                                ? 'bg-primary text-white'
                                                : 'bg-surface-container-high group-hover:bg-primary-fixed'">{{ $key }}</span>
                                        <span class="text-body-md font-medium"
                                            :class="selected === '{{ $key }}' ? 'text-primary' : ''">{{ $text }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <footer
                    class="absolute inset-x-0 bottom-0 z-40 flex items-center justify-between border-t border-outline-variant bg-white px-6 py-4">
                    <button type="button"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-on-surface-variant transition-all hover:bg-surface-variant active:scale-95">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span class="font-label-md">Câu trước</span>
                    </button>
                    <div class="flex gap-2">
                        <button type="button" @click="mobileNav = !mobileNav"
                            class="flex size-10 items-center justify-center rounded-full bg-surface-container-high md:hidden">
                            <span class="material-symbols-outlined">grid_view</span>
                        </button>
                        <button type="button"
                            class="flex items-center gap-2 rounded-lg bg-primary px-6 py-2 text-white shadow-sm transition-all hover:bg-primary-container active:scale-95">
                            <span class="font-label-md">Câu tiếp theo</span>
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </button>
                    </div>
                </footer>
            </div>

            <aside
                class="z-40 h-full w-full flex-col border-l border-outline-variant bg-surface-container-lowest md:flex md:w-80"
                :class="mobileNav ? 'flex' : 'hidden md:flex'">
                <div class="border-b border-outline-variant p-6">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Tiến độ bài thi</h3>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="flex items-center gap-1">
                            <div class="size-3 rounded-sm bg-primary"></div>
                            <span class="text-[10px] font-medium text-on-surface-variant">Đã làm (11)</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="size-3 rounded-sm border border-outline"></div>
                            <span class="text-[10px] font-medium text-on-surface-variant">Chưa làm (29)</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="size-3 rounded-sm bg-tertiary-container"></div>
                            <span class="text-[10px] font-medium text-on-surface-variant">Flag (1)</span>
                        </div>
                    </div>
                </div>

                <div class="custom-scrollbar flex-1 overflow-y-auto p-4">
                    <div class="grid grid-cols-5 gap-3 md:grid-cols-4 lg:grid-cols-5">
                        @foreach ($nav as $item)
                            @if ($item['state'] === 'answered')
                                <button type="button"
                                    class="flex aspect-square items-center justify-center rounded-lg bg-primary text-sm font-bold text-white transition-opacity hover:opacity-90">
                                    {{ $item['n'] }}
                                </button>
                            @elseif ($item['state'] === 'active')
                                <button type="button"
                                    class="flex aspect-square items-center justify-center rounded-lg border-2 border-primary bg-primary/5 text-sm font-extrabold text-primary shadow-inner">
                                    {{ $item['n'] }}
                                </button>
                            @elseif ($item['state'] === 'flagged')
                                <button type="button"
                                    class="relative flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-outline-variant text-sm text-on-surface-variant transition-colors hover:bg-surface">
                                    <div
                                        class="flag-corner absolute top-0 right-0 size-4 bg-tertiary-container"></div>
                                    {{ $item['n'] }}
                                </button>
                            @else
                                <button type="button"
                                    class="flex aspect-square items-center justify-center rounded-lg border border-outline-variant text-sm text-on-surface-variant transition-colors hover:bg-surface">
                                    {{ $item['n'] }}
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-outline-variant bg-surface-container-low p-6">
                    <button type="button" @click="pauseOpen = true"
                        class="w-full rounded-xl bg-secondary py-3 font-headline-sm text-white shadow-md transition-all hover:bg-blue-700 active:scale-[0.98]">
                        Nộp Bài Ngay
                    </button>
                </div>
            </aside>
        </main>

        <div x-show="toastOpen" x-cloak
            class="animate-toast fixed bottom-20 left-4 z-[60] md:right-80 md:left-auto md:mr-8">
            <div
                class="flex items-center gap-3 rounded-xl border border-tertiary/20 bg-tertiary-container px-5 py-3 text-on-tertiary-container shadow-lg">
                <span class="material-symbols-outlined text-tertiary">warning</span>
                <div>
                    <p class="font-label-md font-bold">Còn 5 phút!</p>
                    <p class="text-xs opacity-90">Vui lòng kiểm tra lại các câu chưa trả lời.</p>
                </div>
                <button type="button" class="ml-2 hover:opacity-70" @click="toastOpen = false">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        </div>

        {{-- Pause / exit popup from html/pc-exam-session-pause-map.html --}}
        <div x-show="pauseOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
            @keydown.escape.window="pauseOpen = false">
            <div class="absolute inset-0" @click="pauseOpen = false"></div>
            <div
                class="animate-toast relative flex w-full max-w-md flex-col items-center rounded-[24px] border border-outline-variant bg-surface-container-lowest p-8 text-center shadow-2xl">
                <div class="mb-6 flex size-16 items-center justify-center rounded-2xl bg-primary-container/10">
                    <span class="material-symbols-outlined text-4xl text-primary"
                        style="font-variation-settings: 'FILL' 1;">pause</span>
                </div>
                <h3 class="mb-3 font-headline-md text-headline-md text-on-surface">Bạn muốn thoát?</h3>
                <p class="mb-10 font-body-md text-body-md leading-relaxed text-on-surface-variant">
                    Tiến trình đã được lưu, có thể tiếp tục sau từ Trang chủ. Đừng lo lắng về dữ liệu của bạn.
                </p>
                <div class="flex w-full flex-col gap-3">
                    <a href="{{ route('qbank.index') }}"
                        class="w-full rounded-xl bg-gradient-to-br from-primary-container to-primary py-3.5 font-label-md text-label-md font-bold text-white shadow-lg transition-all hover:opacity-90 active:scale-[0.98]">
                        Lưu &amp; thoát
                    </a>
                    <button type="button" @click="pauseOpen = false"
                        class="w-full rounded-xl border border-outline py-3.5 font-label-md text-label-md font-bold text-primary transition-colors hover:bg-surface-container-high">
                        Tiếp tục làm bài
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.auth>
