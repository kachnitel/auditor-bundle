<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Helper;

/**
 * Formats audit diffs into human-readable previews.
 *
 * Provides both inline summaries for table display and detailed
 * structured data for full diff views.
 */
class DiffFormatter
{
    /**
     * Create a preview summary of changes for display in tables.
     *
     * @param array<string, mixed> $diffs The diffs from an audit entry
     *
     * @return array<string, mixed> Summary data with counts and samples
     */
    public static function createPreview(array $diffs): array
    {
        $preview = [
            'total_changes' => 0,
            'changes' => [],
        ];

        foreach ($diffs as $key => $value) {
            // Skip metadata fields
            if (str_starts_with($key, '@')) {
                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            $changeInfo = [
                'field' => $key,
                'type' => 'unknown',
                'old' => null,
                'new' => null,
                'removed_count' => 0,
                'added_count' => 0,
            ];

            if (\array_key_exists('old', $value) && \array_key_exists('new', $value)) {
                $changeInfo['type'] = 'update';
                $changeInfo['old'] = self::truncateValue($value['old']);
                $changeInfo['new'] = self::truncateValue($value['new']);
            } elseif (\array_key_exists('removed', $value) && \array_key_exists('added', $value)) {
                $changeInfo['type'] = 'association';
                $changeInfo['removed_count'] = \is_array($value['removed']) ? \count($value['removed']) : 0;
                $changeInfo['added_count'] = \is_array($value['added']) ? \count($value['added']) : 0;
            }

            if ('unknown' !== $changeInfo['type']) {
                $preview['changes'][] = $changeInfo;
                ++$preview['total_changes'];
            }
        }

        return $preview;
    }

    /**
     * Get structured data for detailed diff display.
     *
     * @param array<string, mixed> $diffs The diffs from an audit entry
     *
     * @return array<string, mixed> Detailed diffs organized by type
     */
    public static function getDetailedStructure(array $diffs): array
    {
        $result = [
            'updates' => [],
            'associations' => [],
            'other' => [],
        ];

        foreach ($diffs as $key => $value) {
            // Include metadata for detailed view
            if (str_starts_with($key, '@')) {
                $result['metadata'][$key] = $value;

                continue;
            }

            if (!\is_array($value)) {
                $result['other'][$key] = $value;

                continue;
            }

            if (\array_key_exists('old', $value) && \array_key_exists('new', $value)) {
                $result['updates'][$key] = [
                    'old' => $value['old'],
                    'new' => $value['new'],
                ];
            } elseif (\array_key_exists('removed', $value) && \array_key_exists('added', $value)) {
                $result['associations'][$key] = [
                    'removed' => $value['removed'] ?? [],
                    'added' => $value['added'] ?? [],
                ];
            } else {
                $result['other'][$key] = $value;
            }
        }

        // Remove empty sections
        return array_filter($result, static fn (mixed $v) => !empty($v));
    }

    /**
     * Format a single value for JSON display.
     *
     * @param mixed $value     The value to format
     * @param int   $maxLength Maximum length of the output
     */
    public static function formatValue(mixed $value, int $maxLength = 100): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            return 'Error encoding value';
        }

        if (mb_strlen($json) > $maxLength) {
            return mb_substr($json, 0, $maxLength - 3).'...';
        }

