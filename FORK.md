# Fork Extensions for auditor-bundle

This is a fork of [DamienHarper/auditor-bundle](https://github.com/DamienHarper/auditor-bundle)
with additional features for audit context, snapshots, and admin integration.

## Added Features

### 1. AuditContext - Request-Scoped Context Service

Request-scoped service for adding metadata (notes, reasons) to audit entries.

**Location:** `src/Service/AuditContext.php`

<details>
<summary><strong>View usage example</strong></summary>

**Use Case:** When adjusting stock, record why the adjustment was made.

**Usage:**
```php
use DH\AuditorBundle\Service\AuditContext;

class ProductController
{
    public function adjustStock(AuditContext $auditContext, Product $product): Response
    {
        // Set context before making changes
        $auditContext->set([
            'note' => 'Manual correction after inventory count',
            'reason' => 'inventory_count',
            'adjustment' => -5,
        ]);

        // Make the change - context is automatically included in the audit
        $product->setStock(95);
        $this->entityManager->flush();

        // Context is automatically cleared after the request
    }
}
```

**Storage:** Context is stored in the `diffs` JSON field under the `@context` key:
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

</details>

### 2. AuditContextSubscriber - Event Integration

Automatically injects the AuditContext into audit payloads via LifecycleEvent.

**Location:** `src/Event/AuditContextSubscriber.php`

**How it works:**
1. Subscribes to `DH\Auditor\Event\LifecycleEvent` with priority -500,000
2. Runs before `AuditEventSubscriber` (priority -1,000,000) which persists the audit
3. Decodes the `diffs` JSON, adds `@context` key, re-encodes
4. Context applies to all entities flushed in the same transaction

### 3. EventAuditService - Domain Event Auditing

Creates EVENT-type audit entries for domain events (not just entity changes).

**Location:** `src/Service/EventAuditService.php`

<details>
<summary><strong>View usage example</strong></summary>

**Use Case:** Record domain events like "order.created", "task.completed" that represent
business actions rather than simple field changes.

**Usage:**
```php
use DH\AuditorBundle\Service\EventAuditService;

class OrderService
{
    public function createOrder(EventAuditService $eventAudit, Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Record the domain event
        $eventAudit->createEvent($order, 'order.created', [
            'total' => $order->getTotal(),
            'items_count' => $order->getLineItems()->count(),
        ]);
    }
}
```

**Storage:** Events are stored in the entity's audit table with `type = 'event'`:
```json
{
    "@event": "order.created",
    "total": 150.00,
    "items_count": 3,
    "@context": {"note": "Created via API"}
}
```

**Audit Entry Fields:**
- `type`: Always `'event'` for domain events
- `object_id`: The related entity's ID
- `transaction_hash`: Unique hash for the event
- `diffs`: JSON containing `@event` name and custom data
- `blame_id`, `blame_user`: User who triggered the event
- `ip`: Client IP address
- `created_at`: Timestamp

</details>

### 4. AuditReader - Query Interface for Audit Entries

Query interface for retrieving and filtering audit entries.

**Location:** `src/Service/AuditReader.php`

<details>
<summary><strong>View usage example</strong></summary>

**Use Case:** Query audit history with filters for entity type, IDs, date ranges, and operations.

**Usage:**
```php
use DH\AuditorBundle\Service\AuditReader;

class AuditController
{
    public function getProductHistory(AuditReader $reader, Product $product): array
    {
        // Get all audit entries for a specific product
        return $reader->getAudits(Product::class, [$product->getId()]);

        // With date range filter
        return $reader->getAudits(
            Product::class,
            [$product->getId()],
            startDate: new \DateTime('-30 days'),
            endDate: new \DateTime()
        );

        // Filter by operation type
        return $reader->getAudits(
            Product::class,
            [$product->getId()],
            operations: ['update']
        );
    }
}
```

</details>

### 5. Snapshot - Point-in-Time Entity Reconstruction

Reconstructs entity property values at any point in history.

**Location:** `src/Service/Snapshot.php`

<details>
<summary><strong>View usage example</strong></summary>

**Use Case:** Generate historical reports showing what values entities had at a specific date.

**Usage:**
```php
use DH\AuditorBundle\Service\Snapshot;

class InventoryReportService
{
    public function generateHistoricalReport(Snapshot $snapshot, array $products, \DateTime $date): array
    {
        // Get stock and price values as they were on the specified date
        $historicalData = $snapshot->getPropertiesSnapshot(
            $products,
            $date,
            ['stock', 'price']
        );

        // Returns: [productId => ['stock' => 100, 'price' => 29.99], ...]
        return $historicalData;
    }
}
```

**How it works:**
1. Reads current entity property values
2. Queries all `update` audits from the target date to now
3. Applies diffs in reverse order (newest first) to reconstruct historical state
4. Handles scalar values and collections
5. Skips metadata keys (prefixed with `@`)

**Limitations:**
- Only works with `update` audits (inserts have no `old` values)
- Removed collection items cannot be restored (no reference available)
- Entity must currently exist in the database

</details>

### 6. Admin Bundle Integration

Automatic integration with `kachna/admin-bundle` for viewing audit logs.

**Location:** `src/Admin/`, `src/DependencyInjection/AdminBundleIntegrationPass.php`

<details>
<summary><strong>View details</strong></summary>

**Features:**
- **Preview Changes**: View entity modifications directly in admin list views with before/after comparison
- **Audit Data Sources**: Auto-registered admin resources when admin-bundle is installed
- **Filtering & Pagination**: Browse audit logs with comprehensive filtering options

**How it works:**
1. `AdminBundleIntegrationPass` detects if admin-bundle is installed
2. Auto-registers `AuditDataSource` and `AuditDataSourceFactory`
3. Admin UI provides filtering by entity type, date range, user, and operation
4. List views can preview changes before navigating to full audit details

</details>

## Service Registration

All services are automatically registered via `src/Resources/config/services.yaml` and available for dependency injection.

## Requirements

Based on upstream v6.x (master branch):
- PHP >= 8.2
- Symfony >= 6.4
- Doctrine ORM >= 3.1

## Testing

Unit tests are provided in `tests/`:
- `tests/Service/AuditContextTest.php` - AuditContext unit tests
- `tests/Event/AuditContextSubscriberTest.php` - Subscriber integration tests
