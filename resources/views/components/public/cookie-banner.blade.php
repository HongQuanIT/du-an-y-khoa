<div x-data="{ show: true }" x-show="show" x-cloak x-transition.duration.500ms
    class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[calc(100%-32px)] max-w-xl glass p-6 rounded-2xl border border-border shadow-2xl z-[100] flex flex-col md:flex-row items-center gap-6">
    <div class="flex-1 space-y-1">
        <div class="font-label-md text-label-md text-on-surface">Chúng tôi sử dụng Cookies</div>
        <p class="font-body-sm text-body-sm text-text-secondary">Để mang lại trải nghiệm học tập tốt nhất,
            {{ config('app.name') }} sử dụng cookie để phân tích lưu lượng và cá nhân hóa nội dung.</p>
    </div>
    <div class="flex gap-3 w-full md:w-auto">
        <button type="button" @click="show = false"
            class="flex-1 md:flex-none px-6 py-2 rounded-xl bg-primary text-white font-label-md text-label-md hover:opacity-90 transition-opacity">Chấp
            nhận</button>
        <button type="button" @click="show = false"
            class="flex-1 md:flex-none px-6 py-2 rounded-xl border border-border font-label-md text-label-md hover:bg-surface-container-low transition-colors">Từ
            chối</button>
    </div>
</div>
