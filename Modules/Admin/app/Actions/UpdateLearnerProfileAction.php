<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\StaffGuard;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;

final class UpdateLearnerProfileAction
{
    use AsAction;

    /**
     * @param  array{
     *     country_id: int,
     *     administrative_unit_id: int,
     *     institution_id: int,
     *     profession_id: int,
     *     education_stage_id: int|null
     * }  $data
     */
    public function handle(User $actor, User $target, array $data): LearnerProfile
    {
        StaffGuard::assertCanManage($actor, $target);

        $profile = $target->learnerProfile;
        $before = $this->snapshot($profile);
        $profession = Profession::query()->findOrFail($data['profession_id']);
        $stageId = $data['education_stage_id'];

        if ($profession->defaults_to_graduated) {
            $stageId = EducationStage::query()->where('code', 'graduated')->value('id');
        }

        $profile = DB::transaction(function () use ($target, $data, $profession, $stageId, $profile): LearnerProfile {
            $institution = Institution::query()->findOrFail($data['institution_id']);
            $country = Country::query()->findOrFail($data['country_id']);

            $profile = LearnerProfile::query()->updateOrCreate(
                ['user_id' => $target->getKey()],
                [
                    'country_id' => $data['country_id'],
                    'administrative_unit_id' => $data['administrative_unit_id'],
                    'institution_id' => $data['institution_id'],
                    'profession_id' => $profession->getKey(),
                    'education_stage_id' => $stageId,
                    'onboarding_completed_at' => $profile?->onboarding_completed_at ?? now(),
                ],
            );

            $target->forceFill([
                'country' => $country->name,
                'institution' => $institution->name,
                'career_role' => $profession->name,
            ])->save();

            return $profile;
        });

        Auditor::record(
            AuditAction::UserProfileUpdated,
            $actor,
            $target,
            $before,
            $this->snapshot($profile),
        );

        return $profile;
    }

    /** @return array<string, int|null> */
    private function snapshot(?LearnerProfile $profile): array
    {
        return [
            'country_id' => $profile?->country_id,
            'administrative_unit_id' => $profile?->administrative_unit_id,
            'institution_id' => $profile?->institution_id,
            'profession_id' => $profile?->profession_id,
            'education_stage_id' => $profile?->education_stage_id,
        ];
    }
}
