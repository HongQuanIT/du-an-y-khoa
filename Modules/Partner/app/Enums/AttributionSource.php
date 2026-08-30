<?php

declare(strict_types=1);

namespace Modules\Partner\Enums;

enum AttributionSource: string
{
    case Link = 'link';
    case CodeField = 'code_field';
}
