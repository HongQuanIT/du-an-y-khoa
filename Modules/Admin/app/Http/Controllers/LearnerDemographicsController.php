<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Auth\Models\AdministrativeUnit;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\LearnerProfile;
use Modules\Auth\Models\Profession;

final class LearnerDemographicsController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can(Permission::UserView->value), 403);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'administrative_unit_id' => ['nullable', 'integer', 'exists:administrative_units,id'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'education_stage_id' => ['nullable', 'integer', 'exists:education_stages,id'],
            'marketing' => ['nullable', 'in:yes,no'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'registration_method' => ['nullable', 'in:email,google,facebook'],
            'last_login_method' => ['nullable', 'in:email,google,facebook,unrecorded'],
        ]);

        $profiles = LearnerProfile::query();
        if ($request->filled('from')) {
            $profiles->whereDate('learner_profiles.created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $profiles->whereDate('learner_profiles.created_at', '<=', $request->date('to'));
        }
        foreach (['country_id', 'administrative_unit_id', 'institution_id', 'profession_id', 'education_stage_id'] as $foreignKey) {
            if ($request->filled($foreignKey)) {
                $profiles->where("learner_profiles.{$foreignKey}", $request->integer($foreignKey));
            }
        }
        if ($request->query('marketing') === 'yes') {
            $profiles->whereNotNull('marketing_consent_at');
        } elseif ($request->query('marketing') === 'no') {
            $profiles->whereNull('marketing_consent_at');
        }
        if ($request->filled('utm_source')) {
            $profiles->where('utm_source', $request->query('utm_source'));
        }
        if ($request->filled('registration_method')) {
            $profiles->where('registration_method', $request->query('registration_method'));
        }
        if ($request->query('last_login_method') === 'unrecorded') {
            $profiles->whereHas('user', fn ($query) => $query->whereNull('last_login_method'));
        } elseif ($request->filled('last_login_method')) {
            $profiles->whereHas('user', fn ($query) => $query->where('last_login_method', $request->query('last_login_method')));
        }

        $base = (clone $profiles)->whereNotNull('onboarding_completed_at');

        $grouped = static function ($query, string $table, string $foreignKey): array {
            return (clone $query)
                ->join($table, "learner_profiles.{$foreignKey}", '=', "{$table}.id")
                ->select("{$table}.name", DB::raw('COUNT(*) as total'))
                ->groupBy("{$table}.id", "{$table}.name")
                ->orderByDesc('total')
                ->limit(12)
                ->get()
                ->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total])
                ->all();
        };

        $institutions = (clone $base)
            ->join('institutions', 'learner_profiles.institution_id', '=', 'institutions.id')
            ->leftJoin(
                'administrative_units as institution_units',
                'institutions.administrative_unit_id',
                '=',
                'institution_units.id',
            )
            ->select('institutions.name', 'institution_units.name as location_name', DB::raw('COUNT(*) as total'))
            ->groupBy('institutions.id', 'institutions.name', 'institution_units.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name.($row->location_name ? ' — '.$row->location_name : ''),
                'total' => (int) $row->total,
            ])->all();

        return view('admin::learner-data.demographics', [
            'stats' => [
                'completed' => (clone $base)->count(),
                'incomplete' => (clone $profiles)->whereNull('onboarding_completed_at')->count(),
                'marketing' => (clone $base)->whereNotNull('marketing_consent_at')->count(),
                'institutions' => (clone $base)->distinct('institution_id')->count('institution_id'),
            ],
            'institutions' => $institutions,
            'professions' => $grouped($base, 'professions', 'profession_id'),
            'stages' => $grouped($base, 'education_stages', 'education_stage_id'),
            'locations' => $grouped($base, 'administrative_units', 'administrative_unit_id'),
            'registrationMethods' => (clone $base)
                ->select('registration_method', DB::raw('COUNT(*) as total'))
                ->groupBy('registration_method')->orderByDesc('total')->get()
                ->map(fn ($row) => [
                    'name' => match ($row->registration_method) {
                        'google' => 'Google',
                        'facebook' => 'Facebook',
                        default => 'Email/Mật khẩu',
                    },
                    'total' => (int) $row->total,
                ])->all(),
            'lastLoginMethods' => (clone $base)
                ->join('users', 'learner_profiles.user_id', '=', 'users.id')
                ->select('users.last_login_method', DB::raw('COUNT(*) as total'))
                ->groupBy('users.last_login_method')->orderByDesc('total')->get()
                ->map(fn ($row) => [
                    'name' => match ($row->last_login_method) {
                        'email' => 'Email/Mật khẩu',
                        'google' => 'Google',
                        'facebook' => 'Facebook',
                        default => 'Chưa ghi nhận',
                    },
                    'total' => (int) $row->total,
                ])->all(),
            'filters' => $request->only([
                'from', 'to', 'country_id', 'administrative_unit_id', 'institution_id',
                'profession_id', 'education_stage_id', 'marketing', 'utm_source', 'registration_method',
                'last_login_method',
            ]),
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'administrativeUnits' => AdministrativeUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'country_id', 'name']),
            'institutionOptions' => Institution::query()->where('is_active', true)->orderBy('name')->get(['id', 'administrative_unit_id', 'name']),
            'professionOptions' => Profession::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'stageOptions' => EducationStage::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'sourceOptions' => LearnerProfile::query()->whereNotNull('utm_source')->where('utm_source', '!=', '')
                ->distinct()->orderBy('utm_source')->pluck('utm_source'),
        ]);
    }
}
