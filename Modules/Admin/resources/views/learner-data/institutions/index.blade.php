<x-layouts.admin title="Trường và cơ sở đào tạo">
    <x-admin.page-header title="Trường và cơ sở đào tạo" description="Danh mục chuẩn dùng khi học viên hoàn thiện hồ sơ.">
        <x-slot:actions>
            <div class="flex gap-2">
                <a href="{{ route('admin.learner-data.demographics') }}" class="rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">Xem tổng hợp</a>
                @if ($canManage)
                    <a href="{{ route('admin.institutions.create') }}" class="rounded-lg bg-primary px-3 py-2 font-label-md text-on-primary hover:opacity-90">Thêm trường</a>
                @endif
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin::learner-data._tabs')

    <x-admin.flash />

    <form method="get" class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-2 xl:grid-cols-5">
        <div>
            <label for="q" class="mb-1.5 block font-label-sm text-on-surface-variant">Tên hoặc tên viết tắt</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3" placeholder="VD: Đại học Y Hà Nội">
        </div>
        <div>
            <label for="country_id" class="mb-1.5 block font-label-sm text-on-surface-variant">Quốc gia</label>
            <select id="country_id" name="country_id" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected((string)($filters['country_id'] ?? '') === (string)$country->id)>{{ $country->name }}</option>@endforeach</select>
        </div>
        <div>
            <label for="administrative_unit_id" class="mb-1.5 block font-label-sm text-on-surface-variant">Tỉnh/Thành phố</label>
            <select id="administrative_unit_id" name="administrative_unit_id" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)($filters['administrative_unit_id'] ?? '') === (string)$unit->id)>{{ $unit->name }}</option>@endforeach</select>
        </div>
        <div>
            <label for="status" class="mb-1.5 block font-label-sm text-on-surface-variant">Trạng thái</label>
            <select id="status" name="status" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                <option value="">Tất cả</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Đang hiển thị</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Đã ẩn</option>
            </select>
        </div>
        <button class="h-11 rounded-lg bg-primary px-4 font-label-md font-semibold text-on-primary">Lọc</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low text-label-md text-on-surface-variant">
                <tr><th class="px-4 py-3">Trường</th><th class="px-4 py-3">Địa phương</th><th class="px-4 py-3">Học viên</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody>
                @forelse ($institutions as $institution)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3"><p class="font-medium text-on-surface">{{ $institution->name }}</p><p class="text-label-sm text-on-surface-variant">{{ $institution->short_name ?: '—' }}</p></td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $institution->administrativeUnit?->name ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ number_format($institution->learner_profiles_count) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full bg-surface-container px-2.5 py-1 text-label-sm">{{ $institution->is_active ? 'Đang hiển thị' : 'Đã ẩn' }}</span></td>
                        <td class="px-4 py-3 text-right">
                            @if ($canManage)<a href="{{ route('admin.institutions.edit', $institution) }}" class="font-label-sm font-semibold text-primary hover:underline">Chỉnh sửa</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">Chưa có trường phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $institutions->links() }}</div>

</x-layouts.admin>
