<?php

declare(strict_types=1);

namespace Modules\Billing\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\DTO\CheckoutRequest;
use Modules\Billing\DTO\CheckoutResult;
use Modules\Billing\DTO\WebhookPayload;
use Modules\Billing\Support\GatewaySettings;
use RuntimeException;

/**
 * VNPay redirect payment (prepaid). Amount is sent in VND (integer, no decimals).
 *
 * @see https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html
 */
final class VNPayGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly GatewaySettings $gatewaySettings,
    ) {}

    public function name(): string
    {
        return 'vnpay';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResult
    {
        $tmnCode = (string) $this->gatewaySettings->get('vnpay', 'tmn_code', '');
        $hashSecret = (string) $this->gatewaySettings->get('vnpay', 'hash_secret', '');
        $payUrl = (string) $this->gatewaySettings->get('vnpay', 'url', '');

        if ($tmnCode === '' || $hashSecret === '' || $payUrl === '') {
            throw new RuntimeException('VNPay chưa được cấu hình (Admin → Cổng thanh toán hoặc VNPAY_*).');
        }

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $request->amountCents * 100, // VNPay: amount × 100
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $request->orderId,
            'vnp_OrderInfo' => $request->description,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $request->returnUrl,
            'vnp_IpAddr' => $request->buyerIp !== '' ? $request->buyerIp : '127.0.0.1',
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_ExpireDate' => now()->addMinutes((int) config('billing.checkout_ttl_minutes', 60))->format('YmdHis'),
        ];

        ksort($params);
        $hashData = $this->buildHashData($params);
        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, $hashSecret);

        $redirectUrl = $payUrl.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return new CheckoutResult(
            redirectUrl: $redirectUrl,
            gatewayOrderId: $request->orderId,
            metadata: ['provider' => 'vnpay'],
        );
    }

    public function verifyWebhook(Request $request): WebhookPayload
    {
        $hashSecret = (string) $this->gatewaySettings->get('vnpay', 'hash_secret', '');
        /** @var array<string, mixed> $input */
        $input = $request->query() !== [] ? $request->query() : $request->all();

        $secureHash = (string) ($input['vnp_SecureHash'] ?? '');
        unset($input['vnp_SecureHash'], $input['vnp_SecureHashType']);

        $filtered = [];
        foreach ($input as $key => $value) {
            if (str_starts_with((string) $key, 'vnp_') && $value !== null && $value !== '') {
                $filtered[(string) $key] = $value;
            }
        }

        ksort($filtered);
        $hashData = $this->buildHashData($filtered);
        $calculated = hash_hmac('sha512', $hashData, $hashSecret);

        if (! hash_equals($calculated, $secureHash)) {
            Log::warning('billing.vnpay.invalid_signature', ['txn' => $filtered['vnp_TxnRef'] ?? null]);
            throw new RuntimeException('Chữ ký VNPay không hợp lệ.');
        }

        $responseCode = (string) ($filtered['vnp_ResponseCode'] ?? '');
        $txnRef = (string) ($filtered['vnp_TxnRef'] ?? '');
        $amount = (int) round(((int) ($filtered['vnp_Amount'] ?? 0)) / 100); // back to VND
        $transactionNo = isset($filtered['vnp_TransactionNo']) ? (string) $filtered['vnp_TransactionNo'] : null;
        $eventId = $txnRef.':'.($transactionNo ?? $responseCode);

        return new WebhookPayload(
            eventId: $eventId,
            eventType: $responseCode === '00' ? 'payment.succeeded' : 'payment.failed',
            orderId: $txnRef,
            success: $responseCode === '00',
            amountCents: $amount,
            providerPaymentId: $transactionNo,
            raw: $filtered,
        );
    }

    public function supportsRecurring(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function buildHashData(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key.'='.urlencode((string) $value);
        }

        return implode('&', $parts);
    }
}
