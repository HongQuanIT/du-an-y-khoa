<?php

declare(strict_types=1);

namespace Modules\Analytics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;

/**
 * Rolled-up accuracy per medical taxonomy node, feeding weak topics and adaptive replanning.
 *
 * @property int $id
 * @property int $user_id
 * @property int $medical_taxonomy_node_id
 * @property int $attempts
 * @property int $correct
 * @property float $correct_rate
 * @property int $mastery_level
 * @property Carbon|null $last_activity_at
 * @property array<string, mixed>|null $trend
 */
class TopicMastery extends Model
{
    protected $table = 'topic_mastery';

    protected $fillable = [
        'user_id',
        'medical_taxonomy_node_id',
        'attempts',
        'correct',
        'correct_rate',
        'mastery_level',
        'last_activity_at',
        'trend',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'correct' => 'integer',
        'correct_rate' => 'float',
        'mastery_level' => 'integer',
        'last_activity_at' => 'datetime',
        'trend' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MedicalTaxonomyNode, $this> */
    public function medicalTaxonomyNode(): BelongsTo
    {
        return $this->belongsTo(MedicalTaxonomyNode::class);
    }
}
