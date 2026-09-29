<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;
use Syriable\MaintenanceGuard\Tests\Fixtures\AllowFromHeader;

beforeEach(function (): void {
    AllowFromHeader::$resolved = 0;

    $this->down();
});

it('allows requests when a callback returns true', function (): void {
    MaintenanceGuard::when(fn (Request $request): bool => $request->is('health'));

    $this->get('health')->assertOk();
    $this->get('blog')->assertServiceUnavailable();
});

it('only treats a strict true as allowed', function (): void {
    MaintenanceGuard::when(fn (): string => 'yes');

    $this->get('blog')->assertServiceUnavailable();
});

it('fails closed and reports when a rule throws', function (): void {
    Exceptions::fake();

    MaintenanceGuard::when(fn (): bool => throw new RuntimeException('Rule exploded'));
    MaintenanceGuard::when(fn (Request $request): bool => $request->is('health'));

    $this->get('blog')->assertServiceUnavailable();
    $this->get('health')->assertOk();

    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Rule exploded');
});

it('stops at the first matching rule', function (): void {
    $calls = [];

    MaintenanceGuard::when(function () use (&$calls): bool {
        $calls[] = 'first';

        return true;
    });
    MaintenanceGuard::when(function () use (&$calls): bool {
        $calls[] = 'second';

        return true;
    });

    $this->get('blog')->assertOk();

    expect($calls)->toBe(['first']);
});

it('skips rules entirely when a URI pattern already matched', function (): void {
    $called = false;

    MaintenanceGuard::except('health');
    MaintenanceGuard::when(function () use (&$called): bool {
        $called = true;

        return false;
    });

    $this->get('health')->assertOk();

    expect($called)->toBeFalse();
});

it('accepts rule objects and rule class names', function (): void {
    MaintenanceGuard::when(new AllowFromHeader);

    $this->get('blog', ['X-Maintenance-Pass' => 'let-me-in'])->assertOk();
    $this->get('blog', ['X-Maintenance-Pass' => 'nope'])->assertServiceUnavailable();
});

it('resolves configured rule classes lazily and only once', function (): void {
    config()->set('maintenance-guard.rules', [AllowFromHeader::class]);
    $this->refreshGuard();

    expect(AllowFromHeader::$resolved)->toBe(0);

    $this->get('blog', ['X-Maintenance-Pass' => 'let-me-in'])->assertOk();
    $this->get('blog')->assertServiceUnavailable();

    expect(AllowFromHeader::$resolved)->toBe(1);
});

it('registers the same rule only once', function (): void {
    $calls = 0;
    $rule = function () use (&$calls): bool {
        $calls++;

        return false;
    };

    MaintenanceGuard::when($rule)->when($rule);

    $this->get('blog')->assertServiceUnavailable();

    expect($calls)->toBe(1);
});

it('flushes every registration', function (): void {
    MaintenanceGuard::except('health')
        ->exceptRoutes('blog')
        ->when(fn (): bool => true)
        ->flush();

    $this->get('health')->assertServiceUnavailable();
    $this->get('blog')->assertServiceUnavailable();
});

it('can be disabled and re-enabled at runtime', function (): void {
    MaintenanceGuard::except('health')->disable();

    $this->get('health')->assertServiceUnavailable();

    MaintenanceGuard::enable();

    $this->get('health')->assertOk();
});

it('lets overlapping rules resolve as a union', function (): void {
    MaintenanceGuard::except('status', 'GET');
    MaintenanceGuard::exceptRoutes('status', 'POST');

    $this->get('status')->assertOk();
    $this->post('status')->assertOk();
});
