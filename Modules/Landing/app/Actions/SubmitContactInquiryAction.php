<?php

declare(strict_types=1);

namespace Modules\Landing\Actions;

use App\Events\ContactInquirySubmitted;
use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Http\Request;
use Modules\Admin\Enums\ContactInquiryStatus;
use Modules\Admin\Enums\ContactSubject;
use Modules\Admin\Models\ContactInquiry;

final class SubmitContactInquiryAction
{
    use AsAction;

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     subject: string,
     *     message: string,
     * }  $data
     */
    public function handle(array $data, Request $request, ?User $user = null): ContactInquiry
    {
        $inquiry = ContactInquiry::query()->create([
            'reference' => ContactInquiry::generateReference(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'subject' => ContactSubject::from($data['subject']),
            'message' => $data['message'],
            'status' => ContactInquiryStatus::New,
            'user_id' => $user?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        ContactInquirySubmitted::dispatch($inquiry);

        return $inquiry;
    }
}
