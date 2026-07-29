<?php

declare(strict_types=1);

namespace App\Support\Concerns;

/**
 * Single-responsibility use-case actions.
 *
 * Implement `handle(...)` on the action and invoke it via:
 *   MyAction::run($input);          // resolved from the container
 *   app(MyAction::class)->handle(); // manual
 *
 * Keeping controllers thin: they delegate one business operation to one action.
 */
trait AsAction
{
    /**
     * Resolve the action from the container (dependencies auto-injected).
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Resolve and execute in one call.
     */
    public static function run(mixed ...$arguments): mixed
    {
        return static::make()->handle(...$arguments);
    }
}
