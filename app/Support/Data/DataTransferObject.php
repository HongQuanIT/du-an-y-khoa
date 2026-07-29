<?php

declare(strict_types=1);

namespace App\Support\Data;

use ReflectionClass;
use ReflectionNamedType;

/**
 * Lightweight, dependency-free base DTO for business input/output.
 *
 * Subclasses declare `public readonly` constructor-promoted properties.
 * Hydrate from a request/array with `::from()` — only known properties are
 * mapped (whitelisting), which keeps boundaries explicit and fast.
 */
abstract class DataTransferObject
{
    /**
     * Build the DTO from an associative array, ignoring unknown keys.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): static
    {
        $constructor = (new ReflectionClass(static::class))->getConstructor();

        if ($constructor === null) {
            return new static; // @phpstan-ignore-line
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $payload)) {
                $arguments[$name] = $payload[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[$name] = $parameter->getDefaultValue();
            } else {
                $type = $parameter->getType();
                $arguments[$name] = ($type instanceof ReflectionNamedType && $type->allowsNull()) ? null : null;
            }
        }

        return new static(...$arguments); // @phpstan-ignore-line
    }

    /**
     * Shallow array representation (public properties only).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
