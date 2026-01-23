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

## UI Components

Reusable Twig components for displaying audit information. Requires `symfony/ux-twig-component` package.

| Component | Description | Props |
|-----------|-------------|-------|
| `K:Audit:ChangesPreview` | Inline preview with modal for audit changes | `item`, `dataSource` |
| `K:Audit:RowActions` | Row action buttons (request filter, user timeline) | `item`, `dataSource` |
| `K:Audit:TimelineLink` | Link to user timeline view | `item`, `dataSource`, `showLabel?`, `class?` |
| `K:Audit:DiffInlineEntitySummary` | Inline display for insert/remove operations | `diffs` |
| `K:Audit:DiffInlineAssociationLink` | Inline display for associate/dissociate | `diffs` |
| `K:Audit:DiffInlineFieldChanges` | Inline display for field changes | `diffs` |
| `K:Audit:DiffModalEntitySummary` | Modal content for entity operations | `diffs` |
| `K:Audit:DiffModalAssociationLink` | Modal content for associations | `diffs` |
| `K:Audit:DiffModalFieldChanges` | Modal content for field changes | `diffs`, `entryId` |

### Usage Example

```twig
{# Display audit entry with preview and actions #}
<twig:K:Audit:ChangesPreview :item="entry" :dataSource="dataSource" />
<twig:K:Audit:RowActions :item="entry" :dataSource="dataSource" />

{# Timeline link with custom styling #}
<twig:K:Audit:TimelineLink :item="entry" :dataSource="dataSource" showLabel class="btn btn-sm" />
```

## Requirements

```bash
composer require kachnitel/admin-bundle
```

The integration is automatic once admin-bundle is installed.
