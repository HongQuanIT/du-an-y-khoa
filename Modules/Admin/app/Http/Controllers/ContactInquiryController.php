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
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $this->authorizePermission('contact.view');

        $rawStatus = $request->query('status');
        $rawSubject = $request->query('subject');
        $rawAssigned = $request->query('assigned');

        $statusList = collect(is_array($rawStatus) ? $rawStatus : ($rawStatus !== null && $rawStatus !== '' ? explode(',', (string) $rawStatus) : []))
            ->map(fn ($val) => trim((string) $val))
            ->filter(fn ($val) => ContactInquiryStatus::tryFrom($val) !== null)
            ->unique()
            ->values()
            ->all();

        $subjectList = collect(is_array($rawSubject) ? $rawSubject : ($rawSubject !== null && $rawSubject !== '' ? explode(',', (string) $rawSubject) : []))
            ->map(fn ($val) => trim((string) $val))
            ->filter(fn ($val) => ContactSubject::tryFrom($val) !== null)
            ->unique()
            ->values()
            ->all();

        $assignedList = collect(is_array($rawAssigned) ? $rawAssigned : ($rawAssigned !== null && $rawAssigned !== '' ? explode(',', (string) $rawAssigned) : []))
            ->map(fn ($val) => trim((string) $val))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $statusList,
            'subject' => $subjectList,
            'assigned' => $assignedList,
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

        if (! empty($statusList)) {
            $query->whereIn('status', $statusList);
        }

        if (! empty($subjectList)) {
            $query->whereIn('subject', $subjectList);
        }

        if (! empty($assignedList)) {
            $hasMe = in_array('me', $assignedList, true);
            $hasUnassigned = in_array('unassigned', $assignedList, true);
            $userKey = $request->user()?->getKey();

            $query->where(function ($builder) use ($hasMe, $hasUnassigned, $userKey): void {
                if ($hasMe && $hasUnassigned) {
                    $builder->where('assigned_admin_id', $userKey)
                        ->orWhereNull('assigned_admin_id');
                } elseif ($hasMe) {
                    $builder->where('assigned_admin_id', $userKey);
                } elseif ($hasUnassigned) {
                    $builder->whereNull('assigned_admin_id');
                }
            });
        }

        $statusCounts = ContactInquiry::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $statuses = ContactInquiryStatus::cases();
        $statusTone = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->tone()]
        )->all();

        $inquiries = $query->paginate(20)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'table_html' => view('admin::contacts.partials.table', [
                    'inquiries' => $inquiries,
                    'statusTone' => $statusTone,
                ])->render(),
                'statusCounts' => $statusCounts,
                'openCount' => ContactInquiry::query()->open()->count(),
                'newCount' => ContactInquiry::newCount(),
                'total' => $inquiries->total(),
            ]);
        }

        return view('admin::contacts.index', [
            'inquiries' => $inquiries,
            'filters' => $filters,
            'statuses' => $statuses,
            'subjects' => ContactSubject::cases(),
            'statusCounts' => $statusCounts,
            'statusTone' => $statusTone,
            'openCount' => ContactInquiry::query()->open()->count(),
            'newCount' => ContactInquiry::newCount(),
        ]);
    }

    public function show(ContactInquiry $contact): View
    {
        $this->authorizePermission('contact.view');

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
            'canManage' => $this->actor()->canAny([
                'contact.update',

            ]),
        ]);
    }

    public function update(Request $request, ContactInquiry $contact, UpdateContactInquiryAction $update): RedirectResponse
    {
        $this->authorizePermission('contact.update');

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(ContactInquiryStatus::class)],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:users,id'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'status.required' => 'Vui lòng chọn trạng thái.',
        ]);

        if ((int) ($validated['assigned_admin_id'] ?? 0) !== (int) $contact->assigned_admin_id) {
            $this->authorizePermission('contact.update');
        }

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
        $this->authorizePermission('contact.update');

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

    private function authorizePermission(string|Permission ...$permissions): void
    {
        $names = array_map(
            static fn (string|Permission $permission): string => $permission instanceof Permission
                ? $permission->value
                : $permission,
            $permissions,
        );

        abort_unless($this->actor()->canAny($names), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
