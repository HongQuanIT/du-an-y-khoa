@php
    // Static port of html/pc-question-review.html. Placeholders until review state persists.
    $filters = ['Tất cả', 'Đúng', 'Sai', 'Bỏ qua', 'Đã đánh dấu'];

    $questions = [
        [
            'id' => 'Q12',
            'result' => 'correct',
            'icon' => 'check_circle',
            'iconClass' => 'text-green-600',
            'topic' => 'Nội khoa',
            'topicClass' => 'bg-secondary-fixed text-on-secondary-fixed',
            'excerpt' => 'Bệnh nhân nam 45 tuổi nhập viện vì đau ngực trái dữ dội sau xương ức, kéo dài 30 phút...',
            'pick' => 'Bạn chọn <strong class="text-primary">B</strong> • Đúng <strong class="text-green-600">B</strong>',
            'flagged' => false,
            'flagClass' => 'text-outline',
            'active' => true,
            'dimmed' => false,
        ],
        [
            'id' => 'Q13',
            'result' => 'wrong',
            'icon' => 'cancel',
            'iconClass' => 'text-error',
            'topic' => 'Ngoại khoa',
            'topicClass' => 'bg-surface-container-highest text-on-surface-variant',
            'excerpt' => 'Các biến chứng muộn sau phẫu thuật cắt dạ dày bao gồm tất cả các trường hợp sau ngoại trừ...',
            'pick' => 'Bạn chọn <strong class="text-error">C</strong> • Đúng <strong class="text-green-600">A</strong>',
            'flagged' => true,
            'flagClass' => 'text-orange-400',
            'active' => false,
            'dimmed' => false,
        ],
        [
            'id' => 'Q14',
            'result' => 'skipped',
            'icon' => 'horizontal_rule',
            'iconClass' => 'text-outline',
            'topic' => 'Sản khoa',
            'topicClass' => 'bg-surface-container-highest text-on-surface-variant',
            'excerpt' => 'Triệu chứng lâm sàng quan trọng nhất để chẩn đoán thai ngoài tử cung chưa vỡ là...',
            'pick' => 'Chưa trả lời • Đúng <strong class="text-green-600">D</strong>',
            'flagged' => false,
            'flagClass' => null,
            'active' => false,
            'dimmed' => false,
        ],
        [
            'id' => 'Q15',
            'result' => 'correct',
            'icon' => 'check_circle',
            'iconClass' => 'text-green-600',
            'topic' => 'Nhi khoa',
            'topicClass' => 'bg-surface-container-highest text-on-surface-variant',
            'excerpt' => 'Phác đồ bù dịch trong tiêu chảy cấp mất nước nặng ở trẻ 10 tháng tuổi là...',
            'pick' => null,
            'flagged' => false,
            'flagClass' => null,
            'active' => false,
            'dimmed' => true,
        ],
    ];

    $options = [
        ['key' => 'A', 'text' => 'Đau thắt ngực không ổn định', 'state' => 'neutral'],
        ['key' => 'B', 'text' => 'Nhồi máu cơ tim cấp vùng trước rộng', 'state' => 'correct_selected'],
        ['key' => 'C', 'text' => 'Viêm màng ngoài tim cấp', 'state' => 'dimmed'],
        ['key' => 'D', 'text' => 'Bóc tách động mạch chủ ngực', 'state' => 'dimmed'],
    ];
@endphp

