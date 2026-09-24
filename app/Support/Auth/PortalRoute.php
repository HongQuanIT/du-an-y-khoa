<?php

declare(strict_types=1);

namespace App\Support\Auth;

final class PortalRoute
{
    public static function content(string $suffix): string
    {
        return request()->routeIs('editor.*') ? 'editor.'.$suffix : 'admin.'.$suffix;
    }
}
