<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DataSource;

/**
 * Stub interface for testing when admin-bundle is not installed.
 *
 * @internal
 */
interface DataSourceInterface
{
    public function getIdentifier(): string;

    public function getLabel(): string;

    public function getIcon(): ?string;

    /**
     * @return array<string, ColumnMetadata>
     */
    public function getColumns(): array;

    /**
     * @return array<string, FilterMetadata>
     */
    public function getFilters(): array;

    public function getDefaultSortBy(): string;

    public function getDefaultSortDirection(): string;

    public function getDefaultItemsPerPage(): int;

    /**
     * @param array<string, mixed> $filters
     */
    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage
    ): PaginatedResult;

    public function find(int|string $id): ?object;

    public function supportsAction(string $action): bool;

    public function getIdField(): string;

    public function getItemId(object $item): int|string;

    public function getItemValue(object $item, string $field): mixed;
}
