@php
    $unreads = $popupNotifications ?? collect();
    $unreadCount = (int) ($headerUnreadCount ?? 0);
    $userId = auth()->id();
@endphp

@if ($userId && $unreads->isNotEmpty())
    <div x-data="{
            open: false,
            items: {{ Illuminate\Support\Js::from($unreads->map(function ($item) {
                $category = $item->category ?? 'system';
                $type = $item->type ?? '';
                
                $style = match(true) {
                    str_contains($type, 'streak') => [
                        'badge' => 'bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-400 border-orange-200 dark:border-orange-800',
                        'icon' => 'local_fire_department',
                        'gradient' => 'from-amber-500 to-orange-600',
                        'cta' => 'Tiếp tục chuỗi học',
                    ],
                    str_contains($type, 'daily') || str_contains($type, 'study_plan') || str_contains($type, 'comeback') => [
                        'badge' => 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400 border-blue-200 dark:border-blue-800',
                        'icon' => str_contains($type, 'comeback') ? 'waving_hand' : 'alarm',
                        'gradient' => 'from-blue-500 to-indigo-600',
                        'cta' => 'Bắt đầu học ngay',
                    ],
                    str_contains($type, 'assignment') => [
                        'badge' => 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 border-rose-200 dark:border-rose-800',
                        'icon' => 'assignment_late',
                        'gradient' => 'from-rose-500 to-red-600',
                        'cta' => 'Xem bài tập',
                    ],
                    str_contains($type, 'live') || str_contains($type, 'recording') => [
                        'badge' => 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400 border-red-200 dark:border-red-800',
                        'icon' => str_contains($type, 'recording') ? 'videocam' : 'live_tv',
                        'gradient' => 'from-red-600 to-pink-600',
                        'cta' => 'Vào lớp ngay',
                    ],
                    str_contains($type, 'session') || str_contains($type, 'exam') || str_contains($type, 'achievement') || str_contains($type, 'weekly') => [
                        'badge' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                        'icon' => str_contains($type, 'achievement') ? 'workspace_premium' : 'task_alt',
                        'gradient' => 'from-emerald-500 to-teal-600',
                        'cta' => 'Xem kết quả',
                    ],
                    default => [
                        'badge' => 'bg-primary/10 text-primary border-primary/20',
                        'icon' => $item->icon() ?: 'notifications',
                        'gradient' => 'from-primary to-primary-container',
                        'cta' => 'Xem chi tiết',
                    ],
                };

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'body' => $item->body,
                    'action_url' => $item->action_url,
                    'created_at' => $item->created_at->diffForHumans(),
                    'style' => $style,
                ];
            }))->toHtml() }},
            currentIndex: 0,
            async markRead(item) {
                try {
                    await fetch('/notifications/' + item.id + '/read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                } catch (e) {}

                if (item.action_url) {
                    window.location.href = item.action_url;
                } else {
                    this.dismissItem(item.id);
                }
            },
            async markAllRead() {
                try {
                    await fetch('/notifications/read-all', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                } catch (e) {}
                this.closeModal();
            },
            dismissItem(id) {
                this.items = this.items.filter(i => i.id !== id);
                if (this.items.length === 0) {
                    this.closeModal();
                } else if (this.currentIndex >= this.items.length) {
                    this.currentIndex = this.items.length - 1;
                }
            },
            closeModal() {
                this.open = false;
                if (this.items.length > 0) {
                    sessionStorage.setItem('notification_popup_dismissed_latest', String(this.items[0].id));
                }
            },
            init() {
                const latestId = this.items[0]?.id;
                const dismissedLatest = sessionStorage.getItem('notification_popup_dismissed_latest');
                if (latestId && (!dismissedLatest || String(latestId) !== dismissedLatest)) {
                    // Small delay for smooth entry on page load
                    setTimeout(() => {
                        this.open = true;
                    }, 400);
                }
            }
        }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="notification-modal-title">

        {{-- Backdrop --}}
        <div x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            @click="closeModal()"></div>

        {{-- Modal Content Card --}}
        <div x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-outline-variant bg-surface p-0 shadow-2xl transition-all"
            @click.outside="closeModal()">

            <template x-if="items.length > 0 && items[currentIndex]">
                <div>
                    {{-- Top Accent Gradient Bar --}}
                    <div class="h-2 w-full bg-gradient-to-r" :class="items[currentIndex].style.gradient"></div>

                    {{-- Modal Header --}}
                    <div class="flex items-start justify-between p-5 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 items-center justify-center rounded-xl border"
                                :class="items[currentIndex].style.badge">
                                <span class="material-symbols-outlined text-[24px]" x-text="items[currentIndex].style.icon"></span>
                            </div>
                            <div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary">
                                    <span class="size-1.5 rounded-full bg-primary animate-ping"></span>
                                    Thông báo mới
                                </span>
                                <p class="text-xs text-on-surface-variant mt-0.5" x-text="items[currentIndex].created_at"></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <template x-if="items.length > 1">
                                <span class="text-xs font-semibold text-on-surface-variant bg-surface-container px-2 py-1 rounded-md">
                                    <span x-text="currentIndex + 1"></span>/<span x-text="items.length"></span>
                                </span>
                            </template>
                            <button type="button" @click="closeModal()"
                                class="rounded-lg p-1.5 text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors"
                                aria-label="Đóng thông báo">
                                <span class="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>
                    </div>

                    {{-- Notification Main Body --}}
                    <div class="px-5 py-3">
                        <h3 id="notification-modal-title" class="font-headline-sm text-lg font-bold text-on-surface"
                            x-text="items[currentIndex].title"></h3>
                        <p class="mt-2 text-sm text-on-surface-variant leading-relaxed"
                            x-text="items[currentIndex].body"></p>
                    </div>

                    {{-- Multi-item Navigator Dots (if multiple) --}}
                    <template x-if="items.length > 1">
                        <div class="flex items-center justify-center gap-1.5 px-5 py-2">
                            <template x-for="(item, idx) in items" :key="item.id">
                                <button type="button" @click="currentIndex = idx"
                                    class="size-2 rounded-full transition-all"
                                    :class="currentIndex === idx ? 'bg-primary w-5' : 'bg-outline-variant hover:bg-on-surface-variant'">
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- Action Footer --}}
                    <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-3 border-t border-outline-variant bg-surface-container-lowest p-4 px-5">
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <button type="button" @click="markAllRead()"
                                class="w-full sm:w-auto text-xs font-medium text-on-surface-variant hover:text-primary transition-colors py-2 px-1 text-center sm:text-left">
                                Đánh dấu đã đọc tất cả
                            </button>
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <button type="button" @click="dismissItem(items[currentIndex].id)"
                                class="flex-1 sm:flex-none rounded-xl border border-outline-variant px-4 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                Bỏ qua
                            </button>
                            <button type="button" @click="markRead(items[currentIndex])"
                                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-primary-container hover:text-on-primary-container active:scale-95 transition-all">
                                <span x-text="items[currentIndex].action_url ? items[currentIndex].style.cta : 'Đã hiểu'"></span>
                                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
@endif
