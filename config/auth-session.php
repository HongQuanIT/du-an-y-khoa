<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Web session policy (all portals, web guard)
    |--------------------------------------------------------------------------
    |
    | - max_lifetime_days: absolute cap from successful login (after password/2FA).
    | - idle_timeout_hours: force re-login when no requests in this window.
    | - two_factor_trust_days: skip TOTP when trusted-device cookie is valid.
    |
    */

    'max_lifetime_days' => (int) env('AUTH_SESSION_MAX_DAYS', 30),

    'idle_timeout_hours' => (int) env('AUTH_SESSION_IDLE_HOURS', 24),

    'two_factor_trust_days' => (int) env('AUTH_TWO_FACTOR_TRUST_DAYS', 30),

];
