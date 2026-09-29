<?php

declare(strict_types=1);

namespace Syriable\MaintenanceGuard\Exceptions;

use InvalidArgumentException;
use Syriable\MaintenanceGuard\Contracts\Rule;

final class InvalidRegistration extends InvalidArgumentException
{
    public static function emptyValue(string $type): self
    {
        return new self("A maintenance guard {$type} must be a non-empty string.");
    }

    public static function invalidValue(string $type, mixed $value): self
    {
        return new self(sprintf(
            'A maintenance guard %s must be a non-empty string, [%s] given.',
            $type,
            get_debug_type($value),
        ));
    }

    public static function unknownMethod(string $method): self
    {
        return new self("[{$method}] is not a valid HTTP method for a maintenance guard exception.");
    }

    public static function noMethods(string $value): self
    {
        return new self("The maintenance guard exception [{$value}] has an empty method list. Pass null to allow every method.");
    }

    public static function invalidRule(mixed $rule): self
    {
        return new self(sprintf(
            'A maintenance guard rule must be a Closure, an instance of %s, or the name of a class implementing it; [%s] given.',
            Rule::class,
            is_string($rule) ? $rule : get_debug_type($rule),
        ));
    }
}
