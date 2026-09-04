<x-layouts.admin title="Phân loại câu hỏi">
    <x-admin.page-header title="Phân loại câu hỏi"
        description="Quản lý ba lớp phân loại: ma trận đề thi, danh mục y khoa và thẻ.">
    </x-admin.page-header>

    @include('admin::taxonomy._sub-nav', ['active' => 'overview'])

    <x-admin.flash />

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-admin.kpi-card label="Ma trận đề thi" :value="number_format($stats['blueprints'])" hint="{{ number_format($stats['sections']) }} phần · {{ number_format($stats['core_topics']) }} chủ đề lâm sàng" icon="assignment" />
        <x-admin.kpi-card label="Danh mục y khoa" :value="number_format($stats['medical_nodes'])" hint="Cây phân loại kiến thức y khoa" icon="account_tree" />
        <x-admin.kpi-card label="Thẻ" :value="number_format($stats['tags'])" hint="Nhãn phân loại bổ sung" icon="sell" />
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <a href="{{ route('admin.blueprints.index') }}"
            class="group rounded-xl border border-outline-variant bg-surface p-5 transition-colors hover:border-primary/40 hover:bg-primary/5">
            <div class="flex items-start gap-4">
                <span class="flex size-12 items-center justify-center rounded-xl bg-primary-container text-on-primary-container">
                    <span class="material-symbols-outlined text-[28px]">assignment</span>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-label-lg font-semibold text-on-surface group-hover:text-primary">Ma trận đề thi</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Ma trận → Phần → Chủ đề lâm sàng (128 chủ đề theo QĐ 22/QĐ-HĐYKQG). Dùng cho cấu hình kỳ thi và bộ lọc phiên luyện.
                    </p>
                    <p class="mt-3 text-xs font-semibold text-primary">Quản lý ma trận →</p>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.medical-taxonomy.index') }}"
            class="group rounded-xl border border-outline-variant bg-surface p-5 transition-colors hover:border-primary/40 hover:bg-primary/5">
            <div class="flex items-start gap-4">
                <span class="flex size-12 items-center justify-center rounded-xl bg-secondary-container text-on-secondary-container">
                    <span class="material-symbols-outlined text-[28px]">account_tree</span>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-label-lg font-semibold text-on-surface group-hover:text-primary">Danh mục y khoa</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Cây kiến thức y khoa: hệ cơ quan → chuyên khoa → bệnh; kèm triệu chứng, dấu hiệu, cận lâm sàng và khái niệm để gắn câu hỏi.
                    </p>
                    <p class="mt-3 text-xs font-semibold text-primary">Duyệt danh mục →</p>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.tags.index') }}"
            class="group rounded-xl border border-outline-variant bg-surface p-5 transition-colors hover:border-primary/40 hover:bg-primary/5">
            <div class="flex items-start gap-4">
                <span class="flex size-12 items-center justify-center rounded-xl bg-tertiary-container text-on-tertiary-container">
                    <span class="material-symbols-outlined text-[28px]">sell</span>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-label-lg font-semibold text-on-surface group-hover:text-primary">Thẻ</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Nhãn phân loại (ECG, cấp cứu, trọng tâm…) để lọc câu hỏi và tùy chỉnh phiên luyện.
                    </p>
                    <p class="mt-3 text-xs font-semibold text-primary">Quản lý thẻ →</p>
                </div>
            </div>
        </a>
    </div>
</x-layouts.admin>
