<x-layouts.admin title="Trung tâm báo cáo">
    <x-admin.page-header title="Trung tâm báo cáo"
        description="Danh mục báo cáo dựng sẵn. Lọc kỳ, biểu đồ, xuất CSV và lên lịch gửi email định kỳ.">
        <x-slot:actions>
            @can(\App\Support\Enums\Permission::SystemManage->value)
                <a href="{{ route('admin.settings.index', ['tab' => 'reports']) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface-variant transition hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[18px]">settings</span>
                    Chu kỳ cron
                </a>
            @endcan
            <form method="post" action="{{ route('admin.reports.cache.warm-all') }}"
                x-data="{ busy: @js(in_array($warmAllStatus['status'] ?? 'idle', ['queued', 'processing'], true)) }"
                @submit="busy = true">
                @csrf
                <button type="submit" :disabled="busy"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 py-2 font-label-md text-label-md text-on-surface transition hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-60">
                    <span class="material-symbols-outlined text-[18px]" :class="{ 'animate-spin': busy }" x-text="busy ? 'progress_activity' : 'cached'"></span>
                    <span x-text="busy ? 'Đang làm mới cache…' : 'Làm mới toàn bộ cache'"></span>
                </button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @if (in_array($warmAllStatus['status'] ?? 'idle', ['queued', 'processing'], true))
        <div class="mb-4 rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 font-body-sm text-on-surface" role="status"
            x-data="{
                status: @js($warmAllStatus['status']),
                poll() {
                    fetch(@js(route('admin.reports.cache.warm-all-status')), { headers: { Accept: 'application/json' } })
                        .then(r => r.json())
                        .then(d => {
                            this.status = d.status;
                            if (d.status === 'ready' || d.status === 'failed' || d.status === 'idle') window.location.reload();
                        });
                }
            }"
            x-init="setInterval(() => poll(), 3000)"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined animate-spin text-[20px]">progress_activity</span>
                    <span class="font-label-md">Đang làm mới toàn bộ cache báo cáo trong hàng đợi…</span>
                </div>
                <form method="post" action="{{ route('admin.reports.cache.warm-all-reset') }}">
                    @csrf
                    <button type="submit" class="font-label-sm text-on-surface-variant underline hover:text-on-surface">
                        Reset nếu Horizon không có job
                    </button>
                </form>
            </div>
        </div>
    @elseif (($warmAllStatus['status'] ?? '') === 'failed')
        <div class="mb-4 rounded-xl border border-error/30 bg-error-container/30 px-4 py-3 font-body-sm text-on-surface" role="alert">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p>
                    <span class="font-label-md">Làm mới cache thất bại / bị kẹt.</span>
                    {{ $warmAllStatus['error'] ?? '' }}
                </p>
                <form method="post" action="{{ route('admin.reports.cache.warm-all') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm hover:bg-surface">
                        Chạy lại
                    </button>
                </form>
            </div>
        </div>
    @endif
    @if ($categories === [])
        <div class="rounded-xl border border-dashed border-outline-variant bg-surface px-6 py-10 text-center">
            <span class="material-symbols-outlined mb-3 text-[40px] text-on-surface-variant">analytics</span>
            <h3 class="font-title-md text-on-surface">Không có báo cáo cho quyền hiện tại</h3>
            <p class="mx-auto mt-2 max-w-lg font-body-sm text-on-surface-variant">
                Liên hệ Super Admin để được cấp quyền xem báo cáo phù hợp vai trò của bạn.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($categories as $category)
                <x-admin.report-catalog-card
                    :title="$category['title']"
                    :description="$category['description']"
                    :icon="$category['icon']"
                    :report-count="count($category['reports'])"
                    :href="route('admin.reports.show-category', $category['slug'])" />
            @endforeach
        </div>

        <section class="mt-8 rounded-xl border border-outline-variant bg-surface p-5">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[22px] text-on-surface-variant">schedule</span>
                    <div>
                        <h3 class="font-headline-sm text-headline-sm text-on-surface">Báo cáo đã lên lịch</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">
                            Tắt lịch = dừng hẳn · Tắt email = vẫn chạy, không gửi mail
                        </p>
                    </div>
                </div>
                <p class="font-label-sm text-on-surface-variant">
                    Cache báo cáo · chu kỳ {{ $cacheMeta['interval_days'] ?? 1 }} ngày:
                    @if ($cacheMeta['warmed_at'])
                        cập nhật {{ $cacheMeta['warmed_at']->format('d/m/Y H:i') }}
                        ({{ $cacheMeta['count'] }} snapshot)
                    @else
                        chưa warm — cron sẽ chạy khi đủ chu kỳ (mặc định 1 ngày)
                    @endif
                    @can(\App\Support\Enums\Permission::SystemManage->value)
                        · <a href="{{ route('admin.settings.index', ['tab' => 'reports']) }}" class="text-primary hover:underline">Đổi trong Cài đặt → Báo cáo</a>
                    @endcan
                </p>
            </div>

            @if ($schedules->isEmpty())
                <div class="rounded-lg border border-dashed border-outline-variant px-4 py-8 text-center">
                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                        Chưa có lịch. Mở một báo cáo → kéo xuống «Lên lịch báo cáo».
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left font-body-sm text-body-sm">
                        <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2.5">Báo cáo</th>
                                <th class="px-3 py-2.5">Lịch</th>
                                <th class="px-3 py-2.5">Email</th>
                                <th class="px-3 py-2.5">Lần tới</th>
                                <th class="px-3 py-2.5">Trạng thái</th>
                                <th class="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedules as $schedule)
                                <tr class="border-b border-outline-variant/60 last:border-0">
                                    <td class="px-3 py-3">
                                        <a href="{{ route('admin.reports.show', [$schedule->category_slug, $schedule->report_slug]) }}"
                                            class="font-label-md text-primary hover:underline">
                                            {{ $schedule->reportTitle() }}
                                        </a>
                                        <div class="font-label-sm text-on-surface-variant">{{ $schedule->categoryTitle() }} · {{ $schedule->range_key }}</div>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-on-surface-variant">{{ $schedule->frequencySummary() }}</td>
                                    <td class="px-3 py-3 text-on-surface-variant">
                                        @if ($schedule->send_email)
                                            {{ implode(', ', array_slice($schedule->recipients, 0, 2)) }}
                                            @if (count($schedule->recipients) > 2)
                                                <span class="text-on-surface-variant/70">+{{ count($schedule->recipients) - 2 }}</span>
                                            @endif
                                        @else
                                            <span class="font-label-sm text-amber-800">Đã tắt gửi</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-on-surface-variant">
                                        {{ $schedule->next_run_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-col gap-1">
                                            @if ($schedule->is_active)
                                                <span class="w-fit rounded-full bg-emerald-500/10 px-2 py-0.5 font-label-sm text-emerald-700">Lịch bật</span>
                                            @else
                                                <span class="w-fit rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Lịch tắt</span>
                                            @endif
                                            @if ($schedule->send_email)
                                                <span class="w-fit rounded-full bg-sky-500/10 px-2 py-0.5 font-label-sm text-sky-800">Email bật</span>
                                            @else
                                                <span class="w-fit rounded-full bg-amber-500/10 px-2 py-0.5 font-label-sm text-amber-800">Email tắt</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <form method="post" action="{{ route('admin.reports.schedules.toggle', $schedule) }}">
                                                @csrf
                                                <button type="submit" class="font-label-sm text-primary hover:underline">
                                                    {{ $schedule->is_active ? 'Tắt lịch' : 'Bật lịch' }}
                                                </button>
                                            </form>
                                            <form method="post" action="{{ route('admin.reports.schedules.toggle-email', $schedule) }}">
                                                @csrf
                                                <button type="submit" class="font-label-sm text-primary hover:underline">
                                                    {{ $schedule->send_email ? 'Tắt email' : 'Bật email' }}
                                                </button>
                                            </form>
                                            <form method="post" action="{{ route('admin.reports.schedules.destroy', $schedule) }}"
                                                onsubmit="return confirm('Xóa lịch này?')">
                                                @csrf
                                                <button type="submit" class="font-label-sm text-error hover:underline">Xóa</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</x-layouts.admin>
