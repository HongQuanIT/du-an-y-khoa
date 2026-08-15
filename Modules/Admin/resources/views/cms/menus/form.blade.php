@php
    use Modules\Admin\Support\Enums\MenuKey;

    $key = $menu->key;
    $isHeader = $key === MenuKey::Header;
    $isFooter = $key === MenuKey::Footer;

    $initial = $isHeader
        ? ['links' => old('items.links', $items['links'] ?? [])]
        : [
            'brand_blurb' => old('items.brand_blurb', $items['brand_blurb'] ?? ''),
            'columns' => old('items.columns', $items['columns'] ?? []),
            'bottom_links' => old('items.bottom_links', $items['bottom_links'] ?? []),
        ];

    $blankLink = ['label' => '', 'type' => 'route', 'value' => 'landing.home', 'enabled' => true];
@endphp

<x-layouts.admin title="CMS — Sửa menu">
    @include('admin::cms._sub-nav')

    <x-admin.page-header :title="'Sửa: '.($menu->name ?: $key?->label())"
        :description="$key?->description() ?? 'Chỉnh liên kết điều hướng công khai.'">
        <x-slot:actions>
            <a href="{{ route('admin.cms.menus.index') }}"
                class="inline-flex items-center rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
            <a href="{{ route('landing.home') }}" target="_blank" rel="noopener noreferrer"
                class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface hover:bg-surface-container-low">
                Xem landing
                <span class="material-symbols-outlined text-[18px] leading-none">open_in_new</span>
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <form method="post" action="{{ route('admin.cms.menus.update', $menu) }}" class="w-full max-w-4xl space-y-6"
        x-data="menuBuilder(@js($initial), @js($blankLink))">
        @csrf
        @method('PUT')

        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <div class="border-b border-outline-variant px-5 py-4">
                <h3 class="font-label-md text-label-md text-on-surface">Thông tin</h3>
            </div>
            <div class="space-y-4 p-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <span class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant">Key hệ thống</span>
                        <p class="font-label-md text-on-surface">{{ $key?->value }}</p>
                    </div>
                    <div class="min-w-0">
                        <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="name">Tên hiển thị <span class="text-error">*</span></label>
                        <input id="name" name="name" type="text" required maxlength="255"
                            value="{{ old('name', $menu->name) }}"
                            class="block w-full min-w-0 rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary">
                        @error('name')
                            <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        @if ($isHeader)
            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant px-5 py-4">
                    <div>
                        <h3 class="font-label-md text-label-md text-on-surface">Liên kết header</h3>
                        <p class="mt-1 font-label-sm text-on-surface-variant">Thứ tự trên desktop và drawer mobile. Đăng nhập / Đăng ký giữ cố định.</p>
                    </div>
                    <button type="button" @click="addLink('links')"
                        class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-on-surface hover:bg-surface-container-low">+ Thêm link</button>
                </div>
                <div class="space-y-3 p-5">
                    <template x-for="(link, index) in items.links" :key="'h-'+index">
                        <div class="rounded-lg bg-surface-container-lowest p-4">
                            @include('admin::cms.menus._link-fields', [
                                'prefix' => 'items[links]',
                                'indexExpr' => 'index',
                                'linkExpr' => 'link',
                                'listExpr' => 'items.links',
                                'routeOptions' => $routeOptions,
                            ])
                        </div>
                    </template>
                    @error('items.links')
                        <p class="font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endif

        @if ($isFooter)
            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="border-b border-outline-variant px-5 py-4">
                    <h3 class="font-label-md text-label-md text-on-surface">Thương hiệu</h3>
                </div>
                <div class="p-5">
                    <label class="mb-1.5 block font-label-sm text-label-sm text-on-surface-variant" for="brand_blurb">Mô tả ngắn</label>
                    <textarea id="brand_blurb" name="items[brand_blurb]" rows="3" maxlength="1000" x-model="items.brand_blurb"
                        class="block w-full min-w-0 resize-y rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm text-body-sm focus:ring-2 focus:ring-primary"></textarea>
                    @error('items.brand_blurb')
                        <p class="mt-1 font-label-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant px-5 py-4">
                    <div>
                        <h3 class="font-label-md text-label-md text-on-surface">Cột liên kết</h3>
                        <p class="mt-1 font-label-sm text-on-surface-variant">Tối đa 6 cột. Mỗi cột có tiêu đề và danh sách link.</p>
                    </div>
                    <button type="button" @click="addColumn()"
                        class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-on-surface hover:bg-surface-container-low">+ Thêm cột</button>
                </div>
                <div class="space-y-6 p-5">
                    <template x-for="(column, cIndex) in items.columns" :key="'c-'+cIndex">
                        <div class="space-y-3 rounded-xl border border-outline-variant/70 p-4">
                            <div class="flex flex-wrap items-end justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <label class="mb-1.5 block font-label-sm text-on-surface-variant">Tiêu đề cột</label>
                                    <input type="text" required maxlength="120" x-model="column.title"
                                        :name="'items[columns]['+cIndex+'][title]'"
                                        class="block w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-lg px-2 py-1.5 font-label-sm text-on-surface-variant hover:bg-surface-container-low"
                                        @click="moveItem(items.columns, cIndex, -1)" :disabled="cIndex === 0">↑</button>
                                    <button type="button" class="rounded-lg px-2 py-1.5 font-label-sm text-on-surface-variant hover:bg-surface-container-low"
                                        @click="moveItem(items.columns, cIndex, 1)" :disabled="cIndex === items.columns.length - 1">↓</button>
                                    <button type="button" class="rounded-lg px-2 py-1.5 font-label-sm text-error hover:bg-error/10"
                                        @click="removeAt(items.columns, cIndex)" :disabled="items.columns.length <= 1">Xóa cột</button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <p class="font-label-sm text-on-surface-variant">Liên kết trong cột</p>
                                <button type="button" @click="addColumnLink(cIndex)"
                                    class="font-label-sm text-primary hover:underline">+ Thêm link</button>
                            </div>

                            <template x-for="(link, index) in column.links" :key="'cl-'+cIndex+'-'+index">
                                <div class="rounded-lg bg-surface-container-lowest p-4">
                                    @include('admin::cms.menus._link-fields', [
                                        'prefix' => null,
                                        'namePrefixExpr' => "'items[columns]['+cIndex+'][links]'",
                                        'indexExpr' => 'index',
                                        'linkExpr' => 'link',
                                        'listExpr' => 'column.links',
                                        'routeOptions' => $routeOptions,
                                    ])
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant px-5 py-4">
                    <div>
                        <h3 class="font-label-md text-label-md text-on-surface">Liên kết đáy footer</h3>
                        <p class="mt-1 font-label-sm text-on-surface-variant">Cookie / Sitemap… bên cạnh dòng copyright.</p>
                    </div>
                    <button type="button" @click="addLink('bottom_links')"
                        class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-on-surface hover:bg-surface-container-low">+ Thêm link</button>
                </div>
                <div class="space-y-3 p-5">
                    <template x-for="(link, index) in items.bottom_links" :key="'b-'+index">
                        <div class="rounded-lg bg-surface-container-lowest p-4">
                            @include('admin::cms.menus._link-fields', [
                                'prefix' => 'items[bottom_links]',
                                'indexExpr' => 'index',
                                'linkExpr' => 'link',
                                'listExpr' => 'items.bottom_links',
                                'routeOptions' => $routeOptions,
                            ])
                        </div>
                    </template>
                </div>
            </div>
        @endif

        <div class="sticky bottom-0 z-10 -mx-1 border-t border-outline-variant bg-surface-container-lowest/95 px-1 py-4 backdrop-blur supports-[backdrop-filter]:bg-surface-container-lowest/80">
            <button type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-label-md text-on-primary hover:opacity-90">
                Lưu menu
            </button>
        </div>
    </form>

    <script>
        function menuBuilder(initial, blankLink) {
            return {
                items: initial,
                blankLink,
                addLink(listKey) {
                    if (!Array.isArray(this.items[listKey])) {
                        this.items[listKey] = [];
                    }
                    this.items[listKey].push({ ...this.blankLink });
                },
                addColumn() {
                    if (!Array.isArray(this.items.columns)) {
                        this.items.columns = [];
                    }
                    this.items.columns.push({
                        title: 'Cột mới',
                        links: [{ ...this.blankLink }],
                    });
                },
                addColumnLink(cIndex) {
                    this.items.columns[cIndex].links.push({ ...this.blankLink });
                },
                removeAt(list, index) {
                    list.splice(index, 1);
                },
                moveItem(list, index, delta) {
                    const target = index + delta;
                    if (target < 0 || target >= list.length) return;
                    const [row] = list.splice(index, 1);
                    list.splice(target, 0, row);
                },
            };
        }
    </script>
</x-layouts.admin>
