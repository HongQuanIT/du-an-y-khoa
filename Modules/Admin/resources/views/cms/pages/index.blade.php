@php
    $isLanding = ($group ?? 'static') === 'landing';
@endphp

<x-layouts.admin :title="$isLanding ? 'CMS — Landing' : 'CMS — Trang tĩnh'">
    @include('admin::cms._sub-nav')

    <x-admin.page-header
        :title="$isLanding ? 'Landing blocks' : 'Trang tĩnh'"
        :description="$isLanding
            ? 'Chỉnh copy/ảnh cho trang chủ và tính năng. Trang luôn public — nháp = nội dung mặc định.'
            : 'Quản lý nội dung trang công khai cố định (điều khoản, chính sách bảo mật, …).'">
    </x-admin.page-header>

    <x-admin.flash />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Tổng trang" :value="number_format($stats['total'])" hint="Trong nhóm này" icon="article" />
        <x-admin.kpi-card label="Đã xuất bản" :value="number_format($stats['published'])" hint="Nội dung CMS trên web" icon="visibility" />
        <x-admin.kpi-card label="Nháp" :value="number_format($stats['draft'])" :hint="$isLanding ? 'Public dùng mặc định' : 'Chưa hiển thị công khai'" icon="draft" />
    </div>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Trang</th>
                    <th class="px-4 py-3">URL công khai</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Cập nhật</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php
                        /** @var \Modules\Admin\Support\Enums\CmsPageKey $key */
                        $key = $row['key'];
                        /** @var \Modules\Admin\Models\CmsPage|null $page */
                        $page = $row['page'];
                    @endphp
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md text-label-md text-on-surface">{{ $page?->title ?? $key->defaultTitle() }}</div>
                            <div class="font-label-sm text-label-sm text-on-surface-variant">{{ $key->label() }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            <code class="rounded bg-surface-container-low px-1.5 py-0.5 text-xs">{{ $key->slug() }}</code>
                            @if ($page?->publicUrl())
                                <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener noreferrer"
                                    class="ml-2 font-label-sm text-primary hover:underline">Xem ↗</a>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($page?->isPublished())
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 font-label-sm text-primary">Đã xuất bản</span>
                            @else
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5 font-label-sm text-on-surface-variant">Nháp</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap">
                            {{ $page?->updated_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($page)
                                <a href="{{ route('admin.cms.pages.edit', $page) }}"
                                    class="font-label-md text-primary hover:underline">Sửa</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
