<?php

namespace Modules\AiAssistant\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\AiAssistant\Contracts\AiTutorClient;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Policies\AiThreadPolicy;
use Modules\AiAssistant\Services\Clients\FakeTutorClient;
use Modules\AiAssistant\Services\Clients\OpenAiTutorClient;
use Modules\AiAssistant\Services\Clients\ResilientTutorClient;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AiAssistantServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'AiAssistant';
    protected string $nameLower = 'aiassistant';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(AiTutorClient::class, function ($app): AiTutorClient {
            if ($this->useFakeClient()) {
                return new FakeTutorClient;
            }

            // OpenAI first; Fake fallback if the key is invalid / provider is down.
            return new ResilientTutorClient(
                new OpenAiTutorClient,
                new FakeTutorClient,
            );
        });
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(AiThread::class, AiThreadPolicy::class);
    }

    private function useFakeClient(): bool
    {
        $driver = config('aiassistant.driver');

        if ($driver === 'fake') {
            return true;
        }

        if ($driver === 'openai') {
            return false;
        }

        // Auto: real client only when a key is present and not under tests.
        return $this->app->runningUnitTests()
            || (string) config('services.openai.api_key') === '';
    }
}
