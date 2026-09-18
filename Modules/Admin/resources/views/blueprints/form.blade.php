@php $isNew = ! $blueprint->exists; @endphp
<x-layouts.admin :title="$isNew ? 'Tạo ma trận đề thi' : 'Sửa ma trận đề thi'">
    <x-admin.page-header :title="$isNew ? 'Tạo ma trận đề thi' : $blueprint->name"
        :description="$isNew ? 'Tạo ma trận mới, sau đó thêm các phần, chủ đề lâm sàng và map sang bài học.' : 'Chỉnh metadata, phần, chủ đề lâm sàng. Map CCT ↔ bài học để câu hỏi (đã gắn bài học) tự khớp ma trận — không gắn câu hỏi trực tiếp.'">
        <x-slot:actions>
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprints.index'))
<a href="{{ route('admin.blueprints.index') }}" class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
@endif
        </x-slot:actions>
    </x-admin.page-header>

    @unless($isNew)
        @include('admin::taxonomy._sub-nav', ['active' => 'blueprints'])
    @endunless

    <x-admin.flash />

    <form method="post" action="{{ $isNew ? route('admin.blueprints.store') : route('admin.blueprints.update', $blueprint) }}" class="w-full">
        @csrf @unless($isNew) @method('PUT') @endunless
        <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm" aria-labelledby="blueprint-information-heading">
            <div class="flex items-start gap-3 border-b border-outline-variant px-5 py-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                    <span class="material-symbols-outlined text-[22px]">account_tree</span>
                </span>
                <div class="min-w-0">
                    <h2 id="blueprint-information-heading" class="font-headline-sm text-headline-sm text-on-surface">Thông tin ma trận</h2>
                    <p class="mt-0.5 font-body-sm text-on-surface-variant">Tên, mã, trạng thái và mô tả phạm vi của ma trận đề thi.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 divide-y divide-outline-variant lg:grid-cols-[minmax(20rem,24rem)_minmax(0,1fr)] lg:divide-x lg:divide-y-0">
                <div class="space-y-5 p-5">
                    <div>
                        <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-name">Tên ma trận <span class="text-error">*</span></label>
                        <input id="blueprint-name" name="name" value="{{ old('name', $blueprint->name) }}" required
                            class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                        @error('name') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-code">Mã</label>
                        <input id="blueprint-code" name="code" value="{{ old('code', $blueprint->code) }}" placeholder="medical_practice_licensing_exam"
                            class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-mono text-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                        @error('code') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-status">Trạng thái</label>
                            <select id="blueprint-status" name="status" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $blueprint->status?->value) === $status->value)>{{ $status->value === 'active' ? 'Đang hoạt động' : 'Ngừng sử dụng' }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-sort-order">Thứ tự</label>
                            <input id="blueprint-sort-order" type="number" name="sort_order" min="0" value="{{ old('sort_order', $blueprint->sort_order ?? 0) }}"
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70" @disabled(! $canUpdate)>
                            @error('sort_order') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex min-h-[20rem] flex-col p-5">
                    <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-description">Mô tả</label>
                    <textarea id="blueprint-description" name="description" rows="12" placeholder="Mô tả phạm vi, số phần và chủ đề của ma trận…"
                        class="min-h-[16rem] flex-1 resize-y rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2.5 font-body-sm leading-6 text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70 lg:min-h-0" @disabled(! $canUpdate)>{{ old('description', $blueprint->description) }}</textarea>
                    @error('description') <p class="mt-1.5 font-label-sm text-error">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($canUpdate)
                <div class="flex items-center justify-end border-t border-outline-variant bg-surface-container-low/40 px-5 py-3.5">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 font-label-md font-medium text-on-primary transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                        Lưu thay đổi
                    </button>
                </div>
            @endif
        </section>
    </form>

    @if (! $isNew)
        @php
            $weightSectionsPayload = $blueprint->sections->map(function ($section) {
                return [
                    'id' => (int) $section->id,
                    'name' => $section->name,
                    'weight_min' => $section->weight_min,
                    'weight_max' => $section->weight_max,
                    'open' => false,
                    'topics' => $section->coreClinicalTopics->map(fn ($topic) => [
                        'id' => (int) $topic->id,
                        'name' => $topic->name,
                        'weight' => $topic->weight,
                    ])->values()->all(),
                ];
            })->values()->all();
        @endphp

        <section
            class="mt-8 overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm"
            aria-labelledby="blueprint-weights-heading"
            x-data="blueprintWeightMatrix({
                saveUrl: @js(route('admin.blueprints.weights.update', $blueprint)),
                csrfToken: @js(csrf_token()),
                canUpdate: @js((bool) $canUpdate),
                totalQuestions: @js($blueprint->total_questions),
                sections: @js($weightSectionsPayload),
            })"
        >
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-outline-variant px-5 py-4">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                        <span class="material-symbols-outlined text-[22px]">percent</span>
                    </span>
                    <div class="min-w-0">
                        <h2 id="blueprint-weights-heading" class="font-headline-sm text-headline-sm text-on-surface">Cấu hình tỉ trọng</h2>
                        <p class="mt-0.5 font-body-sm text-on-surface-variant">Tổng số câu → tỉ trọng phần (min–max % toàn ma trận) → tỉ trọng chủ đề (% trong phần, tổng = 100%).</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-surface-container-high px-2.5 py-1 font-semibold text-on-surface" x-text="sections.length + ' phần'"></span>
                    <span class="rounded-full bg-surface-container-high px-2.5 py-1 font-semibold text-on-surface" x-text="topicCount + ' chủ đề'"></span>
                </div>
            </div>

            <div class="space-y-5 p-5">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[12rem]">
                        <label class="mb-1.5 block font-label-sm font-medium text-on-surface-variant" for="blueprint-total-questions">Tổng số câu ma trận</label>
                        <input
                            id="blueprint-total-questions"
                            type="number"
                            min="1"
                            max="10000"
                            step="1"
                            x-model.number="totalQuestions"
                            @input="markDirty()"
                            placeholder="VD: 200"
                            class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-body-sm text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70"
                            @disabled(! $canUpdate)
                        >
                    </div>
                    <p class="pb-2 font-body-sm text-on-surface-variant">Dùng để ước lượng số câu theo từng phần / chủ đề.</p>
                </div>

                <div
                    class="rounded-lg border px-4 py-3"
                    :class="sectionCoverage.ok
                        ? 'border-primary/25 bg-primary/5 text-on-surface'
                        : 'border-error/30 bg-error-container/30 text-on-surface'"
                    role="status"
                >
                    <div class="flex flex-wrap items-start gap-2">
                        <span
                            class="material-symbols-outlined mt-0.5 text-[20px]"
                            :class="sectionCoverage.ok ? 'text-primary' : 'text-error'"
                            aria-hidden="true"
                            x-text="sectionCoverage.ok ? 'check_circle' : 'warning'"
                        ></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-label-md font-semibold" x-text="sectionCoverage.message"></p>
                            <p class="mt-1 font-body-sm text-on-surface-variant">
                                Σ min = <span class="font-semibold tabular-nums text-on-surface" x-text="formatPct(sectionCoverage.sumMin)"></span>
                                · Σ max = <span class="font-semibold tabular-nums text-on-surface" x-text="formatPct(sectionCoverage.sumMax)"></span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 space-y-1.5" aria-hidden="true">
                        <div class="flex items-center gap-2">
                            <span class="w-10 shrink-0 font-label-sm text-on-surface-variant">Min</span>
                            <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-surface-container-high">
                                <div class="absolute inset-y-0 left-0 rounded-full bg-on-surface-variant/50" :style="'width:' + coverageBarWidth(sectionCoverage.sumMin) + '%'"></div>
                                <div class="absolute inset-y-0 w-px -translate-x-1/2 bg-on-surface" :style="'left:' + coverageMarkerLeft() + '%'"></div>
                            </div>
                            <span class="w-14 shrink-0 text-right font-label-sm tabular-nums text-on-surface-variant" x-text="formatPct(sectionCoverage.sumMin)"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-10 shrink-0 font-label-sm text-on-surface-variant">Max</span>
                            <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-surface-container-high">
                                <div
                                    class="absolute inset-y-0 left-0 rounded-full"
                                    :class="sectionCoverage.ok ? 'bg-primary' : 'bg-error'"
                                    :style="'width:' + coverageBarWidth(sectionCoverage.sumMax) + '%'"
                                ></div>
                                <div class="absolute inset-y-0 w-px -translate-x-1/2 bg-on-surface" :style="'left:' + coverageMarkerLeft() + '%'"></div>
                            </div>
                            <span class="w-14 shrink-0 text-right font-label-sm tabular-nums text-on-surface-variant" x-text="formatPct(sectionCoverage.sumMax)"></span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-outline-variant">
                    <table class="min-w-full border-collapse text-sm">
                        <thead class="bg-surface-container-low text-left font-label-sm text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 font-medium">Phần</th>
                                <th class="w-28 px-3 py-3 font-medium">Min %</th>
                                <th class="w-28 px-3 py-3 font-medium">Max %</th>
                                <th class="w-36 px-3 py-3 font-medium">Ước lượng câu</th>
                                <th class="w-28 px-3 py-3 font-medium text-right">Chủ đề</th>
                            </tr>
                        </thead>
                        <template x-for="section in sections" :key="section.id">
                            <tbody class="border-t border-outline-variant" :class="section.open && 'bg-surface-container-low/40'">
                                <tr class="align-middle">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-on-surface" x-text="section.name"></p>
                                        <p class="mt-0.5 text-xs text-on-surface-variant" x-text="section.topics.length + ' chủ đề lâm sàng'"></p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            x-model="section.weight_min"
                                            @input="markDirty()"
                                            placeholder="—"
                                            class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-2.5 font-body-sm tabular-nums outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:opacity-70"
                                            @disabled(! $canUpdate)
                                        >
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            x-model="section.weight_max"
                                            @input="markDirty()"
                                            placeholder="—"
                                            class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-2.5 font-body-sm tabular-nums outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:opacity-70"
                                            @disabled(! $canUpdate)
                                        >
                                    </td>
                                    <td class="px-3 py-3 font-body-sm tabular-nums text-on-surface-variant" x-text="estimateLabel(section.weight_min, section.weight_max)"></td>
                                    <td class="px-3 py-3 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 items-center gap-1 rounded-lg border border-outline-variant px-2.5 text-xs font-semibold text-on-surface-variant transition hover:bg-surface"
                                            @click="section.open = !section.open"
                                            :aria-expanded="section.open"
                                        >
                                            <span class="material-symbols-outlined text-[16px]" aria-hidden="true" x-text="section.open ? 'expand_less' : 'expand_more'"></span>
                                            <span x-text="section.open ? 'Thu gọn' : 'Chủ đề'"></span>
                                        </button>
                                    </td>
                                </tr>
                                <tr x-show="section.open" x-cloak>
                                    <td colspan="5" class="px-4 pb-4 pt-0">
                                        <div class="space-y-3 border-t border-outline-variant/70 pt-4">
                                            <div
                                                class="rounded-md border px-3 py-2 text-xs"
                                                :class="topicCoverage(section).ok
                                                    ? 'border-outline-variant bg-surface text-on-surface-variant'
                                                    : 'border-error/25 bg-error-container/20 text-on-surface'"
                                            >
                                                <span class="font-semibold" x-text="topicCoverage(section).message"></span>
                                                <span class="ml-1 tabular-nums">
                                                    (Σ = <span x-text="formatPct(topicCoverage(section).sum)"></span>)
                                                </span>
                                            </div>

                                            <div class="overflow-x-auto rounded-lg border border-outline-variant/80 bg-surface">
                                                <table class="min-w-full text-sm">
                                                    <thead class="bg-surface-container-low/80 text-left font-label-sm text-on-surface-variant">
                                                        <tr>
                                                            <th class="px-3 py-2.5 font-medium">Chủ đề trong phần</th>
                                                            <th class="w-32 px-2 py-2.5 font-medium">Tỉ trọng %</th>
                                                            <th class="w-36 px-2 py-2.5 font-medium">Ước lượng câu</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-if="section.topics.length === 0">
                                                            <tr>
                                                                <td colspan="3" class="px-3 py-3 text-on-surface-variant">Chưa có chủ đề lâm sàng.</td>
                                                            </tr>
                                                        </template>
                                                        <template x-for="topic in section.topics" :key="topic.id">
                                                            <tr class="border-t border-outline-variant/60">
                                                                <td class="px-3 py-2.5 text-on-surface" x-text="topic.name"></td>
                                                                <td class="px-2 py-2">
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        max="100"
                                                                        step="0.01"
                                                                        x-model="topic.weight"
                                                                        @input="markDirty()"
                                                                        placeholder="—"
                                                                        class="h-9 w-full rounded-lg border border-outline-variant bg-surface-container-low px-2 font-body-sm tabular-nums outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:opacity-70"
                                                                        @disabled(! $canUpdate)
                                                                    >
                                                                </td>
                                                                <td class="px-2 py-2.5 tabular-nums text-on-surface-variant" x-text="topicEstimateLabel(section, topic)"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                    <tfoot class="border-t border-outline-variant/80 bg-surface-container-low/50 font-label-sm">
                                                        <tr>
                                                            <td class="px-3 py-2.5 font-semibold text-on-surface">Tổng chủ đề</td>
                                                            <td class="px-2 py-2.5 font-semibold tabular-nums" :class="topicCoverage(section).ok ? 'text-on-surface' : 'text-error'" x-text="formatPct(topicCoverage(section).sum)"></td>
                                                            <td></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                            <p class="text-xs text-on-surface-variant">Tỉ trọng chủ đề tính trên 100% của phần này — tổng các chủ đề phải đúng 100%.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                        <tbody x-show="sections.length === 0">
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Thêm phần bên dưới trước khi cấu hình tỉ trọng.</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-outline-variant bg-surface-container-low/60 font-label-sm">
                            <tr>
                                <td class="px-4 py-3 font-semibold text-on-surface">Tổng phần</td>
                                <td class="px-3 py-3 font-semibold tabular-nums text-on-surface" x-text="formatPct(sectionCoverage.sumMin)"></td>
                                <td class="px-3 py-3 font-semibold tabular-nums text-on-surface" x-text="formatPct(sectionCoverage.sumMax)"></td>
                                <td class="px-3 py-3 tabular-nums text-on-surface-variant" x-text="totalEstimateLabel()"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($canUpdate)
                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-outline-variant bg-surface-container-low/40 px-5 py-3.5">
                    <p
                        class="mr-auto min-h-[1.25rem] text-xs"
                        :class="statusError ? 'text-error' : (statusMessage ? 'text-primary' : 'text-on-surface-variant')"
                        x-text="statusMessage || (isDirty ? 'Có thay đổi chưa lưu' : '')"
                    ></p>
                    <button
                        type="button"
                        @click="save()"
                        :disabled="!isDirty || saving"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 font-label-md font-medium text-on-primary transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="saving ? 'progress_activity' : 'save'"></span>
                        <span x-text="saving ? 'Đang lưu…' : 'Lưu tỉ trọng'"></span>
                    </button>
                </div>
            @endif
        </section>
    @endif

    @if (! $isNew)
        <div
            class="mt-8 space-y-6"
            x-data="{
                confirmOpen: false,
                confirmTitle: '',
                confirmMessage: '',
                confirmAction: '',
                confirmLabel: 'Xóa',
                openConfirm({ title, message, action, label }) {
                    this.confirmTitle = title;
                    this.confirmMessage = message;
                    this.confirmAction = action;
                    this.confirmLabel = label || 'Xóa';
                    this.confirmOpen = true;
                    this.$nextTick(() => this.$refs.confirmCancel?.focus());
                },
                closeConfirm() {
                    this.confirmOpen = false;
                },
            }"
            @keydown.escape.window="confirmOpen && closeConfirm()"
            @blueprint-confirm="openConfirm($event.detail)"
        >
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-label-lg font-semibold">Phần & Chủ đề lâm sàng</h2>
                <span class="text-sm text-on-surface-variant">{{ $blueprint->sections->sum(fn ($s) => $s->coreClinicalTopics->count()) }} chủ đề</span>
            </div>

            @foreach ($blueprint->sections as $section)
                <div class="rounded-xl border border-outline-variant bg-surface p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-semibold">{{ $section->name }}</h3>
                            <p class="text-xs text-on-surface-variant">slug: {{ $section->slug }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-surface-container-high px-2.5 py-1 text-xs font-semibold">{{ $section->coreClinicalTopics->count() }} chủ đề</span>
                            @if ($canDelete)
                                <button
                                    type="button"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-error/20 px-2.5 text-xs font-semibold text-error transition hover:bg-error/5"
                                    @click="openConfirm({
                                        title: 'Xóa phần này?',
                                        message: @js('Phần «'.$section->name.'» và '.$section->coreClinicalTopics->count().' chủ đề lâm sàng bên trong sẽ bị xóa vĩnh viễn. Liên kết bài học và tag cũng bị gỡ.'),
                                        action: @js(route('admin.blueprint-sections.destroy', $section)),
                                        label: 'Xóa phần',
                                    })"
                                >
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>
                                    Xóa phần
                                </button>
                            @endif
                        </div>
                    </div>

                    <ul class="mb-4 space-y-3 text-sm">
                        @forelse ($section->coreClinicalTopics as $topic)
                            @php
                                $topicLessons = $topic->lessons->map(fn ($l) => [
                                    'id' => (int) $l->id,
                                    'name' => $l->name,
                                    'is_priority' => (bool) ($l->pivot->is_priority ?? true),
                                ])->values()->all();
                                $topicTags = $topic->tags->map(fn ($t) => [
                                    'id' => (int) $t->id,
                                    'name' => $t->name,
                                ])->values()->all();
                            @endphp
                            <li
                                class="rounded-lg border border-outline-variant/60 p-3"
                                x-data="blueprintTopicLinkMapper({
                                    lessonLookupUrl: @js(route('admin.taxonomy.lookups.lessons')),
                                    tagLookupUrl: @js(route('admin.taxonomy.lookups.tags')),
                                    syncUrl: @js(route('admin.core-clinical-topics.medical-nodes.sync', $topic)),
                                    csrfToken: @js(csrf_token()),
                                    canUpdate: @js((bool) $canUpdate),
                                    initialLessons: @js(collect($topicLessons)->keyBy('id')->all()),
                                    initialTags: @js(collect($topicTags)->keyBy('id')->all()),
                                })"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <button type="button" @click="open = !open" class="text-left font-medium hover:text-primary">
                                        <span class="text-on-surface-variant">{{ $topic->sort_order }}.</span> {{ $topic->name }}
                                    </button>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span
                                            class="rounded bg-secondary-container px-2 py-0.5 text-on-secondary-container"
                                            x-text="linkCountLabel()"
                                        >{{ count($topicLessons) + count($topicTags) }} liên kết</span>
                                        <span
                                            class="rounded bg-surface-container-high px-2 py-0.5 text-on-surface"
                                            x-show="selectedLessonIds.length > 0"
                                            x-text="priorityCountLabel()"
                                        ></span>
                                        @if ($canUpdate)
                                            <button type="button" @click="open = !open" class="font-semibold text-primary" x-text="open ? 'Đóng' : 'Liên kết'"></button>
                                        @endif
                                        @if ($canDelete)
                                            <button
                                                type="button"
                                                class="inline-flex size-8 items-center justify-center rounded-lg text-error/80 transition hover:bg-error/5 hover:text-error"
                                                aria-label="Xóa chủ đề {{ $topic->name }}"
                                                @click="$dispatch('blueprint-confirm', {
                                                    title: 'Xóa chủ đề lâm sàng?',
                                                    message: @js('Chủ đề «'.$topic->name.'» sẽ bị xóa vĩnh viễn. Liên kết bài học và tag của chủ đề này cũng bị gỡ.'),
                                                    action: @js(route('admin.core-clinical-topics.destroy', $topic)),
                                                    label: 'Xóa chủ đề',
                                                })"
                                            >
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Preview chips when collapsed --}}
                                <div
                                    x-show="!open && (selectedLessonIds.length > 0 || selectedTagIds.length > 0)"
                                    class="mt-2 flex flex-wrap gap-1.5"
                                >
                                    <template x-for="id in selectedLessonIds" :key="'preview-lesson-'+id">
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-surface-container px-2 py-0.5 text-xs text-on-surface">
                                            <span
                                                class="material-symbols-outlined text-[12px]"
                                                :class="selectedLessons[id]?.is_priority ? 'text-amber-600' : 'text-on-surface-variant/40'"
                                                aria-hidden="true"
                                            >star</span>
                                            <span class="truncate" x-text="selectedLessons[id]?.name || ('#'+id)"></span>
                                        </span>
                                    </template>
                                    <template x-for="id in selectedTagIds" :key="'preview-tag-'+id">
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-surface-container px-2 py-0.5 text-xs text-on-surface">
                                            <span class="material-symbols-outlined text-[12px] text-on-surface-variant" aria-hidden="true">sell</span>
                                            <span class="truncate" x-text="selectedTags[id]?.name || ('#'+id)"></span>
                                        </span>
                                    </template>
                                </div>

                                <div x-show="open" x-cloak class="mt-3 space-y-3 border-t border-outline-variant/60 pt-3">
                                    <p class="text-xs text-on-surface-variant">Map bài học hoặc tag cho chủ đề này. Đánh sao bài trọng điểm — khi sinh đề ưu tiên lấy câu từ bài có sao, thiếu mới lấy bài còn lại.</p>

                                    <div
                                        x-show="selectedLessonIds.length > 0 && priorityLessonCount === 0"
                                        x-cloak
                                        class="rounded-md border border-error/25 bg-error-container/20 px-3 py-2 text-xs text-on-surface"
                                    >
                                        Chủ đề có bài học liên kết nhưng chưa đánh dấu bài trọng điểm nào.
                                    </div>

                                    {{-- Selected links --}}
                                    <div x-show="selectedLessonIds.length > 0 || selectedTagIds.length > 0" class="flex flex-wrap gap-1.5">
                                        <template x-for="id in selectedLessonIds" :key="'chip-lesson-'+id">
                                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                                @if ($canUpdate)
                                                    <button
                                                        type="button"
                                                        @click="toggleLessonPriority(id)"
                                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md transition hover:bg-primary/15"
                                                        :aria-label="(selectedLessons[id]?.is_priority ? 'Bỏ trọng điểm ' : 'Đánh trọng điểm ') + (selectedLessons[id]?.name || id)"
                                                        :title="selectedLessons[id]?.is_priority ? 'Trọng điểm — bấm để bỏ' : 'Không trọng điểm — bấm để đánh sao'"
                                                    >
                                                        <span
                                                            class="material-symbols-outlined text-[14px]"
                                                            :class="selectedLessons[id]?.is_priority ? 'text-amber-600' : 'text-on-surface-variant/50'"
                                                            aria-hidden="true"
                                                        >star</span>
                                                    </button>
                                                @else
                                                    <span
                                                        class="material-symbols-outlined text-[14px]"
                                                        :class="selectedLessons[id]?.is_priority ? 'text-amber-600' : 'text-on-surface-variant/50'"
                                                        aria-hidden="true"
                                                    >star</span>
                                                @endif
                                                <span class="truncate" x-text="selectedLessons[id]?.name || ('#'+id)"></span>
                                                @if ($canUpdate)
                                                    <button
                                                        type="button"
                                                        @click="removeLesson(id)"
                                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-primary/70 transition hover:bg-primary/15 hover:text-primary"
                                                        :aria-label="'Xóa ' + (selectedLessons[id]?.name || id)"
                                                    >
                                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">close</span>
                                                    </button>
                                                @endif
                                            </span>
                                        </template>
                                        <template x-for="id in selectedTagIds" :key="'chip-tag-'+id">
                                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg bg-secondary-container px-2 py-1 text-xs font-medium text-on-secondary-container">
                                                <span class="material-symbols-outlined text-[12px]" aria-hidden="true">sell</span>
                                                <span class="truncate" x-text="selectedTags[id]?.name || ('#'+id)"></span>
                                                <span class="shrink-0 text-[10px] font-normal opacity-70">Tag</span>
                                                @if ($canUpdate)
                                                    <button
                                                        type="button"
                                                        @click="removeTag(id)"
                                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md opacity-70 transition hover:bg-on-secondary-container/10 hover:opacity-100"
                                                        :aria-label="'Xóa tag ' + (selectedTags[id]?.name || id)"
                                                    >
                                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">close</span>
                                                    </button>
                                                @endif
                                            </span>
                                        </template>
                                    </div>

                                    @if ($canUpdate)
                                        <div class="flex gap-1 rounded-lg bg-surface-container-low p-1">
                                            <button
                                                type="button"
                                                @click="linkMode = 'lesson'; clearSearch()"
                                                class="flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                                :class="linkMode === 'lesson' ? 'bg-surface text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                                            >Bài học</button>
                                            <button
                                                type="button"
                                                @click="linkMode = 'tag'; clearSearch()"
                                                class="flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                                :class="linkMode === 'tag' ? 'bg-surface text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                                            >Tag</button>
                                        </div>

                                        <div class="relative">
                                            <div class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-on-surface-variant" aria-hidden="true">
                                                    <span class="material-symbols-outlined text-[18px]">search</span>
                                                </span>
                                                <input
                                                    type="search"
                                                    x-model="searchQuery"
                                                    @input.debounce.300ms="runSearch()"
                                                    @keydown.enter.prevent="addFirstResult()"
                                                    @keydown.escape.prevent="clearSearch()"
                                                    :placeholder="linkMode === 'tag' ? 'Tìm tag…' : 'Tìm bài học…'"
                                                    autocomplete="off"
                                                    class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                                >
                                            </div>

                                            <div
                                                x-show="searchResults.length > 0"
                                                x-cloak
                                                class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-outline-variant bg-surface py-1 shadow-md"
                                                role="listbox"
                                            >
                                                <template x-for="item in searchResults" :key="linkMode + '-' + item.id">
                                                    <button
                                                        type="button"
                                                        role="option"
                                                        @click="addResult(item)"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-surface-container-low"
                                                        :class="isResultSelected(item.id) && 'bg-primary/5'"
                                                    >
                                                        <span class="material-symbols-outlined text-[16px] text-on-surface-variant" aria-hidden="true"
                                                              x-text="isResultSelected(item.id) ? 'check_circle' : 'add_circle'"></span>
                                                        <span class="min-w-0 flex-1 truncate font-medium" x-text="item.name"></span>
                                                        <span
                                                            class="shrink-0 text-[10px] uppercase text-on-surface-variant"
                                                            x-text="linkMode === 'tag' ? 'Tag' : 'Bài học'"
                                                        ></span>
                                                    </button>
                                                </template>
                                            </div>

                                            <p
                                                x-show="searchQuery.trim().length > 0 && !searching && searchResults.length === 0"
                                                x-cloak
                                                class="mt-1.5 text-xs text-on-surface-variant"
                                            >Không tìm thấy mục phù hợp.</p>
                                        </div>

                                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-outline-variant/60 pt-3">
                                            <p
                                                class="mr-auto min-h-[1.25rem] text-xs"
                                                :class="statusError ? 'text-error' : (statusMessage ? 'text-primary' : 'text-on-surface-variant')"
                                                x-text="statusMessage || (isDirty ? 'Có thay đổi chưa lưu' : '')"
                                            ></p>
                                            <button
                                                type="button"
                                                @click="save()"
                                                :disabled="!isDirty || saving"
                                                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-3.5 text-xs font-semibold text-on-primary transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true" x-text="saving ? 'progress_activity' : 'save'"></span>
                                                <span x-text="saving ? 'Đang lưu…' : 'Lưu liên kết'"></span>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="text-on-surface-variant">Chưa có chủ đề lâm sàng.</li>
                        @endforelse
                    </ul>

                    @if ($canUpdate)
                        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprint-sections.core-topics.store'))
