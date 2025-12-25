<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Service;

use DateTimeInterface;
use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;

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
     * @param string $entityClass The entity class name
     * @param array<int|string>|null $ids Entity IDs to filter (null for all)
     * @param DateTimeInterface|null $from Start date (inclusive)
     * @param DateTimeInterface|null $to End date (exclusive)
     * @param string|null $type Audit type filter ('insert', 'update', 'delete', 'associate', 'dissociate')
     * @param string $orderBy Field to order by (id, created_at, object_id)
     * @param string $order Sort direction (ASC, DESC)
     * @return array<Entry>
     */
    public function findByEntityClass(
        string $entityClass,
        ?array $ids = null,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
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
        $query->addOrderBy($queryOrderBy, strtoupper($order));

        return $query->execute();
    }

    /**
     * Get the underlying Reader for advanced queries.
     */
    public function getReader(): Reader
    {
        return $this->reader;
    }
}
