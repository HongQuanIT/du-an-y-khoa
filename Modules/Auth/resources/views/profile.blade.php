@php
    use App\Support\TargetExams;

    $roleLabel = $user->career_role ?: 'Sinh viên Y khoa';
    $year = $user->graduation_year;
    $country = $user->country ?: 'Việt Nam';
    $university = $user->institution;
    $objectiveKey = $user->study_objective;
    $objectiveTitle = $objectiveKey ? TargetExams::title($objectiveKey) : null;
    $careerRoles = [
        'Sinh viên Y khoa',
        'Bác sĩ nội trú',
        'Bác sĩ',
        'Giảng viên',
        'Khác',
    ];
    $years = range((int) now()->year + 8, (int) now()->year - 5);
    $countries = ['Việt Nam', 'United States', 'Germany', 'Italy', 'Australia', 'Canada', 'United Kingdom', 'Khác'];
@endphp

<x-layouts.app title="Hồ sơ nghề nghiệp & học tập">
    <div class="mx-auto w-full max-w-[920px] space-y-8 px-margin-mobile py-8 md:px-margin-desktop md:py-10"
        x-data="{ editingCareer: {{ $errors->any() && old('_form') === 'career' ? 'true' : 'false' }}, editingObjective: {{ $errors->any() && old('_form') === 'objective' ? 'true' : 'false' }} }">

        @include('auth::partials.account-nav', ['active' => 'career'])

        <h1 class="font-headline-lg text-headline-lg-mobile text-on-surface md:text-headline-lg">
            Hồ sơ nghề nghiệp & học tập
        </h1>

        @if (session('status'))
            <div class="rounded-lg border border-primary/25 bg-primary-fixed/25 px-4 py-3 font-body-md text-body-md text-primary">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-error/30 bg-error-container px-4 py-3 font-body-md text-body-md text-on-error-container">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Career summary card --}}
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-5 md:px-6 md:py-6">
            <div x-show="!editingCareer" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-5">
                @include('auth::partials.avatar', ['user' => $user, 'size' => 'xs'])
                <div class="min-w-0 flex-1 font-body-lg text-body-lg leading-relaxed text-on-surface">
                    Tôi là <strong>{{ $roleLabel }}</strong>
                    @if ($year)
                        tốt nghiệp <strong>{{ $year }}</strong>
                    @endif
                    tại <strong>{{ $country }}</strong>@if ($university),
                        Trường: <strong>{{ $university }}</strong>
                    @endif.
                </div>
                <button type="button" @click="editingCareer = true; editingObjective = false"
                    class="shrink-0 self-start rounded-md border border-outline px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low sm:self-center">
                    Chỉnh sửa hồ sơ
                </button>
            </div>

            <form x-show="editingCareer" x-cloak method="post" action="{{ route('settings.profile') }}" class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="career">
                <input type="hidden" name="name" value="{{ $user->name }}">
                <div class="mb-2 flex items-center gap-2 font-title-md text-title-md text-on-surface">
                    <span class="material-symbols-outlined text-primary">person</span>
                    Chỉnh sửa hồ sơ nghề nghiệp
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="career_role" class="font-label-sm text-label-sm text-on-surface-variant">Vai trò</label>
                        <select id="career_role" name="career_role"
                            class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            @foreach ($careerRoles as $role)
                                <option value="{{ $role }}" @selected(old('career_role', $user->career_role ?: 'Sinh viên Y khoa') === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="graduation_year" class="font-label-sm text-label-sm text-on-surface-variant">Năm tốt nghiệp</label>
                        <select id="graduation_year" name="graduation_year"
                            class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">— Chọn —</option>
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected((string) old('graduation_year', $user->graduation_year) === (string) $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="country" class="font-label-sm text-label-sm text-on-surface-variant">Quốc gia</label>
                        <select id="country" name="country"
                            class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            @foreach ($countries as $c)
                                <option value="{{ $c }}" @selected(old('country', $user->country ?: 'Việt Nam') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="institution" class="font-label-sm text-label-sm text-on-surface-variant">Trường / Cơ sở</label>
                        <input id="institution" name="institution" type="text"
                            value="{{ old('institution', $user->institution) }}"
                            placeholder="Ví dụ: ĐH Y Dược TP.HCM"
                            class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div class="flex flex-col gap-1.5 md:col-span-2">
                        <label for="specialty" class="font-label-sm text-label-sm text-on-surface-variant">Chuyên ngành (tuỳ chọn)</label>
                        <input id="specialty" name="specialty" type="text"
                            value="{{ old('specialty', $user->specialty) }}"
                            class="h-10 rounded-md border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                    <button type="button" @click="editingCareer = false"
                        class="rounded-md border border-outline px-4 py-2 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                        Hủy
                    </button>
                    <button type="submit"
                        class="rounded-md bg-primary px-4 py-2 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        Lưu
                    </button>
                </div>
            </form>
        </section>

        {{-- Study objective card --}}
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-5 md:px-6 md:py-6">
            <div x-show="!editingObjective" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-5">
                <div class="flex size-11 shrink-0 items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[32px]">menu_book</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-body-lg text-body-lg text-on-surface">
                        Mục tiêu học —
                        <strong>{{ $objectiveTitle ?: 'Chưa chọn' }}</strong>
                    </p>
                    <p class="mt-1 font-body-md text-body-md text-on-surface-variant">
                        Chọn mục tiêu phù hợp nhất lúc này để nội dung được tối ưu theo hướng ôn tập của bạn.
                    </p>
                </div>
                <button type="button" @click="editingObjective = true; editingCareer = false"
                    class="shrink-0 self-start rounded-md border border-outline px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low sm:self-center">
                    Chỉnh sửa mục tiêu
                </button>
            </div>

            <form x-show="editingObjective" x-cloak method="post" action="{{ route('settings.objective') }}" class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="objective">
                <div class="mb-2 flex items-center gap-2 font-title-md text-title-md text-on-surface">
                    <span class="material-symbols-outlined text-primary">menu_book</span>
                    Chọn mục tiêu học
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach (TargetExams::selectable() as $key => $exam)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-outline-variant p-4 transition-colors hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="study_objective" value="{{ $key }}" class="mt-1 text-primary focus:ring-primary"
                                @checked(old('study_objective', $user->study_objective) === $key)>
                            <span>
                                <span class="block font-label-md text-label-md font-semibold text-on-surface">{{ $exam['title'] }}</span>
                                <span class="mt-0.5 block font-body-sm text-body-sm text-on-surface-variant">{{ $exam['hint'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                    <button type="button" @click="editingObjective = false"
                        class="rounded-md border border-outline px-4 py-2 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                        Hủy
                    </button>
                    <button type="submit"
                        class="rounded-md bg-primary px-4 py-2 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        Lưu mục tiêu
                    </button>
                </div>
            </form>
        </section>

        {{-- Also in your account --}}
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-5 md:px-6 md:py-6">
            <h2 class="mb-4 font-title-md text-title-md text-on-surface">Cũng trong tài khoản của bạn</h2>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-2 font-body-md text-body-md">
                <a href="{{ route('settings.edit', ['tab' => 'contact']) }}" class="text-primary hover:underline">Liên hệ & cài đặt</a>
                <span class="text-outline-variant">|</span>
                <a href="{{ route('settings.edit', ['tab' => 'membership']) }}" class="text-primary hover:underline">Gói & giấy phép</a>
                <span class="text-outline-variant">|</span>
                <a href="{{ route('settings.edit', ['tab' => 'redeem']) }}" class="text-primary hover:underline">Đổi mã</a>
                <span class="text-outline-variant">|</span>
                <a href="{{ route('settings.edit', ['tab' => 'notes']) }}" class="text-primary hover:underline">Ghi chú & cài đặt khác</a>
            </div>
        </section>
    </div>
</x-layouts.app>
