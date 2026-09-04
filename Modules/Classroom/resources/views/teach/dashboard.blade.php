<x-layouts.teach title="Tổng quan">
    <x-admin.page-header title="Bảng điều khiển giảng viên"
        description="Tạo và chạy buổi chữa đề. Hàng chờ feedback và chữa theo exam sẽ mở ở các phase tiếp theo." />

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('teach.classes.index') }}" class="block transition hover:opacity-90">
            <x-admin.kpi-card label="Lớp của tôi" value="→" hint="Xem & tạo lớp" icon="school" />
        </a>
        <x-admin.kpi-card label="Sắp live" value="—" hint="Theo lịch lớp" icon="podcasts" />
        <x-admin.kpi-card label="Câu cần chữa" value="—" hint="Phase B+" icon="flag" />
    </div>

    <section class="rounded-xl border border-outline-variant bg-surface p-5">
        <h3 class="mb-2 font-headline-sm text-headline-sm text-on-surface">Bắt đầu nhanh</h3>
        <p class="mb-4 font-body-sm text-body-sm text-on-surface-variant">
            Xin chào, <span class="text-on-surface">{{ auth()->user()->name }}</span> — tạo lớp chữa đề
            (feedback QBank hoặc exam) rồi gắn đề và host live ở bước sau.
        </p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('teach.classes.create') }}"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Tạo lớp
            </a>
            <a href="{{ route('teach.classes.index') }}"
                class="inline-flex items-center gap-2 rounded-lg border border-outline-variant px-4 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container-low">
                Xem lớp của tôi
            </a>
        </div>
        <ul class="mt-6 space-y-2 font-body-sm text-body-sm text-on-surface-variant">
            <li>• Phase B (xong): list + tạo lớp + trang chi tiết stub</li>
            <li>• Giai đoạn B+: hàng chờ phản hồi ngân hàng câu hỏi → gắn bộ câu hỏi</li>
            <li>• Giai đoạn C: chữa theo đề thi / kỳ thi</li>
        </ul>
    </section>
</x-layouts.teach>
