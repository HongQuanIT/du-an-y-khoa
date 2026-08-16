<?php

declare(strict_types=1);

namespace Modules\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Database\Factories\MediaFactory;
use Modules\Media\Support\Enums\MediaStatus;
use Modules\Media\Support\Enums\MediaType;

class Media extends Model
{
    public const DISK_EXTERNAL = 'external';

    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'uuid',
        'type',
        'disk',
        'path',
        'variants',
        'original_name',
        'mime',
        'size_bytes',
        'alt',
        'caption',
        'credit',
        'is_premium',
        'status',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'status' => MediaStatus::class,
            'variants' => 'array',
            'is_premium' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<MediaUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class, 'media_id');
    }

    /** @return HasMany<MediaJob, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(MediaJob::class, 'media_id');
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }

    public function isExternal(): bool
    {
        return $this->disk === self::DISK_EXTERNAL;
    }

    public function isPublicDisk(): bool
    {
        return $this->disk === 'public';
    }

    public function variantPath(?string $variant = null): string
    {
        if ($this->isExternal()) {
            return $this->path;
        }
        $variants = $this->variants ?? [];

        if ($variant !== null && isset($variants[$variant]['path'])) {
            return (string) $variants[$variant]['path'];
        }

        if (isset($variants['lg']['path'])) {
            return (string) $variants['lg']['path'];
        }

        if (isset($variants['original']['path'])) {
            return (string) $variants['original']['path'];
        }

        return $this->path;
    }

    public function publicUrl(?string $variant = null): ?string
    {
        if ($this->isExternal()) {
            return $this->path;
        }

        if (! $this->isPublicDisk() || $this->is_premium) {
            return null;
        }

        $path = $this->variantPath($variant);

        return Storage::disk($this->disk)->url($path);
    }

    public function thumbUrl(): ?string
    {
        return $this->publicUrl('thumb') ?? $this->publicUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPickerArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'alt' => $this->alt,
            'caption' => $this->caption,
            'credit' => $this->credit,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'is_premium' => $this->is_premium,
            'external' => $this->isExternal(),
            'ready' => $this->isReady(),
            'url' => $this->publicUrl('lg') ?? $this->publicUrl(),
            'thumb_url' => $this->thumbUrl(),
            'width' => data_get($this->variants, 'original.width'),
            'height' => data_get($this->variants, 'original.height'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
