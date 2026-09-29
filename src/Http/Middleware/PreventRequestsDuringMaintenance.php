<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Http\Middleware;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as NativePreventRequestsDuringMaintenance;
use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard;

/**
 * Laravel's maintenance middleware, with exceptions supplied by the maintenance guard.
 *
 * Only the framework's own exclusion hook is extended. Everything else - the
 * secret bypass route and cookie, `--redirect`, `--render`, `--status`,
 * `--retry` and `--refresh` - is Laravel's implementation, untouched.
 */
class PreventRequestsDuringMaintenance extends NativePreventRequestsDuringMaintenance
{
    public function __construct(Application $app, protected MaintenanceGuard $guard)
    {
        parent::__construct($app);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     */
    protected function inExceptArray($request): bool
    {
        // Laravel's own `$except` list and `preventRequestsDuringMaintenance(except: ...)` still apply.
        if (parent::inExceptArray($request)) {
            return true;
        }

        // Guard rules only matter (and only cost anything) while the application is down.
        return $this->app->maintenanceMode()->active()
            && $this->guard->allows($request);
    }
}
