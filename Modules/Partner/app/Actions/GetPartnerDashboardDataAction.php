<?php

declare(strict_types=1);

namespace Modules\Partner\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Billing\Support\MoneyFormatter;
use Modules\Partner\Enums\CommissionStatus;
use Modules\Partner\Enums\PayoutStatus;
use Modules\Partner\Models\Partner;
use Modules\Partner\Models\PartnerAttribution;
use Modules\Partner\Models\PartnerCommission;
use Modules\Partner\Models\PartnerInviteCode;
use Modules\Partner\Models\PartnerPayout;

final class GetPartnerDashboardDataAction
{
    use AsAction;

    private const CACHE_TTL_SECONDS = 180;

    private const CACHE_VERSION = 'v1';

    /** @return array<string, mixed> */
    public function handle(User $viewer, Partner $partner): array
    {
        $permissionSignature = sha1($viewer->getAllPermissions()->pluck('name')->sort()->implode('|'));
        $cacheKey = 'partner:dashboard:'.self::CACHE_VERSION.':'.$partner->getKey().':'.$permissionSignature;

        /** @var array<string, mixed> $data */
        $data = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn (): array => $this->build($viewer, $partner));
        $data['refreshed_at'] = Carbon::parse($data['refreshed_at']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function build(User $viewer, Partner $partner): array
    {
        $partnerId = (int) $partner->getKey();
        $today = Carbon::today();
        $currentStart = $today->copy()->subDays(29)->startOfDay();
        $previousStart = $today->copy()->subDays(59)->startOfDay();

        $referralTotal = PartnerAttribution::query()->where('partner_id', $partnerId)->count();
        $referralsCurrent = PartnerAttribution::query()->where('partner_id', $partnerId)->where('attributed_at', '>=', $currentStart)->count();
        $referralsPrevious = PartnerAttribution::query()
            ->where('partner_id', $partnerId)
            ->where('attributed_at', '>=', $previousStart)
            ->where('attributed_at', '<', $currentStart)
            ->count();

        $commissionTotals = PartnerCommission::query()
            ->where('partner_id', $partnerId)
            ->whereIn('status', [CommissionStatus::Pending->value, CommissionStatus::Approved->value, CommissionStatus::Paid->value])
            ->selectRaw('status, SUM(commission_cents) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($amount): int => (int) $amount);

        $pendingAmount = (int) ($commissionTotals[CommissionStatus::Pending->value] ?? 0);
        $approvedAmount = (int) ($commissionTotals[CommissionStatus::Approved->value] ?? 0);
        $paidAmount = (int) ($commissionTotals[CommissionStatus::Paid->value] ?? 0);

        $codes = PartnerInviteCode::query()
            ->where('partner_id', $partnerId)
            ->with('partner')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();
        $activeCodes = $codes->filter(fn (PartnerInviteCode $code): bool => $code->isCurrentlyValid());
        $expiringCodes = $activeCodes->filter(fn (PartnerInviteCode $code): bool => $code->expires_at !== null && $code->expires_at->lte(now()->addDays(7)));
        $exhaustedCodes = $codes->filter(fn (PartnerInviteCode $code): bool => $code->is_active && $code->max_uses !== null && $code->use_count >= $code->max_uses);

        return [
            'refreshed_at' => now()->toIso8601String(),
            'kpis' => $this->kpis($viewer, $referralTotal, $referralsCurrent, $referralsPrevious, $pendingAmount, $approvedAmount, $paidAmount),
            'charts' => $this->charts($viewer, $partnerId, $currentStart),
            'alerts' => $this->alerts($viewer, $activeCodes->count(), $expiringCodes->count(), $exhaustedCodes->count(), $pendingAmount, $approvedAmount),
            'active_codes' => $viewer->can('partner_code.view')
                ? $activeCodes->take(3)->map(fn (PartnerInviteCode $code): array => [
                    'code' => $code->code,
                    'label' => $code->label,
                    'url' => $code->registerUrl(),
                    'use_count' => $code->use_count,
                    'max_uses' => $code->max_uses,
                    'expires_at' => $code->expires_at?->toIso8601String(),
                    'rate_percent' => $code->effectiveRateBps() / 100,
                ])->values()->all()
                : [],
            'recent_activity' => $this->recentActivity($viewer, $partnerId),
            'quick_actions' => $this->quickActions($viewer),
            'primary_invite_url' => $viewer->can('partner_code.view') ? $activeCodes->first()?->registerUrl() : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function kpis(User $viewer, int $referralTotal, int $referralsCurrent, int $referralsPrevious, int $pendingAmount, int $approvedAmount, int $paidAmount): array
    {
        $kpis = [];
        if ($viewer->can('partner_referral.view')) {
            $kpis[] = $this->kpi('Người được mời', number_format($referralTotal), '30 ngày qua: '.number_format($referralsCurrent), 'group_add', $this->percentDelta($referralsCurrent, $referralsPrevious), $this->route('partner.referrals.index'));
        }
        if ($viewer->can('partner_commission.view')) {
            $kpis[] = $this->kpi('Hoa hồng chờ duyệt', MoneyFormatter::vnd($pendingAmount), 'Đang chờ đối soát', 'hourglass_top', null, $this->route('partner.commissions.index'), $pendingAmount > 0 ? 'warning' : null);
            $kpis[] = $this->kpi('Có thể chi trả', MoneyFormatter::vnd($approvedAmount), 'Đã duyệt, chưa chi', 'account_balance_wallet', null, $this->route('partner.commissions.index'), $approvedAmount > 0 ? 'warning' : null);
            $kpis[] = $this->kpi('Đã chi trả', MoneyFormatter::vnd($paidAmount), 'Tổng hoa hồng đã nhận', 'payments', null, $viewer->can('partner_payout.view') ? $this->route('partner.payouts.index') : null);
        }

        return $kpis;
    }

    /** @return list<array<string, mixed>> */
    private function charts(User $viewer, int $partnerId, Carbon $start): array
    {
        $labels = [];
        $dateKeys = [];
        for ($day = 0; $day < 30; $day++) {
            $date = $start->copy()->addDays($day);
            $labels[] = $date->format('d/m');
            $dateKeys[] = $date->toDateString();
        }

        $charts = [];
        if ($viewer->can('partner_referral.view')) {
            $counts = PartnerAttribution::query()->where('partner_id', $partnerId)->where('attributed_at', '>=', $start)->get(['attributed_at'])->countBy(fn (PartnerAttribution $item): string => $item->attributed_at->toDateString());
            $charts[] = [
                'id' => 'chart-partner-referrals', 'title' => 'Hiệu quả giới thiệu',
                'subtitle' => '30 ngày qua · Người đăng ký qua mã của bạn', 'type' => 'line', 'format' => 'number', 'labels' => $labels,
                'datasets' => [['label' => 'Người được mời', 'data' => array_map(fn (string $date): int => (int) ($counts[$date] ?? 0), $dateKeys), 'color' => '#0f766e']],
            ];
        }

        if ($viewer->can('partner_commission.view')) {
            $series = [CommissionStatus::Pending->value => [], CommissionStatus::Approved->value => [], CommissionStatus::Paid->value => []];
            PartnerCommission::query()
                ->where('partner_id', $partnerId)->where('created_at', '>=', $start)
                ->whereIn('status', array_keys($series))->get(['commission_cents', 'status', 'created_at'])
                ->each(function (PartnerCommission $commission) use (&$series): void {
                    $date = $commission->created_at?->toDateString();
                    if ($date !== null) {
                        $status = $commission->status->value;
                        $series[$status][$date] = ($series[$status][$date] ?? 0) + $commission->commission_cents;
                    }
                });
            $charts[] = [
                'id' => 'chart-partner-commissions', 'title' => 'Hoa hồng phát sinh',
                'subtitle' => '30 ngày qua · Theo trạng thái hiện tại', 'type' => 'bar', 'format' => 'vnd', 'labels' => $labels,
                'datasets' => [
                    $this->chartDataset('Chờ duyệt', '#b45309', $series[CommissionStatus::Pending->value], $dateKeys),
                    $this->chartDataset('Đã duyệt', '#0891b2', $series[CommissionStatus::Approved->value], $dateKeys),
                    $this->chartDataset('Đã chi', '#0f766e', $series[CommissionStatus::Paid->value], $dateKeys),
                ],
            ];
        }

        return $charts;
    }

    /** @param array<string, int> $values @param list<string> $dateKeys @return array<string, mixed> */
    private function chartDataset(string $label, string $color, array $values, array $dateKeys): array
    {
        return ['label' => $label, 'data' => array_map(fn (string $date): int => (int) ($values[$date] ?? 0), $dateKeys), 'color' => $color];
    }

    /** @return list<array<string, mixed>> */
    private function alerts(User $viewer, int $activeCodeCount, int $expiringCodeCount, int $exhaustedCodeCount, int $pendingAmount, int $approvedAmount): array
    {
        $alerts = [];
        if ($viewer->can('partner_code.view') && $activeCodeCount === 0) {
            $alerts[] = $this->alert('no-active-code', 'Mã mời', 'warning', 'Không có mã mời đang hiệu lực', 'Liên hệ quản trị viên để được cấp hoặc gia hạn mã.', $this->route('partner.codes.index'));
        } elseif ($viewer->can('partner_code.view') && $expiringCodeCount > 0) {
            $alerts[] = $this->alert('codes-expiring', 'Mã mời', 'warning', $expiringCodeCount.' mã sắp hết hạn', 'Các mã này sẽ hết hạn trong 7 ngày tới.', $this->route('partner.codes.index'));
        }
        if ($viewer->can('partner_code.view') && $exhaustedCodeCount > 0) {
            $alerts[] = $this->alert('codes-exhausted', 'Mã mời', 'info', $exhaustedCodeCount.' mã đã hết lượt dùng', 'Mã không thể ghi nhận thêm người được mời.', $this->route('partner.codes.index'));
        }
        if ($viewer->can('partner_commission.view') && $pendingAmount > 0) {
            $alerts[] = $this->alert('commission-pending', 'Hoa hồng', 'info', MoneyFormatter::vnd($pendingAmount).' đang chờ duyệt', 'Khoản này đang trong quá trình đối soát.', $this->route('partner.commissions.index'));
        }
        if ($viewer->can('partner_payout.view') && $approvedAmount > 0) {
            $alerts[] = $this->alert('commission-approved', 'Chi trả', 'warning', MoneyFormatter::vnd($approvedAmount).' đang chờ chi trả', 'Hoa hồng đã được duyệt và chưa hoàn tất chi trả.', $this->route('partner.payouts.index'));
        }
        if ($alerts === []) {
            $alerts[] = $this->alert('all-clear', 'Tổng quan', 'ok', 'Không có việc cần xử lý ngay', 'Mã mời và hoa hồng của bạn đang ở trạng thái ổn định.', null);
        }

        return $alerts;
    }

    /** @return list<array<string, mixed>> */
    private function recentActivity(User $viewer, int $partnerId): array
    {
        $items = collect();
        if ($viewer->can('partner_referral.view')) {
            PartnerAttribution::query()->where('partner_id', $partnerId)->with(['referredUser:id,name', 'inviteCode:id,code'])->latest('attributed_at')->limit(5)->get()
                ->each(function (PartnerAttribution $attribution) use ($items): void {
                    $items->push(['id' => 'referral-'.$attribution->getKey(), 'icon' => 'person_add', 'title' => ($attribution->referredUser?->name ?? 'Người dùng mới').' đã đăng ký', 'description' => 'Qua mã '.($attribution->inviteCode?->code ?? '—'), 'occurred_at' => $attribution->attributed_at?->toIso8601String(), 'href' => $this->route('partner.referrals.index')]);
                });
        }
        if ($viewer->can('partner_commission.view')) {
            PartnerCommission::query()->where('partner_id', $partnerId)->latest('id')->limit(5)->get()
                ->each(function (PartnerCommission $commission) use ($items): void {
                    $items->push(['id' => 'commission-'.$commission->getKey(), 'icon' => 'payments', 'title' => 'Hoa hồng '.MoneyFormatter::vnd($commission->commission_cents), 'description' => $commission->status->label(), 'occurred_at' => $commission->created_at?->toIso8601String(), 'href' => $this->route('partner.commissions.index')]);
                });
        }
        if ($viewer->can('partner_payout.view')) {
            PartnerPayout::query()->where('partner_id', $partnerId)->where('status', PayoutStatus::Paid->value)->latest('paid_at')->limit(5)->get()
                ->each(function (PartnerPayout $payout) use ($items): void {
                    $items->push(['id' => 'payout-'.$payout->getKey(), 'icon' => 'account_balance_wallet', 'title' => 'Đã chi '.MoneyFormatter::vnd($payout->amount_cents), 'description' => 'Kỳ '.$payout->period_from->format('d/m').' – '.$payout->period_to->format('d/m/Y'), 'occurred_at' => $payout->paid_at?->toIso8601String(), 'href' => $this->route('partner.payouts.index')]);
                });
        }

        return $items->filter(fn (array $item): bool => is_string($item['occurred_at']))->sortByDesc('occurred_at')->take(8)->values()->all();
    }

    /** @return list<array{label: string, icon: string, href: string}> */
    private function quickActions(User $viewer): array
    {
        $actions = [];
        $definitions = [
            ['partner_code.view', 'Xem mã mời', 'link', 'partner.codes.index'],
            ['partner_referral.view', 'Người được mời', 'group', 'partner.referrals.index'],
            ['partner_commission.view', 'Xem hoa hồng', 'payments', 'partner.commissions.index'],
            ['partner_payout.view', 'Lịch sử chi trả', 'account_balance_wallet', 'partner.payouts.index'],
        ];
        foreach ($definitions as [$permission, $label, $icon, $route]) {
            if ($viewer->can($permission) && Route::has($route)) {
                $actions[] = ['label' => $label, 'icon' => $icon, 'href' => route($route)];
            }
        }

        return $actions;
    }

    /** @return array<string, mixed> */
    private function kpi(string $label, string $value, string $hint, string $icon, ?float $delta, ?string $href, ?string $severity = null): array
    {
        return compact('label', 'value', 'hint', 'icon', 'delta', 'href', 'severity') + ['delta_suffix' => '%', 'delta_mode' => 'percent'];
    }

    /** @return array<string, mixed> */
    private function alert(string $id, string $category, string $severity, string $title, string $message, ?string $href): array
    {
        return compact('id', 'category', 'severity', 'title', 'message', 'href');
    }

    private function percentDelta(int $current, int $previous): ?float
    {
        return $previous === 0 ? ($current === 0 ? 0.0 : null) : round((($current - $previous) / $previous) * 100, 1);
    }

    private function route(string $name): ?string
    {
        return Route::has($name) ? route($name) : null;
    }
}
