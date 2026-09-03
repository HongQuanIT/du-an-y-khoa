@php
    // Static port of html/pc-flashcard-create.html. Placeholders until decks persist.
    $decks = ['Dược lý tim mạch', 'Nội khoa cơ sở', 'Ngoại khoa lồng ngực', 'Sản phụ khoa'];
    $tags = ['#Cardiology', '#HighYield'];
@endphp

<x-layouts.app title="Tạo thẻ ghi nhớ">
    <div class="relative pb-24" x-data="{
        previewOpen: false,
        flipped: false,
        front: '',
        back: '',
        previewFront: '',
        previewBack: '',
        aiBusy: false,
        aiDone: false,
        openPreview() {
            this.previewFront = this.front || 'Mặt trước đang trống...';
            this.previewBack = this.back || 'Mặt sau đang trống...';
            this.flipped = false;
            this.previewOpen = true;
        },
        generateAi() {
            if (this.aiBusy) return;
            this.aiBusy = true;
            this.aiDone = false;
            setTimeout(() => {
                this.front = 'Cơ chế tác dụng của thuốc Digoxin trong điều trị suy tim là gì?';
                this.back =
                    'Digoxin ức chế bơm Na+/K+-ATPase ở màng tế bào cơ tim. \n\nKết quả:\n- Tăng nồng độ Na+ nội bào.\n- Giảm trao đổi Na+/Ca2+ → Tăng Ca2+ nội bào.\n- Tăng sức co bóp cơ tim (Inotropic dương).';
                this.aiBusy = false;
                this.aiDone = true;
                setTimeout(() => { this.aiDone = false; }, 2000);
            }, 1200);
        },
    }">
        <div class="mx-auto max-w-[1000px] px-8 py-8">
            <nav class="mb-6 flex items-center gap-2 text-sm text-on-surface-variant">
                <a href="{{ route('flashcards.index') }}" class="transition-colors hover:text-primary">Thẻ học</a>
                <span class="material-symbols-outlined text-sm">chevron_right</span>
                <span class="font-medium text-on-surface">Tạo thẻ mới</span>
            </nav>

            <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <div class="mb-2 flex items-center gap-3">
                        <h1 class="text-3xl font-bold tracking-tight text-on-surface">Tạo thẻ ghi nhớ mới</h1>
                        <span
                            class="premium-gradient flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold tracking-widest text-white uppercase">
                            <span class="material-symbols-outlined text-[12px]"
                                style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                            AI Tutor
                        </span>
                    </div>
                    <p class="text-on-surface-variant">
                        Xây dựng bộ thẻ học cá nhân hóa để ghi nhớ kiến thức Y khoa hiệu quả hơn.
                    </p>
                </div>
                <button type="button" @click="generateAi()"
                    :disabled="aiBusy"
                    :class="aiDone ? 'bg-green-600' : 'bg-primary-container hover:bg-[#0d6b63]'"
                    class="flex items-center gap-2 rounded-xl px-6 py-3 font-semibold text-white shadow-md transition-all active:scale-95 disabled:cursor-wait disabled:opacity-75">
                    <template x-if="aiBusy">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined animate-spin">sync</span>
                            Đang phân tích...
                        </span>
                    </template>
                    <template x-if="!aiBusy && aiDone">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined">check_circle</span>
                            Hoàn tất
                        </span>
                    </template>
                    <template x-if="!aiBusy && !aiDone">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined">bolt</span>
                            Tạo với AI
                        </span>
                    </template>
                </button>
            </div>

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-on-surface">Chọn bộ thẻ</label>
                        <select
                            class="w-full cursor-pointer appearance-none rounded-xl border border-outline-variant bg-white p-3 outline-none focus:ring-2 focus:ring-primary/20">
                            @foreach ($decks as $deck)
                                <option>{{ $deck }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-on-surface">Thêm nhãn (Tags)</label>
                        <div
                            class="flex min-h-[50px] flex-wrap items-center gap-2 rounded-xl border border-outline-variant bg-white p-2">
                            @foreach ($tags as $tag)
                                <span
                                    class="flex items-center gap-1 rounded-lg bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                    {{ $tag }}
                                    <span class="material-symbols-outlined cursor-pointer text-xs">close</span>
                                </span>
                            @endforeach
                            <input type="text" placeholder="Nhấn Enter để thêm..."
                                class="min-w-[120px] flex-1 border-none bg-transparent py-1 text-sm focus:ring-0">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm font-bold text-on-surface">
                                <span class="size-2 rounded-full bg-primary"></span>
                                Mặt trước (Câu hỏi)
                            </label>
                            <button type="button"
                                class="text-xs font-semibold text-primary hover:underline">Sử dụng Template</button>
                        </div>
                        <div
                            class="overflow-hidden rounded-2xl border border-outline-variant bg-white transition-colors focus-within:border-primary">
                            <div
                                class="flex items-center gap-1 border-b border-outline-variant bg-surface-container-low p-2">
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">format_bold</span>
                                </button>
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">format_italic</span>
                                </button>
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">format_underlined</span>
                                </button>
                                <div class="mx-1 h-4 w-px bg-outline-variant"></div>
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">image</span>
                                </button>
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">link</span>
                                </button>
                                <button type="button"
                                    class="ml-auto rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">mic</span>
                                </button>
                            </div>
                            <textarea x-model="front" rows="8"
                                placeholder="Nhập câu hỏi hoặc khái niệm cần ghi nhớ..."
                                class="font-body text-body-lg w-full resize-none border-none p-6 focus:ring-0"></textarea>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm font-bold text-on-surface">
                                <span class="size-2 rounded-full bg-tertiary"></span>
                                Mặt sau (Đáp án)
                            </label>
                            <span
                                class="rounded bg-surface-container px-2 py-1 text-[10px] text-on-surface-variant">Markdown
                                supported</span>
                        </div>
                        <div
                            class="overflow-hidden rounded-2xl border border-outline-variant bg-white transition-colors focus-within:border-primary">
                            <div
                                class="flex items-center gap-1 border-b border-outline-variant bg-surface-container-low p-2">
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">format_bold</span>
                                </button>
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">format_italic</span>
                                </button>
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">format_list_bulleted</span>
                                </button>
                                <div class="mx-1 h-4 w-px bg-outline-variant"></div>
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">image</span>
                                </button>
                                <button type="button"
                                    class="rounded-md p-2 text-on-surface-variant hover:bg-surface-variant">
                                    <span class="material-symbols-outlined text-[20px]">table_chart</span>
                                </button>
                            </div>
                            <textarea x-model="back" rows="8"
                                placeholder="Nhập giải thích chi tiết, đáp án hoặc sơ đồ..."
                                class="font-body text-body-lg w-full resize-none border-none p-6 focus:ring-0"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-center justify-between gap-4 pt-4 md:flex-row">
                    <button type="button" @click="openPreview()"
                        class="flex items-center gap-2 rounded-xl border border-outline-variant bg-white px-5 py-2.5 text-sm font-semibold text-on-surface transition-colors hover:bg-surface-container-low">
                        <span class="material-symbols-outlined">visibility</span>
                        Xem trước thẻ
                    </button>
                    <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">link</span>
                        Nguồn:
                        <a href="#" class="flex items-center gap-1 text-primary hover:underline">
                            Từ câu hỏi #1024 (Shock tim)
                            <span class="material-symbols-outlined text-[12px]">open_in_new</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="previewOpen" x-cloak
            class="fixed inset-0 z-[60] flex items-center justify-center bg-on-background/40 p-4 backdrop-blur-sm"
            @click.self="previewOpen = false">
            <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white p-8 shadow-2xl">
                <button type="button" @click="previewOpen = false"
                    class="absolute top-4 right-4 rounded-full p-2 hover:bg-surface-container">
                    <span class="material-symbols-outlined">close</span>
                </button>
                <h3 class="mb-8 text-center text-xs font-bold tracking-widest text-on-surface-variant uppercase">
                    Xem trước</h3>
                <div class="card-flip-container aspect-[3/2] w-full cursor-pointer"
                    :class="{ flipped: flipped }" @click="flipped = !flipped">
                    <div class="card-flip-inner relative h-full w-full">
                        <div
                            class="card-front absolute inset-0 flex items-center justify-center rounded-2xl border-2 border-primary/20 bg-primary-container/5 p-8 text-center">
                            <p class="text-xl font-bold text-primary" x-text="previewFront"></p>
                        </div>
                        <div
                            class="card-back absolute inset-0 flex flex-col items-center justify-center rounded-2xl border-2 border-primary/20 bg-white p-8 text-center shadow-inner">
                            <p class="text-lg whitespace-pre-line text-on-surface" x-text="previewBack"></p>
                        </div>
                    </div>
                </div>
                <p class="mt-6 text-center text-[10px] tracking-tight text-on-surface-variant uppercase">
                    Click vào thẻ để lật</p>
            </div>
        </div>

        <div
            class="fixed right-0 bottom-0 left-0 z-30 flex items-center justify-between border-t border-outline-variant bg-white/80 p-4 px-8 backdrop-blur-md md:left-sidebar-width">
            <a href="{{ route('flashcards.index') }}"
                class="px-4 text-sm font-medium text-on-surface-variant transition-colors hover:text-error">Hủy</a>
            <div class="flex items-center gap-3">
                <button type="button"
                    class="rounded-xl border border-primary px-6 py-2.5 text-sm font-bold text-primary transition-colors hover:bg-primary/5">
                    Lưu &amp; tạo thẻ tiếp
                </button>
                <a href="{{ route('flashcards.deck') }}"
                    class="rounded-xl bg-primary px-8 py-2.5 text-sm font-bold text-white shadow-lg transition-all hover:shadow-primary/20 active:scale-95">
                    Lưu thẻ
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
