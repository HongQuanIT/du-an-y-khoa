<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\ReportScheduleFrequency;
use Modules\Admin\Models\ReportSchedule;
use Modules\Admin\Support\AdminReportCatalog;

final class SaveReportScheduleAction
{
    use AsAction;

    /**
     * @param  array{
     *     category_slug: string,
     *     report_slug: string,
     *     range_key?: string,
     *     frequency: string,
     *     weekday?: int|null,
     *     day_of_month?: int|null,
     *     send_time: string,
     *     recipients?: string|list<string>,
     *     send_email?: bool|string|int,
     * }  $input
     */
    public function handle(User $actor, array $input): ReportSchedule
    {
        $validated = Validator::make($input, [
            'category_slug' => ['required', 'string', 'max:40'],
            'report_slug' => ['required', 'string', 'max:40'],
            'range_key' => ['nullable', 'string', Rule::in(['7d', '30d', '90d', '365d'])],
            'frequency' => ['required', Rule::enum(ReportScheduleFrequency::class)],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'day_of_month' => ['nullable', 'integer', 'between:1,28'],
            'send_time' => ['required', 'date_format:H:i'],
            'recipients' => ['nullable'],
            'send_email' => ['sometimes', 'boolean'],
        ])->validate();

        $match = AdminReportCatalog::findReport($validated['category_slug'], $validated['report_slug']);
        if ($match === null) {
            throw ValidationException::withMessages([
                'report_slug' => 'Báo cáo không tồn tại.',
            ]);
        }

        $permission = $match['category']['permission'];
        if ($permission !== null && ! $actor->can($permission->value)) {
            abort(403);
        }

        $frequency = ReportScheduleFrequency::from($validated['frequency']);
        $sendEmail = array_key_exists('send_email', $input)
            ? filter_var($input['send_email'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $recipients = $this->normalizeRecipients($validated['recipients'] ?? []);

        if ($sendEmail && $recipients === []) {
            throw ValidationException::withMessages([
                'recipients' => 'Nhập ít nhất một email hợp lệ khi bật gửi email.',
            ]);
        }

        $weekday = $frequency === ReportScheduleFrequency::Weekly
            ? (int) ($validated['weekday'] ?? 1)
            : null;
        $dayOfMonth = $frequency === ReportScheduleFrequency::Monthly
            ? (int) ($validated['day_of_month'] ?? 1)
            : null;

        if ($frequency === ReportScheduleFrequency::Weekly && ($weekday < 1 || $weekday > 7)) {
            throw ValidationException::withMessages([
                'weekday' => 'Chọn ngày trong tuần.',
            ]);
        }

        $schedule = ReportSchedule::query()->create([
            'created_by' => $actor->id,
            'category_slug' => $validated['category_slug'],
            'report_slug' => $validated['report_slug'],
            'range_key' => $validated['range_key'] ?? '30d',
            'frequency' => $frequency,
            'weekday' => $weekday,
            'day_of_month' => $dayOfMonth,
            'send_time' => $validated['send_time'].':00',
            'recipients' => $recipients,
            'is_active' => true,
            'send_email' => $sendEmail,
        ]);

        $schedule->refreshNextRunAt();

        return $schedule->fresh() ?? $schedule;
    }

    /**
     * @param  string|list<string>  $raw
     * @return list<string>
     */
    private function normalizeRecipients(string|array $raw): array
    {
        $parts = is_array($raw)
            ? $raw
            : (preg_split('/[\s,;]+/', $raw) ?: []);

        $emails = [];
        foreach ($parts as $part) {
            $email = strtolower(trim((string) $part));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[$email] = $email;
        }

        return array_values($emails);
    }
}
