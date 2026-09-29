<?php

declare(strict_types=1);

arch('source files use strict types')
    ->expect('Syriable\MaintenanceGuard')
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'print_r'])
    ->not->toBeUsed();

arch('the package does not depend on application code or helpers')
    ->expect('Syriable\MaintenanceGuard')
    ->not->toUse(['App', 'settings', 'env', Illuminate\Support\Facades\DB::class, 'Illuminate\Database']);

arch('only the Livewire rule knows about Livewire')
    ->expect('Livewire')
    ->toOnlyBeUsedIn(Syriable\MaintenanceGuard\Rules\AllowLivewireRequests::class);

arch('rules implement the rule contract')
    ->expect('Syriable\MaintenanceGuard\Rules')
    ->toImplement(Syriable\MaintenanceGuard\Contracts\Rule::class)
    ->toBeFinal();

arch('contracts are interfaces')
    ->expect('Syriable\MaintenanceGuard\Contracts')
    ->toBeInterfaces();
