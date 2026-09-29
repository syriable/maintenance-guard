<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as NativeMiddleware;
use Syriable\MaintenanceGuard\Facades\MaintenanceGuard;
use Syriable\MaintenanceGuard\Http\Middleware\PreventRequestsDuringMaintenance;

it('replaces the native middleware in the container', function (): void {
    expect(app(NativeMiddleware::class))->toBeInstanceOf(PreventRequestsDuringMaintenance::class);
});

it('allows every request while the application is up', function (): void {
    $this->get('blog')->assertOk();
    $this->post('status')->assertOk();
});

it('blocks requests with a 503 when down and nothing matches', function (): void {
    MaintenanceGuard::except('install');

    $this->down();

    $this->get('blog')->assertServiceUnavailable();
    $this->getJson('blog')->assertServiceUnavailable();
});

it('lets requests through again after php artisan up', function (): void {
    $this->down();
    $this->get('blog')->assertServiceUnavailable();

    $this->artisan('up')->assertSuccessful();

    $this->get('blog')->assertOk();
});

it('keeps the native secret bypass working', function (): void {
    $this->down(['--secret' => 'open-sesame']);

    $response = $this->get('open-sesame');

    $response->assertRedirect('/')->assertCookie('laravel_maintenance');

    $this->withUnencryptedCookie('laravel_maintenance', $response->getCookie('laravel_maintenance', false)->getValue())
        ->get('blog')
        ->assertOk();
});

it('does not accept a bypass cookie for a different secret', function (): void {
    $this->down(['--secret' => 'open-sesame']);

    $this->withCookie('laravel_maintenance', 'forged')->get('blog')->assertServiceUnavailable();
});

it('preserves the native status, retry and refresh options', function (): void {
    $this->down(['--status' => 599, '--retry' => 60, '--refresh' => 15]);

    $this->get('blog')
        ->assertStatus(599)
        ->assertHeader('Retry-After', '60')
        ->assertHeader('Refresh', '15');
});

it('preserves the native redirect option', function (): void {
    MaintenanceGuard::except('install');

    $this->down(['--redirect' => '/install']);

    $this->get('blog')->assertRedirect('install');
    $this->get('install')->assertOk();
});

it('preserves the native pre-rendered template', function (): void {
    $this->down(['--render' => 'errors::503']);

    $this->get('blog')->assertServiceUnavailable()->assertSee('Be right back.');
});

it('still honours the native except list', function (): void {
    NativeMiddleware::except(['legacy']);

    $this->down();

    $this->get('legacy')->assertOk();
    $this->get('blog')->assertServiceUnavailable();
});

it('falls back to pure native behaviour when disabled', function (): void {
    config()->set('maintenance-guard.enabled', false);
    config()->set('maintenance-guard.except', ['install']);
    $this->refreshGuard();

    $this->down();

    $this->get('install')->assertServiceUnavailable();
});

it('does not evaluate rules while the application is up', function (): void {
    $calls = 0;

    MaintenanceGuard::when(function () use (&$calls): bool {
        $calls++;

        return false;
    });

    $this->get('blog')->assertOk();

    expect($calls)->toBe(0);
});
