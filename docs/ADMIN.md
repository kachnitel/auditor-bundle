# Admin Integration

Browse audit logs with filtering and pagination in `kachnitel/admin-bundle`.

## Overview

When `kachnitel/admin-bundle` is installed, audit data sources are automatically registered. No configuration required.

## Features

- Preview entity modifications in admin list views
- Filter by entity type, date range, user, and operation
- Pagination for browsing large audit logs
- View full audit details including context metadata
- "Timeline" - detailed view to see user actions related to viewed entry

## How It Works

1. `AdminBundleIntegrationPass` detects if admin-bundle is installed
2. Auto-registers `AuditDataSource` and `AuditDataSourceFactory`
3. Admin UI provides filtering and pagination
4. List views can preview changes without navigating to full details

## Components

| Component | Description |
|-----------|-------------|
| `AuditDataSource` | DataSource implementation for audit entries |
| `AuditDataSourceFactory` | Creates data sources for audited entity types |

## Requirements

```bash
composer require kachnitel/admin-bundle
```

The integration is automatic once admin-bundle is installed.
