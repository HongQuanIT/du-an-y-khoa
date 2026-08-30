<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Admin\Enums\ContactInquiryStatus;
use Modules\Admin\Enums\ContactSubject;

class ContactInquiry extends Model
{
    protected $fillable = [
        'reference',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'user_id',
        'assigned_admin_id',
        'admin_notes',
        'ip_address',
        'user_agent',
        'read_at',
        'resolved_at',
        'resolved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject' => ContactSubject::class,
            'status' => ContactInquiryStatus::class,
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ContactInquiryStatus::New->value,
            ContactInquiryStatus::InProgress->value,
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at')
            ->where('status', ContactInquiryStatus::New->value);
    }

    public function markRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'CT-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public static function newCount(): int
    {
        return (int) self::query()
            ->where('status', ContactInquiryStatus::New->value)
            ->count();
    }
}
