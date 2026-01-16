<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Service;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Configuration;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/**
 * Service for querying audit entries using the Reader API.
 *
 * Provides a simpler interface for common audit queries, replacing
 * the legacy EntityAuditRepository methods.
 *
 * Usage:
 *   $audits = $auditReader->findByEntityClass(Product::class, $ids, $from, $to, 'update');
 */
class AuditReader
{
    public function __construct(
        private readonly Reader $reader
    ) {}

    /**
     * Find audit entries for an entity class within a date range.
     *
     * @param string                  $entityClass The entity class name
     * @param null|array<int|string>  $ids         Entity IDs to filter (null for all)
     * @param null|\DateTimeInterface $from        Start date (inclusive)
     * @param null|\DateTimeInterface $to          End date (exclusive)
     * @param null|string             $type        Audit type filter ('insert', 'update', 'delete', 'associate', 'dissociate')
     * @param string                  $orderBy     Field to order by (id, created_at, object_id)
     * @param string                  $order       Sort direction (ASC, DESC)
     *
     * @return array<Entry>
     */
    public function findByEntityClass(
        string $entityClass,
        ?array $ids = null,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        ?string $type = null,
        string $orderBy = 'id',
        string $order = 'DESC'
    ): array {
        $query = $this->reader->createQuery($entityClass, ['page_size' => null]);

        // Get the configured timezone to match how auditor stores timestamps
        $timezone = new \DateTimeZone(
            $this->reader->getProvider()->getAuditor()->getConfiguration()->getTimezone()
        );

        if (null !== $ids && [] !== $ids) {
            // Use SimpleFilter for ID array - it handles arrays with IN clause
            $query->addFilter(new SimpleFilter(Query::OBJECT_ID, array_map('strval', $ids)));
        }

        // Convert dates to the correct timezone for proper filtering
        if (null !== $from) {
            $from = \DateTimeImmutable::createFromInterface($from)->setTimezone($timezone);
        }
        if (null !== $to) {
            $to = \DateTimeImmutable::createFromInterface($to)->setTimezone($timezone);
        }

        if (null !== $from && null !== $to) {
            $query->addFilter(new DateRangeFilter(Query::CREATED_AT, $from, $to));
        } elseif (null !== $from) {
            $query->addFilter(new DateRangeFilter(Query::CREATED_AT, $from, new \DateTimeImmutable('+100 years', $timezone)));
        } elseif (null !== $to) {
            $query->addFilter(new DateRangeFilter(Query::CREATED_AT, new \DateTimeImmutable('1970-01-01', $timezone), $to));
        }

        if (null !== $type) {
            $query->addFilter(new SimpleFilter(Query::TYPE, $type));
        }

        $query->resetOrderBy();
        $queryOrderBy = match ($orderBy) {
            'id' => Query::ID,
            'created_at', 'createdAt' => Query::CREATED_AT,
            'object_id', 'objectId', 'entityId' => Query::OBJECT_ID,
            default => Query::ID,
        };
        $query->addOrderBy($queryOrderBy, mb_strtoupper($order));

        return $query->execute();
    }

    /**
     * Get the underlying Reader for advanced queries.
     */
    public function getReader(): Reader
    {
        return $this->reader;
    }

    /**
     * Find all audit entries that share the same request ID.
     *
     * Request ID is stored in the diffs JSON under @context.request_id.
     * This method queries across all audited entity types.
     *
     * @return array<string, array<Entry>> Indexed by entity FQCN
     */
    public function findByRequestId(string $requestId): array
    {
        $provider = $this->reader->getProvider();
        $configuration = $provider->getConfiguration();
        $results = [];

        if (!$configuration instanceof Configuration) {
            return $results;
        }

        $entities = $configuration->getEntities();
        foreach (array_keys($entities) as $entity) {
            \assert(\is_string($entity));

            try {
                $audits = $this->findEntityAuditsByRequestId($entity, $requestId);
                if ([] !== $audits) {
                    $results[$entity] = $audits;
                }
            } catch (\Exception) {
                // Skip entities that are not accessible or have errors
            }
        }

        return $results;
    }

    /**
     * Find audit entries for a specific entity by request ID.
     *
     * @return array<Entry>
     */
    public function findEntityAuditsByRequestId(string $entityClass, string $requestId): array
    {
        $provider = $this->reader->getProvider();
        $tableName = $this->reader->getEntityAuditTableName($entityClass);

        $storageService = $provider->getStorageServiceForEntity($entityClass);
        $connection = $storageService->getEntityManager()->getConnection();
        $platform = $connection->getDatabasePlatform();

        // Build platform-specific JSON query
        $jsonCondition = $this->buildJsonCondition($platform, 'diffs', '@context', 'request_id');

        $sql = \sprintf(
            'SELECT * FROM %s WHERE %s = ? ORDER BY created_at DESC, id DESC',
            $tableName,
            $jsonCondition
        );

        $result = $connection->executeQuery($sql, [$requestId]);

        $timezone = new \DateTimeZone(
            $provider->getAuditor()->getConfiguration()->getTimezone()
        );

        $entries = [];
        foreach ($result->fetchAllAssociative() as $row) {
            \assert(\is_string($row['created_at']));
            $row['created_at'] = new \DateTimeImmutable($row['created_at'], $timezone);
            $entries[] = Entry::fromArray($row);
        }

        return $entries;
    }

