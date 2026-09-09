<x-layouts.admin title="Tổng hợp học viên">
    <x-admin.page-header title="Tổng hợp dữ liệu học viên" description="Phân khúc đăng ký phục vụ sản phẩm và marketing.">
        <x-slot:actions><a href="{{ route('admin.institutions.index') }}" class="rounded-lg border border-outline-variant px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">Quản lý trường</a></x-slot:actions>
    </x-admin.page-header>

    @include('admin::learner-data._tabs')

    <form method="get" class="mb-6 grid items-end gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
        <div><label class="mb-1 block text-label-sm text-on-surface-variant">Từ ngày đăng ký</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"></div>
        <div><label class="mb-1 block text-label-sm text-on-surface-variant">Đến ngày đăng ký</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"></div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Quốc gia</label>
            <select name="country_id" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected((string)($filters['country_id'] ?? '') === (string)$country->id)>{{ $country->name }}</option>@endforeach</select>
        </div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Tỉnh/Thành phố</label>
            <select name="administrative_unit_id" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option>@foreach($administrativeUnits as $unit)<option value="{{ $unit->id }}" @selected((string)($filters['administrative_unit_id'] ?? '') === (string)$unit->id)>{{ $unit->name }}</option>@endforeach</select>
        </div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Trường học</label>
            <select name="institution_id" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option>@foreach($institutionOptions as $institution)<option value="{{ $institution->id }}" @selected((string)($filters['institution_id'] ?? '') === (string)$institution->id)>{{ $institution->name }}</option>@endforeach</select>
        </div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Chức danh</label>
            <select name="profession_id" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option>@foreach($professionOptions as $profession)<option value="{{ $profession->id }}" @selected((string)($filters['profession_id'] ?? '') === (string)$profession->id)>{{ $profession->name }}</option>@endforeach</select>
        </div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Năm học</label>
            <select name="education_stage_id" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option>@foreach($stageOptions as $stage)<option value="{{ $stage->id }}" @selected((string)($filters['education_stage_id'] ?? '') === (string)$stage->id)>{{ $stage->name }}</option>@endforeach</select>
        </div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Nhận marketing</label>
            <select name="marketing" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option><option value="yes" @selected(($filters['marketing'] ?? '') === 'yes')>Đồng ý</option><option value="no" @selected(($filters['marketing'] ?? '') === 'no')>Không đồng ý</option></select>
        </div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Nguồn đăng ký</label>
            <select name="utm_source" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3"><option value="">Tất cả</option>@foreach($sourceOptions as $source)<option value="{{ $source }}" @selected(($filters['utm_source'] ?? '') === $source)>{{ $source }}</option>@endforeach</select>
        </div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Phương thức đăng ký</label>
            <select name="registration_method" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                <option value="">Tất cả</option>
                <option value="email" @selected(($filters['registration_method'] ?? '') === 'email')>Email/Mật khẩu</option>
                <option value="google" @selected(($filters['registration_method'] ?? '') === 'google')>Google</option>
                <option value="facebook" @selected(($filters['registration_method'] ?? '') === 'facebook')>Facebook</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-label-sm text-on-surface-variant">Phương thức đăng nhập gần nhất</label>
            <select name="last_login_method" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3">
                <option value="">Tất cả</option>
                <option value="email" @selected(($filters['last_login_method'] ?? '') === 'email')>Email/Mật khẩu</option>
                <option value="google" @selected(($filters['last_login_method'] ?? '') === 'google')>Google</option>
                <option value="facebook" @selected(($filters['last_login_method'] ?? '') === 'facebook')>Facebook</option>
                <option value="unrecorded" @selected(($filters['last_login_method'] ?? '') === 'unrecorded')>Chưa ghi nhận</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button class="h-10 rounded-lg bg-primary px-4 font-label-md font-semibold text-on-primary">Áp dụng</button>
            <a href="{{ route('admin.learner-data.demographics') }}" class="flex h-10 items-center px-2 text-label-sm text-on-surface-variant hover:underline">Xóa lọc</a>
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi-card label="Đã hoàn thiện" :value="number_format($stats['completed'])" hint="Hồ sơ đủ dữ liệu" icon="verified_user" />
        <x-admin.kpi-card label="Chưa hoàn thiện" :value="number_format($stats['incomplete'])" hint="Tài khoản onboarding dở" icon="pending" />
        <x-admin.kpi-card label="Đồng ý nhận tin" :value="number_format($stats['marketing'])" hint="Có thể nhận marketing" icon="campaign" />
        <x-admin.kpi-card label="Số trường" :value="number_format($stats['institutions'])" hint="Có học viên đăng ký" icon="school" />
    </div>

    @php($groups = ['institutions' => 'Theo trường', 'professions' => 'Theo chức danh', 'stages' => 'Theo năm học', 'locations' => 'Theo tỉnh/thành phố', 'registrationMethods' => 'Theo phương thức đăng ký', 'lastLoginMethods' => 'Theo phương thức đăng nhập gần nhất'])
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($groups as $key => $title)
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="font-title-md font-semibold text-on-surface">{{ $title }}</h2>
                <div class="mt-4 divide-y divide-outline-variant/60">
                    @forelse ($$key as $row)
                        <div class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0"><span class="text-body-sm text-on-surface">{{ $row['name'] }}</span><span class="font-label-md font-semibold tabular-nums text-primary">{{ number_format($row['total']) }}</span></div>
                    @empty
                        <p class="py-4 text-body-sm text-on-surface-variant">Chưa có dữ liệu.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
