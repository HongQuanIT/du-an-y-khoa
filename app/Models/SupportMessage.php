<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupportMessage extends Model
{
    protected $fillable = ['support_conversation_id', 'sender_id', 'sender_type', 'body', 'meta'];

    protected function casts(): array { return ['meta' => 'array']; }

    /** @return BelongsTo<SupportConversation, $this> */
    public function conversation(): BelongsTo { return $this->belongsTo(SupportConversation::class, 'support_conversation_id'); }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
}
