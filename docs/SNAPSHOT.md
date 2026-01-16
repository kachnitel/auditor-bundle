# Snapshots

Reconstruct entity property values at any point in history.

## Snapshot Service

The `Snapshot` service reconstructs entity state by reversing audit diffs from the current state back to a target date.

### Basic Usage

```php
use Kachnitel\AuditorBundle\Service\Snapshot;

// Get stock and price values as they were on a specific date
$historicalData = $snapshot->getPropertiesSnapshot($products, $date, ['stock', 'price']);
// Returns: [productId => ['stock' => 100, 'price' => 29.99], ...]
```

<details>
<summary><strong>Full example</strong></summary>

```php
use Kachnitel\AuditorBundle\Service\Snapshot;

class InventoryReportService
{
    public function __construct(private Snapshot $snapshot) {}

    public function generateHistoricalReport(array $products, \DateTime $date): array
    {
        return $this->snapshot->getPropertiesSnapshot(
            $products,
            $date,
            ['stock', 'price', 'name']
        );
        // Returns: [
        //     42 => ['stock' => 100, 'price' => 29.99, 'name' => 'Widget'],
        //     43 => ['stock' => 50, 'price' => 19.99, 'name' => 'Gadget'],
        // ]
    }

    public function compareStockLevels(array $products, \DateTime $then): array
    {
        $historical = $this->snapshot->getPropertiesSnapshot($products, $then, ['stock']);

        $comparison = [];
        foreach ($products as $product) {
            $comparison[$product->getId()] = [
                'current' => $product->getStock(),
                'historical' => $historical[$product->getId()]['stock'] ?? null,
            ];
        }
        return $comparison;
    }
}
```

</details>

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$entities` | `array` | Array of entity objects |
| `$date` | `\DateTime` | Target date for reconstruction |
| `$properties` | `array` | Property names to reconstruct |

### How It Works

1. Reads current entity property values via reflection
2. Queries all `update` audits from the target date to now
3. Applies diffs in reverse order (newest first)
4. For each diff, restores the `old` value to reconstruct historical state
5. Handles scalar values and collections
6. Skips metadata keys (prefixed with `@`)

### Limitations

| Limitation | Reason |
|------------|--------|
| Only works with `update` audits | Insert audits have no `old` values to restore |
| Removed collection items cannot be restored | No reference to the removed entity available |
| Entity must currently exist | Needs current state as starting point |
| Properties must be audited | Non-audited properties won't have history |

### Use Cases

- Generate historical reports (e.g., "stock levels on December 31st")
- Compare current vs. historical values
- Audit compliance reporting
- Debug when/how values changed
