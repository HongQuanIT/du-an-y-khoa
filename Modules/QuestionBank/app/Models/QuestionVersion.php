<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'question_id',
        'version',
        'snapshot',
        'created_by',
        'event',
        'restored_from_version',
        'created_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'snapshot' => 'array',
        'restored_from_version' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
