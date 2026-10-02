<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Actions\RegisterUserAction;
use Modules\Auth\Data\RegisterData;
use Tests\TestCase;

final class LearnerCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_receive_a_unique_code_of_two_letters_and_four_digits(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertMatchesRegularExpression('/^[A-Z]{2}\d{4}$/', (string) $first->learner_code);
        $this->assertMatchesRegularExpression('/^[A-Z]{2}\d{4}$/', (string) $second->learner_code);
        $this->assertNotSame($first->learner_code, $second->learner_code);
    }

    public function test_generator_retries_when_the_code_already_exists(): void
    {
        $taken = User::factory()->create();
        $calls = 0;

        $code = LearnerCode::generate(function () use ($taken, &$calls): string {
            $calls++;

            return $calls === 1 ? (string) $taken->learner_code : 'ZX9071';
        });

        $this->assertSame('ZX9071', $code);
        $this->assertSame(2, $calls);
    }

    public function test_registration_assigns_a_learner_code(): void
    {
        $registered = app(RegisterUserAction::class)->handle(new RegisterData(
            name: 'Học viên mới',
            email: 'hocvien-moi@example.com',
            password: 'password-secret',
        ));

        $this->assertMatchesRegularExpression('/^[A-Z]{2}\d{4}$/', (string) $registered->learner_code);
    }
}
