<?php

declare(strict_types=1);

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Media\Support\Enums\MediaJobStatus;

class MediaJob extends Model
{
    protected $fillable = [
        'media_id',
        'type',
        'status',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MediaJobStatus::class,
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
