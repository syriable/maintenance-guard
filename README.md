# Maintenance Guard for Laravel

`syriable/maintenance-guard` decides which requests remain accessible while your Laravel application is in maintenance mode.

It does **not** replace Laravel's maintenance mode. `php artisan down` and `php artisan up` still turn maintenance mode on and off, and every native option (`--secret`, `--redirect`, `--render`, `--status`, `--retry`, `--refresh`) keeps working exactly as documented by Laravel. The package only adds a flexible, extensible way to say *"this request may pass anyway"*.

```php
use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;

MaintenanceGuard::exceptRoutes('filament.admin.*');          // a whole admin panel
MaintenanceGuard::except('up', methods: 'GET');               // a health check, GET only
MaintenanceGuard::when(fn ($request) => $request->ip() === '10.0.0.5');
```

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [How it works](#how-it-works)
- [Configuration](#configuration)
- [Middleware registration](#middleware-registration)
- [URI exceptions](#uri-exceptions)
- [Named route exceptions](#named-route-exceptions)
- [HTTP method constraints](#http-method-constraints)
- [Conditional rules](#conditional-rules)
- [Service provider registration and the fluent API](#service-provider-registration-and-the-fluent-api)
- [Matching order and overlapping rules](#matching-order-and-overlapping-rules)
- [Native maintenance mode compatibility](#native-maintenance-mode-compatibility)
- [Admin dashboard integration](#admin-dashboard-integration)
- [Livewire integration](#livewire-integration)
- [Security considerations](#security-considerations)
- [Migrating from a custom maintenance middleware](#migrating-from-a-custom-maintenance-middleware)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Features and known limitations](#features-and-known-limitations)

## Requirements

| Package              | Version          |
|----------------------|------------------|
| PHP                  | 8.4+             |
| Laravel              | 13.x             |
| Livewire (optional)  | 4.x              |

No database, no application helpers and no third-party UI packages are required.

## Installation

```bash
composer require syriable/maintenance-guard
```

The service provider and the `MaintenanceGuard` facade are registered automatically through Laravel's package discovery.

Publish the configuration file if you want to configure exceptions there:

```bash
php artisan vendor:publish --tag=maintenance-guard-config
```

## How it works

Laravel ships a global `Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance` middleware. Before it checks anything else, it asks its `inExceptArray()` method whether the request is excluded. This package provides a subclass, `Syriable\MaintenanceGuard\Http\Middleware\PreventRequestsDuringMaintenance`, that overrides only that method:

1. Laravel's own exclusions (`$middleware->preventRequestsDuringMaintenance(except: [...])`) are checked first.
2. If the application is down, the maintenance guard is asked whether the request may pass.
3. Otherwise Laravel's original logic runs untouched: secret bypass route, bypass cookie, `--redirect`, `--render` template and the 503 response.

Because the HTTP kernel resolves middleware through the service container, the package binds the native class to its subclass. **You do not need to change `bootstrap/app.php`.**

## Configuration

```php
// config/maintenance-guard.php
return [
    // false = no package exceptions, Laravel behaves exactly as without the package.
    'enabled' => (bool) env('MAINTENANCE_GUARD_ENABLED', true),

    // URI patterns (Request::is() semantics). String keys add method constraints.
    'except' => [
        'install',
        'install/*',
        'up' => ['GET'],
    ],

    // Named routes; wildcards allowed. String keys add method constraints.
    'routes' => [
        'filament.admin.*',
        'login' => ['GET', 'POST'],
    ],

    // Classes implementing Syriable\MaintenanceGuard\Contracts\Rule.
    'rules' => [
        App\Maintenance\AllowOfficeNetwork::class,
    ],

    // Keep Livewire working on allowed pages (no effect without Livewire).
    'livewire' => (bool) env('MAINTENANCE_GUARD_LIVEWIRE', true),
];
```

Every value is a plain string or array, so `php artisan config:cache` works. Closures cannot be cached, so register them at runtime with [`MaintenanceGuard::when()`](#conditional-rules) instead.

Invalid values (an empty pattern, an unknown HTTP method, a class that does not implement `Rule`, and so on) throw `Syriable\MaintenanceGuard\Exceptions\InvalidRegistration` with a message that names the problem.

## Middleware registration

Nothing to do: Laravel 13 already includes the maintenance middleware in the global stack, and the package swaps in its subclass automatically.

If you prefer to be explicit, or you build your own global middleware stack, reference the package middleware directly:

```php
// bootstrap/app.php
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Syriable\MaintenanceGuard\Http\Middleware\PreventRequestsDuringMaintenance as GuardedMaintenance;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->replace(PreventRequestsDuringMaintenance::class, GuardedMaintenance::class);
})
```

Laravel's own `preventRequestsDuringMaintenance(except: [...])` keeps working alongside the package:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->preventRequestsDuringMaintenance(except: ['webhooks/*']);
})
```

## URI exceptions

URI patterns use Laravel's `Request::is()` semantics, the same as the native `$except` list. Leading and trailing slashes are ignored, and `*` is a wildcard.

| Pattern      | `/install` | `/install/step-1` | `/installer` |
|--------------|:----------:|:-----------------:|:------------:|
| `install`    | ✅          | ❌                 | ❌            |
| `install/*`  | ❌          | ✅                 | ❌            |
| `install*`   | ✅          | ✅                 | ✅            |

To open a section and nothing else, register both the root and its children:

```php
MaintenanceGuard::except(['install', 'install/*']);
```

`/` matches the home page only. Full URLs are accepted too, e.g. `https://status.example.com/*`.

## Named route exceptions

Use route names so you never hardcode URLs:

```php
MaintenanceGuard::exceptRoutes([
    'admin.dashboard',
    'admin.login',
    'admin.users.*',   // wildcards are supported
]);
```

Routes with parameters (`admin/users/{user}`) match for any parameter value. Route names are resolved with the application's own route collection, so domain constraints, route caching and custom URL prefixes all behave as they do in the router.

Because Laravel's maintenance middleware runs globally *before* routing, the package resolves the route itself. This only happens while the application is down and only if at least one route name is registered. Unknown paths (404) and wrong methods (405) never match a route exception.

## HTTP method constraints

Methods are a constraint on an individual registration, never a global switch. That way `GET /status` can stay public without also opening `POST /status`, or every GET request on the site.

```php
MaintenanceGuard::except('status', methods: 'GET');           // GET (and HEAD) only
MaintenanceGuard::exceptRoutes('login', methods: ['GET', 'POST']);

// Several patterns with different methods in one call (same syntax as the config file):
MaintenanceGuard::except([
    'install',               // any method
    'status' => ['GET'],     // GET/HEAD only
    'webhooks/stripe' => 'POST',
]);
```

- Methods are case-insensitive and validated (`GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`).
- Allowing `GET` also allows `HEAD`, just as Laravel's router does.
- `null` (the default) means every method. An empty list is rejected as a likely mistake.

## Conditional rules

For anything a pattern cannot express, register a rule. A rule receives the incoming `Illuminate\Http\Request` and must return `true` to allow it.

```php
use Illuminate\Http\Request;

MaintenanceGuard::when(fn (Request $request): bool => $request->is('health'));

MaintenanceGuard::when(
    fn (Request $request): bool => $request->bearerToken() === config('services.monitor.token')
);
```

Reusable rules can be classes implementing `Syriable\MaintenanceGuard\Contracts\Rule`:

```php
namespace App\Maintenance;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Syriable\MaintenanceGuard\Contracts\Rule;

final readonly class AllowOfficeNetwork implements Rule
{
    public function allows(Request $request): bool
    {
        return IpUtils::checkIp((string) $request->ip(), ['10.0.0.0/8', '192.168.1.0/24']);
    }
}
```

```php
MaintenanceGuard::when(AllowOfficeNetwork::class);   // resolved from the container, lazily
MaintenanceGuard::when(new AllowOfficeNetwork);       // or pass an instance
```

The rule contract:

- **Return value:** only a strict `true` allows the request. `false`, `null`, `1` or `'yes'` do not.
- **Execution order:** rules run in registration order (configured rules first, then runtime registrations), and only when no URI pattern or route name matched. Evaluation stops at the first rule that returns `true`.
- **When they run:** only while the application is down. They cost nothing in normal operation.
- **Exceptions:** a rule that throws is reported through your exception handler (`report()`) and treated as `false` (fail closed). The next rule is still evaluated.
- **Class rules** are resolved from the container the first time they are needed, then reused.
- Registering the same closure, instance or class twice has no effect.

## Service provider registration and the fluent API

Register exceptions in any service provider's `boot()` method, typically `AppServiceProvider`:

```php
namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        MaintenanceGuard::except(['install', 'install/*'])
            ->exceptRoutes(['admin.dashboard', 'admin.login'])
            ->except('up', methods: 'GET')
            ->when(fn (Request $request): bool => $request->hasValidSignature());
    }
}
```

You can also inject the contract instead of using the facade:

```php
use Syriable\MaintenanceGuard\Contracts\MaintenanceGuard;

public function boot(MaintenanceGuard $guard): void
{
    $guard->exceptRoutes('filament.admin.*');
}
```

### API reference

| Method | Description |
|---|---|
| `except(string\|array $uris, string\|array\|null $methods = null)` | Allow URI patterns. |
| `exceptRoutes(string\|array $names, string\|array\|null $methods = null)` | Allow named routes (wildcards allowed). |
| `when(Closure\|Rule\|class-string<Rule> $rule)` | Allow requests for which the rule returns `true`. |
| `forget(string\|array $uris)` | Remove URI patterns. |
| `forgetRoutes(string\|array $names)` | Remove route names. |
| `flush()` | Remove every registration, including configured ones and the Livewire integration. |
| `enable()` / `disable()` / `isEnabled()` | Toggle the package exceptions at runtime. |
| `allows(Request $request): bool` | Ask whether a request would be allowed (useful for debugging). |
| `uris()` / `routes()` | Inspect the registered patterns / route names and their method constraints. |

Registration methods return the guard, so calls can be chained. Registrations are merged, not duplicated: registering `status` for `GET` and later for `POST` results in one entry allowing both. Registering it without methods widens it to every method.

To change a configured exception from code, `forget()` it and register it again:

```php
MaintenanceGuard::forget('install')->except('install', methods: 'GET');
```

## Matching order and overlapping rules

For every request, Laravel's maintenance middleware decides in this order:

1. **Application up?** The request passes and no guard rule is evaluated.
2. **Laravel's native exclusions** (`preventRequestsDuringMaintenance(except: ...)`): the request passes.
3. **Package exceptions**, cheapest first, first match wins:
   1. URI patterns
   2. Named routes
   3. Rules (the Livewire integration, then configured rules, then runtime rules), in order
4. **Laravel's native maintenance handling:** secret route, bypass cookie, `--redirect`, `--render` template, otherwise the `--status` (503) response.

All exceptions are *allow* rules and there are no deny rules, so overlapping registrations never conflict: a request is allowed if **any** registration allows it. Method constraints belong to the registration they were declared on. For example, `except('status', 'GET')` plus `exceptRoutes('status', 'POST')` allows both GET and POST.

## Native maintenance mode compatibility

`php artisan down` and `php artisan up` remain the only way to change maintenance state:

```bash
php artisan down --secret="let-me-in" --render="errors::503" --retry=60
php artisan up
```

| Native feature | Behaviour with the package |
|---|---|
| `--secret` bypass route and cookie | Unchanged |
| `--redirect` | Unchanged for blocked requests; allowed requests are not redirected |
| `--render` pre-rendered template | Unchanged |
| `--status`, `--retry`, `--refresh` | Unchanged |
| `maintenance.driver` (`file` / `cache`) | Unchanged; the package uses `app()->maintenanceMode()` |
| `preventRequestsDuringMaintenance(except: ...)` | Still honoured |

Setting `enabled` to `false` (or calling `MaintenanceGuard::disable()`) turns the package into a no-op, and Laravel behaves exactly as it does without it.

## Admin dashboard integration

The package makes no assumptions about where your admin panel lives. Register it by route name or by its actual path.

**Any admin panel with named routes:**

```php
MaintenanceGuard::exceptRoutes('admin.*');
```

**Admin panel on a configurable path:**

```php
$path = config('admin.path', 'backoffice');

MaintenanceGuard::except([$path, "{$path}/*"]);
```

**Filament:** every panel route is named `filament.{panel-id}.*`, which covers the dashboard, resources, and the login, logout and password-reset pages:

```php
MaintenanceGuard::exceptRoutes('filament.admin.*');
```

Filament pages are Livewire components, so keep the [Livewire integration](#livewire-integration) enabled (it is enabled by default). Filament's CSS and JS are published to `public/` and served by your web server, so they are not affected by maintenance mode.

Allowing the admin panel only bypasses *maintenance mode*. Authentication (`auth`, Filament's `Authenticate`) and authorization middleware still run as usual.

## Livewire integration

Livewire sends component interactions to a single update endpoint (in Livewire 4, `/livewire-{hash}/update`). Opening that endpoint wholesale would let visitors keep using every component on every page during maintenance. Hardcoding `livewire/*` patterns is also fragile, because Livewire 4 derives the prefix from your `APP_KEY` and supports custom update routes.

With `livewire => true` (the default), the package instead:

- **Allows a component update only if every component in the request was rendered on a page the guard allows.** Livewire stores the originating page (`memo.path` and `memo.method`) inside each component snapshot. The package rebuilds that page request and runs it through your URI patterns, route names and rules. Snapshots are checksum-protected, and Livewire rejects any tampered snapshot after the maintenance middleware has run.
- Allows Livewire's own non-update endpoints (JavaScript bundle, source maps, component assets and the signed upload/preview endpoints). They expose no application state on their own.
- Recognises custom update routes registered with `Livewire::setUpdateRoute()`, because their names keep the `livewire.update` suffix.

The result: if `filament.admin.*` is allowed, the admin login form and dashboard widgets work during maintenance, while a Livewire component on your public storefront stays blocked.

Rules that inspect the request also run against the rebuilt page request. Cookies, server variables and the user resolver are carried over, so IP- or header-based rules keep working.

Disable the integration if you do not use Livewire or want to handle it yourself:

```php
'livewire' => false,
```

If Livewire is not installed, the integration simply never matches. Livewire is not a dependency of this package.

## Security considerations

- **Only maintenance mode is bypassed.** Allowed requests still go through their routes' authentication, authorization, CSRF, throttling and signature middleware. Allowing `admin/*` does not make the admin panel public. It only means it is not replaced by the 503 page.
- **Prefer route names and narrow patterns.** `admin*` also matches `/administrator` and `/admin-api`. Use `['admin', 'admin/*']` or route names to be precise.
- **Constrain methods** where only reads are needed (`'status' => ['GET']`).
- **Do not allow the secret path.** Package exceptions are evaluated before Laravel's secret handling. If a pattern matches your `--secret` path, the bypass cookie will not be issued for it.
- **Debugging tools** such as Debugbar should only be allowed outside production:

  ```php
  if ($this->app->hasDebugModeEnabled()) {
      MaintenanceGuard::except('_debugbar/*');
  }
  ```

- **Rules fail closed.** A rule that throws keeps the request blocked, and the exception is reported.
- **Livewire updates are scoped to allowed pages**, as described above.

## Migrating from a custom maintenance middleware

Many applications extend Laravel's middleware with a hardcoded `$except` array and application-specific checks. Move each concern to where it belongs:

| Before | After |
|---|---|
| `protected $except = ['install*', 'admin*', ...]` | `config/maintenance-guard.php` or `MaintenanceGuard::except()` |
| `link_dashboard()` or other URL helpers | `MaintenanceGuard::exceptRoutes('admin.*')` |
| `livewire*` pattern | Built-in [Livewire integration](#livewire-integration) |
| `_debugbar*` pattern | Register only when debug mode is on (see above) |
| Checks against database settings (for example "application inactive") | Keep these in your own application middleware. They are not maintenance mode. |

Then delete your custom middleware and remove any `$middleware->replace(...)` that pointed to it, so Laravel resolves the package middleware.

## Testing

In your application's tests, use Laravel's own maintenance mode commands:

```php
use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;

it('keeps the admin login reachable during maintenance', function () {
    $this->artisan('down')->assertSuccessful();

    $this->get(route('admin.login'))->assertOk();
    $this->get('/')->assertServiceUnavailable();

    $this->artisan('up');
});
```

To work on the package itself:

```bash
composer test        # Pest
composer analyse     # PHPStan (level max, Larastan, strict rules)
composer format      # Pint
composer refactor    # Rector
```

## Troubleshooting

**A request I allowed still gets a 503.**
Ask the guard directly, for example in `php artisan tinker`:

```php
use Illuminate\Http\Request;
use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;

MaintenanceGuard::allows(Request::create('/admin/login', 'GET'));
MaintenanceGuard::uris();
MaintenanceGuard::routes();
```

Then check:

- `install` does not match `install/step-1`. Add `install/*` (see the [pattern table](#uri-exceptions)).
- A method constraint may be excluding the request (for example a POST).
- Is `MAINTENANCE_GUARD_ENABLED` set to `false`, or was `MaintenanceGuard::flush()` called?
- Is your app using its own middleware class instead of Laravel's? The package can only swap `Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance`. Remove your subclass or extend `Syriable\MaintenanceGuard\Http\Middleware\PreventRequestsDuringMaintenance`.

**Configuration changes are ignored.**
Run `php artisan config:clear` (or `config:cache` again) after editing the configuration.

**`config:cache` fails with a serialization error.**
A closure was placed in the config file. Move it to `MaintenanceGuard::when()` in a service provider.

**The admin page loads, but Livewire interactions fail with 503.**
Make sure `livewire` is `true`, and that the page is allowed by a registration the rebuilt page request can match. If a rule checks something that only exists on the update request itself (for example a custom header sent by Livewire), it will not match the rebuilt page request.

**Laravel Octane.**
The guard is a singleton. Register exceptions in service providers, not per request, or they will accumulate across requests.

## Features and known limitations

**Features**

- Works on top of Laravel's native maintenance mode, with no duplicated framework logic.
- Automatic middleware integration with no changes to `bootstrap/app.php`.
- URI patterns, named routes (with wildcards), per-registration HTTP method constraints, closures and rule classes.
- Configuration, service provider and facade/contract registration, with duplicate-safe merging.
- Livewire integration scoped to allowed pages.
- No rule evaluation while the application is up; exceptions are only checked during maintenance.
- No database, no application helpers, no mandatory third-party packages.

**Known limitations**

- Package exceptions are evaluated before Laravel's secret route, so a pattern that matches the secret path prevents the bypass cookie from being issued.
- Only allow rules exist. To block something a broader rule allows, narrow the broader rule.
- Rules evaluated for Livewire updates receive a rebuilt page request, not the original update request.
- The Livewire integration relies on Livewire's snapshot `memo` structure and endpoint naming, verified against Livewire 4.
- Each request makes one extra `maintenanceMode()->active()` check. With the `cache` maintenance driver, that is one extra cache read per request.

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

The MIT License (MIT). See [License File](LICENSE.md).
