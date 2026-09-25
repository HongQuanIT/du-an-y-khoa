@php
    $pages = [
        'profile' => ['Hồ sơ cá nhân', 'Quản lý thông tin tài khoản reviewer.'],
        'security' => ['Bảo mật', 'Quản lý mật khẩu và bảo vệ tài khoản.'],
    ];
    [$title, $description] = $pages[$tab] ?? $pages['profile'];

    $profileRoute = fn (string $page = 'profile'): string => $page === 'profile'
        ? route('reviewer.profile.show')
        : route('reviewer.profile.show', ['tab' => $page]);

    $groups = [
        'Hồ sơ' => [
            'profile' => ['label' => 'Hồ sơ reviewer', 'icon' => 'person', 'href' => $profileRoute()],
        ],
        'Tài khoản' => [
            'security' => ['label' => 'Bảo mật', 'icon' => 'lock', 'href' => $profileRoute('security')],
        ],
    ];
    $allItems = collect($groups)->flatMap(fn (array $items): array => $items);

    $inputClass = 'h-10 w-full rounded-lg border border-outline-variant bg-surface px-3 font-body-md text-body-md text-on-surface transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $labelClass = 'font-label-sm text-label-sm font-medium text-on-surface-variant';
    $cardHeaderClass = 'border-b border-outline-variant bg-surface-container-lowest/60 px-5 py-4 md:px-6';
    $cardBodyClass = 'p-5 md:p-6';
    $cardClass = 'overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm';
@endphp

