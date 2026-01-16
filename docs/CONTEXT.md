# Context & Metadata

Add metadata to audit entries including notes, reasons, and automatic request ID tracking.

## Table of Contents

- [AuditContext Service](#auditcontext-service)
- [Request ID Tracking](#request-id-tracking)

## AuditContext Service

Request-scoped service for adding metadata to audit entries. Context is stored in the `diffs` JSON under `@context`.

### Basic Usage

```php
use Kachnitel\AuditorBundle\Service\AuditContext;

public function adjustStock(AuditContext $auditContext, Product $product): void
{
    $auditContext->set(['note' => 'Manual correction', 'reason' => 'inventory_count']);
    $product->setStock(95);
    $this->em->flush();
}
```

<details>
<summary><strong>Full example</strong></summary>

```php
use Kachnitel\AuditorBundle\Service\AuditContext;

class ProductController
{
    public function adjustStock(
        AuditContext $auditContext,
        EntityManagerInterface $em,
        Product $product
    ): Response {
        // Set context before making changes
        $auditContext->set([
            'note' => 'Manual correction after inventory count',
            'reason' => 'inventory_count',
            'adjustment' => -5,
        ]);

        $product->setStock(95);
        $em->flush();

        // Context is automatically cleared after the request
    }
}
```

</details>

### Stored Format

Context is stored in the `diffs` JSON field under the `@context` key:

```json
{
    "stock": {"old": 100, "new": 95},
    "@context": {
        "note": "Manual correction after inventory count",
        "reason": "inventory_count",
        "adjustment": -5
    }
}
```

### How It Works

1. `AuditContextSubscriber` subscribes to `DH\Auditor\Event\LifecycleEvent` (priority -500,000)
2. Runs before `AuditEventSubscriber` (priority -1,000,000) which persists the audit
3. Decodes the `diffs` JSON, adds `@context` key, re-encodes
4. Context applies to all entities flushed in the same transaction

## Request ID Tracking

Automatically captures `X-Request-Id` header (or generates UUID v4) to correlate all audits created during the same HTTP request.

### Basic Usage

```php
// Find all audits from the same HTTP request
$timeline = $reader->findByRequestId($requestId);
// Returns: ['App\Entity\Order' => [Entry, ...], 'App\Entity\Product' => [Entry, ...]]
```

### Extracting Request ID

```php
$entry = $audits[0];
$diffs = $entry->getDiffs(includeMedadata: true);
$requestId = $diffs['@context']['request_id'] ?? null;
```

### Stored Format

```json
{
    "status": {"old": "pending", "new": "confirmed"},
    "@context": {
        "request_id": "550e8400-e29b-41d4-a716-446655440000"
    }
}
```

### How It Works

1. `RequestIdSubscriber` runs on `kernel.request` (priority 255)
2. Captures `X-Request-Id` header or generates UUID v4
3. Stores via `AuditContext->setRequestId()`
4. All audits in the request include the ID in `@context.request_id`

### Use Cases

- Correlate multiple entity changes from a single API call
- Debug which changes happened together
- Link audit entries to external request tracking systems
