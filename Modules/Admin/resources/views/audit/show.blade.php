<x-layouts.admin title="Nhật ký #{{ $log->id }}">
    <x-admin.page-header :title="'Nhật ký #'.$log->id" :description="$log->actionLabel()">
        <x-slot:actions>
            <a href="{{ route('admin.audit.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-outline-variant bg-surface p-5 space-y-3 font-body-sm text-body-sm">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Thông tin nhật ký</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Thời gian</dt>
                    <dd>{{ $log->created_at?->format('d/m/Y H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Hành động</dt>
                    <dd>{{ $log->actionLabel() }} <span class="font-mono text-xs text-on-surface-variant">({{ $log->action }})</span></dd>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <dt class="font-label-sm text-on-surface-variant">Cổng truy cập / Nhóm</dt>
                        <dd>{{ $log->portal ?? '—' }} / {{ $log->category ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-sm text-on-surface-variant">Kết quả</dt>
                        <dd>{{ $log->result ?? '—' }}</dd>
                    </div>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Người thực hiện</dt>
                    <dd>
                        @if ($log->actor)
                            <a href="{{ route('admin.users.show', $log->actor) }}" class="text-primary hover:underline">{{ $log->actor->name }}</a>
                            (#{{ $log->actor_id }})
                            <span class="text-on-surface-variant">· {{ \App\Support\Enums\Role::tryFromName($log->actor_role)?->label() ?? '—' }}</span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Mã phiên</dt>
                    <dd class="break-all font-mono text-xs">{{ $log->session_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Đối tượng</dt>
                    <dd>
                        @if ($log->auditable_type)
                            @if ($log->auditable instanceof \App\Models\User)
                                <a href="{{ route('admin.users.show', $log->auditable) }}" class="text-primary hover:underline">Người dùng #{{ $log->auditable_id }}</a>
                            @elseif ($log->auditable instanceof \Modules\QuestionBank\Models\Question)
                                <a href="{{ route('admin.questions.edit', $log->auditable) }}" class="text-primary hover:underline">Câu hỏi #{{ $log->auditable_id }}</a>
                            @else
                                {{ $log->auditable_type }} #{{ $log->auditable_id }}
                            @endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">IP</dt>
                    <dd>{{ $log->ip ?? '—' }}</dd>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <dt class="font-label-sm text-on-surface-variant">Thiết bị</dt>
                        <dd>{{ $log->device_name ?? $log->deviceTypeLabel() }}</dd>
                        <dd class="text-xs text-on-surface-variant">{{ $log->deviceTypeLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-sm text-on-surface-variant">Hệ điều hành</dt>
                        <dd>{{ $log->operating_system ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-sm text-on-surface-variant">Trình duyệt</dt>
                        <dd>{{ $log->browser ?? '—' }}</dd>
                    </div>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Thông tin trình duyệt</dt>
                    <dd class="break-all text-on-surface-variant">{{ $log->user_agent ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Mã yêu cầu</dt>
                    <dd class="font-mono text-xs">{{ $log->request_id ?? '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="space-y-4">
            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="mb-2 font-headline-sm text-headline-sm text-on-surface">Trước thay đổi</h3>
                <pre class="overflow-x-auto rounded-lg bg-surface-container-low p-3 font-mono text-xs text-on-surface">{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="mb-2 font-headline-sm text-headline-sm text-on-surface">Sau thay đổi</h3>
                <pre class="overflow-x-auto rounded-lg bg-surface-container-low p-3 font-mono text-xs text-on-surface">{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
            </div>
            @if ($log->metadata !== null)
                <div class="rounded-xl border border-outline-variant bg-surface p-5">
                    <h3 class="mb-2 font-headline-sm text-headline-sm text-on-surface">Ngữ cảnh</h3>
                    <pre class="overflow-x-auto rounded-lg bg-surface-container-low p-3 font-mono text-xs text-on-surface">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </section>
    </div>
</x-layouts.admin>
