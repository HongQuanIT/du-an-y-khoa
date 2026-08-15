<x-layouts.admin title="CMS — Banner">
    @include('admin::cms._sub-nav')

    <x-admin.page-header title="Banner / Thông báo"
        description="Quản lý banner hiển thị trên landing và dashboard học viên (lịch, đối tượng, bật/tắt).">
        <x-slot:actions>
            <a href="{{ route('admin.cms.banners.create') }}"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">
                + Thêm banner
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Tổng banner" :value="number_format($stats['total'])" hint="Tất cả bản ghi" icon="campaign" />
        <x-admin.kpi-card label="Đang bật" :value="number_format($stats['enabled'])" hint="Có thể hiển thị" icon="visibility" />
        <x-admin.kpi-card label="Đang tắt" :value="number_format($stats['disabled'])" hint="Ẩn khỏi web" icon="visibility_off" />
    </div>

    <form method="get" action="{{ route('admin.cms.banners.index') }}"
        class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="q">Tìm kiếm</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tiêu đề hoặc nội dung"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="placement">Vị trí</label>
            <select id="placement" name="placement"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($placements as $placement)
                    <option value="{{ $placement->value }}" @selected($filters['placement'] === $placement->value)>{{ $placement->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                <option value="enabled" @selected($filters['status'] === 'enabled')>Đang bật</option>
                <option value="disabled" @selected($filters['status'] === 'disabled')>Đang tắt</option>
            </select>
        </div>
        <div class="sm:col-span-4 flex gap-2">
            <button type="submit"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary hover:opacity-90">Lọc</button>
            <a href="{{ route('admin.cms.banners.index') }}"
                class="rounded-lg px-4 py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Banner</th>
                    <th class="px-4 py-3">Vị trí</th>
                    <th class="px-4 py-3">Đối tượng</th>
                    <th class="px-4 py-3">Lịch</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($banners as $banner)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md text-on-surface">{{ $banner->title }}</div>
                            <div class="mt-0.5 line-clamp-1 text-on-surface-variant">{{ $banner->body }}</div>
                            <div class="mt-1 font-label-sm text-on-surface-variant">{{ $banner->variant->label() }} · TT {{ $banner->sort_order }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $banner->placement->label() }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $banner->audience->label() }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-on-surface-variant">
                            @if ($banner->starts_at || $banner->ends_at)
                                {{ $banner->starts_at?->format('d/m/Y') ?? '…' }}
                                →
                                {{ $banner->ends_at?->format('d/m/Y') ?? '…' }}
                            @else
                                Không giới hạn
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($banner->is_enabled)
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Bật</span>
                            @else
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Tắt</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <form method="post" action="{{ route('admin.cms.banners.toggle', $banner) }}" class="inline">
                                @csrf
                                <button type="submit" class="font-label-md text-on-surface-variant hover:underline">
                                    {{ $banner->is_enabled ? 'Tắt' : 'Bật' }}
                                </button>
                            </form>
                            <span class="mx-1 text-outline-variant">·</span>
                            <a href="{{ route('admin.cms.banners.edit', $banner) }}"
                                class="font-label-md text-primary hover:underline">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">
                            Chưa có banner. <a href="{{ route('admin.cms.banners.create') }}" class="text-primary hover:underline">Tạo banner đầu tiên</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $banners->links() }}</div>
</x-layouts.admin>
