<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Auth\HomePath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Auth\Http\Requests\CompleteOnboardingRequest;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\InstitutionRequest;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;

final class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $profile = LearnerProfile::query()->firstOrCreate(['user_id' => $request->user()->getKey()]);

        if ($profile->onboarding_completed_at !== null) {
            return redirect()->intended(HomePath::for($request->user()));
        }

        $countries = Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $defaultCountryId = $profile->country_id
            ?? $countries->firstWhere('code', 'VN')?->getKey()
            ?? $countries->first()?->getKey();
        $initialInstitution = Institution::query()
            ->find(old('institution_id', $profile->institution_id));

        return view('auth::onboarding.profile', [
            'profile' => $profile,
            'countries' => $countries,
            'defaultCountryId' => $defaultCountryId,
            'initialInstitution' => $initialInstitution,
            'administrativeUnits' => AdministrativeUnit::query()
                ->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'professions' => Profession::query()
                ->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'educationStages' => EducationStage::query()
                ->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function institutions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'administrative_unit_id' => ['required', 'integer', 'exists:administrative_units,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $queryText = trim((string) ($validated['q'] ?? ''));
        $institutions = Institution::query()
            ->active()
            ->where('country_id', $validated['country_id'])
            ->where('administrative_unit_id', $validated['administrative_unit_id'])
            ->when($queryText !== '', function ($query) use ($queryText): void {
                $query->where(function ($nested) use ($queryText): void {
                    $nested->where('name', 'like', "%{$queryText}%")
                        ->orWhere('short_name', 'like', "%{$queryText}%")
                        ->orWhere('search_aliases', 'like', "%{$queryText}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'short_name']);

        return response()->json(['data' => $institutions]);
    }

    public function store(CompleteOnboardingRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $profession = Profession::query()->findOrFail($validated['profession_id']);
        $stageId = $validated['education_stage_id'] ?? null;

        if ($profession->defaults_to_graduated) {
            $stageId = EducationStage::query()->where('code', 'graduated')->value('id');
        }

        DB::transaction(function () use ($request, $validated, $profession, $stageId): void {
            $institution = Institution::query()->findOrFail($validated['institution_id']);
            $country = Country::query()->findOrFail($validated['country_id']);

            LearnerProfile::query()->updateOrCreate(
                ['user_id' => $request->user()->getKey()],
                [
                    'country_id' => $validated['country_id'],
                    'administrative_unit_id' => $validated['administrative_unit_id'],
                    'institution_id' => $validated['institution_id'],
                    'profession_id' => $profession->getKey(),
                    'education_stage_id' => $stageId,
                    'onboarding_completed_at' => now(),
                    'marketing_consent_at' => $request->boolean('marketing_consent') ? now() : null,
                ],
            );

            // Keep legacy profile screens readable while the old text columns are retired.
            $request->user()->forceFill([
                'country' => $country->name,
                'institution' => $institution->name,
                'career_role' => $profession->name,
            ])->save();
        });

        $request->session()->forget('registration_attribution');

        return redirect()->intended(HomePath::for($request->user()))
            ->with('status', 'Đã hoàn tất hồ sơ. Chào mừng bạn!');
    }

    public function requestInstitution(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'administrative_unit_id' => ['required', 'integer', 'exists:administrative_units,id'],
            'requested_name' => ['required', 'string', 'max:180'],
        ]);

        InstitutionRequest::query()->create([
            ...$validated,
            'user_id' => $request->user()->getKey(),
            'status' => 'pending',
        ]);

        return back()->with('institution_request_status', 'Đã gửi yêu cầu. Quản trị viên sẽ bổ sung trường sớm nhất.');
    }
}
