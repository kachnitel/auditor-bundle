<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Service;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for reconstructing entity state at a point in time.
 *
 * Works by taking the current entity state and applying audit diffs
 * in reverse order to reconstruct historical state.
 *
 * Usage:
 *   $snapshot = $snapshotService->getPropertiesSnapshot($products, $datetime, ['stock', 'price']);
 *   // Returns [productId => ['stock' => 100, 'price' => 29.99], ...]
 */
class Snapshot
{
    public function __construct(
        private readonly Reader $reader,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * Get a single property value for a single entity at a specific point in time.
     *
     * @param object             $entity   The entity to get historical state for
     * @param \DateTimeInterface $datetime The point in time to reconstruct
     * @param string             $property The property name to get
     *
     * @return mixed The property value at that point in time
     */
    public function getPropertySnapshot(object $entity, \DateTimeInterface $datetime, string $property): mixed
    {
        $snapshot = $this->getPropertiesSnapshot([$entity], $datetime, [$property]);
        $id = $this->getEntityId($entity);

        return $snapshot[$id][$property] ?? null;
    }

    /**
     * Get entity properties at a specific point in time.
     *
     * @param array<object>      $entities   Entities to get historical state for (must all be same class)
     * @param \DateTimeInterface $datetime   The point in time to reconstruct
     * @param array<string>      $properties The property names to include
     *
     * @return array<int|string, array<string, mixed>> Map of entityId => [property => value]
     */
    public function getPropertiesSnapshot(array $entities, \DateTimeInterface $datetime, array $properties): array
    {
        if ([] === $entities) {
            return [];
        }

        $entity = reset($entities);
        $class = $entity::class;

        // Get current state
        $result = $this->getCurrentSnapshot($entities, $properties, $class);

        // Get all update audits from datetime to now
        $audits = $this->getAuditsForSnapshot($class, array_keys($result), $datetime);

        // Get property types for handling collections vs scalars
        $propertyTypes = $this->getPropertyTypes($class, $properties);

        // Apply audits in reverse order (newest first, we undo them)
        foreach ($audits as $audit) {
            $diffs = $audit->getDiffs();
            $entityId = $audit->getObjectId();

            if (!isset($result[$entityId])) {
                continue;
            }

            foreach ($diffs as $property => $values) {
                // Skip metadata keys
                if (str_starts_with($property, '@')) {
                    continue;
                }

                if (!\in_array($property, $properties, true)) {
                    continue;
                }

                $type = $propertyTypes[$property] ?? 'mixed';

                // Handle collections
                if ($this->isCollectionType($type)) {
                    $currentValue = $result[$entityId][$property] ?? new ArrayCollection();

                    /** @var Collection<int|string, object> $collection */
                    $collection = $currentValue instanceof Collection ? $currentValue : new ArrayCollection();
                    $result[$entityId][$property] = $this->reverseCollectionDiff(
                        $collection,
                        \is_array($values) ? $values : []
                    );

                    continue;
                }

                // Handle scalars - auditor-bundle stores as [old, new]
                if (isset($values['old'])) {
                    $result[$entityId][$property] = $this->convertValue($values['old'], $type);
                } elseif (\is_array($values) && 2 === \count($values) && isset($values[0])) {
                    // Alternative format: [oldValue, newValue]
                    $result[$entityId][$property] = $this->convertValue($values[0], $type);
                }
            }
        }

        return $result;
    }

    /**
     * Get current property values from entities.
     *
     * @param array<object> $entities
     * @param array<string> $properties
     * @param class-string  $class
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function getCurrentSnapshot(array $entities, array $properties, string $class): array
    {
        $result = [];

        foreach ($entities as $entity) {
            if ($entity::class !== $class) {
                throw new \InvalidArgumentException(\sprintf(
                    'All entities must be of the same class. Expected %s but got %s',
                    $class,
                    $entity::class
                ));
            }

            $id = $this->getEntityId($entity);
            $result[$id] = [];

            foreach ($properties as $property) {
                $result[$id][$property] = $this->getPropertyValue($entity, $property);
            }
        }

        return $result;
    }

    /**
     * Get audits for the snapshot query.
     *
     * @param class-string      $class
     * @param array<int|string> $entityIds
     *
     * @return array<Entry>
     */
    private function getAuditsForSnapshot(
        string $class,
        array $entityIds,
        \DateTimeInterface $datetime,
    ): array {
        // Get the configured timezone to match how auditor stores timestamps
        $timezone = new \DateTimeZone(
            $this->reader->getProvider()->getAuditor()->getConfiguration()->getTimezone()
        );
        $now = new \DateTimeImmutable('now', $timezone);

        // Convert $datetime to the same timezone for accurate comparison
        $datetime = \DateTimeImmutable::createFromInterface($datetime)->setTimezone($timezone);

        // If datetime is in the future or very close to now, no audits to reverse
        if ($datetime >= $now) {
            return [];
        }

        if ([] === $entityIds) {
            return [];
        }

        $query = $this->reader->createQuery($class, ['page_size' => null]);

        // Filter by entity IDs - SimpleFilter handles arrays with IN clause
        $query->addFilter(new SimpleFilter(Query::OBJECT_ID, array_map('strval', $entityIds)));

        // Filter by date - get all audits from datetime to now
        $query->addFilter(new DateRangeFilter(Query::CREATED_AT, $datetime, $now));

        // Only update types (inserts don't have 'old' values to reverse)
        $query->addFilter(new SimpleFilter(Query::TYPE, 'update'));

        // Order by ID descending (newest first for reverse application)
        $query->resetOrderBy();
        $query->addOrderBy(Query::ID, 'DESC');

        return $query->execute();
    }

    /**
     * Get property types for the entity class.
     *
     * @param class-string  $class
     * @param array<string> $properties
     *
     * @return array<string, string>
     */
    private function getPropertyTypes(string $class, array $properties): array
    {
        $types = [];
        $reflectionClass = new \ReflectionClass($class);

        foreach ($properties as $property) {
            if (!$reflectionClass->hasProperty($property)) {
                $types[$property] = 'mixed';

                continue;
            }

            $reflectionProperty = $reflectionClass->getProperty($property);
            $type = $reflectionProperty->getType();

            if ($type instanceof \ReflectionNamedType) {
                $types[$property] = $type->getName();
            } else {
                $types[$property] = 'mixed';
            }
        }

        return $types;
    }

    private function isCollectionType(string $type): bool
    {
        return Collection::class === $type
            || is_subclass_of($type, Collection::class)
            || ArrayCollection::class === $type;
    }

    /**
     * Reverse a collection diff (undo add/remove operations).
     *
     * @param Collection<int|string, object> $collection
     * @param array<string, mixed>           $values
     *
     * @return Collection<int|string, object>
     */
    private function reverseCollectionDiff(Collection $collection, array $values): Collection
    {
        $collection = clone $collection;

        // If items were added, remove them
        if (isset($values['added']) && \is_array($values['added'])) {
            foreach ($values['added'] as $added) {
                $id = $added['id'] ?? null;
                if (null === $id) {
                    continue;
                }
                $entity = $collection->findFirst(
                    fn (int|string $key, object $item): bool => $this->getEntityId($item) === $id
                );
                if (null !== $entity) {
                    $collection->removeElement($entity);
                }
            }
        }

        // If items were removed, we cannot add them back (no reference)
        // This is a limitation - removed items would need to be fetched from DB

        return $collection;
    }

    /**
     * Convert value to appropriate type.
     */
    private function convertValue(mixed $value, string $type): mixed
    {
        if (null === $value) {
            return null;
        }

        // Handle backed enums (e.g., OrderStatus, AuditType)
        if (is_subclass_of($type, \BackedEnum::class)) {
            // If already the correct enum, return as-is
            if ($value instanceof $type) {
                return $value;
            }
            // Otherwise try to create from backing value
            if (\is_int($value) || \is_string($value)) {
                return $type::tryFrom($value) ?? $value;
            }

            return $value;
        }

        // Handle DateTime types first (before scalar check, as string dates should be converted)
        if (\in_array($type, [\DateTimeInterface::class, \DateTime::class, \DateTimeImmutable::class], true)) {
            if ($value instanceof \DateTimeInterface) {
                return $value;
            }
            if (\is_string($value)) {
                return new \DateTimeImmutable($value);
            }

            return $value;
        }

        // Handle array type
        if ('array' === $type) {
            return \is_array($value) ? $value : [$value];
        }

        // Handle scalar conversions with type safety
        if (\is_scalar($value)) {
            return match ($type) {
                'int', 'integer' => (int) $value,
                'float', 'double' => (float) $value,
                'bool', 'boolean' => (bool) $value,
                'string' => (string) $value,
                default => $value,
            };
        }

        return $value;
    }

    private function getEntityId(object $entity): string
    {
        $meta = $this->entityManager->getClassMetadata($entity::class);
        $identifierValues = $meta->getIdentifierValues($entity);
        $id = reset($identifierValues);

        return \is_scalar($id) ? (string) $id : '';
    }

    private function getPropertyValue(object $entity, string $property): mixed
    {
        $reflectionClass = new \ReflectionClass($entity);

        if (!$reflectionClass->hasProperty($property)) {
            return null;
        }

        $reflectionProperty = $reflectionClass->getProperty($property);

        return $reflectionProperty->getValue($entity);
    }
}
