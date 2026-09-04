@php
    $mailto = 'mailto:'.rawurlencode($inquiry->email)
        .'?subject='.rawurlencode('Re: ['.$inquiry->reference.'] '.$inquiry->subject->label())
        .'&body='.rawurlencode("Xin chào {$inquiry->name},\n\n");
    $tel = $inquiry->phone ? 'tel:'.preg_replace('/\D+/', '', $inquiry->phone) : null;
@endphp

<x-layouts.admin title="{{ $inquiry->reference }}">
    <x-admin.page-header :title="'Liên hệ '.$inquiry->reference" :description="$inquiry->subject->label().' · '.$inquiry->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i')">
        <x-slot:actions>
            <a href="{{ route('admin.contacts.index') }}"
                class="rounded-lg px-3 py-2 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low">← Danh sách</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-outline-variant bg-surface p-5 sm:p-6">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $inquiry->status->tone() }}">
                        {{ $inquiry->status->label() }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                        <span class="material-symbols-outlined text-[14px]">{{ $inquiry->subject->icon() }}</span>
                        {{ $inquiry->subject->label() }}
                    </span>
                    <span class="font-mono text-xs text-on-surface-variant">{{ $inquiry->reference }}</span>
                </div>

                <h3 class="mb-3 font-headline-sm text-headline-sm text-on-surface">Nội dung</h3>
                <div class="rounded-lg border border-outline-variant/60 bg-surface-container-low/40 p-4">
                    <p class="whitespace-pre-wrap text-body-md leading-relaxed text-on-surface">{{ $inquiry->message }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-outline-variant bg-surface p-5 sm:p-6">
                <h3 class="mb-4 font-headline-sm text-headline-sm text-on-surface">Người gửi</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 font-body-sm text-body-sm">
                    <div>
                        <dt class="font-label-sm text-label-sm text-on-surface-variant">Họ tên</dt>
                        <dd class="mt-0.5 font-semibold text-on-surface">{{ $inquiry->name }}</dd>
                    </div>
                    <div>
                        <dt class="font-label-sm text-label-sm text-on-surface-variant">Email</dt>
                        <dd class="mt-0.5">
                            <a href="{{ $mailto }}" class="font-semibold text-primary hover:underline">{{ $inquiry->email }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-label-sm text-label-sm text-on-surface-variant">Điện thoại</dt>
                        <dd class="mt-0.5 text-on-surface">
                            @if ($inquiry->phone && $tel)
                                <a href="{{ $tel }}" class="text-primary hover:underline">{{ $inquiry->phone }}</a>
                            @else
                                <span class="text-on-surface-variant">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-label-sm text-label-sm text-on-surface-variant">Tài khoản</dt>
                        <dd class="mt-0.5 text-on-surface">
                            @if ($inquiry->user)
                                <a href="{{ route('admin.users.show', $inquiry->user) }}" class="text-primary hover:underline">
                                    {{ $inquiry->user->name }} (#{{ $inquiry->user_id }})
                                </a>
                            @else
                                <span class="text-on-surface-variant">Khách (chưa đăng nhập)</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap gap-2 border-t border-outline-variant pt-4">
                    <a href="{{ $mailto }}"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-on-primary hover:opacity-90">
                        <span class="material-symbols-outlined text-[18px]">reply</span>
                        Trả lời email
                    </a>
                    @if ($tel)
                        <a href="{{ $tel }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                            <span class="material-symbols-outlined text-[18px]">call</span>
                            Gọi điện
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="mb-4 font-headline-sm text-headline-sm text-on-surface">Xử lý</h3>

                @if ($canManage)
                    @if ($inquiry->assigned_admin_id !== auth()->id() && $inquiry->status->isOpen())
                        <form method="post" action="{{ route('admin.contacts.claim', $inquiry) }}" class="mb-4">
                            @csrf
                            <button type="submit"
                                class="w-full rounded-lg border border-primary/30 bg-primary/5 px-3 py-2.5 text-sm font-semibold text-primary hover:bg-primary/10">
                                Nhận xử lý
                            </button>
                        </form>
                    @endif

                    <form method="post" action="{{ route('admin.contacts.update', $inquiry) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Trạng thái</span>
                            <select name="status" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $inquiry->status->value) === $status->value)>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Người xử lý</span>
                            <select name="assigned_admin_id" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
                                <option value="">Chưa gán</option>
                                @foreach ($staff as $member)
                                    <option value="{{ $member->id }}" @selected((string) old('assigned_admin_id', $inquiry->assigned_admin_id) === (string) $member->id)>
                                        {{ $member->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Ghi chú nội bộ</span>
                            <textarea name="admin_notes" rows="5" maxlength="5000"
                                placeholder="Ghi chú cho team (không gửi cho khách)…"
                                class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-sm text-on-surface resize-y">{{ old('admin_notes', $inquiry->admin_notes) }}</textarea>
                        </label>

                        <button type="submit"
                            class="w-full rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-on-primary hover:opacity-90">
                            Lưu thay đổi
                        </button>
                    </form>
                @else
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-on-surface-variant">Người xử lý</dt>
                            <dd class="font-medium text-on-surface">{{ $inquiry->assignedAdmin?->name ?? 'Chưa gán' }}</dd>
                        </div>
                        @if ($inquiry->admin_notes)
                            <div>
                                <dt class="mb-1 text-on-surface-variant">Ghi chú nội bộ</dt>
                                <dd class="whitespace-pre-wrap rounded-lg bg-surface-container-low p-3 text-on-surface">{{ $inquiry->admin_notes }}</dd>
                            </div>
                        @endif
                        <p class="text-on-surface-variant">Bạn chỉ có quyền xem. Cần <code class="text-xs">contact.manage</code> để cập nhật.</p>
                    </dl>
                @endif
            </div>

            <div class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="mb-3 font-headline-sm text-headline-sm text-on-surface">Thông tin bổ sung</h3>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-on-surface-variant">Gửi lúc</dt>
                        <dd class="text-on-surface">{{ $inquiry->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">Đọc lúc</dt>
                        <dd class="text-on-surface">{{ $inquiry->read_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">Xử lý xong</dt>
                        <dd class="text-on-surface">
                            @if ($inquiry->resolved_at)
                                {{ $inquiry->resolved_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                @if ($inquiry->resolver)
                                    <span class="text-on-surface-variant">· {{ $inquiry->resolver->name }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">IP</dt>
                        <dd class="font-mono text-xs text-on-surface">{{ $inquiry->ip_address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">User agent</dt>
                        <dd class="break-all text-xs text-on-surface-variant">{{ \Illuminate\Support\Str::limit($inquiry->user_agent ?? '—', 160) }}</dd>
                    </div>
                </dl>
            </div>
        </aside>
    </div>
</x-layouts.admin>
