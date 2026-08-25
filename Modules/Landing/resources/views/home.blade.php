<x-layouts.public :seo="$seo">
    @php $c = $content; @endphp
    <div class="mx-auto max-w-container-max px-margin-mobile pt-4 md:px-gutter md:pt-6">
        <x-cms.announcement-banners placement="landing" />
    </div>

    <!-- Hero Section -->
    <section class="relative pt-12 md:pt-24 pb-20 overflow-hidden">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="space-y-8 text-center lg:text-left">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[18px]"
                            style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                        {{ $c['hero']['badge'] }}
                    </div>
                    @php
                        $heroTitle = (string) ($c['hero']['title'] ?? '');
                        $titleHighlight = (string) ($c['hero']['title_highlight'] ?? '');
                    @endphp
                    <h1 class="font-display text-3xl sm:text-4xl md:text-display leading-tight tracking-tight">
                        @if ($titleHighlight !== '' && str_contains($heroTitle, $titleHighlight))
                            @php
                                $hlPos = mb_strpos($heroTitle, $titleHighlight);
                                $titleBefore = mb_substr($heroTitle, 0, $hlPos);
                                $titleAfter = mb_substr($heroTitle, $hlPos + mb_strlen($titleHighlight));
                            @endphp
                            {{ $titleBefore }}<span class="text-primary">{{ $titleHighlight }}</span>{{ $titleAfter }}
                        @else
                            {{ $heroTitle }}
                        @endif
                    </h1>
                    <p class="font-body-lg text-body-lg text-text-secondary max-w-xl mx-auto lg:mx-0">
                        {{ $c['hero']['subtitle'] }}
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <x-public.auth-cta
                            :guest-label="$c['hero']['primary_cta_label']"
                            auth-label="Tạo phiên học"
                            class="bg-primary-container text-on-primary-container px-8 py-4 rounded-xl font-label-md text-label-md shadow-lg shadow-primary-container/20 hover:shadow-xl transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </x-public.auth-cta>
                        <a href="#sample-question"
                            class="border border-border bg-surface text-on-surface px-8 py-4 rounded-xl font-label-md text-label-md hover:bg-surface-container-low transition-colors flex items-center justify-center gap-2">
                            {{ $c['hero']['secondary_cta_label'] }}
                        </a>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -top-12 -left-12 w-64 h-64 bg-primary/10 rounded-full blur-3xl -z-10"></div>
                    <div class="absolute -bottom-12 -right-12 w-64 h-64 bg-secondary/10 rounded-full blur-3xl -z-10">
                    </div>
                    <div class="rounded-2xl border border-border shadow-2xl overflow-hidden bg-white/50 backdrop-blur-sm">
                        <img class="w-full aspect-video object-cover"
                            alt="{{ $c['hero']['image_alt'] }}"
                            src="{{ $c['hero']['image_url'] }}">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Bar -->
    <section class="bg-surface border-y border-border py-8">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="flex flex-wrap justify-around items-center gap-8 text-center">
                @foreach ($c['stats']['items'] as $index => $stat)
                    @if ($index > 0)
                        <div class="w-px h-12 bg-border hidden md:block"></div>
                    @endif
                    <div class="space-y-1">
                        <div class="font-headline-lg text-headline-lg text-primary">{{ $stat['value'] }}</div>
                        <div class="font-label-md text-label-md text-text-secondary uppercase tracking-wider">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Value Props -->
    <section class="py-16 md:py-24">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @php
                    $valueIcons = [
                        ['icon' => 'database', 'wrap' => 'bg-primary/10 text-primary'],
                        ['icon' => 'bolt', 'wrap' => 'bg-secondary/10 text-secondary'],
                        ['icon' => 'psychology', 'wrap' => 'bg-warning/10 text-warning'],
                    ];
                @endphp
                @foreach ($c['values']['items'] as $index => $value)
                    @php $iconMeta = $valueIcons[$index] ?? $valueIcons[0]; @endphp
                    <div class="premium-card p-8 flex flex-col items-start gap-6">
                        <div class="w-12 h-12 {{ $iconMeta['wrap'] }} rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-3xl">{{ $iconMeta['icon'] }}</span>
                        </div>
                        <div class="space-y-3">
                            <h3 class="font-headline-sm text-headline-sm">{{ $value['title'] }}</h3>
                            <p class="font-body-md text-body-md text-text-secondary">{{ $value['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Features Sections -->
    <section class="py-12 space-y-24 md:space-y-32">
        @php
            $featureEyebrowColors = ['text-primary', 'text-secondary', 'text-warning', 'text-primary'];
            $featureBlocks = $c['feature_blocks']['items'] ?? [];
        @endphp

        {{-- Feature 1: QBank (bullets) --}}
        @php $fb = $featureBlocks[0] ?? []; @endphp
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="space-y-6">
                    <div class="{{ $featureEyebrowColors[0] }} font-label-md text-label-md uppercase tracking-widest">{{ $fb['eyebrow'] ?? '' }}
                    </div>
                    <h2 class="font-headline-lg text-headline-lg">{{ $fb['title'] ?? '' }}</h2>
                    <p class="font-body-lg text-body-lg text-text-secondary">{{ $fb['body'] ?? '' }}</p>
                    <ul class="space-y-4">
                        @foreach ($fb['bullets'] ?? [] as $bullet)
                            <li class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-success">check_circle</span>
                                <span class="font-body-md text-body-md text-on-surface">{{ $bullet }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="rounded-2xl border border-border shadow-xl overflow-hidden order-first lg:order-none">
                    <img class="w-full" alt="{{ $fb['image_alt'] ?? '' }}"
                        src="{{ $fb['image_url'] ?? '' }}">
                </div>
            </div>
        </div>

        {{-- Feature 2: Library (mini_cards) --}}
        @php $fb = $featureBlocks[1] ?? []; @endphp
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="rounded-2xl border border-border shadow-xl overflow-hidden">
                    <img class="w-full" alt="{{ $fb['image_alt'] ?? '' }}"
                        src="{{ $fb['image_url'] ?? '' }}">
                </div>
                <div class="space-y-6">
                    <div class="{{ $featureEyebrowColors[1] }} font-label-md text-label-md uppercase tracking-widest">{{ $fb['eyebrow'] ?? '' }}</div>
                    <h2 class="font-headline-lg text-headline-lg">{{ $fb['title'] ?? '' }}</h2>
                    <p class="font-body-lg text-body-lg text-text-secondary">{{ $fb['body'] ?? '' }}</p>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach ($fb['mini_cards'] ?? [] as $card)
                            <div class="p-4 bg-surface-container-low rounded-xl border border-border">
                                <div class="font-label-md text-label-md text-primary mb-1">{{ $card['title'] }}</div>
                                <div class="text-body-sm text-text-secondary">{{ $card['description'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Feature 3: AI Assistant (chat) --}}
        @php $fb = $featureBlocks[2] ?? []; @endphp
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="space-y-6">
                    <div class="{{ $featureEyebrowColors[2] }} font-label-md text-label-md uppercase tracking-widest">{{ $fb['eyebrow'] ?? '' }}</div>
                    <h2 class="font-headline-lg text-headline-lg">{{ $fb['title'] ?? '' }}</h2>
                    <p class="font-body-lg text-body-lg text-text-secondary">{{ $fb['body'] ?? '' }}</p>
                    <div class="bg-surface-container-high/50 p-6 rounded-2xl border border-primary/20 space-y-4">
                        <div class="flex gap-3">
                            <div
                                class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold shrink-0">
                                U</div>
                            <div class="bg-surface p-3 rounded-lg border border-border text-body-sm">{{ $fb['chat_user'] ?? '' }}</div>
                        </div>
                        <div class="flex gap-3">
                            <div
                                class="w-8 h-8 rounded-full bg-warning flex items-center justify-center text-white text-xs font-bold shrink-0">
                                AI</div>
                            <div class="bg-warning/10 p-3 rounded-lg border border-warning/20 text-body-sm">{{ $fb['chat_ai'] ?? '' }}</div>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-border shadow-xl overflow-hidden order-first lg:order-none">
                    <img class="w-full" alt="{{ $fb['image_alt'] ?? '' }}"
                        src="{{ $fb['image_url'] ?? '' }}">
                </div>
            </div>
        </div>

        {{-- Feature 4: Analytics (metrics) --}}
        @php $fb = $featureBlocks[3] ?? []; @endphp
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                <div class="rounded-2xl border border-border shadow-xl overflow-hidden">
                    <img class="w-full" alt="{{ $fb['image_alt'] ?? '' }}"
                        src="{{ $fb['image_url'] ?? '' }}">
                </div>
                <div class="space-y-6">
                    <div class="{{ $featureEyebrowColors[3] }} font-label-md text-label-md uppercase tracking-widest">{{ $fb['eyebrow'] ?? '' }}
                    </div>
                    <h2 class="font-headline-lg text-headline-lg">{{ $fb['title'] ?? '' }}</h2>
                    <p class="font-body-lg text-body-lg text-text-secondary">{{ $fb['body'] ?? '' }}</p>
                    <div class="flex items-center gap-6">
                        @foreach ($fb['metrics'] ?? [] as $mIndex => $metric)
                            @if ($mIndex > 0)
                                <div class="w-px h-10 bg-border"></div>
                            @endif
                            <div class="text-center">
                                <div class="text-headline-sm font-bold {{ $mIndex === 0 ? 'text-success' : 'text-secondary' }}">{{ $metric['value'] }}</div>
                                <div class="text-label-sm text-text-secondary">{{ $metric['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Interactive QBank Widget -->
    <section id="sample-question" class="py-16 md:py-24 bg-surface-container-low scroll-mt-20">
        <div class="max-w-3xl mx-auto px-margin-mobile md:px-gutter">
            <div x-data="{ selected: '', checked: false, answer: 'A' }" class="premium-card bg-surface overflow-hidden shadow-2xl">
                <div class="bg-primary-container p-6 text-on-primary-container flex justify-between items-center">
                    <h3 class="font-headline-sm text-headline-sm">Câu hỏi mẫu</h3>
                    <span class="font-label-sm text-label-sm px-2 py-1 rounded bg-white/20">Nội khoa · Tim mạch</span>
                </div>
                <div class="p-6 md:p-8 space-y-6">
                    <div class="font-body-lg text-body-lg font-medium leading-relaxed">
                        Bệnh nhân nam 58 tuổi tiền sử THA, ĐTĐ típ 2, vào viện vì đau ngực trái dữ dội sau xương ức kéo
                        dài 45 phút, không đỡ khi nghỉ ngơi. Điện tâm đồ ghi nhận đoạn ST chênh lên ở V1-V4. Chẩn đoán
                        ưu tiên nhất là:
                    </div>
                    <div class="space-y-3">
                        @foreach (['A' => 'Nhồi máu cơ tim cấp ST chênh lên vùng trước rộng', 'B' => 'Đau thắt ngực ổn định', 'C' => 'Phình tách động mạch chủ ngực', 'D' => 'Viêm màng ngoài tim cấp', 'E' => 'Thuyên tắc phổi cấp'] as $key => $text)
                            <label
                                class="flex items-center gap-4 p-4 rounded-xl border cursor-pointer transition-colors"
                                :class="{
                                    'border-success bg-success/10': checked && answer === '{{ $key }}',
                                    'border-error bg-error/10': checked && selected === '{{ $key }}' && answer !== '{{ $key }}',
                                    'border-border hover:bg-surface-container': !checked || (selected !== '{{ $key }}' && answer !== '{{ $key }}'),
                                }">
                                <input class="w-5 h-5 text-primary border-outline-variant focus:ring-primary"
                                    name="sample_q" type="radio" value="{{ $key }}" x-model="selected"
                                    :disabled="checked">
                                <span class="font-body-md text-body-md text-on-surface">{{ $key }}. {{ $text }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="pt-4 flex justify-end">
                        <button type="button" @click="if (selected) checked = true"
                            class="px-8 py-3 rounded-xl font-label-md text-label-md text-white hover:opacity-90 active:scale-95 transition-all"
                            :class="{
                                'bg-primary': !checked,
                                'bg-success': checked && selected === answer,
                                'bg-error': checked && selected !== answer,
                            }"
                            x-text="!checked ? 'Kiểm tra' : (selected === answer ? 'Chính xác! Xem giải thích' : 'Chưa đúng, thử lại')"
                            @click.away="">Kiểm tra</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-16 md:py-24">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter text-center space-y-16">
            <div class="space-y-4">
                <h2 class="font-headline-lg text-headline-lg">{{ $c['testimonials']['heading'] }}</h2>
                <p class="font-body-lg text-body-lg text-text-secondary max-w-2xl mx-auto">{{ $c['testimonials']['subtitle'] }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach ($c['testimonials']['items'] as $t)
                    <div class="premium-card p-8 text-left space-y-6">
                        <div class="flex gap-1 text-warning">
                            @for ($i = 0; $i < 5; $i++)
                                <span class="material-symbols-outlined"
                                    style="font-variation-settings: 'FILL' 1;">star</span>
                            @endfor
                        </div>
                        <p class="font-body-md text-body-md text-on-surface italic">"{{ $t['quote'] }}"</p>
                        <div class="flex items-center gap-4">
                            <img class="w-12 h-12 rounded-full object-cover" src="{{ $t['image_url'] }}"
                                alt="{{ $t['name'] }}">
                            <div>
                                <div class="font-label-md text-label-md">{{ $t['name'] }}</div>
                                <div class="text-label-sm text-text-secondary">{{ $t['role'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section class="py-16 md:py-24 bg-surface-container-lowest"
        x-data="{
            years: 1,
            plans: {
                1: { label: '1 năm', total: 1790000, perMonth: 149000, save: 25, months: 12 },
                2: { label: '2 năm', total: 2990000, perMonth: 124000, save: 37, months: 24 },
                3: { label: '3 năm', total: 3990000, perMonth: 110000, save: 44, months: 36 },
            },
            monthlyPrice: 199000,
            format(n) {
                return new Intl.NumberFormat('vi-VN').format(n) + '₫';
            },
            get plan() {
                return this.plans[this.years];
            },
            listPrice() {
                return this.monthlyPrice * this.plan.months;
            },
        }">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <div class="text-center space-y-4 mb-10">
                <h2 class="font-headline-lg text-headline-lg">{{ $c['pricing']['heading'] }}</h2>
                <p class="font-body-lg text-body-lg text-text-secondary">{{ $c['pricing']['subtitle'] }}
                </p>
            </div>

            <div class="flex flex-col items-center gap-3 mb-12">
                <p class="font-label-sm text-label-sm text-on-surface-variant">Xem bảng giá theo năm</p>
                <div class="relative inline-flex p-1 pt-3 rounded-2xl bg-surface-container-low border border-border"
                    role="tablist" aria-label="Chọn thời hạn gói năm">
                    <template x-for="y in [1, 2, 3]" :key="y">
                        <button type="button" role="tab" @click="years = y" :aria-selected="years === y"
                            :class="years === y
                                ? 'bg-surface text-primary shadow-sm border border-border'
                                : 'text-on-surface-variant hover:text-on-surface border border-transparent'"
                            class="relative min-w-[5.5rem] sm:min-w-[6.5rem] px-4 py-2.5 rounded-xl font-label-md text-label-md transition-all">
                            <span x-text="plans[y].label"></span>
                            <span x-show="y === 3"
                                class="absolute -top-3.5 left-1/2 -translate-x-1/2 premium-badge px-2 py-0.5 rounded-full text-white text-[10px] font-bold whitespace-nowrap leading-none pointer-events-none">
                                Tiết kiệm nhất
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start relative">
                <!-- Free -->
                <div class="bg-surface border border-border p-8 rounded-xl flex flex-col hover:shadow-lg transition-shadow">
                    <div class="mb-8">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">{{ $c['pricing']['free']['name'] }}</h3>
                        <p class="text-text-secondary font-body-sm text-body-sm">{{ $c['pricing']['free']['description'] }}</p>
                    </div>
                    <div class="mb-8">
                        <span class="text-headline-lg font-bold">0₫</span>
                        <span class="text-text-secondary">/tháng</span>
                    </div>
                    <ul class="space-y-4 mb-12 flex-grow">
                        @foreach ($c['pricing']['free']['features_included'] as $feature)
                            <li class="flex items-center gap-3 font-body-sm text-body-sm">
                                <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                                {{ $feature }}
                            </li>
                        @endforeach
                        @foreach ($c['pricing']['free']['features_excluded'] as $feature)
                            <li class="flex items-center gap-3 font-body-sm text-body-sm opacity-50">
                                <span class="material-symbols-outlined text-[20px]">cancel</span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    <x-public.auth-cta
                        :guest-label="$c['pricing']['free']['cta_label']"
                        auth-label="Tạo phiên học"
                        class="w-full py-3 px-4 border border-border text-on-surface font-label-md text-label-md rounded-xl hover:bg-surface-container-low transition-colors text-center" />
                </div>

                <!-- Premium yearly -->
                <div class="bg-surface premium-border p-8 rounded-xl flex flex-col relative shadow-2xl md:scale-105 z-10">
                    <div
                        class="absolute -top-4 left-1/2 -translate-x-1/2 premium-badge px-4 py-1 rounded-full text-white text-label-sm font-bold flex items-center gap-1 whitespace-nowrap">
                        <span class="material-symbols-outlined text-[14px]"
                            style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                        <span x-text="years === 3 ? 'Tiết kiệm nhất' : 'Giá theo năm'"></span>
                    </div>
                    <div class="mb-8">
                        <h3 class="font-headline-sm text-headline-sm text-primary mb-2">
                            Premium <span x-text="plan.label"></span>
                        </h3>
                        <p class="text-text-secondary font-body-sm text-body-sm">{{ $c['pricing']['premium_yearly']['description'] }}</p>
                    </div>
                    <div class="mb-2">
                        <span class="text-headline-lg font-bold" x-text="format(plan.total)"></span>
                        <span class="text-text-secondary">/<span x-text="plan.label"></span></span>
                    </div>
                    <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant line-through"
                        x-text="'Giá lẻ: ' + format(listPrice())"></p>
                    <div class="mb-8 p-3 bg-primary-fixed/20 rounded-lg">
                        <p class="text-primary font-label-md text-label-md">
                            Chỉ ~<span x-text="format(plan.perMonth)"></span>/tháng · tiết kiệm <span x-text="plan.save"></span>%
                        </p>
                    </div>
                    <ul class="space-y-4 mb-12 flex-grow">
                        <li class="flex items-center gap-3 font-body-sm text-body-sm font-medium">
                            <span class="material-symbols-outlined text-success text-[20px]"
                                style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            Toàn bộ tính năng Premium
                        </li>
                        @foreach ($c['pricing']['premium_yearly']['features'] as $feature)
                            <li class="flex items-center gap-3 font-body-sm text-body-sm">
                                <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ auth()->check() ? route('subscription.upgrade') : route('register') }}"
                        class="w-full py-3 px-4 bg-primary text-on-primary font-label-md text-label-md rounded-xl hover:opacity-90 transition-opacity text-center">
                        {{ $c['pricing']['premium_yearly']['cta_label_prefix'] }} <span x-text="plan.label"></span>
                    </a>
                </div>

                <!-- Premium monthly -->
                <div class="bg-surface border border-border p-8 rounded-xl flex flex-col hover:shadow-lg transition-shadow">
                    <div class="mb-8">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">{{ $c['pricing']['premium_monthly']['name'] }}</h3>
                        <p class="text-text-secondary font-body-sm text-body-sm">{{ $c['pricing']['premium_monthly']['description'] }}</p>
                    </div>
                    <div class="mb-8">
                        <span class="text-headline-lg font-bold">199.000₫</span>
                        <span class="text-text-secondary">/tháng</span>
                    </div>
                    <div class="mb-8 p-3 bg-surface-container-low rounded-lg">
                        <p class="text-on-surface-variant font-label-md text-label-md">{{ $c['pricing']['premium_monthly']['note'] }}</p>
                    </div>
                    <ul class="space-y-4 mb-12 flex-grow">
                        @foreach ($c['pricing']['premium_monthly']['features'] as $feature)
                            <li class="flex items-center gap-3 font-body-sm text-body-sm">
                                <span class="material-symbols-outlined text-success text-[20px]">check_circle</span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ auth()->check() ? route('subscription.upgrade') : route('register') }}"
                        class="w-full py-3 px-4 bg-primary-container text-on-primary-container font-label-md text-label-md rounded-xl hover:opacity-90 transition-opacity text-center">{{ $c['pricing']['premium_monthly']['cta_label'] }}</a>
                </div>
            </div>

            <p class="text-center mt-10">
                <a href="{{ route('landing.pricing') }}"
                    class="font-label-md text-label-md text-primary hover:underline inline-flex items-center gap-1">
                    {{ $c['pricing']['detail_link_label'] }}
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </p>
        </div>
    </section>

    <!-- FAQ Accordion -->
    <section class="py-16 md:py-24">
        <div class="max-w-3xl mx-auto px-margin-mobile md:px-gutter space-y-8">
            <h2 class="font-headline-lg text-headline-lg text-center">{{ $c['faq']['heading'] }}</h2>
            <div class="space-y-4">
                @foreach ($c['faq']['items'] as $faq)
                    <details class="group bg-surface rounded-xl border border-border overflow-hidden">
                        <summary
                            class="flex justify-between items-center p-6 cursor-pointer list-none font-label-md text-label-md text-on-surface">
                            {{ $faq['question'] }}
                            <span
                                class="material-symbols-outlined group-open:rotate-180 transition-transform">expand_more</span>
                        </summary>
                        <div class="px-6 pb-6 text-body-md text-text-secondary border-t border-border/50 pt-4">
                            {{ $faq['answer'] }}
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Bottom CTA -->
    <section class="py-20 px-margin-mobile md:px-gutter">
        <div
            class="max-w-container-max mx-auto bg-primary-container rounded-3xl p-12 md:p-20 text-center space-y-8 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
                <svg fill="none" height="100%" preserveAspectRatio="none" viewBox="0 0 100 100" width="100%">
                    <path d="M0 100 Q 25 0 50 100 T 100 0" fill="none" stroke="white" stroke-width="0.5"></path>
                    <path d="M0 0 Q 50 100 100 0" fill="none" stroke="white" stroke-width="0.5"></path>
                </svg>
            </div>
            <h2 class="font-headline-lg text-headline-lg text-on-primary-container relative">{{ $c['cta']['title'] }}
            </h2>
            <p class="font-body-lg text-body-lg text-on-primary-container/80 max-w-2xl mx-auto relative">{{ $c['cta']['subtitle'] }}</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center relative">
                <x-public.auth-cta
                    :guest-label="$c['cta']['primary_label']"
                    auth-label="Tạo phiên học"
                    class="bg-white text-primary px-8 py-4 rounded-xl font-label-md text-label-md shadow-lg hover:bg-primary-fixed transition-all" />
                <a href="{{ route('landing.contact') }}"
                    class="bg-white/10 text-white border border-white/20 px-8 py-4 rounded-xl font-label-md text-label-md hover:bg-white/20 transition-all">{{ $c['cta']['secondary_label'] }}</a>
            </div>
        </div>
    </section>
</x-layouts.public>
