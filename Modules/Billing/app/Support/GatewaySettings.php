<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use App\Services\SettingService;

/**
 * Catalog + runtime config for payment gateways.
 * DB settings (admin UI) override env/config defaults.
 */
final class GatewaySettings
{
    public const GROUP = 'billing_gateways';

    public function __construct(
        private readonly SettingService $settings,
    ) {}

    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     phase: int,
     *     implemented: bool,
     *     icon: string,
     *     fields: array<string, array{label: string, type: string, secret?: bool, default?: mixed, rules: list<mixed>}>
     * }>
     */
    public function catalog(): array
    {
        return [
            'fake' => [
                'label' => 'Fake Gateway',
                'description' => 'Cổng giả lập cho local/test — không gọi cổng thật.',
                'phase' => 1,
                'implemented' => true,
                'icon' => 'science',
                'fields' => [
                    'enabled' => [
                        'label' => 'Bật cổng Fake',
                        'type' => 'boolean',
                        'default' => (bool) config('billing.gateways.fake.enabled', true),
                        'rules' => ['sometimes', 'boolean'],
                    ],
                ],
            ],
            'vnpay' => [
                'label' => 'VNPay',
                'description' => 'Thanh toán prepaid QR/ATM/Visa qua VNPay (sandbox hoặc production).',
                'phase' => 1,
                'implemented' => true,
                'icon' => 'account_balance',
                'fields' => [
                    'enabled' => [
                        'label' => 'Bật VNPay',
                        'type' => 'boolean',
                        'default' => (string) config('billing.gateways.vnpay.tmn_code', '') !== '',
                        'rules' => ['sometimes', 'boolean'],
                    ],
                    'tmn_code' => [
                        'label' => 'Terminal Code (vnp_TmnCode)',
                        'type' => 'string',
                        'default' => (string) config('billing.gateways.vnpay.tmn_code', ''),
                        'rules' => ['nullable', 'string', 'max:64'],
                    ],
                    'hash_secret' => [
                        'label' => 'Hash Secret',
                        'type' => 'string',
                        'secret' => true,
                        'default' => (string) config('billing.gateways.vnpay.hash_secret', ''),
                        'rules' => ['nullable', 'string', 'max:255'],
                    ],
                    'url' => [
                        'label' => 'Payment URL',
                        'type' => 'string',
                        'default' => (string) config('billing.gateways.vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
                        'rules' => ['nullable', 'url', 'max:255'],
                    ],
                ],
            ],
            'momo' => [
                'label' => 'MoMo',
                'description' => 'Ví MoMo — cấu hình sẵn; adapter checkout sẽ bổ sung ở Phase 2.',
                'phase' => 2,
                'implemented' => false,
                'icon' => 'wallet',
                'fields' => [
                    'enabled' => [
                        'label' => 'Bật MoMo (khi sẵn sàng)',
                        'type' => 'boolean',
                        'default' => false,
                        'rules' => ['sometimes', 'boolean'],
                    ],
                    'partner_code' => [
                        'label' => 'Partner Code',
                        'type' => 'string',
                        'default' => (string) config('billing.gateways.momo.partner_code', ''),
                        'rules' => ['nullable', 'string', 'max:64'],
                    ],
                    'access_key' => [
                        'label' => 'Access Key',
                        'type' => 'string',
                        'secret' => true,
                        'default' => (string) config('billing.gateways.momo.access_key', ''),
                        'rules' => ['nullable', 'string', 'max:255'],
                    ],
                    'secret_key' => [
                        'label' => 'Secret Key',
                        'type' => 'string',
                        'secret' => true,
                        'default' => (string) config('billing.gateways.momo.secret_key', ''),
                        'rules' => ['nullable', 'string', 'max:255'],
                    ],
                    'endpoint' => [
                        'label' => 'API Endpoint',
                        'type' => 'string',
                        'default' => (string) config('billing.gateways.momo.endpoint', 'https://test-payment.momo.vn/v2/gateway/api/create'),
                        'rules' => ['nullable', 'url', 'max:255'],
                    ],
                ],
            ],
            'zalopay' => [
                'label' => 'ZaloPay',
                'description' => 'Ví ZaloPay — cấu hình sẵn; adapter checkout sẽ bổ sung ở Phase 2.',
                'phase' => 2,
                'implemented' => false,
                'icon' => 'payments',
                'fields' => [
                    'enabled' => [
                        'label' => 'Bật ZaloPay (khi sẵn sàng)',
                        'type' => 'boolean',
                        'default' => false,
                        'rules' => ['sometimes', 'boolean'],
                    ],
                    'app_id' => [
                        'label' => 'App ID',
                        'type' => 'string',
                        'default' => (string) config('billing.gateways.zalopay.app_id', ''),
                        'rules' => ['nullable', 'string', 'max:64'],
                    ],
                    'key1' => [
                        'label' => 'Key 1',
                        'type' => 'string',
                        'secret' => true,
                        'default' => (string) config('billing.gateways.zalopay.key1', ''),
                        'rules' => ['nullable', 'string', 'max:255'],
                    ],
                    'key2' => [
                        'label' => 'Key 2',
                        'type' => 'string',
                        'secret' => true,
                        'default' => (string) config('billing.gateways.zalopay.key2', ''),
                        'rules' => ['nullable', 'string', 'max:255'],
                    ],
                    'endpoint' => [
                        'label' => 'API Endpoint',
                        'type' => 'string',
                        'default' => (string) config('billing.gateways.zalopay.endpoint', 'https://sb-openapi.zalopay.vn/v2/create'),
                        'rules' => ['nullable', 'url', 'max:255'],
                    ],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public function implementedKeys(): array
    {
        return array_keys(array_filter(
            $this->catalog(),
            fn (array $gw): bool => $gw['implemented'] === true,
        ));
    }

    public function defaultGateway(): string
    {
        $stored = $this->settings->get(self::GROUP.'.default_gateway');
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return (string) config('billing.default_gateway', 'fake');
    }

    /**
     * @return array<string, mixed>
     */
    public function valuesFor(string $gateway): array
    {
        $catalog = $this->catalog()[$gateway] ?? null;
        if ($catalog === null) {
            return [];
        }

        $values = [];
        foreach ($catalog['fields'] as $fieldKey => $field) {
            $settingKey = self::GROUP.".{$gateway}_{$fieldKey}";
            $stored = $this->settings->get($settingKey);

            if ($stored === null) {
                $values[$fieldKey] = $field['default'] ?? ($field['type'] === 'boolean' ? false : '');
            } else {
                $values[$fieldKey] = $stored;
            }
        }

        return $values;
    }

    public function get(string $gateway, string $field, mixed $default = null): mixed
    {
        return $this->valuesFor($gateway)[$field] ?? $default;
    }

    public function isEnabled(string $gateway): bool
    {
        return (bool) $this->get($gateway, 'enabled', false);
    }

    public function isReady(string $gateway): bool
    {
        $meta = $this->catalog()[$gateway] ?? null;
        if ($meta === null || ! $meta['implemented'] || ! $this->isEnabled($gateway)) {
            return false;
        }

        return match ($gateway) {
            'fake' => true,
            'vnpay' => $this->stringFilled($gateway, 'tmn_code')
                && $this->stringFilled($gateway, 'hash_secret')
                && $this->stringFilled($gateway, 'url'),
            default => false,
        };
    }

    /** @return list<string> */
    public function availableForCheckout(): array
    {
        $ready = [];
        foreach ($this->implementedKeys() as $key) {
            if ($this->isReady($key)) {
                $ready[] = $key;
            }
        }

        return $ready;
    }

    /**
     * Status badge for admin UI.
     *
     * @return array{key: string, label: string, tone: string}
     */
    public function status(string $gateway): array
    {
        $meta = $this->catalog()[$gateway] ?? null;
        if ($meta === null) {
            return ['key' => 'unknown', 'label' => 'Không rõ', 'tone' => 'neutral'];
        }

        if (! $meta['implemented']) {
            return ['key' => 'coming_soon', 'label' => 'Phase '.$meta['phase'].' — sắp có', 'tone' => 'neutral'];
        }

        if (! $this->isEnabled($gateway)) {
            return ['key' => 'disabled', 'label' => 'Tắt', 'tone' => 'warning'];
        }

        if (! $this->isReady($gateway)) {
            return ['key' => 'incomplete', 'label' => 'Thiếu cấu hình', 'tone' => 'error'];
        }

        return ['key' => 'ready', 'label' => 'Sẵn sàng', 'tone' => 'success'];
    }

    /**
     * Persist admin form payload.
     *
     * @param  array<string, mixed>  $payload  keys: default_gateway, gateways.{name}.{field}
     */
    public function save(array $payload): void
    {
        $default = (string) ($payload['default_gateway'] ?? $this->defaultGateway());
        $implemented = $this->implementedKeys();

        if (! in_array($default, $implemented, true)) {
            $default = 'fake';
        }

        $this->settings->set(self::GROUP.'.default_gateway', $default, 'string');

        $gatewaysInput = is_array($payload['gateways'] ?? null) ? $payload['gateways'] : [];

        foreach ($this->catalog() as $gateway => $meta) {
            $input = is_array($gatewaysInput[$gateway] ?? null) ? $gatewaysInput[$gateway] : [];

            foreach ($meta['fields'] as $fieldKey => $field) {
                $settingKey = self::GROUP.".{$gateway}_{$fieldKey}";
                $type = $field['type'];

                if ($type === 'boolean') {
                    $this->settings->set($settingKey, (bool) ($input[$fieldKey] ?? false), 'boolean');

                    continue;
                }

                $value = $input[$fieldKey] ?? null;
                $isSecret = (bool) ($field['secret'] ?? false);

                // Blank secret → keep existing (or env default already in DB).
                if ($isSecret && ($value === null || $value === '')) {
                    continue;
                }

                $this->settings->set($settingKey, is_string($value) ? trim($value) : (string) $value, 'string');
            }
        }
    }

    /**
     * Masked preview for secret fields in the UI.
     */
    public function maskedSecret(string $gateway, string $field): ?string
    {
        $value = (string) $this->get($gateway, $field, '');
        if ($value === '') {
            return null;
        }

        $len = mb_strlen($value);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }

        return str_repeat('•', max(4, $len - 4)).mb_substr($value, -4);
    }

    private function stringFilled(string $gateway, string $field): bool
    {
        return trim((string) $this->get($gateway, $field, '')) !== '';
    }
}
