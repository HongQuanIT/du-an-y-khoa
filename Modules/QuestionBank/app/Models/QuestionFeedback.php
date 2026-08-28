<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuestionFeedback extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'question_feedback';

    protected $fillable = [
        'user_id',
        'question_id',
        'question_session_id',
        'question_option_id',
        'target',
        'category',
        'message',
        'status',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<QuestionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(QuestionSession::class, 'question_session_id');
    }

    /** @return BelongsTo<QuestionOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Chờ xử lý',
            self::STATUS_REVIEWING => 'Đang xem xét',
            self::STATUS_RESOLVED => 'Đã xử lý',
            self::STATUS_DISMISSED => 'Bỏ qua',
        ];
    }

    /** @return array<string, string> */
    public static function categoryLabels(): array
    {
        return [
            'grammar' => 'Ngữ pháp và chính tả',
            'incorrect' => 'Nội dung không chính xác',
            'missing' => 'Nội dung bị thiếu',
            'improvement' => 'Cải thiện nội dung',
            'technical' => 'Sự cố kỹ thuật',
            'media' => 'Phản hồi hình ảnh',
            'search' => 'Kết quả tìm kiếm',
            'other' => 'Khác',
        ];
    }

    /** @return array<string, string> */
    public static function targetLabels(): array
    {
        return [
            'question' => 'Câu hỏi',
            'knowledge' => 'Kiến thức',
            'answer' => 'Đáp án',
        ];
    }
}
