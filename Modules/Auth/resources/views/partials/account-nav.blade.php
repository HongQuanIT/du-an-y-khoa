@php
    $active = $active ?? 'career';
    $tabs = [
        'career' => ['label' => 'Hồ sơ nghề nghiệp & học tập', 'route' => 'profile.show', 'params' => []],
        'contact' => ['label' => 'Liên hệ & cài đặt', 'route' => 'settings.edit', 'params' => ['tab' => 'contact']],
        'membership' => ['label' => 'Gói & giấy phép', 'route' => 'settings.edit', 'params' => ['tab' => 'membership']],
        'invoices' => ['label' => 'Hóa đơn', 'route' => 'settings.edit', 'params' => ['tab' => 'invoices']],
        'redeem' => ['label' => 'Đổi mã', 'route' => 'settings.edit', 'params' => ['tab' => 'redeem']],
    ];
@endphp

<nav class="relative z-20 border-b border-outline-variant" aria-label="Tài khoản">
    <div class="flex flex-wrap items-end gap-1">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route'], $tab['params']) }}"
                @class([
                    'shrink-0 border-b-2 px-3 pb-3 pt-1 font-label-md text-label-md transition-colors',
                    'border-primary font-semibold text-primary' => $active === $key,
                    'border-transparent text-on-surface-variant hover:text-on-surface' => $active !== $key,
                ])>
                {{ $tab['label'] }}
            </a>
        @endforeach

        <details class="group relative shrink-0" x-data @click.outside="$el.open = false">
            <summary
                @class([
                    'flex cursor-pointer list-none items-center gap-1 border-b-2 px-3 pb-3 pt-1 font-label-md text-label-md transition-colors marker:content-none [&::-webkit-details-marker]:hidden',
                    'border-primary font-semibold text-primary' => in_array($active, ['notes', 'org-license'], true),
                    'border-transparent text-on-surface-variant hover:text-on-surface' => ! in_array($active, ['notes', 'org-license'], true),
                ])>
                Thêm
                <span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180">expand_more</span>
            </summary>
            <div
                class="absolute left-0 top-full z-50 mt-1 min-w-[240px] rounded-lg border border-outline-variant bg-surface-container-lowest py-1 shadow-lg">
                <a href="{{ route('settings.edit', ['tab' => 'org-license']) }}"
                    class="block px-4 py-2.5 font-body-md text-body-md text-on-surface hover:bg-surface-container-low">
                    Kích hoạt giấy phép tổ chức
                </a>
                <a href="{{ route('settings.edit', ['tab' => 'notes']) }}"
                    class="block px-4 py-2.5 font-body-md text-body-md text-on-surface hover:bg-surface-container-low">
                    Ghi chú &amp; khác
                </a>
            </div>
        </details>
    </div>
</nav>
