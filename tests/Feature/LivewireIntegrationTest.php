<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard as MaintenanceGuardContract;
use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;
use Syriable\MaintenanceGuard\Rules\AllowLivewireRequests;
use Syriable\MaintenanceGuard\Tests\Fixtures\Counter;

beforeEach(function (): void {
    $this->app->register(LivewireServiceProvider::class);

    Livewire::component('counter', Counter::class);

    Route::middleware('web')->group(function (): void {
        Route::get('admin/counter', fn (): string => Blade::render('<livewire:counter />'))->name('admin.counter');
        Route::get('shop/counter', fn (): string => Blade::render('<livewire:counter />'))->name('shop.counter');
    });

    MaintenanceGuard::exceptRoutes('admin.*');
});

/**
 * Render a page and pull the component snapshot out of the HTML, like the Livewire JS client does.
 */
function snapshotFrom(string $uri): string
{
    $html = test()->get($uri)->assertOk()->getContent();

    preg_match('/wire:snapshot="([^"]+)"/', (string) $html, $matches);

    return html_entity_decode($matches[1], ENT_QUOTES);
}

/**
 * Send a component update exactly like the Livewire JS client.
 */
function livewireUpdate(string $snapshot, string $method = 'increment'): TestResponse
{
    return test()
        ->withHeaders(['X-Livewire' => '1'])
        ->postJson(EndpointResolver::updatePath(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['method' => $method, 'params' => [], 'metadata' => []]],
            ]],
        ]);
}

it('allows updates for components rendered on an allowed page', function (): void {
    $this->down();

    $snapshot = snapshotFrom('admin/counter');

    livewireUpdate($snapshot)->assertOk()->assertSee('Count: 1');
});

it('blocks updates for components rendered on a blocked page', function (): void {
    $snapshot = snapshotFrom('shop/counter');

    $this->down();

    livewireUpdate($snapshot)->assertServiceUnavailable();
});

it('relies on the Livewire checksum to reject a forged origin page', function (): void {
    $snapshot = json_decode(snapshotFrom('shop/counter'), true);
    $snapshot['memo']['path'] = 'admin/counter';

    $this->down();

    // The guard lets it through to Livewire, which rejects the tampered snapshot.
    expect(livewireUpdate(json_encode($snapshot))->status())->not->toBeIn([200, 503]);
});

it('requires every component in a batched update to come from an allowed page', function (): void {
    $allowed = snapshotFrom('admin/counter');
    $blocked = snapshotFrom('shop/counter');

    $this->down();

    $this->withHeaders(['X-Livewire' => '1'])
        ->postJson(EndpointResolver::updatePath(), ['components' => [
            ['snapshot' => $allowed, 'updates' => [], 'calls' => []],
            ['snapshot' => $blocked, 'updates' => [], 'calls' => []],
        ]])
        ->assertServiceUnavailable();
});

it('blocks update requests without components', function (): void {
    $this->down();

    $this->withHeaders(['X-Livewire' => '1'])
        ->postJson(EndpointResolver::updatePath(), ['components' => []])
        ->assertServiceUnavailable();
});

it('does not treat the Livewire header as a bypass on other routes', function (): void {
    $this->down();

    $this->withHeaders(['X-Livewire' => '1'])->get('shop/counter')->assertServiceUnavailable();
});

it('keeps the Livewire script endpoint reachable', function (): void {
    $this->down();

    $this->get(EndpointResolver::scriptPath(minified: ! config('app.debug')))->assertOk();
});

it('can be turned off in the configuration', function (): void {
    config()->set('maintenance-guard.livewire', false);
    $this->refreshGuard()->exceptRoutes('admin.*');

    $this->down();

    $snapshot = snapshotFrom('admin/counter');

    livewireUpdate($snapshot)->assertServiceUnavailable();
    $this->get(EndpointResolver::scriptPath(minified: ! config('app.debug')))->assertServiceUnavailable();
});

it('rebuilds the origin page for applications served from a subdirectory', function (): void {
    $guard = app(MaintenanceGuardContract::class);
    $seen = null;

    $guard->flush()->when(function (Request $request) use (&$seen): bool {
        $seen = $request;

        return false;
    })->when(AllowLivewireRequests::class);

    $snapshot = json_encode(['memo' => ['path' => 'admin/counter', 'method' => 'GET']]);

    $update = Request::create(
        'http://localhost/app'.EndpointResolver::updatePath(),
        'POST',
        ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]],
        server: ['SCRIPT_NAME' => '/app/index.php', 'SCRIPT_FILENAME' => '/var/www/app/index.php', 'HTTP_X_LIVEWIRE' => '1'],
    );

    // The update request resolves to Livewire's route relative to the /app base path.
    expect($update->path())->toBe(ltrim(EndpointResolver::updatePath(), '/'));

    $guard->allows($update);

    expect($seen?->path())->toBe('admin/counter')
        ->and($seen?->getBaseUrl())->toBe('/app')
        ->and($seen?->headers->has('X-Livewire'))->toBeFalse();
});
