<?php

declare(strict_types=1);

namespace Modules\Landing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Landing\Actions\SubmitContactInquiryAction;
use Modules\Landing\Http\Requests\StoreContactRequest;

final class ContactController extends Controller
{
    public function store(StoreContactRequest $request, SubmitContactInquiryAction $submit): RedirectResponse
    {
        // Silent success for honeypot hits — do not store or notify.
        if ($request->isHoneypotTriggered()) {
            return redirect()
                ->route('landing.contact')
                ->with('contact_success', true)
                ->with('contact_reference', 'CT-VERIFY');
        }

        $inquiry = $submit->handle(
            $request->payload(),
            $request,
            $request->user(),
        );

        return redirect()
            ->route('landing.contact')
            ->with('contact_success', true)
            ->with('contact_reference', $inquiry->reference);
    }
}
