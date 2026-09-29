<?php

declare(strict_types=1);

use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard;
use Syriable\MaintenanceGuard\Exceptions\InvalidRegistration;
use Syriable\MaintenanceGuard\MaintenanceGuardManager;
use Syriable\MaintenanceGuard\Tests\Fixtures\AllowFromHeader;

beforeEach(function (): void {
    $this->guard = app(MaintenanceGuardManager::class);
});

it('is exposed through the contract as a singleton', function (): void {
    expect(app(MaintenanceGuard::class))
        ->toBeInstanceOf(MaintenanceGuardManager::class)
        ->toBe(app(MaintenanceGuard::class));
});

it('normalises patterns and method names', function (): void {
    $this->guard->except(['/install/', 'status' => ['get', 'post']]);

    expect($this->guard->uris())->toBe([
        'install' => null,
        'status' => ['GET', 'HEAD', 'POST'],
    ]);
});

it('does not store duplicate registrations', function (): void {
    $this->guard
        ->except(['install', 'install', '/install'])
        ->exceptRoutes(['admin.dashboard', 'admin.dashboard']);

    expect($this->guard->uris())->toBe(['install' => null])
        ->and($this->guard->routes())->toBe(['admin.dashboard' => null]);
});

it('rejects invalid registrations with a clear error', function (Closure $register, string $message): void {
    expect(fn () => $register($this->guard))->toThrow(InvalidRegistration::class, $message);
})->with([
    'empty pattern' => [fn ($guard) => $guard->except(''), 'URI pattern must be a non-empty string'],
    'blank route' => [fn ($guard) => $guard->exceptRoutes('  '), 'route name must be a non-empty string'],
    'non-string pattern' => [fn ($guard) => $guard->except([42 => 42]), '[int] given'],
    'unknown method' => [fn ($guard) => $guard->except('status', 'FETCH'), '[FETCH] is not a valid HTTP method'],
    'empty methods' => [fn ($guard) => $guard->except('status', []), 'Pass null to allow every method'],
    'non-rule class' => [fn ($guard) => $guard->when(stdClass::class), 'must be a Closure'],
    'missing class' => [fn ($guard) => $guard->when('App\\Missing\\Rule'), 'App\\Missing\\Rule'],
]);

it('accepts every supported registration style', function (): void {
    $this->guard
        ->except('install')
        ->exceptRoutes('admin.*', ['GET'])
        ->when(fn (): bool => false)
        ->when(new AllowFromHeader)
        ->when(AllowFromHeader::class);

    expect($this->guard->uris())->toHaveKey('install')
        ->and($this->guard->routes())->toBe(['admin.*' => ['GET', 'HEAD']]);
});

it('starts enabled', function (): void {
    expect($this->guard->isEnabled())->toBeTrue()
        ->and($this->guard->disable()->isEnabled())->toBeFalse();
});
