@php
    // Static port of html/pc-flashcard-deck-detail.html. Placeholders until deck CRUD lands.
    $cards = [
        [
            'front' => 'Cơ chế tác dụng của Metformin?',
            'back' => 'Giảm sản xuất glucose ở gan, tăng nhạy cảm insulin ngoại vi.',
            'tags' => ['#Dược_lý', '#Tiểu_đường'],
            'status' => 'learning',
            'statusLabel' => 'Đang học',
            'statusClass' => 'bg-orange-100 text-orange-700',
            'due' => 'còn 2 ngày',
            'leech' => true,
        ],
        [
            'front' => 'Cơ chế của thuốc ức chế men chuyển (ACE inhibitors)?',
            'back' => 'Ngăn Angiotensin I chuyển thành Angiotensin II, gây giãn mạch.',
            'tags' => ['#Huyết_áp'],
            'status' => 'mastered',
            'statusLabel' => 'Đã thuộc',
            'statusClass' => 'bg-green-100 text-green-700',
            'due' => '2 tháng',
            'leech' => false,
        ],
        [
            'front' => 'Liều dùng Heparin trong rung nhĩ?',
            'back' => 'Duy trì aPTT gấp 1.5 - 2.5 lần giá trị chứng.',
            'tags' => ['#Chống_đông'],
            'status' => 'new',
            'statusLabel' => 'Mới',
            'statusClass' => 'bg-blue-100 text-blue-700',
            'due' => '-',
            'leech' => false,
        ],
        [
            'front' => 'Chống chỉ định tuyệt đối của Digoxin?',
            'back' => 'Block AV độ II, III; Hội chứng WPW; Nhịp nhanh thất.',
            'tags' => ['#Suy_tim'],
            'status' => 'learning',
            'statusLabel' => 'Đang học',
            'statusClass' => 'bg-orange-100 text-orange-700',
            'due' => '3 ngày',
            'leech' => true,
        ],
    ];
@endphp

