<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Stub class for testing when admin-bundle is not installed.
 *
 * @internal
 */
class ColumnMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type = 'string',
        public readonly bool $sortable = true,
        public readonly ?string $template = null,
    ) {}
}
