<x-layouts.admin title="Hỗ trợ chat">
    @php
        $toneClasses = [
            'warning' => 'bg-amber-100 text-amber-950',
            'success' => 'bg-emerald-100 text-emerald-950',
            'info' => 'bg-sky-100 text-sky-950',
            'muted' => 'bg-surface-container text-on-surface-variant',
        ];
        $filterNeedsReply = request()->query('needs_reply') === '1';
    @endphp

    <script>document.body.dataset.supportInboxList = '1';</script>

    <x-admin.page-header title="Hỗ trợ khách hàng" description="Theo dõi trạng thái và người đang xử lý từng hội thoại." />
    <x-admin.flash />

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.support.index') }}"
            class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ ! $filterNeedsReply && ! request()->query('status') ? 'bg-primary text-on-primary' : 'border border-outline-variant text-on-surface-variant' }}">Tất cả</a>
        <a href="{{ route('admin.support.index', ['needs_reply' => 1]) }}"
            class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $filterNeedsReply ? 'bg-primary text-on-primary' : 'border border-outline-variant text-on-surface-variant' }}">Chưa trả lời</a>
        <a href="{{ route('admin.support.index', ['status' => 'waiting_admin']) }}"
            class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ request()->query('status') === 'waiting_admin' ? 'bg-primary text-on-primary' : 'border border-outline-variant text-on-surface-variant' }}">Chờ xử lý</a>
        <a href="{{ route('admin.support.index', ['status' => 'admin_active']) }}"
            class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ request()->query('status') === 'admin_active' ? 'bg-primary text-on-primary' : 'border border-outline-variant text-on-surface-variant' }}">Đang xử lý</a>
    </div>

    <div class="overflow-x-auto overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <table class="w-full min-w-[880px] text-left">
            <thead class="bg-surface-container-low text-sm text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Người dùng</th>
                    <th class="px-4 py-3">Danh mục</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Người xử lý</th>
                    <th class="px-4 py-3">Tin gần nhất</th>
                    <th class="px-4 py-3">Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($conversations as $conversation)
                    @php
                        $state = $conversation->adminListStateFor($admin);
                        $workflow = $conversation->adminWorkflowStatus();
                        $latest = $conversation->latestMessage;
                        $handledByOther = $conversation->isHandledByOtherAdmin($admin);
                        $previewSender = match ($latest?->sender_type) {
                            'user' => 'Người dùng',
                            'admin' => 'Admin',
                            'ai' => 'AI',
                            'system' => 'Hệ thống',
                            default => '—',
                        };
                    @endphp
                    <tr class="border-t border-outline-variant hover:bg-surface-container-low {{ $state['unread'] ? 'bg-primary-container/30' : '' }}"
                        data-support-row
                        data-conversation-id="{{ $conversation->id }}">
                        <td class="px-4 py-3">
                            <a
                                class="inline-flex items-start gap-2 font-semibold text-primary"
                                href="{{ route('admin.support.show', $conversation) }}"
                                data-support-open
                                data-handled-by-other="{{ $handledByOther ? '1' : '0' }}"
                                data-handler-name="{{ $conversation->assignedAdmin?->name ?? '' }}"
                                data-claim-url="{{ route('admin.support.claim', $conversation) }}"
                            >
                                @if ($state['unread'])
                                    <span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-error" title="Có tin nhắn mới"></span>
                                @elseif ($state['needs_reply'])
                                    <span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-amber-500" title="Chưa trả lời"></span>
                                @else
                                    <span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-emerald-500" title="Đã xử lý gần đây"></span>
                                @endif
                                <span>
                                    <span class="{{ $state['unread'] || $state['needs_reply'] ? 'font-bold' : '' }}">{{ $conversation->user->name }}</span>
                                    <p class="text-sm font-normal text-on-surface-variant">{{ $conversation->subject ?: 'Không có tiêu đề' }}</p>
                                </span>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ \App\Models\SupportConversation::CATEGORY_LABELS[$conversation->category] ?? $conversation->category }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $toneClasses[$workflow['tone']] ?? $toneClasses['muted'] }}">
                                {{ $workflow['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($conversation->assignedAdmin)
                                <span class="font-medium text-on-surface">{{ $conversation->assignedAdmin->name }}</span>
                                @if ($handledByOther)
                                    <p class="text-xs text-on-surface-variant">Đang được xử lý</p>
                                @elseif ((int) $conversation->assigned_admin_id === (int) $admin->id)
                                    <p class="text-xs text-on-surface-variant">Bạn</p>
                                @endif
                            @else
                                <span class="text-on-surface-variant">Chưa có</span>
                            @endif
                        </td>
                        <td class="max-w-[280px] px-4 py-3 text-sm text-on-surface-variant">
                            @if ($latest)
                                <p class="font-semibold text-on-surface">{{ $previewSender }}</p>
                                <p class="truncate">{{ \Illuminate\Support\Str::limit($latest->body, 80) }}</p>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm whitespace-nowrap">{{ $conversation->last_message_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">Chưa có yêu cầu hỗ trợ.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $conversations->links() }}</div>

    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            document.querySelectorAll('[data-support-open]').forEach((link) => {
                link.addEventListener('click', async (event) => {
                    if (link.dataset.handledByOther !== '1') {
                        return;
                    }

                    event.preventDefault();
                    const handler = link.dataset.handlerName || 'một quản trị viên khác';
                    const accepted = window.confirm(
                        `Phiên chat này đang được xử lý bởi ${handler}.\nBạn có muốn tiếp nhận và xử lý không?`
                    );
                    if (!accepted) {
                        return;
                    }

                    try {
                        const body = new FormData();
                        body.append('_token', csrf);
                        const response = await fetch(link.dataset.claimUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body,
                        });
                        if (!response.ok) {
                            throw new Error('claim_failed');
                        }
                        const data = await response.json();
                        window.location.href = data.redirect || link.href;
                    } catch {
                        window.alert('Không thể tiếp nhận phiên chat. Vui lòng thử lại.');
                    }
                });
            });
        })();
    </script>
</x-layouts.admin>
