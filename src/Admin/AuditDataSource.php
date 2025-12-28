<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Admin;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use DH\AuditorBundle\Helper\UrlHelper;
use Kachnitel\AdminBundle\DataSource\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Kachnitel\AdminBundle\DataSource\PaginatedResult;

/**
 * Data source for audit logs of a specific entity.
 *
 * Provides audit log data through the admin-bundle's DataSourceInterface,
 * enabling viewing of audit history with filtering and pagination.
 */
class AuditDataSource implements DataSourceInterface
{
    private ?string $shortName = null;

    /**
     * @param class-string $entityClass The audited entity class
     */
    public function __construct(
        private readonly Reader $reader,
        private readonly string $entityClass,
    ) {}

    public function getIdentifier(): string
    {
        return 'audit-' . UrlHelper::namespaceToParam($this->entityClass);
    }

    public function getLabel(): string
    {
        return 'Audit: ' . $this->getShortName();
    }

    public function getIcon(): ?string
    {
        return 'history';
    }

    public function getColumns(): array
    {
        return [
            'id' => new ColumnMetadata('id', 'ID', 'integer', true),
            'object_id' => new ColumnMetadata('object_id', 'Entity ID', 'string', true),
            'type' => new ColumnMetadata('type', 'Action', 'string', true),
            'blame_user' => new ColumnMetadata('blame_user', 'User', 'string', false),
            'created_at' => new ColumnMetadata('created_at', 'Date', 'datetime', true),
            'diffs' => new ColumnMetadata('diffs', 'Changes', 'json', false),
        ];
    }

    public function getFilters(): array
    {
        return [
            'object_id' => FilterMetadata::text('object_id', 'Entity ID', 'Search by ID...', 1),
            'type' => FilterMetadata::enum('type', [
                'insert',
                'update',
                'remove',
                'associate',
                'dissociate',
                'event',
            ], 'Action', true, 2),
            'created_at' => FilterMetadata::dateRange('created_at', 'Date', 3),
            'blame_user' => FilterMetadata::text('blame_user', 'User', 'Search user...', 4),
            'transaction_hash' => FilterMetadata::text('transaction_hash', 'Transaction', 'Transaction hash...', 5),
        ];
    }

    public function getDefaultSortBy(): string
    {
        return 'created_at';
    }

    public function getDefaultSortDirection(): string
    {
        return 'DESC';
    }

    public function getDefaultItemsPerPage(): int
    {
        return 50;
    }

    public function query(
        string $search,
        array $filters,
        string $sortBy,
        string $sortDirection,
        int $page,
        int $itemsPerPage
    ): PaginatedResult {
        $query = $this->reader->createQuery($this->entityClass);

        // Apply filters
        $this->applyFilters($query, $filters, $search);

        // Apply sorting
        $validSortFields = ['id', 'object_id', 'type', 'created_at'];
        if (\in_array($sortBy, $validSortFields, true)) {
            $query->addOrderBy($sortBy, $sortDirection);
        } else {
            $query->addOrderBy('created_at', 'DESC');
        }

        // Get total count before pagination
        $total = $query->count();

        // Clamp page to valid range
        $page = max(1, $page);
        $totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        // Apply pagination
        $offset = ($page - 1) * $itemsPerPage;
        $query->limit($itemsPerPage, $offset);

        // Execute query
        $entries = $query->execute();

        return new PaginatedResult(
            items: $entries,
            totalItems: $total,
            currentPage: $page,
            itemsPerPage: $itemsPerPage,
        );
    }

    public function find(string|int $id): ?object
    {
        $query = $this->reader->createQuery($this->entityClass);
        $query->addFilter(new SimpleFilter(Query::ID, $id));

        $results = $query->execute();

        return $results[0] ?? null;
    }

    public function supportsAction(string $action): bool
    {
        // Audit logs are read-only
        return \in_array($action, ['index', 'show'], true);
    }

    public function getIdField(): string
    {
        return 'id';
    }

    public function getItemId(object $item): string|int
    {
        \assert($item instanceof Entry);
        return $item->getId() ?? 0;
    }

    public function getItemValue(object $item, string $field): mixed
    {
        \assert($item instanceof Entry);

        return match ($field) {
            'id' => $item->getId(),
            'object_id' => $item->getObjectId(),
            'type' => $item->getType(),
            'discriminator' => $item->getDiscriminator(),
            'transaction_hash' => $item->getTransactionHash(),
            'blame_id' => $item->getUserId(),
            'blame_user' => $item->getUsername(),
            'ip' => $item->getIp(),
            'created_at' => $item->getCreatedAt(),
            'diffs' => $item->getDiffs(),
            default => null,
        };
    }

    /**
     * Get the audited entity class.
     *
     * @return class-string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * Get the short name of the audited entity.
     */
    public function getShortName(): string
    {
        if ($this->shortName === null) {
            $parts = explode('\\', $this->entityClass);
            $this->shortName = end($parts);
        }

        return $this->shortName;
    }

    /**
     * Apply filters to the query.
     *
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Query $query, array $filters, string $search): void
    {
        $timezone = new \DateTimeZone(
            $this->reader->getProvider()->getAuditor()->getConfiguration()->getTimezone()
        );

        // Object ID filter
        if (!empty($filters['object_id'])) {
            $query->addFilter(new SimpleFilter(Query::OBJECT_ID, $filters['object_id']));
        }

        // Type filter
        if (!empty($filters['type'])) {
            $types = \is_array($filters['type']) ? $filters['type'] : [$filters['type']];
            $query->addFilter(new SimpleFilter(Query::TYPE, $types));
        }

        // Date range filter (from created_at filter with daterange type)
        if (!empty($filters['created_at'])) {
            $dateFilter = $filters['created_at'];
            if (\is_array($dateFilter)) {
                $from = !empty($dateFilter['from'])
                    ? new \DateTimeImmutable($dateFilter['from'], $timezone)
                    : new \DateTimeImmutable('1970-01-01', $timezone);
                $to = !empty($dateFilter['to'])
                    ? new \DateTimeImmutable($dateFilter['to'] . ' 23:59:59', $timezone)
                    : new \DateTimeImmutable('+100 years', $timezone);
                $query->addFilter(new DateRangeFilter(Query::CREATED_AT, $from, $to));
            }
        }

        // User filter (searches blame_id)
        if (!empty($filters['blame_user'])) {
            // Note: The auditor Query only supports exact match on blame_id
            // For text search, we'd need LIKE support which isn't available
            $query->addFilter(new SimpleFilter(Query::USER_ID, $filters['blame_user']));
        }

        // Transaction hash filter
        if (!empty($filters['transaction_hash'])) {
            $query->addFilter(new SimpleFilter(Query::TRANSACTION_HASH, $filters['transaction_hash']));
        }

        // Global search - search in object_id
        if (!empty($search)) {
            // The auditor Query uses exact/IN match, not LIKE
            // So global search is limited to exact object_id match
            $query->addFilter(new SimpleFilter(Query::OBJECT_ID, $search));
        }
    }
}
