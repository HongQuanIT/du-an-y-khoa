<x-layouts.admin title="CMS — Menu">
    @include('admin::cms._sub-nav')

    <x-admin.page-header title="Menu điều hướng"
        description="Chỉnh liên kết header và footer trang công khai. Không cần deploy.">
    </x-admin.page-header>

    <x-admin.flash />

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Menu</th>
                    <th class="px-4 py-3">Key</th>
                    <th class="px-4 py-3">Cập nhật</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php
                        /** @var \Modules\Admin\Support\Enums\MenuKey $key */
                        $key = $row['key'];
                        /** @var \Modules\Admin\Models\Menu|null $menu */
                        $menu = $row['menu'];
                    @endphp
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md text-label-md text-on-surface">{{ $menu?->name ?? $key->label() }}</div>
                            <div class="mt-0.5 max-w-xl font-label-sm text-label-sm text-on-surface-variant">{{ $key->description() }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <code class="rounded bg-surface-container-low px-1.5 py-0.5 text-xs">{{ $key->value }}</code>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap">
                            {{ $menu?->updated_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($menu)
                                <a href="{{ route('admin.cms.menus.edit', $menu) }}"
                                    class="font-label-md text-primary hover:underline">Sửa</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
