# Querying Audits

Query audit entries with filters, date ranges, and timeline views.

## Table of Contents

- [AuditReader Service](#auditreader-service)
- [User Timeline](#user-timeline)
- [Request ID Lookup](#request-id-lookup)

## AuditReader Service

Query interface for retrieving audit entries with comprehensive filters.

### Basic Usage

```php
use Kachnitel\AuditorBundle\Service\AuditReader;

// By entity class and IDs
$entries = $reader->findByEntityClass(Product::class, [$productId]);

// With date range
$entries = $reader->findByEntityClass(
    Product::class,
    ids: [$productId],
    from: new \DateTime('-30 days'),
    to: new \DateTime()
);

// Filter by operation type
$entries = $reader->findByEntityClass(
    Product::class,
    ids: [$productId],
    type: 'update'
);
```

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$entityClass` | `string` | Entity FQCN to query |
| `$ids` | `array` | Entity IDs to filter by |
| `$from` | `?\DateTime` | Start of date range |
| `$to` | `?\DateTime` | End of date range |
| `$type` | `?string` | Operation type: `insert`, `update`, `delete`, `associate`, `dissociate`, `event` |

## User Timeline

Query audit entries from the same user within a time window around a reference entry.

### Basic Usage

```php
// Get all audits by the same user within 5 minutes (default)
$timeline = $reader->findUserTimeline($referenceEntry);

// Custom time window
$timeline = $reader->findUserTimeline($referenceEntry, windowMinutes: 10);

// Include system events (async jobs, commands with no user)
$timeline = $reader->findUserTimeline(
    $referenceEntry,
    windowMinutes: 5,
    includeSystemEvents: true
);
```

<details>
<summary><strong>Full example</strong></summary>

```php
use Kachnitel\AuditorBundle\Service\AuditReader;
use DH\Auditor\Model\Entry;

class AuditController
{
    public function viewUserTimeline(AuditReader $reader, Entry $referenceEntry): array
    {
        $timeline = $reader->findUserTimeline(
            $referenceEntry,
            windowMinutes: 5,
            includeSystemEvents: true
        );

        // Returns grouped by entity class:
        // ['App\Entity\Order' => [Entry, ...], 'App\Entity\Product' => [Entry, ...]]
        return $timeline;
    }
}
```

</details>

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$referenceEntry` | `Entry` | required | Entry to build timeline around |
| `$windowMinutes` | `int` | `5` | Minutes before and after to include |
| `$includeSystemEvents` | `bool` | `false` | Include audits with no user |

### Behavior

- Groups results by entity class
- Orders entries chronologically (oldest first)
- If reference entry has no user, shows only system events in the time range

### Use Cases

- Understand the full context of a change
- Debug complex workflows involving multiple entities
- Investigate what else a user did around a suspicious action

## Request ID Lookup

Find all audits from the same HTTP request.

```php
$timeline = $reader->findByRequestId($requestId);
// Returns: ['App\Entity\Order' => [Entry, ...], 'App\Entity\Product' => [Entry, ...]]
```

See [Context & Metadata](CONTEXT.md#request-id-tracking) for how request IDs are captured.
