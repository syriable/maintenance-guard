<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Contracts;

use Closure;
use Illuminate\Http\Request;

/**
 * Decides which requests remain accessible while Laravel is in maintenance mode.
 *
 * Laravel's maintenance mode (`php artisan down` / `php artisan up`) stays the
 * single source of truth for whether the application is down. The guard only
 * answers the question "may this request pass anyway?".
 */
interface MaintenanceGuard
{
    /**
     * Allow requests whose path (or full URL) matches the given pattern(s).
     *
     * Patterns use Laravel's `Request::is()` semantics, where `*` is a wildcard.
     * String keys may carry their own method constraints: `['status' => 'GET']`.
     *
     * @param  string|array<array-key, string|list<string>>  $uris
     * @param  string|list<string>|null  $methods  Null allows every HTTP method.
     */
    public function except(string|array $uris, string|array|null $methods = null): static;

    /**
     * Allow requests that resolve to the given named route(s).
     *
     * Names support `*` wildcards, e.g. `filament.admin.*`.
     *
     * @param  string|array<array-key, string|list<string>>  $names
     * @param  string|list<string>|null  $methods  Null allows every HTTP method.
     */
    public function exceptRoutes(string|array $names, string|array|null $methods = null): static;

    /**
     * Allow requests for which the given rule returns true.
     *
     * @param  (Closure(Request): bool)|Rule|class-string<Rule>  $rule
     */
    public function when(Closure|Rule|string $rule): static;

    /**
     * Remove previously registered URI pattern(s).
     *
     * @param  string|list<string>  $uris
     */
    public function forget(string|array $uris): static;

    /**
     * Remove previously registered route name(s).
     *
     * @param  string|list<string>  $names
     */
    public function forgetRoutes(string|array $names): static;

    /**
     * Remove every registration, including configured ones and integrations.
     */
    public function flush(): static;

    public function enable(): static;

    public function disable(): static;

    public function isEnabled(): bool;

    /**
     * Determine whether the request may bypass maintenance mode.
     */
    public function allows(Request $request): bool;

    /**
     * The registered URI patterns and their method constraints (null = any method).
     *
     * @return array<string, list<string>|null>
     */
    public function uris(): array;

    /**
     * The registered route names and their method constraints (null = any method).
     *
     * @return array<string, list<string>|null>
     */
    public function routes(): array;
}
