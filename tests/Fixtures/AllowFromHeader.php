<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Tests\Fixtures;

use Illuminate\Http\Request;
use Syriable\MaintenanceGuard\Contracts\Rule;

final class AllowFromHeader implements Rule
{
    public static int $resolved = 0;

    public function __construct()
    {
        self::$resolved++;
    }

    public function allows(Request $request): bool
    {
        return $request->header('X-Maintenance-Pass') === 'let-me-in';
    }
}