<x-layouts.app title="Dược lý tim mạch">
    <div class="mx-auto w-full max-w-container-max p-gutter" x-data="{
        selected: {},
        get selectedCount() {
            return Object.values(this.selected).filter(Boolean).length;
        },
        get allSelected() {
            return this.selectedCount === {{ count($cards) }};
        },
        toggleAll() {
            const on = !this.allSelected;
            @foreach ($cards as $i => $card)
                this.selected[{{ $i }}] = on;
            @endforeach
        },
        clearSelection() {
            this.selected = {};
        },
        toggleRow(i, event) {
            if (event.target.closest('input, button, a')) return;
            this.selected[i] = !this.selected[i];
        },
    }">
        <nav class="mb-4 flex items-center gap-2 font-label-md text-label-md text-on-surface-variant">
            <a href="{{ route('flashcards.index') }}" class="hover:text-primary">Thẻ học</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <a href="{{ route('flashcards.index') }}" class="hover:text-primary">Bộ thẻ</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-on-surface">Dược lý tim mạch</span>
        </nav>

        <div
            class="mb-8 flex flex-col justify-between gap-6 rounded-xl border border-outline-variant bg-white p-6 md:flex-row md:items-end">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="font-headline-lg text-headline-lg text-on-surface">Dược lý tim mạch</h2>
                    <div class="flex gap-2">
                        <span
                            class="rounded-full bg-surface-container px-3 py-1 font-label-sm text-label-sm text-on-surface-variant">250
                            thẻ</span>
                        <span
                            class="rounded-full bg-tertiary-fixed px-3 py-1 font-label-sm text-label-sm text-on-tertiary-fixed-variant">20
                            đến hạn</span>
                    </div>
                </div>
                <p class="max-w-2xl font-body-md text-body-md text-on-surface-variant">
                    Tập hợp các thẻ về nhóm thuốc hạ áp, chống đông và thuốc vận mạch. Phân loại theo cơ chế, chỉ định và
                    tác dụng phụ điển hình.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('flashcards.review') }}"
                    class="flex items-center gap-2 rounded-lg bg-primary px-6 py-3 font-semibold text-white shadow-sm transition-all hover:bg-primary-container">
                    <span class="material-symbols-outlined"
                        style="font-variation-settings: 'FILL' 1;">play_arrow</span>
                    Ôn deck này
                </a>
                <a href="{{ route('flashcards.create') }}"
                    class="flex items-center gap-2 rounded-lg border border-outline-variant px-6 py-3 font-semibold text-primary transition-all hover:bg-surface-variant">
                    <span class="material-symbols-outlined">add</span>
                    Thêm thẻ
                </a>
            </div>
        </div>

        <div class="sticky top-header-height z-30 mb-6 flex flex-wrap items-center justify-between gap-4 bg-surface py-2">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                <div class="relative min-w-[300px]">
                    <span
                        class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-xl text-on-surface-variant">search</span>
                    <input type="text" placeholder="Tìm thẻ..."
                        class="w-full rounded-lg border border-outline-variant bg-white py-2.5 pr-4 pl-10 text-sm focus:border-primary focus:ring-primary">
                </div>
                <select
                    class="rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm focus:border-primary focus:ring-primary">
                    <option>Trạng thái: Tất cả</option>
                    <option>Mới</option>
                    <option>Đang học</option>
                    <option>Đã thuộc</option>
                </select>
                <select
                    class="rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm focus:border-primary focus:ring-primary">
                    <option>Tag: Tất cả</option>
                    <option>Dược lý</option>
                    <option>Tim mạch</option>
                    <option>Huyết áp</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                    class="rounded-lg border border-outline-variant p-2.5 transition-colors hover:bg-white">
                    <span class="material-symbols-outlined">sort</span>
                </button>
                <button type="button"
                    class="rounded-lg border border-outline-variant p-2.5 transition-colors hover:bg-white">
                    <span class="material-symbols-outlined">view_list</span>
                </button>
            </div>
        </div>

        <div x-show="selectedCount > 0" x-cloak
            class="fixed bottom-8 left-1/2 z-[60] flex -translate-x-1/2 items-center gap-6 rounded-full bg-on-surface px-6 py-3 text-white shadow-2xl">
            <span class="text-sm font-medium"><span x-text="selectedCount"></span> đã chọn</span>
            <div class="h-6 w-px bg-white/20"></div>
            <div class="flex items-center gap-4">
                <button type="button"
                    class="flex items-center gap-2 text-sm transition-colors hover:text-primary-fixed">
                    <span class="material-symbols-outlined text-lg">visibility_off</span> Tạm ẩn
                </button>
                <button type="button"
                    class="flex items-center gap-2 text-sm transition-colors hover:text-primary-fixed">
                    <span class="material-symbols-outlined text-lg">move_to_inbox</span> Đổi deck
                </button>
                <button type="button"
                    class="flex items-center gap-2 text-sm text-red-400 transition-colors hover:text-error">
                    <span class="material-symbols-outlined text-lg">delete</span> Xóa
                </button>
            </div>
            <button type="button" @click="clearSelection()" class="rounded-full p-1 hover:bg-white/10">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-outline-variant bg-white">
            <table class="w-full border-collapse text-left">
                <thead class="border-b border-outline-variant bg-surface-container-low">
                    <tr>
                        <th class="w-10 px-6 py-4">
                            <input type="checkbox" :checked="allSelected" @change="toggleAll()"
                                class="rounded border-outline-variant text-primary focus:ring-primary">
                        </th>
                        <th class="w-1/3 px-4 py-4 font-label-md text-label-md text-on-surface-variant">Mặt trước
                            (Câu hỏi)</th>
                        <th class="w-1/3 px-4 py-4 font-label-md text-label-md text-on-surface-variant">Mặt sau (Đáp án)
                        </th>
                        <th class="px-4 py-4 font-label-md text-label-md text-on-surface-variant">Tag</th>
                        <th class="px-4 py-4 font-label-md text-label-md text-on-surface-variant">Trạng thái</th>
                        <th class="px-4 py-4 font-label-md text-label-md text-on-surface-variant">Ôn kế</th>
                        <th class="w-12 px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @foreach ($cards as $i => $card)
                        <tr class="group cursor-pointer transition-colors hover:bg-surface-container-low/50"
                            @click="toggleRow({{ $i }}, $event)">
                            <td class="px-6 py-4">
                                <input type="checkbox" x-model="selected[{{ $i }}]"
                                    class="rounded border-outline-variant text-primary focus:ring-primary">
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-col gap-1">
                                    <span
                                        class="line-clamp-2 font-body-md text-body-md text-on-surface">{{ $card['front'] }}</span>
                                    @if ($card['leech'])
                                        <span
                                            class="w-fit rounded bg-red-100 px-2 py-0.5 text-[10px] font-bold tracking-wider text-red-700 uppercase">Hay
                                            sai (leech)</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4 font-body-sm text-body-sm text-on-surface-variant">
                                <p class="line-clamp-2">{{ $card['back'] }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($card['tags'] as $tag)
                                        <span
                                            class="rounded bg-primary-fixed/30 px-2 py-0.5 text-[11px] font-medium text-on-primary-fixed-variant">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    class="rounded-full px-3 py-1 font-label-sm text-label-sm {{ $card['statusClass'] }}">{{ $card['statusLabel'] }}</span>
                            </td>
                            <td class="px-4 py-4 font-body-sm text-body-sm text-on-surface-variant">
                                {{ $card['due'] }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button"
                                    class="p-1 text-on-surface-variant opacity-0 transition-opacity group-hover:opacity-100">
                                    <span class="material-symbols-outlined text-xl">edit</span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex items-center justify-between px-2">
            <p class="text-sm text-on-surface-variant">Hiển thị 1-25 của 250 thẻ</p>
            <div class="flex items-center gap-1">
                <button type="button"
                    class="rounded-lg border border-outline-variant p-2 transition-colors hover:bg-white disabled:opacity-50">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button type="button" class="rounded-lg bg-primary px-4 py-2 font-medium text-white">1</button>
                <button type="button" class="rounded-lg px-4 py-2 transition-colors hover:bg-white">2</button>
                <button type="button" class="rounded-lg px-4 py-2 transition-colors hover:bg-white">3</button>
                <span class="px-2">...</span>
                <button type="button" class="rounded-lg px-4 py-2 transition-colors hover:bg-white">10</button>
                <button type="button"
                    class="rounded-lg border border-outline-variant p-2 transition-colors hover:bg-white">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>
        </div>
    </div>
</x-layouts.app>
