<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Stub class for testing when admin-bundle is not installed.
 *
 * @internal
 */
final class PaginatedResult
{
    /**
     * @param array<int, object> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $totalItems,
        public readonly int $currentPage,
        public readonly int $itemsPerPage,
    ) {}
}
