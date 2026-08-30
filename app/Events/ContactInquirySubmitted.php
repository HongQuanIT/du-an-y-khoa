<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Admin\Models\ContactInquiry;

final class ContactInquirySubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ContactInquiry $inquiry) {}
}