<x-layouts.reviewer :title="$title" :pending-count="0">
    <x-admin.page-header :title="$title" :description="$description" />

    <div class="space-y-6">
        <x-admin.flash />

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-error/30 bg-error-container px-4 py-3 text-on-error-container">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
            <aside class="lg:w-56 lg:shrink-0">
                <nav class="lg:hidden" aria-label="Tài khoản reviewer">
                    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                        @foreach ($allItems as $key => $item)
                            <a href="{{ $item['href'] }}"
                                @class([
                                    'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 font-label-sm text-label-sm transition-colors',
                                    'border-primary bg-primary/10 font-semibold text-primary' => $tab === $key,
                                    'border-outline-variant bg-surface text-on-surface-variant hover:border-primary/30 hover:text-on-surface' => $tab !== $key,
                                ])>
                                <span class="material-symbols-outlined text-[16px]">{{ $item['icon'] }}</span>
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </nav>

                <nav class="hidden space-y-6 lg:block" aria-label="Tài khoản reviewer">
                    @foreach ($groups as $groupLabel => $items)
                        <div>
                            <p class="mb-2 px-3 font-label-sm text-label-sm font-semibold uppercase tracking-wide text-on-surface-variant">
                                {{ $groupLabel }}
                            </p>
                            <ul class="space-y-0.5">
                                @foreach ($items as $key => $item)
                                    <li>
                                        <a href="{{ $item['href'] }}"
                                            @class([
                                                'flex items-center gap-2.5 rounded-lg px-3 py-2.5 font-label-md text-label-md transition-colors',
                                                'bg-primary/10 font-semibold text-primary' => $tab === $key,
                                                'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface' => $tab !== $key,
                                            ])>
                                            <span @class([
                                                'material-symbols-outlined text-[20px]',
                                                'text-primary' => $tab === $key,
                                            ])>{{ $item['icon'] }}</span>
                                            {{ $item['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>
            </aside>

            <main class="min-w-0 flex-1 space-y-6">
                @if ($tab === 'profile')
                    <section class="{{ $cardClass }}">
                        <div class="{{ $cardHeaderClass }}">
                            <h2 class="font-title-md text-title-md text-on-surface">Thông tin tài khoản</h2>
                            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                                Ảnh đại diện, tên hiển thị và email đăng nhập.
                            </p>
                        </div>

                        <div class="{{ $cardBodyClass }} space-y-6">
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                                @include('auth::partials.avatar', ['user' => $user, 'size' => 'lg'])
                                <div>
                                    <p class="font-headline-sm text-headline-sm text-on-surface">{{ $user->name }}</p>
                                    <p class="font-body-md text-body-md text-on-surface-variant">{{ $user->email }}</p>
                                    <span class="mt-2 inline-flex rounded-full bg-primary/10 px-3 py-1 font-label-sm text-label-sm font-medium text-primary">
                                        Reviewer
                                    </span>
                                </div>
                            </div>

                            @can('profile.avatar_update')
                                <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest/50 p-4">
                                    <form method="post" action="{{ route('reviewer.profile.avatar') }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                        @csrf
                                        @method('PUT')
                                        <div class="min-w-0 flex-1">
                                            <label for="avatar" class="{{ $labelClass }}">Ảnh đại diện</label>
                                            <p class="mb-2 font-body-sm text-body-sm text-on-surface-variant">JPG, PNG hoặc WebP, tối đa 2 MB.</p>
                                            <input id="avatar" name="avatar" type="file" required accept="image/jpeg,image/png,image/webp"
                                                class="block w-full text-body-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-on-primary">
                                        </div>
                                        <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                                            Tải lên
                                        </button>
                                    </form>

                                    @if (filled($user->avatar_path))
                                        <form method="post" action="{{ route('reviewer.profile.avatar.destroy') }}" class="mt-3">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-label-sm text-label-sm text-on-surface-variant hover:text-error">
                                                Xóa ảnh hiện tại
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endcan

                            @can('profile.update')
                                <form method="post" action="{{ route('reviewer.profile.update') }}" class="border-t border-outline-variant pt-5">
                                    @csrf
                                    @method('PUT')
                                    <div class="max-w-md flex flex-col gap-1.5">
                                        <label for="name" class="{{ $labelClass }}">Tên hiển thị</label>
                                        <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}"
                                            class="{{ $inputClass }} @error('name') border-error @enderror">
                                        @error('name')
                                            <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                                            Lưu thay đổi
                                        </button>
                                    </div>
                                </form>
                            @endcan
                        </div>
                    </section>
                @elseif ($tab === 'security')
                    @can('profile.password_update')
                        <section class="{{ $cardClass }}">
                            <div class="{{ $cardHeaderClass }}">
                                <h2 class="font-title-md text-title-md text-on-surface">Đổi mật khẩu</h2>
                                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                                    Sử dụng mật khẩu mạnh, tối thiểu 8 ký tự.
                                </p>
                            </div>

                            <form method="post" action="{{ route('reviewer.profile.password') }}" class="{{ $cardBodyClass }} space-y-5">
                                @csrf
                                @method('PUT')

                                <div class="max-w-md space-y-4">
                                    @foreach ([
                                        'current_password' => ['Mật khẩu hiện tại', 'current-password', '••••••••'],
                                        'password' => ['Mật khẩu mới', 'new-password', 'Tối thiểu 8 ký tự'],
                                        'password_confirmation' => ['Xác nhận mật khẩu mới', 'new-password', 'Nhập lại mật khẩu mới'],
                                    ] as $field => [$label, $autocomplete, $placeholder])
                                        <div class="flex flex-col gap-1.5">
                                            <label for="{{ $field }}" class="{{ $labelClass }}">{{ $label }}</label>
                                            <input id="{{ $field }}" name="{{ $field }}" type="password" required autocomplete="{{ $autocomplete }}"
                                                placeholder="{{ $placeholder }}"
                                                class="{{ $inputClass }} @error($field) border-error @enderror">
                                            @error($field)
                                                <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                                            @enderror
                                            @if ($field === 'current_password')
                                                <p class="mt-1">
                                                    <a href="{{ route('password.request') }}" class="font-label-sm text-label-sm text-primary hover:underline">
                                                        Quên mật khẩu?
                                                    </a>
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="flex justify-end border-t border-outline-variant pt-4">
                                    <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                                        Cập nhật mật khẩu
                                    </button>
                                </div>
                            </form>
                        </section>
                    @endcan

                    @can('profile.two_factor_toggle')
                        <section class="{{ $cardClass }}">
                            <div class="{{ $cardHeaderClass }}">
                                <h2 class="flex items-center gap-2 font-title-md text-title-md text-on-surface">
                                    <span class="material-symbols-outlined text-[20px] text-primary">phonelink_lock</span>
                                    Xác thực hai bước (2FA)
                                </h2>
                                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                                    Tăng bảo mật bằng mã từ ứng dụng Authenticator. Tùy chọn, bật/tắt bất cứ lúc nào bằng mật khẩu hiện tại.
                                </p>
                            </div>

                            <div class="{{ $cardBodyClass }}">
                                @if ($user->hasTwoFactorEnabled())
                                    <span class="mb-5 inline-flex rounded-md bg-primary/10 px-2.5 py-1 font-label-sm text-label-sm font-semibold text-primary">
                                        Đã bật
                                    </span>
                                    <form method="post" action="{{ route('reviewer.profile.2fa.disable') }}" class="max-w-md space-y-4">
                                        @csrf
                                        @method('DELETE')
                                        <div class="flex flex-col gap-1.5">
                                            <label for="disable_2fa_password" class="{{ $labelClass }}">Mật khẩu hiện tại để tắt 2FA</label>
                                            <input id="disable_2fa_password" name="current_password" type="password" required autocomplete="current-password"
                                                placeholder="••••••••"
                                                class="{{ $inputClass }} @error('current_password') border-error @enderror">
                                            @error('current_password')
                                                <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <button type="submit" class="rounded-lg border border-error/40 bg-error-container px-5 py-2.5 font-label-md text-label-md font-semibold text-on-error-container hover:opacity-90">
                                            Tắt xác thực hai bước
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('reviewer.profile.2fa.setup') }}" class="inline-flex rounded-lg bg-primary px-5 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                                        Bật 2FA
                                    </a>
                                @endif
                            </div>
                        </section>
                    @endcan
                @endif
            </main>
        </div>
    </div>
</x-layouts.reviewer>