<form method="post" action="{{ route('admin.blueprint-sections.core-topics.store', $section) }}" class="flex flex-wrap gap-2">
                            @csrf
                            <input name="name" placeholder="Tên chủ đề lâm sàng" required class="min-w-[200px] flex-1 rounded-lg bg-surface-container-low px-3 py-2 text-sm">
                            <input type="number" name="sort_order" min="0" value="0" class="w-20 rounded-lg bg-surface-container-low px-2 py-2 text-sm" title="Thứ tự">
                            <button class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-on-primary">Thêm chủ đề</button>
                        </form>
@endif
                    @endif
                </div>
            @endforeach

            @if ($canUpdate)
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprints.sections.store'))
<form method="post" action="{{ route('admin.blueprints.sections.store', $blueprint) }}" class="flex flex-wrap gap-2 rounded-xl border border-dashed border-outline-variant p-4">
                    @csrf
                    <input name="name" placeholder="Tên phần mới" required class="min-w-[200px] flex-1 rounded-lg bg-surface-container-low px-3 py-2">
                    <input type="number" name="sort_order" min="0" value="0" class="w-20 rounded-lg bg-surface-container-low px-2 py-2" title="Thứ tự">
                    <button class="rounded-lg border border-outline-variant px-4 py-2 font-semibold hover:bg-surface-container-low">Thêm phần</button>
                </form>
