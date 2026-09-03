<x-layouts.admin title="Tạo role">
    <x-admin.page-header title="Tạo role mới"
        description="Tạo role tùy chỉnh và gán các permission hiện có.">
        <x-slot:actions>
            <a href="{{ route('admin.roles.index') }}" class="rounded-lg px-3 py-2 text-on-surface-variant hover:bg-surface-container-low">← Vai trò</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ route('admin.roles.store') }}" class="space-y-6">
        @csrf
        <section class="rounded-xl border border-outline-variant bg-surface p-5">
            <label for="name" class="mb-2 block font-label-md font-semibold text-on-surface">Tên role</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="80"
                placeholder="Ví dụ: medical_reviewer"
                class="w-full max-w-lg rounded-lg border border-outline-variant bg-surface px-3 py-2.5 text-on-surface focus:border-primary focus:ring-primary">
            <p class="mt-2 text-xs text-on-surface-variant">Dùng chữ thường, số, dấu chấm, gạch ngang hoặc gạch dưới.</p>
            @error('name')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
        </section>

        @foreach ($permissionGroups as $group)
            <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="border-b border-outline-variant bg-surface-container-low px-4 py-3">
                    <h2 class="font-headline-sm text-on-surface">{{ $group['portal']->label() }}</h2>
                </div>
                <div class="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($group['permissions'] as $permission)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-outline-variant/60 p-3 hover:bg-surface-container-low">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                @checked(in_array($permission->id, array_map('intval', (array) old('permissions', [])), true))
                                class="mt-0.5 size-4 rounded border-outline text-primary focus:ring-primary">
                            <span class="text-sm text-on-surface">{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex justify-end">
            <button class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-on-primary hover:opacity-90">Tạo role</button>
        </div>
    </form>
</x-layouts.admin>
