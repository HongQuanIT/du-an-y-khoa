<x-layouts.auth title="Hoàn thiện hồ sơ">
    <x-auth.shell tagline="Cá nhân hóa hành trình học tập của bạn">
        <div class="mb-7">
            <div class="mb-3 flex items-center justify-between gap-3">
                <span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-primary">Bước 2/2</span>
                <span class="font-label-sm text-label-sm text-on-surface-variant">Hồ sơ nghề nghiệp</span>
            </div>
            <div class="mb-6 h-1.5 overflow-hidden rounded-full bg-surface-container-high" aria-hidden="true">
                <div class="h-full w-full rounded-full bg-primary"></div>
            </div>
            <h2 class="mb-2 font-headline-md text-headline-md text-on-background">Cho chúng tôi biết thêm về bạn</h2>
            <p class="font-body-sm text-body-sm text-text-secondary">
                Thông tin này giúp hệ thống cá nhân hóa nội dung học tập phù hợp hơn.
            </p>
        </div>

        <x-auth.errors />

        <form method="post" action="{{ route('onboarding.profile.store') }}" class="space-y-5"
            x-data="onboardingProfile({
                countries: @js($countries->map(fn ($country) => ['id' => (string) $country->id, 'name' => $country->name])),
                units: @js($administrativeUnits->map(fn ($unit) => ['id' => (string) $unit->id, 'country_id' => (string) $unit->country_id, 'name' => $unit->name])),
                professions: @js($professions->map(fn ($profession) => ['id' => (string) $profession->id, 'name' => $profession->name, 'requires_stage' => $profession->requires_education_stage, 'graduated' => $profession->defaults_to_graduated])),
                stages: @js($educationStages->map(fn ($stage) => ['id' => (string) $stage->id, 'name' => $stage->name])),
                institutionsUrl: @js(route('onboarding.institutions')),
                initialCountry: @js((string) old('country_id', $profile->country_id ?? $defaultCountryId)),
                initialUnit: @js((string) old('administrative_unit_id', $profile->administrative_unit_id)),
                initialProfession: @js((string) old('profession_id', $profile->profession_id)),
                initialStage: @js((string) old('education_stage_id', $profile->education_stage_id)),
                initialInstitutionId: @js((string) ($initialInstitution?->id ?? '')),
                initialInstitutionName: @js((string) ($initialInstitution?->name ?? '')),
            })" x-init="init()">
            @csrf

            <div class="relative" @click.outside="countryOpen = false">
                <label for="country_search" class="mb-1.5 block font-label-sm font-medium text-on-surface-variant">Quốc gia</label>
                <input type="hidden" name="country_id" :value="countryId">
                <input id="country_search" type="search" x-model="countryQuery" @input="countryTyped" @focus="countryOpen = true" @click="countryOpen = true"
                    @keydown.escape="countryOpen = false" autocomplete="off" placeholder="Chọn hoặc tìm quốc gia"
                    role="combobox" :aria-expanded="countryOpen"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-2 focus:ring-primary/20">
                <div x-show="countryOpen" x-cloak
                    class="absolute z-40 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                    <template x-for="country in matchingCountries" :key="country.id">
                        <button type="button" @click="chooseCountry(country)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="country.name"></button>
                    </template>
                    <p x-show="matchingCountries.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy quốc gia phù hợp.</p>
                </div>
                @error('country_id') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="relative" @click.outside="unitOpen = false">
                <label for="unit_search" class="mb-1.5 block font-label-sm font-medium text-on-surface-variant">Tỉnh/Thành phố</label>
                <input type="hidden" name="administrative_unit_id" :value="unitId">
                <input id="unit_search" type="search" x-model="unitQuery" @input="unitTyped" @focus="unitOpen = true" @click="unitOpen = true"
                    @keydown.escape="unitOpen = false" :disabled="!countryId" autocomplete="off" placeholder="Chọn hoặc tìm tỉnh/thành phố"
                    role="combobox" :aria-expanded="unitOpen"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60">
                <div x-show="unitOpen && countryId" x-cloak
                    class="absolute z-40 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                    <template x-for="unit in matchingUnits" :key="unit.id">
                        <button type="button" @click="chooseUnit(unit)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="unit.name"></button>
                    </template>
                    <p x-show="matchingUnits.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy tỉnh/thành phố phù hợp.</p>
                </div>
                @error('administrative_unit_id') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="relative" @click.outside="institutionOpen = false">
                <label for="institution_search" class="mb-1.5 block font-label-sm font-medium text-on-surface-variant">Trường/Cơ sở đào tạo</label>
                <input type="hidden" name="institution_id" :value="institutionId">
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant">school</span>
                    <input id="institution_search" type="search" x-model="institutionQuery" @input="institutionId = ''" @input.debounce.250ms="loadInstitutions" @focus="openInstitutionOptions" @click="openInstitutionOptions"
                        @keydown.escape="institutionOpen = false" :disabled="!unitId" autocomplete="off"
                        placeholder="Chọn hoặc tìm tên trường"
                        class="h-11 w-full rounded-lg border border-outline-variant bg-surface py-2 pl-10 pr-3 font-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60">
                </div>
                <div x-show="institutionOpen && unitId" x-cloak
                    class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                    <template x-if="institutionLoading"><p class="px-3 py-2 text-body-sm text-on-surface-variant">Đang tìm…</p></template>
                    <template x-for="institution in institutions" :key="institution.id">
                        <button type="button" @click="chooseInstitution(institution)"
                            class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-body-sm text-on-surface hover:bg-surface-container-low">
                            <span x-text="institution.name"></span>
                            <span class="shrink-0 text-label-sm text-on-surface-variant" x-text="institution.short_name || ''"></span>
                        </button>
                    </template>
                    <template x-if="!institutionLoading && institutions.length === 0">
                        <p class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy trường phù hợp.</p>
                    </template>
                </div>
                <p class="mt-1 text-label-sm text-on-surface-variant">Bạn phải chọn một kết quả trong danh sách.</p>
                @error('institution_id') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="relative" @click.outside="professionOpen = false">
                <label for="profession_search" class="mb-1.5 block font-label-sm font-medium text-on-surface-variant">Chức danh hiện tại</label>
                <input type="hidden" name="profession_id" :value="professionId">
                <input id="profession_search" type="search" x-model="professionQuery" @input="professionTyped" @focus="professionOpen = true" @click="professionOpen = true"
                    @keydown.escape="professionOpen = false" autocomplete="off" placeholder="Chọn hoặc tìm chức danh"
                    role="combobox" :aria-expanded="professionOpen"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-2 focus:ring-primary/20">
                <div x-show="professionOpen" x-cloak
                    class="absolute z-40 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                    <template x-for="profession in matchingProfessions" :key="profession.id">
                        <button type="button" @click="chooseProfession(profession)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="profession.name"></button>
                    </template>
                    <p x-show="matchingProfessions.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy chức danh phù hợp.</p>
                </div>
                @error('profession_id') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="relative" x-show="showEducationStage" @click.outside="stageOpen = false" x-cloak>
                <label for="stage_search" class="mb-1.5 block font-label-sm font-medium text-on-surface-variant">Năm học</label>
                <input type="hidden" name="education_stage_id" :value="stageId">
                <input id="stage_search" type="search" x-model="stageQuery" @input="stageId = ''" @focus="stageOpen = true" @click="stageOpen = true"
                    @keydown.escape="stageOpen = false" autocomplete="off" placeholder="Chọn hoặc tìm năm học"
                    role="combobox" :aria-expanded="stageOpen"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-sm text-on-surface outline-none transition placeholder:text-on-surface-variant focus:border-primary focus:ring-2 focus:ring-primary/20">
                <div x-show="stageOpen" x-cloak
                    class="absolute z-40 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-outline-variant bg-surface p-1 shadow-xl">
                    <template x-for="stage in matchingStages" :key="stage.id">
                        <button type="button" @click="chooseStage(stage)" class="w-full rounded-md px-3 py-2 text-left text-body-sm hover:bg-surface-container-low" x-text="stage.name"></button>
                    </template>
                    <p x-show="matchingStages.length === 0" class="px-3 py-3 text-body-sm text-on-surface-variant">Không tìm thấy năm học phù hợp.</p>
                </div>
                @error('education_stage_id') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-start gap-3 py-1">
                <input id="marketing_consent" name="marketing_consent" type="checkbox" value="1" @checked(old('marketing_consent'))
                    class="mt-1 size-4 rounded border-border text-primary focus:ring-primary">
                <label for="marketing_consent" class="font-body-sm text-body-sm leading-tight text-on-surface-variant">
                    Tôi muốn nhận thông tin học tập, ưu đãi và cập nhật sản phẩm qua email.
                    <span class="block text-label-sm">Không bắt buộc và có thể thay đổi sau.</span>
                </label>
            </div>

            <x-auth.submit>Hoàn tất</x-auth.submit>
        </form>

        <form method="post" action="{{ route('logout') }}" class="mt-6 text-center">
            @csrf
            <button type="submit" class="font-label-sm text-on-surface-variant hover:text-primary hover:underline">Đăng xuất</button>
        </form>

        <script>
                function onboardingProfile(config) {
                    return {
                        countries: config.countries,
                        units: config.units,
                        professions: config.professions,
                        stages: config.stages,
                        institutionsUrl: config.institutionsUrl,
                        countryId: config.initialCountry,
                        countryQuery: '',
                        countryOpen: false,
                        unitId: config.initialUnit,
                        unitQuery: '',
                        unitOpen: false,
                        professionId: config.initialProfession,
                        professionQuery: '',
                        professionOpen: false,
                        stageId: config.initialStage,
                        stageQuery: '',
                        stageOpen: false,
                        institutionId: config.initialInstitutionId,
                        institutionQuery: config.initialInstitutionName,
                        institutions: [],
                        institutionOpen: false,
                        institutionLoading: false,
                        get filteredUnits() { return this.units.filter(unit => unit.country_id === this.countryId); },
                        get matchingCountries() { return this.countryId ? this.countries : this.filterOptions(this.countries, this.countryQuery); },
                        get matchingUnits() { return this.unitId ? this.filteredUnits : this.filterOptions(this.filteredUnits, this.unitQuery); },
                        get matchingProfessions() { return this.professionId ? this.professions : this.filterOptions(this.professions, this.professionQuery); },
                        get matchingStages() { return this.stageId ? this.stages : this.filterOptions(this.stages, this.stageQuery); },
                        get selectedProfession() { return this.professions.find(item => item.id === this.professionId); },
                        get showEducationStage() { return Boolean(this.selectedProfession?.requires_stage) && !this.selectedProfession?.graduated; },
                        init() {
                            this.countryQuery = this.labelFor(this.countries, this.countryId);
                            this.unitQuery = this.labelFor(this.units, this.unitId);
                            this.professionQuery = this.labelFor(this.professions, this.professionId);
                            this.stageQuery = this.labelFor(this.stages, this.stageId);
                            if (this.unitId) this.loadInstitutions();
                        },
                        normalize(value) { return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('vi'); },
                        filterOptions(options, query) {
                            const needle = this.normalize(query);
                            return needle ? options.filter(item => this.normalize(item.name).includes(needle)) : options;
                        },
                        labelFor(options, id) { return options.find(item => item.id === id)?.name || ''; },
                        countryTyped() {
                            this.countryId = '';
                            this.unitId = '';
                            this.unitQuery = '';
                            this.clearInstitution();
                            this.countryOpen = true;
                        },
                        chooseCountry(item) {
                            const changed = this.countryId !== item.id;
                            this.countryId = item.id;
                            this.countryQuery = item.name;
                            this.countryOpen = false;
                            if (changed) {
                                this.unitId = '';
                                this.unitQuery = '';
                                this.clearInstitution();
                            }
                        },
                        unitTyped() { this.unitId = ''; this.clearInstitution(); this.unitOpen = true; },
                        chooseUnit(item) {
                            const changed = this.unitId !== item.id;
                            this.unitId = item.id;
                            this.unitQuery = item.name;
                            this.unitOpen = false;
                            if (changed) this.clearInstitution();
                            this.loadInstitutions();
                        },
                        professionTyped() {
                            this.professionId = '';
                            this.stageId = '';
                            this.stageQuery = '';
                            this.professionOpen = true;
                        },
                        chooseProfession(item) {
                            this.professionId = item.id;
                            this.professionQuery = item.name;
                            this.professionOpen = false;
                            if (!this.showEducationStage) {
                                this.stageId = '';
                                this.stageQuery = '';
                            }
                        },
                        chooseStage(item) { this.stageId = item.id; this.stageQuery = item.name; this.stageOpen = false; },
                        clearInstitution() { this.institutionId = ''; this.institutionQuery = ''; this.institutions = []; },
                        chooseInstitution(item) { this.institutionId = String(item.id); this.institutionQuery = item.name; this.institutionOpen = false; },
                        openInstitutionOptions() {
                            this.institutionOpen = true;
                            if (!this.institutionLoading) this.loadInstitutions(true);
                        },
                        async loadInstitutions(showAll = false) {
                            if (!this.countryId || !this.unitId) return;
                            this.institutionLoading = true;
                            const params = new URLSearchParams({country_id: this.countryId, administrative_unit_id: this.unitId, q: showAll ? '' : this.institutionQuery});
                            const response = await fetch(`${this.institutionsUrl}?${params}`, {headers: {'Accept': 'application/json'}});
                            const payload = await response.json();
                            this.institutions = payload.data || [];
                            this.institutionLoading = false;
                            this.institutionOpen = true;
                        },
                    };
                }

        </script>
    </x-auth.shell>
</x-layouts.auth>
