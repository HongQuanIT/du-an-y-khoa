<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\QuestionBank\Enums\QuestionImportBatchStatus;

/**
 * @property string $id
 * @property int $uploaded_by
 * @property string $original_filename
 * @property string $disk_path
 * @property string $format
 * @property QuestionImportBatchStatus $status
 * @property list<string>|null $source_headers
 * @property array<string, int|string|null>|null $column_map
 * @property array<string, mixed>|null $stats
 * @property string|null $error_report_path
 */
class QuestionImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'uploaded_by',
        'original_filename',
        'disk_path',
        'format',
        'status',
        'source_headers',
        'column_map',
        'stats',
        'error_report_path',
        'committed_at',
    ];

    protected $casts = [
        'status' => QuestionImportBatchStatus::class,
        'source_headers' => 'array',
        'column_map' => 'array',
        'stats' => 'array',
        'committed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'import_batch_id');
    }
}
