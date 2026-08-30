<x-layouts.partner title="Mã mời">
    <p class="mb-6 font-body-sm text-body-sm text-on-surface-variant">
        Mã và link do quản trị viên cấp. Học viên cần đăng ký trong cửa sổ giữ mã
        (mặc định {{ \Modules\Partner\Support\PartnerSettings::attributionWindowDays() }} ngày sau khi mở link)
        thì mới được gắn về bạn. Bạn có thể sao chép link để chia sẻ.
    </p>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Mã / Link</th>
                    <th class="px-4 py-3">Hiệu lực</th>
                    <th class="px-4 py-3">Lượt</th>
                    <th class="px-4 py-3">%</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($codes as $code)
                    <tr class="border-b border-outline-variant/60 last:border-0 align-top"
                        x-data="{ copied: false, link: @js($code->registerUrl()) }">
                        <td class="px-4 py-3">
                            <div class="font-label-md font-bold tracking-wide text-on-surface">{{ $code->code }}</div>
                            @if ($code->label)
                                <div class="text-on-surface-variant">{{ $code->label }}</div>
                            @endif
                            <div class="mt-1 break-all font-label-sm text-label-sm text-primary">{{ $code->registerUrl() }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            @if ($code->starts_at)
                                Từ {{ $code->starts_at->format('d/m/Y H:i') }}<br>
                            @endif
                            @if ($code->expires_at)
                                Đến {{ $code->expires_at->format('d/m/Y H:i') }}
                            @else
                                Không hết hạn
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $code->use_count }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}
                        </td>
                        <td class="px-4 py-3">
                            {{ number_format($code->effectiveRateBps() / 100, 1) }}%
                        </td>
                        <td class="px-4 py-3">
                            {{ $code->is_active ? 'Bật' : 'Tắt' }}
                            @if (! $code->isCurrentlyValid())
                                <span class="text-warning">(không hiệu lực)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <button type="button"
                                class="font-label-md text-primary hover:underline"
                                @click="navigator.clipboard.writeText(link); copied = true; setTimeout(() => copied = false, 1500)"
                                x-text="copied ? 'Đã copy' : 'Copy link'">
                                Copy link
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-on-surface-variant">
                            Chưa được cấp mã mời. Liên hệ quản trị viên.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $codes->links() }}</div>
</x-layouts.partner>