@endif
            @endif

            {{-- Confirm delete dialog --}}
            <div
                x-show="confirmOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="presentation"
            >
                <div
                    class="absolute inset-0 bg-on-surface/40 backdrop-blur-[2px]"
                    @click="closeConfirm()"
                    aria-hidden="true"
                ></div>
                <div
                    class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-6 shadow-xl"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="blueprint-confirm-title"
                    aria-describedby="blueprint-confirm-desc"
                    @click.stop
                >
                    <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-error-container/50 text-error">
                        <span class="material-symbols-outlined text-[28px]" aria-hidden="true">delete</span>
                    </div>
                    <h2 id="blueprint-confirm-title" class="font-headline-sm text-headline-sm text-on-surface" x-text="confirmTitle"></h2>
                    <p id="blueprint-confirm-desc" class="mt-2 font-body-sm leading-6 text-on-surface-variant" x-text="confirmMessage"></p>
                    <form method="post" :action="confirmAction" class="mt-6 flex flex-wrap justify-end gap-3">
                        @csrf
                        @method('DELETE')
                        <button
                            type="button"
                            x-ref="confirmCancel"
                            @click="closeConfirm()"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-outline-variant px-4 font-label-md font-semibold text-on-surface-variant transition hover:bg-surface-container-low"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg bg-error px-4 font-label-md font-semibold text-on-error transition hover:opacity-90"
                        >
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                            <span x-text="confirmLabel">Xóa</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @once
        <script>
            function blueprintTopicLinkMapper(config) {
                const normalizeLessons = (lessons) => {
                    const map = {};
                    Object.values(lessons || {}).forEach((lesson) => {
                        const id = Number(lesson.id);
                        map[id] = {
                            id,
                            name: lesson.name,
                            is_priority: lesson.is_priority !== false && lesson.is_priority !== 0 && lesson.is_priority !== '0',
                        };
                    });

                    return map;
                };

                const initialLessons = normalizeLessons(config.initialLessons || {});
                const initialLessonIds = Object.keys(initialLessons).map((id) => Number(id)).sort((a, b) => a - b);
                const initialTags = { ...(config.initialTags || {}) };
                const initialTagIds = Object.keys(initialTags).map((id) => Number(id)).sort((a, b) => a - b);

                const lessonPrioritySnapshot = (lessons, ids) => ids
                    .map(Number)
                    .sort((a, b) => a - b)
                    .map((id) => `${id}:${lessons[id]?.is_priority ? 1 : 0}`)
                    .join('|');

                return {
                    open: false,
                    linkMode: 'lesson',
                    searchQuery: '',
                    searchResults: [],
                    searching: false,
                    saving: false,
                    statusMessage: '',
                    statusError: false,
                    canUpdate: Boolean(config.canUpdate),
                    lessonLookupUrl: config.lessonLookupUrl,
                    tagLookupUrl: config.tagLookupUrl,
                    syncUrl: config.syncUrl,
                    csrfToken: config.csrfToken,
                    selectedLessons: { ...initialLessons },
                    selectedLessonIds: [...initialLessonIds],
                    savedLessonIds: [...initialLessonIds],
                    savedLessonPriorities: lessonPrioritySnapshot(initialLessons, initialLessonIds),
                    selectedTags: { ...initialTags },
                    selectedTagIds: [...initialTagIds],
                    savedTagIds: [...initialTagIds],

                    get isDirty() {
                        return ! this.sameIdList(this.selectedLessonIds, this.savedLessonIds)
                            || ! this.sameIdList(this.selectedTagIds, this.savedTagIds)
                            || this.savedLessonPriorities !== lessonPrioritySnapshot(this.selectedLessons, this.selectedLessonIds);
                    },

                    get priorityLessonCount() {
                        return this.selectedLessonIds.filter((id) => this.selectedLessons[id]?.is_priority).length;
                    },

                    sameIdList(a, b) {
                        const left = [...a].map(Number).sort((x, y) => x - y);
                        const right = [...b].map(Number).sort((x, y) => x - y);
                        if (left.length !== right.length) {
                            return false;
                        }

                        return left.every((id, index) => id === right[index]);
                    },

                    linkCountLabel() {
                        const total = this.selectedLessonIds.length + this.selectedTagIds.length;

                        return total + ' liên kết';
                    },

                    priorityCountLabel() {
                        return this.priorityLessonCount + '/' + this.selectedLessonIds.length + ' trọng điểm';
                    },

                    isResultSelected(id) {
                        const value = Number(id);

                        return this.linkMode === 'tag'
                            ? this.selectedTagIds.includes(value)
                            : this.selectedLessonIds.includes(value);
                    },

                    clearSearch() {
                        this.searchQuery = '';
                        this.searchResults = [];
                    },

                    async runSearch() {
                        const q = this.searchQuery.trim();
                        if (q.length < 1) {
                            this.searchResults = [];
                            this.searching = false;
                            return;
                        }

                        this.searching = true;
                        const url = this.linkMode === 'tag' ? this.tagLookupUrl : this.lessonLookupUrl;
                        try {
                            const response = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                                headers: { Accept: 'application/json' },
                            });
                            const json = await response.json();
                            this.searchResults = json.data ?? [];
                        } catch {
                            this.searchResults = [];
                        } finally {
                            this.searching = false;
                        }
                    },

                    addFirstResult() {
                        if (this.searchResults.length) {
                            this.addResult(this.searchResults[0]);
                        }
                    },

                    addResult(item) {
                        if (this.linkMode === 'tag') {
                            this.addTag(item);
                            return;
                        }

                        this.addLesson(item);
                    },

                    addLesson(lesson) {
                        const id = Number(lesson.id);
                        if (this.selectedLessonIds.includes(id)) {
                            this.removeLesson(id);
                            return;
                        }

                        this.selectedLessonIds.push(id);
                        this.selectedLessons[id] = {
                            id,
                            name: lesson.name,
                            is_priority: true,
                        };
                        this.statusMessage = '';
                        this.clearSearch();
                    },

                    toggleLessonPriority(id) {
                        const lessonId = Number(id);
                        const lesson = this.selectedLessons[lessonId];
                        if (! lesson) {
                            return;
                        }

                        lesson.is_priority = ! lesson.is_priority;
                        this.statusMessage = '';
                    },

                    removeLesson(id) {
                        const lessonId = Number(id);
                        this.selectedLessonIds = this.selectedLessonIds.filter((item) => item !== lessonId);
                        delete this.selectedLessons[lessonId];
                        this.statusMessage = '';
                    },

                    addTag(tag) {
                        const id = Number(tag.id);
                        if (this.selectedTagIds.includes(id)) {
                            this.removeTag(id);
                            return;
                        }

                        this.selectedTagIds.push(id);
                        this.selectedTags[id] = {
                            id,
                            name: tag.name,
                        };
                        this.statusMessage = '';
                        this.clearSearch();
                    },

                    removeTag(id) {
                        const tagId = Number(id);
                        this.selectedTagIds = this.selectedTagIds.filter((item) => item !== tagId);
                        delete this.selectedTags[tagId];
                        this.statusMessage = '';
                    },

                    async save() {
                        if (! this.isDirty || this.saving) {
                            return;
                        }

                        this.saving = true;
                        this.statusMessage = '';
                        this.statusError = false;

                        try {
                            const payload = {
                                lessons: this.selectedLessonIds.map((id) => ({
                                    id: Number(id),
                                    is_priority: Boolean(this.selectedLessons[id]?.is_priority),
                                })),
                                tag_ids: this.selectedTagIds.map((id) => Number(id)),
                            };

                            const response = await fetch(this.syncUrl, {
                                method: 'PUT',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                },
                                body: JSON.stringify(payload),
                            });

                            if (! response.ok) {
                                throw new Error('save_failed');
                            }

                            const json = await response.json().catch(() => null);
                            if (json?.data?.lessons) {
                                const nextLessons = normalizeLessons(
                                    Object.fromEntries(json.data.lessons.map((lesson) => [lesson.id, lesson])),
                                );
                                this.selectedLessons = nextLessons;
                                this.selectedLessonIds = Object.keys(nextLessons).map(Number).sort((a, b) => a - b);
                            }

                            this.savedLessonIds = [...this.selectedLessonIds].map(Number).sort((a, b) => a - b);
                            this.savedLessonPriorities = lessonPrioritySnapshot(this.selectedLessons, this.selectedLessonIds);
                            this.savedTagIds = [...this.selectedTagIds].map(Number).sort((a, b) => a - b);
                            this.statusMessage = 'Đã lưu liên kết.';
                            this.statusError = false;
                        } catch {
                            this.statusMessage = 'Không lưu được. Thử lại.';
                            this.statusError = true;
                        } finally {
                            this.saving = false;
                        }
                    },
                };
            }

            function blueprintWeightMatrix(config) {
                const cloneSections = (sections) => (sections || []).map((section) => ({
                    id: Number(section.id),
                    name: section.name,
                    weight_min: section.weight_min ?? '',
                    weight_max: section.weight_max ?? '',
                    open: Boolean(section.open),
                    topics: (section.topics || []).map((topic) => ({
                        id: Number(topic.id),
                        name: topic.name,
                        weight: topic.weight ?? '',
                    })),
                }));

                const initialTotal = config.totalQuestions == null || config.totalQuestions === ''
                    ? ''
                    : Number(config.totalQuestions);

                return {
                    saveUrl: config.saveUrl,
                    csrfToken: config.csrfToken,
                    canUpdate: Boolean(config.canUpdate),
                    totalQuestions: initialTotal,
                    sections: cloneSections(config.sections),
                    savedSnapshot: '',
                    saving: false,
                    statusMessage: '',
                    statusError: false,
                    isDirty: false,

                    init() {
                        this.savedSnapshot = this.snapshot();
                        this.isDirty = false;
                    },

                    get topicCount() {
                        return this.sections.reduce((sum, section) => sum + section.topics.length, 0);
                    },

                    get sectionCoverage() {
                        return this.coverageFor(this.sections, 'phần');
                    },

                    markDirty() {
                        this.isDirty = this.snapshot() !== this.savedSnapshot;
                        if (this.statusMessage && ! this.statusError) {
                            this.statusMessage = '';
                        }
                    },

                    snapshot() {
                        return JSON.stringify({
                            totalQuestions: this.normalizeNumber(this.totalQuestions),
                            sections: this.sections.map((section) => ({
                                id: section.id,
                                weight_min: this.normalizeNumber(section.weight_min),
                                weight_max: this.normalizeNumber(section.weight_max),
                                topics: section.topics.map((topic) => ({
                                    id: topic.id,
                                    weight: this.normalizeNumber(topic.weight),
                                })),
                            })),
                        });
                    },

                    normalizeNumber(value) {
                        if (value === null || value === undefined || value === '') {
                            return null;
                        }

                        const number = Number(value);
                        if (Number.isNaN(number)) {
                            return null;
                        }

                        return Math.round(number * 100) / 100;
                    },

                    parseWeight(value) {
                        return this.normalizeNumber(value);
                    },

                    coverageFor(items, label) {
                        const configured = items.filter((item) => {
                            return this.parseWeight(item.weight_min) !== null
                                || this.parseWeight(item.weight_max) !== null;
                        });

                        if (items.length === 0) {
                            return {
                                ok: true,
                                sumMin: 0,
                                sumMax: 0,
                                message: `Chưa có ${label} để cấu hình tỉ trọng.`,
                            };
                        }

                        if (configured.length === 0) {
                            return {
                                ok: false,
                                sumMin: 0,
                                sumMax: 0,
                                message: `Chưa nhập tỉ trọng ${label}. Cần Σ min ≤ 100 ≤ Σ max.`,
                            };
                        }

                        let sumMin = 0;
                        let sumMax = 0;
                        let incompletePair = false;

                        items.forEach((item) => {
                            const min = this.parseWeight(item.weight_min);
                            const max = this.parseWeight(item.weight_max);
                            if ((min !== null && max === null) || (min === null && max !== null)) {
                                incompletePair = true;
                            }
                            if (min !== null && max !== null && min > max) {
                                incompletePair = true;
                            }
                            sumMin += min ?? 0;
                            sumMax += max ?? 0;
                        });

                        sumMin = Math.round(sumMin * 100) / 100;
                        sumMax = Math.round(sumMax * 100) / 100;

                        if (incompletePair) {
                            return {
                                ok: false,
                                sumMin,
                                sumMax,
                                message: `Mỗi ${label} cần đủ cặp min–max hợp lệ (min ≤ max).`,
                            };
                        }

                        const ok = sumMin <= 100 && sumMax >= 100;
                        return {
                            ok,
                            sumMin,
                            sumMax,
                            message: ok
                                ? `${label.charAt(0).toUpperCase() + label.slice(1)} phủ đủ 100%.`
                                : `Σ min = ${this.formatPct(sumMin)} / Σ max = ${this.formatPct(sumMax)} — chưa phủ đủ 100% (cần Σ min ≤ 100 ≤ Σ max).`,
                        };
                    },

                    topicCoverage(section) {
                        const topics = section.topics || [];
                        if (topics.length === 0) {
                            return {
                                ok: true,
                                sum: 0,
                                message: 'Chưa có chủ đề để cấu hình tỉ trọng.',
                            };
                        }

                        const configured = topics.filter((topic) => this.parseWeight(topic.weight) !== null);
                        if (configured.length === 0) {
                            return {
                                ok: false,
                                sum: 0,
                                message: 'Chưa nhập tỉ trọng chủ đề. Tổng phải đúng 100%.',
                            };
                        }

                        const sum = Math.round(topics.reduce((total, topic) => {
                            return total + (this.parseWeight(topic.weight) ?? 0);
                        }, 0) * 100) / 100;

                        const ok = Math.abs(sum - 100) < 0.005;

                        return {
                            ok,
                            sum,
                            message: ok
                                ? 'Chủ đề trong phần đủ 100%.'
                                : `Σ chủ đề = ${this.formatPct(sum)} — cần đúng 100%.`,
                        };
                    },

                    formatPct(value) {
                        const number = Number(value || 0);
                        if (Number.isInteger(number)) {
                            return `${number}%`;
                        }

                        return `${number.toFixed(2).replace(/\.?0+$/, '')}%`;
                    },

                    coverageBarWidth(value) {
                        const number = Math.max(0, Number(value || 0));
                        const scale = Math.max(100, this.sectionCoverage.sumMin, this.sectionCoverage.sumMax, 1);

                        return Math.min(100, (number / scale) * 100);
                    },

                    coverageMarkerLeft() {
                        const scale = Math.max(100, this.sectionCoverage.sumMin, this.sectionCoverage.sumMax, 1);

                        return (100 / scale) * 100;
                    },

                    estimateRange(minPct, maxPct, baseTotal) {
                        const total = this.normalizeNumber(baseTotal);
                        if (total === null || total <= 0) {
                            return null;
                        }

                        const min = this.parseWeight(minPct);
                        const max = this.parseWeight(maxPct);
                        if (min === null && max === null) {
                            return null;
                        }

                        const low = Math.round(total * ((min ?? max) / 100));
                        const high = Math.round(total * ((max ?? min) / 100));

                        return [Math.min(low, high), Math.max(low, high)];
                    },

                    estimateLabel(minPct, maxPct) {
                        const range = this.estimateRange(minPct, maxPct, this.totalQuestions);
                        if (! range) {
                            return '—';
                        }

                        if (range[0] === range[1]) {
                            return `${range[0]} câu`;
                        }

                        return `${range[0]}–${range[1]} câu`;
                    },

                    topicEstimateLabel(section, topic) {
                        const sectionRange = this.estimateRange(section.weight_min, section.weight_max, this.totalQuestions);
                        const topicWeight = this.parseWeight(topic.weight);
                        if (topicWeight === null) {
                            return '—';
                        }

                        if (! sectionRange) {
                            return '—';
                        }

                        const low = Math.round(sectionRange[0] * (topicWeight / 100));
                        const high = Math.round(sectionRange[1] * (topicWeight / 100));
                        if (low === high) {
                            return `${low} câu`;
                        }

                        return `${Math.min(low, high)}–${Math.max(low, high)} câu`;
                    },

                    totalEstimateLabel() {
                        const total = this.normalizeNumber(this.totalQuestions);
                        if (total === null) {
                            return '—';
                        }

                        return `${total} câu`;
                    },

                    async save() {
                        if (! this.canUpdate || ! this.isDirty || this.saving) {
                            return;
                        }

                        this.saving = true;
                        this.statusMessage = '';
                        this.statusError = false;

                        try {
                            const payload = {
                                total_questions: this.normalizeNumber(this.totalQuestions),
                                sections: this.sections.map((section) => ({
                                    id: section.id,
                                    weight_min: this.normalizeNumber(section.weight_min),
                                    weight_max: this.normalizeNumber(section.weight_max),
                                    topics: section.topics.map((topic) => ({
                                        id: topic.id,
                                        weight: this.normalizeNumber(topic.weight),
                                    })),
                                })),
                            };

                            const response = await fetch(this.saveUrl, {
                                method: 'PUT',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                },
                                body: JSON.stringify(payload),
                            });

                            if (! response.ok) {
                                let message = 'Không lưu được. Thử lại.';
                                try {
                                    const json = await response.json();
                                    if (json.message) {
                                        message = json.message;
                                    } else if (json.errors) {
                                        const first = Object.values(json.errors)[0];
                                        if (Array.isArray(first) && first[0]) {
                                            message = first[0];
                                        }
                                    }
                                } catch {
                                    // keep default
                                }
                                throw new Error(message);
                            }

                            this.savedSnapshot = this.snapshot();
                            this.isDirty = false;
                            this.statusMessage = 'Đã lưu cấu hình tỉ trọng.';
                            this.statusError = false;
                        } catch (error) {
                            this.statusMessage = error instanceof Error ? error.message : 'Không lưu được. Thử lại.';
                            this.statusError = true;
                        } finally {
                            this.saving = false;
                        }
                    },
                };
            }
        </script>
    @endonce
</x-layouts.admin>
