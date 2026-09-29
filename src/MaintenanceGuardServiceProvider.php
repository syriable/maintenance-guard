<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as NativePreventRequestsDuringMaintenance;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard;
use Syriable\MaintenanceGuard\Contracts\Rule;
use Syriable\MaintenanceGuard\Exceptions\InvalidRegistration;
use Syriable\MaintenanceGuard\Http\Middleware\PreventRequestsDuringMaintenance;
use Syriable\MaintenanceGuard\Rules\AllowLivewireRequests;
use Syriable\MaintenanceGuard\Support\RouteMatcher;

final class MaintenanceGuardServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('maintenance-guard')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RouteMatcher::class);

        $this->app->singleton(MaintenanceGuard::class, function (Application $app): MaintenanceGuard {
            $guard = $app->make(MaintenanceGuardManager::class);

            $this->applyConfiguration($guard, $app->make(Repository::class));

            return $guard;
        });

        // The HTTP kernel resolves global middleware through the container, so this
        // swaps in the guarded middleware without touching bootstrap/app.php.
        $this->app->bind(NativePreventRequestsDuringMaintenance::class, PreventRequestsDuringMaintenance::class);
    }

    private function applyConfiguration(MaintenanceGuard $guard, Repository $config): void
    {
        if (! $config->boolean('maintenance-guard.enabled', true)) {
            $guard->disable();
        }

        $guard->except($this->registrations($config, 'except'));
        $guard->exceptRoutes($this->registrations($config, 'routes'));

        if ($config->boolean('maintenance-guard.livewire', true)) {
            $guard->when(AllowLivewireRequests::class);
        }

        foreach ($config->array('maintenance-guard.rules', []) as $rule) {
            if (! is_string($rule) || ! is_subclass_of($rule, Rule::class)) {
                throw InvalidRegistration::invalidRule($rule);
            }

            $guard->when($rule);
        }
    }

    /**
     * Validate the shape of `except` / `routes`: `['pattern', 'pattern' => ['GET']]`.
     *
     * @return array<array-key, string|list<string>>
     */
    private function registrations(Repository $config, string $key): array
    {
        $registrations = [];

        foreach ($config->array("maintenance-guard.{$key}", []) as $subject => $value) {
            $methods = is_string($subject) && is_array($value) ? array_values($value) : null;

            $registrations[$subject] = match (true) {
                is_int($subject) && is_string($value) => $value,
                is_string($subject) && is_string($value) => [$value],
                $methods !== null && array_filter($methods, is_string(...)) === $methods => $methods,
                default => throw InvalidRegistration::invalidValue("[{$key}] entry", $value),
            };
        }

        return $registrations;
    }
}
