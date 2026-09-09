<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LearnerProfile extends Model
{
    protected $fillable = [
        'user_id', 'registration_method', 'country_id', 'administrative_unit_id', 'institution_id',
        'profession_id', 'education_stage_id', 'onboarding_completed_at',
        'marketing_consent_at', 'utm_source', 'utm_medium', 'utm_campaign',
        'utm_content', 'referrer_url', 'landing_page',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_completed_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function educationStage(): BelongsTo
    {
        return $this->belongsTo(EducationStage::class);
    }
}
