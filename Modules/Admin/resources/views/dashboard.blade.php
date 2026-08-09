<x-layouts.admin title="Tổng quan">
    <x-admin.page-header title="Tổng quan vận hành"
        description="KPI sẽ nối rollup thật ở phase sau. Hiện là khung dashboard + lối tắt theo quyền của bạn." />

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi-card label="MAU" value="—" hint="Sắp có" icon="group" />
        <x-admin.kpi-card label="Đăng ký mới (7 ngày)" value="—" hint="Sắp có" icon="person_add" />
        <x-admin.kpi-card label="Câu hỏi published" value="—" hint="Sắp có" icon="quiz" />
        <x-admin.kpi-card label="Report chờ xử lý" value="—" hint="Sắp có" icon="flag" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-outline-variant bg-surface p-5">
            <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">Lối tắt</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant mb-4">
                Các module quản trị sẽ hiện ở đây khi được triển khai. Menu trái đã lọc theo permission.
            </p>
            <ul class="space-y-2 font-label-md text-label-md text-on-surface-variant">
                @foreach (\Modules\Admin\Support\AdminMenu::for(auth()->user()) as $item)
                    @if ($item['route'])
                        <li>
                            <a href="{{ route($item['route']) }}" class="text-primary hover:underline">{{ $item['label'] }}</a>
                        </li>
                    @elseif ($item['coming_soon'] ?? false)
                        <li class="opacity-60">{{ $item['label'] }} — sắp có</li>
                    @endif
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl border border-outline-variant bg-surface p-5">
            <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">Bảo mật phiên</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant mb-2">
                Đăng nhập quản trị bắt buộc 2FA (TOTP). Mỗi phiên đăng nhập mới cần nhập lại mã.
            </p>
            <p class="font-label-sm text-label-sm text-on-surface-variant">
                Xin chào, <span class="text-on-surface">{{ auth()->user()->name }}</span> — khu vực này độc lập với dashboard học viên.
            </p>
        </section>
    </div>
</x-layouts.admin>
