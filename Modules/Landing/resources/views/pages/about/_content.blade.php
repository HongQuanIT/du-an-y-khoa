    <!-- 1. Hero Section -->
    <section class="bg-[#F0FDFA] py-16 md:py-24 px-margin-mobile md:px-gutter">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="font-display text-3xl sm:text-4xl md:text-display text-primary mb-6">{{ $content['hero']['title'] }}</h1>
            <p class="font-body-lg text-body-lg text-text-secondary max-w-2xl mx-auto">{{ $content['hero']['subtitle'] }}</p>
        </div>
    </section>

    <!-- 2. Câu chuyện ra đời -->
    <section class="py-16 md:py-24 px-margin-mobile md:px-gutter max-w-container-max mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 md:gap-16 items-center">
            <div class="space-y-6">
                <h2 class="font-headline-lg text-headline-lg text-on-background">{{ $content['story']['heading'] }}</h2>
                <p class="font-body-md text-body-md text-text-secondary leading-relaxed">{{ $content['story']['paragraph_1'] }}</p>
                <p class="font-body-md text-body-md text-text-secondary leading-relaxed">{{ $content['story']['paragraph_2'] }}</p>
                <div class="flex items-center gap-4 pt-4">
                    <div class="w-12 h-1 bg-primary rounded-full"></div>
                    <span class="font-label-md text-label-md text-primary uppercase tracking-wider">{{ $content['story']['tagline'] }}</span>
                </div>
            </div>
            <div class="relative group">
                <div class="absolute -inset-4 bg-primary/10 rounded-2xl -z-10 transition-transform group-hover:scale-105"></div>
                <div class="w-full aspect-[4/3] rounded-xl overflow-hidden shadow-xl">
                    <img class="w-full h-full object-cover" alt="{{ $content['story']['image_alt'] }}"
                        src="{{ $content['story']['image_url'] }}">
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Giá trị cốt lõi -->
    <section class="py-16 md:py-20 bg-surface-container-low px-margin-mobile md:px-gutter">
        <div class="max-w-container-max mx-auto text-center mb-16">
            <h2 class="font-headline-lg text-headline-lg text-on-background mb-4">{{ $content['values']['heading'] }}</h2>
            <div class="w-16 h-1 bg-primary mx-auto rounded-full"></div>
        </div>
        <div class="max-w-container-max mx-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8">
            @foreach (['verified', 'psychology', 'groups', 'shield_lock'] as $index => $icon)
                @php $value = $content['values']['items'][$index] ?? ['title' => '', 'description' => '']; @endphp
                <div class="bg-surface p-8 rounded-xl border border-border hover:shadow-lg transition-all text-center">
                    <div class="w-16 h-16 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="material-symbols-outlined text-3xl">{{ $icon }}</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm text-on-background mb-3">{{ $value['title'] }}</h3>
                    <p class="font-body-sm text-body-sm text-text-secondary">{{ $value['description'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 4. Thành tựu -->
    <section class="py-16 bg-white border-y border-border">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter grid grid-cols-2 md:grid-cols-4 gap-8">
            @foreach ($content['stats']['items'] as $stat)
                <div class="text-center">
                    <p class="font-display text-[32px] md:text-[40px] leading-tight text-primary font-bold">{{ $stat['value'] }}</p>
                    <p class="font-label-md text-label-md text-text-secondary">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 5. Đội ngũ chuyên gia -->
    <section class="py-16 md:py-24 px-margin-mobile md:px-gutter max-w-container-max mx-auto">
        <div class="text-center mb-16">
            <h2 class="font-headline-lg text-headline-lg text-on-background mb-4">{{ $content['experts']['heading'] }}</h2>
            <p class="font-body-md text-body-md text-text-secondary">{{ $content['experts']['subtitle'] }}</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-8 md:gap-12">
            @foreach ($content['experts']['items'] as $expert)
                <div class="text-center group">
                    <div class="w-28 h-28 md:w-32 md:h-32 mx-auto mb-6 rounded-full overflow-hidden border-4 border-white shadow-md group-hover:scale-105 transition-transform">
                        <img alt="{{ $expert['name'] }}" class="w-full h-full object-cover" src="{{ $expert['image_url'] }}">
                    </div>
                    <h4 class="font-headline-sm text-headline-sm text-on-background">{{ $expert['name'] }}</h4>
                    <p class="font-body-sm text-body-sm text-primary font-medium">{{ $expert['role'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 6. Đối tác -->
    <section class="py-16 bg-white border-t border-border overflow-hidden">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter">
            <p class="text-center font-label-md text-label-md text-text-secondary uppercase tracking-[0.2em] mb-12">{{ $content['partners']['label'] }}</p>
            <div class="flex flex-wrap justify-center items-center gap-12 md:gap-24 opacity-50 grayscale hover:grayscale-0 transition-all">
                @foreach ($content['partners']['items'] as $partner)
                    <div class="h-12 flex items-center">
                        <span class="font-bold text-lg md:text-xl text-on-surface">{{ $partner }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- 7. CTA Section -->
    <section class="py-16 md:py-24 px-margin-mobile md:px-gutter">
        <div class="max-w-container-max mx-auto bg-primary rounded-[2rem] p-12 md:p-20 text-center relative overflow-hidden shadow-2xl">
            <div class="relative z-10">
                <h2 class="font-display text-3xl md:text-[48px] text-on-primary mb-8">{{ $content['cta']['title'] }}</h2>
                <p class="font-body-lg text-body-lg text-on-primary-container max-w-2xl mx-auto mb-10">{{ $content['cta']['subtitle'] }}</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}"
                        class="px-10 py-4 bg-white text-primary font-bold rounded-xl shadow-lg hover:bg-on-primary-container transition-all flex items-center justify-center gap-2">
                        {{ $content['cta']['primary_label'] }}
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                    <a href="{{ route('landing.features') }}"
                        class="px-10 py-4 border border-white/30 text-white font-bold rounded-xl hover:bg-white/10 transition-all text-center">
                        {{ $content['cta']['secondary_label'] }}
                    </a>
                </div>
            </div>
        </div>
    </section>