    /**
     * Find audit entries for a specific entity by user search (case-insensitive LIKE on blame_user).
     *
     * @return array<Entry>
     */
    public function findEntityAuditsByUserSearch(string $entityClass, string $searchTerm): array
    {
        $provider = $this->reader->getProvider();
        $tableName = $this->reader->getEntityAuditTableName($entityClass);

        $storageService = $provider->getStorageServiceForEntity($entityClass);
        $connection = $storageService->getEntityManager()->getConnection();

        $sql = \sprintf(
            'SELECT * FROM %s WHERE LOWER(blame_user) LIKE LOWER(?) ORDER BY created_at DESC, id DESC',
            $tableName
        );

        $result = $connection->executeQuery($sql, ['%'.$searchTerm.'%']);

        $timezone = new \DateTimeZone(
            $provider->getAuditor()->getConfiguration()->getTimezone()
        );

        $entries = [];
        foreach ($result->fetchAllAssociative() as $row) {
            \assert(\is_string($row['created_at']));
            $row['created_at'] = new \DateTimeImmutable($row['created_at'], $timezone);
            $entries[] = Entry::fromArray($row);
        }

        return $entries;
    }

    /**
     * Find audit entries across all entities by username within a time range.
     *
     * This creates a cross-entity "timeline" view showing all changes made by a user
     * within a time window, useful for understanding the full impact of an action.
     *
     * @param string             $username            The username/email to search for (exact match, case-insensitive)
     * @param \DateTimeInterface $from                Start of the time range
     * @param \DateTimeInterface $to                  End of the time range
     * @param bool               $includeSystemEvents Include events with no user (async/command execution)
     *
     * @return array<string, array<Entry>> Indexed by entity FQCN
     */
    public function findGlobalTimeline(
        string $username,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        bool $includeSystemEvents = false
    ): array {
        $provider = $this->reader->getProvider();
        $configuration = $provider->getConfiguration();

        if (!$configuration instanceof Configuration) {
            return [];
        }

        $timezone = new \DateTimeZone(
            $provider->getAuditor()->getConfiguration()->getTimezone()
        );

        $results = [];
        $entities = $configuration->getEntities();

        foreach (array_keys($entities) as $entity) {
            \assert(\is_string($entity));

            try {
                $audits = $this->findEntityAuditsByGlobalTimeline(
                    $entity,
                    $username,
                    $from,
                    $to,
                    $includeSystemEvents,
                    $timezone
                );
                if ([] !== $audits) {
                    $results[$entity] = $audits;
                }
            } catch (\Exception) {
                // Skip entities that are not accessible or have errors
            }
        }

        return $results;
    }

    /**
     * Find audit entries for a specific entity by username within a time range.
     *
     * @return array<Entry>
     */
    public function findEntityAuditsByGlobalTimeline(
        string $entityClass,
        string $username,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        bool $includeSystemEvents,
        \DateTimeZone $timezone
    ): array {
        $provider = $this->reader->getProvider();
        $tableName = $this->reader->getEntityAuditTableName($entityClass);

        $storageService = $provider->getStorageServiceForEntity($entityClass);
        $connection = $storageService->getEntityManager()->getConnection();

        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        if ($includeSystemEvents) {
            // Include both user's events and system events
            $sql = \sprintf(
                'SELECT * FROM %s WHERE (LOWER(blame_user) = LOWER(?) OR blame_user IS NULL) AND created_at BETWEEN ? AND ? ORDER BY created_at ASC, id ASC',
                $tableName
            );
            $result = $connection->executeQuery($sql, [$username, $fromStr, $toStr]);
        } else {
            // Only user's events (exact match on username)
            $sql = \sprintf(
                'SELECT * FROM %s WHERE LOWER(blame_user) = LOWER(?) AND created_at BETWEEN ? AND ? ORDER BY created_at ASC, id ASC',
                $tableName
            );
            $result = $connection->executeQuery($sql, [$username, $fromStr, $toStr]);
        }

        $entries = [];
        foreach ($result->fetchAllAssociative() as $row) {
            \assert(\is_string($row['created_at']));
            $row['created_at'] = new \DateTimeImmutable($row['created_at'], $timezone);
            $entries[] = Entry::fromArray($row);
        }

        return $entries;
    }

