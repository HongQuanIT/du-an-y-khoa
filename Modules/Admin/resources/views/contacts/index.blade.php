@php
    $statusTone = collect($statuses)->mapWithKeys(
        fn ($status) => [$status->value => $status->tone()]
    )->all();
@endphp

<x-layouts.admin title="Liên hệ">
    <x-admin.page-header title="Hộp thư liên hệ"
        description="Quản lý tin nhắn từ form /contact — lọc, gán xử lý và theo dõi trạng thái." />

    <x-admin.flash />

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <a href="{{ route('admin.contacts.index') }}"
            class="rounded-xl border border-outline-variant bg-surface p-4 transition hover:border-primary hover:bg-primary/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Đang mở</p>
            <p class="mt-2 text-2xl font-bold text-on-surface">{{ number_format($openCount) }}</p>
        </a>
        @foreach ($statuses as $status)
            <a href="{{ route('admin.contacts.index', ['status' => $status->value]) }}"
                class="rounded-xl border border-outline-variant bg-surface p-4 transition hover:border-primary hover:bg-primary/5 {{ $filters['status'] === $status->value ? 'border-primary bg-primary/5' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ $status->label() }}</p>
                <p class="mt-2 text-2xl font-bold text-on-surface">{{ number_format((int) ($statusCounts[$status->value] ?? 0)) }}</p>
            </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('admin.contacts.index') }}" role="search" aria-label="Lọc liên hệ"
        class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
        <label class="md:col-span-4">
            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</span>
            <input name="q" value="{{ $filters['q'] }}" type="search"
                placeholder="Mã, tên, email, nội dung…"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
        </label>
        <label class="md:col-span-2">
            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Trạng thái</span>
            <select name="status" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
                <option value="">Tất cả</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="md:col-span-2">
            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Chủ đề</span>
            <select name="subject" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
                <option value="">Tất cả</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->value }}" @selected($filters['subject'] === $subject->value)>{{ $subject->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="md:col-span-2">
            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Phân công</span>
            <select name="assigned" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
                <option value="">Tất cả</option>
                <option value="unassigned" @selected($filters['assigned'] === 'unassigned')>Chưa gán</option>
                <option value="me" @selected($filters['assigned'] === 'me')>Của tôi</option>
            </select>
        </label>
        <div class="grid grid-cols-2 gap-2 md:col-span-2">
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary hover:opacity-90">
                Lọc
            </button>
            <a href="{{ route('admin.contacts.index') }}"
                class="inline-flex h-11 items-center justify-center rounded-lg border border-outline-variant px-4 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">
                Xóa lọc
            </a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
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
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-on-surface">{{ $inquiry->name }}</p>
                                <p class="text-xs text-on-surface-variant">{{ $inquiry->email }}</p>
                                @if ($inquiry->user)
                                    <a href="{{ route('admin.users.show', $inquiry->user) }}" class="mt-1 inline-block text-xs text-primary hover:underline">
                                        Tài khoản #{{ $inquiry->user_id }}
                                    </a>
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
            <div class="border-t border-outline-variant px-4 py-3">
                {{ $inquiries->links() }}
            </div>
        @endif
    </div>
</x-layouts.admin>
