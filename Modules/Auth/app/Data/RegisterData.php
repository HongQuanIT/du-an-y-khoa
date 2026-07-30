<?php

declare(strict_types=1);

namespace Modules\Auth\Data;

use App\Support\Data\DataTransferObject;

/**
 * Validated input for creating a learner account.
 */
final class RegisterData extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {}
}
