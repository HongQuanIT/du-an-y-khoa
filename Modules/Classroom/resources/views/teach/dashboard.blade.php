<x-layouts.teach title="Tổng quan">
    <x-admin.page-header title="Bảng điều khiển giảng viên"
        description="Tạo và chạy buổi chữa đề. Lớp của tôi, hàng chờ feedback và chữa theo exam sẽ mở ở các phase tiếp theo." />

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.kpi-card label="Lớp của tôi" value="—" hint="Phase B" icon="school" />
        <x-admin.kpi-card label="Sắp live" value="—" hint="Phase B" icon="podcasts" />
        <x-admin.kpi-card label="Câu cần chữa" value="—" hint="Phase B" icon="flag" />
    </div>

    <section class="rounded-xl border border-outline-variant bg-surface p-5">
        <h3 class="font-headline-sm text-headline-sm text-on-surface mb-2">Phase A đã sẵn sàng</h3>
        <p class="font-body-sm text-body-sm text-on-surface-variant mb-4">
            Bạn đang ở portal <span class="text-on-surface font-label-md">/teach</span> — tách biệt với dashboard học viên
            và khu quản trị CMS. Xin chào, <span class="text-on-surface">{{ auth()->user()->name }}</span>.
        </p>
        <ul class="space-y-2 font-body-sm text-body-sm text-on-surface-variant">
            <li>• Phase B: hàng chờ feedback QBank → gắn bộ câu hỏi buổi live</li>
            <li>• Phase C: chữa theo exam / kỳ thi</li>
        </ul>
    </section>
</x-layouts.teach>
