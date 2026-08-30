<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\Staff;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\UpdateContactInquiryAction;
use Modules\Admin\Enums\ContactInquiryStatus;
use Modules\Admin\Enums\ContactSubject;
use Modules\Admin\Models\ContactInquiry;

final class ContactInquiryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::ContactView);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'subject' => (string) $request->query('subject', ''),
            'assigned' => (string) $request->query('assigned', ''),
        ];

        $query = ContactInquiry::query()
            ->with(['user:id,name,email', 'assignedAdmin:id,name'])
            ->latest();

        if ($filters['q'] !== '') {
            $keyword = $filters['q'];
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('reference', 'like', "%{$keyword}%")
                    ->orWhere('message', 'like', "%{$keyword}%");
            });
        }

        if (ContactInquiryStatus::tryFrom($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (ContactSubject::tryFrom($filters['subject'])) {
            $query->where('subject', $filters['subject']);
        }

        if ($filters['assigned'] === 'me') {
            $query->where('assigned_admin_id', $request->user()?->getKey());
        } elseif ($filters['assigned'] === 'unassigned') {
            $query->whereNull('assigned_admin_id');
        }

        $statusCounts = ContactInquiry::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        return view('admin::contacts.index', [
            'inquiries' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => ContactInquiryStatus::cases(),
            'subjects' => ContactSubject::cases(),
            'statusCounts' => $statusCounts,
            'openCount' => ContactInquiry::query()->open()->count(),
            'newCount' => ContactInquiry::newCount(),
        ]);
    }

    public function show(ContactInquiry $contact): View
    {
        $this->authorizePermission(Permission::ContactView);

        $contact->load(['user:id,name,email', 'assignedAdmin:id,name,email', 'resolver:id,name']);
        $contact->markRead();

        $staff = User::query()
            ->role(Staff::roleValues())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin::contacts.show', [
            'inquiry' => $contact,
            'statuses' => ContactInquiryStatus::cases(),
            'staff' => $staff,
            'canManage' => $this->actor()->can(Permission::ContactManage->value),
        ]);
    }

    public function update(Request $request, ContactInquiry $contact, UpdateContactInquiryAction $update): RedirectResponse
    {
        $this->authorizePermission(Permission::ContactManage);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(ContactInquiryStatus::class)],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:users,id'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'status.required' => 'Vui lòng chọn trạng thái.',
        ]);

        if (! empty($validated['assigned_admin_id'])) {
            $assignee = User::query()->findOrFail((int) $validated['assigned_admin_id']);
            abort_unless(Staff::isStaff($assignee), 422, 'Người được gán phải là nhân sự.');
        }

        $update->handle(
            $contact,
            [
                'status' => $validated['status'],
                'assigned_admin_id' => $validated['assigned_admin_id'] ?? null,
                'admin_notes' => $validated['admin_notes'] ?? null,
            ],
            $this->actor(),
            $request,
        );

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('status', 'Đã cập nhật liên hệ '.$contact->reference.'.');
    }

    public function claim(Request $request, ContactInquiry $contact, UpdateContactInquiryAction $update): RedirectResponse
    {
        $this->authorizePermission(Permission::ContactManage);

        $status = $contact->status === ContactInquiryStatus::New
            ? ContactInquiryStatus::InProgress
            : $contact->status;

        $update->handle(
            $contact,
            [
                'status' => $status->value,
                'assigned_admin_id' => $this->actor()->getKey(),
                'admin_notes' => $contact->admin_notes,
            ],
            $this->actor(),
            $request,
        );

        return redirect()
            ->route('admin.contacts.show', $contact)
            ->with('status', 'Bạn đã nhận xử lý liên hệ này.');
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
