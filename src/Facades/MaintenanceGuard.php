<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Facades;

use Illuminate\Support\Facades\Facade;
use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard as MaintenanceGuardContract;

/**
 * @method static MaintenanceGuardContract except(string|array<array-key, string|list<string>> $uris, string|list<string>|null $methods = null)
 * @method static MaintenanceGuardContract exceptRoutes(string|array<array-key, string|list<string>> $names, string|list<string>|null $methods = null)
 * @method static MaintenanceGuardContract when(\Closure|\Syriable\MaintenanceGuard\Contracts\Rule|string $rule)
 * @method static MaintenanceGuardContract forget(string|list<string> $uris)
 * @method static MaintenanceGuardContract forgetRoutes(string|list<string> $names)
 * @method static MaintenanceGuardContract flush()
 * @method static MaintenanceGuardContract enable()
 * @method static MaintenanceGuardContract disable()
 * @method static bool isEnabled()
 * @method static bool allows(\Illuminate\Http\Request $request)
 * @method static array<string, list<string>|null> uris()
 * @method static array<string, list<string>|null> routes()
 *
 * @see \Syriable\MaintenanceGuard\MaintenanceGuardManager
 */
final class MaintenanceGuard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MaintenanceGuardContract::class;
    }
}
