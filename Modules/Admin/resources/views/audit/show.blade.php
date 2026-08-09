<x-layouts.admin title="Audit #{{ $log->id }}">
    <x-admin.page-header :title="'Audit #'.$log->id" :description="$log->action">
        <x-slot:actions>
            <a href="{{ route('admin.audit.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-outline-variant bg-surface p-5 space-y-3 font-body-sm text-body-sm">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Metadata</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Thời gian</dt>
                    <dd>{{ $log->created_at?->format('d/m/Y H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Actor</dt>
                    <dd>
                        @if ($log->actor)
                            <a href="{{ route('admin.users.show', $log->actor) }}" class="text-primary hover:underline">{{ $log->actor->name }}</a>
                            (#{{ $log->actor_id }})
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Đối tượng</dt>
                    <dd>
                        @if ($log->auditable_type)
                            {{ $log->auditable_type }} #{{ $log->auditable_id }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">IP</dt>
                    <dd>{{ $log->ip ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">User-Agent</dt>
                    <dd class="break-all text-on-surface-variant">{{ $log->user_agent ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-label-sm text-on-surface-variant">Request ID</dt>
                    <dd class="font-mono text-xs">{{ $log->request_id ?? '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="space-y-4">
            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="mb-2 font-headline-sm text-headline-sm text-on-surface">Before</h3>
                <pre class="overflow-x-auto rounded-lg bg-surface-container-low p-3 font-mono text-xs text-on-surface">{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="mb-2 font-headline-sm text-headline-sm text-on-surface">After</h3>
                <pre class="overflow-x-auto rounded-lg bg-surface-container-low p-3 font-mono text-xs text-on-surface">{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
            </div>
        </section>
    </div>
</x-layouts.admin>
