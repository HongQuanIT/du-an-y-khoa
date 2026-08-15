@php
    use App\Support\Html\SafeHtml;
    use Modules\Admin\Support\Enums\FaqCategory;

    $activeCategory = FaqCategory::TaiKhoan;
    if (request()->has('danh-muc')) {
        $requested = FaqCategory::tryFrom((string) request('danh-muc'));
        if ($requested !== null) {
            $activeCategory = $requested;
        }
    }

    $hasPublishedFaqs = $faqsByCategory->flatten()->isNotEmpty();
@endphp

<x-layouts.public :seo="$seo">
    <div x-data="{ query: '' }"
        x-effect="document.querySelectorAll('[data-faq-item]').forEach(el => {
            const text = el.dataset.faqText || '';
            const q = query.trim().toLowerCase();
            el.classList.toggle('hidden', q !== '' && !text.includes(q));
        })">
    <!-- Header Section -->
    <header class="py-16 md:py-24 text-center px-margin-mobile md:px-gutter">
        <div class="max-w-3xl mx-auto">
            <h1 class="font-headline-lg text-headline-lg text-on-surface mb-4">Câu hỏi thường gặp</h1>
            <p class="font-body-lg text-body-lg text-text-secondary mb-8">Chúng tôi có thể giúp gì cho bạn? Tìm kiếm câu
                trả lời nhanh chóng cho các vấn đề thường gặp.</p>
            <div class="relative max-w-2xl mx-auto">
                <span
                    class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary">search</span>
                <input x-model="query"
                    class="w-full pl-12 pr-4 h-12 bg-surface border border-border rounded-xl font-body-md text-body-md text-on-surface focus:outline-none focus:border-primary-container focus:ring-1 focus:ring-primary-container transition-all shadow-sm"
                    placeholder="Nhập câu hỏi hoặc từ khóa..." type="search">
            </div>
        </div>
    </header>

    <!-- Main Content Grid -->
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-gutter pb-16 md:pb-24">
        @if (! $hasPublishedFaqs)
            <div class="rounded-2xl border border-border bg-surface p-12 text-center">
                <span class="material-symbols-outlined mb-4 text-4xl text-text-secondary">help</span>
                <h2 class="font-headline-md text-headline-md text-on-surface mb-2">Sắp ra mắt</h2>
                <p class="font-body-md text-text-secondary">Nội dung FAQ đang được cập nhật. Vui lòng quay lại sau.</p>
            </div>
        @else
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Left Sidebar (Categories) -->
                <aside class="w-full md:w-1/4 flex-shrink-0">
                    <div class="bg-surface rounded-xl border border-border p-4 md:sticky md:top-24">
                        <h2 class="font-label-md text-label-md text-text-secondary uppercase tracking-wider mb-4 px-3">Danh
                            mục hỗ trợ</h2>
                        <nav class="space-y-1">
                            @foreach ($categories as $cat)
                                @php
                                    $count = ($faqsByCategory->get($cat->value) ?? collect())->count();
                                    $isActive = $activeCategory === $cat;
                                @endphp
                                @if ($count > 0)
                                    <a @class([
                                        'flex items-center px-3 py-2 rounded-lg font-label-md text-label-md transition-colors',
                                        'bg-[#F1F5F9] border-l-2 border-primary-container text-primary-container' => $isActive,
                                        'text-text-secondary hover:bg-background hover:text-on-surface' => ! $isActive,
                                    ]) href="{{ route('landing.faq', ['danh-muc' => $cat->value]) }}#faq-category-{{ $cat->value }}">
                                        <span class="material-symbols-outlined mr-3">{{ $cat->icon() }}</span>
                                        {{ $cat->label() }}
                                    </a>
                                @endif
                            @endforeach
                        </nav>
                    </div>
                </aside>

                <!-- Right Content (Accordions) -->
                <section class="w-full md:w-3/4 space-y-12">
                    @foreach ($categories as $cat)
                        @php $categoryFaqs = $faqsByCategory->get($cat->value) ?? collect(); @endphp
                        @if ($categoryFaqs->isNotEmpty())
                            <div id="faq-category-{{ $cat->value }}">
                                <h2 class="font-headline-md text-headline-md text-on-surface mb-6">{{ $cat->label() }}</h2>
                                <div class="space-y-4">
                                    @foreach ($categoryFaqs as $faq)
                                        <details id="faq-{{ $faq->id }}" data-faq-item
                                            data-faq-text="{{ Str::lower($faq->question.' '.SafeHtml::plainText($faq->answer)) }}"
                                            class="group bg-surface border border-border rounded-xl overflow-hidden">
                                            <summary
                                                class="w-full flex justify-between items-center p-6 text-left cursor-pointer list-none">
                                                <span
                                                    class="font-headline-sm text-headline-sm text-on-surface pr-4">{{ $faq->question }}</span>
                                                <span
                                                    class="material-symbols-outlined text-text-secondary transition-transform group-open:rotate-180 shrink-0">expand_more</span>
                                            </summary>
                                            <div
                                                class="px-6 pb-6 pt-4 text-text-secondary font-body-md text-body-md border-t border-border prose prose-sm max-w-none">
                                                {!! SafeHtml::forDisplay($faq->answer) !!}
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </section>
            </div>
        @endif
    </div>

    <!-- CTA Section -->
    <section class="max-w-container-max mx-auto px-margin-mobile md:px-gutter pb-16 md:pb-24">
        <div class="bg-surface-container-low border border-border rounded-2xl p-8 md:p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white border border-border mb-6">
                <span class="material-symbols-outlined text-primary-container text-3xl">support_agent</span>
            </div>
            <h3 class="font-headline-md text-headline-md text-on-surface mb-3">Vẫn cần trợ giúp?</h3>
            <p class="font-body-md text-body-md text-text-secondary mb-8 max-w-lg mx-auto">Nếu bạn không tìm thấy câu trả
                lời ở đây, đừng ngần ngại liên hệ với đội ngũ hỗ trợ của chúng tôi. Chúng tôi luôn sẵn sàng hỗ trợ bạn
                24/7.</p>
            <a class="inline-flex items-center justify-center bg-primary-container text-on-primary px-6 py-3 rounded-xl font-label-md text-label-md hover:bg-primary transition-colors shadow-sm"
                href="{{ route('landing.contact') }}">
                Liên hệ hỗ trợ
                <span class="material-symbols-outlined ml-2 text-sm">arrow_forward</span>
            </a>
        </div>
    </section>
    </div>
</x-layouts.public>
