<x-layouts.admin :title="$config['title']">
    <div x-data="{ formModalOpen: @js($editing !== null || $errors->any() || request()->boolean('create')) }">
    <x-admin.page-header :title="'Quản lý '.$config['title']" description="Danh mục chuẩn được dùng trong hồ sơ và autocomplete của học viên.">
        @if ($canManage)
            <x-slot:actions>
                <a href="{{ route($config['route'].'.index', ['create' => 1]) }}" class="rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary hover:opacity-90">Thêm {{ $config['singular'] }}</a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @include('admin::learner-data._tabs')
    <x-admin.flash />
    <x-auth.errors />

    <div>
        <section>
            <form method="get" class="mb-4 grid gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <label for="q" class="mb-1 block text-label-sm text-on-surface-variant">Tên hoặc mã</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nhập từ khóa tìm kiếm"
                        class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-body-sm">
                </div>
                @if ($catalog === 'administrative-units')
                    <div>
                        <label for="country_filter" class="mb-1 block text-label-sm text-on-surface-variant">Quốc gia</label>
                        <select id="country_filter" name="country_id" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-body-sm">
                            <option value="">Tất cả</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @selected((string) ($filters['country_id'] ?? '') === (string) $country->id)>{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="status" class="mb-1 block text-label-sm text-on-surface-variant">Trạng thái</label>
                    <select id="status" name="status" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-body-sm">
                        <option value="">Tất cả</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Đang hiển thị</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Đã ẩn</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button class="h-10 rounded-lg bg-primary px-4 font-label-sm font-semibold text-on-primary">Lọc</button>
                    <a href="{{ route($config['route'].'.index') }}" class="flex h-10 items-center px-2 text-label-sm text-on-surface-variant hover:underline">Xóa</a>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
                <table class="min-w-full text-left text-body-sm">
                    <thead class="border-b border-outline-variant bg-surface-container-low text-label-md text-on-surface-variant">
                        <tr>
                            <th class="px-4 py-3">Tên danh mục</th>
                            <th class="px-4 py-3">Thông tin</th>
                            <th class="px-4 py-3">Học viên</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr class="border-b border-outline-variant/60 last:border-0">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-on-surface">{{ $item->name }}</p>
                                    <p class="font-mono text-label-sm text-on-surface-variant">{{ $item->code }} · TT {{ $item->sort_order }}</p>
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant">
                                    @if ($catalog === 'countries')
                                        {{ number_format($item->administrative_units_count) }} địa phương · {{ number_format($item->institutions_count) }} trường
                                    @elseif ($catalog === 'administrative-units')
                                        {{ $item->country?->name }} · {{ $item->type === 'city' ? 'Thành phố' : 'Tỉnh' }} · {{ number_format($item->institutions_count) }} trường
                                    @elseif ($catalog === 'professions')
                                        {{ $item->defaults_to_graduated ? 'Mặc định đã tốt nghiệp' : ($item->requires_education_stage ? 'Yêu cầu năm học' : 'Không yêu cầu năm học') }}
                                    @else
                                        {{ $item->is_graduated ? 'Đã tốt nghiệp' : 'Đang học' }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ number_format($item->learner_profiles_count) }}</td>
                                <td class="px-4 py-3">
                                    <span @class(['rounded-full px-2.5 py-1 text-label-sm', 'bg-primary-fixed/40 text-primary' => $item->is_active, 'bg-surface-container text-on-surface-variant' => ! $item->is_active])>
                                        {{ $item->is_active ? 'Đang hiển thị' : 'Đã ẩn' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($canManage)
                                        <a href="{{ route($config['route'].'.index', ['edit' => $item->id] + $filters) }}" class="font-label-sm font-semibold text-primary hover:underline">Sửa</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">Chưa có dữ liệu phù hợp.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $items->links() }}</div>
        </section>

    </div>

    @if ($canManage)
        <div x-cloak x-show="formModalOpen" x-transition.opacity @keydown.escape.window="@if($editing) window.location.href = '{{ route($config['route'].'.index') }}' @else formModalOpen = false @endif"
            class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="catalog-form-title">
            @if ($editing)
                <a href="{{ route($config['route'].'.index') }}" class="absolute inset-0 bg-scrim/50" aria-label="Đóng"></a>
            @else
                <button type="button" class="absolute inset-0 bg-scrim/50" aria-label="Đóng" @click="formModalOpen = false"></button>
            @endif
            <section x-show="formModalOpen" x-transition class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-outline-variant bg-surface p-5 shadow-xl md:p-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 id="catalog-form-title" class="font-title-lg font-semibold text-on-surface">{{ $editing ? 'Chỉnh sửa '.$config['singular'] : 'Thêm '.$config['singular'] }}</h2>
                    @if ($editing)
                        <a href="{{ route($config['route'].'.index') }}" class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container" aria-label="Đóng"><span class="material-symbols-outlined">close</span></a>
                    @else
                        <button type="button" @click="formModalOpen = false" class="flex size-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container" aria-label="Đóng"><span class="material-symbols-outlined">close</span></button>
                    @endif
                </div>
                @include('admin::learner-data.catalogs._form')
            </section>
        </div>
    @endif
    </div>
</x-layouts.admin>
