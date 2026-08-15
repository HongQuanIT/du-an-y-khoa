<x-layouts.admin title="CMS — FAQ">
    @include('admin::cms._sub-nav')

    <x-admin.page-header title="FAQ"
        description="Quản lý câu hỏi thường gặp hiển thị trên trang /faq.">
        <x-slot:actions>
            <a href="{{ route('landing.faq') }}" target="_blank" rel="noopener noreferrer"
                class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-on-surface hover:bg-surface-container-low">
                Xem trang FAQ ↗
            </a>
            <a href="{{ route('admin.cms.faq.create') }}"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">
                + Thêm FAQ
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Tổng FAQ" :value="number_format($stats['total'])" hint="Tất cả bản ghi" icon="help" />
        <x-admin.kpi-card label="Đã xuất bản" :value="number_format($stats['published'])" hint="Hiển thị trên web" icon="visibility" />
        <x-admin.kpi-card label="Nháp" :value="number_format($stats['draft'])" hint="Chưa hiển thị công khai" icon="draft" />
    </div>

    <form method="get" action="{{ route('admin.cms.faq.index') }}"
        class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="q">Tìm kiếm</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Câu hỏi hoặc nội dung trả lời"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="category">Danh mục</label>
            <select id="category" name="category"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->value }}" @selected($filters['category'] === $cat->value)>{{ $cat->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                <option value="">Tất cả</option>
                <option value="published" @selected($filters['status'] === 'published')>Đã xuất bản</option>
                <option value="draft" @selected($filters['status'] === 'draft')>Nháp</option>
            </select>
        </div>
        <div class="sm:col-span-4 flex gap-2">
            <button type="submit"
                class="rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary hover:opacity-90">Lọc</button>
            <a href="{{ route('admin.cms.faq.index') }}"
                class="rounded-lg px-4 py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3 w-16">TT</th>
                    <th class="px-4 py-3">Câu hỏi</th>
                    <th class="px-4 py-3">Danh mục</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Cập nhật</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($faqs as $faq)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3 text-on-surface-variant">
                            <div class="flex flex-col gap-1">
                                <span class="font-mono text-xs">{{ $faq->sort_order }}</span>
                                <div class="flex gap-0.5">
                                    <form method="post" action="{{ route('admin.cms.faq.move-up', $faq) }}">
                                        @csrf
                                        <button type="submit" class="rounded p-0.5 text-on-surface-variant hover:bg-surface-container-low" title="Lên">
                                            <span class="material-symbols-outlined text-[18px]">arrow_upward</span>
                                        </button>
                                    </form>
                                    <form method="post" action="{{ route('admin.cms.faq.move-down', $faq) }}">
                                        @csrf
                                        <button type="submit" class="rounded p-0.5 text-on-surface-variant hover:bg-surface-container-low" title="Xuống">
                                            <span class="material-symbols-outlined text-[18px]">arrow_downward</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-label-md text-label-md text-on-surface max-w-md">{{ $faq->question }}</div>
                            <div class="font-label-sm text-label-sm text-on-surface-variant">#{{ $faq->id }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $faq->category->label() }}</td>
                        <td class="px-4 py-3">
                            @if ($faq->is_published)
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Đã xuất bản</span>
                            @else
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Nháp</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap">
                            {{ $faq->updated_at?->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.cms.faq.edit', $faq) }}"
                                class="font-label-md text-primary hover:underline">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-on-surface-variant">
                            Chưa có FAQ nào.
                            <a href="{{ route('admin.cms.faq.create') }}" class="text-primary hover:underline">Thêm FAQ đầu tiên</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($faqs->hasPages())
        <div class="mt-4">
            {{ $faqs->links() }}
        </div>
    @endif
</x-layouts.admin>
