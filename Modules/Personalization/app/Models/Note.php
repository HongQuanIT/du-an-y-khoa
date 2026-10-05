<?php

declare(strict_types=1);

namespace Modules\Personalization\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Modules\Library\Models\Article;
use Modules\Media\Models\Media;
use Modules\QuestionBank\Models\Question;

/**
 * User-owned personal note. notable_type uses stable aliases (not FQCN).
 * Null notable_* means a free-standing notebook entry.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $notable_type
 * @property string|null $notable_id
 * @property string $body
 * @property string $body_html
 * @property string|null $color
 */
final class Note extends Model
{
    use SoftDeletes;

    public const TYPE_QUESTION = 'question';

    public const TYPE_ARTICLE = 'article';

    public const TYPE_DRUG = 'drug';

    public const TYPE_MEDIA = 'media';

    public const TYPE_VIDEO = 'video';

    /** @var list<string> */
    public const ATTACHED_TYPES = [
        self::TYPE_QUESTION,
        self::TYPE_ARTICLE,
        self::TYPE_DRUG,
        self::TYPE_MEDIA,
        self::TYPE_VIDEO,
    ];

    /** @var list<string> */
    public const COLORS = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6'];

    protected $fillable = [
        'user_id',
        'notable_type',
        'notable_id',
        'body',
        'body_html',
        'color',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFree(): bool
    {
        return $this->notable_type === null && $this->notable_id === null;
    }

    public function isAttached(): bool
    {
        return ! $this->isFree();
    }

    /**
     * @return Builder<self>
     */
    public static function forUserQuestion(int $userId, string $questionId): Builder
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('notable_type', self::TYPE_QUESTION)
            ->where('notable_id', $questionId);
    }

    /**
     * @return array{note: string, note_html: string}
     */
    public static function questionPayload(int $userId, string $questionId): array
    {
        $note = self::forUserQuestion($userId, $questionId)->first();

        if ($note === null) {
            return ['note' => '', 'note_html' => ''];
        }

        return [
            'note' => (string) $note->body,
            'note_html' => (string) $note->body_html,
        ];
    }

    /**
     * Map of question_id => note payload for a batch of questions.
     *
     * @param  list<string>  $questionIds
     * @return array<string, array{note: string, note_html: string}>
     */
    public static function questionPayloadMap(int $userId, array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        /** @var Collection<int, self> $notes */
        $notes = self::query()
            ->where('user_id', $userId)
            ->where('notable_type', self::TYPE_QUESTION)
            ->whereIn('notable_id', $questionIds)
            ->get();

        $map = [];
        foreach ($notes as $note) {
            $map[(string) $note->notable_id] = [
                'note' => (string) $note->body,
                'note_html' => (string) $note->body_html,
            ];
        }

        return $map;
    }

    public static function targetExists(string $type, string $id): bool
    {
        return match ($type) {
            self::TYPE_QUESTION => Question::query()->whereKey($id)->exists(),
            self::TYPE_ARTICLE => Article::query()->whereKey($id)->exists(),
            self::TYPE_MEDIA => Media::query()->whereKey($id)->exists(),
            self::TYPE_DRUG, self::TYPE_VIDEO => false,
            default => false,
        };
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            self::TYPE_QUESTION => 'Câu hỏi',
            self::TYPE_ARTICLE => 'Bài viết',
            self::TYPE_DRUG => 'Thuốc',
            self::TYPE_MEDIA => 'Hình ảnh',
            self::TYPE_VIDEO => 'Video',
            null => 'Sổ tay',
            default => 'Khác',
        };
    }
}
