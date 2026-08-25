<x-layouts.public :seo="$seo">
    @php $c = $content; $b = $c['bento']; @endphp
    <!-- Hero Section -->
    <section class="pt-16 md:pt-24 pb-16 md:pb-20 px-margin-mobile md:px-gutter max-w-container-max mx-auto text-center">
        <h1 class="font-display text-3xl sm:text-4xl md:text-display text-on-surface mb-8 max-w-4xl mx-auto leading-tight">
            {{ $c['hero']['title'] }}
        </h1>
        <p class="font-body-lg text-body-lg text-text-secondary max-w-2xl mx-auto mb-12">
            {{ $c['hero']['subtitle'] }}
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4 sm:gap-6">
            <x-public.auth-cta
                :guest-label="$c['hero']['primary_cta_label']"
                auth-label="Tạo phiên học"
                class="bg-primary text-on-primary px-8 md:px-10 py-4 md:py-5 rounded-xl font-headline-sm text-headline-sm hover:shadow-xl hover:shadow-primary/20 transition-all text-center" />
            <a href="{{ $c['hero']['video_url'] }}"
                class="bg-white border border-border text-on-surface px-8 md:px-10 py-4 md:py-5 rounded-xl font-headline-sm text-headline-sm hover:bg-surface-container-low transition-all text-center">{{ $c['hero']['secondary_cta_label'] }}</a>
        </div>
    </section>

    <!-- Features Bento Grid -->
    <section class="px-margin-mobile md:px-gutter pb-24 md:pb-32 max-w-container-max mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            <!-- QBank (Large) -->
            <div
                class="md:col-span-8 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary overflow-hidden group">
                <div class="flex flex-col md:flex-row gap-10 items-center h-full">
                    <div class="flex-1">
                        <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                            <span class="material-symbols-outlined text-primary text-4xl">database</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md mb-4">{{ $b['qbank']['title'] }}</h3>
                        <p class="text-body-md text-text-secondary mb-6 leading-relaxed">{{ $b['qbank']['body'] }}</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($b['qbank']['tags'] as $tag)
                                <span
                                    class="bg-primary-fixed/30 text-primary text-label-sm px-4 py-1.5 rounded-full font-bold">{{ $tag }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div
                        class="flex-1 w-full h-64 md:h-[320px] bg-slate-50 rounded-2xl overflow-hidden border border-border shadow-inner relative">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                            alt="{{ $b['qbank']['image_alt'] }}"
                            src="{{ $b['qbank']['image_url'] }}">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/5 to-transparent"></div>
                    </div>
                </div>
            </div>

            <!-- Study/Exam Mode -->
            <div
                class="md:col-span-4 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary flex flex-col justify-between">
                <div>
                    <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <span class="material-symbols-outlined text-primary text-4xl">timer</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm mb-4">{{ $b['study_exam']['title'] }}</h3>
                    <p class="text-body-md text-text-secondary leading-relaxed">{{ $b['study_exam']['body'] }}</p>
                </div>
                <div class="mt-8 flex justify-center py-6">
                    <div class="flex items-center gap-6 bg-surface-container-low p-4 rounded-2xl border border-border/50">
                        <div
                            class="w-14 h-14 bg-primary rounded-xl flex items-center justify-center text-white shadow-lg shadow-primary/20">
                            <span class="material-symbols-outlined">menu_book</span>
                        </div>
                        <div class="w-16 h-1 bg-border rounded-full relative">
                            <div class="absolute inset-y-0 left-0 w-1/2 bg-primary rounded-full"></div>
                        </div>
                        <div
                            class="w-14 h-14 bg-white rounded-xl border border-border flex items-center justify-center text-on-surface-variant">
                            <span class="material-symbols-outlined">history_edu</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flashcards -->
            <div
                class="md:col-span-4 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary">
                <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary text-4xl">style</span>
                </div>
                <h3 class="font-headline-sm text-headline-sm mb-4">{{ $b['flashcards']['title'] }}</h3>
                <p class="text-body-md text-text-secondary leading-relaxed mb-6">{{ $b['flashcards']['body'] }}</p>
                <div
                    class="h-32 bg-surface-container-lowest rounded-xl border border-dashed border-border flex items-center justify-center gap-4">
                    <div
                        class="w-16 h-24 bg-white border border-border rounded shadow-sm rotate-[-6deg] flex items-center justify-center text-primary/40">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div
                        class="w-16 h-24 bg-white border border-border rounded shadow-sm relative z-10 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined">school</span>
                    </div>
                    <div
                        class="w-16 h-24 bg-white border border-border rounded shadow-sm rotate-[6deg] flex items-center justify-center text-primary/40">
                        <span class="material-symbols-outlined">clinical_notes</span>
                    </div>
                </div>
            </div>

            <!-- AI Tutor (Large) -->
            <div
                class="md:col-span-8 bg-primary-container text-white border border-transparent rounded-2xl p-8 md:p-10 feature-card overflow-hidden group">
                <div class="flex flex-col md:flex-row gap-10 h-full relative">
                    <div class="flex-1 relative z-10">
                        <div
                            class="bg-white/20 w-14 h-14 rounded-xl flex items-center justify-center mb-6 backdrop-blur-sm">
                            <span class="material-symbols-outlined text-white text-4xl"
                                style="font-variation-settings: 'FILL' 1;">smart_toy</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md mb-4">{{ $b['ai_tutor']['title'] }}</h3>
                        <p class="opacity-90 mb-10 text-body-lg leading-relaxed">{{ $b['ai_tutor']['body'] }}</p>
                        <x-public.auth-cta
                            :guest-label="$b['ai_tutor']['cta_label']"
                            auth-label="Tạo phiên học"
                            class="inline-block bg-white text-primary px-8 py-3.5 rounded-xl font-label-md hover:bg-opacity-95 transition-all shadow-lg" />
                    </div>
                    <div
                        class="flex-1 w-full h-64 md:h-[280px] bg-black/10 rounded-2xl overflow-hidden border border-white/10 backdrop-blur-sm self-center">
                        <img class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000"
                            alt="{{ $b['ai_tutor']['image_alt'] }}"
                            src="{{ $b['ai_tutor']['image_url'] }}">
                    </div>
                </div>
            </div>

            <!-- Analytics (Medium) -->
            <div
                class="md:col-span-6 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary group">
                <div class="flex justify-between items-start mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary text-3xl">insights</span>
                        </div>
                        <h3 class="font-headline-sm text-headline-sm">{{ $b['analytics']['title'] }}</h3>
                    </div>
                    <span class="bg-success/10 text-success text-label-md px-3 py-1 rounded-full font-bold shrink-0">{{ $b['analytics']['badge'] }}</span>
                </div>
                <div class="relative rounded-2xl overflow-hidden border border-border mb-6">
                    <img class="w-full h-56 object-cover" alt="{{ $b['analytics']['image_alt'] }}"
                        src="{{ $b['analytics']['image_url'] }}">
                    <div class="absolute inset-0 bg-gradient-to-t from-white/20 to-transparent"></div>
                </div>
                <p class="text-body-md text-text-secondary leading-relaxed">{{ $b['analytics']['body'] }}</p>
            </div>

            <!-- Library (Medium) -->
            <div
                class="md:col-span-6 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary group">
                <div class="flex justify-between items-start mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary text-3xl">library_books</span>
                        </div>
                        <h3 class="font-headline-sm text-headline-sm">{{ $b['library']['title'] }}</h3>
                    </div>
                    <span
                        class="material-symbols-outlined text-text-secondary group-hover:text-primary transition-colors cursor-pointer">open_in_new</span>
                </div>
                <div class="relative rounded-2xl overflow-hidden border border-border mb-6">
                    <img class="w-full h-56 object-cover" alt="{{ $b['library']['image_alt'] }}"
                        src="{{ $b['library']['image_url'] }}">
                </div>
                <p class="text-body-md text-text-secondary leading-relaxed">{{ $b['library']['body'] }}</p>
            </div>

            <!-- Personalized Path -->
            <div
                class="md:col-span-4 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary">
                <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary text-4xl">route</span>
                </div>
                <h3 class="font-headline-sm text-headline-sm mb-4">{{ $b['path']['title'] }}</h3>
                <p class="text-body-md text-text-secondary leading-relaxed">{{ $b['path']['body'] }}</p>
            </div>

            <!-- Exam Simulation (Large) -->
            <div
                class="md:col-span-8 bg-surface border border-border rounded-2xl p-8 md:p-10 feature-card hover:border-primary flex items-center gap-12">
                <div class="flex-1">
                    <div class="w-14 h-14 bg-primary/10 rounded-xl flex items-center justify-center mb-6">
                        <span class="material-symbols-outlined text-primary text-4xl">fact_check</span>
                    </div>
                    <h3 class="font-headline-md text-headline-md mb-4">{{ $b['exam_sim']['title'] }}</h3>
                    <p class="text-body-md text-text-secondary leading-relaxed">{{ $b['exam_sim']['body'] }}</p>
                </div>
                <div
                    class="hidden md:flex flex-shrink-0 w-44 h-44 rounded-full border-[10px] border-primary/10 items-center justify-center relative">
                    <div
                        class="absolute inset-0 rounded-full border-[10px] border-primary border-t-transparent border-l-transparent rotate-[45deg]">
                    </div>
                    <div class="text-center">
                        <span class="text-primary font-bold text-4xl block">{{ $b['exam_sim']['stat_value'] }}</span>
                        <span class="text-label-sm text-text-secondary uppercase tracking-widest font-bold">{{ $b['exam_sim']['stat_label'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-primary/5 py-16 md:py-24 px-margin-mobile md:px-gutter text-center border-y border-border">
        <div class="max-w-3xl mx-auto">
            <h2 class="font-headline-lg text-3xl md:text-display text-on-surface mb-6">{{ $c['cta']['title'] }}</h2>
            <p class="text-body-lg text-text-secondary mb-12">{{ $c['cta']['subtitle'] }}</p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <x-public.auth-cta
                    :guest-label="$c['cta']['primary_label']"
                    auth-label="Tạo phiên học"
                    class="bg-primary text-white px-12 py-5 rounded-xl font-headline-sm hover:shadow-2xl hover:shadow-primary/30 transition-all hover:-translate-y-1 text-center" />
            </div>
            <p class="mt-6 text-label-sm text-text-secondary flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-success">verified</span>
                {{ $c['cta']['footnote'] }}
            </p>
        </div>
    </section>
</x-layouts.public>
