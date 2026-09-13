<nav x-show="total > 0" x-cloak
     class="flex flex-col gap-3 border-t border-outline-variant px-5 py-3 sm:flex-row sm:items-center sm:justify-between"
     aria-label="Phân trang">
    <p class="text-xs tabular-nums text-on-surface-variant">
        <span x-text="pageRangeLabel"></span>
        · <span x-text="lastPage"></span> trang
    </p>
    <div class="flex flex-wrap items-center gap-1" x-show="lastPage > 1">
        <button type="button" @click="goToPage(page - 1)" :disabled="page <= 1 || loading"
            class="inline-flex h-8 items-center rounded-lg border border-outline-variant px-2.5 text-xs font-medium text-on-surface hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-40">
            Trước
        </button>
        <template x-for="n in pageNumbers" :key="'page-'+n">
            <button type="button" @click="goToPage(n)" :disabled="loading"
                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-semibold tabular-nums"
                :class="n === page
                    ? 'bg-primary text-on-primary'
                    : 'border border-outline-variant text-on-surface hover:bg-surface-container-low'"
                x-text="n"></button>
        </template>
        <button type="button" @click="goToPage(page + 1)" :disabled="page >= lastPage || loading"
            class="inline-flex h-8 items-center rounded-lg border border-outline-variant px-2.5 text-xs font-medium text-on-surface hover:bg-surface-container-low disabled:cursor-not-allowed disabled:opacity-40">
            Sau
        </button>
    </div>
</nav>
