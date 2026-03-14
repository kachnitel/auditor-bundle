# Context & Metadata

Add metadata to audit entries including notes, reasons, and automatic request ID tracking.

## Table of Contents

- [AuditContext Service](#auditcontext-service)
- [First-Wins Context Protection](#first-wins-context-protection)
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
        // Set context before making changes — this caller owns the context
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

---

## First-Wins Context Protection

When a single HTTP request triggers a chain of service calls (e.g. completing a task that allocates
a product to an order, which updates inventory), multiple services might attempt to set context.
Without protection, the deepest call in the chain would silently overwrite the most meaningful
context — the top-level action that started everything.

`AuditContext` uses **first-wins** semantics: the first caller to set context owns it for the
duration of the request. Later callers are silently no-ops.

### Behaviour per method

| Method | Blocked when | Blocks others |
|---|---|---|
| `set()` | Any primary context already set (by set/setNote/setReason) | Blocks all subsequent `set()`, `setNote()`, `setReason()` |
| `setNote()` | Note key already exists, or `set()` was called | Blocks subsequent `set()` only |
| `setReason()` | Reason key already exists, or `set()` was called | Blocks subsequent `set()` only |
| `setRequestId()` | Never blocked | Never blocks others — infrastructure only |
| `override()` | Never blocked | Resets and re-locks; subsequent `set()` blocked again |

### Example: nested service calls

```php
// TaskController — top-level, owns the context
$auditContext->set(['note' => 'User completed task', 'reason' => 'task_completion']);

// TaskService calls ProductService internally
$productService->allocateToOrder($product, $order); // triggers its own flush

// Inside ProductService — context is already claimed, these are silent no-ops:
$auditContext->set(['note' => 'Allocated to order']);   // ignored
$auditContext->setReason('allocation');                  // ignored

// All audit entries produced by the flush still carry the top-level context:
// "@context": { "note": "User completed task", "reason": "task_completion" }
```

### Helpers can be combined freely by the same caller

`setNote()` and `setReason()` are independent at the key level — one does not block the other:

```php
$auditContext->setNote('Stock corrected');   // claims note key
$auditContext->setReason('inventory_count'); // claims reason key independently
$auditContext->set(['anything' => '...']);   // no-op — helpers have locked primary
```

### Explicit override

When you genuinely need to replace an existing primary context, use `override()`.
The `request_id` is always preserved:

```php
// Admin action that supersedes whatever context was set by the application layer:
$auditContext->override(['note' => 'Admin correction', 'admin_id' => $adminId]);
```

### `hasPrimary()`

Useful for callers that want to contribute context only if no one else has:

```php
if (!$auditContext->hasPrimary()) {
    $auditContext->set(['note' => 'Fallback context']);
}
```

---

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
