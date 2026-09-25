<?php

declare(strict_types=1);

namespace Modules\Reviewer\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

final class ReviewerServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Reviewer';

    protected string $nameLower = 'reviewer';
}
