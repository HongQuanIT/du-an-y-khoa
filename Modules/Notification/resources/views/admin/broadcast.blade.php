<x-layouts.admin :title="$title">
    <div class="mx-auto max-w-xl space-y-6">
        <div>
            <h2 class="font-headline-sm text-headline-sm text-on-surface">Gửi thông báo hệ thống</h2>
            <p class="mt-1 font-body-md text-body-md text-on-surface-variant">
                Phát tới toàn bộ hoặc theo nhóm người dùng. Tin sẽ hiện realtime trên chuông thông báo.
            </p>
        </div>

        <form method="post" action="{{ route('admin.notifications.broadcast.store') }}"
            class="space-y-5 rounded-xl border border-outline-variant bg-surface p-5">
            @csrf

            <div>
                <label for="title" class="font-label-md text-label-md text-on-surface">Tiêu đề</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="160"
                    class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                @error('title')
                    <p class="mt-1 font-body-sm text-body-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="body" class="font-label-md text-label-md text-on-surface">Nội dung</label>
                <textarea id="body" name="body" rows="5" required maxlength="2000"
                    class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">{{ old('body') }}</textarea>
                @error('body')
                    <p class="mt-1 font-body-sm text-body-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="audience" class="font-label-md text-label-md text-on-surface">Đối tượng</label>
                    <select id="audience" name="audience"
                        class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        @foreach ([
                            'all' => 'Tất cả người dùng',
                            'learners' => 'Học viên',
                            'instructors' => 'Giảng viên',
                            'staff' => 'Nhân sự / Admin',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience', 'learners') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="font-label-md text-label-md text-on-surface">Loại</label>
                    <select id="type" name="type"
                        class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="system.broadcast" @selected(old('type', 'system.broadcast') === 'system.broadcast')>Thông báo chung</option>
                        <option value="system.maintenance" @selected(old('type') === 'system.maintenance')>Bảo trì</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="action_url" class="font-label-md text-label-md text-on-surface">Deep link (tuỳ chọn)</label>
                <input id="action_url" name="action_url" type="text" value="{{ old('action_url') }}" maxlength="500"
                    placeholder="/dashboard hoặc URL nội bộ"
                    class="mt-1.5 w-full rounded-lg border border-outline-variant bg-surface px-3 py-2.5 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
            </div>

            <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                <a href="{{ route('admin.notifications.index') }}"
                    class="rounded-lg px-4 py-2.5 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">Huỷ</a>
                <button type="submit"
                    class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                    Gửi thông báo
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
