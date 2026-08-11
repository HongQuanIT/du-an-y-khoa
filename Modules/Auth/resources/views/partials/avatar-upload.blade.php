<div class="space-y-4 border-b border-outline-variant pb-6">
    <h3 class="font-label-md text-label-md font-semibold text-on-surface">Ảnh đại diện</h3>

    <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
        @include('auth::partials.avatar', ['user' => $user, 'size' => 'lg'])

        <div class="min-w-0 flex-1 space-y-3">
            <form method="post" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                @method('PUT')
                <label for="avatar" class="block font-label-sm text-label-sm text-on-surface-variant">
                    Chọn ảnh JPG, PNG hoặc WebP (tối đa 2 MB)
                </label>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input id="avatar" name="avatar" type="file" required accept="image/jpeg,image/png,image/webp"
                        class="block w-full max-w-sm text-body-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary-container file:px-3 file:py-2 file:font-label-md file:text-label-md file:text-on-primary-container hover:file:opacity-90">
                    <button type="submit"
                        class="shrink-0 rounded-md bg-primary px-4 py-2 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                        Tải lên
                    </button>
                </div>
                @error('avatar')
                    <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                @enderror
            </form>

            @if ($user->avatar_path)
                <form method="post" action="{{ route('settings.avatar.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="rounded-md border border-outline px-4 py-2 font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-low">
                        Xóa ảnh đại diện
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
