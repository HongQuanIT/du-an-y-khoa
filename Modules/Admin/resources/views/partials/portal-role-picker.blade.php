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
        <p class="mb-2 font-label-sm text-label-sm text-on-surface-variant">1. Chọn portal</p>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($portalsWithRoles as $item)
                @php $portal = $item['portal']; @endphp
                <button type="button"
                    @click="selectPortal('{{ $portal->value }}')"
                    :class="portal === '{{ $portal->value }}'
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : 'border-outline-variant hover:bg-surface-container-low'"
                    class="rounded-xl border px-3 py-3 text-start transition">
                    <div class="font-label-md text-label-md text-on-surface">{{ $portal->label() }}</div>
                    <div class="mt-0.5 font-label-sm text-label-sm text-on-surface-variant">{{ $portal->loginPath() }}</div>
                </button>
            @endforeach
        </div>
        @error('portal')
            <p class="mt-1 font-label-sm text-label-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="portal" x-cloak>
        <p class="mb-2 font-label-sm text-label-sm text-on-surface-variant">2. Chọn vai trò</p>
        <template x-if="rolesForPortal().length === 1">
            <p class="font-body-sm text-body-sm text-on-surface">
                <span x-text="rolesForPortal()[0]?.label"></span>
                <span class="text-on-surface-variant"> — tự chọn vì portal chỉ có một role.</span>
            </p>
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
