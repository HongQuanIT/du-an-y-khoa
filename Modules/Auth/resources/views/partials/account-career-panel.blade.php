@php
    $roleLabel = $user->career_role ?: null;

    $completionFields = [
        filled($user->name),
        filled($user->avatar_path),
    ];
    $profileCompletion = (int) round(collect($completionFields)->filter()->count() / count($completionFields) * 100);

    $inputClass = 'h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $labelClass = 'font-label-sm text-label-sm font-medium text-on-surface-variant';
@endphp

<div x-data="{
    panel: {{ $errors->any() && old('_form') === 'learner' ? "'learner'" : ($errors->any() && old('_form') === 'career' ? "'career'" : ($errors->any() && old('_form') === 'objective' ? "'objective'" : 'null')) }},
    open(next) { this.panel = this.panel === next ? null : next; }
}" class="space-y-6">
    @if ($profileCompletion < 100)
        <div class="rounded-xl border border-outline-variant bg-surface p-4 md:p-5">
            <div class="mb-2 flex items-center justify-between gap-3">
                <p class="font-label-md text-label-md font-semibold text-on-surface">Hoàn thiện hồ sơ</p>
                <span class="font-label-sm text-label-sm font-semibold text-primary">{{ $profileCompletion }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-surface-container-high">
                <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ $profileCompletion }}%"></div>
            </div>
            <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                @if (! $user->study_objective)
                    Chọn mục tiêu học tập để hệ thống gợi ý nội dung phù hợp.
                @elseif (! $user->institution)
                    Thêm trường / cơ sở đào tạo để nhận gợi ý chính xác hơn.
                @elseif (! $user->avatar_path)
                    Tải ảnh đại diện để hoàn thiện hồ sơ.
                @else
                    Bổ sung thông tin còn thiếu để tối ưu trải nghiệm học tập.
                @endif
            </p>
        </div>
    @endif

    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
        <div class="border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6">
            <h2 class="font-title-md text-title-md text-on-surface">Thông tin cơ bản</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Ảnh đại diện và tên hiển thị trên nền tảng.</p>
        </div>
        <div class="space-y-6 p-5 md:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                @include('auth::partials.avatar', ['user' => $user, 'size' => 'lg'])
                <div class="min-w-0 flex-1 space-y-3">
                    <div>
                        <p class="font-headline-sm text-headline-sm text-on-surface">{{ $user->name }}</p>
                        <p class="mt-0.5 font-body-md text-body-md text-on-surface-variant">{{ $user->email }}</p>
                    </div>
                    @if ($roleLabel)
                        <span class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm font-medium text-primary">{{ $roleLabel }}</span>
                    @endif
                    <a href="{{ route('profile.show', ['tab' => 'contact']) }}"
                        class="inline-flex items-center gap-1.5 font-label-md text-label-md text-primary hover:underline">
                        Chỉnh sửa tên &amp; liên hệ
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>
            </div>
            @if ($user->hasRole(\App\Support\Enums\Role::Student->value))
                @php $learnerProfile = $user->learnerProfile; @endphp
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest/50 p-4 md:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-label-lg text-label-lg font-semibold text-on-surface">Thông tin học viên</h3>
                        <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Thông tin học tập đã cung cấp khi hoàn thiện hồ sơ.</p>
                    </div>
                    <button type="button" @click="open('learner')"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 font-label-sm text-label-sm font-medium text-primary transition hover:border-primary hover:bg-primary/5">
                        <span class="material-symbols-outlined text-[17px]">edit</span>
                        Chỉnh sửa
                    </button>
                </div>

                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'Trường' => $learnerProfile?->institution?->name,
                        'Tỉnh/Thành phố' => $learnerProfile?->administrativeUnit?->name,
                        'Quốc gia' => $learnerProfile?->country?->name,
                        'Chức danh' => $learnerProfile?->profession?->name,
                        'Năm học' => $learnerProfile?->educationStage?->name,
                    ] as $label => $value)
                        <div>
                            <dt class="font-label-sm text-label-sm text-on-surface-variant">{{ $label }}</dt>
                            <dd class="mt-1 font-body-md text-body-md font-medium text-on-surface">
                                {{ filled($value) ? $value : 'Chưa cập nhật' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <form x-show="panel === 'learner'" x-cloak method="post" action="{{ route('settings.learner-profile') }}"
                    class="mt-5 space-y-4 border-t border-outline-variant pt-5"
                    x-data="learnerProfileEditor({
                        countries: @js($countries->map(fn ($country) => ['id' => (string) $country->id, 'name' => $country->name])),
                        units: @js($administrativeUnits->map(fn ($unit) => ['id' => (string) $unit->id, 'country_id' => (string) $unit->country_id, 'name' => $unit->name])),
                        institutions: @js($institutions->map(fn ($institution) => ['id' => (string) $institution->id, 'country_id' => (string) $institution->country_id, 'unit_id' => (string) $institution->administrative_unit_id, 'name' => $institution->name])),
                        professions: @js($professions->map(fn ($profession) => ['id' => (string) $profession->id, 'name' => $profession->name, 'requires_stage' => $profession->requires_education_stage, 'graduated' => $profession->defaults_to_graduated])),
                        stages: @js($educationStages->map(fn ($stage) => ['id' => (string) $stage->id, 'name' => $stage->name])),
                        countryId: @js((string) old('country_id', $learnerProfile?->country_id)),
                        unitId: @js((string) old('administrative_unit_id', $learnerProfile?->administrative_unit_id)),
                        institutionId: @js((string) old('institution_id', $learnerProfile?->institution_id)),
                        professionId: @js((string) old('profession_id', $learnerProfile?->profession_id)),
                        stageId: @js((string) old('education_stage_id', $learnerProfile?->education_stage_id)),
                    })" x-init="init()">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="learner">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="relative flex flex-col gap-1.5" @click.outside="countryOpen = false">
                            <label for="learner_country_search" class="{{ $labelClass }}">Quốc gia</label>
                            <input type="hidden" name="country_id" :value="countryId">
                            <input id="learner_country_search" type="search" x-model="countryQuery" @input="countryTyped" @focus="countryOpen = true" @keydown.escape="countryOpen = false"
                                autocomplete="off" placeholder="Chọn hoặc tìm quốc gia" role="combobox" :aria-expanded="countryOpen" class="{{ $inputClass }}">
                            <div x-show="countryOpen" x-cloak class="absolute left-0 right-0 top-full z-40 mt-1 max-h-60 overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                                <template x-for="item in matchingCountries" :key="item.id"><button type="button" @click="chooseCountry(item)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="item.name"></button></template>
                                <p x-show="matchingCountries.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy quốc gia phù hợp.</p>
                            </div>
                            @error('country_id') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="relative flex flex-col gap-1.5" @click.outside="unitOpen = false">
                            <label for="learner_unit_search" class="{{ $labelClass }}">Tỉnh/Thành phố</label>
                            <input type="hidden" name="administrative_unit_id" :value="unitId">
                            <input id="learner_unit_search" type="search" x-model="unitQuery" @input="unitTyped" @focus="unitOpen = true" @keydown.escape="unitOpen = false"
                                :disabled="!countryId" autocomplete="off" placeholder="Chọn hoặc tìm tỉnh/thành phố" role="combobox" :aria-expanded="unitOpen" class="{{ $inputClass }} disabled:cursor-not-allowed disabled:opacity-60">
                            <div x-show="unitOpen && countryId" x-cloak class="absolute left-0 right-0 top-full z-40 mt-1 max-h-60 overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                                <template x-for="item in matchingUnits" :key="item.id"><button type="button" @click="chooseUnit(item)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="item.name"></button></template>
                                <p x-show="matchingUnits.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy tỉnh/thành phố phù hợp.</p>
                            </div>
                            @error('administrative_unit_id') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="relative flex flex-col gap-1.5 sm:col-span-2" @click.outside="institutionOpen = false">
                            <label for="learner_institution_search" class="{{ $labelClass }}">Trường/Cơ sở đào tạo</label>
                            <input type="hidden" name="institution_id" :value="institutionId">
                            <input id="learner_institution_search" type="search" x-model="institutionQuery" @input="institutionTyped" @focus="institutionOpen = true" @keydown.escape="institutionOpen = false"
                                :disabled="!unitId" autocomplete="off" placeholder="Chọn hoặc tìm tên trường" role="combobox" :aria-expanded="institutionOpen" class="{{ $inputClass }} disabled:cursor-not-allowed disabled:opacity-60">
                            <div x-show="institutionOpen && unitId" x-cloak class="absolute left-0 right-0 top-full z-40 mt-1 max-h-60 overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                                <template x-for="item in matchingInstitutions" :key="item.id"><button type="button" @click="chooseInstitution(item)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="item.name"></button></template>
                                <p x-show="matchingInstitutions.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy trường phù hợp.</p>
                            </div>
                            @error('institution_id') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="relative flex flex-col gap-1.5" @click.outside="professionOpen = false">
                            <label for="learner_profession_search" class="{{ $labelClass }}">Chức danh</label>
                            <input type="hidden" name="profession_id" :value="professionId">
                            <input id="learner_profession_search" type="search" x-model="professionQuery" @input="professionTyped" @focus="professionOpen = true" @keydown.escape="professionOpen = false"
                                autocomplete="off" placeholder="Chọn hoặc tìm chức danh" role="combobox" :aria-expanded="professionOpen" class="{{ $inputClass }}">
                            <div x-show="professionOpen" x-cloak class="absolute left-0 right-0 top-full z-40 mt-1 max-h-60 overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                                <template x-for="item in matchingProfessions" :key="item.id"><button type="button" @click="chooseProfession(item)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="item.name"></button></template>
                                <p x-show="matchingProfessions.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy chức danh phù hợp.</p>
                            </div>
                            @error('profession_id') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="relative flex flex-col gap-1.5" x-show="showEducationStage" x-cloak @click.outside="stageOpen = false">
                            <label for="learner_stage_search" class="{{ $labelClass }}">Năm học</label>
                            <input type="hidden" name="education_stage_id" :value="stageId">
                            <input id="learner_stage_search" type="search" x-model="stageQuery" @input="stageTyped" @focus="stageOpen = true" @keydown.escape="stageOpen = false"
                                autocomplete="off" placeholder="Chọn hoặc tìm năm học" role="combobox" :aria-expanded="stageOpen" class="{{ $inputClass }}">
                            <div x-show="stageOpen" x-cloak class="absolute left-0 right-0 top-full z-40 mt-1 max-h-60 overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                                <template x-for="item in matchingStages" :key="item.id"><button type="button" @click="chooseStage(item)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="item.name"></button></template>
                                <p x-show="matchingStages.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy năm học phù hợp.</p>
                            </div>
                            @error('education_stage_id') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="panel = null" class="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface">Hủy</button>
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">Lưu thông tin</button>
                    </div>
                </form>
                </div>
                @once
                    <script>
                        function learnerProfileEditor(config) {
                            return {
                                ...config,
                                countryQuery: '', countryOpen: false,
                                unitQuery: '', unitOpen: false,
                                institutionQuery: '', institutionOpen: false,
                                professionQuery: '', professionOpen: false,
                                stageQuery: '', stageOpen: false,
                                get filteredUnits() { return this.units.filter(item => item.country_id === this.countryId); },
                                get filteredInstitutions() { return this.institutions.filter(item => item.country_id === this.countryId && item.unit_id === this.unitId); },
                                get matchingCountries() { return this.countryId ? this.countries : this.filterOptions(this.countries, this.countryQuery); },
                                get matchingUnits() { return this.unitId ? this.filteredUnits : this.filterOptions(this.filteredUnits, this.unitQuery); },
                                get matchingInstitutions() { return this.institutionId ? this.filteredInstitutions : this.filterOptions(this.filteredInstitutions, this.institutionQuery); },
                                get matchingProfessions() { return this.professionId ? this.professions : this.filterOptions(this.professions, this.professionQuery); },
                                get matchingStages() { return this.stageId ? this.stages : this.filterOptions(this.stages, this.stageQuery); },
                                get selectedProfession() { return this.professions.find(item => item.id === this.professionId); },
                                get showEducationStage() { return Boolean(this.selectedProfession?.requires_stage) && !this.selectedProfession?.graduated; },
                                init() {
                                    this.countryQuery = this.labelFor(this.countries, this.countryId);
                                    this.unitQuery = this.labelFor(this.units, this.unitId);
                                    this.institutionQuery = this.labelFor(this.institutions, this.institutionId);
                                    this.professionQuery = this.labelFor(this.professions, this.professionId);
                                    this.stageQuery = this.labelFor(this.stages, this.stageId);
                                },
                                normalize(value) { return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('vi'); },
                                filterOptions(options, query) {
                                    const needle = this.normalize(query);
                                    return needle ? options.filter(item => this.normalize(item.name).includes(needle)) : options;
                                },
                                labelFor(options, id) { return options.find(item => item.id === id)?.name || ''; },
                                countryTyped() {
                                    this.countryId = ''; this.unitId = ''; this.unitQuery = '';
                                    this.institutionId = ''; this.institutionQuery = ''; this.countryOpen = true;
                                },
                                chooseCountry(item) {
                                    const changed = this.countryId !== item.id;
                                    this.countryId = item.id; this.countryQuery = item.name; this.countryOpen = false;
                                    if (changed) { this.unitId = ''; this.unitQuery = ''; this.institutionId = ''; this.institutionQuery = ''; }
                                },
                                unitTyped() { this.unitId = ''; this.institutionId = ''; this.institutionQuery = ''; this.unitOpen = true; },
                                chooseUnit(item) {
                                    const changed = this.unitId !== item.id;
                                    this.unitId = item.id; this.unitQuery = item.name; this.unitOpen = false;
                                    if (changed) { this.institutionId = ''; this.institutionQuery = ''; }
                                },
                                institutionTyped() { this.institutionId = ''; this.institutionOpen = true; },
                                chooseInstitution(item) { this.institutionId = item.id; this.institutionQuery = item.name; this.institutionOpen = false; },
                                professionTyped() { this.professionId = ''; this.stageId = ''; this.stageQuery = ''; this.professionOpen = true; },
                                chooseProfession(item) {
                                    this.professionId = item.id; this.professionQuery = item.name; this.professionOpen = false;
                                    if (!this.showEducationStage) { this.stageId = ''; this.stageQuery = ''; }
                                },
                                stageTyped() { this.stageId = ''; this.stageOpen = true; },
                                chooseStage(item) { this.stageId = item.id; this.stageQuery = item.name; this.stageOpen = false; },
                            };
                        }
                    </script>
                @endonce
            @endif
            <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest/50 p-4">
                <form method="post" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    @method('PUT')
                    <div class="min-w-0 flex-1">
                        <label for="avatar" class="{{ $labelClass }}">Ảnh đại diện</label>
                        <p class="mb-2 font-body-sm text-body-sm text-on-surface-variant">JPG, PNG hoặc WebP — tối đa 2 MB</p>
                        <input id="avatar" name="avatar" type="file" required accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-body-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:font-label-md file:text-label-md file:text-on-primary hover:file:opacity-90">
                        @error('avatar')
                            <p class="mt-1 font-body-sm text-body-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="shrink-0 rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">Tải lên</button>
                </form>
                @if ($user->avatar_path)
                    <form method="post" action="{{ route('settings.avatar.destroy') }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-label-sm text-label-sm text-on-surface-variant underline-offset-2 hover:text-error hover:underline">Xóa ảnh hiện tại</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
</div>
