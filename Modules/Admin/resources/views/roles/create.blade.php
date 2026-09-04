<x-layouts.admin title="Tạo vai trò mới">
    @php
        $portalsList = collect($portals)->map(fn ($p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'description' => $p->description(),
        ])->values()->all();

        $permissionsByPortal = collect($permissionGroups)->mapWithKeys(fn ($g) => [
            $g['portal']->value => $g['permissions']->map(fn ($perm) => [
                'id' => (int) $perm->id,
                'name' => $perm->name,
            ])->values()->all(),
        ])->all();
    @endphp

    <div x-data="{
        portal: @js(old('portal', '')),
        name: @js(old('name', '')),
        selectedPermissions: @js(array_map('intval', (array) old('permissions', []))),
        permissionsByPortal: @js($permissionsByPortal),
        
        get currentPermissions() {
            return this.portal ? (this.permissionsByPortal[this.portal] || []) : [];
        },
        onPortalChange() {
            this.selectedPermissions = [];
        },
        toggleAll() {
            const currentIds = this.currentPermissions.map(p => p.id);
            if (this.isAllSelected()) {
                this.selectedPermissions = this.selectedPermissions.filter(id => !currentIds.includes(id));
            } else {
                const newSet = new Set([...this.selectedPermissions, ...currentIds]);
                this.selectedPermissions = Array.from(newSet);
            }
        },
        isAllSelected() {
            if (this.currentPermissions.length === 0) return false;
            return this.currentPermissions.every(p => this.selectedPermissions.includes(p.id));
        }
    }" class="max-w-4xl">
        <x-admin.page-header title="Tạo vai trò mới"
            description="Chọn cổng truy cập, chọn các quyền tương ứng và đặt tên vai trò.">
            <x-slot:actions>
                <a href="{{ route('admin.roles.index') }}" class="rounded-lg px-3 py-2 text-on-surface-variant hover:bg-surface-container-low">← Danh sách vai trò</a>
            </x-slot:actions>
        </x-admin.page-header>

        @if ($errors->any())
            <section role="alert" class="mb-6 rounded-xl border border-error/30 bg-error-container px-5 py-4 text-on-error-container">
                <h2 class="font-semibold">Chưa thể tạo vai trò</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form method="post" action="{{ route('admin.roles.store') }}" class="space-y-6">
            @csrf

            <div class="rounded-2xl border border-outline-variant bg-surface p-6 space-y-6">
                {{-- 1. Chọn Portal --}}
                <div>
                    <label for="portal_select" class="mb-2 block font-label-md font-semibold text-on-surface">
                        Cổng truy cập (Portal) <span class="text-error">*</span>
                    </label>
                    <select id="portal_select" name="portal" x-model="portal" @change="onPortalChange()" required
                        class="h-11 w-full max-w-md rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm font-semibold text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="" disabled>-- Chọn 1 trong 4 cổng truy cập --</option>
                        @foreach ($portals as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }} ({{ $p->loginPath() }})</option>
                        @endforeach
                    </select>
                    @error('portal')<p class="mt-1.5 text-xs text-error">{{ $message }}</p>@enderror
                </div>

                {{-- 2. Đặt tên Role --}}
                <div>
                    <label for="name" class="mb-2 block font-label-md font-semibold text-on-surface">
                        Tên vai trò <span class="text-error">*</span>
                    </label>
                    <input id="name" name="name" x-model="name" required maxlength="80"
                        placeholder="Ví dụ: medical_reviewer hoặc btv_chuyen_mon"
                        class="h-11 w-full max-w-md rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    <p class="mt-1.5 text-xs text-on-surface-variant">Tên định danh chữ thường, số, dấu gạch dưới hoặc gạch ngang.</p>
                    @error('name')<p class="mt-1.5 text-xs text-error">{{ $message }}</p>@enderror
                </div>

                {{-- 3. Danh sách quyền (Load theo portal) --}}
                <div x-show="portal" x-cloak class="border-t border-outline-variant/60 pt-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-headline-sm text-on-surface font-semibold">Danh sách quyền (Permissions)</h3>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Chọn các quyền áp dụng cho vai trò này thuộc portal đã chọn.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary"
                                  x-text="selectedPermissions.filter(id => currentPermissions.some(p => p.id === id)).length + '/' + currentPermissions.length + ' đã chọn'"></span>
                            <button type="button" @click="toggleAll()"
                                class="rounded-lg border border-outline-variant px-3 py-1.5 text-xs font-semibold text-on-surface hover:bg-surface-container-low">
                                <span x-text="isAllSelected() ? 'Bỏ chọn tất cả' : 'Chọn tất cả'"></span>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="perm in currentPermissions" :key="perm.id">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-outline-variant/70 p-3 hover:bg-surface-container-low has-[:checked]:border-primary has-[:checked]:bg-primary/5 transition-colors">
                                <input type="checkbox" name="permissions[]" :value="perm.id" x-model.number="selectedPermissions"
                                    class="mt-0.5 size-4 rounded border-outline text-primary focus:ring-primary">
                                <span class="text-sm font-medium text-on-surface" x-text="perm.name"></span>
                            </label>
                        </template>
                        <template x-if="currentPermissions.length === 0">
                            <p class="col-span-full py-4 text-center text-sm text-on-surface-variant">Cổng truy cập này chưa có quyền nào trong hệ thống.</p>
                        </template>
                    </div>
                    @error('permissions')<p class="mt-2 text-xs text-error">{{ $message }}</p>@enderror
                </div>

                {{-- Nút lưu --}}
                <div class="flex items-center justify-end gap-3 border-t border-outline-variant/60 pt-6">
                    <a href="{{ route('admin.roles.index') }}"
                        class="rounded-xl border border-outline-variant px-5 py-2.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors">
                        Hủy
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-on-primary hover:bg-primary/90 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        <span>Lưu vai trò</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.admin>
