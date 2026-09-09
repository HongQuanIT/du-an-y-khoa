<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Modules\Auth\Models\Country;
use Modules\Auth\Models\EducationStage;
use Modules\Auth\Models\Institution;
use Modules\Auth\Models\Profession;
use Tests\TestCase;

final class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, AuthDatabaseSeeder::class]);
    }

    public function test_new_registration_must_complete_profile_before_dashboard(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Học viên mới',
            'email' => 'new-learner@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ]);
        $response->assertRedirect(route('onboarding.profile'));
        $this->carrySessionFrom($response);

        $user = auth()->user();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('learner_profiles', [
            'user_id' => $user->getAuthIdentifier(),
            'onboarding_completed_at' => null,
        ]);
        $this->get(route('dashboard'))->assertRedirect(route('onboarding.profile'));
        $this->get(route('onboarding.profile'))->assertOk()->assertSee('Cho chúng tôi biết thêm về bạn');
    }

    public function test_learner_can_complete_onboarding_with_catalog_values(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Sinh viên Y',
            'email' => 'student-profile@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ]);
        $this->carrySessionFrom($response);

        $country = Country::query()->where('code', 'VN')->firstOrFail();
        $institution = Institution::query()->where('short_name', 'HMU')->firstOrFail();
        $profession = Profession::query()->where('code', 'medical_student')->firstOrFail();
        $stage = EducationStage::query()->where('code', 'year_3')->firstOrFail();

        $this->post(route('onboarding.profile.store'), [
            'country_id' => $country->id,
            'administrative_unit_id' => $institution->administrative_unit_id,
            'institution_id' => $institution->id,
            'profession_id' => $profession->id,
            'education_stage_id' => $stage->id,
            'marketing_consent' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('learner_profiles', [
            'user_id' => auth()->id(),
            'institution_id' => $institution->id,
            'profession_id' => $profession->id,
            'education_stage_id' => $stage->id,
        ]);
        $this->assertNotNull(auth()->user()->learnerProfile()->value('onboarding_completed_at'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_all_onboarding_catalog_fields_render_as_autocomplete_controls(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Học viên Autocomplete',
            'email' => 'autocomplete@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ]);
        $this->carrySessionFrom($response);

        $this->get(route('onboarding.profile'))
            ->assertOk()
            ->assertSee('id="country_search"', false)
            ->assertSee('id="unit_search"', false)
            ->assertSee('id="institution_search"', false)
            ->assertSee('id="profession_search"', false)
            ->assertSee('id="stage_search"', false)
            ->assertDontSee('Không tìm thấy trường của bạn?')
            ->assertDontSee('name="requested_name"', false)
            ->assertDontSee('<select id="country_id"', false)
            ->assertDontSee('<select id="administrative_unit_id"', false)
            ->assertDontSee('<select id="profession_id"', false)
            ->assertDontSee('<select id="education_stage_id"', false);
    }

    public function test_institution_must_belong_to_selected_location(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Học viên Test', 'email' => 'location@example.com',
            'password' => 'Password1', 'password_confirmation' => 'Password1', 'terms' => '1',
        ]);
        $this->carrySessionFrom($response);

        $country = Country::query()->where('code', 'VN')->firstOrFail();
        $institution = Institution::query()->where('short_name', 'HMU')->firstOrFail();
        $otherInstitution = Institution::query()->where('short_name', 'UMP')->firstOrFail();
        $profession = Profession::query()->where('code', 'doctor')->firstOrFail();

        $this->post(route('onboarding.profile.store'), [
            'country_id' => $country->id,
            'administrative_unit_id' => $institution->administrative_unit_id,
            'institution_id' => $otherInstitution->id,
            'profession_id' => $profession->id,
        ])->assertSessionHasErrors('institution_id');
    }
}
