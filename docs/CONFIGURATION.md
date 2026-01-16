# Configuration

Full configuration reference for kachnitel/auditor-bundle.

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

<details>
<summary><strong>Full configuration example</strong></summary>

```yaml
kachnitel_auditor:
    enabled: true
    timezone: UTC
    user_provider: kachnitel_auditor.user_provider
    security_provider: kachnitel_auditor.security_provider
    role_checker: kachnitel_auditor.role_checker
    providers:
        doctrine:
            table_prefix: ''
            table_suffix: '_audit'
            storage_services:
                - doctrine.orm.default_entity_manager
            auditing_services:
                - doctrine.orm.default_entity_manager
            storage_mapper: null
            viewer:
                enabled: false
                page_size: 50
            entities:
                App\Entity\Product:
                    enabled: true
                App\Entity\Order:
                    enabled: true
```

</details>

## Root Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Enable/disable auditing globally |
| `timezone` | string | `'UTC'` | Timezone for audit timestamps |
| `user_provider` | string | `'kachnitel_auditor.user_provider'` | Service for user identification |
| `security_provider` | string | `'kachnitel_auditor.security_provider'` | Service for security context |
| `role_checker` | string | `'kachnitel_auditor.role_checker'` | Service for role checking |

## Doctrine Provider Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `table_prefix` | string | `''` | Prefix for audit table names |
| `table_suffix` | string | `'_audit'` | Suffix for audit table names |
| `storage_services` | array | `['doctrine.orm.default_entity_manager']` | Entity managers for storing audits |
| `auditing_services` | array | `['doctrine.orm.default_entity_manager']` | Entity managers to audit |
| `storage_mapper` | string | `null` | Custom storage mapper service |

## Viewer Options (Upstream)

> **Note:** These settings control the original basic viewer from `damienharper/auditor`. For a full-featured audit UI, use `kachnitel/admin-bundle` instead - see [Admin Integration](ADMIN.md).

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `viewer.enabled` | bool | `false` | Enable basic built-in viewer routes |
| `viewer.page_size` | int | `50` | Default page size for viewer |

## Entity Configuration

Entities can be enabled/disabled individually:

```yaml
entities:
    App\Entity\Product:
        enabled: true
    App\Entity\User:
        enabled: false  # Disable auditing for this entity
```

Use `~` or omit `enabled` to use the default (enabled):

```yaml
entities:
    App\Entity\Product: ~  # enabled by default
```

## Custom Providers

Register custom user/security providers:

```yaml
kachnitel_auditor:
    user_provider: App\Audit\CustomUserProvider
    security_provider: App\Audit\CustomSecurityProvider
```

Your providers must implement the appropriate interfaces from `damienharper/auditor`.

## Multiple Entity Managers

For applications with multiple entity managers:

```yaml
kachnitel_auditor:
    providers:
        doctrine:
            storage_services:
                - doctrine.orm.default_entity_manager
                - doctrine.orm.audit_entity_manager
            auditing_services:
                - doctrine.orm.default_entity_manager
```

## Differences from Upstream

This fork uses the `kachnitel_auditor` configuration key instead of `dh_auditor`.

| Upstream | This Fork |
|----------|-----------|
| `dh_auditor:` | `kachnitel_auditor:` |
| `dh_auditor.user_provider` | `kachnitel_auditor.user_provider` |
| `dh_auditor.security_provider` | `kachnitel_auditor.security_provider` |
| `dh_auditor.role_checker` | `kachnitel_auditor.role_checker` |

See [MIGRATION.md](../MIGRATION.md) for migration instructions.
