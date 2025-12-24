<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Service;

use DateTimeInterface;
use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\DateRangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\RangeFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Filter\SimpleFilter;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Query;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

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
     * Get entity properties at a specific point in time.
     *
     * @param array<object> $entities Entities to get historical state for (must all be same class)
     * @param DateTimeInterface $datetime The point in time to reconstruct
     * @param array<string> $properties The property names to include
     * @return array<int|string, array<string, mixed>> Map of entityId => [property => value]
     */
    public function getPropertiesSnapshot(array $entities, DateTimeInterface $datetime, array $properties): array
    {
        if ([] === $entities) {
            return [];
        }

        $entity = reset($entities);
        $class = $entity::class;

        // Get current state
        $result = $this->getCurrentSnapshot($entities, $properties, $class);

        // Get all update audits from datetime to now
        $audits = $this->getAuditsForSnapshot($class, array_keys($result), $datetime, $properties);

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

                if (!in_array($property, $properties, true)) {
                    continue;
                }

                $type = $propertyTypes[$property] ?? 'mixed';

                // Handle collections
                if ($this->isCollectionType($type)) {
                    $result[$entityId][$property] = $this->reverseCollectionDiff(
                        $result[$entityId][$property] ?? new ArrayCollection(),
                        $values
                    );
                    continue;
                }

                // Handle scalars - auditor-bundle stores as [old, new]
                if (isset($values['old'])) {
                    $result[$entityId][$property] = $this->convertValue($values['old'], $type);
                } elseif (is_array($values) && count($values) === 2 && isset($values[0])) {
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
     * @return array<int|string, array<string, mixed>>
     */
    private function getCurrentSnapshot(array $entities, array $properties, string $class): array
    {
        $result = [];

        foreach ($entities as $entity) {
            if ($entity::class !== $class) {
                throw new InvalidArgumentException(sprintf(
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
     * @param array<int|string> $entityIds
     * @param array<string> $properties
     * @return array<Entry>
     */
    private function getAuditsForSnapshot(
        string $class,
        array $entityIds,
        DateTimeInterface $datetime,
        array $properties
    ): array {
        $query = $this->reader->createQuery($class, ['page_size' => null]);

        // Filter by entity IDs
        $query->addFilter(new RangeFilter(Query::OBJECT_ID, $entityIds));

        // Filter by date - get all audits from datetime to now
        $query->addFilter(new DateRangeFilter(Query::CREATED_AT, $datetime, new \DateTimeImmutable()));

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
     * @param array<string> $properties
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
        return $type === Collection::class
            || is_subclass_of($type, Collection::class)
            || $type === ArrayCollection::class;
    }

    /**
     * Reverse a collection diff (undo add/remove operations).
     *
     * @param Collection<int|string, object> $collection
     * @param array<string, mixed> $values
     * @return Collection<int|string, object>
     */
    private function reverseCollectionDiff(Collection $collection, array $values): Collection
    {
        $collection = clone $collection;

        // If items were added, remove them
        if (isset($values['added']) && is_array($values['added'])) {
            foreach ($values['added'] as $added) {
                $id = $added['id'] ?? null;
                if (null === $id) {
                    continue;
                }
                $entity = $collection->findFirst(
                    fn ($key, $item) => $this->getEntityId($item) === $id
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

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'array' => (array) $value,
            \DateTimeInterface::class, \DateTime::class, \DateTimeImmutable::class => $value instanceof \DateTimeInterface
                ? $value
                : new \DateTimeImmutable($value),
            default => $value,
        };
    }

    private function getEntityId(object $entity): string
    {
        $meta = $this->entityManager->getClassMetadata($entity::class);
        $identifierValues = $meta->getIdentifierValues($entity);

        return (string) reset($identifierValues);
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
