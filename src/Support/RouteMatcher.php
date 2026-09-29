<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use WeakMap;

/**
 * Resolves the route a request will be dispatched to.
 *
 * The maintenance middleware runs globally, before the router has matched the
 * request, so `$request->route()` is still null at that point. This class asks
 * the route collection directly and remembers the answer for the request.
 */
final class RouteMatcher
{
    /** @var WeakMap<Request, Route|false> */
    private WeakMap $resolved;

    public function __construct(private readonly Router $router)
    {
        $this->resolved = new WeakMap;
    }

    public function match(Request $request): ?Route
    {
        if ($request->route() instanceof Route) {
            return $request->route();
        }

        if (! isset($this->resolved[$request])) {
            try {
                $this->resolved[$request] = $this->router->getRoutes()->match($request);
            } catch (HttpExceptionInterface) {
                // No route (404) or wrong method (405): nothing to match against.
                $this->resolved[$request] = false;
            }
        }

        $route = $this->resolved[$request];

        return $route === false ? null : $route;
    }
}
