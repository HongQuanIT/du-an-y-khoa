<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Billing\Support\GatewaySettings;

final class BillingGatewayController extends Controller
{
    public function index(GatewaySettings $gateways): View
    {
        $this->authorizePermission(Permission::BillingManage);

        $catalog = $gateways->catalog();
        $cards = [];

        foreach ($catalog as $key => $meta) {
            $cards[$key] = [
                ...$meta,
                'values' => $gateways->valuesFor($key),
                'status' => $gateways->status($key),
                'secrets' => collect($meta['fields'])
                    ->filter(fn (array $field): bool => (bool) ($field['secret'] ?? false))
                    ->mapWithKeys(fn (array $field, string $fieldKey) => [
                        $fieldKey => $gateways->maskedSecret($key, $fieldKey),
                    ])
                    ->all(),
            ];
        }

        return view('admin::billing.gateways.index', [
            'gateways' => $cards,
            'defaultGateway' => $gateways->defaultGateway(),
            'implementedKeys' => $gateways->implementedKeys(),
            'returnUrl' => url('/billing/return/{gateway}'),
            'ipnUrl' => url('/webhooks/billing/{provider}'),
        ]);
    }

    public function update(Request $request, GatewaySettings $gateways): RedirectResponse
    {
        $this->authorizePermission(Permission::BillingManage);

        $rules = [
            'default_gateway' => ['required', 'string', Rule::in($gateways->implementedKeys())],
            'gateways' => ['required', 'array'],
        ];

        foreach ($gateways->catalog() as $gateway => $meta) {
            foreach ($meta['fields'] as $fieldKey => $field) {
                $rules["gateways.{$gateway}.{$fieldKey}"] = $field['rules'];
            }
        }

        $validated = $request->validate($rules);

        // Checkboxes omitted when unchecked — normalize booleans before save.
        $normalized = $validated;
        foreach ($gateways->catalog() as $gateway => $meta) {
            foreach ($meta['fields'] as $fieldKey => $field) {
                if ($field['type'] !== 'boolean') {
                    continue;
                }
                $normalized['gateways'][$gateway][$fieldKey] = $request->boolean("gateways.{$gateway}.{$fieldKey}");
            }
        }

        $gateways->save($normalized);

        return redirect()
            ->route('admin.billing.gateways.index')
            ->with('status', 'Đã lưu cấu hình cổng thanh toán.');
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
