<?php

declare(strict_types=1);

use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;

beforeEach(fn () => $this->down());

it('allows named routes without knowing their URLs', function (): void {
    MaintenanceGuard::exceptRoutes(['admin.dashboard', 'admin.login']);

    $this->get('admin')->assertOk();
    $this->get('admin/login')->assertOk();
    $this->post('admin/login')->assertServiceUnavailable();
    $this->get('blog')->assertServiceUnavailable();
});

it('matches routes with parameters', function (): void {
    MaintenanceGuard::exceptRoutes('admin.users.show');

    $this->get('admin/users/42')->assertOk();
});

it('supports wildcard route names', function (): void {
    MaintenanceGuard::exceptRoutes('admin.*');

    $this->get('admin')->assertOk();
    $this->get('admin/users/42')->assertOk();
    $this->post('admin/login')->assertOk();
    $this->get('administrator')->assertServiceUnavailable();
});

it('restricts a route to the given methods', function (): void {
    MaintenanceGuard::exceptRoutes('status', methods: 'GET');

    $this->get('status')->assertOk();
    $this->post('status')->assertServiceUnavailable();
});

it('keeps unknown paths and wrong methods behind maintenance mode', function (): void {
    MaintenanceGuard::exceptRoutes('*');

    $this->get('does-not-exist')->assertServiceUnavailable();
    $this->delete('status')->assertServiceUnavailable();
});

it('ignores route names that do not exist', function (): void {
    MaintenanceGuard::exceptRoutes('missing.route');

    $this->get('blog')->assertServiceUnavailable();
});

it('loads route names from the configuration', function (): void {
    config()->set('maintenance-guard.routes', ['admin.dashboard', 'status' => ['POST']]);
    $this->refreshGuard();

    $this->get('admin')->assertOk();
    $this->post('status')->assertOk();
    $this->get('status')->assertServiceUnavailable();
});

it('can forget a route name', function (): void {
    MaintenanceGuard::exceptRoutes(['admin.dashboard', 'health'])->forgetRoutes('admin.dashboard');

    $this->get('admin')->assertServiceUnavailable();
    $this->get('health')->assertOk();
});

it('keeps an application admin panel on a custom path reachable', function (): void {
    // e.g. inside AppServiceProvider::boot(), with the panel path read from the app's own config.
    config()->set('app.admin_path', 'administrator');
    MaintenanceGuard::except([config('app.admin_path'), config('app.admin_path').'/*']);

    $this->get('administrator')->assertOk();
    $this->get('admin')->assertServiceUnavailable();
});
