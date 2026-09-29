<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Tests\Fixtures;

use Livewire\Component;

final class Counter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return '<div>Count: {{ $count }}</div>';
    }
}
