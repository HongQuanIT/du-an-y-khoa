<x-layouts.admin :title="$config['title']">
    <x-admin.page-header :title="'Quản lý '.$config['title']" description="Danh mục chuẩn được dùng trong hồ sơ và autocomplete của học viên." />

    @include('admin::learner-data._tabs')
    <x-admin.flash />
    <x-auth.errors />

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
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

        @if ($canManage)
            @php
                $formRoute = $editing
                    ? route($config['route'].'.update', ['item' => $editing->id])
                    : route($config['route'].'.store');
            @endphp
            <aside class="rounded-xl border border-outline-variant bg-surface p-5 xl:sticky xl:top-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-title-md font-semibold text-on-surface">{{ $editing ? 'Chỉnh sửa' : 'Thêm mới' }}</h2>
                    @if ($editing)<a href="{{ route($config['route'].'.index') }}" class="text-label-sm text-primary hover:underline">Hủy sửa</a>@endif
                </div>
                <form method="post" action="{{ $formRoute }}" class="space-y-4">
                    @csrf
                    @if ($editing) @method('PUT') @endif

                    @if ($catalog === 'administrative-units')
                        <div>
                            <label for="country_id" class="mb-1 block text-label-sm text-on-surface-variant">Quốc gia</label>
                            <select id="country_id" name="country_id" required class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                                <option value="">Chọn quốc gia</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected((string) old('country_id', $editing?->country_id) === (string) $country->id)>{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label for="name" class="mb-1 block text-label-sm text-on-surface-variant">Tên hiển thị</label>
                        <input id="name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="120"
                            class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                    </div>
                    <div>
                        <label for="code" class="mb-1 block text-label-sm text-on-surface-variant">Mã</label>
                        <input id="code" name="code" value="{{ old('code', $editing?->code) }}" required maxlength="50"
                            @readonly($catalog === 'countries' && $editing?->code === 'VN')
                            class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 font-mono read-only:opacity-60">
                    </div>

                    @if ($catalog === 'administrative-units')
                        <div>
                            <label for="type" class="mb-1 block text-label-sm text-on-surface-variant">Loại địa phương</label>
                            <select id="type" name="type" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                                <option value="province" @selected(old('type', $editing?->type) === 'province')>Tỉnh</option>
                                <option value="city" @selected(old('type', $editing?->type) === 'city')>Thành phố trực thuộc trung ương</option>
                            </select>
                        </div>
                    @elseif ($catalog === 'professions')
                        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="requires_education_stage" value="1" @checked(old('requires_education_stage', $editing?->requires_education_stage)) class="mt-0.5 size-4 rounded text-primary"><span>Yêu cầu học viên chọn năm học</span></label>
                        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="defaults_to_graduated" value="1" @checked(old('defaults_to_graduated', $editing?->defaults_to_graduated)) class="mt-0.5 size-4 rounded text-primary"><span>Mặc định là đã tốt nghiệp</span></label>
                    @elseif ($catalog === 'education-stages')
                        <label class="flex items-start gap-2 text-body-sm"><input type="checkbox" name="is_graduated" value="1" @checked(old('is_graduated', $editing?->is_graduated)) class="mt-0.5 size-4 rounded text-primary"><span>Đây là trạng thái đã tốt nghiệp</span></label>
                    @endif

                    <div>
                        <label for="sort_order" class="mb-1 block text-label-sm text-on-surface-variant">Thứ tự hiển thị</label>
                        <input id="sort_order" type="number" name="sort_order" min="0" max="65535" value="{{ old('sort_order', $editing?->sort_order ?? 0) }}"
                            class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                    </div>
                    <label class="flex items-center gap-2 text-body-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $editing->is_active : true)) class="size-4 rounded text-primary">Hiển thị cho học viên</label>
                    <button class="w-full rounded-lg bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary">{{ $editing ? 'Lưu thay đổi' : 'Thêm '.$config['singular'] }}</button>
                </form>

                @if ($editing)
                    <form method="post" action="{{ route($config['route'].'.toggle', ['item' => $editing->id]) }}" class="mt-3 text-center">
                        @csrf @method('PATCH')
                        <button class="text-label-sm font-semibold text-on-surface-variant hover:text-primary hover:underline">{{ $editing->is_active ? 'Ngừng hiển thị' : 'Kích hoạt lại' }}</button>
                    </form>
                @endif
            </aside>
        @endif
    </div>
</x-layouts.admin>
