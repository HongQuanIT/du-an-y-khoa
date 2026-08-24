<x-layouts.admin title="Ma trận đề thi">
    <x-admin.page-header title="Ma trận đề thi" description="Ma trận → Phần → Chủ đề lâm sàng (128 chủ đề).">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.blueprints.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-label-md font-semibold text-on-primary">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Tạo ma trận
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'blueprints'])

    <x-admin.flash />

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-sm">
            <thead class="bg-surface-container-low text-left font-label-sm text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Tên</th>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3 text-center">Phần</th>
                    <th class="px-4 py-3 text-center">Chủ đề lâm sàng</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($blueprints as $blueprint)
                    <tr class="border-t border-outline-variant hover:bg-surface-container-lowest/60">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-on-surface">{{ $blueprint->name }}</p>
                            @if ($blueprint->description)
                                <p class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ $blueprint->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $blueprint->code ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">{{ $blueprint->sections_count }}</td>
                        <td class="px-4 py-3 text-center font-semibold">{{ $coreTopicCounts[$blueprint->id] ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $blueprint->status->value === 'active' ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">
                                {{ $blueprint->status->value }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.blueprints.edit', $blueprint) }}" class="font-semibold text-primary hover:underline">Quản lý</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-on-surface-variant">
                            Chưa có ma trận đề thi.
                            @if ($canCreate)
                                <a href="{{ route('admin.blueprints.create') }}" class="ml-1 font-semibold text-primary hover:underline">Tạo ma trận đầu tiên</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
