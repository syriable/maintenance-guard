<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Contracts;

use Illuminate\Http\Request;

/**
 * A custom condition that can keep a request accessible during maintenance mode.
 *
 * Rules are only evaluated while the application is down, so they may be
 * more expensive than a URI pattern. Return true to allow the request.
 */
interface Rule
{
    public function allows(Request $request): bool;
}
