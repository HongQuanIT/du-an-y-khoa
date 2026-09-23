<?php

declare(strict_types=1);

namespace Modules\Editor\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

final class EditorServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Editor';

    protected string $nameLower = 'editor';
}
