@php
    $toneClass = [
        'success' => 'bg-primary/10 text-primary',
        'warning' => 'bg-surface-container-high text-on-surface',
        'error' => 'bg-error/10 text-error',
        'neutral' => 'bg-surface-container-high text-on-surface-variant',
    ];
@endphp

<x-layouts.admin title="Cổng thanh toán">
    <x-admin.page-header title="Cổng thanh toán"
        description="Bật/tắt và cấu hình VNPay (mặc định), MoMo, ZaloPay. Giá trị lưu trong settings; env dùng làm mặc định khi chưa lưu.">
    </x-admin.page-header>

    @include('admin::billing._nav')

    <x-admin.flash />

    <div class="mb-6 grid gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 sm:grid-cols-2">
        <div>
            <p class="font-label-sm text-on-surface-variant">Return URL (mẫu)</p>
            <code class="mt-1 block break-all font-body-sm text-on-surface">{{ $returnUrl }}</code>
        </div>
        <div>
            <p class="font-label-sm text-on-surface-variant">IPN / Webhook (mẫu)</p>
            <code class="mt-1 block break-all font-body-sm text-on-surface">{{ $ipnUrl }}</code>
        </div>
    </div>

    <form method="post" action="{{ route('admin.billing.gateways.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-xl border border-outline-variant bg-surface p-5">
            <h2 class="font-title-md text-on-surface">Cổng mặc định</h2>
            <p class="mt-1 font-body-sm text-on-surface-variant">
                Dùng khi học viên không chọn cổng (checkout / API). Chỉ chọn cổng đã có adapter (Phase 1).
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ($implementedKeys as $key)
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-outline-variant px-3 py-2 font-label-md has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                        <input type="radio" name="default_gateway" value="{{ $key }}"
                            @checked(old('default_gateway', $defaultGateway) === $key)
                            class="text-primary focus:ring-primary">
                        {{ strtoupper($key) }}
                    </label>
                @endforeach
            </div>
            @error('default_gateway')
                <p class="mt-2 font-body-sm text-error">{{ $message }}</p>
            @enderror
        </section>

        @foreach ($gateways as $key => $gateway)
            <section class="rounded-xl border border-outline-variant bg-surface">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-outline-variant px-5 py-4">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 text-[28px] text-primary">{{ $gateway['icon'] }}</span>
                        <div>
                            <h2 class="font-title-md text-on-surface">{{ $gateway['label'] }}</h2>
                            <p class="mt-1 font-body-sm text-on-surface-variant">{{ $gateway['description'] }}</p>
                        </div>
                    </div>
                    <span @class([
                        'rounded-full px-3 py-1 font-label-sm',
                        $toneClass[$gateway['status']['tone']] ?? $toneClass['neutral'],
                    ])>
                        {{ $gateway['status']['label'] }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-2">
                    @foreach ($gateway['fields'] as $fieldKey => $field)
                        @php
                            $name = "gateways[{$key}][{$fieldKey}]";
                            $dot = "gateways.{$key}.{$fieldKey}";
                            $value = old($dot, $gateway['values'][$fieldKey] ?? ($field['default'] ?? null));
                            $isSecret = (bool) ($field['secret'] ?? false);
                            $masked = $gateway['secrets'][$fieldKey] ?? null;
                        @endphp

                        <div @class(['lg:col-span-2' => $field['type'] === 'boolean'])>
                            @if ($field['type'] === 'boolean')
                                <label class="flex items-center justify-between gap-4 rounded-lg border border-outline-variant bg-surface-container-lowest px-4 py-3">
                                    <span class="font-label-md text-on-surface">{{ $field['label'] }}</span>
                                    <input type="checkbox" name="{{ $name }}" value="1"
                                        @checked((bool) $value)
                                        class="size-5 rounded border-outline text-primary focus:ring-primary">
                                </label>
                            @else
                                <label for="gw_{{ $key }}_{{ $fieldKey }}"
                                    class="mb-1.5 block font-label-md text-on-surface">{{ $field['label'] }}</label>
                                <input id="gw_{{ $key }}_{{ $fieldKey }}"
                                    name="{{ $name }}"
                                    type="{{ $isSecret ? 'password' : 'text' }}"
                                    value="{{ $isSecret ? '' : $value }}"
                                    @if ($isSecret) autocomplete="new-password" placeholder="{{ $masked ? 'Đã cấu hình ('.$masked.') — để trống để giữ' : 'Chưa cấu hình' }}" @endif
                                    class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2.5 font-body-sm text-on-surface focus:ring-2 focus:ring-primary">
                            @endif
                            @error($dot)
                                <p class="mt-1.5 font-body-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                @unless ($gateway['implemented'])
                    <p class="border-t border-outline-variant px-5 py-3 font-body-sm text-on-surface-variant">
                        Adapter checkout chưa gắn — có thể lưu credential trước; học viên chưa chọn được cổng này.
                    </p>
                @endunless
            </section>
        @endforeach

        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-label-md font-bold text-on-primary hover:opacity-90">
                <span class="material-symbols-outlined text-[20px]">save</span>
                Lưu cấu hình
            </button>
        </div>
    </form>
</x-layouts.admin>
