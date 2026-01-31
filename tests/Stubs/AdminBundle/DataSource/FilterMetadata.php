<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Stub class for testing when admin-bundle is not installed.
 *
 * @internal
 */
final class FilterMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        /** @var null|array<int, string> */
        public readonly ?array $choices = null,
        public readonly ?string $placeholder = null,
        public readonly ?int $priority = null,
        public readonly bool $multiple = false,
    ) {}

    public static function text(string $name, string $label, ?string $placeholder = null, ?int $priority = null): self
    {
        return new self($name, 'text', $label, null, $placeholder, $priority);
    }

    /**
     * @param array<int, string> $choices
     */
    public static function enum(string $name, array $choices, string $label, bool $multiple = false, ?int $priority = null): self
    {
        return new self($name, 'enum', $label, $choices, null, $priority, $multiple);
    }

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    public static function enumClass(string $name, string $enumClass, string $label, bool $showAllOption = true, bool $multiple = false, ?int $priority = null): self
    {
        // Get values from the enum
        $choices = array_map(
            static fn (\BackedEnum $case): string => (string) $case->value,
            $enumClass::cases()
        );

        return new self($name, 'enum', $label, $choices, null, $priority, $multiple);
    }

    public static function dateRange(string $name, string $label, ?int $priority = null): self
    {
        return new self($name, 'daterange', $label, null, null, $priority);
    }

    public static function boolean(string $name, string $label, bool $showAllOption = true, ?int $priority = null): self
    {
        return new self($name, 'boolean', $label, null, null, $priority);
    }
}
