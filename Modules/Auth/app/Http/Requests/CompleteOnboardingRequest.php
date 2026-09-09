<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\Profession;

final class CompleteOnboardingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'administrative_unit_id' => ['required', 'integer', Rule::exists('administrative_units', 'id')->where('is_active', true)],
            'institution_id' => ['required', 'integer', Rule::exists('institutions', 'id')->where('is_active', true)],
            'profession_id' => ['required', 'integer', Rule::exists('professions', 'id')->where('is_active', true)],
            'education_stage_id' => ['nullable', 'integer', Rule::exists('education_stages', 'id')->where('is_active', true)],
            'marketing_consent' => ['sometimes', 'accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'country_id' => 'quốc gia',
            'administrative_unit_id' => 'tỉnh/thành phố',
            'institution_id' => 'trường/cơ sở đào tạo',
            'profession_id' => 'chức danh',
            'education_stage_id' => 'năm học',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $countryId = (int) $this->input('country_id');
            $unitId = (int) $this->input('administrative_unit_id');
            $institutionId = (int) $this->input('institution_id');

            $unitMatches = AdministrativeUnit::query()
                ->whereKey($unitId)->where('country_id', $countryId)->where('is_active', true)->exists();
            if (! $unitMatches) {
                $validator->errors()->add('administrative_unit_id', 'Tỉnh/thành phố không thuộc quốc gia đã chọn.');
            }

            $institutionMatches = Institution::query()
                ->whereKey($institutionId)
                ->where('country_id', $countryId)
                ->where('administrative_unit_id', $unitId)
                ->where('is_active', true)
                ->exists();
            if (! $institutionMatches) {
                $validator->errors()->add('institution_id', 'Trường không thuộc tỉnh/thành phố đã chọn.');
            }

            $profession = Profession::query()->find($this->input('profession_id'));
            if ($profession?->requires_education_stage && ! $this->filled('education_stage_id')) {
                $validator->errors()->add('education_stage_id', 'Vui lòng chọn năm học hoặc trạng thái tốt nghiệp.');
            }
        }];
    }
}
