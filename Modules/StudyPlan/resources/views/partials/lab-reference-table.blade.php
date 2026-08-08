@php
    /** @var array<string, array{label: string, rows: array<int, array<string, mixed>>}> $labReferenceGroups */
    $labPanelPrefix = $labPanelPrefix ?? 'lab';
    $labCloseAction = $labCloseAction ?? 'labOpen = false';
@endphp

<div class="flex h-full min-h-0 flex-col bg-white" data-testid="{{ $labPanelPrefix }}-values-table">
    <div class="flex shrink-0 items-center border-b border-outline-variant bg-surface-container-low">
        <div class="flex min-w-0 flex-1 overflow-x-auto" role="tablist" aria-label="Nhóm giá trị xét nghiệm">
            @foreach ($labReferenceGroups as $tabKey => $group)
                <button type="button" @click="activeLabTab = '{{ $tabKey }}'; labQuery = ''"
                    class="shrink-0 border-b-2 px-4 py-3 text-label-sm font-bold transition-colors"
                    :class="activeLabTab === '{{ $tabKey }}'
                        ? 'border-primary bg-white text-primary'
                        : 'border-transparent text-on-surface-variant hover:bg-white/70 hover:text-on-surface'"
                    :aria-selected="activeLabTab === '{{ $tabKey }}'"
                    role="tab"
                    data-testid="{{ $labPanelPrefix }}-tab-{{ $tabKey }}">
                    {{ $group['label'] }}
                </button>
            @endforeach
        </div>
        <div class="flex shrink-0 items-center gap-2 px-3">
            <label class="flex w-40 items-center gap-2 rounded-md bg-white px-2.5 py-1.5 xl:w-52">
                <span class="material-symbols-outlined text-[17px] text-outline">search</span>
                <input x-model="labQuery" type="search" placeholder="Search..."
                    class="min-w-0 flex-1 bg-transparent text-body-sm outline-none">
            </label>
            <span class="h-6 w-px bg-outline-variant"></span>
            <button type="button" @click="{{ $labCloseAction }}"
                class="flex size-8 items-center justify-center rounded-full text-outline transition-colors hover:bg-white"
                aria-label="Đóng bảng Lab">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto p-3 pb-24">
        <h2 class="mb-3 text-label-md font-bold tracking-wide text-on-surface uppercase">Lab Values</h2>
        <div class="overflow-hidden rounded-md border border-outline-variant">
            <table class="w-full table-fixed text-left text-[12px]">
                <thead class="sticky top-0 z-[1] bg-surface-container-high font-bold text-on-surface">
                    <tr>
                        <th class="w-[32%] border-r border-outline-variant px-3 py-2"
                            x-text="labReferenceGroups[activeLabTab]?.label || 'Lab Values'"></th>
                        <th class="w-[39%] border-r border-outline-variant px-3 py-2">Reference Range</th>
                        <th class="w-[29%] px-3 py-2">SI Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    <template x-for="(item, rowIndex) in filteredLabs()"
                        :key="item.section || (item.test + item.reference + rowIndex)">
                        <tr class="align-top"
                            :class="item.section ? 'bg-surface-container-high font-bold' : 'hover:bg-surface-container-low'">
                            <td class="border-r border-outline-variant px-3 py-2"
                                :colspan="item.section ? 3 : 1">
                                <span class="block"
                                    :class="item.nested ? 'pl-3' : ''"
                                    x-text="item.section || item.test"></span>
                            </td>
                            <td x-show="!item.section"
                                class="border-r border-outline-variant px-3 py-2 leading-relaxed whitespace-pre-line text-on-surface-variant"
                                x-text="item.reference"></td>
                            <td x-show="!item.section"
                                class="px-3 py-2 leading-relaxed whitespace-pre-line text-on-surface-variant"
                                x-text="item.si"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p x-show="filteredLabs().length === 0" class="p-6 text-center text-body-sm text-outline">
            Không tìm thấy xét nghiệm phù hợp.
        </p>
        <p class="mt-3 rounded-md bg-amber-50 p-3 text-[10px] leading-relaxed text-amber-800">
            Khoảng tham chiếu có thể thay đổi theo phòng xét nghiệm, tuổi, giới và tình trạng lâm sàng.
        </p>
    </div>
</div>
