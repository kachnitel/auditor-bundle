<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Admin;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use Kachnitel\AuditorBundle\Helper\DiffFormatter;
use Kachnitel\AuditorBundle\Helper\UrlHelper;
use Kachnitel\AuditorBundle\Service\AuditReader;
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
        private readonly ?AuditReader $auditReader = null,
    ) {}

    public function getIdentifier(): string
    {
        return 'audit-'.UrlHelper::namespaceToParam($this->entityClass);
    }

    public function getLabel(): string
    {
        return 'Audit: '.$this->getShortName();
    }

    public function getIcon(): ?string
    {
        return 'history';
    }

    public function getColumns(): array
    {
        return [
            'id' => new ColumnMetadata(name: 'id', label: 'ID', type: 'integer'),
            'object_id' => new ColumnMetadata(name: 'object_id', label: 'Entity ID', type: 'string'),
            'type' => new ColumnMetadata(name: 'type', label: 'Action', type: 'string'),
            'blame_user' => new ColumnMetadata(name: 'blame_user', label: 'User', type: 'string', sortable: false),
            'created_at' => new ColumnMetadata(name: 'created_at', label: 'Date', type: 'datetime'),
            'diffs' => new ColumnMetadata(
                name: 'diffs',
                label: 'Changes',
                type: 'json',
                sortable: false,
                template: '@DHAuditor/Admin/Audit/_changes-preview.html.twig',
            ),
            'actions' => new ColumnMetadata(
                name: 'actions',
                label: '',
                type: 'actions',
                sortable: false,
                template: '@DHAuditor/Admin/Audit/_row-actions.html.twig',
            ),
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
            'hide_system' => FilterMetadata::boolean('hide_system', 'Hide System Events', false, 5),
            'transaction_hash' => FilterMetadata::text('transaction_hash', 'Transaction', 'Transaction hash...', 6),
            'request_id' => FilterMetadata::text('request_id', 'Request ID', 'Request correlation ID...', 7),
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
        // Special case: request_id filter requires JSON querying via AuditReader
        if (!empty($filters['request_id']) && \is_string($filters['request_id']) && null !== $this->auditReader) {
            return $this->queryByRequestId($filters['request_id'], $page, $itemsPerPage);
        }

        // Special case: blame_user filter uses case-insensitive LIKE search via AuditReader
        if (!empty($filters['blame_user']) && \is_string($filters['blame_user']) && null !== $this->auditReader) {
            return $this->queryByUserSearch($filters['blame_user'], $filters, $page, $itemsPerPage);
        }

        // Check if we need in-memory filtering for system events
        $hideSystem = !empty($filters['hide_system']) && ('1' === $filters['hide_system'] || true === $filters['hide_system']);

        $query = $this->reader->createQuery($this->entityClass);

        // Apply filters (excluding hide_system which is handled in-memory)
        $this->applyFilters($query, $filters, $search);

        // Apply sorting
        $validSortFields = ['id', 'object_id', 'type', 'created_at'];
        if (\in_array($sortBy, $validSortFields, true)) {
            $query->addOrderBy($sortBy, $sortDirection);
        } else {
            $query->addOrderBy('created_at', 'DESC');
        }

        // When filtering system events, we need to fetch all and filter in memory
        if ($hideSystem) {
            // Fetch all entries (no pagination at query level)
            $entries = $query->execute();

            // Filter out system events (entries without username)
            $entries = array_filter($entries, static fn (Entry $entry) => null !== $entry->getUsername());
            $entries = array_values($entries);
            $total = \count($entries);

            // Apply pagination in memory
            $page = max(1, $page);
            $totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
            if ($totalPages > 0 && $page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $itemsPerPage;
            $entries = \array_slice($entries, $offset, $itemsPerPage);

            return new PaginatedResult(
                items: $entries,
                totalItems: $total,
                currentPage: $page,
                itemsPerPage: $itemsPerPage,
            );
        }

        // Standard query path with database pagination
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

    public function find(int|string $id): ?object
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

    public function getItemId(object $item): int|string
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
        if (null === $this->shortName) {
            $parts = explode('\\', $this->entityClass);
            $this->shortName = end($parts);
        }

        return $this->shortName;
    }

    /**
     * Get a formatted preview of changes for an audit entry.
     *
     * @return array<string, mixed>
     */
    public function getChangePreview(Entry $entry): array
    {
        $diffs = $entry->getDiffs();

        return DiffFormatter::createPreview($diffs);
    }

    /**
     * Get detailed structured diffs for display in a detail view.
     *
     * @return array<string, mixed>
     */
    public function getDetailedDiffs(Entry $entry): array
    {
        $diffs = $entry->getDiffs(includeMedadata: true);

        return DiffFormatter::getDetailedStructure($diffs);
    }

    /**
     * Get the request ID from an audit entry's context.
     */
    public function getRequestId(Entry $entry): ?string
    {
        $diffs = $entry->getDiffs(includeMedadata: true);
        $requestId = $diffs['@context']['request_id'] ?? null;

        return \is_string($requestId) ? $requestId : null;
    }

    /**
     * Find all audit entries that share the same request ID.
     *
     * @return array<string, array<Entry>> Indexed by entity FQCN
     */
    public function findRelatedByRequest(Entry $entry): array
    {
        if (null === $this->auditReader) {
            return [];
        }

        $requestId = $this->getRequestId($entry);
        if (null === $requestId) {
            return [];
        }

        return $this->auditReader->findByRequestId($requestId);
    }

    /**
     * Find audit entries from the same user within a time window.
     *
     * @return array<string, array<Entry>> Indexed by entity FQCN
     */
    public function findUserTimeline(Entry $entry, int $windowMinutes = 5, bool $includeSystemEvents = false): array
    {
        if (null === $this->auditReader) {
            return [];
        }

        return $this->auditReader->findUserTimeline($entry, $windowMinutes, $includeSystemEvents);
    }

    /**
     * Check if timeline features are available.
     */
    public function hasTimelineSupport(): bool
    {
        return null !== $this->auditReader;
    }

    /**
     * Query audit entries by request ID.
     *
     * This is a special query path that uses JSON-based filtering
     * to find entries sharing the same request correlation ID.
     */
    private function queryByRequestId(string $requestId, int $page, int $itemsPerPage): PaginatedResult
    {
        \assert(null !== $this->auditReader);

        $entries = $this->auditReader->findEntityAuditsByRequestId($this->entityClass, $requestId);
        $total = \count($entries);

        // Clamp page to valid range
        $page = max(1, $page);
        $totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        // Apply pagination manually
        $offset = ($page - 1) * $itemsPerPage;
        $entries = \array_slice($entries, $offset, $itemsPerPage);

        return new PaginatedResult(
            items: $entries,
            totalItems: $total,
            currentPage: $page,
            itemsPerPage: $itemsPerPage,
        );
    }

    /**
     * Query audit entries by user search (case-insensitive LIKE on blame_user).
     *
     * @param array<string, mixed> $filters Additional filters to apply in memory
     */
    private function queryByUserSearch(string $userSearch, array $filters, int $page, int $itemsPerPage): PaginatedResult
    {
        \assert(null !== $this->auditReader);

        $entries = $this->auditReader->findEntityAuditsByUserSearch($this->entityClass, $userSearch);

        // Apply additional filters in memory
        $entries = $this->filterEntriesInMemory($entries, $filters);

        $total = \count($entries);

        // Clamp page to valid range
        $page = max(1, $page);
        $totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        // Apply pagination manually
        $offset = ($page - 1) * $itemsPerPage;
        $entries = \array_slice($entries, $offset, $itemsPerPage);

        return new PaginatedResult(
            items: $entries,
            totalItems: $total,
            currentPage: $page,
            itemsPerPage: $itemsPerPage,
        );
    }

    /**
     * Filter entries in memory for combined filter queries.
     *
     * @param array<Entry>         $entries
     * @param array<string, mixed> $filters
     *
     * @return array<Entry>
     */
    private function filterEntriesInMemory(array $entries, array $filters): array
    {
        $timezone = new \DateTimeZone(
            $this->reader->getProvider()->getAuditor()->getConfiguration()->getTimezone()
        );

        return array_values(array_filter($entries, function (Entry $entry) use ($filters, $timezone): bool {
            // Object ID filter
            if (!empty($filters['object_id']) && $entry->getObjectId() !== $filters['object_id']) {
                return false;
            }

            // Type filter
            if (!empty($filters['type'])) {
                $types = \is_array($filters['type']) ? $filters['type'] : [$filters['type']];
                if (!\in_array($entry->getType(), $types, true)) {
                    return false;
                }
            }

            // Date range filter (handles both array and JSON string formats)
            if (!empty($filters['created_at'])) {
                $createdAt = $entry->getCreatedAt();
                if (null === $createdAt) {
                    return false;
                }

                $dateFilter = $filters['created_at'];
                // Handle JSON string format from URL (e.g., '{"from":"2024-01-01","to":"2024-01-02"}')
                if (\is_string($dateFilter)) {
                    $decoded = json_decode($dateFilter, true);
                    $dateFilter = \is_array($decoded) ? $decoded : [];
                }

                if (!\is_array($dateFilter)) {
                    return true; // Invalid format, skip filter
                }

                $fromStr = $dateFilter['from'] ?? '';
                $toStr = $dateFilter['to'] ?? '';

                if (!empty($fromStr)) {
                    $from = str_contains($fromStr, ':')
                        ? new \DateTimeImmutable($fromStr, $timezone)
                        : new \DateTimeImmutable($fromStr.' 00:00:00', $timezone);
                    if ($createdAt < $from) {
                        return false;
                    }
                }

                if (!empty($toStr)) {
                    $to = str_contains($toStr, ':')
                        ? new \DateTimeImmutable($toStr, $timezone)
                        : new \DateTimeImmutable($toStr.' 23:59:59', $timezone);
                    if ($createdAt > $to) {
                        return false;
                    }
                }
            }

            // Transaction hash filter
            if (!empty($filters['transaction_hash']) && $entry->getTransactionHash() !== $filters['transaction_hash']) {
                return false;
            }

            return true;
        }));
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

        // Date range filter (handles both array and JSON string formats)
        if (!empty($filters['created_at'])) {
            $dateFilter = $filters['created_at'];

            // Handle JSON string format from URL (e.g., '{"from":"2024-01-01","to":"2024-01-02"}')
            if (\is_string($dateFilter)) {
                $decoded = json_decode($dateFilter, true);
                $dateFilter = \is_array($decoded) ? $decoded : null;
            }

            if (\is_array($dateFilter)) {
                $fromStr = $dateFilter['from'] ?? '';
                $toStr = $dateFilter['to'] ?? '';

                // Handle full datetime or date-only formats
                if (!empty($fromStr)) {
                    // If it's just a date (no time component), use start of day
                    $from = str_contains($fromStr, ':')
                        ? new \DateTimeImmutable($fromStr, $timezone)
                        : new \DateTimeImmutable($fromStr.' 00:00:00', $timezone);
                } else {
                    $from = new \DateTimeImmutable('1970-01-01', $timezone);
                }

                if (!empty($toStr)) {
                    // If it's just a date (no time component), use end of day
                    $to = str_contains($toStr, ':')
                        ? new \DateTimeImmutable($toStr, $timezone)
                        : new \DateTimeImmutable($toStr.' 23:59:59', $timezone);
                } else {
                    $to = new \DateTimeImmutable('+100 years', $timezone);
                }

                $query->addFilter(new DateRangeFilter(Query::CREATED_AT, $from, $to));
            }
        }

        // User filter - fallback to exact blame_id match when AuditReader not available
        // (When AuditReader IS available, this is handled via queryByUserSearch() for LIKE search)
        if (!empty($filters['blame_user'])) {
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
