<?php

namespace Tests;

use App\Models\User;
use App\Support\Auth\WebSessionManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Docker Compose injects APP_ENV=local and DB_*=mysql via the process
     * environment. Those beat phpunit.xml / .env, so RefreshDatabase would
     * migrate:fresh the real MySQL volume. Force a disposable sqlite DB here
     * before the app boots.
     */
    public function createApplication(): Application
    {
        $this->forceTestingEnvironment();

        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Keep the session cookie from a login/register response for the next HTTP call.
     */
    protected function carrySessionFrom(TestResponse $response): static
    {
        $name = config('session.cookie');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                $this->withUnencryptedCookie($name, $cookie->getValue());

                break;
            }
        }

        return $this;
    }

    /**
     * actingAs + web session markers required by EnforceWebSessionPolicy.
     */
    protected function actingAsWithWebSession(User $user, ?string $guard = null): static
    {
        $guard = $guard ?? config('auth.defaults.guard');
        $this->startSession();

        $this->app['auth']->guard($guard)->login($user);

        $sessionId = $this->app['session']->getId();
        $now = now()->timestamp;

        $this->session([
            WebSessionManager::BOUND_SESSION_ID => $sessionId,
            WebSessionManager::LOGGED_IN_AT => $now,
            WebSessionManager::LAST_ACTIVITY_AT => $now,
        ]);

        $this->withCookie(config('session.cookie'), $sessionId);

        return $this;
    }

    private function forceTestingEnvironment(): void
    {
        $forced = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'QUEUE_CONNECTION' => 'sync',
        ];

        foreach ($forced as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
