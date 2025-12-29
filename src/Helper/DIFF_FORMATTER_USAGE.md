# DiffFormatter Usage Guide

The `DiffFormatter` helper provides utilities for formatting and previewing audit change diffs in the admin interface.

## Overview

The formatter transforms raw audit diffs into human-readable previews suitable for display in:
- Table views (inline summaries)
- Detail/modal views (full structured data)

## API Reference

### `DiffFormatter::createPreview(array $diffs): array`

Creates a summary preview of changes for table display.

**Returns:** Array with structure:
```php
[
    'total_changes' => int,
    'changes' => [
        [
            'field' => string,
            'type' => 'update' | 'association',
            'old' => mixed,           // for 'update' type
            'new' => mixed,           // for 'update' type
            'removed_count' => int,   // for 'association' type
            'added_count' => int,     // for 'association' type
        ]
    ]
]
```

**Example:**
```php
use DH\AuditorBundle\Helper\DiffFormatter;

$diffs = $entry->getDiffs();
$preview = DiffFormatter::createPreview($diffs);

foreach ($preview['changes'] as $change) {
    echo $change['field'] . ': ' . $change['type'];
}
```

### `DiffFormatter::getDetailedStructure(array $diffs): array`

Gets structured data organized by change type for detailed views.

**Returns:** Array with keys:
- `updates`: Field value changes (old → new)
- `associations`: Relation changes (added/removed items)
- `other`: Other types of changes
- `metadata`: Metadata fields (@source, @context, etc.)

**Example:**
```php
$detailed = DiffFormatter::getDetailedStructure($entry->getDiffs(includeMedadata: true));

if (!empty($detailed['updates'])) {
    foreach ($detailed['updates'] as $field => $change) {
        echo "$field: {$change['old']} → {$change['new']}";
    }
}
```

### `DiffFormatter::formatValue(mixed $value, int $maxLength = 100): string`

Formats a single value for JSON display with optional truncation.

**Example:**
```php
$formatted = DiffFormatter::formatValue($someValue, 50);
echo $formatted; // "{"key":"value...truncated"}"
```

## Using in AuditDataSource

The `AuditDataSource` provides convenience methods:

```php
class AuditDataSource {
    public function getChangePreview(Entry $entry): array
    public function getDetailedDiffs(Entry $entry): array
}
```

**Example:**
```php
$dataSource = new AuditDataSource($reader, MyEntity::class);
$entry = $dataSource->find(123);

// Get preview for table display
$preview = $dataSource->getChangePreview($entry);

// Get detailed diffs for full view
$detailed = $dataSource->getDetailedDiffs($entry);
```

## Twig Templates

### Table Inline Preview

Use `_changes-preview.html.twig` to show a compact preview in tables:

```twig
{% include 'Admin/Audit/_changes-preview.html.twig' with {entry: auditEntry} %}
```

Displays:
- Edit/Link badges showing count of field updates and relation changes
- First 3 changes inline with truncated values
- "... and X more" indicator if more changes exist

### Detail View

Use `detail.html.twig` for full change display:

```twig
{% extends 'Admin/Audit/detail.html.twig' %}
```

Features:
- Accordion-style collapsed/expandable changes
- Side-by-side old/new values for updates
- Removed/Added lists for relations
- Pretty-printed JSON for complex values
- Bootstrap styling

## Diff Data Structure

Audit diffs follow these patterns:

### Field Update
```php
[
    'fieldName' => [
        'old' => $previousValue,
        'new' => $newValue
    ]
]
```

### Association Change
```php
[
    'relationName' => [
        'removed' => [$removedItem1, $removedItem2],
        'added' => [$addedItem1, $addedItem2]
    ]
]
```

### Metadata
```php
[
    '@source' => [
        'blame_id' => 123,
        'blame_user' => 'john@example.com',
        'ip' => '192.168.1.1',
        'transaction_hash' => 'abc123',
    ],
    '@context' => [
        'notes' => 'User-provided context',
        'reason' => 'Manual correction'
    ]
]
```
