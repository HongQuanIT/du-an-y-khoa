<?php

declare(strict_types=1);

namespace Modules\Partner\Support;

/**
 * Typed accessors for Admin `partner.*` system settings.
 *
 * Defaults match SettingController schema so missing DB rows still behave safely.
 */
final class PartnerSettings
{
    public const GROUP = 'partner';

    /** Days to keep invite ref after click until registration (A: click → ĐK). */
    public static function attributionWindowDays(): int
    {
        return max(1, (int) setting('partner.attribution_window_days', 7));
    }

    /** Default commission for new partners, in basis points (1000 = 10%). */
    public static function defaultCommissionRateBps(): int
    {
        $percent = (int) setting('partner.default_commission_rate_percent', 10);

        return max(0, min(10_000, $percent * 100));
    }

    public static function defaultCommissionRatePercent(): float
    {
        return round(self::defaultCommissionRateBps() / 100, 2);
    }

    /**
     * Auto-set invite expires_at when Admin leaves expiry empty.
     * 0 = do not auto-expire.
     */
    public static function defaultInviteExpiresDays(): int
    {
        return max(0, (int) setting('partner.default_invite_expires_days', 7));
    }

    /**
     * Default max_uses when Admin leaves blank. 0 = unlimited (null in DB).
     */
    public static function defaultInviteMaxUses(): ?int
    {
        $value = (int) setting('partner.default_invite_max_uses', 0);

        return $value > 0 ? $value : null;
    }

    public static function commissionOnRenewals(): bool
    {
        return (bool) setting('partner.commission_on_renewals', true);
    }

    /**
     * Only payments within N days after attribution earn commission.
     * 0 = no limit.
     */
    public static function firstPaymentWindowDays(): int
    {
        return max(0, (int) setting('partner.first_payment_window_days', 0));
    }

    public static function allowSelfReferral(): bool
    {
        return (bool) setting('partner.allow_self_referral', false);
    }

    /** Minimum payout amount in cents; 0 = no minimum. */
    public static function minPayoutCents(): int
    {
        return max(0, (int) setting('partner.min_payout_cents', 0));
    }

    /**
     * When false (default): first-touch — keep existing cookie/session ref.
     * When true: last-touch — newer valid ?ref= replaces older.
     */
    public static function overwriteAttribution(): bool
    {
        return (bool) setting('partner.overwrite_attribution', false);
    }

    public static function requireActivePartner(): bool
    {
        return (bool) setting('partner.require_active_partner', true);
    }

    /**
     * Seed / ensure defaults exist (safe for existing installs).
     *
     * @return array<string, array{value: mixed, type: string}>
     */
    public static function defaultRows(): array
    {
        return [
            'partner.attribution_window_days' => ['value' => 7, 'type' => 'integer'],
            'partner.default_commission_rate_percent' => ['value' => 10, 'type' => 'integer'],
            'partner.default_invite_expires_days' => ['value' => 7, 'type' => 'integer'],
            'partner.default_invite_max_uses' => ['value' => 0, 'type' => 'integer'],
            'partner.commission_on_renewals' => ['value' => true, 'type' => 'boolean'],
            'partner.first_payment_window_days' => ['value' => 0, 'type' => 'integer'],
            'partner.allow_self_referral' => ['value' => false, 'type' => 'boolean'],
            'partner.min_payout_cents' => ['value' => 0, 'type' => 'integer'],
            'partner.overwrite_attribution' => ['value' => false, 'type' => 'boolean'],
            'partner.require_active_partner' => ['value' => true, 'type' => 'boolean'],
        ];
    }
}
