<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Concerns\AsAction;
use Illuminate\Http\Request;
use Modules\Admin\Enums\ContactInquiryStatus;
use Modules\Admin\Models\ContactInquiry;

final class UpdateContactInquiryAction
{
    use AsAction;

    /**
     * @param  array{
     *     status: string,
     *     assigned_admin_id?: int|null,
     *     admin_notes?: string|null,
     * }  $data
     */
    public function handle(ContactInquiry $inquiry, array $data, User $actor, ?Request $request = null): ContactInquiry
    {
        $before = [
            'status' => $inquiry->status->value,
            'assigned_admin_id' => $inquiry->assigned_admin_id,
            'admin_notes' => $inquiry->admin_notes,
        ];

        $status = ContactInquiryStatus::from($data['status']);
        $assignedAdminId = array_key_exists('assigned_admin_id', $data)
            ? $data['assigned_admin_id']
            : $inquiry->assigned_admin_id;

        $attributes = [
            'status' => $status,
            'assigned_admin_id' => $assignedAdminId,
            'admin_notes' => array_key_exists('admin_notes', $data)
                ? ($data['admin_notes'] !== null && $data['admin_notes'] !== '' ? $data['admin_notes'] : null)
                : $inquiry->admin_notes,
        ];

        if ($status === ContactInquiryStatus::Resolved && $inquiry->status !== ContactInquiryStatus::Resolved) {
            $attributes['resolved_at'] = now();
            $attributes['resolved_by'] = $actor->getKey();
        }

        if ($status !== ContactInquiryStatus::Resolved && $inquiry->status === ContactInquiryStatus::Resolved) {
            $attributes['resolved_at'] = null;
            $attributes['resolved_by'] = null;
        }

        if ($status === ContactInquiryStatus::InProgress && $assignedAdminId === null) {
            $attributes['assigned_admin_id'] = $actor->getKey();
        }

        $inquiry->forceFill($attributes)->save();

        Auditor::record(
            'admin.contact.update',
            actor: $actor,
            auditable: $inquiry,
            before: $before,
            after: [
                'status' => $inquiry->status->value,
                'assigned_admin_id' => $inquiry->assigned_admin_id,
                'admin_notes' => $inquiry->admin_notes,
            ],
            request: $request,
            metadata: [
                'reference' => $inquiry->reference,
            ],
        );

        return $inquiry->refresh();
    }
}
