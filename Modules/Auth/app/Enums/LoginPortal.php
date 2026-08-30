<?php

declare(strict_types=1);

namespace Modules\Auth\Enums;

/**
 * Which public login entry the credentials were submitted through.
 * Same web guard/session; portals must not accept the other audience.
 */
enum LoginPortal: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Partner = 'partner';
    case Admin = 'admin';
}
