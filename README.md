# kachnitel/auditor-bundle

Fork of [DamienHarper/auditor-bundle](https://github.com/DamienHarper/auditor-bundle) with additional features for audit context, request tracking, snapshots, and admin integration.

## Documentation

- [Context & Metadata](docs/CONTEXT.md) - Add notes, reasons, and request IDs to audits
- [Querying Audits](docs/READER.md) - Query audit entries with filters and timeline views
- [Snapshots](docs/SNAPSHOT.md) - Reconstruct entity state at any point in history
- [Domain Events](docs/EVENTS.md) - Create EVENT-type audits for business actions
- [Admin Integration](docs/ADMIN.md) - Browse audit logs in kachnitel/admin-bundle
- [Configuration](docs/CONFIGURATION.md) - Full configuration reference

For core auditing functionality, see the [original documentation](https://damienharper.github.io/auditor-docs/docs/auditor-bundle/index.html).

## Features

| Feature | Description |
|---------|-------------|
| AuditContext | Add metadata (notes, reasons) to audit entries |
| Request ID Tracking | Correlate audits from the same HTTP request |
| User Timeline | View related user activity around an audit entry |
| AuditReader | Query audit entries with comprehensive filters |
| Snapshot | Reconstruct entity state at any point in history |
| EventAuditService | Create EVENT-type audits for domain events |
| Admin Integration | Browse audit logs in kachnitel/admin-bundle |

## Installation

```bash
composer require kachnitel/auditor-bundle
```

## Quick Start

```yaml
# config/packages/kachnitel_auditor.yaml
kachnitel_auditor:
    providers:
        doctrine:
            entities:
                App\Entity\Product: ~
                App\Entity\Order: ~
```

Once configured, any database change affecting audited entities is automatically logged to `*_audit` tables.

```php
// Add context to audit entries
public function adjustStock(AuditContext $auditContext, Product $product): void
{
    $auditContext->set(['note' => 'Manual correction', 'reason' => 'inventory_count']);
    $product->setStock(95);
    $this->em->flush();
}

// Query audit history
$entries = $reader->findByEntityClass(Product::class, [$productId]);

// Get historical state
$historicalData = $snapshot->getPropertiesSnapshot($products, $date, ['stock', 'price']);
```

## Requirements

- PHP >= 8.2
- Symfony >= 5.4
- Doctrine ORM >= 3.1

## Migration

Migrating from `damienharper/auditor-bundle`? See [MIGRATION.md](MIGRATION.md).

## License

MIT - see [LICENSE](LICENSE)
