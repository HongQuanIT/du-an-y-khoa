<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StudyPlanReminderLog extends Model
{
    protected $table = 'study_plan_reminder_logs';

    protected $fillable = [
        'user_id',
        'reminder_date',
        'task_count',
        'sent_at',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'task_count' => 'integer',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
