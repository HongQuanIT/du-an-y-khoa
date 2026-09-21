@php
    $statusTone = $statusTone ?? collect(\Modules\Admin\Enums\ContactInquiryStatus::cases())->mapWithKeys(
        fn ($status) => [$status->value => $status->tone()]
    )->all();
@endphp

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-outline-variant text-sm">
        <thead class="bg-surface-container-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
            <tr>
                <th class="px-4 py-3">Liên hệ</th>
                <th class="px-4 py-3">Người gửi</th>
                <th class="px-4 py-3">Chủ đề</th>
                <th class="px-4 py-3">Người xử lý</th>
                <th class="px-4 py-3">Thời gian</th>
                <th class="px-4 py-3">Trạng thái</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant">
            @forelse ($inquiries as $inquiry)
                @php
                    $unread = $inquiry->read_at === null && $inquiry->status->value === 'new';
                @endphp
                <tr class="align-top {{ $unread ? 'bg-primary-container/20' : '' }}">
                    <td class="max-w-md px-4 py-4">
                        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.contacts.show'))
                            <a href="{{ route('admin.contacts.show', $inquiry) }}" class="group block">
                                <div class="mb-1 flex items-center gap-2">
                                    @if ($unread)
                                        <span class="size-2 shrink-0 rounded-full bg-error" title="Chưa đọc"></span>
                                    @endif
                                    <span class="font-mono text-xs text-on-surface-variant">{{ $inquiry->reference }}</span>
                                </div>
                                <p class="font-semibold text-primary group-hover:underline {{ $unread ? 'font-bold' : '' }}">
                                    {{ \Illuminate\Support\Str::limit($inquiry->message, 120) }}
                                </p>
                            </a>
                        @endif
                    </td>
                    <td class="px-4 py-4">
                        <p class="font-semibold text-on-surface">{{ $inquiry->name }}</p>
                        <p class="text-xs text-on-surface-variant">{{ $inquiry->email }}</p>
                        @if ($inquiry->user)
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.users.show'))
                                <a href="{{ route('admin.users.show', $inquiry->user) }}" class="mt-1 inline-block text-xs text-primary hover:underline">
                                    Tài khoản #{{ $inquiry->user_id }}
                                </a>
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-4">
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                            <span class="material-symbols-outlined text-[14px]">{{ $inquiry->subject->icon() }}</span>
                            {{ $inquiry->subject->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-4 text-on-surface-variant">
                        {{ $inquiry->assignedAdmin?->name ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-4 text-on-surface-variant">
                        {{ $inquiry->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-4">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusTone[$inquiry->status->value] ?? '' }}">
                            {{ $inquiry->status->label() }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-on-surface-variant">
                        <span class="material-symbols-outlined mb-2 block text-3xl text-on-surface-variant/60">inbox</span>
                        Chưa có liên hệ phù hợp bộ lọc.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($inquiries->hasPages())
    <div class="border-t border-outline-variant px-4 py-3 pagination-container">
        {{ $inquiries->links() }}
    </div>
@endif
