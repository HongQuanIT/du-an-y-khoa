<x-layouts.partner title="Người được mời">
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Người dùng</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Mã</th>
                    <th class="px-4 py-3">Gói hiện tại</th>
                    <th class="px-4 py-3">Đăng ký</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($referrals as $row)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3 font-label-md text-on-surface">{{ $row['user']?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $row['user']?->email ?? '—' }}</td>
                        <td class="px-4 py-3 tracking-wide">{{ $row['attribution']->inviteCode?->code }}</td>
                        <td class="px-4 py-3">
                            <div>{{ $row['plan_name'] }}</div>
                            @if ($row['ends_at'])
                                <div class="font-label-sm text-on-surface-variant">Hết hạn {{ $row['ends_at']->format('d/m/Y') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $row['attribution']->attributed_at?->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Chưa có người đăng ký qua mã của bạn.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $referrals->links() }}</div>
</x-layouts.partner>
