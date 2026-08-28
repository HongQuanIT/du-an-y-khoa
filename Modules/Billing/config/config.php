<?php

declare(strict_types=1);

return [
    'name' => 'Billing',

    'default_gateway' => env('BILLING_DEFAULT_GATEWAY', 'vnpay'),
    'currency' => env('BILLING_CURRENCY', 'VND'),
    'tax_rate' => (float) env('BILLING_TAX_RATE', 0),
    'checkout_ttl_minutes' => (int) env('BILLING_CHECKOUT_TTL_MINUTES', 60),
    'entitlement_cache_ttl' => (int) env('BILLING_ENTITLEMENT_CACHE_TTL', 300),

    'gateways' => [
        'fake' => [
            'enabled' => env('BILLING_FAKE_GATEWAY', false),
        ],
        'vnpay' => [
            'tmn_code' => env('VNPAY_TMN_CODE', ''),
            'hash_secret' => env('VNPAY_HASH_SECRET', ''),
            'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        ],
        'momo' => [
            'partner_code' => env('MOMO_PARTNER_CODE', ''),
            'access_key' => env('MOMO_ACCESS_KEY', ''),
            'secret_key' => env('MOMO_SECRET_KEY', ''),
            'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
        ],
        'zalopay' => [
            'app_id' => env('ZALOPAY_APP_ID', ''),
            'key1' => env('ZALOPAY_KEY1', ''),
            'key2' => env('ZALOPAY_KEY2', ''),
            'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/create'),
        ],
    ],
];
