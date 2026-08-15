<?php

declare(strict_types=1);

namespace Modules\Search\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class SearchDocument extends Model
{
    use HasFactory;
    use HasUuids;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'source_type',
        'source_id',
        'scope',
        'type',
        'title',
        'summary',
        'body',
        'url',
        'is_free',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function searchableAs(): string
    {
        return 'global-search';
    }

    public function toSearchableArray(): array
    {
        $plainBody = strip_tags(html_entity_decode((string) $this->body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plainBody = trim(preg_replace('/\s+/u', ' ', $plainBody) ?? $plainBody);

        return [
            'id' => $this->getKey(),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'scope' => $this->scope,
            'type' => $this->type,
            'title' => $this->title,
            'summary' => $this->summary,
            'body' => $plainBody,
            'url' => $this->url,
            'is_free' => $this->is_free,
            'is_published' => $this->is_published,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_published;
    }
}