    /**
     * Find audit entries from the same user within a time window around a reference entry.
     *
     * This creates a "timeline" view showing what the user did before and after
     * the specified action, useful for understanding context and related changes.
     *
     * @param Entry $referenceEntry      The entry to build timeline around
     * @param int   $windowMinutes       Minutes before and after the entry to include (default: 5)
     * @param bool  $includeSystemEvents Include events with no user (async/command execution)
     *
     * @return array<string, array<Entry>> Indexed by entity FQCN
     */
    public function findUserTimeline(
        Entry $referenceEntry,
        int $windowMinutes = 5,
        bool $includeSystemEvents = false
    ): array {
        $provider = $this->reader->getProvider();
        $configuration = $provider->getConfiguration();

        if (!$configuration instanceof Configuration) {
            return [];
        }

        $userId = $referenceEntry->getUserId();
        $createdAt = $referenceEntry->getCreatedAt();

        if (null === $createdAt) {
            return [];
        }

        $timezone = new \DateTimeZone(
            $provider->getAuditor()->getConfiguration()->getTimezone()
        );

        $from = $createdAt->modify("-{$windowMinutes} minutes");
        $to = $createdAt->modify("+{$windowMinutes} minutes");

        $results = [];
        $entities = $configuration->getEntities();

        foreach (array_keys($entities) as $entity) {
            \assert(\is_string($entity));

            try {
                $audits = $this->findEntityAuditsByUserTimeline(
                    $entity,
                    $userId,
                    $from,
                    $to,
                    $includeSystemEvents,
                    $timezone
                );
                if ([] !== $audits) {
                    $results[$entity] = $audits;
                }
            } catch (\Exception) {
                // Skip entities that are not accessible or have errors
            }
        }

        return $results;
    }

    /**
     * Find audit entries for a specific entity by user within a time range.
     *
     * @param null|int|string $userId The user ID to filter by (null matches system events)
     *
     * @return array<Entry>
     */
    public function findEntityAuditsByUserTimeline(
        string $entityClass,
        int|string|null $userId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        bool $includeSystemEvents,
        \DateTimeZone $timezone
    ): array {
        $provider = $this->reader->getProvider();
        $tableName = $this->reader->getEntityAuditTableName($entityClass);

        $storageService = $provider->getStorageServiceForEntity($entityClass);
        $connection = $storageService->getEntityManager()->getConnection();

        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        if (null === $userId) {
            // If reference entry has no user, only show system events in the time range
            $sql = \sprintf(
                'SELECT * FROM %s WHERE blame_id IS NULL AND created_at BETWEEN ? AND ? ORDER BY created_at ASC, id ASC',
                $tableName
            );
            $result = $connection->executeQuery($sql, [$fromStr, $toStr]);
        } elseif ($includeSystemEvents) {
            // Include both user's events and system events
            $sql = \sprintf(
                'SELECT * FROM %s WHERE (blame_id = ? OR blame_id IS NULL) AND created_at BETWEEN ? AND ? ORDER BY created_at ASC, id ASC',
                $tableName
            );
            $result = $connection->executeQuery($sql, [(string) $userId, $fromStr, $toStr]);
        } else {
            // Only user's events
            $sql = \sprintf(
                'SELECT * FROM %s WHERE blame_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC, id ASC',
                $tableName
            );
            $result = $connection->executeQuery($sql, [(string) $userId, $fromStr, $toStr]);
        }

        $entries = [];
        foreach ($result->fetchAllAssociative() as $row) {
            \assert(\is_string($row['created_at']));
            $row['created_at'] = new \DateTimeImmutable($row['created_at'], $timezone);
            $entries[] = Entry::fromArray($row);
        }

        return $entries;
    }

    /**
     * Build platform-specific JSON field extraction SQL.
     *
     * @param object $platform The database platform
     */
    private function buildJsonCondition(object $platform, string $column, string $contextKey, string $fieldKey): string
    {
        // JSON path to @context.request_id
        return match (true) {
            $platform instanceof PostgreSQLPlatform => \sprintf(
                "%s->'%s'->>'%s'",
                $column,
                $contextKey,
                $fieldKey
            ),
            $platform instanceof MySQLPlatform, $platform instanceof MariaDBPlatform => \sprintf(
                "JSON_UNQUOTE(JSON_EXTRACT(%s, '\$.\"%s\".\"%s\"'))",
                $column,
                $contextKey,
                $fieldKey
            ),
            $platform instanceof SQLitePlatform => \sprintf(
                "JSON_EXTRACT(%s, '\$.%s.%s')",
                $column,
                $contextKey,
                $fieldKey
            ),
            default => \sprintf(
                "JSON_UNQUOTE(JSON_EXTRACT(%s, '\$.\"%s\".\"%s\"'))",
                $column,
                $contextKey,
                $fieldKey
            ),
        };
    }
}
