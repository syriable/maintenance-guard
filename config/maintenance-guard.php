<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When disabled, no package exception is applied and Laravel's maintenance
    | middleware behaves exactly as it does out of the box. Maintenance mode
    | itself is always controlled by `php artisan down` / `php artisan up`.
    |
    */

    'enabled' => (bool) env('MAINTENANCE_GUARD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | URI Patterns
    |--------------------------------------------------------------------------
    |
    | Paths that stay reachable during maintenance, matched with Laravel's
    | `Request::is()` semantics. A pattern matches exactly what it says:
    |
    |   'install'    -> /install only
    |   'install/*'  -> /install/step-1, /install/a/b (not /install itself)
    |   'install*'   -> /install, /install/step-1 and also /installer
    |
    | Restrict a pattern to specific HTTP methods with a string key:
    |
    |   'status' => ['GET'],   // GET (and HEAD) only
    |
    */

    'except' => [
        // 'install',
        // 'install/*',
        // 'up' => ['GET'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Named Routes
    |--------------------------------------------------------------------------
    |
    | Route names that stay reachable during maintenance. Wildcards are
    | supported, so 'filament.admin.*' covers a whole Filament panel. Method
    | constraints use the same string-key syntax as the URI patterns above.
    |
    */

    'routes' => [
        // 'admin.*',
        // 'login' => ['GET', 'POST'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rules
    |--------------------------------------------------------------------------
    |
    | Classes implementing Syriable\MaintenanceGuard\Contracts\Rule. They are
    | resolved from the container the first time they are needed and are
    | evaluated in the order listed. Closures can be registered at runtime
    | with MaintenanceGuard::when() (closures cannot be config-cached).
    |
    */

    'rules' => [
        // App\Maintenance\AllowInternalNetwork::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire Integration
    |--------------------------------------------------------------------------
    |
    | Allows Livewire component updates, but only for components rendered on a
    | page that is itself allowed by the rules above, plus Livewire's static
    | script endpoints. Has no effect when Livewire is not installed.
    |
    */

    'livewire' => (bool) env('MAINTENANCE_GUARD_LIVEWIRE', true),

];
