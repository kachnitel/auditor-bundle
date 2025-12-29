<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Helper;

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

            if (!is_array($value)) {
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

            if (array_key_exists('old', $value) && array_key_exists('new', $value)) {
                $changeInfo['type'] = 'update';
                $changeInfo['old'] = self::truncateValue($value['old']);
                $changeInfo['new'] = self::truncateValue($value['new']);
            } elseif (array_key_exists('removed', $value) && array_key_exists('added', $value)) {
                $changeInfo['type'] = 'association';
                $changeInfo['removed_count'] = is_array($value['removed']) ? count($value['removed']) : 0;
                $changeInfo['added_count'] = is_array($value['added']) ? count($value['added']) : 0;
            }

            if ($changeInfo['type'] !== 'unknown') {
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

            if (!is_array($value)) {
                $result['other'][$key] = $value;
                continue;
            }

            if (array_key_exists('old', $value) && array_key_exists('new', $value)) {
                $result['updates'][$key] = [
                    'old' => $value['old'],
                    'new' => $value['new'],
                ];
            } elseif (array_key_exists('removed', $value) && array_key_exists('added', $value)) {
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
     * Truncate a value for preview display.
     */
    private static function truncateValue(mixed $value): mixed
    {
        if (is_string($value) && strlen($value) > 50) {
            return substr($value, 0, 47) . '...';
        }

        if (is_array($value) && count($value) > 3) {
            $truncated = array_slice($value, 0, 3);
            $truncated['...'] = '(' . (count($value) - 3) . ' more)';
            return $truncated;
        }

        return $value;
    }

    /**
     * Format a single value for JSON display.
     *
     * @param mixed $value The value to format
     * @param int $maxLength Maximum length of the output
     */
    public static function formatValue(mixed $value, int $maxLength = 100): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return 'Error encoding value';
        }

        if (strlen($json) > $maxLength) {
            return substr($json, 0, $maxLength - 3) . '...';
        }

        return $json;
    }
}
