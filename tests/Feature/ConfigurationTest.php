<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Syriable\MaintenanceGuard\Exceptions\InvalidRegistration;
use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;
use Syriable\MaintenanceGuard\MaintenanceGuardServiceProvider;
use Syriable\MaintenanceGuard\Tests\Fixtures\AllowFromHeader;

it('merges the package configuration with sensible defaults', function (): void {
    expect(config('maintenance-guard'))->toBe([
        'enabled' => true,
        'except' => [],
        'routes' => [],
        'rules' => [],
        'livewire' => true,
    ]);
});

it('publishes the configuration file', function (): void {
    $target = config_path('maintenance-guard.php');
    @unlink($target);

    $this->artisan('vendor:publish', ['--tag' => 'maintenance-guard-config'])->assertSuccessful();

    expect($target)->toBeFile()
        ->and(require $target)->toHaveKeys(['enabled', 'except', 'routes', 'rules', 'livewire']);

    unlink($target);
});

it('survives configuration caching', function (): void {
    config()->set('maintenance-guard.except', ['install', 'status' => ['GET']]);
    config()->set('maintenance-guard.rules', [AllowFromHeader::class]);

    // `php artisan config:cache` persists configuration with var_export(), exactly like this.
    $path = tempnam(sys_get_temp_dir(), 'maintenance-guard');
    file_put_contents($path, '<?php return '.var_export(config('maintenance-guard'), true).';'.PHP_EOL);
    $cached = require $path;
    unlink($path);

    expect($cached)->toBe(config('maintenance-guard'));

    config()->set('maintenance-guard', $cached);
    $this->refreshGuard();
    $this->down();

    $this->get('install')->assertOk();
    $this->get('blog', ['X-Maintenance-Pass' => 'let-me-in'])->assertOk();
});

it('fails with a clear error for malformed configuration', function (string $key, mixed $value): void {
    config()->set("maintenance-guard.{$key}", $value);

    expect(fn () => $this->refreshGuard())->toThrow(InvalidArgumentException::class);
})->with([
    'except is not an array' => ['except', 'install'],
    'route methods are not strings' => ['routes', ['status' => [1]]],
    'rule is not a rule class' => ['rules', [stdClass::class]],
    'rule is a closure' => ['rules', [fn (): bool => true]],
]);

it('reports invalid configuration entries with the package exception', function (): void {
    config()->set('maintenance-guard.except', [['nested']]);

    expect(fn () => $this->refreshGuard())->toThrow(InvalidRegistration::class, '[except] entry');
});

it('works without Livewire being registered', function (): void {
    // Livewire's service provider is not loaded in this test case.
    MaintenanceGuard::except('health');

    $this->down();

    $this->withHeaders(['X-Livewire' => '1'])->postJson('livewire/update', ['components' => []])->assertServiceUnavailable();
    $this->get('health')->assertOk();

    expect(MaintenanceGuard::allows(Request::create('/blog')))->toBeFalse();
});

it('declares a discoverable service provider and facade', function (): void {
    $laravel = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true)['extra']['laravel'];

    expect($laravel['providers'])->toBe([MaintenanceGuardServiceProvider::class])
        ->and($laravel['aliases'])->toBe(['MaintenanceGuard' => MaintenanceGuard::class]);
});