        return $json;
    }

    /**
     * Detect the type of diff structure.
     *
     * @param array<string, mixed> $diffs The diffs from an audit entry
     *
     * @return string One of: 'update', 'association_change', 'entity_summary', 'association_link', 'unknown'
     */
    public static function detectDiffType(array $diffs): string
    {
        // Filter out metadata
        $data = array_filter($diffs, static fn (string $k): bool => !str_starts_with($k, '@'), ARRAY_FILTER_USE_KEY);

        if ([] === $data) {
            return 'unknown';
        }

        // Check for entity summary (insert/remove operations)
        if (self::isEntitySummary($data)) {
            return 'entity_summary';
        }

        // Check for association link (associate/dissociate operations)
        if (self::isAssociationLink($data)) {
            return 'association_link';
        }

        // Check for field updates or association changes
        foreach ($data as $value) {
            if (\is_array($value)) {
                if (\array_key_exists('old', $value) || \array_key_exists('new', $value)) {
                    return 'update';
                }
                if (\array_key_exists('removed', $value) || \array_key_exists('added', $value)) {
                    return 'association_change';
                }
            }
        }

        return 'unknown';
    }

    /**
     * Check if this is an entity summary (insert/remove operations).
     *
     * Entity summaries have a flat structure with class, label, id, and table keys.
     *
     * @param array<string, mixed> $diffs The diffs from an audit entry
     */
    public static function isEntitySummary(array $diffs): bool
    {
        return isset($diffs['class']) && isset($diffs['label']);
    }

    /**
     * Check if this is an association link (associate/dissociate operations).
     *
     * Association links have source, target, and is_owning_side keys.
     *
     * @param array<string, mixed> $diffs The diffs from an audit entry
     */
    public static function isAssociationLink(array $diffs): bool
    {
        return isset($diffs['source'])
            && isset($diffs['target'])
            && \array_key_exists('is_owning_side', $diffs);
    }

    /**
     * Format entity summary for display.
     *
     * @param array<string, mixed> $diffs The diffs from an audit entry
     *
     * @return array{label: string, class: string, id: mixed, shortClass: string}
     */
    public static function formatEntitySummary(array $diffs): array
    {
        $class = isset($diffs['class']) && \is_string($diffs['class']) ? $diffs['class'] : '';
        $parts = explode('\\', $class);
        $shortClass = end($parts) ?: $class;

        $pkName = isset($diffs['pkName']) && \is_string($diffs['pkName']) ? $diffs['pkName'] : 'id';

        return [
            'label' => isset($diffs['label']) && \is_string($diffs['label']) ? $diffs['label'] : '',
            'class' => $class,
            'id' => $diffs['id'] ?? $diffs[$pkName] ?? null,
            'shortClass' => $shortClass,
        ];
    }

    /**
     * Format association link for display.
     *
     * @param array<string, mixed> $diffs The diffs from an audit entry
     *
     * @return array{source: string, target: string, sourceClass: string, targetClass: string}
     */
    public static function formatAssociationLink(array $diffs): array
    {
        /** @var array{label?: string, class?: string, id?: mixed} $source */
        $source = $diffs['source'] ?? [];

        /** @var array{label?: string, class?: string, id?: mixed} $target */
        $target = $diffs['target'] ?? [];

        $sourceLabel = $source['label'] ?? self::formatEntityLabel($source);
        $targetLabel = $target['label'] ?? self::formatEntityLabel($target);

        $sourceClass = $source['class'] ?? '';
        $targetClass = $target['class'] ?? '';

        return [
            'source' => $sourceLabel,
            'target' => $targetLabel,
            'sourceClass' => $sourceClass,
            'targetClass' => $targetClass,
        ];
    }

    /**
     * Truncate a value for preview display.
     */
    private static function truncateValue(mixed $value): mixed
    {
        if (\is_string($value) && mb_strlen($value) > 50) {
            return mb_substr($value, 0, 47).'...';
        }

        if (\is_array($value) && \count($value) > 3) {
            $truncated = \array_slice($value, 0, 3);
            $truncated['...'] = '('.(\count($value) - 3).' more)';

            return $truncated;
        }

        return $value;
    }

    /**
     * Format an entity label from its class and id.
     *
     * @param array{class?: string, id?: mixed} $entity
     */
    private static function formatEntityLabel(array $entity): string
    {
        $class = $entity['class'] ?? '';
        $parts = explode('\\', $class);
        $shortClass = end($parts) ?: $class;
        $id = $entity['id'] ?? '?';

        return $shortClass.'#'.$id;
    }
}
