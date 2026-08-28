@php
    // Static port of html/pc-flashcard-review.html. Placeholders until SRS ratings persist.
    $current = 12;
    $total = 20;
    $progress = (int) round(($current / $total) * 100);
@endphp

<x-layouts.auth title="Ôn Flashcard">
    <div class="overflow-hidden bg-[#F8FAFC]" x-data="{
        flipped: false,
        animating: false,
        flip() {
            if (this.flipped || this.animating) return;
            this.flipped = true;
        },
        rate() {
            if (!this.flipped || this.animating) return;
            this.animating = true;
            const card = this.$refs.card;
            card.style.transform = 'translateY(-100vh) rotate(-10deg)';
            card.style.opacity = '0';
            setTimeout(() => {
                this.flipped = false;
                card.style.transition = 'none';
                card.style.transform = 'translateY(100vh)';
                setTimeout(() => {
                    card.style.transition = 'transform 0.6s, opacity 0.6s';
                    card.style.transform = '';
                    card.style.opacity = '1';
                    this.animating = false;
                }, 50);
            }, 400);
        },
    }" @keydown.space.window.prevent="flipped ? null : flip()"
        @keydown.1.window="flipped && rate()" @keydown.2.window="flipped && rate()"
        @keydown.3.window="flipped && rate()" @keydown.4.window="flipped && rate()">
        <header class="fixed inset-x-0 top-0 z-50 flex h-16 flex-col justify-center bg-surface px-margin-desktop">
            <div class="mb-2 flex items-center justify-between">
                <a href="{{ route('flashcards.deck') }}"
                    class="flex items-center gap-2 font-label-md text-label-md text-on-surface-variant transition-colors hover:text-primary">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                    <span>Kết thúc phiên</span>
                </a>
                <div class="flex flex-col items-center">
                    <span class="font-label-sm text-label-sm tracking-widest text-primary">TIẾN TRÌNH</span>
                    <span class="font-headline-sm text-headline-sm text-on-surface">{{ $current }} / {{ $total }}</span>
                </div>
                <div class="flex items-center gap-4">
                    <button type="button"
                        class="material-symbols-outlined text-on-surface-variant hover:text-primary">settings</button>
                    <div
                        class="flex size-8 items-center justify-center overflow-hidden rounded-full border border-outline-variant bg-primary-container text-sm font-bold text-on-primary-container">
                        {{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}
                    </div>
                </div>
            </div>
            <div class="h-1 w-full overflow-hidden rounded-full bg-surface-container-low">
                <div class="h-full bg-primary transition-all duration-500 ease-out"
                    style="width: {{ $progress }}%"></div>
            </div>
        </header>

        <main class="flex h-screen w-full items-center justify-center px-6 pt-24 pb-12">
            <div class="flashcard-container flex h-full w-full max-w-3xl flex-col items-center justify-center gap-8">
                <div class="flashcard relative aspect-[4/3] max-h-[500px] w-full" :class="{ flipped: flipped, 'cursor-pointer': !flipped }"
                    @click="!flipped && flip()"
                    x-ref="card">
                    <div
                        class="flashcard-front flex flex-col justify-center rounded-xl border border-outline-variant bg-white p-12 text-center shadow-sm">
                        <div class="mb-4">
                            <span
                                class="rounded-full bg-primary-container px-3 py-1 font-label-sm text-label-sm tracking-wider text-on-primary-container uppercase">Dược
                                lý học</span>
                        </div>
                        <h2 class="font-headline-lg text-headline-lg leading-tight text-on-surface">
                            Cơ chế tác dụng của Metformin?
                        </h2>
                        <div class="absolute right-6 bottom-6 left-6 flex items-center justify-between">
                            <span class="font-label-sm text-label-sm text-on-surface-variant">Thẻ ID: #MED-4021</span>
                            <div class="flex gap-2">
                                <span class="material-symbols-outlined text-outline-variant">edit</span>
                                <span class="material-symbols-outlined text-outline-variant">visibility_off</span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flashcard-back flex flex-col overflow-y-auto rounded-xl border border-outline-variant bg-white p-10 shadow-sm">
                        <div class="mb-6 flex items-start justify-between">
                            <span
                                class="rounded-full bg-primary-container px-3 py-1 font-label-sm text-label-sm text-on-primary-container">ĐÁP
                                ÁN CHI TIẾT</span>
                            <div class="flex gap-4">
                                <button type="button"
                                    class="flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant hover:text-primary">
                                    <span class="material-symbols-outlined text-[18px]">edit</span> Sửa
                                </button>
                                <button type="button"
                                    class="flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant hover:text-error">
                                    <span class="material-symbols-outlined text-[18px]">visibility_off</span> Ẩn
                                </button>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <section>
                                <h3 class="mb-2 font-headline-sm text-headline-sm text-primary">Cơ chế chính</h3>
                                <p class="font-body-md text-body-md leading-relaxed text-on-surface">
                                    Metformin hoạt động chủ yếu bằng cách kích hoạt enzyme
                                    <strong class="text-primary">AMPK (AMP-activated protein kinase)</strong>, dẫn đến:
                                </p>
                                <ul
                                    class="mt-3 list-inside list-disc space-y-2 font-body-md text-body-md text-on-surface-variant">
                                    <li>Ức chế quá trình tân tạo glucose tại gan (Gluconeogenesis).</li>
                                    <li>Tăng nhạy cảm với insulin ở các mô ngoại vi (cơ vân).</li>
                                    <li>Giảm hấp thu glucose tại ruột.</li>
                                </ul>
                            </section>
                            <div class="linear-divider"></div>
                            <section>
                                <h3 class="mb-2 font-headline-sm text-headline-sm text-on-surface">Lưu ý lâm sàng</h3>
                                <div class="rounded-lg border-l-4 border-tertiary bg-surface-container-low p-4">
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                                        Không gây hạ đường huyết khi dùng đơn trị liệu vì không kích thích bài tiết
                                        insulin từ tế bào beta đảo tụy. Thận trọng với nguy cơ nhiễm toan lactic.
                                    </p>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <div class="flex h-20 w-full justify-center">
                    <button type="button" x-show="!flipped" x-cloak @click="flip()"
                        class="btn-hover-scale group flex items-center gap-3 rounded-lg bg-primary px-10 py-4 font-headline-sm text-headline-sm text-on-primary shadow-md">
                        <span>Hiện đáp án</span>
                        <span
                            class="material-symbols-outlined transition-transform group-hover:translate-y-1">keyboard_double_arrow_down</span>
                    </button>

                    <div x-show="flipped" x-cloak class="grid w-full max-w-2xl grid-cols-4 gap-4">
                        <button type="button" @click="rate()"
                            class="btn-hover-scale flex flex-col items-center justify-center rounded-lg border border-red-100 bg-red-50 p-3 transition-colors hover:bg-red-100">
                            <span class="font-headline-sm text-headline-sm text-[#DC2626]">Lại</span>
                            <span class="font-label-sm text-label-sm text-[#DC2626]/70">&lt; 1 phút</span>
                        </button>
                        <button type="button" @click="rate()"
                            class="btn-hover-scale flex flex-col items-center justify-center rounded-lg border border-orange-100 bg-orange-50 p-3 transition-colors hover:bg-orange-100">
                            <span class="font-headline-sm text-headline-sm text-[#D97706]">Khó</span>
                            <span class="font-label-sm text-label-sm text-[#D97706]/70">6 phút</span>
                        </button>
                        <button type="button" @click="rate()"
                            class="btn-hover-scale flex flex-col items-center justify-center rounded-lg border border-teal-100 bg-teal-50 p-3 transition-colors hover:bg-teal-100">
                            <span class="font-headline-sm text-headline-sm text-[#0F766E]">Tốt</span>
                            <span class="font-label-sm text-label-sm text-[#0F766E]/70">1 ngày</span>
                        </button>
                        <button type="button" @click="rate()"
                            class="btn-hover-scale flex flex-col items-center justify-center rounded-lg border border-green-100 bg-green-50 p-3 transition-colors hover:bg-green-100">
                            <span class="font-headline-sm text-headline-sm text-[#16A34A]">Dễ</span>
                            <span class="font-label-sm text-label-sm text-[#16A34A]/70">4 ngày</span>
                        </button>
                    </div>
                </div>

                <div class="flex gap-6 font-label-sm text-label-sm text-outline">
                    <div class="flex items-center gap-1.5">
                        <kbd
                            class="rounded border border-outline-variant bg-white px-2 py-1 text-on-surface shadow-sm">Space</kbd>
                        <span>Lật thẻ</span>
                    </div>
                </div>
            </div>
        </main>

        <footer
            class="pointer-events-none fixed inset-x-0 bottom-0 flex h-12 items-center justify-between px-margin-desktop">
            <div class="flex items-center gap-2 opacity-40">
                <div class="flex size-6 items-center justify-center rounded-md bg-primary">
                    <span class="material-symbols-outlined text-[16px] text-white"
                        style="font-variation-settings: 'FILL' 1;">medical_services</span>
                </div>
                <span class="font-bold tracking-tight text-on-surface-variant">{{ config('app.name') }}</span>
            </div>
            <div class="font-label-sm text-label-sm text-on-surface-variant italic opacity-40">
                Chế độ học tập trung: Đang bật
            </div>
        </footer>
    </div>
</x-layouts.auth>
