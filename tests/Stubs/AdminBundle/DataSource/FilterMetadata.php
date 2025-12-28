<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Stub class for testing when admin-bundle is not installed.
 *
 * @internal
 */
class FilterMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        /** @var array<int, string>|null */
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

    public static function dateRange(string $name, string $label, ?int $priority = null): self
    {
        return new self($name, 'daterange', $label, null, null, $priority);
    }
}