<x-layouts.app title="Xem lại câu hỏi">
    <div class="flex h-[calc(100vh-64px)] flex-col overflow-hidden md:flex-row" x-data="{
        filter: 'Tất cả',
        showDetail: false,
        openDetail() {
            if (window.innerWidth < 768) this.showDetail = true;
        },
        closeDetail() {
            this.showDetail = false;
        },
    }" @resize.window="if (window.innerWidth >= 768) showDetail = false">
        <section
            class="z-20 flex w-full flex-col border-r border-outline-variant bg-white transition-transform duration-300 md:w-[400px]"
            :class="{ 'hidden md:flex': showDetail }">
            <div class="border-b border-outline-variant bg-surface-container-lowest p-4">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-headline-sm text-headline-sm">Danh sách câu hỏi</h2>
                    <span
                        class="rounded-full bg-primary-container px-2 py-0.5 text-[10px] font-bold text-on-primary-container">40/50
                        Hoàn thành</span>
                </div>
                <div class="custom-scrollbar flex gap-2 overflow-x-auto pb-2">
                    @foreach ($filters as $filter)
                        <button type="button" @click="filter = '{{ $filter }}'"
                            :class="filter === '{{ $filter }}'
                                ? 'bg-primary text-white shadow-sm'
                                : 'bg-surface-container-high text-on-surface-variant hover:bg-outline-variant'"
                            class="font-label-sm text-label-sm whitespace-nowrap rounded-full px-4 py-1.5 transition-colors">
                            {{ $filter }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="custom-scrollbar flex-1 overflow-y-auto">
                @foreach ($questions as $q)
                    <button type="button" @click="openDetail()"
                        @class([
                            'w-full border-b border-outline-variant p-4 text-left transition-colors hover:bg-surface-container-low',
                            'border-l-4 border-l-primary bg-surface-container-highest/30' => $q['active'],
                            'opacity-70' => $q['dimmed'],
                        ])>
                        <div class="mb-2 flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    @class(['font-bold', 'text-primary' => $q['active'], 'text-on-surface' => !$q['active']])>{{ $q['id'] }}</span>
                                <span class="material-symbols-outlined text-lg {{ $q['iconClass'] }}"
                                    @if ($q['result'] !== 'skipped') style="font-variation-settings: 'FILL' 1;" @endif>{{ $q['icon'] }}</span>
                            </div>
                            <span
                                class="rounded px-2 py-0.5 text-[10px] font-semibold uppercase {{ $q['topicClass'] }}">{{ $q['topic'] }}</span>
                        </div>
                        <p
                            @class([
                                'mb-2 line-clamp-2 font-body-sm text-body-sm',
                                'text-on-surface' => $q['active'],
                                'text-on-surface-variant' => !$q['active'],
                            ])>{{ $q['excerpt'] }}</p>
                        @if ($q['pick'] || $q['flagged'])
                            <div class="flex items-center justify-between">
                                @if ($q['pick'])
                                    <span class="text-[11px] font-medium text-outline">{!! $q['pick'] !!}</span>
                                @else
                                    <span></span>
                                @endif
                                @if ($q['flagClass'] !== null)
                                    <span class="material-symbols-outlined text-sm {{ $q['flagClass'] }}"
                                        @if ($q['flagged']) style="font-variation-settings: 'FILL' 1;" @endif>flag</span>
                                @endif
                            </div>
                        @endif
                    </button>
                @endforeach
            </div>
        </section>

        <section class="custom-scrollbar relative flex-1 overflow-y-auto bg-white"
            :class="{ 'hidden md:block': !showDetail }">
            <div
                class="sticky top-0 z-30 flex items-center justify-between border-b border-outline-variant bg-surface p-4 md:hidden">
                <button type="button" @click="closeDetail()"
                    class="flex items-center gap-2 font-semibold text-primary">
                    <span class="material-symbols-outlined">arrow_back</span>
                    <span>Quay lại</span>
                </button>
                <div class="flex gap-4">
                    <span class="material-symbols-outlined">flag</span>
                    <span class="material-symbols-outlined">share</span>
                </div>
            </div>

            <div class="mx-auto max-w-4xl space-y-8 p-6 md:p-10">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span
                            class="rounded bg-primary-container px-3 py-1 text-xs font-bold tracking-widest text-white uppercase">Câu
                            12</span>
                        <span class="text-sm text-outline">•</span>
                        <span class="text-sm font-medium text-outline">ID: #MS-99210</span>
                    </div>
                    <h3 class="font-body-lg text-body-lg leading-relaxed font-semibold text-on-surface">
                        Bệnh nhân nam 45 tuổi nhập viện vì đau ngực trái dữ dội sau xương ức, kéo dài 30 phút, lan lên
                        vai trái và hàm dưới. Khám lâm sàng: HA 140/90 mmHg, nhịp tim 100 lần/phút. Điện tâm đồ ghi nhận
                        ST chênh lên ở các chuyển đạo V1-V4. Men tim Troponin I tăng cao. Chẩn đoán xác định phù hợp nhất
                        là gì?
                    </h3>
                </div>

                <div class="space-y-3">
                    @foreach ($options as $option)
                        @if ($option['state'] === 'correct_selected')
                            <div
                                class="flex items-center rounded-xl border-2 border-primary bg-primary-container/10 p-4 shadow-sm transition-all">
                                <div
                                    class="mr-4 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                                    <span class="material-symbols-outlined text-sm">check</span>
                                </div>
                                <p class="text-body-md flex-1 font-semibold text-primary">{{ $option['text'] }}</p>
                                <span
                                    class="rounded bg-primary-container px-2 py-0.5 text-[10px] font-bold text-white uppercase">Lựa
                                    chọn của bạn</span>
                            </div>
                        @elseif ($option['state'] === 'dimmed')
                            <div
                                class="flex items-center rounded-xl border border-outline-variant bg-surface-container-low p-4 opacity-60">
                                <div
                                    class="mr-4 flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-outline-variant">
                                    <span class="text-sm font-bold">{{ $option['key'] }}</span>
                                </div>
                                <p class="text-body-md flex-1">{{ $option['text'] }}</p>
                            </div>
                        @else
                            <div
                                class="group flex items-center rounded-xl border border-outline-variant bg-surface-container-low p-4 transition-all">
                                <div
                                    class="mr-4 flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-outline-variant group-hover:border-primary">
                                    <span class="text-sm font-bold">{{ $option['key'] }}</span>
                                </div>
                                <p class="text-body-md flex-1">{{ $option['text'] }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-3 border-y border-outline-variant py-4">
                    <button type="button"
                        class="flex items-center gap-2 rounded-lg bg-surface-container-high px-4 py-2 text-on-surface-variant transition-colors hover:bg-outline-variant">
                        <span class="material-symbols-outlined text-orange-500"
                            style="font-variation-settings: 'FILL' 1;">flag</span>
                        <span class="font-label-md text-label-md">Đã đánh dấu</span>
                    </button>
                    <button type="button"
                        class="flex items-center gap-2 rounded-lg bg-surface-container-high px-4 py-2 text-on-surface-variant transition-colors hover:bg-outline-variant">
                        <span class="material-symbols-outlined">edit_note</span>
                        <span class="font-label-md text-label-md">Ghi chú</span>
                    </button>
                    <button type="button"
                        class="flex items-center gap-2 rounded-lg bg-surface-container-high px-4 py-2 text-on-surface-variant transition-colors hover:bg-outline-variant">
                        <span class="material-symbols-outlined">forum</span>
                        <span class="font-label-md text-label-md">Thảo luận (24)</span>
                    </button>
                </div>



                <div class="glass-ai rounded-2xl p-6 shadow-sm">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-10 items-center justify-center rounded-full bg-gradient-to-tr from-primary to-secondary">
                                <span class="material-symbols-outlined text-white"
                                    style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                            </div>
                            <div>
                                <h4 class="font-label-md text-label-md font-bold text-primary">AI MedAssist Analysis
                                </h4>
                                <p class="text-[11px] text-outline">Gợi ý thông minh cho kỳ thi</p>
                            </div>
                        </div>
                        <span class="rounded bg-green-100 px-2 py-1 text-[10px] font-bold text-green-700">SMART
                            TIPS</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-white/80 bg-white/50 p-4">
                            <h5
                                class="mb-2 flex items-center gap-2 text-xs font-bold tracking-tight text-on-surface uppercase">
                                <span class="material-symbols-outlined text-sm text-primary">psychology</span> Key
                                Concepts
                            </h5>
                            <p class="text-xs leading-relaxed text-on-surface-variant">Luôn nhớ quy luật "vùng soi gương"
                                (reciprocal changes) trên EKG. Nếu thấy ST chênh ở V1-V4, hãy tìm ST chênh xuống ở các
                                chuyển đạo vùng dưới (II, III, aVF).</p>
                        </div>
                        <div class="rounded-xl border border-white/80 bg-white/50 p-4">
                            <h5
                                class="mb-2 flex items-center gap-2 text-xs font-bold tracking-tight text-on-surface uppercase">
                                <span class="material-symbols-outlined text-sm text-primary">trending_up</span>
                                Thống kê
                            </h5>
                            <p class="text-xs leading-relaxed text-on-surface-variant">85% học viên MedPro chọn đúng câu
                                này. Các lỗi sai thường gặp là nhầm lẫn với <strong>Viêm màng ngoài tim</strong> do cũng
                                có ST chênh (nhưng chênh lan tỏa toàn bộ chuyển đạo).</p>
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-outline-variant pt-10 pb-20 md:pb-10">
                    <button type="button"
                        class="flex items-center gap-2 text-outline transition-colors hover:text-primary">
                        <span class="material-symbols-outlined">chevron_left</span>
                        <span class="font-label-md text-label-md">Câu trước (Q11)</span>
                    </button>
                    <button type="button"
                        class="rounded-full bg-primary px-8 py-2.5 font-bold text-white shadow-md transition-all hover:scale-[1.02] hover:shadow-lg">
                        Câu tiếp theo
                    </button>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
