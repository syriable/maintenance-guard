<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard;
use Syriable\MaintenanceGuard\Contracts\Rule;
use Syriable\MaintenanceGuard\Exceptions\InvalidRegistration;
use Syriable\MaintenanceGuard\Support\RouteMatcher;
use Throwable;

final class MaintenanceGuardManager implements MaintenanceGuard
{
    private const array METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    private bool $enabled = true;

    /** @var array<string, list<string>|null> */
    private array $uris = [];

    /** @var array<string, list<string>|null> */
    private array $routes = [];

    /** @var array<string, (Closure(Request): bool)|Rule|class-string<Rule>> */
    private array $rules = [];

    public function __construct(
        private readonly Container $container,
        private readonly RouteMatcher $routeMatcher,
    ) {}

    public function except(string|array $uris, string|array|null $methods = null): static
    {
        foreach ($this->normalize('URI pattern', $uris, $methods) as $uri => $allowed) {
            $this->uris[$uri] = $this->mergeMethods($this->uris, $uri, $allowed);
        }

        return $this;
    }

    public function exceptRoutes(string|array $names, string|array|null $methods = null): static
    {
        foreach ($this->normalize('route name', $names, $methods) as $name => $allowed) {
            $this->routes[$name] = $this->mergeMethods($this->routes, $name, $allowed);
        }

        return $this;
    }

    public function when(Closure|Rule|string $rule): static
    {
        if (is_string($rule) && ! is_subclass_of($rule, Rule::class)) {
            throw InvalidRegistration::invalidRule($rule);
        }

        $key = is_string($rule) ? $rule : 'object#'.spl_object_id($rule);

        $this->rules[$key] = $rule;

        return $this;
    }

    public function forget(string|array $uris): static
    {
        foreach ((array) $uris as $uri) {
            unset($this->uris[$this->normalizeUri($uri)]);
        }

        return $this;
    }

    public function forgetRoutes(string|array $names): static
    {
        foreach ((array) $names as $name) {
            unset($this->routes[$name]);
        }

        return $this;
    }

    public function flush(): static
    {
        $this->uris = [];
        $this->routes = [];
        $this->rules = [];

        return $this;
    }

    public function enable(): static
    {
        $this->enabled = true;

        return $this;
    }

    public function disable(): static
    {
        $this->enabled = false;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Evaluation order, cheapest first; the first match wins:
     *   1. URI patterns
     *   2. Named routes
     *   3. Rules and callbacks, in registration order
     */
    public function allows(Request $request): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return $this->matchesUri($request)
            || $this->matchesRoute($request)
            || $this->passesRules($request);
    }

    public function uris(): array
    {
        return $this->uris;
    }

    public function routes(): array
    {
        return $this->routes;
    }

    private function matchesUri(Request $request): bool
    {
        return array_any(
            $this->uris,
            fn (?array $methods, string $pattern): bool => ($request->is($pattern) || $request->fullUrlIs($pattern))
                && $this->methodAllowed($request, $methods),
        );
    }

    private function matchesRoute(Request $request): bool
    {
        if ($this->routes === []) {
            return false;
        }

        $route = $this->routeMatcher->match($request);

        if (! $route instanceof Route) {
            return false;
        }

        return array_any(
            $this->routes,
            fn (?array $methods, string $name): bool => $route->named($name) && $this->methodAllowed($request, $methods),
        );
    }

    private function passesRules(Request $request): bool
    {
        foreach ($this->rules as $key => $rule) {
            if (is_string($rule)) {
                $rule = $this->rules[$key] = $this->resolveRule($rule);
            }

            try {
                $allowed = $rule instanceof Rule ? $rule->allows($request) : $rule($request);
            } catch (Throwable $exception) {
                // Fail closed: a broken rule keeps the request behind maintenance mode.
                report($exception);

                continue;
            }

            if ($allowed === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Class-based rules are resolved lazily, so they cost nothing until maintenance mode is active.
     *
     * @param  class-string<Rule>  $class
     */
    private function resolveRule(string $class): Rule
    {
        $rule = $this->container->make($class);

        if (! $rule instanceof Rule) {
            throw InvalidRegistration::invalidRule($rule);
        }

        return $rule;
    }

    /**
     * @param  list<string>|null  $methods
     */
    private function methodAllowed(Request $request, ?array $methods): bool
    {
        return $methods === null || in_array($request->getMethod(), $methods, true);
    }

    /**
     * Turn `['a', 'b' => ['GET']]` style input into `['a' => null, 'b' => ['GET', 'HEAD']]`.
     *
     * @param  string|array<array-key, mixed>  $values
     * @param  string|array<array-key, mixed>|null  $methods
     * @return array<string, list<string>|null>
     */
    private function normalize(string $type, string|array $values, string|array|null $methods): array
    {
        $normalized = [];

        foreach ((array) $values as $key => $value) {
            [$subject, $allowed] = is_string($key) ? [$key, $value] : [$value, $methods];

            if (! is_string($subject)) {
                throw InvalidRegistration::invalidValue($type, $subject);
            }

            if (trim($subject) === '') {
                throw InvalidRegistration::emptyValue($type);
            }

            if ($type === 'URI pattern') {
                $subject = $this->normalizeUri($subject);
            }

            $allowed = $this->normalizeMethods($subject, $allowed);

            $normalized[$subject] = $this->mergeMethods($normalized, $subject, $allowed);
        }

        return $normalized;
    }

    private function normalizeUri(string $uri): string
    {
        // Same normalisation as Laravel's own `$except` handling.
        return $uri === '/' ? $uri : trim($uri, '/');
    }

    /**
     * @return list<string>|null
     */
    private function normalizeMethods(string $subject, mixed $methods): ?array
    {
        if ($methods === null) {
            return null;
        }

        $normalized = [];

        foreach ((array) $methods as $method) {
            if (! is_string($method) || ! in_array($method = strtoupper($method), self::METHODS, true)) {
                throw InvalidRegistration::unknownMethod(is_string($method) ? $method : get_debug_type($method));
            }

            $normalized[] = $method;

            // Laravel answers HEAD requests with GET routes, so mirror that here.
            if ($method === 'GET') {
                $normalized[] = 'HEAD';
            }
        }

        if ($normalized === []) {
            throw InvalidRegistration::noMethods($subject);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Registering the same pattern twice widens it rather than duplicating it.
     *
     * @param  array<string, list<string>|null>  $existing
     * @param  list<string>|null  $methods
     * @return list<string>|null
     */
    private function mergeMethods(array $existing, string $key, ?array $methods): ?array
    {
        if (! array_key_exists($key, $existing)) {
            return $methods;
        }

        if ($existing[$key] === null || $methods === null) {
            return null;
        }

        return array_values(array_unique([...$existing[$key], ...$methods]));
    }
}
