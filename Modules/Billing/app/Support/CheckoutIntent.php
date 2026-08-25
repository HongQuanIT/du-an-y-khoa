<?php

declare(strict_types=1);

namespace Modules\Billing\Support;

use Illuminate\Http\Request;
use Modules\Billing\Models\PlanPrice;

/**
 * Purchase intent for guests: pricing CTA → register/login → /subscription/upgrade (variant A).
 */
final class CheckoutIntent
{
    public const QUERY_KEY = 'plan_price_id';

    public const SESSION_KEY = 'checkout_intent.plan_price_id';

    public static function capture(Request $request): void
    {
        $id = self::resolveFromRequest($request);
        if ($id === null) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, $id);
        $request->session()->put('url.intended', self::upgradeUrl($id));
    }

    public static function peek(Request $request): ?int
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public static function pull(Request $request): ?int
    {
        $id = $request->session()->pull(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public static function resolveSelectedPriceId(Request $request): ?int
    {
        $fromQuery = self::resolveFromRequest($request);
        if ($fromQuery !== null) {
            self::clear($request);

            return $fromQuery;
        }

        $fromSession = self::pull($request);
        if ($fromSession !== null && self::isPurchasable($fromSession)) {
            return $fromSession;
        }

        return null;
    }

    public static function isPurchasable(int $planPriceId): bool
    {
        return PlanPrice::query()
            ->public()
            ->whereKey($planPriceId)
            ->where('price_cents', '>', 0)
            ->exists();
    }

    public static function featuredPremiumPriceId(): ?int
    {
        $featured = PlanPrice::query()
            ->public()
            ->where('is_featured', true)
            ->where('price_cents', '>', 0)
            ->ordered()
            ->value('id');

        if ($featured !== null) {
            return (int) $featured;
        }

        $fallback = PlanPrice::query()
            ->public()
            ->where('price_cents', '>', 0)
            ->ordered()
            ->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    public static function upgradeUrl(?int $planPriceId = null): string
    {
        if ($planPriceId !== null && $planPriceId > 0) {
            return route('subscription.upgrade', [self::QUERY_KEY => $planPriceId]);
        }

        return route('subscription.upgrade');
    }

    public static function registerUrl(?int $planPriceId = null): string
    {
        if ($planPriceId !== null && $planPriceId > 0) {
            return route('register', [self::QUERY_KEY => $planPriceId]);
        }

        return route('register');
    }

    public static function loginUrl(?int $planPriceId = null): string
    {
        if ($planPriceId !== null && $planPriceId > 0) {
            return route('login', [self::QUERY_KEY => $planPriceId]);
        }

        return route('login');
    }

    public static function authQuery(?int $planPriceId): array
    {
        if ($planPriceId === null || $planPriceId <= 0) {
            return [];
        }

        return [self::QUERY_KEY => $planPriceId];
    }

    private static function resolveFromRequest(Request $request): ?int
    {
        $raw = $request->query(self::QUERY_KEY) ?? $request->input(self::QUERY_KEY);
        if (! is_numeric($raw)) {
            return null;
        }

        $id = (int) $raw;
        if ($id <= 0 || ! self::isPurchasable($id)) {
            return null;
        }

        return $id;
    }
}
