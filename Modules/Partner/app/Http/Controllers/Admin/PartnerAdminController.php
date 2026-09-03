<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Billing\Support\CurrentSubscription;
use Modules\Partner\Actions\CreatePartnerPayoutAction;
use Modules\Partner\Actions\MarkPartnerPayoutPaidAction;
use Modules\Partner\Enums\PartnerStatus;
use Modules\Partner\Models\Partner;
use Modules\Partner\Models\PartnerAttribution;
use Modules\Partner\Models\PartnerCommission;
use Modules\Partner\Models\PartnerInviteCode;
use Modules\Partner\Models\PartnerPayout;
use Modules\Partner\Support\PartnerPeriodFilter;
use Modules\Partner\Support\PartnerSettings;

final class PartnerAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::AdminPartnersManage);

        $period = PartnerPeriodFilter::fromRequest($request);
        $filters = PartnerPeriodFilter::listFilters($request);
        $now = Carbon::now();

        $query = Partner::query()
            ->with('user')
            ->withCount([
                'inviteCodes as active_codes_count' => function ($q) use ($now): void {
                    $q->where('is_active', true)
                        ->where(function ($inner) use ($now): void {
                            $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                        })
                        ->where(function ($inner) use ($now): void {
                            $inner->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
                        })
                        ->where(function ($inner): void {
                            $inner->whereNull('max_uses')
                                ->orWhereColumn('use_count', '<', 'max_uses');
                        });
                },
                'attributions as period_referrals_count' => function ($q) use ($period): void {
                    $q->whereBetween('attributed_at', [$period['from'], $period['to']]);
                },
            ])
            ->withSum([
                'commissions as period_gross_cents' => function ($q) use ($period): void {
                    $q->where('status', '!=', 'void')
                        ->whereBetween('created_at', [$period['from'], $period['to']]);
                },
            ], 'gross_cents')
            ->withSum([
                'commissions as period_commission_cents' => function ($q) use ($period): void {
                    $q->where('status', '!=', 'void')
                        ->whereBetween('created_at', [$period['from'], $period['to']]);
                },
            ], 'commission_cents');

        $this->applyPartnerListFilters($query, $filters);

        $sortColumn = match ($filters['sort']) {
            PartnerPeriodFilter::SORT_GROSS => 'period_gross_cents',
            PartnerPeriodFilter::SORT_REFERRALS => 'period_referrals_count',
            PartnerPeriodFilter::SORT_NAME => 'display_name',
            default => 'period_commission_cents',
        };

        if ($filters['sort'] === PartnerPeriodFilter::SORT_NAME) {
            $query->orderBy('display_name', $filters['dir']);
        } else {
            $direction = $filters['dir'] === 'asc' ? 'asc' : 'desc';
            $query->orderByRaw('COALESCE('.$sortColumn.', 0) '.$direction)
                ->orderByDesc('id');
        }

        $partnerIds = $this->filteredPartnerIds($filters);

        $totals = [
            'partners' => $partnerIds->count(),
            'gross_cents' => 0,
            'commission_cents' => 0,
            'referrals' => 0,
        ];

        if ($partnerIds->isNotEmpty()) {
            $totals['gross_cents'] = (int) PartnerCommission::query()
                ->whereIn('partner_id', $partnerIds)
                ->where('status', '!=', 'void')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->sum('gross_cents');
            $totals['commission_cents'] = (int) PartnerCommission::query()
                ->whereIn('partner_id', $partnerIds)
                ->where('status', '!=', 'void')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->sum('commission_cents');
            $totals['referrals'] = (int) PartnerAttribution::query()
                ->whereIn('partner_id', $partnerIds)
                ->whereBetween('attributed_at', [$period['from'], $period['to']])
                ->count();
        }

        $partners = $query->paginate(20)->withQueryString();

        $queryParams = array_filter([
            'preset' => $period['preset'],
            'from' => $period['preset'] === PartnerPeriodFilter::PRESET_CUSTOM ? $period['from']->toDateString() : null,
            'to' => $period['preset'] === PartnerPeriodFilter::PRESET_CUSTOM ? $period['to']->toDateString() : null,
            'status' => $filters['status'] !== 'all' ? $filters['status'] : null,
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
            'sort' => $filters['sort'],
            'dir' => $filters['dir'],
        ], fn ($v) => $v !== null && $v !== '');

        return view('partner::admin.index', [
            'partners' => $partners,
            'period' => $period,
            'filters' => $filters,
            'totals' => $totals,
            'queryParams' => $queryParams,
            'presets' => PartnerPeriodFilter::presets(),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Partner>  $query
     * @param  array{status: string, q: string, sort: string, dir: string}  $filters
     */
    private function applyPartnerListFilters($query, array $filters): void
    {
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('display_name', 'like', $term)
                    ->orWhereHas('user', function ($userQuery) use ($term): void {
                        $userQuery->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        }
    }

    /**
     * @param  array{status: string, q: string, sort: string, dir: string}  $filters
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function filteredPartnerIds(array $filters)
    {
        $query = Partner::query();
        $this->applyPartnerListFilters($query, $filters);

        return $query->pluck('id');
    }

    public function show(Partner $partner): View
    {
        $this->authorizePermission(Permission::AdminPartnersManage);

        $partner->load(['user', 'inviteCodes']);

        $attributions = $partner->attributions()
            ->with(['referredUser', 'inviteCode'])
            ->latest('attributed_at')
            ->paginate(20, ['*'], 'referrals_page');

        $rows = $attributions->getCollection()->map(function ($attribution) {
            $sub = CurrentSubscription::for($attribution->referredUser);

            return [
                'attribution' => $attribution,
                'plan_name' => $sub['plan_name'],
                'ends_at' => $sub['ends_at'],
            ];
        });
        $attributions->setCollection($rows);

        return view('partner::admin.show', [
            'partner' => $partner,
            'referrals' => $attributions,
        ]);
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $this->authorizePermission(Permission::AdminPartnersManage);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'default_commission_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:active,suspended'],
        ]);

        $partner->forceFill([
            'display_name' => $data['display_name'],
            'default_commission_rate_bps' => (int) round(((float) $data['default_commission_rate_percent']) * 100),
            'status' => PartnerStatus::from($data['status']),
        ])->save();

        return back()->with('status', 'Đã cập nhật cộng tác viên.');
    }

    public function storeCode(Request $request, Partner $partner): RedirectResponse
    {
        $this->authorizePermission(Permission::AdminPartnersManage);

        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('partner_invite_codes', 'code')],
            'label' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'commission_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        PartnerInviteCode::query()->create([
            'partner_id' => $partner->getKey(),
            'code' => $data['code'],
            'label' => $data['label'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $this->resolveInviteExpiresAt($data['expires_at'] ?? null),
            'max_uses' => $this->resolveInviteMaxUses($data['max_uses'] ?? null),
            'commission_rate_bps' => isset($data['commission_rate_percent']) && $data['commission_rate_percent'] !== null && $data['commission_rate_percent'] !== ''
                ? (int) round(((float) $data['commission_rate_percent']) * 100)
                : null,
            'is_active' => $request->boolean('is_active', true),
            'use_count' => 0,
        ]);

        return back()->with('status', 'Đã tạo mã mời cho CTV.');
    }

    public function updateCode(Request $request, Partner $partner, PartnerInviteCode $inviteCode): RedirectResponse
    {
        $this->authorizePermission(Permission::AdminPartnersManage);
        abort_unless($inviteCode->partner_id === $partner->getKey(), 404);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'commission_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $inviteCode->forceFill([
            'label' => $data['label'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'commission_rate_bps' => array_key_exists('commission_rate_percent', $data)
                && $data['commission_rate_percent'] !== null
                && $data['commission_rate_percent'] !== ''
                ? (int) round(((float) $data['commission_rate_percent']) * 100)
                : null,
        ])->save();

        return back()->with('status', 'Đã cập nhật mã mời.');
    }

    public function toggleCode(Partner $partner, PartnerInviteCode $inviteCode): RedirectResponse
    {
        $this->authorizePermission(Permission::AdminPartnersManage);
        abort_unless($inviteCode->partner_id === $partner->getKey(), 404);

        $inviteCode->forceFill([
            'is_active' => ! $inviteCode->is_active,
        ])->save();

        return back()->with('status', $inviteCode->is_active ? 'Đã bật mã.' : 'Đã tắt mã.');
    }

    public function payoutsIndex(): View
    {
        $this->authorizePermission(Permission::AdminPartnersPayouts);

        $payouts = PartnerPayout::query()
            ->with(['partner.user', 'creator'])
            ->latest('id')
            ->paginate(20);

        $partners = Partner::query()->with('user')->orderBy('display_name')->get();

        return view('partner::admin.payouts', [
            'payouts' => $payouts,
            'partners' => $partners,
        ]);
    }

    public function payoutsStore(Request $request, CreatePartnerPayoutAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::AdminPartnersPayouts);

        $data = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var Partner $partner */
        $partner = Partner::query()->findOrFail((int) $data['partner_id']);

        $action->handle(
            $partner,
            Carbon::parse($data['period_from'])->startOfDay(),
            Carbon::parse($data['period_to'])->endOfDay(),
            $this->actor(),
            $data['note'] ?? null,
        );

        return back()->with('status', 'Đã tạo kỳ chi trả và duyệt hoa hồng.');
    }

    public function payoutsMarkPaid(PartnerPayout $payout, MarkPartnerPayoutPaidAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::AdminPartnersPayouts);

        $action->handle($payout);

        return back()->with('status', 'Đã đánh dấu chi trả.');
    }

    private function resolveInviteExpiresAt(mixed $expiresAt): ?Carbon
    {
        if (is_string($expiresAt) && trim($expiresAt) !== '') {
            return Carbon::parse($expiresAt);
        }

        $days = PartnerSettings::defaultInviteExpiresDays();
        if ($days <= 0) {
            return null;
        }

        return Carbon::now()->addDays($days);
    }

    private function resolveInviteMaxUses(mixed $maxUses): ?int
    {
        if ($maxUses !== null && $maxUses !== '') {
            return (int) $maxUses;
        }

        return PartnerSettings::defaultInviteMaxUses();
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
