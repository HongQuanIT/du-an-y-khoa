<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Billing\Actions\ListPublicPlansAction;
use Modules\Billing\Support\CurrentSubscription;

final class SubscriptionController extends Controller
{
    public function show(Request $request, ListPublicPlansAction $list): View
    {
        $catalog = $list->handle();
        $current = CurrentSubscription::for($request->user());

        return view('billing::subscription', [
            'current' => $current,
            'plans' => $catalog['plans'],
            'free' => $catalog['free'],
            'premium' => $catalog['premium'],
        ]);
    }
}
