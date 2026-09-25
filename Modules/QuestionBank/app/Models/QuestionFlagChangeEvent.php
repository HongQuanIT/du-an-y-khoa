<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\QuestionBank\Enums\ReviewerFlag;

class QuestionFlagChangeEvent extends Model
{
    public const ACK_TEXT_VERSION = 'v1-2026-09-24';

    public const ACK_TEXT = 'Yêu cầu rà soát kỹ lại câu hỏi, tự chịu trách nhiệm với quyết định thay đổi cờ. Nếu bạn cho là cờ trước đó của mình là đúng thì không nên thay đổi.';

    protected $fillable = [
        'question_id',
        'review_cycle',
        'reviewer_id',
        'from_flag',
        'to_flag',
        'note',
        'reaffirmed',
        'responsibility_acked',
        'ack_text_version',
    ];

    protected $casts = [
        'from_flag' => ReviewerFlag::class,
        'to_flag' => ReviewerFlag::class,
        'review_cycle' => 'integer',
        'reaffirmed' => 'boolean',
        'responsibility_acked' => 'boolean',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
