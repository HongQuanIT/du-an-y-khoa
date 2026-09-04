{{--
  Portal → role picker (Alpine).
  Expects:
    - $assignableRoles: list<Role>
    - $selectedRole: ?string (optional current role value)
    - $inputName: string (default "role")
--}}
@php
    use App\Support\Enums\PortalGroup;
    use App\Support\Enums\Role;

    $assignableRoles = $assignableRoles ?? [];
    $selectedRole = $selectedRole ?? old('role');
    $inputName = $inputName ?? 'role';

    $portalsWithRoles = [];
    foreach (PortalGroup::cases() as $portal) {
        $roles = array_values(array_filter(
            $assignableRoles,
            static fn (Role $role): bool => $role->portal() === $portal,
        ));
        if ($roles !== []) {
            $portalsWithRoles[] = [
                'portal' => $portal,
                'roles' => $roles,
            ];
        }
    }

    $initialPortal = null;
    $initialRole = is_string($selectedRole) ? $selectedRole : '';
    if ($initialRole !== '') {
        $initialPortal = Role::tryFrom($initialRole)?->portal()->value;
    }
    if ($initialPortal === null && count($portalsWithRoles) === 1) {
        $initialPortal = $portalsWithRoles[0]['portal']->value;
        if (count($portalsWithRoles[0]['roles']) === 1) {
            $initialRole = $portalsWithRoles[0]['roles'][0]->value;
        }
    }

    $rolesByPortalJson = collect($portalsWithRoles)->mapWithKeys(function (array $item) {
        /** @var PortalGroup $portal */
        $portal = $item['portal'];

        return [
            $portal->value => collect($item['roles'])->map(fn (Role $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values()->all(),
        ];
    })->all();
@endphp

<div
    class="space-y-4"
    x-data="{
        portal: @js($initialPortal),
        role: @js($initialRole),
        rolesByPortal: @js($rolesByPortalJson),
        selectPortal(value) {
            this.portal = value;
            const roles = this.rolesByPortal[value] || [];
            if (roles.length === 1) {
                this.role = roles[0].value;
            } else if (!roles.some(r => r.value === this.role)) {
                this.role = '';
            }
        },
        rolesForPortal() {
            return this.portal ? (this.rolesByPortal[this.portal] || []) : [];
        }
    }"
>
    <div>
        <p class="mb-2 font-label-sm font-medium text-on-surface-variant">Cổng truy cập</p>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($portalsWithRoles as $item)
                @php $portal = $item['portal']; @endphp
                <button type="button"
                    @click="selectPortal('{{ $portal->value }}')"
                    :class="portal === '{{ $portal->value }}'
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : 'border-outline-variant hover:bg-surface-container-low'"
                    class="relative min-h-14 rounded-lg border px-3 py-2.5 text-start transition focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-label-md font-medium text-on-surface">{{ $portal->label() }}</span>
                        <span x-show="portal === '{{ $portal->value }}'" class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">check_circle</span>
                    </div>
                </button>
            @endforeach
        </div>
        @error('portal')
            <p class="mt-1 font-label-sm text-label-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="portal" x-cloak>
        <p class="mb-2 font-label-sm font-medium text-on-surface-variant">Vai trò</p>
        <template x-if="rolesForPortal().length === 1">
            <div class="flex items-center gap-2 rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2.5">
                <span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">badge</span>
                <span class="font-label-md font-medium text-on-surface" x-text="rolesForPortal()[0]?.label"></span>
            </div>
        </template>
        <div class="space-y-2" x-show="rolesForPortal().length > 1">
            <template x-for="item in rolesForPortal()" :key="item.value">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-outline-variant px-3 py-2 hover:bg-surface-container-low"
                    :class="role === item.value ? 'border-primary bg-primary/5' : ''">
                    <input type="radio" :value="item.value" x-model="role" class="text-primary focus:ring-primary">
                    <span class="font-label-md text-label-md text-on-surface" x-text="item.label"></span>
                </label>
            </template>
        </div>
        @error($inputName)
            <p class="mt-1 font-label-sm text-label-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <input type="hidden" name="{{ $inputName }}" :value="role">
    <input type="hidden" name="portal" :value="portal ?? ''">
</div>
