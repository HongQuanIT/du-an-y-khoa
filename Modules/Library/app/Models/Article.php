<?php

declare(strict_types=1);

namespace Modules\Library\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use HasFactory;
    use HasUuids;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'slug',
        'title',
        'summary',
        'body',
        'is_free',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function toSearchableArray(): array
    {
        $plainBody = strip_tags(html_entity_decode((string) $this->body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plainBody = trim(preg_replace('/\s+/u', ' ', $plainBody) ?? $plainBody);

        return [
            'id' => $this->getKey(),
            'type' => $this->type,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'body' => $plainBody,
            'is_free' => $this->is_free,
            'is_published' => $this->is_published,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_published;
    }
}
