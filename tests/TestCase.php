<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard;
use Syriable\MaintenanceGuard\MaintenanceGuardServiceProvider;

class TestCase extends Orchestra
{
    protected function tearDown(): void
    {
        if ($this->app?->maintenanceMode()->active()) {
            $this->app->maintenanceMode()->deactivate();
        }

        PreventRequestsDuringMaintenance::flushState();

        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MaintenanceGuardServiceProvider::class];
    }

    protected function defineRoutes($router): void
    {
        $this->registerRoutes($router);
    }

    protected function registerRoutes(Router $router): void
    {
        $ok = static fn (): string => 'ok';

        $router->get('/', $ok)->name('home');
        $router->get('install', $ok)->name('install');
        $router->get('install/step-1', $ok)->name('install.step');
        $router->get('admin', $ok)->name('admin.dashboard');
        $router->get('admin/login', $ok)->name('admin.login');
        $router->post('admin/login', $ok)->name('admin.login.attempt');
        $router->get('admin/users/{user}', $ok)->name('admin.users.show');
        $router->get('administrator', $ok)->name('administrator');
        $router->match(['GET', 'POST'], 'status', $ok)->name('status');
        $router->get('health', $ok)->name('health');
        $router->get('blog', $ok)->name('blog');
        $router->get('legacy', $ok)->name('legacy');
    }

    /**
     * Rebuild the guard so it picks up configuration changed inside a test.
     */
    protected function refreshGuard(): MaintenanceGuard
    {
        $this->app->forgetInstance(MaintenanceGuard::class);

        return $this->app->make(MaintenanceGuard::class);
    }

    /**
     * Put the application down with Laravel's own `php artisan down` command.
     *
     * @param  array<string, mixed>  $options
     */
    protected function down(array $options = []): void
    {
        $this->artisan('down', $options)->assertSuccessful();
    }
}
