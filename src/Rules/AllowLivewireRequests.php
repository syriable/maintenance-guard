<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Rules;

use Illuminate\Http\Request;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard;
use Syriable\MaintenanceGuard\Contracts\Rule;
use Syriable\MaintenanceGuard\Support\RouteMatcher;

/**
 * Keeps Livewire working on pages that are accessible during maintenance.
 *
 * - Component updates are allowed only when *every* component in the payload
 *   was rendered on a page that the guard allows. Livewire records that page
 *   in the checksum-protected snapshot memo (`memo.path` / `memo.method`) and
 *   rejects tampered snapshots after this middleware has run.
 * - Other Livewire endpoints (the JavaScript bundle, source maps, component
 *   assets and the signed upload/preview endpoints) are allowed as-is. They
 *   expose no application state on their own.
 *
 * Livewire is optional: without it installed this rule never allows anything.
 */
final readonly class AllowLivewireRequests implements Rule
{
    public function __construct(
        private MaintenanceGuard $guard,
        private RouteMatcher $routeMatcher,
    ) {}

    public function allows(Request $request): bool
    {
        if (! class_exists(Livewire::class)) {
            return false;
        }

        if ($this->isUpdateRequest($request)) {
            return $this->componentsOriginateFromAllowedPages($request);
        }

        return $request->is($this->endpointPrefix(), $this->endpointPrefix().'/*');
    }

    private function isUpdateRequest(Request $request): bool
    {
        // Custom update routes (Livewire::setUpdateRoute) keep the `livewire.update` name suffix.
        return $this->routeMatcher->match($request)?->named('*livewire.update') ?? false;
    }

    private function componentsOriginateFromAllowedPages(Request $request): bool
    {
        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $origin = $this->originRequest($request, $component);

            if (! $origin instanceof Request || ! $this->guard->allows($origin)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rebuild the request that originally rendered the component.
     */
    private function originRequest(Request $request, mixed $component): ?Request
    {
        $snapshot = is_array($component) && is_string($component['snapshot'] ?? null)
            ? json_decode($component['snapshot'], true)
            : null;

        $path = is_array($snapshot) && is_array($snapshot['memo'] ?? null) ? ($snapshot['memo']['path'] ?? null) : null;
        $method = is_array($snapshot) && is_array($snapshot['memo'] ?? null) ? ($snapshot['memo']['method'] ?? null) : null;

        if (! is_string($path) || ! is_string($method) || $method === '') {
            return null;
        }

        $server = $request->server->all();

        // The origin page was a normal browser request: drop the Livewire-specific headers and body metadata.
        unset($server['HTTP_X_LIVEWIRE'], $server['CONTENT_TYPE'], $server['CONTENT_LENGTH']);

        $origin = Request::create(
            $request->getSchemeAndHttpHost().$request->getBaseUrl().'/'.ltrim($path, '/'),
            strtoupper($method),
            cookies: $request->cookies->all(),
            server: $server,
        );

        $origin->setUserResolver($request->getUserResolver());

        return $origin;
    }

    private function endpointPrefix(): string
    {
        // Livewire 4 derives a per-application prefix (e.g. "livewire-1a2b3c4d"); Livewire 3 uses "livewire".
        return class_exists(EndpointResolver::class)
            ? trim(EndpointResolver::prefix(), '/')
            : 'livewire';
    }
}
