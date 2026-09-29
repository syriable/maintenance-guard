<?php

declare(strict_types=1);

use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;

beforeEach(fn () => $this->down());

it('matches an exact path only', function (): void {
    MaintenanceGuard::except('install');

    $this->get('install')->assertOk();
    $this->get('install/step-1')->assertServiceUnavailable();
});

it('matches nested paths but not the root with a trailing wildcard segment', function (): void {
    MaintenanceGuard::except('install/*');

    $this->get('install/step-1')->assertOk();
    $this->get('install')->assertServiceUnavailable();
});

it('matches the root, nested paths and siblings with a bare wildcard', function (): void {
    MaintenanceGuard::except('admin*');

    $this->get('admin')->assertOk();
    $this->get('admin/login')->assertOk();
    $this->get('administrator')->assertOk();
    $this->get('blog')->assertServiceUnavailable();
});

it('covers a section without its siblings when both forms are registered', function (): void {
    MaintenanceGuard::except(['admin', 'admin/*']);

    $this->get('admin')->assertOk();
    $this->get('admin/users/1')->assertOk();
    $this->get('administrator')->assertServiceUnavailable();
});

it('treats slash as the home page only', function (): void {
    MaintenanceGuard::except('/');

    $this->get('/')->assertOk();
    $this->get('blog')->assertServiceUnavailable();
});

it('ignores leading and trailing slashes in patterns', function (): void {
    MaintenanceGuard::except('/install/');

    $this->get('install')->assertOk();
});

it('supports full URL patterns like the native except list', function (): void {
    MaintenanceGuard::except('http://localhost/health');

    $this->get('health')->assertOk();
});

it('restricts a pattern to the given methods', function (): void {
    MaintenanceGuard::except('status', methods: 'GET');

    $this->get('status')->assertOk();
    $this->post('status')->assertServiceUnavailable();
});

it('treats HEAD like GET', function (): void {
    MaintenanceGuard::except('status', methods: ['get']);

    $this->call('HEAD', 'status')->assertOk();
});

it('accepts per-pattern methods using string keys', function (): void {
    MaintenanceGuard::except(['status' => ['POST'], 'health']);

    $this->post('status')->assertOk();
    $this->get('status')->assertServiceUnavailable();
    $this->get('health')->assertOk();
});

it('widens rather than duplicates when a pattern is registered twice', function (): void {
    MaintenanceGuard::except('status', 'GET');
    MaintenanceGuard::except('status', 'POST');

    $this->get('status')->assertOk();
    $this->post('status')->assertOk();

    expect(MaintenanceGuard::uris())->toBe(['status' => ['GET', 'HEAD', 'POST']]);
});

it('lets an unrestricted registration win over a restricted one', function (): void {
    MaintenanceGuard::except('status', 'GET');
    MaintenanceGuard::except('status');

    $this->post('status')->assertOk();
});

it('does not match unknown paths that are not registered', function (): void {
    MaintenanceGuard::except('install');

    $this->get('does-not-exist')->assertServiceUnavailable();
});

it('allows unknown paths that match a pattern and lets the router 404 them', function (): void {
    MaintenanceGuard::except('install/*');

    $this->get('install/missing')->assertNotFound();
});

it('loads patterns from the configuration', function (): void {
    config()->set('maintenance-guard.except', ['install', 'status' => 'GET']);
    $this->refreshGuard();

    $this->get('install')->assertOk();
    $this->get('status')->assertOk();
    $this->post('status')->assertServiceUnavailable();
});

it('can forget a pattern', function (): void {
    MaintenanceGuard::except(['install', 'health'])->forget('/install');

    $this->get('install')->assertServiceUnavailable();
    $this->get('health')->assertOk();
});
