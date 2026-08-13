<?php

declare(strict_types=1);

namespace Modules\Landing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Billing\Actions\ListPublicPlansAction;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Support\CurrentSubscription;
use Modules\Billing\Support\MoneyFormatter;

class LandingController extends Controller
{
    public function home(): View
    {
        return view('landing::home');
    }

    public function features(): View
    {
        return view('landing::features');
    }

    public function pricing(Request $request, ListPublicPlansAction $list): View
    {
        $catalog = $list->handle();
        $current = CurrentSubscription::for($request->user());

        $yearlyForAlpine = $catalog['yearlyPrices']->mapWithKeys(function (PlanPrice $price): array {
            $years = max(1, (int) round(($price->duration_days ?? 365) / 365));

            return [$years => [
                'id' => $price->id,
                'label' => $price->label,
                'total' => $price->price_cents,
                'perMonth' => $price->perMonthCents() ?? 0,
                'save' => $price->displaySavingsPercent() ?? 0,
                'listPrice' => $price->listPriceCents() ?? 0,
                'months' => (int) round(($price->duration_days ?? 365) / 30),
                'badge' => $price->badge_label,
                'cta' => $price->cta_label ?? 'Mua gói '.$price->label,
            ]];
        });

        return view('landing::pricing', [
            'free' => $catalog['free'],
            'premium' => $catalog['premium'],
            'monthlyPrice' => $catalog['monthlyPrice'],
            'yearlyForAlpine' => $yearlyForAlpine,
            'current' => $current,
        ]);
    }

    public function about(): View
    {
        return view('landing::about');
    }

    public function contact(): View
    {
        return view('landing::contact');
    }

    public function faq(): View
    {
        return view('landing::faq');
    }
}
