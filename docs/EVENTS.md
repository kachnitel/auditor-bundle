# Domain Events

Create EVENT-type audit entries for business actions (not just entity field changes).

## EventAuditService

Creates audit entries with `type = 'event'` for domain events like "order.created" or "payment.processed".

### Basic Usage

```php
use Kachnitel\AuditorBundle\Service\EventAuditService;

$eventAudit->createEvent($order, 'order.created', [
    'total' => $order->getTotal(),
    'items_count' => $order->getLineItems()->count(),
]);
```

<details>
<summary><strong>Full example</strong></summary>

```php
use Kachnitel\AuditorBundle\Service\EventAuditService;
use Kachnitel\AuditorBundle\Service\AuditContext;

class OrderService
{
    public function __construct(
        private EventAuditService $eventAudit,
        private AuditContext $auditContext,
        private EntityManagerInterface $em
    ) {}

    public function createOrder(Order $order): void
    {
        $this->auditContext->set(['source' => 'api', 'client_version' => '2.1']);

        $this->em->persist($order);
        $this->em->flush();

        // Record the domain event
        $this->eventAudit->createEvent($order, 'order.created', [
            'total' => $order->getTotal(),
            'items_count' => $order->getLineItems()->count(),
            'customer_id' => $order->getCustomer()->getId(),
        ]);
    }

    public function cancelOrder(Order $order, string $reason): void
    {
        $order->setStatus('cancelled');
        $this->em->flush();

        $this->eventAudit->createEvent($order, 'order.cancelled', [
            'reason' => $reason,
            'refund_amount' => $order->getTotal(),
        ]);
    }
}
```

</details>

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$entity` | `object` | Related entity |
| `$eventName` | `string` | Event identifier (e.g., `order.created`) |
| `$data` | `array` | Custom event data |

### Stored Format

Events are stored in the entity's audit table with `type = 'event'`:

```json
{
    "@event": "order.created",
    "total": 150.00,
    "items_count": 3,
    "customer_id": 42,
    "@context": {"source": "api", "client_version": "2.1"}
}
```

### Audit Entry Fields

| Field | Description |
|-------|-------------|
| `type` | Always `'event'` |
| `object_id` | Related entity's ID |
| `transaction_hash` | Unique hash for the event |
| `diffs` | JSON with `@event` name and custom data |
| `blame_id`, `blame_user` | User who triggered the event |
| `ip` | Client IP address |
| `created_at` | Timestamp |

### Use Cases

- Record business milestones (order created, payment received, shipment dispatched)
- Track workflow transitions that aren't simple field changes
- Capture calculated values at a point in time
- Create audit trail for compliance requirements

### Events vs. Field Changes

| Aspect | Field Change Audit | Domain Event Audit |
|--------|-------------------|-------------------|
| Trigger | Automatic on entity change | Manual via `createEvent()` |
| Type | `insert`, `update`, `delete` | `event` |
| Data | Old/new field values | Custom event payload |
| Use case | Track what changed | Track what happened |
